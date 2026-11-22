<?php

namespace App\Tests\Formation\Exercice;

use App\Formation\Exercice\ResultatTests;
use PHPUnit\Framework\TestCase;

/** ResultatTests n'est qu'une donnée : l'affichage des rouges est dans ListeDesRouges. */
class ResultatTestsTest extends TestCase
{
    public function testUnResultatPorteSesDonneesSansRienSavoirDeLAffichage(): void
    {
        $resultat = new ResultatTests(false, 3, 1, 'a', ['a', 'b']);

        self::assertFalse($resultat->vert);
        self::assertSame(3, $resultat->nbTests);
        self::assertSame(1, $resultat->nbReussis);
        self::assertSame('a', $resultat->resume);
        self::assertSame(['a', 'b'], $resultat->echecs);
        self::assertSame([], (new ResultatTests(true, 1, 1, 'ok'))->echecs);
    }

    public function testLesDonneesNeDependentPasDeLaPresentation(): void
    {
        foreach ([ResultatTests::class, \App\Formation\Progression\Lancement::class] as $classe) {
            $source = (string) file_get_contents((new \ReflectionClass($classe))->getFileName());
            self::assertStringNotContainsString('App\\Formation\\Exercice\\ListeDesRouges', $source, $classe.' ne doit pas importer ListeDesRouges');
            self::assertFalse((new \ReflectionClass($classe))->hasMethod('lignesRouges'), $classe.' ne rend plus de lignes à afficher');
        }
    }
}
