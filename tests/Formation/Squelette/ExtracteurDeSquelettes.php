<?php

namespace App\Tests\Formation\Squelette;

use App\Formation\Contenu\ChargeurCatalogue;
use App\Formation\Contenu\TypeBloc;

/**
 * Lit la section « Squelette des fichiers » des exercices du contenu réel et en rend les blocs.
 * Partagé par SqueletteTest (rapide) et SqueletteNeSuffitPasTest (lent) : une seule lecture de la convention.
 *
 * Convention (docs/superpowers/plans/2026-11-22-lot5-tableaux-et-squelettes.md) : la section commence par
 * « ## Squelette des fichiers » et finit au titre suivant de niveau 1 ou 2 ; chaque bloc ```php est soit
 * un fichier complet (`<?php` puis `// src/…/X.php`), soit un extrait de méthodes nouvelles (`// à ajouter dans src/…/X.php`),
 * soit un extrait de méthodes existantes dont la forme change (`// à modifier dans src/…/X.php`).
 */
final class ExtracteurDeSquelettes
{
    public const TITRE_SECTION = '## Squelette des fichiers';

    private const RACINE = __DIR__.'/../../..';

    /** @return list<ExerciceDeSujet> les exercices du parcours, dans l'ordre */
    public static function exercices(): array
    {
        $racine = (string) realpath(self::RACINE);
        $catalogue = (new ChargeurCatalogue($racine.'/contenu', $racine))->charger();
        $exercices = [];
        $precedent = null;
        foreach ($catalogue->blocsOrdonnes() as $bloc) {
            if (TypeBloc::Exercice !== $bloc->type) {
                continue;
            }
            $sujet = $catalogue->sujetDuBloc($bloc->id)?->id ?? throw new \LogicException('Exercice sans sujet : '.$bloc->id);
            $exercices[] = new ExerciceDeSujet($sujet, $bloc->id, \count($exercices) + 1, (string) $bloc->dossierTests, $catalogue->contenuDuBloc($bloc->id), $precedent);
            $precedent = $sujet;
        }

        return $exercices;
    }

    /** @return list<BlocSquelette> les blocs de tous les exercices qui ont la section */
    public static function tousLesBlocs(): array
    {
        $blocs = [];
        foreach (self::exercices() as $exercice) {
            array_push($blocs, ...self::blocsDe($exercice->markdown, $exercice->sujet));
        }

        return $blocs;
    }

    /** @return list<ExerciceDeSujet> les exercices qui n'ont pas (encore) la section */
    public static function exercicesSansSection(): array
    {
        return array_values(array_filter(self::exercices(), static fn (ExerciceDeSujet $e): bool => null === self::section($e->markdown)));
    }

    /** Le texte de la section (sans son titre), ou null si l'exercice n'en a pas. */
    public static function section(string $markdown): ?string
    {
        $lignes = [];
        $dedans = false;
        $dansCode = false;
        foreach (preg_split('/\R/', $markdown) as $ligne) {
            if (str_starts_with(ltrim($ligne), '```')) {
                $dansCode = !$dansCode;
            }
            if (!$dansCode && !$dedans && trim($ligne) === self::TITRE_SECTION) {
                $dedans = true;
                continue;
            }
            if ($dedans && !$dansCode && 1 === preg_match('/^#{1,2}\s/', $ligne)) {
                break;
            }
            if ($dedans) {
                $lignes[] = $ligne;
            }
        }

        return $dedans ? implode("\n", $lignes) : null;
    }

    /** @return list<BlocSquelette> vide si le Markdown n'a pas la section */
    public static function blocsDe(string $markdown, string $sujet): array
    {
        $section = self::section($markdown);
        if (null === $section) {
            return [];
        }

        $blocs = [];
        $code = null;
        foreach (preg_split('/\R/', $section) as $ligne) {
            if (null === $code && 1 === preg_match('/^```php\s*$/', $ligne)) {
                $code = [];
            } elseif (null !== $code && '```' === rtrim($ligne)) {
                $blocs[] = self::classer($sujet, implode("\n", $code));
                $code = null;
            } elseif (null !== $code) {
                $code[] = $ligne;
            }
        }
        if (null !== $code) {
            $blocs[] = new BlocSquelette($sujet, BlocSquelette::INVALIDE, '', implode("\n", $code), 'bloc de code jamais refermé');
        }
        if ([] === $blocs) {
            $blocs[] = new BlocSquelette($sujet, BlocSquelette::INVALIDE, '', '', 'la section ne contient aucun bloc ```php');
        }

        return $blocs;
    }

    private static function classer(string $sujet, string $code): BlocSquelette
    {
        $lignes = preg_split('/\R/', $code);
        $invalide = static fn (string $erreur): BlocSquelette => new BlocSquelette($sujet, BlocSquelette::INVALIDE, '', $code, $erreur);

        if ('<?php' === rtrim($lignes[0])) {
            if (1 === preg_match('#^// ((?:src|tests)/\S+\.php)\s*$#', $lignes[1] ?? '', $m)) {
                return new BlocSquelette($sujet, BlocSquelette::COMPLET, $m[1], $code);
            }
            $genre = self::genreDExtrait($lignes[1] ?? '', $m);
            if (null !== $genre) {
                return new BlocSquelette($sujet, $genre, $m[1], $code);
            }

            return $invalide('la deuxième ligne doit être « // src/…/X.php » (ou « // tests/…/X.php ») pour un fichier complet, « // à ajouter dans src/…/X.php » pour une méthode nouvelle, ou « // à modifier dans src/…/X.php » pour une méthode qui existe déjà');
        }
        $genre = self::genreDExtrait($lignes[0], $m);
        if (null !== $genre) {
            return new BlocSquelette($sujet, $genre, $m[1], $code);
        }

        return $invalide('la première ligne doit être « <?php » (fichier complet), « // à ajouter dans src/…/X.php » (méthode nouvelle) ou « // à modifier dans src/…/X.php » (méthode qui existe déjà)');
    }

    /**
     * @param array<int, string> $m reçoit le chemin en $m[1]
     *
     * @return BlocSquelette::EXTRAIT|BlocSquelette::MODIFICATION|null
     */
    private static function genreDExtrait(string $ligne, ?array &$m): ?string
    {
        if (1 === preg_match('#^// à ajouter dans ((?:src|tests)/\S+\.php)\s*$#u', $ligne, $m)) {
            return BlocSquelette::EXTRAIT;
        }
        if (1 === preg_match('#^// à modifier dans ((?:src|tests)/\S+\.php)\s*$#u', $ligne, $m)) {
            return BlocSquelette::MODIFICATION;
        }

        return null;
    }
}
