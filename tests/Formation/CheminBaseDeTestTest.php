<?php

namespace App\Tests\Formation;

use App\Formation\Progression\ProgressionStore;

/**
 * Le chemin de la base de progression des tests a une seule source : FORMATION_DB, lu par le
 * service ProgressionStore. Le trait AvecProgressionVierge ne le recopie pas en dur.
 */
class CheminBaseDeTestTest extends PlateformeWebTestCase
{
    use AvecProgressionVierge;

    public function testLeTraitLitLeCheminDuServiceProgressionStore(): void
    {
        self::bootKernel();
        $duService = self::getContainer()->get(ProgressionStore::class)->chemin();
        self::ensureKernelShutdown();

        self::assertSame($duService, $this->cheminBaseDeTest());
        self::assertStringEndsWith('/var/formation-test.sqlite', $duService, 'la base de test est bien celle de .env.test');
    }

    public function testLeTraitNeContientAucunCheminEnDur(): void
    {
        self::assertStringNotContainsString('.sqlite', (string) file_get_contents(__DIR__.'/AvecProgressionVierge.php'));
    }
}
