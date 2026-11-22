<?php

namespace App\Tests\Formation\Squelette;

/**
 * Lit la structure d'un fichier PHP par ses jetons (PhpToken::tokenize), sans le charger : le squelette et le
 * corrigé déclarent la même classe, et deux classes de même nom ne peuvent pas vivre dans un même processus.
 *
 * Ce qui est lu : l'espace de noms, les `use`, et pour chaque classe son genre, son parent, ses interfaces, ses
 * attributs et ses méthodes publiques ou protégées (nom, paramètres avec types et valeurs par défaut, type de retour).
 * Tous les noms de classe sont résolus en noms complets, pour qu'un alias ou un nom relatif ne fasse pas une
 * différence. Les corps de méthode, les propriétés et les constantes sont sautés.
 */
final class AnalyseurPhp
{
    private const NATIFS = [
        'int', 'float', 'string', 'bool', 'array', 'callable', 'iterable', 'object', 'mixed', 'void', 'null', 'never',
        'false', 'true', 'self', 'static', 'parent',
    ];

    /** @var list<\PhpToken> */
    private array $t;
    private int $n;
    private int $i = 0;
    private string $espace = '';
    /** @var array<string, string> */
    private array $importations = [];

    private function __construct(string $code)
    {
        $this->t = array_values(array_filter(
            \PhpToken::tokenize($code),
            static fn (\PhpToken $jeton): bool => !$jeton->isIgnorable() && !$jeton->is(\T_CLOSE_TAG),
        ));
        $this->n = \count($this->t);
    }

    public static function analyser(string $code): FichierAnalyse
    {
        return (new self($code))->lire();
    }

    /**
     * Les méthodes publiques d'un extrait (méthodes à ajouter à une classe), lues comme si elles étaient dans
     * le fichier $dans : même espace de noms, mêmes `use`.
     *
     * @return array<string, MethodeAnalysee>
     */
    public static function analyserExtrait(string $extrait, FichierAnalyse $dans): array
    {
        $entete = '' === $dans->espace ? '' : 'namespace '.$dans->espace.";\n";
        foreach ($dans->importations as $alias => $nom) {
            $entete .= 'use '.$nom.' as '.$alias.";\n";
        }
        $extrait = preg_replace('/\A<\?php[^\n]*\n/', '', $extrait) ?? $extrait;

        $fichier = self::analyser("<?php\n".$entete."class ExtraitAAnalyser\n{\n".$extrait."\n}\n");

        return $fichier->classes[0]->methodes ?? [];
    }

    private function lire(): FichierAnalyse
    {
        $classes = [];
        $attributs = [];
        while ($this->i < $this->n) {
            $jeton = $this->t[$this->i];
            if ($jeton->is(\T_NAMESPACE)) {
                $this->lireEspace();
            } elseif ($jeton->is(\T_USE)) {
                $this->lireImportation();
            } elseif ($jeton->is(\T_ATTRIBUTE)) {
                array_push($attributs, ...$this->lireAttributs());
            } elseif ($jeton->is([\T_CLASS, \T_INTERFACE, \T_TRAIT, \T_ENUM]) && !$this->precedentEst([\T_NEW, \T_DOUBLE_COLON])) {
                $classes[] = $this->lireClasse($attributs);
                $attributs = [];
            } else {
                ++$this->i;
            }
        }

        return new FichierAnalyse($this->espace, $this->importations, $classes);
    }

    private function lireEspace(): void
    {
        ++$this->i;
        $nom = '';
        while ($this->i < $this->n && !$this->t[$this->i]->is([';', '{'])) {
            $nom .= $this->t[$this->i++]->text;
        }
        $this->espace = trim($nom, '\\');
    }

    /** `use A\B;`, `use A\B as C;`, `use A\{B, C as D};` ; les `use function` et `use const` sont ignorés. */
    private function lireImportation(): void
    {
        ++$this->i;
        if ($this->i < $this->n && $this->t[$this->i]->is([\T_FUNCTION, \T_CONST])) {
            $this->sauterInstruction();

            return;
        }
        $prefixe = '';
        $courant = '';
        $alias = null;
        $apresAs = false;
        $enregistrer = function () use (&$prefixe, &$courant, &$alias): void {
            if ('' !== $courant) {
                $nom = ltrim($prefixe.$courant, '\\');
                $this->importations[strtolower($alias ?? substr(strrchr('\\'.$nom, '\\'), 1))] = $nom;
            }
            $courant = '';
            $alias = null;
        };
        while ($this->i < $this->n && !$this->t[$this->i]->is(';')) {
            $jeton = $this->t[$this->i++];
            if ($jeton->is('{')) {
                $prefixe = rtrim($courant, '\\').'\\';
                $courant = '';
            } elseif ($jeton->is([',', '}'])) {
                $enregistrer();
                $apresAs = false;
            } elseif ($jeton->is(\T_AS)) {
                $apresAs = true;
                $alias = '';
            } elseif ($apresAs) {
                $alias .= $jeton->text;
            } else {
                $courant .= $jeton->text;
            }
        }
        $enregistrer();
        ++$this->i;
    }

    /** @param list<string> $attributs */
    private function lireClasse(array $attributs): ClasseAnalysee
    {
        $genre = $this->t[$this->i]->text;
        $nom = $this->t[++$this->i]->text;
        ++$this->i;
        $parent = null;
        $interfaces = [];
        $mode = null;
        while ($this->i < $this->n && !$this->t[$this->i]->is('{')) {
            $jeton = $this->t[$this->i++];
            if ($jeton->is(\T_EXTENDS)) {
                $mode = 'extends';
            } elseif ($jeton->is(\T_IMPLEMENTS)) {
                $mode = 'implements';
            } elseif (null !== $mode && $jeton->is([\T_STRING, \T_NAME_QUALIFIED, \T_NAME_FULLY_QUALIFIED])) {
                $resolu = $this->resoudre($jeton->text);
                if ('extends' === $mode && 'interface' !== $genre) {
                    $parent = $resolu;
                } else {
                    $interfaces[] = $resolu;
                }
            }
        }
        ++$this->i; // '{'

        $methodes = $this->lireCorps();
        sort($interfaces);
        sort($attributs);

        return new ClasseAnalysee($nom, $this->espace, $genre, $parent, $interfaces, array_values(array_unique($attributs)), $methodes);
    }

    /**
     * Membres de la classe, jusqu'à son accolade fermante.
     *
     * @return array<string, MethodeAnalysee>
     */
    private function lireCorps(): array
    {
        $methodes = [];
        $attributs = [];
        $modificateurs = [];
        while ($this->i < $this->n && !$this->t[$this->i]->is('}')) {
            $jeton = $this->t[$this->i];
            if ($jeton->is(\T_ATTRIBUTE)) {
                array_push($attributs, ...$this->lireAttributs());
            } elseif ($jeton->is([\T_PUBLIC, \T_PROTECTED, \T_PRIVATE, \T_STATIC, \T_ABSTRACT, \T_FINAL, \T_VAR, \T_READONLY])) {
                $modificateurs[] = strtolower($jeton->text);
                ++$this->i;
            } elseif ($jeton->is(\T_FUNCTION)) {
                $methode = $this->lireMethode($modificateurs, $attributs);
                if (null !== $methode) {
                    $methodes[$methode->nom] = $methode;
                }
                $attributs = [];
                $modificateurs = [];
            } else {
                $this->sauterInstruction(); // propriété, constante, `use` de trait, `case` d'énumération
                $attributs = [];
                $modificateurs = [];
            }
        }
        ++$this->i; // '}'

        return $methodes;
    }

    /**
     * @param list<string> $modificateurs
     * @param list<string> $attributs
     */
    private function lireMethode(array $modificateurs, array $attributs): ?MethodeAnalysee
    {
        ++$this->i; // function
        if ($this->t[$this->i]->is(\T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG) || $this->t[$this->i]->is(\T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG)) {
            ++$this->i;
        }
        $nom = $this->t[$this->i++]->text;
        $parametres = $this->lireParametres();
        $retour = '';
        if ($this->i < $this->n && $this->t[$this->i]->is(':')) {
            ++$this->i;
            $retour = $this->texteDeType($this->jetonsJusquA(['{', ';']));
        }
        $corps = [];
        if ($this->i < $this->n && $this->t[$this->i]->is('{')) {
            $corps = $this->lireBloc();
        } else {
            ++$this->i; // ';'
        }

        if (\in_array('private', $modificateurs, true)) {
            return null;
        }
        sort($attributs);
        // La visibilité protégée fait partie de la signature : `Voter` impose `supports()` et `voteOnAttribute()` protégées.
        $signature = (\in_array('protected', $modificateurs, true) ? 'protected ' : '').(\in_array('static', $modificateurs, true) ? 'static ' : '').'function '.$nom.'('.implode(', ', $parametres).')'.('' === $retour ? '' : ': '.$retour);

        return new MethodeAnalysee($nom, $signature, array_values(array_unique($attributs)), !self::corpsDeSqueletteAutorise($corps));
    }

    /** @return list<string> un texte normalisé par paramètre : « Type &...$nom = défaut », sans promotion ni attributs */
    private function lireParametres(): array
    {
        ++$this->i; // '('
        $parametres = [];
        $courant = [];
        $profondeur = 0;
        while ($this->i < $this->n) {
            $jeton = $this->t[$this->i++];
            if (0 === $profondeur && $jeton->is(')')) {
                break;
            }
            if (0 === $profondeur && $jeton->is(',')) {
                $parametres[] = $courant;
                $courant = [];
                continue;
            }
            if ($jeton->is(['(', '[', \T_ATTRIBUTE]) || $this->ouvre($jeton)) {
                ++$profondeur;
            } elseif ($jeton->is([')', ']', '}'])) {
                --$profondeur;
            }
            $courant[] = $jeton;
        }
        if ([] !== $courant) {
            $parametres[] = $courant;
        }

        return array_map(fn (array $jetons): string => $this->normaliserParametre($jetons), $parametres);
    }

    /** @param list<\PhpToken> $jetons */
    private function normaliserParametre(array $jetons): string
    {
        $k = 0;
        $nb = \count($jetons);
        while ($k < $nb && $jetons[$k]->is(\T_ATTRIBUTE)) {
            $profondeur = 0;
            do {
                $profondeur += $jetons[$k]->is([\T_ATTRIBUTE, '[']) ? 1 : ($jetons[$k]->is(']') ? -1 : 0);
                ++$k;
            } while ($k < $nb && $profondeur > 0);
        }
        $type = [];
        $reference = false;
        $variadique = false;
        $nom = '';
        for (; $k < $nb; ++$k) {
            $jeton = $jetons[$k];
            if ($jeton->is([\T_PUBLIC, \T_PROTECTED, \T_PRIVATE, \T_READONLY])) {
                continue;
            }
            if ($jeton->is(\T_VARIABLE)) {
                $nom = $jeton->text;
                ++$k;
                break;
            }
            if ($jeton->is(\T_ELLIPSIS)) {
                $variadique = true;
            } elseif ($jeton->is(\T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG)) {
                $reference = true;
            } else {
                $type[] = $jeton;
            }
        }
        $defaut = '';
        if ($k < $nb && $jetons[$k]->is('=')) {
            $defaut = ' = '.implode('', array_map(static fn (\PhpToken $j): string => $j->text, \array_slice($jetons, $k + 1)));
        }
        $type = $this->texteDeType($type);

        return ('' === $type ? '' : $type.' ').($reference ? '&' : '').($variadique ? '...' : '').$nom.$defaut;
    }

    /** @param list<\PhpToken> $jetons */
    private function texteDeType(array $jetons): string
    {
        $texte = '';
        foreach ($jetons as $jeton) {
            $texte .= $jeton->is([\T_STRING, \T_NAME_QUALIFIED, \T_NAME_FULLY_QUALIFIED, \T_STATIC, \T_ARRAY, \T_CALLABLE])
                ? $this->resoudre($jeton->text)
                : $jeton->text;
        }

        return $texte;
    }

    /** `#[A, B(x: 1)]` : rend les noms complets des attributs du groupe, et avance après le `]`. @return list<string> */
    private function lireAttributs(): array
    {
        ++$this->i; // '#['
        $noms = [];
        $attendNom = true;
        $profondeur = 0;
        while ($this->i < $this->n) {
            $jeton = $this->t[$this->i++];
            if (0 === $profondeur && $jeton->is(']')) {
                break;
            }
            if (0 === $profondeur && $jeton->is(',')) {
                $attendNom = true;
            } elseif (0 === $profondeur && $attendNom && $jeton->is([\T_STRING, \T_NAME_QUALIFIED, \T_NAME_FULLY_QUALIFIED])) {
                $noms[] = $this->resoudre($jeton->text);
                $attendNom = false;
            } elseif ($jeton->is(['(', '[']) || $this->ouvre($jeton)) {
                ++$profondeur;
            } elseif ($jeton->is([')', ']', '}'])) {
                --$profondeur;
            }
        }

        return $noms;
    }

    /** @param list<string> $arrets @return list<\PhpToken> les jetons jusqu'à l'un des arrêts (non consommé) */
    private function jetonsJusquA(array $arrets): array
    {
        $jetons = [];
        while ($this->i < $this->n && !$this->t[$this->i]->is($arrets)) {
            $jetons[] = $this->t[$this->i++];
        }

        return $jetons;
    }

    /** Avance après l'accolade fermante du bloc qui s'ouvre sur le jeton courant. */
    private function sauterBloc(): void
    {
        $this->lireBloc();
    }

    /**
     * Avance après l'accolade fermante du bloc qui s'ouvre sur le jeton courant.
     *
     * @return list<\PhpToken> les jetons du bloc, sans ses accolades (les commentaires n'y sont pas)
     */
    private function lireBloc(): array
    {
        $profondeur = 0;
        $jetons = [];
        while ($this->i < $this->n) {
            $jeton = $this->t[$this->i++];
            if ($this->ouvre($jeton)) {
                ++$profondeur;
            } elseif ($jeton->is('}') && 0 === --$profondeur) {
                return \array_slice($jetons, 1);
            }
            $jetons[] = $jeton;
        }

        return [];
    }

    /**
     * Un corps de squelette est vide (seuls des commentaires), ou donne le `return` d'une valeur simple que le type
     * de retour oblige à écrire (null, true, false, un nombre, une chaîne, `[]`), ou lève une exception (`throw new …`).
     * Tout le reste est de la solution.
     *
     * @param list<\PhpToken> $jetons
     */
    private static function corpsDeSqueletteAutorise(array $jetons): bool
    {
        if ([] === $jetons) {
            return true;
        }
        if (!end($jetons)->is(';')) {
            return false;
        }
        if ($jetons[0]->is(\T_THROW)) {
            // throw new X('…') : aucune variable, aucun appel enchaîné.
            foreach ($jetons as $jeton) {
                if ($jeton->is([\T_VARIABLE, \T_OBJECT_OPERATOR, \T_NULLSAFE_OBJECT_OPERATOR, \T_DOUBLE_COLON])) {
                    return false;
                }
            }

            return isset($jetons[1]) && $jetons[1]->is(\T_NEW);
        }
        if (!$jetons[0]->is(\T_RETURN)) {
            return false;
        }
        $valeur = array_map(static fn (\PhpToken $j): string => strtolower($j->text), \array_slice($jetons, 1, -1));
        if (\in_array($valeur, [[], ['null'], ['true'], ['false'], ['[', ']']], true)) {
            return true;
        }

        if (1 === \count($valeur) && $jetons[1]->is([\T_LNUMBER, \T_DNUMBER, \T_CONSTANT_ENCAPSED_STRING])) {
            return true;
        }

        // `return Command::SUCCESS;` : une constante de classe est une valeur simple, comme un nombre.
        return 3 === \count($valeur)
            && $jetons[1]->is([\T_STRING, \T_NAME_QUALIFIED, \T_NAME_FULLY_QUALIFIED])
            && $jetons[2]->is(\T_DOUBLE_COLON)
            && $jetons[3]->is(\T_STRING)
            && strtoupper($jetons[3]->text) === $jetons[3]->text;
    }

    /** Avance après le « ; » de l'instruction courante, ou après son bloc `{ … }` (cas du `use` de trait). */
    private function sauterInstruction(): void
    {
        $profondeur = 0;
        while ($this->i < $this->n) {
            $jeton = $this->t[$this->i];
            if (0 === $profondeur && $jeton->is('}')) {
                return; // fin de la classe : ne pas la consommer
            }
            ++$this->i;
            if (0 === $profondeur && $jeton->is(';')) {
                return;
            }
            if (0 === $profondeur && $jeton->is('{')) {
                --$this->i;
                $this->sauterBloc();

                return;
            }
            if ($jeton->is(['(', '[', \T_ATTRIBUTE]) || $this->ouvre($jeton)) {
                ++$profondeur;
            } elseif ($jeton->is([')', ']', '}'])) {
                --$profondeur;
            }
        }
    }

    private function ouvre(\PhpToken $jeton): bool
    {
        return $jeton->is(['{', \T_CURLY_OPEN, \T_DOLLAR_OPEN_CURLY_BRACES]);
    }

    /** @param int|string|list<int|string> $jetons */
    private function precedentEst(int|string|array $jetons): bool
    {
        return $this->i > 0 && $this->t[$this->i - 1]->is($jetons);
    }

    private function resoudre(string $nom): string
    {
        if (\in_array(strtolower($nom), self::NATIFS, true)) {
            return strtolower($nom);
        }
        if (str_starts_with($nom, '\\')) {
            return substr($nom, 1);
        }
        $premier = strstr($nom, '\\', true);
        $premier = false === $premier ? $nom : $premier;
        $reste = substr($nom, \strlen($premier));
        if (isset($this->importations[strtolower($premier)])) {
            return $this->importations[strtolower($premier)].$reste;
        }

        return ('' === $this->espace ? '' : $this->espace.'\\').$nom;
    }
}
