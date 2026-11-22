<?php

namespace App\Tests\Formation\Controller;

use App\Tests\Formation\AvecProgressionVierge;
use App\Tests\Formation\PlateformeWebTestCase;

class BadgesPageTest extends PlateformeWebTestCase
{
    use AvecProgressionVierge;

    protected function setUp(): void
    {
        $this->repartirDeZero();
    }

    public function testBadgesObtenusEtAObtenir(): void
    {
        $this->faire('rappel-a', 's1-preparation', 's1-demonstration', 's1-exercice');

        $client = static::createClient();
        $crawler = $client->request('GET', '/formation/badges');

        self::assertResponseIsSuccessful();
        // Fixture : deux badges de sujet et un badge de niveau.
        self::assertStringContainsString('1 / 3 obtenus', $crawler->filter('body')->text());
        self::assertCount(1, $crawler->filter('.badge:not(.badge--a-obtenir)'));
        self::assertCount(2, $crawler->filter('.badge.badge--a-obtenir'));
        self::assertStringContainsString('Premier badge', $crawler->filter('.badge:not(.badge--a-obtenir)')->text());
        self::assertStringContainsString('À obtenir', $crawler->filter('.badge.badge--a-obtenir')->first()->text());
        self::assertStringContainsString('Niveau Débutant', $crawler->filter('body')->text());
    }

    public function testAucunBadgeAuDepart(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/formation/badges');

        self::assertStringContainsString('0 / 3 obtenus', $crawler->filter('body')->text());
        self::assertCount(3, $crawler->filter('.badge.badge--a-obtenir'));
    }
}
