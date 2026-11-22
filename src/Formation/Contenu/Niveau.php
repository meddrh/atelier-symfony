<?php

namespace App\Formation\Contenu;

final class Niveau
{
    /**
     * @param list<Sujet> $sujets
     * @param list<Bloc>  $blocs  tous les blocs du niveau dans l'ordre du parcours, intercalaires compris
     */
    public function __construct(
        public readonly string $id,
        public readonly string $nom,
        public readonly string $sousTitre,
        public readonly Badge $badgeNiveau,
        public readonly array $sujets,
        public readonly array $blocs,
    ) {
    }
}
