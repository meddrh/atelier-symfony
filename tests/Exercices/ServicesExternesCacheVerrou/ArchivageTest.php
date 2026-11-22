<?php

namespace App\Tests\Exercices\ServicesExternesCacheVerrou;

use App\Entity\HistoriqueAffectation;
use App\Entity\Materiel;
use App\Tests\Exercices\ExerciceTestCase;
use App\Tests\Support\ReponsesSimulees;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpClient\Exception\TimeoutException;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Lock\LockFactory;

/**
 * La commande app:historique:archiver, lancée comme le fait la console, dans le noyau de test.
 * Aucun accès réseau : l'API d'archivage est le faux service ReponsesSimulees.
 *
 * Le noyau de ce test (KernelAvecVerrousGardes) remplace la fabrique de verrous par une fabrique qui garde une
 * référence à chaque verrou créé, sur un stockage en mémoire : un verrou que la commande n'a pas rendu elle-même
 * (release() dans un « finally ») reste pris jusqu'à la fin du test, quels que soient les arguments de createLock().
 * Le test prend lui-même le verrou, sur la même ressource et la même fabrique, avant de lancer la commande.
 */
class ArchivageTest extends ExerciceTestCase
{
    private const BOM = "\xEF\xBB\xBF";
    private const VERROU = 'archivage-historique';

    private EntityManagerInterface $em;

    protected static function getKernelClass(): string
    {
        return KernelAvecVerrousGardes::class;
    }

    protected function setUp(): void
    {
        parent::setUp();

        static::bootKernel();
        $this->em = static::getContainer()->get('doctrine')->getManager();
    }

    public function testLaFabriqueDuTestGardeLesVerrousMemeSansLibererLObjet(): void
    {
        // Garde du test lui-même : sans cette fabrique, un verrou à libération automatique se rendrait en disparaissant
        // et les tests de libération ne pourraient pas voir un « finally » oublié.
        self::assertInstanceOf(FabriqueDeVerrousGardes::class, $this->fabrique());
        $a = $this->fabrique()->createLock(self::VERROU);
        self::assertTrue($a->acquire());
        unset($a);
        self::assertFalse($this->fabrique()->createLock(self::VERROU)->acquire(), 'Un verrou non rendu reste pris, même quand son objet a disparu.');
    }

    public function testUnSuccesAfficheUnMessageEtEnvoieLeCsvParUnPost(): void
    {
        $this->creerLigne('Imprimante');
        $this->reponses()->prevoir(new MockResponse('', ['http_code' => 201]));

        $commande = $this->lancer();

        self::assertSame(0, $commande->getStatusCode(), $this->texte($commande));
        self::assertStringContainsString('Historique archivé', $this->texte($commande));

        $demandes = $this->reponses()->demandes();
        self::assertCount(1, $demandes);
        self::assertSame('POST', $demandes[0]['methode']);
        self::assertStringStartsWith('https://archives.exemple.test/', $demandes[0]['url'], 'Le client limité de l\'archivage, avec son adresse de base par défaut.');
        $corps = $demandes[0]['corps'];
        self::assertStringStartsWith(self::BOM, $corps, 'Le CSV est celui de l\'export, BOM compris.');
        $lignes = array_map(static fn (string $l): array => str_getcsv($l, ';', '"', ''), explode("\n", trim(substr($corps, 3))));
        self::assertEqualsCanonicalizing(['Date', 'Matériel', 'Ancien utilisateur', 'Nouvel utilisateur', 'Auteur'], $lignes[0], 'La première ligne est l\'en-tête du CSV.');
        self::assertCount(2, $lignes, 'L\'en-tête et la ligne de l\'historique.');
        self::assertContains('Imprimante', $lignes[1]);
    }

    public function testUnSecondLancementSimultaneEstRefuse(): void
    {
        $this->creerLigne('Imprimante');
        $verrou = $this->fabrique()->createLock(self::VERROU);
        self::assertTrue($verrou->acquire(), 'Le test prend lui-même le verrou : un autre archivage est « en cours ».');

        try {
            $commande = $this->lancer();
        } finally {
            $verrou->release();
        }

        self::assertNotSame(0, $commande->getStatusCode(), 'Un code de sortie non nul : un script appelant sait que rien n\'a été fait.');
        self::assertStringContainsString('déjà en cours', $this->texte($commande));
        self::assertCount(0, $this->reponses()->demandes(), 'Rien n\'est envoyé à l\'API.');
    }

    public function testLeVerrouEstRenduApresUnSucces(): void
    {
        $this->creerLigne('Imprimante');
        $this->reponses()->prevoir(new MockResponse('', ['http_code' => 200]));

        $this->lancer();

        $verrou = $this->fabrique()->createLock(self::VERROU);
        self::assertTrue($verrou->acquire(), 'Le verrou est libre après la fin de la commande.');
        $verrou->release();
    }

    public function testUneApiEnPanneDonneUnMessageClairEtUnCodeNonNul(): void
    {
        $this->creerLigne('Imprimante');
        $this->reponses()->prevoir(new MockResponse('{"erreur":"panne"}', ['http_code' => 500]));

        $commande = $this->lancer();

        self::assertNotSame(0, $commande->getStatusCode());
        $texte = $this->texte($commande);
        self::assertStringContainsString('500', $texte);
        self::assertStringContainsString("pas été archivé", $texte);
        $this->assertPasDeTraceBrute($texte);
    }

    public function testUnDelaiDepasseDonneUnMessageClairEtUnCodeNonNul(): void
    {
        $this->creerLigne('Imprimante');
        $this->reponses()->prevoir(new MockResponse('', ['error' => 'Délai dépassé']));

        $commande = $this->lancer();

        self::assertNotSame(0, $commande->getStatusCode());
        $texte = $this->texte($commande);
        self::assertStringContainsString('délai dépassé', $texte);
        self::assertStringContainsString("pas été archivé", $texte);
        $this->assertPasDeTraceBrute($texte);
    }

    public function testUneConnexionImpossibleDonneUnMessageClairEtUnCodeNonNul(): void
    {
        $this->creerLigne('Imprimante');
        $this->reponses()->prevoir(static function (): never {
            throw new TimeoutException('Connexion impossible.');
        });

        $commande = $this->lancer();

        self::assertNotSame(0, $commande->getStatusCode());
        $texte = $this->texte($commande);
        self::assertStringContainsString("pas été archivé", $texte);
        $this->assertPasDeTraceBrute($texte);
    }

    public function testLeVerrouEstRenduApresUnEchec(): void
    {
        $this->creerLigne('Imprimante');
        $this->reponses()->prevoir(new MockResponse('', ['http_code' => 503]));

        $this->lancer();

        $verrou = $this->fabrique()->createLock(self::VERROU);
        self::assertTrue($verrou->acquire(), 'Un échec ne laisse pas le verrou pris : sinon plus aucun archivage ne partirait.');
        $verrou->release();
    }

    public function testUnHistoriqueVideEnvoieQuandMemeLEnTete(): void
    {
        $this->reponses()->prevoir(new MockResponse('', ['http_code' => 200]));

        $commande = $this->lancer();

        self::assertSame(0, $commande->getStatusCode());
        self::assertStringContainsString('Date', $this->reponses()->demandes()[0]['corps']);
    }

    // ---- Outils -------------------------------------------------------------------------------------

    private function lancer(): CommandTester
    {
        $application = new Application(static::$kernel);
        $application->setAutoExit(false);
        $testeur = new CommandTester($application->find('app:historique:archiver'));
        // Pas d'attrapeur d'exceptions ici : une exception qui sortirait de la commande ferait échouer le test.
        $testeur->execute([], ['decorated' => false]);

        return $testeur;
    }

    /** La sortie sur une seule ligne, sans les marques de blocs de SymfonyStyle : les phrases s'y lisent d'un trait. */
    private function texte(CommandTester $commande): string
    {
        return trim(preg_replace('/\s+/', ' ', str_replace(['!', '[ERROR]', '[OK]'], ' ', $commande->getDisplay())));
    }

    private function assertPasDeTraceBrute(string $texte): void
    {
        foreach (['Exception', 'Stack trace', '#0', '.php', 'vendor/'] as $trace) {
            self::assertStringNotContainsString($trace, $texte, 'Le junior ne doit pas lire une trace brute à l\'écran.');
        }
    }

    private function reponses(): ReponsesSimulees
    {
        return static::getContainer()->get(ReponsesSimulees::class);
    }

    private function fabrique(): LockFactory
    {
        return static::getContainer()->get(LockFactory::class);
    }

    private function creerLigne(string $nomDuMateriel): void
    {
        $materiel = (new Materiel())->setNom($nomDuMateriel)->setCategorie('Informatique');
        $this->em->persist($materiel);
        $this->em->persist(
            (new HistoriqueAffectation())
                ->setMateriel($materiel)
                ->setAncienUtilisateur(null)
                ->setNouvelUtilisateur(null)
                ->setFaitLe(new \DateTimeImmutable('2026-11-01 09:00:00'))
                ->setAuteur(null)
        );
        $this->em->flush();
    }
}
