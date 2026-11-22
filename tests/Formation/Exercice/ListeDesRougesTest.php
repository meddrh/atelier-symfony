<?php

namespace App\Tests\Formation\Exercice;

use App\Formation\Exercice\ListeDesRouges;
use PHPUnit\Framework\TestCase;

class ListeDesRougesTest extends TestCase
{
    private const DOSSIER = 'tests/Exercices/Essai';

    public function testLesDixPremiersRougesSontListesPuisLeNombreCache(): void
    {
        $echecs = array_map(static fn (int $i) => sprintf('testRouge%d : raison %d', $i, $i), range(1, 12));

        $liste = ListeDesRouges::depuis($echecs, self::DOSSIER);

        self::assertNotNull($liste);
        self::assertCount(10, $liste->lignes);
        self::assertSame('testRouge1 : raison 1', $liste->lignes[0]);
        self::assertSame('testRouge10 : raison 10', $liste->lignes[9]);
        self::assertSame(2, $liste->nombreCache);
        self::assertSame('… et 2 autres', $liste->suite());
    }

    public function testSousLaLimiteOuALaLimiteAucuneLigneDeSuite(): void
    {
        $trois = ListeDesRouges::depuis(['a', 'b', 'c'], self::DOSSIER);
        $dix = ListeDesRouges::depuis(array_map(static fn (int $i) => 'rouge'.$i, range(1, 10)), self::DOSSIER);

        self::assertSame(['a', 'b', 'c'], $trois?->lignes);
        self::assertSame(0, $trois->nombreCache);
        self::assertNull($trois->suite());
        self::assertCount(10, $dix->lignes);
        self::assertNull($dix->suite(), 'exactement dix rouges : tous listés, aucune ligne « … et »');
    }

    public function testUnSeulAutreEstAccordeAuSingulier(): void
    {
        $liste = ListeDesRouges::depuis(array_map(static fn (int $i) => 'rouge'.$i, range(1, 11)), self::DOSSIER);

        self::assertSame('… et 1 autre', $liste?->suite());
    }

    public function testSansListeIlNYAPasDeValeur(): void
    {
        self::assertNull(ListeDesRouges::depuis([], self::DOSSIER), 'plantage ou délai dépassé : le résumé tient lieu de ligne unique');
    }

    public function testLesTextesEtLaCommandeDeDetailSontCeuxDeLaConsoleEtDeLaPage(): void
    {
        $liste = ListeDesRouges::depuis(['a'], self::DOSSIER);

        self::assertSame('Tests à corriger :', $liste?->titre());
        self::assertSame('Pour voir le détail :', $liste->libelleDuDetail());
        self::assertSame('docker compose exec app php bin/phpunit tests/Exercices/Essai', $liste->commandeDuDetail);
    }
}
