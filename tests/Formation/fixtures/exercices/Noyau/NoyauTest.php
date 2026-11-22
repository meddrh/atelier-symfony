<?php

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Fixture de LanceurTestsTest : démarre le noyau, comme les vrais exercices. Elle échoue avec
 * « You must set the KERNEL_CLASS environment variable » si PHPUnit n'est pas lancé en environnement de test.
 * Jamais exécutée par la suite « formation » (phpunit.xml.dist exclut tests/Formation/fixtures).
 */
class NoyauTest extends KernelTestCase
{
    public function testLeNoyauDemarre(): void
    {
        self::bootKernel();

        self::assertSame('test', self::$kernel->getEnvironment());
    }
}
