<?php

namespace App\Tests\Formation\Exercice;

use App\Formation\Contenu\Catalogue;
use App\Formation\Contenu\ChargeurCatalogue;
use App\Formation\Exercice\ExerciceCommand;
use App\Formation\Exercice\LanceurTests;
use App\Formation\Parcours\Parcours;
use App\Formation\Progression\ProgressionStore;
use App\Tests\Formation\FabriqueLancement;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Parcours du catalogue de test : rappel-a, s1-preparation, s1-demonstration, s1-exercice (tests Vert),
 * s2-preparation, s2-exercice (tests Rouge : 1 test sur 2 passe).
 */
class ExerciceCommandTest extends TestCase
{
    private const PROJET = __DIR__.'/../../..';

    private string $chemin;
    private ProgressionStore $store;
    private Catalogue $catalogue;
    private CommandTester $testeur;

    /** @var list<string> */
    private array $copiesDeContenu = [];

    protected function setUp(): void
    {
        $this->chemin = sys_get_temp_dir().'/exercice-'.bin2hex(random_bytes(4)).'.sqlite';
        $this->store = new ProgressionStore($this->chemin);
        $this->catalogue = (new ChargeurCatalogue(__DIR__.'/../fixtures/contenu-mini', self::PROJET))->charger();
        $this->testeur = new CommandTester(new ExerciceCommand(
            new Parcours($this->catalogue, $this->store),
            $this->catalogue,
            $this->store,
            new LanceurTests(self::PROJET),
        ));
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->copiesDeContenu);
        if (is_file($this->chemin)) {
            unlink($this->chemin);
        }
    }

    public function testRefuseSiLeBlocCourantNEstPasUnExercice(): void
    {
        $code = $this->testeur->execute(['action' => 'test']);

        self::assertSame(1, $code);
        self::assertStringContainsString("Pas d'exercice en cours. Ta séance du jour : « Rappel HTTP ».", $this->testeur->getDisplay());
        self::assertSame([], $this->store->lancements('rappel-a'));
        self::assertSame([], $this->store->lancements('s1-exercice'));
        self::assertSame([], $this->store->blocsFaits());
    }

    public function testEnregistreUnRouge(): void
    {
        $this->avancerJusqua('s2-exercice');

        $code = $this->testeur->execute(['action' => 'test']);

        self::assertSame(1, $code);
        $affichage = $this->testeur->getDisplay();
        self::assertStringContainsString('1/2 tests passent.', $affichage);
        self::assertStringContainsString('- testQuiEchoue', $affichage);
        self::assertStringContainsString('Pour voir le détail : docker compose exec app php bin/phpunit tests/Formation/fixtures/exercices/Rouge', $affichage);
        self::assertStringNotContainsString('Demander de l\'aide', $this->testeur->getDisplay());
        self::assertSame(1, $this->store->nombreDeRouges('s2-exercice'));
        self::assertFalse($this->store->estFait('s2-exercice'));
        self::assertSame(1, $this->store->lancements('s2-exercice')[0]->nbReussis);
        self::assertCount(1, $this->store->lancements('s2-exercice')[0]->echecs, 'la liste des tests rouges est enregistrée pour la page web');
        self::assertStringStartsWith('testQuiEchoue', $this->store->lancements('s2-exercice')[0]->echecs[0]);
    }

    public function testUneRaisonQuiRessembleAUneBaliseDeLaConsoleEstAfficheeTelleQuelle(): void
    {
        $testeur = $this->testeurSur('RaisonBalisee');

        $testeur->execute(['action' => 'test']);

        self::assertStringContainsString('<info>x</info>', $testeur->getDisplay());
    }

    public function testExactementDixRougesSontTousListesSansLigneDeSuite(): void
    {
        $testeur = $this->testeurSur('DixRouges');

        $testeur->execute(['action' => 'test']);

        $affichage = $testeur->getDisplay();
        self::assertSame(10, substr_count($affichage, "\n  - "), $affichage);
        self::assertStringContainsString('raison 10', $affichage);
        self::assertStringNotContainsString('… et', $affichage);
    }

    public function testOnzeRougesAffichentUneLigneDeSuiteAuSingulier(): void
    {
        $testeur = $this->testeurSur('OnzeRouges');

        $testeur->execute(['action' => 'test']);

        $affichage = $testeur->getDisplay();
        self::assertSame(10, substr_count($affichage, "\n  - "), $affichage);
        self::assertStringNotContainsString('raison 11', $affichage);
        self::assertStringContainsString("\n  … et 1 autre\n", $affichage);
        self::assertStringContainsString('Pour voir le détail : docker compose exec app php bin/phpunit tests/Formation/fixtures/exercices/OnzeRouges', $affichage);
    }

    public function testUnVertTermineLExerciceEtAnnonceLeBadge(): void
    {
        $this->avancerJusqua('s1-exercice');

        $code = $this->testeur->execute(['action' => 'test']);

        self::assertSame(0, $code);
        self::assertStringContainsString('Bravo, tous les tests passent (2/2). Badge débloqué : Premier badge.', $this->testeur->getDisplay());
        self::assertTrue($this->store->estFait('s1-exercice'));
        self::assertTrue($this->store->lancements('s1-exercice')[0]->vert);
    }

    public function testLeQuatriemeRougeAnnonceLAide(): void
    {
        $this->avancerJusqua('s2-exercice');
        for ($i = 0; $i < 3; ++$i) {
            $this->store->enregistrerLancement(FabriqueLancement::creer('s2-exercice', false, null, 'avant'));
        }

        $code = $this->testeur->execute(['action' => 'test']);

        self::assertSame(1, $code);
        self::assertStringContainsString(
            "C'est le 4e essai sans succès : clique sur « Demander de l'aide » dans la plateforme.",
            $this->testeur->getDisplay()
        );
    }

    public function testQuandToutEstFaitLeParcoursEstAnnonceTermine(): void
    {
        $this->avancerJusqua(null);

        $code = $this->testeur->execute(['action' => 'test']);

        self::assertSame(0, $code);
        self::assertStringContainsString('Tout le parcours disponible est terminé.', $this->testeur->getDisplay());
    }

    public function testUneActionInconnueEstRefusee(): void
    {
        $code = $this->testeur->execute(['action' => 'supprimer']);

        self::assertSame(2, $code);
        self::assertStringContainsString('supprimer', $this->testeur->getDisplay());
        self::assertSame([], $this->store->blocsFaits());
    }

    /** La commande, sur un catalogue dont l'exercice du sujet 2 est lancé sur un dossier de fixtures donné. */
    private function testeurSur(string $dossierDeFixtures): CommandTester
    {
        $copie = sys_get_temp_dir().'/contenu-'.bin2hex(random_bytes(4));
        $this->copiesDeContenu[] = $copie;
        (new Filesystem())->mirror(__DIR__.'/../fixtures/contenu-mini', $copie);
        $sujet = $copie.'/niveau1/02-s2/sujet.yaml';
        file_put_contents($sujet, str_replace('exercices/Rouge', 'exercices/'.$dossierDeFixtures, (string) file_get_contents($sujet)));

        $catalogue = (new ChargeurCatalogue($copie, self::PROJET))->charger();
        foreach ($catalogue->blocsOrdonnes() as $bloc) {
            if ('s2-exercice' === $bloc->id) {
                break;
            }
            $this->store->marquerFait($bloc->id, new \DateTimeImmutable());
        }

        return new CommandTester(new ExerciceCommand(
            new Parcours($catalogue, $this->store),
            $catalogue,
            $this->store,
            new LanceurTests(self::PROJET),
        ));
    }

    /** Marque faits tous les blocs qui précèdent $blocId (tous les blocs si null). */
    private function avancerJusqua(?string $blocId): void
    {
        foreach ($this->catalogue->blocsOrdonnes() as $bloc) {
            if ($bloc->id === $blocId) {
                return;
            }
            $this->store->marquerFait($bloc->id, new \DateTimeImmutable());
        }
    }
}
