<?php

namespace App\Tests\Formation\Squelette;

/**
 * Un bloc de code de la section « Squelette des fichiers » d'un exercice.
 *
 * - complet : un fichier PHP entier (`<?php`, puis `// src/…/X.php`, puis `namespace …`) ;
 * - extrait : une ou plusieurs méthodes à ajouter à une classe existante (`// à ajouter dans src/…/X.php`, sans `namespace`) ;
 * - modification : une ou plusieurs méthodes qui existent déjà dans la classe et dont la forme change (`// à modifier dans src/…/X.php`, sans `namespace`) ;
 * - invalide : le bloc ne suit pas la convention ; $erreur dit pourquoi.
 */
final class BlocSquelette
{
    public const COMPLET = 'complet';
    public const EXTRAIT = 'extrait';
    public const MODIFICATION = 'modification';
    public const INVALIDE = 'invalide';

    public function __construct(
        public readonly string $sujet,
        public readonly string $type,
        /** Chemin relatif au projet (`src/Controller/X.php`), vide si le bloc est invalide. */
        public readonly string $chemin,
        /** Le code tel qu'écrit dans l'énoncé. */
        public readonly string $code,
        public readonly ?string $erreur = null,
    ) {
    }

    /** Libellé court et unique par bloc, pour nommer un cas de test. */
    public function libelle(): string
    {
        if ('' === $this->chemin) {
            return $this->sujet.' (bloc invalide)';
        }

        // Un même fichier peut avoir un extrait « à modifier » et un extrait « à ajouter » : le genre les distingue.
        $genre = match ($this->type) {
            self::EXTRAIT => ' (à ajouter)',
            self::MODIFICATION => ' (à modifier)',
            default => '',
        };

        return $this->sujet.' '.$this->chemin.$genre;
    }
}
