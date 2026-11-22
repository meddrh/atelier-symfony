<?php

namespace App\Formation\Contenu;

final class Badge
{
    public function __construct(
        public readonly string $id,
        public readonly string $nom,
        public readonly string $description,
    ) {
    }
}
