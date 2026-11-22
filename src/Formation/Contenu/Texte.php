<?php

namespace App\Formation\Contenu;

/**
 * Découpage du Markdown d'un bloc, pour l'affichage.
 */
final class Texte
{
    public const TITRE_TESTS_ATTENDUS = '## Tests attendus';

    /**
     * Étapes d'une démonstration : séparées par une ligne ne contenant que « --- »
     * (hors bloc de code). Le séparateur doit suivre une ligne vide, sinon Markdown le lit
     * comme le soulignement d'un titre.
     *
     * @return list<string>
     */
    public static function etapes(string $markdown): array
    {
        $etapes = [];
        $courante = [];
        $dansCode = false;
        foreach (preg_split('/\R/', $markdown) as $ligne) {
            if (str_starts_with(ltrim($ligne), '```')) {
                $dansCode = !$dansCode;
            }
            if (!$dansCode && 1 === preg_match('/^\s*---\s*$/', $ligne)) {
                $etapes[] = implode("\n", $courante);
                $courante = [];
                continue;
            }
            $courante[] = $ligne;
        }
        $etapes[] = implode("\n", $courante);

        $etapes = array_values(array_filter(array_map('trim', $etapes), static fn (string $e): bool => '' !== $e));

        return [] === $etapes ? [''] : $etapes;
    }

    /**
     * Sépare la section « Tests attendus » du reste de l'énoncé.
     *
     * @return array{0: string, 1: ?string} l'énoncé sans la section, puis le contenu de la section (null si absente)
     */
    public static function separerTestsAttendus(string $markdown): array
    {
        $zones = ['avant' => [], 'tests' => [], 'apres' => []];
        $zone = 'avant';
        $trouvee = false;
        $dansCode = false;
        foreach (preg_split('/\R/', $markdown) as $ligne) {
            if (str_starts_with(ltrim($ligne), '```')) {
                $dansCode = !$dansCode;
            }
            if (!$dansCode && 'avant' === $zone && trim($ligne) === self::TITRE_TESTS_ATTENDUS) {
                $zone = 'tests';
                $trouvee = true;
                continue;
            }
            if (!$dansCode && 'tests' === $zone && 1 === preg_match('/^#{1,2}\s/', $ligne)) {
                $zone = 'apres';
            }
            $zones[$zone][] = $ligne;
        }

        $enonce = trim(implode("\n", $zones['avant']));
        $suite = trim(implode("\n", $zones['apres']));
        if ('' !== $suite) {
            $enonce = '' === $enonce ? $suite : $enonce."\n\n".$suite;
        }

        return [$enonce, $trouvee ? trim(implode("\n", $zones['tests'])) : null];
    }
}
