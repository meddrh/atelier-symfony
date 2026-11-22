<?php

namespace App\Tests\Formation;

use App\Formation\Progression\Lancement;

/**
 * Source unique des lancements de tests fabriqués pour les tests : 2 tests, tous réussis si vert, un seul sinon.
 */
final class FabriqueLancement
{
    /**
     * @param string|null $date   « maintenant » par défaut
     * @param string|null $resume « Tous les tests passent. » si vert, « Un test échoue. » sinon, par défaut
     * @param list<string> $echecs une ligne par test rouge (aucune par défaut : le résumé tient lieu de ligne)
     */
    public static function creer(string $blocId, bool $vert, ?string $date = null, ?string $resume = null, array $echecs = []): Lancement
    {
        return new Lancement(
            $blocId,
            $vert,
            2,
            $vert ? 2 : 1,
            $resume ?? ($vert ? 'Tous les tests passent.' : 'Un test échoue.'),
            new \DateTimeImmutable($date ?? 'now'),
            $echecs,
        );
    }
}
