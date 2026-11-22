<?php

use PHPUnit\Framework\TestCase;

/**
 * Fixture de ExerciceCommandTest : un test rouge dont la raison ressemble à une balise de la console. Jamais exécutée
 * par la suite « formation » (phpunit.xml.dist exclut tests/Formation/fixtures).
 */
class RaisonBaliseeTest extends TestCase
{
    public function testRaisonBalisee(): void
    {
        self::fail('<info>x</info>');
    }
}
