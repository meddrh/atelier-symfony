<?php

namespace App\Formation\Parcours;

use App\Formation\Contenu\Bloc;

/**
 * Les blocs proposés pour la séance du jour.
 */
final class Seance
{
    /**
     * @param list<Bloc> $blocs
     * @param bool       $exerciceEnCours au moins un lancement sur l'exercice affiché
     */
    public function __construct(
        public readonly array $blocs,
        public readonly bool $exerciceEnCours,
        public readonly bool $parcoursTermine,
    ) {
    }
}
