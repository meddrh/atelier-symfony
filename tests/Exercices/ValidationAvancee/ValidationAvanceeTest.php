<?php

namespace App\Tests\Exercices\ValidationAvancee;

use App\Entity\Materiel;
use App\Entity\Ticket;
use App\Entity\Utilisateur;
use App\Tests\Exercices\ExerciceTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Form;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Les formulaires sont retrouvés par le texte de leur bouton et leurs champs par la fin de leur nom
 * (« …[resolution] ») : le nom de TA classe de formulaire n'a pas d'importance.
 */
class ValidationAvanceeTest extends ExerciceTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private ValidatorInterface $validateur;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient();
        $conteneur = static::getContainer();
        $this->em = $conteneur->get('doctrine')->getManager();
        $this->validateur = $conteneur->get(ValidatorInterface::class);
    }

    // ---- Démonstration : le numéro de série et la mise au rebut d'un matériel -----------

    public function testUnNumeroDeSerieBienFormeEstAccepte(): void
    {
        $this->envoyer('/materiels/nouveau', 'Enregistrer', ['nom' => 'Scanner', 'categorie' => 'Informatique', 'numeroSerie' => 'ab12-cd9']);

        self::assertResponseStatusCodeSame(302);
        self::assertSame('AB12-CD9', $this->em->getRepository(Materiel::class)->findOneBy(['nom' => 'Scanner'])->getNumeroSerie());
    }

    public function testUnMaterielSansNumeroDeSerieResteValide(): void
    {
        $this->envoyer('/materiels/nouveau', 'Enregistrer', ['nom' => 'Scanner', 'categorie' => 'Informatique']);

        self::assertResponseStatusCodeSame(302);
    }

    /**
     * @dataProvider numerosInvalides
     */
    public function testUnNumeroDeSerieMalFormeEstRefuseAvecUnMessageEnFrancais(string $saisi): void
    {
        $this->envoyer('/materiels/nouveau', 'Enregistrer', ['nom' => 'Scanner', 'categorie' => 'Informatique', 'numeroSerie' => $saisi]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'n\'est pas valide');
        self::assertSelectorTextContains('body', 'Le numéro de série');
        self::assertSame(0, $this->em->getRepository(Materiel::class)->count([]));
    }

    /** @return iterable<string, array{string}> */
    public function numerosInvalides(): iterable
    {
        yield 'trop court' => ['ab1'];
        yield 'caractères interdits' => ['ab12_cd@9'];
        yield 'commence par un tiret' => ['-ab12cd'];
    }

    public function testLaContrainteNumeroDeSerieVitDansUneClasseDeValidation(): void
    {
        self::assertTrue(class_exists(\App\Validator\NumeroDeSerie::class), 'La contrainte doit être la classe App\Validator\NumeroDeSerie.');
        self::assertTrue(class_exists(\App\Validator\NumeroDeSerieValidator::class), 'Son validateur doit être App\Validator\NumeroDeSerieValidator.');
    }

    public function testLeMessageDeLaViolationContientLaValeurRefusee(): void
    {
        $materiel = (new Materiel())->setNom('Scanner')->setCategorie('Informatique')->setNumeroSerie('ab1');

        $violations = $this->violations($materiel);

        self::assertCount(1, $violations);
        self::assertSame('numeroSerie', $violations[0]->getPropertyPath());
        self::assertStringContainsString('ab1', $violations[0]->getMessage(), 'Le message doit citer la valeur refusée ({{ valeur }}).');
    }

    public function testLaSequenceDeGroupesMontreDAbordLesErreursDeBase(): void
    {
        // Nom vide ET numéro de série invalide : la séquence s'arrête au premier groupe en échec.
        $materiel = (new Materiel())->setCategorie('Informatique')->setNumeroSerie('ab1');

        $chemins = $this->chemins($this->violations($materiel));

        self::assertSame(['nom'], $chemins, 'Tant que le nom est vide, le numéro de série n\'est pas examiné (séquence de groupes).');
    }

    public function testLeNumeroDeSerieEstExamineQuandLesErreursDeBaseSontCorrigees(): void
    {
        $materiel = (new Materiel())->setNom('Scanner')->setCategorie('Informatique')->setNumeroSerie('ab1');

        self::assertSame(['numeroSerie'], $this->chemins($this->violations($materiel)));
    }

    public function testLeFormulaireDeMiseAuRebutDemandeUnMotif(): void
    {
        $materiel = $this->enregistrerMateriel('Écran', 'Informatique');

        $this->client->request('GET', '/materiels/'.$materiel->getId().'/rebut');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form [name$="[motifRebut]"]');
    }

    public function testUneMiseAuRebutSansMotifEstRefusee(): void
    {
        $materiel = $this->enregistrerMateriel('Écran', 'Informatique');

        $this->envoyer('/materiels/'.$materiel->getId().'/rebut', 'Mettre au rebut', ['motifRebut' => '  ']);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'Le motif de mise au rebut est obligatoire.');
        $this->em->clear();
        self::assertNull($this->em->find(Materiel::class, $materiel->getId())->getMotifRebut());
    }

    public function testUneMiseAuRebutAvecMotifEstEnregistree(): void
    {
        $materiel = $this->enregistrerMateriel('Écran', 'Informatique');

        $this->envoyer('/materiels/'.$materiel->getId().'/rebut', 'Mettre au rebut', ['motifRebut' => 'Dalle fissurée']);

        self::assertResponseRedirects('/materiels/'.$materiel->getId());
        $this->em->clear();
        self::assertSame('Dalle fissurée', $this->em->find(Materiel::class, $materiel->getId())->getMotifRebut());

        $this->client->followRedirect();
        self::assertSelectorTextContains('body', 'Dalle fissurée');
    }

    public function testUnMaterielAffecteNePeutPasEtreMisAuRebut(): void
    {
        $alice = (new Utilisateur())->setNom('Alice Martin')->setEmail('alice@exemple.test');
        $this->em->persist($alice);
        $materiel = $this->enregistrerMateriel('Écran', 'Informatique');
        $materiel->setUtilisateur($alice);
        $this->em->flush();

        $this->envoyer('/materiels/'.$materiel->getId().'/rebut', 'Mettre au rebut', ['motifRebut' => 'Dalle fissurée']);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'Un matériel encore affecté à quelqu\'un ne peut pas être mis au rebut.');
        $this->em->clear();
        self::assertNull($this->em->find(Materiel::class, $materiel->getId())->getMotifRebut());
    }

    public function testMettreAuRebutUnMaterielInconnuRenvoie404(): void
    {
        $this->client->request('GET', '/materiels/999/rebut');

        self::assertResponseStatusCodeSame(404);
    }

    public function testLeMotifDeRebutNEstPasExigeAilleurs(): void
    {
        // Le groupe « rebut » ne s'applique qu'à la mise au rebut : créer et modifier un matériel n'exige pas de motif.
        $this->envoyer('/materiels/nouveau', 'Enregistrer', ['nom' => 'Scanner', 'categorie' => 'Informatique']);
        self::assertResponseStatusCodeSame(302);

        $materiel = $this->em->getRepository(Materiel::class)->findOneBy(['nom' => 'Scanner']);
        $this->envoyer('/materiels/'.$materiel->getId().'/modifier', 'Enregistrer', ['categorie' => 'Bureautique']);
        self::assertResponseStatusCodeSame(302);

        self::assertCount(0, $this->validateur->validate($materiel), 'Validé sans groupe, un matériel sans motif de rebut est valide.');
    }

    // ---- Exercice : la clôture d'un ticket ------------------------------------------

    public function testLaPageDeClotureDemandeUneResolution(): void
    {
        $ticket = $this->enregistrerTicket();

        $this->client->request('GET', '/tickets/'.$ticket->getId().'/cloturer');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form [name$="[resolution]"]');
    }

    public function testUneClotureSansResolutionEstRefusee(): void
    {
        $ticket = $this->enregistrerTicket();

        $this->envoyer('/tickets/'.$ticket->getId().'/cloturer', 'Clôturer', ['resolution' => '   ']);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'La résolution est obligatoire pour clôturer un ticket.');
        $this->em->clear();
        $relu = $this->em->find(Ticket::class, $ticket->getId());
        self::assertSame('ouvert', $relu->getStatut());
        self::assertNull($relu->getResoluLe());
    }

    public function testUneClotureAvecResolutionResoutLeTicket(): void
    {
        $ticket = $this->enregistrerTicket();
        $avant = new \DateTimeImmutable('-2 seconds');

        $this->envoyer('/tickets/'.$ticket->getId().'/cloturer', 'Clôturer', ['resolution' => 'Papier retiré, rouleau nettoyé.']);

        self::assertResponseRedirects('/materiels/'.$ticket->getMateriel()->getId());
        $this->em->clear();
        $relu = $this->em->find(Ticket::class, $ticket->getId());
        self::assertSame('resolu', $relu->getStatut());
        self::assertSame('Papier retiré, rouleau nettoyé.', $relu->getResolution());
        self::assertNotNull($relu->getResoluLe());
        self::assertGreaterThanOrEqual($avant, $relu->getResoluLe(), 'La date de résolution est celle de la clôture.');
    }

    public function testCloturerUnTicketInconnuRenvoie404(): void
    {
        $this->client->request('GET', '/tickets/999/cloturer');

        self::assertResponseStatusCodeSame(404);
    }

    public function testLaResolutionNEstPasExigeeAilleurs(): void
    {
        // Un ticket se crée et se modifie sans résolution : l'obligation ne vaut que pour le groupe « cloture ».
        $materiel = $this->enregistrerMateriel('Imprimante', 'Informatique');
        $this->envoyer('/materiels/'.$materiel->getId().'/tickets/nouveau', 'Enregistrer', ['titre' => 'Bourrage papier', 'description' => 'Le papier se coince.']);
        self::assertResponseStatusCodeSame(302);

        $ticket = $this->em->getRepository(Ticket::class)->findOneBy(['titre' => 'Bourrage papier']);
        $this->envoyer('/tickets/'.$ticket->getId().'/modifier', 'Enregistrer', ['description' => 'Le papier se coince encore.']);
        self::assertResponseStatusCodeSame(302);

        self::assertCount(0, $this->validateur->validate($ticket));
        self::assertGreaterThan(0, \count($this->validateur->validate($ticket, null, ['cloture'])), 'Dans le groupe « cloture », la résolution est obligatoire.');
    }

    public function testLaDateDeResolutionNePrecedePasLaCreation(): void
    {
        $ticket = $this->enregistrerTicket();
        $ticket->setResoluLe($ticket->getCreeLe()->modify('-1 day'));

        $violations = $this->violations($ticket);

        self::assertSame(['resoluLe'], $this->chemins($violations));
        self::assertSame('La date de résolution ne peut pas précéder la création du ticket.', $violations[0]->getMessage());
    }

    public function testUneDateDeResolutionApresLaCreationEstValide(): void
    {
        $ticket = $this->enregistrerTicket();
        $ticket->setResoluLe($ticket->getCreeLe()->modify('+1 hour'));

        self::assertCount(0, $this->violations($ticket));
    }

    /**
     * @dataProvider titresRefuses
     */
    public function testUnTitreIllisibleEstRefuseParLaContrainteTitreLisible(string $titre, string $message): void
    {
        $materiel = $this->enregistrerMateriel('Imprimante', 'Informatique');

        $this->envoyer('/materiels/'.$materiel->getId().'/tickets/nouveau', 'Enregistrer', ['titre' => $titre, 'description' => 'Le papier se coince.']);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', $message);
        self::assertSame(0, $this->em->getRepository(Ticket::class)->count([]));
    }

    /** @return iterable<string, array{string, string}> */
    public function titresRefuses(): iterable
    {
        yield 'trop court' => ['Bug', 'Le titre doit contenir au moins 5 caractères.'];
        yield 'tout en majuscules' => ['IMPRIMANTE EN PANNE', 'Le titre ne doit pas être écrit tout en majuscules.'];
    }

    public function testDesTitresLisiblesSontAcceptes(): void
    {
        foreach (['Wi-Fi', 'Écran noir', 'Imprimante HS', 'Bug n° 404'] as $titre) {
            $ticket = (new Ticket())->setTitre($titre)->setDescription('x')->setMateriel($this->enregistrerMateriel('M', 'C'));

            self::assertCount(0, $this->violations($ticket), $titre);
        }
    }

    public function testLaContrainteTitreLisibleVitDansUneClasseDeValidation(): void
    {
        self::assertTrue(class_exists(\App\Validator\TitreLisible::class), 'La contrainte doit être la classe App\Validator\TitreLisible.');
        self::assertTrue(class_exists(\App\Validator\TitreLisibleValidator::class), 'Son validateur doit être App\Validator\TitreLisibleValidator.');
    }

    public function testUnTitreVideNEstSignaleQuUneFois(): void
    {
        $ticket = (new Ticket())->setTitre('')->setDescription('x')->setMateriel($this->enregistrerMateriel('M', 'C'));

        self::assertSame(['titre'], $this->chemins($this->violations($ticket)), 'Un titre vide dit seulement « obligatoire » : la contrainte personnalisée ignore les valeurs vides.');
    }

    public function testLaFicheDuMaterielProposeDeCloturerUnTicketOuvertSeulement(): void
    {
        $materiel = $this->enregistrerMateriel('Imprimante', 'Informatique');
        $ouvert = $this->enregistrerTicket($materiel, 'Bourrage papier');
        $resolu = $this->enregistrerTicket($materiel, 'Écran noir');
        $resolu->setStatut('resolu');
        $this->em->flush();
        // La fiche relit le matériel et ses tickets depuis la base, pas depuis les objets déjà en mémoire.
        $this->em->clear();

        $this->client->request('GET', '/materiels/'.$materiel->getId());

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('a[href="/tickets/'.$ouvert->getId().'/cloturer"]');
        self::assertSelectorNotExists('a[href="/tickets/'.$resolu->getId().'/cloturer"]');
    }

    // ---- Outils de test --------------------------------------------------------------

    /**
     * Ouvre la page, y remplit le formulaire dont le bouton porte ce texte, puis l'envoie.
     * Un champ est désigné par la fin de son nom : « resolution » désigne « cloture_ticket[resolution] ».
     * Les autres champs gardent la valeur que la page leur donne (formulaire prérempli en modification).
     *
     * @param array<string, string> $valeurs
     */
    private function envoyer(string $adresse, string $bouton, array $valeurs): Crawler
    {
        $crawler = $this->client->request('GET', $adresse);
        self::assertResponseIsSuccessful();

        $formulaire = $crawler->selectButton($bouton)->form();
        foreach ($valeurs as $fin => $valeur) {
            $formulaire[$this->nomDuChamp($formulaire, $fin)] = $valeur;
        }

        return $this->client->submit($formulaire);
    }

    private function nomDuChamp(Form $formulaire, string $fin): string
    {
        foreach (array_keys($formulaire->all()) as $nom) {
            if (str_ends_with((string) $nom, '['.$fin.']')) {
                return (string) $nom;
            }
        }
        self::fail(sprintf('Aucun champ « %s » dans le formulaire (champs : %s).', $fin, implode(', ', array_keys($formulaire->all()))));
    }

    private function violations(object $objet): ConstraintViolationListInterface
    {
        return $this->validateur->validate($objet);
    }

    /** @return list<string> */
    private function chemins(ConstraintViolationListInterface $violations): array
    {
        $chemins = [];
        foreach ($violations as $violation) {
            $chemins[] = $violation->getPropertyPath();
        }

        return $chemins;
    }

    private function enregistrerMateriel(string $nom, string $categorie): Materiel
    {
        $materiel = (new Materiel())->setNom($nom)->setCategorie($categorie);
        $this->em->persist($materiel);
        $this->em->flush();

        return $materiel;
    }

    private function enregistrerTicket(?Materiel $materiel = null, string $titre = 'Bourrage papier'): Ticket
    {
        $ticket = (new Ticket())->setTitre($titre)->setDescription('Le papier se coince.')->setMateriel($materiel ?? $this->enregistrerMateriel('Imprimante', 'Informatique'));
        $this->em->persist($ticket);
        $this->em->flush();

        return $ticket;
    }
}
