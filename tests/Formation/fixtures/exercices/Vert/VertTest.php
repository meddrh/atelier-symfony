<?php

use PHPUnit\Framework\TestCase;

/**
 * Fixture de LanceurTestsTest : deux tests qui passent. Jamais exécutée par la suite « formation »
 * (phpunit.xml.dist exclut tests/Formation/fixtures).
 */
class VertTest extends TestCase
{
    public function testPremierQuiPasse(): void
    {
        self::assertTrue(true);
    }

    public function testSecondQuiPasse(): void
    {
        self::assertSame(4, 2 + 2);
    }
}
