<?php

namespace App\Formation\Export;

/**
 * Noms des fichiers du site exporté : un seul dossier, sans sous-dossier de pages, donc des liens relatifs
 * réduits à un nom de fichier (valables à la racine d'un domaine comme ouverts depuis n'importe quel dossier).
 */
final class NomsDePages
{
    public const ACCUEIL = 'index.html';
    public const BADGES = 'badges.html';
    public const PROGRAMME = 'programme.html';

    public static function bloc(string $blocId): string
    {
        return sprintf('lecon-%s.html', $blocId);
    }

    /** La première étape est la page du bloc ; les suivantes ont la leur. */
    public static function etape(string $blocId, int $etape): string
    {
        return $etape <= 1 ? self::bloc($blocId) : sprintf('lecon-%s-etape-%d.html', $blocId, $etape);
    }

    /**
     * Fichier de la page qui répond, sur la plateforme, à une route : le même appel de gabarit sert aux deux.
     * La séance du jour n'existe pas dans l'export : l'accueil, qui est le parcours, la remplace.
     *
     * @param array<string, mixed> $parametres
     */
    public static function depuisRoute(string $route, array $parametres): string
    {
        return match ($route) {
            'formation_seance', 'formation_parcours' => self::ACCUEIL,
            'formation_badges' => self::BADGES,
            'formation_programme' => self::PROGRAMME,
            'formation_lecon' => self::etape((string) $parametres['blocId'], (int) ($parametres['etape'] ?? 1)),
            default => throw new \InvalidArgumentException(sprintf('Aucune page exportée pour la route « %s ».', $route)),
        };
    }
}
