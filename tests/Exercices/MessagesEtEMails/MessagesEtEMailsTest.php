<?php

namespace App\Tests\Exercices\MessagesEtEMails;

use App\Entity\Materiel;
use App\Entity\Ticket;
use App\Entity\Utilisateur;
use App\Message\MaterielAffecteNotification;
use App\Message\TicketAffecte;
use App\Tests\Exercices\ExerciceTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Transport\InMemoryTransport;

/**
 * Chaque test crée ses propres utilisateurs (noms et adresses fictifs) et se connecte par loginUser().
 * Aucun accès réseau : le transport « async » est en mémoire (MESSENGER_TRANSPORT_DSN=in-memory:// dans .env.test)
 * et les e-mails partent vers null://null. Le client redémarre le noyau avant chaque requête (sauf la première) : le
 * transport en mémoire repart donc de vide, et chaque test lit les messages posés par la DERNIÈRE requête, avant
 * la suivante. Le traitement des messages se fait dans un NOYAU NEUF, comme dans le processus à part de
 * `messenger:consume` : aucun contexte de requête n'y traîne.
 */
class MessagesEtEMailsTest extends ExerciceTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient();
        $this->em = static::getContainer()->get('doctrine')->getManager();
    }

    // ---- Démonstration : un message et un e-mail à l'affectation d'un matériel ----------------------

    public function testLeMessageNePorteQueDesIdentifiants(): void
    {
        $message = new MaterielAffecteNotification(12, 34);

        self::assertSame(12, $message->getMaterielId());
        self::assertSame(34, $message->getUtilisateurId());
    }

    public function testAffecterUnMaterielPoseUnMessageDansLeTransportEtN_EnvoieRienTouteDeSuite(): void
    {
        $ada = $this->creerUtilisateur('Ada');
        $materiel = $this->creerMateriel('Imprimante');
        $this->client->loginUser($this->creerUtilisateur('Gestionnaire', ['ROLE_GESTIONNAIRE']));

        $this->affecterLeMateriel($materiel, $ada);

        $messages = $this->messagesEnvoyes();
        self::assertCount(1, $messages);
        self::assertInstanceOf(MaterielAffecteNotification::class, $messages[0]);
        self::assertSame($materiel->getId(), $messages[0]->getMaterielId());
        self::assertSame($ada->getId(), $messages[0]->getUtilisateurId());
        self::assertEmailCount(0, null, 'Le message attend dans la file : le gestionnaire du message n\'a pas encore tourné.');
    }

    public function testAucunMessageSiLAffectationNeChangePasOuSiLeMaterielRetourneAPersonne(): void
    {
        $ada = $this->creerUtilisateur('Ada');
        $materiel = $this->creerMateriel('Imprimante', $ada);
        $this->client->loginUser($this->creerUtilisateur('Gestionnaire', ['ROLE_GESTIONNAIRE']));

        $this->affecterLeMateriel($materiel, $ada);
        self::assertCount(0, $this->messagesEnvoyes(), 'Même affectation : rien à annoncer.');

        $this->affecterLeMateriel($materiel, null);
        self::assertCount(0, $this->messagesEnvoyes(), 'Retour à personne : personne à prévenir.');
    }

    public function testUnMaterielCreeDejaAffecteEnvoieUnMessage(): void
    {
        $ada = $this->creerUtilisateur('Ada');
        $this->client->loginUser($this->creerUtilisateur('Gestionnaire', ['ROLE_GESTIONNAIRE']));

        $this->client->request('GET', '/materiels/nouveau');
        $this->client->submitForm('Enregistrer', ['materiel[nom]' => 'Écran', 'materiel[categorie]' => 'Informatique', 'materiel[utilisateur]' => (string) $ada->getId()]);

        self::assertCount(1, $this->messagesEnvoyes());
    }

    public function testLeMessageDeLaDemonstrationEstAcheminePuisTraite(): void
    {
        $ada = $this->creerUtilisateur('Ada');
        $materiel = $this->creerMateriel('Imprimante');
        $this->client->loginUser($this->creerUtilisateur('Gestionnaire', ['ROLE_GESTIONNAIRE']));
        $this->affecterLeMateriel($materiel, $ada);

        self::assertSame(1, $this->traiterLaFile());

        self::assertEmailCount(1);
        $courriel = self::getMailerMessage(0);
        self::assertEmailAddressContains($courriel, 'To', 'ada@exemple.test');
        self::assertEmailAddressContains($courriel, 'From', static::getContainer()->getParameter('app.expediteur'));
        self::assertStringContainsString('Imprimante', $courriel->getSubject());
        self::assertEmailTextBodyContains($courriel, 'Imprimante');
        self::assertEmailTextBodyContains($courriel, 'Ada');
        self::assertEmailHtmlBodyContains($courriel, 'Imprimante');
        self::assertEmailTextBodyContains($courriel, static::getContainer()->getParameter('app.url_publique').'/materiels/'.$materiel->getId(), "Le lien est ABSOLU : l'e-mail est écrit hors de toute requête.");
    }

    public function testUnMessageEnvoyeAuBusVaDansLaFileEtNEstPasTraiteSurLeChamp(): void
    {
        $ada = $this->creerUtilisateur('Ada');
        $materiel = $this->creerMateriel('Imprimante');

        static::getContainer()->get(MessageBusInterface::class)->dispatch(new MaterielAffecteNotification($materiel->getId(), $ada->getId()));

        self::assertCount(1, $this->messagesEnvoyes(), 'Le routage envoie ce message vers le transport « async ».');
        self::assertEmailCount(0);
    }

    public function testLeGestionnaireDuMessageIgnoreUnMaterielOuUnUtilisateurSupprime(): void
    {
        $ada = $this->creerUtilisateur('Ada');
        $materiel = $this->creerMateriel('Imprimante', $ada);

        $this->demarrerLeProcessusDeLaFile();
        $this->traiter(new MaterielAffecteNotification(9999, 9999));
        // Un seul des deux a disparu : le matériel existe (et est bien affecté à Ada), l'utilisateur du message non.
        $this->traiter(new MaterielAffecteNotification($materiel->getId(), 9999));
        // Et l'inverse : l'utilisateur existe, le matériel du message non.
        $this->traiter(new MaterielAffecteNotification(9999, $ada->getId()));

        self::assertEmailCount(0);
    }

    // ---- Exercice : le technicien d'un ticket, le message TicketAffecte et son e-mail ---------------

    public function testLeMessageTicketAffecteNePorteQueDesIdentifiants(): void
    {
        $message = new TicketAffecte(5, 6);

        self::assertSame(5, $message->getTicketId());
        self::assertSame(6, $message->getTechnicienId());
    }

    public function testLeFormulaireProposeLesTechniciensSeulement(): void
    {
        $this->creerUtilisateur('Grace', ['ROLE_TECHNICIEN']);
        $this->creerUtilisateur('Ada', ['ROLE_TECHNICIEN', 'ROLE_USER']);
        $this->creerUtilisateur('Simple');
        $this->creerUtilisateur('Chef', ['ROLE_GESTIONNAIRE']);
        $ticket = $this->creerTicket('Imprimante en panne');
        $this->client->loginUser($this->creerUtilisateur('Gestionnaire', ['ROLE_GESTIONNAIRE']));

        $crawler = $this->client->request('GET', '/tickets/'.$ticket->getId().'/modifier');

        self::assertResponseIsSuccessful();
        $choix = $crawler->filter('select[name="ticket[technicien]"] option')->each(static fn ($option): string => $option->text());
        self::assertSame(['Personne', 'Ada', 'Grace'], $choix, 'Les techniciens, par ordre alphabétique, et « Personne » : ni l\'utilisateur simple ni le gestionnaire (dont le rôle enregistré n\'est pas ROLE_TECHNICIEN).');
    }

    public function testLeChampTechnicienEstReserveAuGestionnaire(): void
    {
        $ticket = $this->creerTicket('Imprimante en panne');
        $technicien = $this->creerUtilisateur('Ada', ['ROLE_TECHNICIEN']);
        $ticket->setTechnicien($technicien);
        $this->em->flush();

        $this->client->loginUser($technicien);
        $crawler = $this->client->request('GET', '/tickets/'.$ticket->getId().'/modifier');
        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('select[name="ticket[technicien]"]'), 'Un technicien ne choisit pas le technicien.');

        $simple = $this->creerUtilisateur('Simple');
        $sien = $this->creerMateriel('Portable', $simple);
        $this->client->loginUser($simple);
        $crawler = $this->client->request('GET', '/materiels/'.$sien->getId().'/tickets/nouveau');
        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('select[name="ticket[technicien]"]'), 'Un utilisateur simple non plus : il signale un incident sur son matériel, sans choisir le technicien.');

        $this->client->loginUser($this->creerUtilisateur('Gestionnaire', ['ROLE_GESTIONNAIRE']));
        $crawler = $this->client->request('GET', '/materiels/'.$ticket->getMateriel()->getId().'/tickets/nouveau');
        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('select[name="ticket[technicien]"]'), 'Le gestionnaire le choisit, aussi à la création.');
    }

    public function testUnTechnicienNePeutPasChangerLeTechnicienEnForcantLeFormulaire(): void
    {
        $ada = $this->creerUtilisateur('Ada', ['ROLE_TECHNICIEN']);
        $grace = $this->creerUtilisateur('Grace', ['ROLE_TECHNICIEN']);
        $ticket = $this->creerTicket('Imprimante en panne');
        $ticket->setTechnicien($ada);
        $this->em->flush();
        $this->client->loginUser($ada);

        $crawler = $this->client->request('GET', '/tickets/'.$ticket->getId().'/modifier');
        $formulaire = $crawler->selectButton('Enregistrer')->form();
        $valeurs = $formulaire->getPhpValues();
        $valeurs['ticket']['technicien'] = (string) $grace->getId();
        $this->client->request('POST', $formulaire->getUri(), $valeurs);

        $this->em->clear();
        self::assertSame($ada->getId(), $this->em->getRepository(Ticket::class)->find($ticket->getId())->getTechnicien()->getId(), 'Le technicien n\'a pas changé.');
        self::assertCount(0, $this->messagesEnvoyes());
    }

    public function testUnUtilisateurQuiNEstPasTechnicienEstRefuseParLeFormulaire(): void
    {
        $simple = $this->creerUtilisateur('Simple');
        $ticket = $this->creerTicket('Imprimante en panne');
        $this->client->loginUser($this->creerUtilisateur('Gestionnaire', ['ROLE_GESTIONNAIRE']));

        $crawler = $this->client->request('GET', '/tickets/'.$ticket->getId().'/modifier');
        $formulaire = $crawler->selectButton('Enregistrer')->form();
        $valeurs = $formulaire->getPhpValues();
        $valeurs['ticket']['technicien'] = (string) $simple->getId();
        $this->client->request('POST', $formulaire->getUri(), $valeurs);

        self::assertResponseStatusCodeSame(422, 'Le formulaire est réaffiché avec son erreur.');
        self::assertCount(0, $this->messagesEnvoyes());
        $this->em->clear();
        self::assertNull($this->em->getRepository(Ticket::class)->find($ticket->getId())->getTechnicien());
    }

    public function testAffecterUnTicketPoseUnMessageTicketAffecte(): void
    {
        $ada = $this->creerUtilisateur('Ada', ['ROLE_TECHNICIEN']);
        $ticket = $this->creerTicket('Imprimante en panne');
        $this->client->loginUser($this->creerUtilisateur('Gestionnaire', ['ROLE_GESTIONNAIRE']));

        $this->affecterLeTicket($ticket, $ada);

        $messages = $this->messagesEnvoyes();
        self::assertCount(1, $messages);
        self::assertInstanceOf(TicketAffecte::class, $messages[0]);
        self::assertSame($ticket->getId(), $messages[0]->getTicketId());
        self::assertSame($ada->getId(), $messages[0]->getTechnicienId());
        self::assertEmailCount(0, null, 'Pas d\'e-mail avant le traitement du message.');
        $this->em->clear();
        self::assertSame($ada->getId(), $this->em->getRepository(Ticket::class)->find($ticket->getId())->getTechnicien()->getId(), 'Le technicien est enregistré.');
    }

    public function testUnMessageParChangementDeTechnicienEtAucunSiRienNeChange(): void
    {
        $ada = $this->creerUtilisateur('Ada', ['ROLE_TECHNICIEN']);
        $grace = $this->creerUtilisateur('Grace', ['ROLE_TECHNICIEN']);
        $ticket = $this->creerTicket('Imprimante en panne');
        $this->client->loginUser($this->creerUtilisateur('Gestionnaire', ['ROLE_GESTIONNAIRE']));

        $this->affecterLeTicket($ticket, $ada);
        self::assertCount(1, $this->messagesEnvoyes(), 'Premier technicien : un message.');

        $this->affecterLeTicket($ticket, $ada);
        self::assertCount(0, $this->messagesEnvoyes(), 'Même technicien : aucun message.');

        $this->affecterLeTicket($ticket, $grace);
        self::assertCount(1, $this->messagesEnvoyes(), 'Nouveau technicien : un message.');
        self::assertSame($grace->getId(), $this->messagesEnvoyes()[0]->getTechnicienId());

        $this->affecterLeTicket($ticket, null);
        self::assertCount(0, $this->messagesEnvoyes(), 'Plus de technicien : personne à prévenir.');
    }

    public function testUnTicketCreeAvecUnTechnicienPoseUnMessage(): void
    {
        $ada = $this->creerUtilisateur('Ada', ['ROLE_TECHNICIEN']);
        $materiel = $this->creerMateriel('Imprimante');
        $this->client->loginUser($this->creerUtilisateur('Gestionnaire', ['ROLE_GESTIONNAIRE']));

        $this->client->request('GET', '/materiels/'.$materiel->getId().'/tickets/nouveau');
        $this->client->submitForm('Enregistrer', ['ticket[titre]' => 'Écran noir', 'ticket[description]' => 'Rien ne s\'affiche.', 'ticket[technicien]' => (string) $ada->getId()]);
        self::assertCount(1, $this->messagesEnvoyes());

        $this->client->request('GET', '/materiels/'.$materiel->getId().'/tickets/nouveau');
        $this->client->submitForm('Enregistrer', ['ticket[titre]' => 'Clavier', 'ticket[description]' => 'Une touche manque.']);
        self::assertCount(0, $this->messagesEnvoyes(), 'Sans technicien : aucun message.');
    }

    public function testLeGestionnaireDuMessageEnvoieLEmailAuTechnicien(): void
    {
        $ada = $this->creerUtilisateur('Ada', ['ROLE_TECHNICIEN']);
        $ticket = $this->creerTicket('Imprimante en panne');
        $this->client->loginUser($this->creerUtilisateur('Gestionnaire', ['ROLE_GESTIONNAIRE']));
        $this->affecterLeTicket($ticket, $ada);

        self::assertSame(1, $this->traiterLaFile());

        self::assertEmailCount(1);
        $courriel = self::getMailerMessage(0);
        self::assertEmailHeaderSame($courriel, 'subject', 'Nouveau ticket : Imprimante en panne');
        self::assertEmailAddressContains($courriel, 'To', 'ada@exemple.test');
        self::assertEmailAddressContains($courriel, 'From', static::getContainer()->getParameter('app.expediteur'));
        self::assertEmailTextBodyContains($courriel, 'Imprimante en panne');
        self::assertEmailTextBodyContains($courriel, 'Ada');
        self::assertEmailTextBodyContains($courriel, static::getContainer()->getParameter('app.url_publique').'/tickets/'.$ticket->getId().'/modifier', 'Le lien vers le ticket est absolu.');
        self::assertEmailHtmlBodyContains($courriel, 'Imprimante en panne');
    }

    public function testTraiterUnMessageNeLeRenvoiePasDansLaFile(): void
    {
        $ada = $this->creerUtilisateur('Ada', ['ROLE_TECHNICIEN']);
        $ticket = $this->creerTicket('Imprimante en panne');
        $this->client->loginUser($this->creerUtilisateur('Gestionnaire', ['ROLE_GESTIONNAIRE']));
        $this->affecterLeTicket($ticket, $ada);

        $this->traiterLaFile();

        self::assertCount(0, $this->transport()->getSent(), 'Un message reçu de la file n\'y retourne pas : sinon il bouclerait.');
        self::assertEmailCount(1);
    }

    public function testLeGestionnaireDuMessageIgnoreUnTicketSupprimeOuReaffecte(): void
    {
        $ada = $this->creerUtilisateur('Ada', ['ROLE_TECHNICIEN']);
        $grace = $this->creerUtilisateur('Grace', ['ROLE_TECHNICIEN']);
        $ticket = $this->creerTicket('Imprimante en panne');
        $ticket->setTechnicien($grace);
        $this->em->flush();

        $this->demarrerLeProcessusDeLaFile();
        $this->traiter(new TicketAffecte(9999, $ada->getId()));
        $this->traiter(new TicketAffecte($ticket->getId(), $ada->getId()));

        self::assertEmailCount(0, null, 'Ticket supprimé, ou déjà confié à quelqu\'un d\'autre quand le message est traité : personne n\'est prévenu à tort.');
    }

    // ---- Outils -------------------------------------------------------------------------------------

    /** @param list<string> $roles */
    private function creerUtilisateur(string $nom, array $roles = []): Utilisateur
    {
        $utilisateur = (new Utilisateur())
            ->setNom($nom)
            ->setEmail(strtolower($nom).'@exemple.test')
            ->setRoles($roles);
        $this->em->persist($utilisateur);
        $this->em->flush();

        return $utilisateur;
    }

    private function creerMateriel(string $nom, ?Utilisateur $utilisateur = null): Materiel
    {
        $materiel = (new Materiel())->setNom($nom)->setCategorie('Informatique')->setUtilisateur($utilisateur);
        $this->em->persist($materiel);
        $this->em->flush();

        return $materiel;
    }

    private function creerTicket(string $titre): Ticket
    {
        $ticket = (new Ticket())->setTitre($titre)->setDescription('Description du ticket.')->setMateriel($this->creerMateriel('Imprimante '.$titre));
        $this->em->persist($ticket);
        $this->em->flush();

        return $ticket;
    }

    private function affecterLeMateriel(Materiel $materiel, ?Utilisateur $utilisateur): void
    {
        $this->client->request('GET', '/materiels/'.$materiel->getId().'/modifier');
        $this->client->submitForm('Enregistrer', ['materiel[utilisateur]' => null === $utilisateur ? '' : (string) $utilisateur->getId()]);
        self::assertResponseRedirects('/materiels/'.$materiel->getId());
    }

    private function affecterLeTicket(Ticket $ticket, ?Utilisateur $technicien): void
    {
        $this->client->request('GET', '/tickets/'.$ticket->getId().'/modifier');
        $this->client->submitForm('Enregistrer', ['ticket[technicien]' => null === $technicien ? '' : (string) $technicien->getId()]);
        self::assertResponseRedirects('/materiels/'.$ticket->getMateriel()->getId());
    }

    private function transport(): InMemoryTransport
    {
        return static::getContainer()->get('messenger.transport.async');
    }

    /** @return list<object> les messages posés dans le transport « async » depuis le début du test */
    private function messagesEnvoyes(): array
    {
        return array_map(static fn (Envelope $enveloppe): object => $enveloppe->getMessage(), $this->transport()->getSent());
    }

    /**
     * Traite les messages posés par la dernière requête, comme le ferait `messenger:consume` dans son processus à part :
     * un noyau NEUF (aucun contexte de requête, un routeur au contexte par défaut, un journal d'e-mails vide), qui
     * reçoit les messages tels qu'ils ont voyagé (sérialisés), marqués comme « reçus » (sans ce marquage, le bus les
     * renverrait dans la file au lieu de les traiter).
     *
     * @return int le nombre de messages traités
     */
    private function traiterLaFile(): int
    {
        $messages = array_map(static fn (object $message): object => unserialize(serialize($message)), $this->messagesEnvoyes());
        $this->demarrerLeProcessusDeLaFile();
        foreach ($messages as $message) {
            $this->traiter($message);
        }

        return \count($messages);
    }

    /** Un noyau neuf : c'est ce que voit un processus `messenger:consume` qui démarre. */
    private function demarrerLeProcessusDeLaFile(): void
    {
        static::ensureKernelShutdown();
        static::bootKernel();
        $this->em = static::getContainer()->get('doctrine')->getManager();
    }

    /** Fait traiter un message par le bus du noyau courant, comme s'il venait de la file. */
    private function traiter(object $message): void
    {
        static::getContainer()->get(MessageBusInterface::class)->dispatch(new Envelope($message, [new ReceivedStamp('async')]));
    }
}
