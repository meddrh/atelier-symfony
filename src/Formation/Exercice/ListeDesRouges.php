<?php

namespace App\Formation\Exercice;

/**
 * Ce que le junior lit quand des tests sont rouges, le même dans le terminal (bin/exercice) et sur la page de
 * l'exercice : au plus LIGNES_MAX tests rouges, le nombre de ceux qu'on ne détaille pas, et la commande qui montre
 * tout. La console et le gabarit lisent cette valeur ; les textes fixes ne sont écrits qu'ici.
 */
final class ListeDesRouges
{
    /** Nombre de tests rouges détaillés ; les suivants sont seulement comptés. */
    public const LIGNES_MAX = 10;

    private const TITRE = 'Tests à corriger :';
    private const LIBELLE_DU_DETAIL = 'Pour voir le détail :';

    /**
     * @param list<string> $lignes une ligne « nom du test : raison » par test rouge affiché (au plus LIGNES_MAX)
     */
    private function __construct(
        public readonly array $lignes,
        public readonly int $nombreCache,
        public readonly string $commandeDuDetail,
    ) {
    }

    /**
     * Null sans liste (plantage, délai dépassé, cause dans un fichier du junior) : le résumé du lancement tient alors
     * lieu de ligne unique.
     *
     * @param list<string> $echecs une ligne « nom du test : raison » par test rouge
     */
    public static function depuis(array $echecs, string $dossierDesTests): ?self
    {
        if ([] === $echecs) {
            return null;
        }

        return new self(
            \array_slice($echecs, 0, self::LIGNES_MAX),
            max(0, \count($echecs) - self::LIGNES_MAX),
            'docker compose exec app php bin/phpunit '.$dossierDesTests,
        );
    }

    public function titre(): string
    {
        return self::TITRE;
    }

    public function libelleDuDetail(): string
    {
        return self::LIBELLE_DU_DETAIL;
    }

    /** « … et N autres » (« … et 1 autre » au singulier) ; null quand tous les tests rouges sont détaillés. */
    public function suite(): ?string
    {
        if (0 === $this->nombreCache) {
            return null;
        }

        return sprintf('… et %d %s', $this->nombreCache, $this->nombreCache > 1 ? 'autres' : 'autre');
    }
}
