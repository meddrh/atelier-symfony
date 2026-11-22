<?php

namespace App\Tests\Exercices\ServicesExternesCacheVerrou;

use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;

/**
 * La fabrique de verrous des tests de l'exercice 15 : elle garde une référence à CHAQUE verrou qu'elle crée.
 *
 * Un verrou créé avec la libération automatique (le réglage par défaut de createLock()) se rend tout seul quand son
 * objet disparaît, c'est-à-dire à la sortie de la commande. Ici, aucun objet verrou ne disparaît avant la fin du test :
 * un verrou que la commande n'a pas rendu elle-même (release(), dans un « finally ») reste pris, quels que soient les
 * arguments de createLock() choisis par le junior.
 */
class FabriqueDeVerrousGardes extends LockFactory
{
    /** @var list<LockInterface> */
    private array $verrousGardes = [];

    public function createLock(string $resource, ?float $ttl = 300.0, bool $autoRelease = true): LockInterface
    {
        return $this->verrousGardes[] = parent::createLock($resource, $ttl, $autoRelease);
    }
}
