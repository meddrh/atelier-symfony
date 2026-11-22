<?php

namespace App\Tests\Exercices\TestsFonctionnels;

use App\Tests\Exercices\TestsFonctionnels\Defauts\CreationIgnoree;
use App\Tests\Exercices\TestsFonctionnels\Defauts\FiltreIgnore;
use App\Tests\Exercices\TestsFonctionnels\Defauts\ListeEnPanne;
use App\Tests\Exercices\TestsFonctionnels\Defauts\RedirectionPerdue;
use App\Tests\Exercices\TestsFonctionnels\Defauts\ValidateurIndulgent;
use App\Tests\Formation\AvecCopieDuProjet;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

/**
 * Cet exercice juge TES tests : il les lance, par un sous-processus PHPUnit, dans des copies jetables de ton projet.
 *
 * 1. sur une copie saine de l'application, tes tests doivent passer ;
 * 2. sur des copies où l'on a cassé l'application volontairement, UN défaut par copie, ils doivent échouer à chaque fois,
 *    mais pas TOUS : au moins un test reste vert (la copie démarre, l'échec vient bien du défaut).
 *    Un test qui passe encore sur une application cassée ne teste rien.
 *
 * Chaque copie est sous var/ et supprimée à la fin de son lancement, même en cas d'échec. La durée totale reste bien
 * sous le délai de 300 s du lanceur d'exercices (un lancement dure quelques secondes : voir le temps affiché par PHPUnit).
 */
class TestsFonctionnelsTest extends TestCase
{
    use AvecCopieDuProjet;

    private const DELAI_D_UN_LANCEMENT = 90;
    private const FICHIER_DEMONSTRATION = 'tests/Junior/MaterielTest.php';
    private const FICHIER_DU_JUNIOR = 'tests/Junior/TicketTest.php';
    private const NB_MINIMAL_DE_TESTS_DU_JUNIOR = 4;

    /**
     * Les versions cassées : un défaut par copie, installé par une configuration de conteneur ajoutée à la copie.
     * Leur code est dans tests/Exercices/TestsFonctionnels/Defauts/ ; il n'est jamais chargé par ton application.
     */
    private const DEFAUTS = [
        1 => [RedirectionPerdue::class, 'abonne'],
        2 => [ValidateurIndulgent::class, 'decore'],
        3 => [FiltreIgnore::class, 'abonne'],
        4 => [ListeEnPanne::class, 'abonne'],
        5 => [CreationIgnoree::class, 'abonne'],
    ];

    // ---- Démonstration : les tests des matériels ---------------------------------------

    public function testLesTestsDeLaDemonstrationExistentEtPassent(): void
    {
        $bilan = $this->lancerDansUneCopie(self::FICHIER_DEMONSTRATION, null);

        self::assertGreaterThanOrEqual(6, $bilan['nbTests'], "La démonstration écrit au moins 6 tests dans tests/Junior/MaterielTest.php.\n".$bilan['sortie']);
        self::assertSame(0, $bilan['nbEchecs'], "Les tests de la démonstration doivent passer sur l'application.\n".$bilan['sortie']);
        self::assertSame(0, $bilan['code'], $bilan['sortie']);
    }

    // ---- Exercice : tes tests des tickets ----------------------------------------------

    public function testTesTestsDesTicketsPassentSurLApplication(): void
    {
        $bilan = $this->lancerDansUneCopie(self::FICHIER_DU_JUNIOR, null);

        self::assertGreaterThanOrEqual(self::NB_MINIMAL_DE_TESTS_DU_JUNIOR, $bilan['nbTests'], sprintf("Il faut au moins %d tests dans %s (les tests ignorés ne comptent pas).\n%s", self::NB_MINIMAL_DE_TESTS_DU_JUNIOR, self::FICHIER_DU_JUNIOR, $bilan['sortie']));
        self::assertSame(0, $bilan['nbEchecs'], "Tes tests doivent d'abord passer sur l'application saine.\n".$bilan['sortie']);
        self::assertSame(0, $bilan['code'], $bilan['sortie']);
    }

    /**
     * @dataProvider numerosDeDefauts
     */
    public function testTesTestsEchouentSurUneApplicationCassee(int $numero): void
    {
        $bilan = $this->lancerDansUneCopie(self::FICHIER_DU_JUNIOR, $numero);

        self::assertGreaterThanOrEqual(self::NB_MINIMAL_DE_TESTS_DU_JUNIOR, $bilan['nbTests'], "Tes tests n'ont pas pu s'exécuter sur la version cassée n° $numero.\n".$bilan['sortie']);
        self::assertGreaterThan(
            0,
            $bilan['nbEchecs'],
            sprintf(
                "Version cassée n° %d sur %d : tes tests passent encore alors que l'application ne fait plus ce qu'elle doit.\n".
                "Un bon test échoue quand le comportement change : que vérifie-t-il vraiment, la réponse seulement, ou aussi ce qu'elle a produit ?",
                $numero,
                \count(self::DEFAUTS),
            ),
        );
        // Témoin : une copie dont le noyau plante fait échouer TOUS les tests, sans que le défaut y soit pour quelque chose.
        // Au moins un test du junior doit donc rester vert : la copie démarre, et l'échec vient bien du défaut.
        self::assertLessThan(
            $bilan['nbTests'],
            $bilan['nbEchecs'],
            sprintf(
                "Version cassée n° %d sur %d : TOUS tes tests échouent (%d sur %d), même ceux qui n'ont aucun rapport avec le défaut.\n".
                "Un test qui échoue pour une autre raison que le défaut ne prouve rien : au moins un de tes tests doit rester vert sur la copie cassée.\n%s",
                $numero,
                \count(self::DEFAUTS),
                $bilan['nbEchecs'],
                $bilan['nbTests'],
                $bilan['sortie'],
            ),
        );
    }

    /** @return iterable<string, array{int}> */
    public function numerosDeDefauts(): iterable
    {
        foreach (array_keys(self::DEFAUTS) as $numero) {
            yield 'version cassée n° '.$numero => [$numero];
        }
    }

    // ---- Outils : la copie jetable et le sous-processus --------------------------------

    /**
     * Copie le projet, y installe éventuellement un défaut, lance PHPUnit sur un fichier de tests, puis supprime la copie
     * (toujours : le « finally » s'exécute même si le lancement échoue).
     *
     * @return array{code: int, nbTests: int, nbEchecs: int, sortie: string}
     */
    private function lancerDansUneCopie(string $fichierDeTests, ?int $numeroDeDefaut): array
    {
        $copie = $this->copierLeProjetComplet();

        try {
            if (!is_file($copie.'/'.$fichierDeTests)) {
                self::fail(sprintf('Le fichier « %s » n\'existe pas : écris-le (voir l\'énoncé).', $fichierDeTests));
            }
            if (null !== $numeroDeDefaut) {
                $this->installerLeDefaut($copie, $numeroDeDefaut);
            }

            return $this->lancerPhpunit($copie, $fichierDeTests);
        } finally {
            (new Filesystem())->remove($copie);
        }
    }

    private function installerLeDefaut(string $copie, int $numero): void
    {
        [$classe, $genre] = self::DEFAUTS[$numero];

        $service = 'abonne' === $genre
            ? "    $classe:\n        tags: ['kernel.event_subscriber']\n"
            : "    $classe:\n        decorates: validator\n        arguments: ['@.inner']\n";

        // Un fichier de config/packages est chargé par le noyau pour tous les environnements.
        file_put_contents($copie.'/config/packages/zz_defaut_volontaire.yaml', "services:\n".$service);
    }

    /** @return array{code: int, nbTests: int, nbEchecs: int, sortie: string} */
    private function lancerPhpunit(string $copie, string $fichierDeTests): array
    {
        $journal = $copie.'/var/journal-du-junior.xml';
        $processus = new Process([\PHP_BINARY, 'bin/phpunit', $fichierDeTests, '--log-junit', $journal], $copie, $this->environnementDeTest());
        $processus->setTimeout(self::DELAI_D_UN_LANCEMENT);
        $processus->run();

        $nbTests = 0;
        $nbEchecs = 0;
        if (is_file($journal)) {
            $xml = simplexml_load_file($journal);
            foreach ($xml->xpath('//testcase') ?: [] as $cas) {
                if (isset($cas->skipped)) {
                    continue;
                }
                ++$nbTests;
                if (isset($cas->failure) || isset($cas->error)) {
                    ++$nbEchecs;
                }
            }
        }

        $sortie = $processus->getOutput().$processus->getErrorOutput();

        return ['code' => (int) $processus->getExitCode(), 'nbTests' => $nbTests, 'nbEchecs' => $nbEchecs, 'sortie' => mb_substr($sortie, -1500)];
    }

    /**
     * Même environnement qu'un lancement direct de PHPUnit : on retire les variables que le .env a chargées dans ce
     * processus, pour que le sous-processus relise le sien (voir LanceurTests::environnementDeTest()).
     *
     * @return array<string, string|false>
     */
    private function environnementDeTest(): array
    {
        $chargees = $_SERVER['SYMFONY_DOTENV_VARS'] ?? $_ENV['SYMFONY_DOTENV_VARS'] ?? getenv('SYMFONY_DOTENV_VARS') ?: '';

        $env = ['SYMFONY_DOTENV_VARS' => false];
        foreach (explode(',', (string) $chargees) as $nom) {
            if ('' !== $nom) {
                $env[$nom] = false;
            }
        }
        $env['APP_DEBUG'] = false;
        $env['APP_ENV'] = 'test';

        return $env;
    }
}
