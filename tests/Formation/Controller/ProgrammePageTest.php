<?php

namespace App\Tests\Formation\Controller;

use App\Tests\Formation\AvecProgressionVierge;
use App\Tests\Formation\PlateformeWebTestCase;

class ProgrammePageTest extends PlateformeWebTestCase
{
    use AvecProgressionVierge;

    protected function setUp(): void
    {
        $this->repartirDeZero();
    }

    /**
     * Fixture : section « HTTP » (http.requete, http.codes) et section hors programme (hp.doctrine).
     * s1-demonstration cite http.codes ; rappel-a cite http.requete.
     */
    public function testCompteurParSection(): void
    {
        $this->faire('rappel-a', 's1-preparation', 's1-demonstration');

        $client = static::createClient();
        $crawler = $client->request('GET', '/formation/programme');

        self::assertResponseIsSuccessful();
        $sections = $crawler->filter('.section');
        self::assertCount(2, $sections);
        self::assertStringContainsString('2 / 2', $sections->eq(0)->filter('summary')->text());
        self::assertStringContainsString('0 / 1', $sections->eq(1)->filter('summary')->text());
        // Compteur global : 2 lignes couvertes sur 3.
        self::assertStringContainsString('2 / 3', $crawler->filter('body')->text());
    }

    public function testUneSeuleLigneCouverte(): void
    {
        $this->faire('rappel-a');

        $client = static::createClient();
        $crawler = $client->request('GET', '/formation/programme');

        self::assertStringContainsString('1 / 2', $crawler->filter('.section')->eq(0)->filter('summary')->text());
        self::assertCount(1, $crawler->filter('.ligne--couverte'));
    }

    public function testLaSectionHorsProgrammeEstAffichee(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/formation/programme');

        self::assertStringContainsString('Hors programme : Doctrine, API Platform', $crawler->filter('body')->text());
        self::assertStringContainsString('Doctrine ORM', $crawler->filter('body')->text());
        // Pas de case cliquable : la couverture découle des blocs faits.
        self::assertCount(0, $crawler->filter('input[type="checkbox"]'));
    }
}
