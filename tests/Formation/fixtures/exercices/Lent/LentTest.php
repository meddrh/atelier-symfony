<?php

use PHPUnit\Framework\TestCase;

/**
 * Fixture de LanceurTestsTest : un test qui dort bien plus longtemps que le délai que le test donne
 * au lanceur. Jamais exécutée par la suite « formation » (phpunit.xml.dist exclut tests/Formation/fixtures).
 */
class LentTest extends TestCase
{
    public function testQuiDortTropLongtemps(): void
    {
        sleep(30);

        self::assertTrue(true);
    }
}
