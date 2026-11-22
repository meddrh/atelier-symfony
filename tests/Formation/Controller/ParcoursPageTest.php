<?php

namespace App\Tests\Formation\Controller;

use App\Tests\Formation\AvecProgressionVierge;
use App\Tests\Formation\PlateformeWebTestCase;

class ParcoursPageTest extends PlateformeWebTestCase
{
    use AvecProgressionVierge;
    use AvecLignesDeBloc;

    protected function setUp(): void
    {
        $this->repartirDeZero();
    }

    public function testLesStatutsSontAffiches(): void
    {
        $this->faire('rappel-a');

        $client = static::createClient();
        $crawler = $client->request('GET', '/formation/parcours');
        $texte = $crawler->filter('body')->text();

        self::assertResponseIsSuccessful();
        $page = $crawler->filter('body');
        self::assertSame('Fait', $this->etatDeLaLigne($page, 'Rappel HTTP', 'Rappel'));
        self::assertSame('En cours', $this->etatDeLaLigne($page, 'Préparer le premier sujet', 'Fiche de préparation'));
        self::assertSame('Verrouillé', $this->etatDeLaLigne($page, 'Démonstration', 'Démonstration'));
        self::assertSame('Verrouillé', $this->etatDeLaLigne($page, 'Exercice', 'Exercice'));
        self::assertSame('Verrouillé', $this->etatDeLaLigne($page, 'Préparer le deuxième sujet', 'Fiche de préparation'));
        self::assertSame('Verrouillé', $this->etatDeLaLigne($page, 'Exercice deux', 'Exercice'));
        self::assertStringContainsString('Premier sujet', $texte);
        self::assertStringContainsString('Deuxième sujet', $texte);
    }

    public function testUnBlocVerrouilleNEstPasUnLien(): void
    {
        $this->faire('rappel-a');

        $client = static::createClient();
        $crawler = $client->request('GET', '/formation/parcours');

        self::assertCount(1, $crawler->filter('a[href="/formation/lecon/rappel-a"]'));
        self::assertCount(1, $crawler->filter('a[href="/formation/lecon/s1-preparation"]'));
        self::assertCount(0, $crawler->filter('a[href="/formation/lecon/s1-demonstration"]'));
        self::assertCount(0, $crawler->filter('a[href="/formation/lecon/s1-exercice"]'));
        self::assertStringContainsString('Démonstration', $crawler->filter('body')->text());
    }
}
