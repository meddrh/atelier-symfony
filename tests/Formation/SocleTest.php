<?php

namespace App\Tests\Formation;

use App\Formation\FormationKernel;

class SocleTest extends PlateformeWebTestCase
{
    public function testLeNoyauDemarreEnEnvironnementTest(): void
    {
        self::bootKernel();
        self::assertSame('test', self::$kernel->getEnvironment());
    }

    public function testLaPlateformeADonSonPropreNoyauEtSonCache(): void
    {
        self::bootKernel();

        self::assertInstanceOf(FormationKernel::class, self::$kernel);
        self::assertSame(\dirname(__DIR__, 2).'/var/cache/formation_test', self::$kernel->getCacheDir());
        self::assertSame(\dirname(__DIR__, 2).'/var/log', self::$kernel->getLogDir());
    }

    public function testLaPageFormationRepond(): void
    {
        self::ensureKernelShutdown();
        $client = static::createClient();
        $client->request('GET', '/formation');
        self::assertResponseIsSuccessful();
    }
}
