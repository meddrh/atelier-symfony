<?php

namespace App\Tests\Formation;

use App\Formation\Contenu\Catalogue;
use App\Formation\Contenu\ChargeurCatalogue;
use App\Formation\Parcours\Parcours;
use App\Formation\Progression\ProgressionStore;

/**
 * Pour les tests fonctionnels (WebTestCase) : la base de progression de l'environnement de test
 * est supprimée avant chaque test, puis relue de l'extérieur.
 * À appeler depuis setUp() : `$this->repartirDeZero();`.
 *
 * Source unique du chemin : FORMATION_DB (.env.test), tel que le service ProgressionStore le résout.
 */
trait AvecProgressionVierge
{
    private static ?string $cheminDeLaBaseDeTest = null;

    private function cheminBaseDeTest(): string
    {
        if (null === self::$cheminDeLaBaseDeTest) {
            self::bootKernel();
            self::$cheminDeLaBaseDeTest = self::getContainer()->get(ProgressionStore::class)->chemin();
            self::ensureKernelShutdown();
        }

        return self::$cheminDeLaBaseDeTest;
    }

    private function repartirDeZero(): void
    {
        if (is_file($this->cheminBaseDeTest())) {
            unlink($this->cheminBaseDeTest());
        }
    }

    private function progression(): ProgressionStore
    {
        return new ProgressionStore($this->cheminBaseDeTest());
    }

    private function catalogue(): Catalogue
    {
        return (new ChargeurCatalogue(__DIR__.'/fixtures/contenu-mini', \dirname(__DIR__, 2)))->charger();
    }

    private function parcours(): Parcours
    {
        return new Parcours($this->catalogue(), $this->progression());
    }

    private function faire(string ...$blocIds): void
    {
        $store = $this->progression();
        foreach ($blocIds as $id) {
            $store->marquerFait($id, new \DateTimeImmutable());
        }
    }

    private function lancer(string $blocId, bool $vert = false): void
    {
        $this->progression()->enregistrerLancement(FabriqueLancement::creer($blocId, $vert));
    }
}
