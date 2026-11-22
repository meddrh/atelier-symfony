<?php

namespace App\Tests\Formation\Contenu;

use App\Formation\Contenu\ChargeurCatalogue;
use App\Formation\Contenu\VerifierContenuCommand;
use App\Tests\Formation\AvecContenuModifiable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class VerifierContenuCommandTest extends TestCase
{
    use AvecContenuModifiable;

    private const PROJET = __DIR__.'/../../..';
    private const MINI = __DIR__.'/../fixtures/contenu-mini';

    public function testUnContenuValideEstAnnonceAvecSesCompteurs(): void
    {
        $testeur = $this->testeur(self::MINI);

        self::assertSame(0, $testeur->execute([]));
        self::assertStringContainsString('Contenu valide : 6 blocs, 3 lignes de programme rattachées sur 3.', $testeur->getDisplay());
    }

    public function testLaCouvertureDistingueLesLignesDemandeesDesLignesMontrees(): void
    {
        $testeur = $this->testeur(self::MINI);

        self::assertSame(0, $testeur->execute([]));
        $sortie = $testeur->getDisplay();
        self::assertStringContainsString('Lignes demandées par un exercice : 2.', $sortie);
        self::assertStringContainsString('Lignes demandées et montrées (démonstration ou rappel) : 1 sur 2.', $sortie);
        self::assertStringContainsString("Lignes demandées jamais montrées :\n  - hp.doctrine : Doctrine ORM", str_replace("\r", '', $sortie));
        self::assertStringNotContainsString('  - http.codes', $sortie, 'une ligne montrée n\'est pas listée');
    }

    public function testUneLigneMontreeSeulementParUnRappelCompte(): void
    {
        $this->modifier('niveau1/02-s2/sujet.yaml', function (array $d) {
            $d['blocs'][1]['programme'] = ['hp.doctrine', 'http.requete'];

            return $d;
        });
        $testeur = $this->testeur($this->temporaire);

        self::assertSame(0, $testeur->execute([]));
        self::assertStringContainsString('Lignes demandées et montrées (démonstration ou rappel) : 2 sur 3.', $testeur->getDisplay());
    }

    public function testToutesLesLignesDemandeesMontreesNeListentRien(): void
    {
        $this->modifier('niveau1/02-s2/sujet.yaml', function (array $d) {
            $d['blocs'][1]['programme'] = ['http.requete'];

            return $d;
        });
        $testeur = $this->testeur($this->temporaire);

        self::assertSame(0, $testeur->execute([]));
        self::assertStringContainsString('Lignes demandées et montrées (démonstration ou rappel) : 2 sur 2.', $testeur->getDisplay());
        self::assertStringNotContainsString('jamais montrées', $testeur->getDisplay());
    }

    public function testLesLignesNonRattacheesSontListees(): void
    {
        $this->modifier('niveau1/02-s2/sujet.yaml', function (array $d) {
            $d['blocs'][1]['programme'] = [];

            return $d;
        });
        $testeur = $this->testeur($this->temporaire);

        self::assertSame(0, $testeur->execute([]));
        $sortie = $testeur->getDisplay();
        self::assertStringContainsString('Contenu valide : 6 blocs, 2 lignes de programme rattachées sur 3.', $sortie);
        self::assertStringContainsString('hp.doctrine', $sortie);
    }

    public function testUnContenuInvalideDonneLeCode1EtLeMessage(): void
    {
        $this->modifier('niveau1/01-s1/sujet.yaml', function (array $d) {
            $d['blocs'][1]['programme'] = ['http.inconnue'];

            return $d;
        });
        $testeur = $this->testeur($this->temporaire);

        self::assertSame(1, $testeur->execute([]));
        self::assertStringContainsString('http.inconnue', $testeur->getDisplay());
    }

    private function testeur(string $dossier): CommandTester
    {
        return new CommandTester(new VerifierContenuCommand(new ChargeurCatalogue($dossier, self::PROJET)));
    }
}
