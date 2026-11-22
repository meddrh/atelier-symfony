<?php

namespace App\Tests\Formation\Contenu;

use App\Formation\Contenu\Texte;
use PHPUnit\Framework\TestCase;

class TexteTest extends TestCase
{
    public function testLesEtapesSontSeparreesParUneLigneDeTroisTirets(): void
    {
        $etapes = Texte::etapes("# Un\n\nA.\n\n---\n\n# Deux\n\nB.\n\n  ---  \n\nC.\n");

        self::assertSame(["# Un\n\nA.", "# Deux\n\nB.", 'C.'], $etapes);
    }

    public function testUnTexteSansSeparateurEstUneSeuleEtape(): void
    {
        self::assertSame(['Bonjour.'], Texte::etapes("Bonjour.\n"));
    }

    public function testUnSeparateurDansUnBlocDeCodeNeCoupePas(): void
    {
        $texte = "Avant.\n\n```yaml\n---\ncle: valeur\n```\n\n---\n\nAprès.";

        self::assertSame(["Avant.\n\n```yaml\n---\ncle: valeur\n```", 'Après.'], Texte::etapes($texte));
    }

    public function testLesTestsAttendusSontSepares(): void
    {
        [$enonce, $tests] = Texte::separerTestsAttendus("Fais la page.\n\n## Tests attendus\n\n- Un\n- Deux\n\n## Pour aller plus loin\n\nPlus.\n");

        self::assertSame("Fais la page.\n\n## Pour aller plus loin\n\nPlus.", $enonce);
        self::assertSame("- Un\n- Deux", $tests);
    }

    public function testLeTitreTestsAttendusDansUnBlocDeCodeNEstPasLaSection(): void
    {
        $texte = "Avant.\n\n```markdown\n## Tests attendus\n\n- Un\n```\n\nFin.";

        [$enonce, $tests] = Texte::separerTestsAttendus($texte);

        self::assertSame($texte, $enonce);
        self::assertNull($tests);
    }

    public function testLaSectionTestsAttendusEnFinDeTexteVaJusquAuBout(): void
    {
        [$enonce, $tests] = Texte::separerTestsAttendus("Fais la page.\n\n## Tests attendus\n\n- Un\n- Deux\n");

        self::assertSame('Fais la page.', $enonce);
        self::assertSame("- Un\n- Deux", $tests);
    }

    public function testUnTitreDeNiveau1FermeLaSectionTestsAttendus(): void
    {
        [$enonce, $tests] = Texte::separerTestsAttendus("Fais la page.\n\n## Tests attendus\n\n- Un\n\n# Autre partie\n\nSuite.\n");

        self::assertSame("Fais la page.\n\n# Autre partie\n\nSuite.", $enonce);
        self::assertSame('- Un', $tests);
    }

    public function testUnTexteVideDonneUneSeuleEtapeVide(): void
    {
        self::assertSame([''], Texte::etapes(''));
        self::assertSame([''], Texte::etapes("\n---\n\n"));
    }

    public function testSansSectionTestsAttendusLEnonceResteEntier(): void
    {
        [$enonce, $tests] = Texte::separerTestsAttendus("Fais la page.\n");

        self::assertSame('Fais la page.', $enonce);
        self::assertNull($tests);
    }
}
