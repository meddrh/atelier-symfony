<?php

namespace App\Tests\Formation\Exercice;

use App\Formation\Contenu\ChargeurCatalogue;
use App\Formation\Contenu\TypeBloc;
use App\Formation\Progression\ProgressionStore;

/**
 * Pour les tests lents sur une copie du projet : fait, dans la base de progression donnée, tous les blocs
 * du contenu réel qui précèdent un exercice.
 */
trait AvanceJusquALExercice
{
    /**
     * Fait tous les blocs qui précèdent l'exercice de ce rang (1 = le premier), exercices précédents compris ;
     * rend l'identifiant de cet exercice.
     */
    private function avancerJusquALExercice(string $base, int $rang = 1): string
    {
        $racine = \dirname(__DIR__, 3);
        $catalogue = (new ChargeurCatalogue($racine.'/contenu', $racine))->charger();
        $store = new ProgressionStore($base);
        $vus = 0;
        foreach ($catalogue->blocsOrdonnes() as $bloc) {
            if (TypeBloc::Exercice === $bloc->type && ++$vus === $rang) {
                return $bloc->id;
            }
            $store->marquerFait($bloc->id, new \DateTimeImmutable());
        }

        self::fail(sprintf('Le contenu ne comporte pas %d exercice(s).', $rang));
    }
}
