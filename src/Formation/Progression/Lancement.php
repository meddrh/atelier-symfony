<?php

namespace App\Formation\Progression;

/**
 * Un lancement des tests d'un exercice (commande `bin/exercice test`).
 */
final class Lancement
{
    public function __construct(
        public readonly string $blocId,
        public readonly bool $vert,
        public readonly int $nbTests,
        public readonly int $nbReussis,
        public readonly string $resume,
        public readonly \DateTimeImmutable $date,
        /** @var list<string> une ligne « nom du test : raison » par test rouge ; vide pour un lancement vert ou enregistré avant cette liste */
        public readonly array $echecs = [],
    ) {
    }
}
