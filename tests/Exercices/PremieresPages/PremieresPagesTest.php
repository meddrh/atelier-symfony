<?php

namespace App\Tests\Exercices\PremieresPages;

use App\Tests\Exercices\ExerciceTestCase;

class PremieresPagesTest extends ExerciceTestCase
{
    public function testLaPageDesMaterielsDeLaDemonstrationRepond(): void
    {
        $client = static::createClient();
        $client->request('GET', '/materiels');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Ordinateur portable');
    }

    public function testLaFicheDUnMaterielAfficheSonNom(): void
    {
        $client = static::createClient();
        $client->request('GET', '/materiels/2');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Écran 24 pouces');
    }

    public function testUnMaterielInconnuRenvoie404(): void
    {
        $client = static::createClient();
        $client->request('GET', '/materiels/999');

        self::assertResponseStatusCodeSame(404);
    }

    public function testLaListeDesFournisseursRepondEtMontreTousLesNoms(): void
    {
        $client = static::createClient();
        $client->request('GET', '/fournisseurs');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Dupont Informatique');
        self::assertSelectorTextContains('body', 'Bureau Express');
        self::assertSelectorTextContains('body', 'Papeterie Martin');
    }

    public function testChaqueFournisseurDeLaListeMeneASaFiche(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/fournisseurs');

        $liens = $crawler->filter('a[href^="/fournisseurs/"]');
        self::assertCount(3, $liens, 'La liste doit contenir un lien par fournisseur, vers /fournisseurs/{id}.');
    }

    public function testLaFicheDUnFournisseurAfficheSonNom(): void
    {
        $client = static::createClient();
        $client->request('GET', '/fournisseurs/2');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Bureau Express');
    }

    public function testUnFournisseurInconnuRenvoie404(): void
    {
        $client = static::createClient();
        $client->request('GET', '/fournisseurs/999');

        self::assertResponseStatusCodeSame(404);
    }

    public function testUnIdentifiantNonNumeriqueRenvoie404(): void
    {
        $client = static::createClient();
        $client->request('GET', '/fournisseurs/abc');

        self::assertResponseStatusCodeSame(404);
    }

    public function testLesPagesHeritentDuGabaritDeBase(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/fournisseurs');

        self::assertSame(
            1,
            $crawler->filter('html > head > title')->count(),
            'La page doit être une page HTML complète (gabarit hérité de base.html.twig).'
        );
    }
}
