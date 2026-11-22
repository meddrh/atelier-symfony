<?php

namespace App\Formation\Contenu;

final class Bloc
{
    /**
     * @param list<string>                                                                 $programme    lignes du programme de référence couvertes
     * @param string|null                                                                  $dossierTests chemin relatif au projet (exercice seulement)
     * @param list<array{cle: string, libelle: string, aide: string, correction: string}> $champsFiche  (préparation seulement)
     */
    public function __construct(
        public readonly string $id,
        public readonly TypeBloc $type,
        public readonly string $titre,
        public readonly int $dureeMinutes,
        public readonly string $fichier,
        public readonly array $programme,
        public readonly ?string $dossierTests,
        public readonly array $champsFiche,
    ) {
    }
}
