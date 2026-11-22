<?php

namespace App\Formation\Contenu;

final class Sujet
{
    /**
     * @param list<Bloc> $blocs
     */
    public function __construct(
        public readonly string $id,
        public readonly int $numero,
        public readonly string $titre,
        public readonly ?Badge $badge,
        public readonly array $blocs,
    ) {
    }
}
