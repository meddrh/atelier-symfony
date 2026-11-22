<?php

namespace App\Tests\Formation\Squelette;

/**
 * Remplace, dans le fichier d'une classe, des méthodes par celles d'un extrait « à modifier » : ce que fait le junior
 * quand il adapte la signature (et l'attribut) d'une méthode qu'il a déjà écrite. L'ancienne méthode part en entier,
 * attributs compris ; les autres méthodes du fichier ne bougent pas.
 *
 * Lecture par les jetons : les emplacements sont des positions en octets, de début de ligne jusqu'à l'accolade fermante.
 */
final class RemplaceurDeMethodes
{
    private const MODIFICATEURS = [\T_PUBLIC, \T_PROTECTED, \T_PRIVATE, \T_STATIC, \T_FINAL, \T_ABSTRACT, \T_READONLY];

    /**
     * @throws \LogicException si une méthode de l'extrait n'existe pas dans le fichier
     */
    public static function remplacerDansFichier(string $source, string $extrait): string
    {
        $habillage = "<?php\nclass ExtraitAAnalyser\n{\n".self::sansEntete($extrait)."\n}\n";
        $nouvelles = self::emplacements($habillage);
        $anciennes = self::emplacements($source);

        // De la fin vers le début, pour que les positions déjà calculées restent justes.
        $remplacements = [];
        foreach ($nouvelles as $nom => [$debut, $fin]) {
            if (!isset($anciennes[$nom])) {
                throw new \LogicException(sprintf('la méthode %s() n\'existe pas dans le fichier de départ : impossible de la modifier', $nom));
            }
            $remplacements[$anciennes[$nom][0]] = [$anciennes[$nom][1], substr($habillage, $debut, $fin - $debut)];
        }
        krsort($remplacements);
        foreach ($remplacements as $debut => [$fin, $texte]) {
            $source = substr($source, 0, $debut).$texte.substr($source, $fin);
        }

        return $source;
    }

    /** Retire l'éventuel `<?php` et la ligne « // à modifier dans … » : il reste les méthodes. */
    public static function sansEntete(string $extrait): string
    {
        $extrait = preg_replace('/\A<\?php[^\n]*\n/', '', $extrait) ?? $extrait;

        return preg_replace('#\A// à (?:modifier|ajouter) dans [^\n]*\n#u', '', $extrait) ?? $extrait;
    }

    /**
     * @return array<string, array{int, int}> nom de méthode => [début de ligne du premier attribut ou modificateur, fin après l'accolade fermante]
     */
    private static function emplacements(string $code): array
    {
        $jetons = \PhpToken::tokenize($code);
        $n = \count($jetons);
        $i = 0;
        while ($i < $n && !$jetons[$i]->is([\T_CLASS, \T_TRAIT])) {
            ++$i;
        }
        while ($i < $n && '{' !== $jetons[$i]->text) {
            ++$i;
        }

        $emplacements = [];
        $profondeur = 0;
        for (; $i < $n; ++$i) {
            $jeton = $jetons[$i];
            if ($jeton->is(\T_CURLY_OPEN) || $jeton->is(\T_DOLLAR_OPEN_CURLY_BRACES) || '{' === $jeton->text) {
                ++$profondeur;
            } elseif ('}' === $jeton->text) {
                --$profondeur;
            } elseif (1 === $profondeur && $jeton->is(\T_FUNCTION)) {
                $nom = null;
                for ($j = $i + 1; $j < $n; ++$j) {
                    if ($jetons[$j]->is(\T_STRING)) {
                        $nom = $jetons[$j]->text;
                        break;
                    }
                }
                $fin = self::finDeLaMethode($jetons, $i);
                if (null !== $nom && null !== $fin) {
                    $emplacements[$nom] = [self::debutDeLaMethode($jetons, $i, $code), $fin];
                    // Saute le corps : ses accolades ne comptent pas dans la profondeur de la classe.
                    while ($i < $n && $jetons[$i]->pos + \strlen($jetons[$i]->text) < $fin) {
                        ++$i;
                    }
                }
            }
        }

        return $emplacements;
    }

    /** Position, en octets, après l'accolade fermante du corps (ou le point-virgule d'une méthode abstraite). */
    private static function finDeLaMethode(array $jetons, int $i): ?int
    {
        $n = \count($jetons);
        $parentheses = 0;
        for ($j = $i; $j < $n; ++$j) {
            $texte = $jetons[$j]->text;
            if ('(' === $texte) {
                ++$parentheses;
            } elseif (')' === $texte) {
                --$parentheses;
            } elseif (0 === $parentheses && ';' === $texte) {
                return $jetons[$j]->pos + 1;
            } elseif (0 === $parentheses && '{' === $texte) {
                $accolades = 0;
                for (; $j < $n; ++$j) {
                    $t = $jetons[$j];
                    if ($t->is(\T_CURLY_OPEN) || $t->is(\T_DOLLAR_OPEN_CURLY_BRACES) || '{' === $t->text) {
                        ++$accolades;
                    } elseif ('}' === $t->text && 0 === --$accolades) {
                        return $t->pos + 1;
                    }
                }

                return null;
            }
        }

        return null;
    }

    /** Début de ligne du premier modificateur ou attribut qui précède `function`, en remontant. */
    private static function debutDeLaMethode(array $jetons, int $i, string $code): int
    {
        $debut = $i;
        $j = $i - 1;
        while ($j >= 0) {
            $jeton = $jetons[$j];
            if ($jeton->isIgnorable()) {
                --$j;
            } elseif ($jeton->is(self::MODIFICATEURS)) {
                $debut = $j;
                --$j;
            } elseif (']' === $jeton->text) {
                $crochets = 0;
                for (; $j >= 0; --$j) {
                    if (']' === $jetons[$j]->text) {
                        ++$crochets;
                    } elseif ('[' === $jetons[$j]->text || $jetons[$j]->is(\T_ATTRIBUTE)) {
                        if (0 === --$crochets) {
                            break;
                        }
                    }
                }
                if ($j < 0 || !$jetons[$j]->is(\T_ATTRIBUTE)) {
                    break;
                }
                $debut = $j;
                --$j;
            } else {
                break;
            }
        }

        $position = $jetons[$debut]->pos;
        $ligne = strrpos(substr($code, 0, $position), "\n");

        return false === $ligne ? 0 : $ligne + 1;
    }
}
