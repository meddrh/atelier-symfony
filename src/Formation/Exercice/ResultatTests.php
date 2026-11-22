<?php

namespace App\Formation\Exercice;

/**
 * Résultat d'un lancement des tests d'un exercice.
 */
final class ResultatTests
{
    /**
     * @param string       $resume 1re ligne d'échec, « Aucun test exécuté. » ou « Tous les tests passent. »
     * @param list<string> $echecs une ligne « nom du test : raison » par test rouge, dans l'ordre d'exécution
     */
    public function __construct(
        public readonly bool $vert,
        public readonly int $nbTests,
        public readonly int $nbReussis,
        public readonly string $resume,
        public readonly array $echecs = [],
    ) {
    }
}
