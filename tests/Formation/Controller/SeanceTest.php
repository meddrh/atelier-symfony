<?php

namespace App\Tests\Formation\Controller;

use App\Tests\Formation\AvecProgressionVierge;
use App\Tests\Formation\PlateformeWebTestCase;

class SeanceTest extends PlateformeWebTestCase
{
    use AvecProgressionVierge;

    protected function setUp(): void
    {
        $this->repartirDeZero();
    }

    public function testAfficheLeBlocDuJourEtLaDuree(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/formation');

        self::assertResponseIsSuccessful();
        $texte = $crawler->filter('body')->text();
        self::assertStringContainsString('Aujourd\'hui · 15 min', $texte);
        self::assertStringContainsString('Rappel HTTP', $texte);
        self::assertStringContainsString('Niveau Débutant — 0 / 6 blocs', $texte);
        self::assertStringContainsString('Aucun badge', $texte);
        self::assertSame('/formation/lecon/rappel-a', $crawler->selectLink('Commencer la séance')->attr('href'));
        self::assertCount(1, $crawler->selectLink('Voir le parcours'));

        $html = mb_strtolower($client->getResponse()->getContent());
        self::assertStringNotContainsString('série', $html);
        self::assertStringNotContainsString('jours d\'affilée', $html);
    }

    public function testAfficheLeDernierBadgeEtLaProgression(): void
    {
        $this->faire('rappel-a', 's1-preparation', 's1-demonstration', 's1-exercice');

        $client = static::createClient();
        $texte = $client->request('GET', '/formation')->filter('body')->text();

        self::assertStringContainsString('Dernier badge obtenu', $texte);
        self::assertStringContainsString('Premier badge', $texte);
        self::assertStringContainsString('4 / 6 blocs', $texte);
        self::assertStringContainsString('67 %', $texte);
    }

    public function testAfficheContinuerLExerciceQuandUnLancementExiste(): void
    {
        $this->faire('rappel-a', 's1-preparation', 's1-demonstration');
        $this->lancer('s1-exercice');

        $client = static::createClient();
        $crawler = $client->request('GET', '/formation');

        self::assertCount(1, $crawler->selectLink('Continuer l\'exercice'));
        self::assertCount(0, $crawler->selectLink('Commencer la séance'));
        self::assertSame('/formation/lecon/s1-exercice', $crawler->selectLink('Continuer l\'exercice')->attr('href'));
    }
}
