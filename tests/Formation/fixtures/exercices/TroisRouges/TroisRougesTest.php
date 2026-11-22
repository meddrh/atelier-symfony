<?php

use PHPUnit\Framework\TestCase;

/**
 * Fixture de LanceurTestsTest : un test qui passe, trois qui échouent. Jamais exécutée par la suite
 * « formation » (phpunit.xml.dist exclut tests/Formation/fixtures).
 */
class TroisRougesTest extends TestCase
{
    public function testQuiPasse(): void
    {
        self::assertTrue(true);
    }

    public function testPremierRouge(): void
    {
        self::assertSame('a', 'b');
    }

    public function testDeuxiemeRouge(): void
    {
        self::assertSame(1, 2);
    }

    public function testTroisiemeRouge(): void
    {
        self::fail('troisieme raison');
    }
}
