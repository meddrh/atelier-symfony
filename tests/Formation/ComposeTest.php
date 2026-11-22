<?php

namespace App\Tests\Formation;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * La plateforme écrit la progression et affiche les erreurs détaillées du mode développement :
 * elle ne doit pas être joignable depuis le réseau local.
 */
class ComposeTest extends TestCase
{
    public function testLePortEstPublieSurLaBoucleLocaleSeulement(): void
    {
        $compose = Yaml::parseFile(__DIR__.'/../../compose.yaml');

        self::assertSame(['127.0.0.1:8000:8000'], $compose['services']['app']['ports']);
    }

    public function testLeServeurPasseParLeRouteurQuiSepareLaPlateformeDuJunior(): void
    {
        $compose = Yaml::parseFile(__DIR__.'/../../compose.yaml');

        self::assertSame('php -S 0.0.0.0:8000 -t public public/routeur.php', $compose['services']['app']['command']);
    }
}
