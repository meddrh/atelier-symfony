<?php

namespace App\Tests\Formation\Squelette;

/** Une méthode publique ou protégée : sa signature normalisée (types résolus, espaces retirés) et ses attributs. */
final class MethodeAnalysee
{
    /** @param list<string> $attributs noms complets, triés */
    public function __construct(
        public readonly string $nom,
        /** Par exemple « function fiche(int $id): Symfony\Component\HttpFoundation\Response » (avec « protected » et « static » devant si besoin). */
        public readonly string $signature,
        public readonly array $attributs,
        /** Le corps contient autre chose que rien, un `return` d'une valeur simple ou un `throw new …` : c'est de la solution. */
        public readonly bool $corpsRempli = false,
    ) {
    }
}
