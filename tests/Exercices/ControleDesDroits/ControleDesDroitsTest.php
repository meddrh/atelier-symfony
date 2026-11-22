<?php

namespace App\Tests\Exercices\ControleDesDroits;

use App\Entity\Materiel;
use App\Entity\Ticket;
use App\Entity\Utilisateur;
use App\Tests\Exercices\ExerciceTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * Chaque test crée ses propres utilisateurs (adresses et noms fictifs) et se connecte par loginUser().
 * Le droit est toujours éprouvé par l'appel DIRECT de l'adresse, en GET et en POST : un bouton masqué ne prouve rien.
 */
class ControleDesDroitsTest extends ExerciceTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient();
        $this->em = static::getContainer()->get('doctrine')->getManager();
    }

    // ---- Démonstration : le contrôle des droits sur un matériel -------------------------------------

    public function testLeGestionnaireModifieTousLesMateriels(): void
    {
        $materiel = $this->creerMateriel('Imprimante', $this->creerUtilisateur('autre@exemple.test'));
        $this->client->loginUser($this->creerUtilisateur('gestionnaire@exemple.test', ['ROLE_GESTIONNAIRE']));

        $this->client->request('GET', '/materiels/'.$materiel->getId().'/modifier');
        self::assertResponseIsSuccessful();

        $this->client->request('GET', '/materiels/'.$materiel->getId().'/rebut');
        self::assertResponseIsSuccessful();
    }

    public function testUnTechnicienNeModifiePasUnMateriel(): void
    {
        $materiel = $this->creerMateriel();
        $this->client->loginUser($this->creerUtilisateur('technicien@exemple.test', ['ROLE_TECHNICIEN']));

        $this->client->request('GET', '/materiels/'.$materiel->getId().'/modifier');

        self::assertResponseStatusCodeSame(403);
    }

    public function testLUtilisateurAffecteConsulteSansModifier(): void
    {
        $ada = $this->creerUtilisateur('ada@exemple.test');
        $materiel = $this->creerMateriel('Imprimante', $ada);
        $this->client->loginUser($ada);

        $crawler = $this->client->request('GET', '/materiels/'.$materiel->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Imprimante');
        self::assertCount(0, $crawler->filter('a[href$="/modifier"], a[href$="/rebut"]'), 'Les liens « Modifier » et « Mettre au rebut » sont masqués.');

        // Masqués, mais aussi refusés : l'appel direct donne 403, en GET comme en POST, et ne change rien.
        $this->client->request('GET', '/materiels/'.$materiel->getId().'/modifier');
        self::assertResponseStatusCodeSame(403);
        $this->client->request('POST', '/materiels/'.$materiel->getId().'/modifier', ['materiel' => ['nom' => 'Piraté', 'categorie' => 'X']]);
        self::assertResponseStatusCodeSame(403);
        $this->client->request('POST', '/materiels/'.$materiel->getId().'/rebut', ['mise_au_rebut' => ['motifRebut' => 'Piraté']]);
        self::assertResponseStatusCodeSame(403);

        $this->em->clear();
        $recharge = $this->em->getRepository(Materiel::class)->find($materiel->getId());
        self::assertSame('Imprimante', $recharge->getNom());
        self::assertNull($recharge->getMotifRebut());
    }

    public function testLeGestionnaireVoitLesLiensDeModification(): void
    {
        $materiel = $this->creerMateriel();
        $this->client->loginUser($this->creerUtilisateur('gestionnaire@exemple.test', ['ROLE_GESTIONNAIRE']));

        $crawler = $this->client->request('GET', '/materiels/'.$materiel->getId());

        self::assertCount(1, $crawler->filter('a[href="/materiels/'.$materiel->getId().'/modifier"]'));
        self::assertCount(1, $crawler->filter('a[href="/materiels/'.$materiel->getId().'/rebut"]'));
    }

    public function testUnUtilisateurSimpleNeConsultePasUnMaterielQuiNeLuiEstPasAffecte(): void
    {
        $materiel = $this->creerMateriel('Imprimante', $this->creerUtilisateur('autre@exemple.test'));
        $this->client->loginUser($this->creerUtilisateur('simple@exemple.test'));

        $this->client->request('GET', '/materiels/'.$materiel->getId());

        self::assertResponseStatusCodeSame(403);
    }

    public function testSeulLAdministrateurSupprimeUnMateriel(): void
    {
        $materiel = $this->creerMateriel();

        // Un gestionnaire modifie tout mais ne supprime pas : 403 avant même le jeton CSRF.
        $this->client->loginUser($this->creerUtilisateur('gestionnaire@exemple.test', ['ROLE_GESTIONNAIRE']));
        $this->client->request('POST', '/materiels/'.$materiel->getId().'/supprimer');
        self::assertResponseStatusCodeSame(403);
        $this->em->clear();
        self::assertNotNull($this->em->getRepository(Materiel::class)->find($materiel->getId()));

        // L'administrateur le supprime par le vrai formulaire (avec son jeton CSRF).
        $this->client->loginUser($this->creerUtilisateur('admin@exemple.test', ['ROLE_ADMIN']));
        $this->client->request('GET', '/materiels');
        $this->client->submitForm('Supprimer');
        self::assertResponseRedirects('/materiels');
        $this->em->clear();
        self::assertNull($this->em->getRepository(Materiel::class)->find($materiel->getId()), "L'administrateur supprime.");
    }

    public function testLaListeMasqueLesBoutonsInterdits(): void
    {
        $this->creerMateriel();
        $this->client->loginUser($this->creerUtilisateur('gestionnaire@exemple.test', ['ROLE_GESTIONNAIRE']));

        $crawler = $this->client->request('GET', '/materiels');

        self::assertCount(1, $crawler->filter('a[href$="/modifier"]'), 'Le gestionnaire modifie.');
        self::assertCount(0, $crawler->selectButton('Supprimer'), 'Il ne supprime pas : pas de bouton.');
    }

    public function testUneRegleAllowIfReserveLeResumeDuParcAuxTechniciensEnLecture(): void
    {
        $this->client->loginUser($this->creerUtilisateur('simple@exemple.test'));
        $this->client->request('GET', '/fr/parc');
        self::assertResponseStatusCodeSame(403, 'Un utilisateur simple ne voit pas le résumé du parc.');

        $this->client->loginUser($this->creerUtilisateur('technicien@exemple.test', ['ROLE_TECHNICIEN']));
        $this->client->request('GET', '/fr/parc');
        self::assertResponseIsSuccessful();

        $this->client->request('POST', '/fr/parc');
        self::assertResponseStatusCodeSame(403, 'La règle est en lecture seule : un POST est refusé.');
    }

    // ---- Exercice : le technicien d'un ticket et le contrôle des droits sur les tickets -------------

    public function testUnTicketPeutAvoirUnTechnicienOuNon(): void
    {
        $technicien = $this->creerUtilisateur('technicien@exemple.test', ['ROLE_TECHNICIEN']);
        $sans = $this->creerTicket('Sans technicien');
        $avec = $this->creerTicket('Avec technicien', $technicien);

        $this->em->clear();
        $ticketSans = $this->em->getRepository(Ticket::class)->find($sans->getId());
        $ticketAvec = $this->em->getRepository(Ticket::class)->find($avec->getId());

        self::assertNull($ticketSans->getTechnicien());
        self::assertSame($technicien->getId(), $ticketAvec->getTechnicien()->getId());
    }

    public function testUnTechnicienModifieSonTicketSeulement(): void
    {
        $ada = $this->creerUtilisateur('ada@exemple.test', ['ROLE_TECHNICIEN']);
        $grace = $this->creerUtilisateur('grace@exemple.test', ['ROLE_TECHNICIEN']);
        $sonTicket = $this->creerTicket('À Ada', $ada);
        $ticketDeGrace = $this->creerTicket('À Grace', $grace);
        $ticketLibre = $this->creerTicket('Sans technicien');
        $this->client->loginUser($ada);

        $this->client->request('GET', '/tickets/'.$sonTicket->getId().'/modifier');
        self::assertResponseIsSuccessful();
        $this->client->request('GET', '/tickets/'.$sonTicket->getId().'/cloturer');
        self::assertResponseIsSuccessful();

        foreach ([$ticketDeGrace, $ticketLibre] as $autre) {
            $this->client->request('GET', '/tickets/'.$autre->getId().'/modifier');
            self::assertResponseStatusCodeSame(403, 'GET modifier, ticket « '.$autre->getTitre().' »');
            $this->client->request('GET', '/tickets/'.$autre->getId().'/cloturer');
            self::assertResponseStatusCodeSame(403, 'GET clôturer, ticket « '.$autre->getTitre().' »');
        }
    }

    public function testUnAppelDirectEnPostSurUnTicketDUnAutreEstRefuseAvantToutChangement(): void
    {
        $ada = $this->creerUtilisateur('ada@exemple.test', ['ROLE_TECHNICIEN']);
        $grace = $this->creerUtilisateur('grace@exemple.test', ['ROLE_TECHNICIEN']);
        $ticketDeGrace = $this->creerTicket('À Grace', $grace);
        $this->client->loginUser($ada);

        $this->client->request('POST', '/tickets/'.$ticketDeGrace->getId().'/cloturer', ['cloture_ticket' => ['resolution' => 'Réglé par Ada.']]);
        self::assertResponseStatusCodeSame(403);
        $this->client->request('POST', '/tickets/'.$ticketDeGrace->getId().'/modifier', ['ticket' => ['titre' => 'Piraté', 'description' => 'x']]);
        self::assertResponseStatusCodeSame(403);

        $this->em->clear();
        $recharge = $this->em->getRepository(Ticket::class)->find($ticketDeGrace->getId());
        self::assertSame(Ticket::STATUT_OUVERT, $recharge->getStatut());
        self::assertNull($recharge->getResolution());
        self::assertSame('À Grace', $recharge->getTitre());
    }

    public function testLeGestionnaireModifieTousLesTickets(): void
    {
        $grace = $this->creerUtilisateur('grace@exemple.test', ['ROLE_TECHNICIEN']);
        $ticket = $this->creerTicket('À Grace', $grace);
        $this->client->loginUser($this->creerUtilisateur('gestionnaire@exemple.test', ['ROLE_GESTIONNAIRE']));

        $this->client->request('GET', '/tickets/'.$ticket->getId().'/modifier');

        self::assertResponseIsSuccessful();
    }

    public function testLesBoutonsDesTicketsSontMasquesPourLesTicketsDesAutres(): void
    {
        $ada = $this->creerUtilisateur('ada@exemple.test', ['ROLE_TECHNICIEN']);
        $grace = $this->creerUtilisateur('grace@exemple.test', ['ROLE_TECHNICIEN']);
        $materiel = $this->creerMateriel();
        $sonTicket = $this->creerTicket('À Ada', $ada, $materiel);
        $ticketDeGrace = $this->creerTicket('À Grace', $grace, $materiel);
        // Les objets du test sont rangés dans le gestionnaire d'entités que la requête réutilise : on repart d'une lecture neuve.
        $this->em->clear();
        $this->client->loginUser($ada);

        $crawler = $this->client->request('GET', '/materiels/'.$materiel->getId());

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('a[href="/tickets/'.$sonTicket->getId().'/modifier"]'), 'Son ticket : lien « Modifier ».');
        self::assertCount(1, $crawler->filter('a[href="/tickets/'.$sonTicket->getId().'/cloturer"]'), 'Son ticket : lien « Clôturer ».');
        self::assertCount(0, $crawler->filter('a[href="/tickets/'.$ticketDeGrace->getId().'/modifier"]'), "Le ticket d'un autre : lien masqué.");
        self::assertCount(0, $crawler->filter('a[href="/tickets/'.$ticketDeGrace->getId().'/cloturer"]'));
        self::assertCount(0, $crawler->selectButton('Supprimer'), 'Un technicien ne supprime jamais.');
    }

    public function testSeulLAdministrateurSupprimeUnTicket(): void
    {
        $ada = $this->creerUtilisateur('ada@exemple.test', ['ROLE_TECHNICIEN']);
        $materiel = $this->creerMateriel();
        $ticket = $this->creerTicket('À Ada', $ada, $materiel);

        foreach ([$ada, $this->creerUtilisateur('gestionnaire@exemple.test', ['ROLE_GESTIONNAIRE'])] as $utilisateur) {
            $this->client->loginUser($utilisateur);
            $this->client->request('POST', '/tickets/'.$ticket->getId().'/supprimer');
            self::assertResponseStatusCodeSame(403, $utilisateur->getUserIdentifier().' ne supprime pas, même son propre ticket.');
        }
        $this->em->clear();
        self::assertNotNull($this->em->getRepository(Ticket::class)->find($ticket->getId()));

        // Sans jeton CSRF valide, l'administrateur ne supprime pas non plus.
        $this->client->loginUser($this->creerUtilisateur('admin@exemple.test', ['ROLE_ADMIN']));
        $this->client->request('POST', '/tickets/'.$ticket->getId().'/supprimer', ['_token' => 'faux']);
        $this->em->clear();
        self::assertNotNull($this->em->getRepository(Ticket::class)->find($ticket->getId()), 'Jeton CSRF faux : rien ne change.');

        // Par le vrai formulaire, il supprime.
        $this->client->request('GET', '/materiels/'.$materiel->getId());
        $this->client->submitForm('Supprimer');
        self::assertResponseRedirects('/materiels/'.$materiel->getId());
        $this->em->clear();
        self::assertNull($this->em->getRepository(Ticket::class)->find($ticket->getId()));
    }

    public function testUnCompteActifParDefaut(): void
    {
        self::assertTrue((new Utilisateur())->isActif());

        $enBase = $this->creerUtilisateur('ada@exemple.test');
        $this->em->clear();
        self::assertTrue($this->em->getRepository(Utilisateur::class)->find($enBase->getId())->isActif());
    }

    /**
     * Le test qui prouve la stratégie « unanimous » : avec la stratégie par défaut (« affirmative »), le « oui » de
     * ROLE_ADMIN suffirait à faire passer le compte désactivé. Il faut qu'un seul « non » l'emporte.
     */
    public function testUnCompteDesactiveAvecRoleAdminEstRefuse(): void
    {
        $this->client->loginUser($this->creerUtilisateur('admin-inactif@exemple.test', ['ROLE_ADMIN'], actif: false));

        $this->client->request('GET', '/utilisateurs');

        self::assertResponseStatusCodeSame(403, 'Un compte désactivé est refusé même avec ROLE_ADMIN.');
    }

    public function testLeMemeAdministrateurActifPasse(): void
    {
        $this->client->loginUser($this->creerUtilisateur('admin@exemple.test', ['ROLE_ADMIN']));

        $this->client->request('GET', '/utilisateurs');

        self::assertResponseIsSuccessful();
    }

    public function testUnCompteDesactiveEstRefuseSurTouteLApplication(): void
    {
        $inactif = $this->creerUtilisateur('gestionnaire-inactif@exemple.test', ['ROLE_GESTIONNAIRE'], actif: false);
        $materiel = $this->creerMateriel('Imprimante', $inactif);
        $ticket = $this->creerTicket('Au compte désactivé', $inactif);
        $this->client->loginUser($inactif);

        foreach (['/materiels', '/materiels/'.$materiel->getId(), '/materiels/'.$materiel->getId().'/modifier', '/fr/tickets', '/tickets/'.$ticket->getId().'/modifier', '/mon-compte', '/fr/parc'] as $adresse) {
            $this->client->request('GET', $adresse);
            self::assertResponseStatusCodeSame(403, $adresse);
        }
    }

    public function testUnCompteDesactiveAvecUnJetonValideEstRefuseSurLeFlux(): void
    {
        $inactif = $this->creerUtilisateur('flux-inactif@exemple.test', ['ROLE_TECHNICIEN'], actif: false);
        $inactif->setJetonApi('jeton-du-compte-desactive');
        $actif = $this->creerUtilisateur('flux-actif@exemple.test', ['ROLE_TECHNICIEN']);
        $actif->setJetonApi('jeton-du-compte-actif');
        $this->em->flush();

        $this->client->request('GET', '/flux/mes-tickets', [], [], ['HTTP_X_JETON' => 'jeton-du-compte-actif']);
        self::assertResponseIsSuccessful();

        // Le jeton est valide : l'authentificateur reconnaît le compte. C'est le contrôle des droits qui le refuse.
        $this->client->request('GET', '/flux/mes-tickets', [], [], ['HTTP_X_JETON' => 'jeton-du-compte-desactive']);
        self::assertResponseStatusCodeSame(403, 'Un compte désactivé est refusé, même avec un jeton valide.');
    }

    public function testLaPageDeConnexionResteOuvertePourUnCompteDesactive(): void
    {
        $this->client->loginUser($this->creerUtilisateur('inactif@exemple.test', [], actif: false));

        $this->client->request('GET', '/connexion');

        self::assertResponseIsSuccessful();
    }

    // ---- Parité : créer demande les mêmes droits que modifier --------------------------------------

    public function testUnUtilisateurSimpleNePeutPasCreerDeMateriel(): void
    {
        $this->client->loginUser($this->creerUtilisateur('simple@exemple.test'));

        $this->client->request('GET', '/materiels/nouveau');
        self::assertResponseStatusCodeSame(403, 'Le formulaire de création est refusé.');

        $this->client->request('POST', '/materiels/nouveau', ['materiel' => ['nom' => 'Pirate', 'categorie' => 'Informatique']]);
        self::assertResponseStatusCodeSame(403, 'Un appel direct en POST aussi.');

        $this->em->clear();
        self::assertSame(0, $this->em->getRepository(Materiel::class)->count([]), 'Rien n\'a été enregistré.');
    }

    public function testUnTechnicienNePeutPasCreerDeMaterielEtLeGestionnaireOui(): void
    {
        $this->client->loginUser($this->creerUtilisateur('tech@exemple.test', ['ROLE_TECHNICIEN']));
        $this->client->request('GET', '/materiels/nouveau');
        self::assertResponseStatusCodeSame(403);

        $this->client->loginUser($this->creerUtilisateur('gest@exemple.test', ['ROLE_GESTIONNAIRE']));
        $this->client->request('GET', '/materiels/nouveau');
        self::assertResponseIsSuccessful();
    }

    public function testLeLienAjouterUnMaterielEstMasquePourUnUtilisateurSimple(): void
    {
        $this->client->loginUser($this->creerUtilisateur('simple@exemple.test'));
        $crawler = $this->client->request('GET', '/materiels');
        self::assertCount(0, $crawler->filter('a[href="/materiels/nouveau"]'));

        $this->client->loginUser($this->creerUtilisateur('gest@exemple.test', ['ROLE_GESTIONNAIRE']));
        $crawler = $this->client->request('GET', '/materiels');
        self::assertCount(1, $crawler->filter('a[href="/materiels/nouveau"]'));
    }

    public function testUnUtilisateurSimpleNePeutPasCreerDeTicketSurUnMaterielQuiNeLuiEstPasAffecte(): void
    {
        $materiel = $this->creerMateriel('Imprimante');
        $this->client->loginUser($this->creerUtilisateur('simple@exemple.test'));

        $this->client->request('GET', '/materiels/'.$materiel->getId().'/tickets/nouveau');
        self::assertResponseStatusCodeSame(403);

        $this->client->request('POST', '/materiels/'.$materiel->getId().'/tickets/nouveau', ['ticket' => ['titre' => 'Intrus', 'description' => 'Je n\'ai pas le droit.']]);
        self::assertResponseStatusCodeSame(403);

        $this->em->clear();
        self::assertSame(0, $this->em->getRepository(Ticket::class)->count([]), 'Aucun ticket n\'a été créé.');
    }

    public function testUnUtilisateurSignaleUnIncidentSurSonMaterielEtUnTechnicienSurTous(): void
    {
        $simple = $this->creerUtilisateur('simple@exemple.test');
        $sien = $this->creerMateriel('Portable', $simple);
        $autre = $this->creerMateriel('Imprimante');

        $this->client->loginUser($simple);
        $this->client->request('GET', '/materiels/'.$sien->getId().'/tickets/nouveau');
        self::assertResponseIsSuccessful();

        $this->client->loginUser($this->creerUtilisateur('tech@exemple.test', ['ROLE_TECHNICIEN']));
        $this->client->request('GET', '/materiels/'.$autre->getId().'/tickets/nouveau');
        self::assertResponseIsSuccessful();
    }

    public function testLesActionsDeGestionDesFournisseursSontReservees(): void
    {
        $simple = $this->creerUtilisateur('simple@exemple.test');
        $fournisseur = (new \App\Entity\Fournisseur())->setNom('Atelier Dupont')->setEmail('dupont@exemple.test');
        $this->em->persist($fournisseur);
        $this->em->flush();
        $id = $fournisseur->getId();
        $this->client->loginUser($simple);

        $this->client->request('GET', '/fournisseurs');
        self::assertResponseIsSuccessful('La consultation reste ouverte à tout utilisateur connecté.');
        foreach (['/fournisseurs/nouveau', '/fournisseurs/'.$id.'/modifier'] as $adresse) {
            $this->client->request('GET', $adresse);
            self::assertResponseStatusCodeSame(403, $adresse);
            $this->client->request('POST', $adresse, ['fournisseur' => ['nom' => 'Changé', 'email' => 'x@exemple.test']]);
            self::assertResponseStatusCodeSame(403, 'POST '.$adresse);
        }
        $this->client->request('POST', '/fournisseurs/'.$id.'/supprimer');
        self::assertResponseStatusCodeSame(403);

        $this->client->loginUser($this->creerUtilisateur('gest@exemple.test', ['ROLE_GESTIONNAIRE']));
        $this->client->request('GET', '/fournisseurs/'.$id.'/modifier');
        self::assertResponseIsSuccessful();
        $this->client->request('POST', '/fournisseurs/'.$id.'/supprimer');
        self::assertResponseStatusCodeSame(403, 'Seul l\'administrateur supprime un fournisseur.');

        $this->em->clear();
        $apres = $this->em->getRepository(\App\Entity\Fournisseur::class)->find($id);
        self::assertNotNull($apres);
        self::assertSame('Atelier Dupont', $apres->getNom(), 'Rien n\'a changé.');
    }

    // ---- Outils -------------------------------------------------------------------------------------

    /** @param list<string> $roles */
    private function creerUtilisateur(string $email, array $roles = [], bool $actif = true): Utilisateur
    {
        $utilisateur = (new Utilisateur())->setNom('Utilisateur '.$email)->setEmail($email)->setRoles($roles)->setActif($actif);
        $this->em->persist($utilisateur);
        $this->em->flush();

        return $utilisateur;
    }

    private function creerMateriel(string $nom = 'Imprimante', ?Utilisateur $utilisateur = null): Materiel
    {
        $materiel = (new Materiel())->setNom($nom)->setCategorie('Informatique')->setUtilisateur($utilisateur);
        $this->em->persist($materiel);
        $this->em->flush();

        return $materiel;
    }

    private function creerTicket(string $titre, ?Utilisateur $technicien = null, ?Materiel $materiel = null): Ticket
    {
        $ticket = (new Ticket())
            ->setTitre($titre)
            ->setDescription('Description du ticket.')
            ->setMateriel($materiel ?? $this->creerMateriel())
            ->setTechnicien($technicien);
        $this->em->persist($ticket);
        $this->em->flush();

        return $ticket;
    }
}
