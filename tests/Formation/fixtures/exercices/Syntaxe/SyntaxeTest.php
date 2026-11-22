<?php

use PHPUnit\Framework\TestCase;

/**
 * Fixture de LanceurTestsTest : ce fichier contient volontairement une erreur de syntaxe.
 * Jamais exécutée par la suite « formation » (phpunit.xml.dist exclut tests/Formation/fixtures).
 */
class SyntaxeTest extends TestCase
{
    public function testQuiNeSeraJamaisCharge(): void
    {
        self::assertTrue(true
    }
}
