<?php

namespace App\Tests\Formation\Exercice;

use App\Formation\Exercice\LanceurTests;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class LanceurTestsTest extends TestCase
{
    private const PROJET = __DIR__.'/../../..';
    private const FIXTURES = 'tests/Formation/fixtures/exercices/';

    public function testUnDossierDeTestsQuiPasseEstVert(): void
    {
        $resultat = $this->lanceur()->lancer(self::FIXTURES.'Vert');

        self::assertTrue($resultat->vert);
        self::assertSame(2, $resultat->nbTests);
        self::assertSame(2, $resultat->nbReussis);
        self::assertSame('Tous les tests passent.', $resultat->resume);
    }

    public function testUnTestQuiEchoueRendLeLancementRouge(): void
    {
        $resultat = $this->lanceur()->lancer(self::FIXTURES.'Rouge');

        self::assertFalse($resultat->vert);
        self::assertSame(2, $resultat->nbTests);
        self::assertSame(1, $resultat->nbReussis);
        self::assertStringContainsString('testQuiEchoue', $resultat->resume);
    }

    public function testTousLesTestsRougesSontListes(): void
    {
        $resultat = $this->lanceur()->lancer(self::FIXTURES.'TroisRouges');

        self::assertFalse($resultat->vert);
        self::assertSame(4, $resultat->nbTests);
        self::assertSame(1, $resultat->nbReussis);
        self::assertCount(3, $resultat->echecs);
        self::assertStringStartsWith('testPremierRouge', $resultat->echecs[0]);
        self::assertStringStartsWith('testDeuxiemeRouge', $resultat->echecs[1]);
        self::assertStringContainsString('troisieme raison', $resultat->echecs[2]);
        self::assertSame($resultat->echecs[0], $resultat->resume, 'le résumé reste la première ligne d\'échec');
    }

    public function testUneErreurDeSyntaxeEstRougeJamaisVerte(): void
    {
        $resultat = $this->lanceur()->lancer(self::FIXTURES.'Syntaxe');

        self::assertFalse($resultat->vert);
        self::assertSame(0, $resultat->nbTests);
        self::assertStringContainsString('Unclosed', $resultat->resume, 'le résumé nomme la cause, pas la pile d\'appels');
    }

    public function testAucunTestExecuteEstRouge(): void
    {
        $resultat = $this->lanceur()->lancer(self::FIXTURES.'Vide');

        self::assertFalse($resultat->vert);
        self::assertSame(0, $resultat->nbTests);
        self::assertSame('Aucun test exécuté.', $resultat->resume);
    }

    public function testUnDossierInexistantEstRouge(): void
    {
        $resultat = $this->lanceur()->lancer(self::FIXTURES.'Inexistant');

        self::assertFalse($resultat->vert);
        self::assertSame(0, $resultat->nbTests);
        self::assertSame(0, $resultat->nbReussis);
        self::assertNotSame('', trim($resultat->resume), 'le résumé dit pourquoi, il n\'est pas vide');
        self::assertStringContainsString('Inexistant', $resultat->resume, 'le résumé nomme ce qui manque');
    }

    public function testLeDelaiParDefautEstDeSixCentsSecondes(): void
    {
        $delai = (new \ReflectionClass(LanceurTests::class))->getConstructor()->getParameters()[1];

        self::assertSame(600, $delai->getDefaultValue());
    }

    public function testLeResultatPrecedentNEstJamaisRelu(): void
    {
        $vert = $this->lanceur()->lancer(self::FIXTURES.'Vert');
        self::assertTrue($vert->vert, 'le lancement précédent laisse bien un journal vert');
        // Si le journal du lancement précédent (vert, 2 tests) était relu par erreur, ce lancement le serait aussi.
        $resultat = $this->lanceur()->lancer(self::FIXTURES.'Syntaxe');

        self::assertFalse($resultat->vert);
        self::assertSame(0, $resultat->nbTests, 'aucun test du lancement précédent');
        self::assertSame(0, $resultat->nbReussis);
        self::assertStringContainsString('Unclosed', $resultat->resume);
    }

    public function testUnLancementQuiDepasseLeDelaiEstRougeAvecUnMessageClair(): void
    {
        $debut = microtime(true);

        $resultat = (new LanceurTests(self::PROJET, 1))->lancer(self::FIXTURES.'Lent');

        self::assertFalse($resultat->vert);
        self::assertSame(0, $resultat->nbTests);
        self::assertStringContainsString('Délai dépassé', $resultat->resume);
        self::assertLessThan(20, microtime(true) - $debut, 'le processus est interrompu, pas attendu jusqu\'au bout');
    }

    public function testLeNoyauDemarreMemeLanceDepuisLaConsoleEnDev(): void
    {
        // Reproduit bin/exercice : le processus parent est en APP_ENV=dev, .env chargé par Dotenv
        // (SYMFONY_DOTENV_VARS, variables du .env). PHPUnit doit tout de même tourner en environnement de test.
        $resultat = $this->lancerDepuisUneConsoleEnDev(self::FIXTURES.'Noyau');

        self::assertSame('dev', $resultat['appEnvParent'], 'le parent simule bien la console en dev');
        self::assertTrue($resultat['vert'], $resultat['resume']);
        self::assertSame(1, $resultat['nbTests']);
    }

    /** @return array{vert: bool, nbTests: int, nbReussis: int, resume: string, appEnvParent: ?string} */
    private function lancerDepuisUneConsoleEnDev(string $dossierTests): array
    {
        // Environnement vierge : celui de PHPUnit (KERNEL_CLASS de .env.test, APP_ENV=test) masquerait le défaut.
        $env = [];
        foreach (array_keys(getenv() + $_ENV + $_SERVER) as $nom) {
            if (!\in_array($nom, ['PATH', 'HOME'], true)) {
                $env[$nom] = false;
            }
        }

        $processus = new Process([\PHP_BINARY, self::PROJET.'/tests/Formation/fixtures/lanceur-en-dev.php', $dossierTests], self::PROJET, $env);
        $processus->setTimeout(120);
        $processus->mustRun();

        return json_decode($processus->getOutput(), true, 512, \JSON_THROW_ON_ERROR);
    }

    private function lanceur(): LanceurTests
    {
        return new LanceurTests(self::PROJET);
    }
}
