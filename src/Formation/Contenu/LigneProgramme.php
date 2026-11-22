<?php

namespace App\Formation\Contenu;

final class LigneProgramme
{
    public function __construct(
        public readonly string $id,
        public readonly string $sectionId,
        public readonly string $section,
        public readonly bool $horsProgramme,
        public readonly string $libelle,
    ) {
    }
}
