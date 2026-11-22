<?php

namespace App\Tests\Formation\Squelette;

/** Ce que AnalyseurPhp lit d'un fichier PHP : espace de noms, importations et classes déclarées. */
final class FichierAnalyse
{
    /**
     * @param array<string, string>  $importations alias en minuscules => nom complet
     * @param list<ClasseAnalysee>   $classes
     */
    public function __construct(
        public readonly string $espace,
        public readonly array $importations,
        public readonly array $classes,
    ) {
    }
}
