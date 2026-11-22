<?php

namespace App\Tests\Formation\Controller;

use App\Tests\Formation\AvecProgressionVierge;
use App\Tests\Formation\PlateformeWebTestCase;

class DureeTest extends PlateformeWebTestCase
{
    use AvecProgressionVierge;

    protected function setUp(): void
    {
        $this->repartirDeZero();
    }

    public function testChangerLaDureeA30(): void
    {
        $client = static::createClient();
        $client->request('GET', '/formation');
        $client->submitForm('30 min');

        self::assertResponseRedirects();
        $texte = $client->followRedirect()->filter('body')->text();
        self::assertStringContainsString('Aujourd\'hui · 30 min', $texte);
        self::assertSame(30, $this->progression()->duree());
    }

    public function testUneDureeInvalideEstRefusee(): void
    {
        $cas = ['hors liste' => '20', 'texte' => 'abc', 'vide' => '', 'décimale' => '30.5'];
        foreach ($cas as $nom => $minutes) {
            self::ensureKernelShutdown();
            $client = static::createClient();
            $crawler = $client->request('GET', '/formation');
            $jeton = $crawler->filter('form[action="/formation/duree"] input[name="_token"]')->first()->attr('value');

            $client->request('POST', '/formation/duree', ['minutes' => $minutes, '_token' => $jeton]);
            self::assertResponseRedirects(null, null, $nom);
            $texte = $client->followRedirect()->filter('body')->text();

            self::assertStringContainsString('Durée non prise en compte.', $texte, $nom);
            self::assertStringContainsString('Aujourd\'hui · 15 min', $texte, $nom);
            self::assertSame(15, $this->progression()->duree(), $nom);
        }
    }

    public function testSansJetonCsrfLaDureeNeChangePas(): void
    {
        $client = static::createClient();
        $client->request('POST', '/formation/duree', ['minutes' => '30']);

        self::assertResponseRedirects();
        $texte = $client->followRedirect()->filter('body')->text();
        self::assertStringContainsString('Durée non prise en compte.', $texte);
        self::assertSame(15, $this->progression()->duree());
    }

    public function testUnJetonForgeNeSuffitPas(): void
    {
        $client = static::createClient();
        $client->request('GET', '/formation');
        $client->request('POST', '/formation/duree', ['minutes' => '30', '_token' => 'jeton-forge']);

        self::assertSame(15, $this->progression()->duree());
    }
}
