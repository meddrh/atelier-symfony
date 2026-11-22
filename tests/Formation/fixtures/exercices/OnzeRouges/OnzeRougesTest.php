<?php

use PHPUnit\Framework\TestCase;

/**
 * Fixture de ExerciceCommandTest : exactement 11 tests rouges. Jamais exécutée par la suite « formation »
 * (phpunit.xml.dist exclut tests/Formation/fixtures).
 */
class OnzeRougesTest extends TestCase
{
    /** @dataProvider rouges */
    public function testRouge(int $numero): void
    {
        self::fail('raison '.$numero);
    }

    public static function rouges(): iterable
    {
        for ($i = 1; $i <= 11; ++$i) {
            yield 'rouge '.$i => [$i];
        }
    }
}
