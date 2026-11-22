<?php

use PHPUnit\Framework\TestCase;

/**
 * Fixture de LanceurTestsTest : un test qui passe, un qui échoue. Jamais exécutée par la suite
 * « formation » (phpunit.xml.dist exclut tests/Formation/fixtures).
 */
class RougeTest extends TestCase
{
    public function testQuiPasse(): void
    {
        self::assertTrue(true);
    }

    public function testQuiEchoue(): void
    {
        self::assertSame('attendu', 'obtenu');
    }
}
