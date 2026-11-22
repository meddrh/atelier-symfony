<?php

namespace App\Tests\Exercices\Evenements;

use App\Entity\HistoriqueAffectation;
use App\Entity\Materiel;
use App\Entity\Utilisateur;
use App\Event\MaterielAffecteEvent;
use App\Tests\Exercices\ExerciceTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Console\Tester\ApplicationTester;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

/**
 * Chaque test crée ses propres utilisateurs (noms et adresses fictifs) et se connecte par loginUser().
 * Aucun accès réseau : l'événement et son abonné sont éprouvés avec la base de test et le répartiteur du conteneur.
 */
class EvenementsTest extends ExerciceTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient();
        $this->em = static::getContainer()->get('doctrine')->getManager();
    }

    protected function tearDown(): void
    {
        // Le mode maintenance se règle par une variable d'environnement : on la retire pour les tests suivants.
        unset($_SERVER['APP_MAINTENANCE']);

        parent::tearDown();
    }

    // ---- Démonstration : les événements du noyau et de la console -----------------------------------

    public function testChaqueReponseAUnEnTeteXAtelier(): void
    {
        $this->client->request('GET', '/connexion');
        self::assertResponseHeaderSame('X-Atelier', 'parc-informatique');

        $this->client->loginUser($this->creerUtilisateur('Ada', ['ROLE_GESTIONNAIRE']));
        $this->client->request('GET', '/materiels/999');
        self::assertResponseStatusCodeSame(404);
        self::assertResponseHeaderSame('X-Atelier', 'parc-informatique', "L'en-tête est aussi sur les erreurs.");
    }

    public function testLeModeMaintenanceEstInactifParDefaut(): void
    {
        $this->client->request('GET', '/connexion');

        self::assertResponseIsSuccessful();
    }

    public function testLeModeMaintenanceRepondEn503ATouteRequete(): void
    {
        $_SERVER['APP_MAINTENANCE'] = '1';
        static::ensureKernelShutdown();
        $client = static::createClient();

        foreach (['/connexion', '/materiels', '/fr/parc'] as $adresse) {
            $client->request('GET', $adresse);

            self::assertResponseStatusCodeSame(503, $adresse);
            self::assertResponseHasHeader('Retry-After');
            self::assertStringContainsString('Maintenance en cours', $client->getResponse()->getContent());
        }
        self::assertResponseHeaderSame('X-Atelier', 'parc-informatique', "Même la réponse de maintenance passe par l'événement « réponse ».");
    }

    public function testUnMaterielAuRebutNePeutPlusEtreAffecte(): void
    {
        $ada = $this->creerUtilisateur('Ada');
        $materiel = $this->creerMateriel('Scanner', null, 'Hors service');
        $this->client->loginUser($this->creerUtilisateur('Gestionnaire', ['ROLE_GESTIONNAIRE']));

        $this->client->request('GET', '/materiels/'.$materiel->getId().'/modifier');
        $this->client->submitForm('Enregistrer', ['materiel[utilisateur]' => (string) $ada->getId()]);

        self::assertResponseStatusCodeSame(409);
        self::assertSelectorTextContains('h1', 'Matériel indisponible');
        self::assertSelectorTextContains('body', 'Scanner');
        $this->em->clear();
        self::assertNull($this->em->getRepository(Materiel::class)->find($materiel->getId())->getUtilisateur(), 'Rien n\'a été enregistré.');
    }

    public function testUnMaterielAuRebutResteModifiableSansChangerSonAffectation(): void
    {
        $materiel = $this->creerMateriel('Scanner', null, 'Hors service');
        $this->client->loginUser($this->creerUtilisateur('Gestionnaire', ['ROLE_GESTIONNAIRE']));

        $this->client->request('GET', '/materiels/'.$materiel->getId().'/modifier');
        $this->client->submitForm('Enregistrer', ['materiel[nom]' => 'Scanner à plat']);

        self::assertResponseRedirects('/materiels/'.$materiel->getId());
    }

    public function testLesAutresExceptionsGardentLeurPageHabituelle(): void
    {
        $this->client->loginUser($this->creerUtilisateur('Ada', ['ROLE_GESTIONNAIRE']));

        $this->client->request('GET', '/materiels/999');

        self::assertResponseStatusCodeSame(404);
    }

    public function testLaDureeDUneCommandeEstEcriteDansLeJournal(): void
    {
        $journal = static::getContainer()->getParameter('app.fichier_journal');
        clearstatcache();
        $avant = is_file($journal) ? filesize($journal) : 0;

        $application = new Application(static::$kernel);
        $application->setAutoExit(false);
        $testeur = new ApplicationTester($application);
        $testeur->run(['command' => 'app:materiel:importer', 'fichier' => __DIR__.'/../Console/materiels-valides.csv']);

        self::assertSame(0, $testeur->getStatusCode());
        clearstatcache();
        self::assertFileExists($journal);
        $ecrit = (string) file_get_contents($journal, false, null, $avant);
        self::assertMatchesRegularExpression('/app:materiel:importer.*\d+(?:[.,]\d+)?\s*ms/', $ecrit, "Le journal doit dire quelle commande a duré combien de millisecondes.");
    }

    // ---- Exercice : l'historique des affectations ---------------------------------------------------

    public function testUneLigneDHistoriqueSEnregistreAvecSesCinqInformations(): void
    {
        $ada = $this->creerUtilisateur('Ada');
        $grace = $this->creerUtilisateur('Grace');
        $auteur = $this->creerUtilisateur('Gestionnaire', ['ROLE_GESTIONNAIRE']);
        $materiel = $this->creerMateriel('Imprimante');

        $this->creerLigne($materiel, $ada, $grace, '2026-01-02 10:00', $auteur);
        $this->em->clear();

        $ligne = $this->em->getRepository(HistoriqueAffectation::class)->findAll()[0];
        self::assertSame($materiel->getId(), $ligne->getMateriel()->getId());
        self::assertSame('Ada', $ligne->getAncienUtilisateur()->getNom());
        self::assertSame('Grace', $ligne->getNouvelUtilisateur()->getNom());
        self::assertSame('2026-01-02 10:00', $ligne->getFaitLe()->format('Y-m-d H:i'));
        self::assertSame('Gestionnaire', $ligne->getAuteur()->getNom());
    }

    public function testLEvenementPorteLeMaterielEtLesDeuxUtilisateurs(): void
    {
        $ada = $this->creerUtilisateur('Ada');
        $materiel = $this->creerMateriel('Imprimante');

        $evenement = new MaterielAffecteEvent($materiel, null, $ada);

        self::assertSame($materiel, $evenement->getMateriel());
        self::assertNull($evenement->getAncienUtilisateur());
        self::assertSame($ada, $evenement->getNouvelUtilisateur());
    }

    public function testLAbonneEnregistreLaLigneAvecLUtilisateurConnecteCommeAuteur(): void
    {
        $ada = $this->creerUtilisateur('Ada');
        $grace = $this->creerUtilisateur('Grace');
        $auteur = $this->creerUtilisateur('Gestionnaire', ['ROLE_GESTIONNAIRE']);
        $materiel = $this->creerMateriel('Imprimante', $grace);
        static::getContainer()->get('security.token_storage')->setToken(new UsernamePasswordToken($auteur, 'main', $auteur->getRoles()));

        static::getContainer()->get('event_dispatcher')->dispatch(new MaterielAffecteEvent($materiel, $ada, $grace));

        $this->em->clear();
        $lignes = $this->em->getRepository(HistoriqueAffectation::class)->findAll();
        self::assertCount(1, $lignes, "L'abonné enregistre exactement une ligne.");
        self::assertSame('Ada', $lignes[0]->getAncienUtilisateur()->getNom());
        self::assertSame('Grace', $lignes[0]->getNouvelUtilisateur()->getNom());
        self::assertSame('Gestionnaire', $lignes[0]->getAuteur()->getNom(), "L'auteur est l'utilisateur connecté.");
        self::assertEqualsWithDelta(time(), $lignes[0]->getFaitLe()->getTimestamp(), 120, 'faitLe est le moment du changement.');
    }

    public function testChangerLAffectationEnregistreUneLigneParChangement(): void
    {
        $ada = $this->creerUtilisateur('Ada');
        $grace = $this->creerUtilisateur('Grace');
        $gestionnaire = $this->creerUtilisateur('Gestionnaire', ['ROLE_GESTIONNAIRE']);
        $materiel = $this->creerMateriel('Imprimante');
        $this->client->loginUser($gestionnaire);

        $this->modifierLAffectation($materiel, $ada);
        $this->modifierLAffectation($materiel, $grace);
        $this->modifierLAffectation($materiel, null);

        $this->em->clear();
        $lignes = $this->em->getRepository(HistoriqueAffectation::class)->findBy([], ['id' => 'ASC']);
        self::assertCount(3, $lignes);
        self::assertSame([null, 'Ada'], [$lignes[0]->getAncienUtilisateur()?->getNom(), $lignes[0]->getNouvelUtilisateur()?->getNom()]);
        self::assertSame(['Ada', 'Grace'], [$lignes[1]->getAncienUtilisateur()?->getNom(), $lignes[1]->getNouvelUtilisateur()?->getNom()]);
        self::assertSame(['Grace', null], [$lignes[2]->getAncienUtilisateur()?->getNom(), $lignes[2]->getNouvelUtilisateur()?->getNom()]);
        foreach ($lignes as $ligne) {
            self::assertSame('Gestionnaire', $ligne->getAuteur()->getNom(), "L'auteur est l'utilisateur connecté.");
        }
    }

    public function testAucuneLigneSiLAffectationNeChangePas(): void
    {
        $ada = $this->creerUtilisateur('Ada');
        $materiel = $this->creerMateriel('Imprimante', $ada);
        $this->client->loginUser($this->creerUtilisateur('Gestionnaire', ['ROLE_GESTIONNAIRE']));

        // Même affectation, autre champ modifié.
        $this->client->request('GET', '/materiels/'.$materiel->getId().'/modifier');
        $this->client->submitForm('Enregistrer', ['materiel[nom]' => 'Imprimante laser']);
        self::assertResponseRedirects('/materiels/'.$materiel->getId());

        // Même affectation, rien d'autre de changé.
        $this->modifierLAffectation($materiel, $ada);

        $this->em->clear();
        self::assertSame('Imprimante laser', $this->em->getRepository(Materiel::class)->find($materiel->getId())->getNom());
        self::assertSame(0, $this->em->getRepository(HistoriqueAffectation::class)->count([]), "Aucune ligne : l'affectation n'a pas changé.");
    }

    public function testLEvenementEstEmisSeulementQuandLAffectationChange(): void
    {
        $ada = $this->creerUtilisateur('Ada');
        $grace = $this->creerUtilisateur('Grace');
        $materiel = $this->creerMateriel('Imprimante', $ada);
        $this->client->loginUser($this->creerUtilisateur('Gestionnaire', ['ROLE_GESTIONNAIRE']));
        $this->client->disableReboot();
        $recus = [];
        static::getContainer()->get('event_dispatcher')->addListener(MaterielAffecteEvent::class, static function (MaterielAffecteEvent $evenement) use (&$recus): void {
            $recus[] = $evenement;
        });

        $this->modifierLAffectation($materiel, $ada);
        self::assertCount(0, $recus, 'Même affectation : aucun événement.');

        $this->modifierLAffectation($materiel, $grace);
        self::assertCount(1, $recus, 'Affectation changée : un événement.');
        self::assertSame('Ada', $recus[0]->getAncienUtilisateur()->getNom());
        self::assertSame('Grace', $recus[0]->getNouvelUtilisateur()->getNom());
        self::assertSame($materiel->getId(), $recus[0]->getMateriel()->getId());
    }

    public function testUnMaterielCreeDejaAffecteEnregistreSonAffectation(): void
    {
        $ada = $this->creerUtilisateur('Ada');
        $this->client->loginUser($this->creerUtilisateur('Gestionnaire', ['ROLE_GESTIONNAIRE']));

        $this->client->request('GET', '/materiels/nouveau');
        $this->client->submitForm('Enregistrer', ['materiel[nom]' => 'Écran', 'materiel[categorie]' => 'Informatique', 'materiel[utilisateur]' => (string) $ada->getId()]);
        $this->client->request('GET', '/materiels/nouveau');
        $this->client->submitForm('Enregistrer', ['materiel[nom]' => 'Clavier', 'materiel[categorie]' => 'Informatique', 'materiel[utilisateur]' => '']);

        $this->em->clear();
        $lignes = $this->em->getRepository(HistoriqueAffectation::class)->findAll();
        self::assertCount(1, $lignes, "Seul l'écran, créé affecté, a une ligne : le clavier n'est à personne.");
        self::assertNull($lignes[0]->getAncienUtilisateur());
        self::assertSame('Ada', $lignes[0]->getNouvelUtilisateur()->getNom());
        self::assertSame('Écran', $lignes[0]->getMateriel()->getNom());
    }

    public function testLaPageDHistoriqueListeDuPlusRecentAuPlusAncien(): void
    {
        $auteur = $this->creerUtilisateur('Gestionnaire', ['ROLE_GESTIONNAIRE']);
        $un = $this->creerUtilisateur('Premiere');
        $deux = $this->creerUtilisateur('Deuxieme');
        $trois = $this->creerUtilisateur('Troisieme');
        $materiel = $this->creerMateriel('Imprimante');
        $autreMateriel = $this->creerMateriel('Scanner');
        $this->creerLigne($materiel, null, $un, '2026-01-01 10:00', $auteur);
        $this->creerLigne($materiel, $deux, $trois, '2026-03-01 10:00', $auteur);
        $this->creerLigne($materiel, $un, $deux, '2026-02-01 10:00', $auteur);
        $this->creerLigne($autreMateriel, null, $un, '2026-04-01 10:00', $auteur);
        $this->em->clear();
        $this->client->loginUser($auteur);

        $crawler = $this->client->request('GET', '/materiels/'.$materiel->getId().'/historique');

        self::assertResponseIsSuccessful();
        $lignes = $crawler->filter('li.affectation')->each(static fn ($ligne): string => $ligne->text());
        self::assertCount(3, $lignes, "Seules les lignes de ce matériel apparaissent.");
        self::assertStringContainsString('Troisieme', $lignes[0], 'Le plus récent (mars) en premier.');
        self::assertStringContainsString('Deuxieme', $lignes[1]);
        self::assertStringContainsString('Premiere', $lignes[2], 'Le plus ancien (janvier) en dernier.');
        self::assertStringContainsString('personne', $lignes[2], 'Avant la première affectation, le matériel était à personne.');
        self::assertStringContainsString('Gestionnaire', $lignes[0], "L'auteur du changement est affiché.");
    }

    public function testLaPageDHistoriqueSansLigneEtLesAccesRefuses(): void
    {
        $materiel = $this->creerMateriel('Imprimante');

        $this->client->request('GET', '/materiels/'.$materiel->getId().'/historique');
        self::assertResponseRedirects('http://localhost/connexion');

        $this->client->loginUser($this->creerUtilisateur('Simple'));
        $this->client->request('GET', '/materiels/'.$materiel->getId().'/historique');
        self::assertResponseStatusCodeSame(403, "Un utilisateur simple ne consulte pas le matériel d'un autre.");

        $this->client->loginUser($this->creerUtilisateur('Gestionnaire', ['ROLE_GESTIONNAIRE']));
        $this->client->request('GET', '/materiels/'.$materiel->getId().'/historique');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Aucune affectation');

        $this->client->request('GET', '/materiels/999/historique');
        self::assertResponseStatusCodeSame(404);
    }

    public function testLaFicheDuMaterielProposeLHistorique(): void
    {
        $materiel = $this->creerMateriel('Imprimante');
        $this->client->loginUser($this->creerUtilisateur('Gestionnaire', ['ROLE_GESTIONNAIRE']));

        $crawler = $this->client->request('GET', '/materiels/'.$materiel->getId());

        self::assertCount(1, $crawler->filter('a[href="/materiels/'.$materiel->getId().'/historique"]'));
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

    private function creerMateriel(string $nom, ?Utilisateur $utilisateur = null, ?string $motifRebut = null): Materiel
    {
        $materiel = (new Materiel())->setNom($nom)->setCategorie('Informatique')->setUtilisateur($utilisateur)->setMotifRebut($motifRebut);
        $this->em->persist($materiel);
        $this->em->flush();

        return $materiel;
    }

    private function creerLigne(Materiel $materiel, ?Utilisateur $ancien, ?Utilisateur $nouvel, string $faitLe, ?Utilisateur $auteur): HistoriqueAffectation
    {
        $ligne = (new HistoriqueAffectation())
            ->setMateriel($materiel)
            ->setAncienUtilisateur($ancien)
            ->setNouvelUtilisateur($nouvel)
            ->setFaitLe(new \DateTimeImmutable($faitLe))
            ->setAuteur($auteur);
        $this->em->persist($ligne);
        $this->em->flush();

        return $ligne;
    }

    /** Ouvre le formulaire de modification et l'envoie avec cette affectation (null = personne), comme le ferait un gestionnaire. */
    private function modifierLAffectation(Materiel $materiel, ?Utilisateur $utilisateur): void
    {
        $this->client->request('GET', '/materiels/'.$materiel->getId().'/modifier');
        $this->client->submitForm('Enregistrer', ['materiel[utilisateur]' => null === $utilisateur ? '' : (string) $utilisateur->getId()]);
        self::assertResponseRedirects('/materiels/'.$materiel->getId());
    }
}
