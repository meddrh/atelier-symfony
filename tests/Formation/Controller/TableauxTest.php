<?php

namespace App\Tests\Formation\Controller;

use App\Tests\Formation\AvecProgressionVierge;
use App\Tests\Formation\PlateformeWebTestCase;

/**
 * Le moteur Markdown de la plateforme rend les tableaux (GitHub Flavored Markdown), sans laisser passer le HTML brut du contenu.
 * Le contenu de test (fixtures/contenu-mini) reprend le tableau de l'exercice 01 du vrai parcours.
 */
class TableauxTest extends PlateformeWebTestCase
{
    use AvecProgressionVierge;

    protected function setUp(): void
    {
        $this->repartirDeZero();
        $this->faire('rappel-a', 's1-preparation', 's1-demonstration');
    }

    public function testUnTableauMarkdownEstRenduEnTableHtmlAvecSesEntetes(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/formation/lecon/s1-exercice');

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('.prose table'));
        self::assertSame(['Identifiant', 'Nom', 'Ville'], $crawler->filter('.prose table thead th')->each(static fn ($th): string => $th->text()));
        self::assertSame(['1', 'Dupont Informatique', 'Lyon'], $crawler->filter('.prose table tbody tr')->eq(0)->filter('td')->each(static fn ($td): string => $td->text()));
        self::assertStringNotContainsString('|---|', $crawler->filter('.prose')->text(), 'le tableau ne doit plus apparaître en texte brut');
    }

    public function testLeHtmlBrutDuContenuResteRetire(): void
    {
        $client = static::createClient();
        $client->request('GET', '/formation/lecon/s1-exercice');
        $prose = (string) $client->getCrawler()->filter('.prose')->first()->html();

        self::assertStringNotContainsString('<script', $prose);
        self::assertStringNotContainsString('<b>', $prose);
        self::assertStringContainsString('gras brut', $prose, 'le texte reste, seules les balises sont retirées');
    }
}
