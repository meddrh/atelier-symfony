<?php

namespace App\Tests\Formation\Contenu;

use App\Formation\Contenu\ChargeurCatalogue;
use PHPUnit\Framework\TestCase;

/**
 * La progression du junior est indexée par l'identifiant des blocs : renommer ou supprimer un identifiant lui ferait
 * perdre ce qu'il a déjà fait. La liste gelée (fixtures/identifiants-de-blocs.txt) dit lesquels existent déjà.
 *
 * Un nouvel identifiant s'ajoute au contenu puis à cette liste ; un identifiant listé ne se retire jamais.
 */
class IdentifiantsDeBlocsTest extends TestCase
{
    private const LISTE = __DIR__.'/../fixtures/identifiants-de-blocs.txt';

    public function testChaqueIdentifiantGeleExisteEncoreDansLeCatalogue(): void
    {
        $racine = \dirname(__DIR__, 3);
        $catalogue = (new ChargeurCatalogue($racine.'/contenu', $racine))->charger();

        $geles = $this->identifiantsGeles();
        self::assertNotSame([], $geles, 'la liste gelée ne doit pas être vide');

        $disparus = array_values(array_filter($geles, static fn (string $id) => !$catalogue->existe($id)));
        self::assertSame(
            [],
            $disparus,
            'Identifiants de blocs renommés ou supprimés : '.implode(', ', $disparus).'. La progression du junior est indexée par identifiant : ne renomme et ne supprime jamais un bloc existant.',
        );
    }

    public function testChaqueIdentifiantDuCatalogueEstDansLaListeGelee(): void
    {
        $racine = \dirname(__DIR__, 3);
        $catalogue = (new ChargeurCatalogue($racine.'/contenu', $racine))->charger();

        $nouveaux = array_values(array_diff(
            array_map(static fn ($bloc) => $bloc->id, $catalogue->blocsOrdonnes()),
            $this->identifiantsGeles(),
        ));
        self::assertSame(
            [],
            $nouveaux,
            'Identifiants de blocs absents de la liste gelée : '.implode(', ', $nouveaux).'. Ajoute-les à tests/Formation/fixtures/identifiants-de-blocs.txt : ils ne pourront plus jamais être renommés ni supprimés.',
        );
    }

    public function testLaListeGeleeEstSansDoublonNiLigneVide(): void
    {
        $lignes = explode("\n", rtrim((string) file_get_contents(self::LISTE), "\n"));

        self::assertSame(array_values(array_unique($lignes)), $lignes, 'un identifiant par ligne, sans doublon');
        self::assertNotContains('', $lignes, 'aucune ligne vide');
    }

    /** @return list<string> */
    private function identifiantsGeles(): array
    {
        return is_file(self::LISTE) ? array_values(array_filter(array_map('trim', file(self::LISTE) ?: []))) : [];
    }
}
