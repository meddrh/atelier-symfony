<?php

namespace App\Tests\Formation\Squelette;

/** Une classe, interface, trait ou énumération telle que AnalyseurPhp la lit : noms résolus en noms complets. */
final class ClasseAnalysee
{
    /**
     * @param string                        $genre      class, interface, trait ou enum
     * @param list<string>                  $interfaces noms complets, triés
     * @param list<string>                  $attributs  noms complets des attributs de la classe, triés
     * @param array<string, MethodeAnalysee> $methodes   méthodes publiques ou protégées, par nom
     */
    public function __construct(
        public readonly string $nom,
        public readonly string $espace,
        public readonly string $genre,
        public readonly ?string $parent,
        public readonly array $interfaces,
        public readonly array $attributs,
        public readonly array $methodes,
    ) {
    }

    public function nomComplet(): string
    {
        return '' === $this->espace ? $this->nom : $this->espace.'\\'.$this->nom;
    }
}
