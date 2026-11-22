<?php

namespace App\Tests\Formation\Squelette;

use Symfony\Component\Process\Process;

/**
 * Les trois contrôles d'un squelette, qui rendent la liste de leurs constats (vide = en règle).
 * Partagés par SqueletteTest et par son test d'outillage.
 */
final class VerificationsSquelette
{
    /**
     * Un fichier complet compile (`php -l`). Un extrait (à ajouter, à modifier) n'est pas un fichier : il est vérifié par la cohérence.
     *
     * @return list<string>
     */
    public static function erreursDeCompilation(BlocSquelette $bloc): array
    {
        $fichier = (string) tempnam(sys_get_temp_dir(), 'squelette-');
        try {
            file_put_contents($fichier, $bloc->code."\n");
            $processus = new Process([\PHP_BINARY, '-l', $fichier]);
            $processus->run();
            if (0 === $processus->getExitCode()) {
                return [];
            }

            return [trim(str_replace($fichier, $bloc->chemin, $processus->getOutput().$processus->getErrorOutput()))];
        } finally {
            @unlink($fichier);
        }
    }

    /**
     * PSR-4 : `src/A/B.php` déclare, dans l'espace de noms `App\A`, une seule classe nommée `B` ; `tests/A/B.php` dans `App\Tests\A`.
     *
     * @return list<string>
     */
    public static function erreursPsr4(BlocSquelette $bloc): array
    {
        if (!preg_match('#^(src|tests)/((?:[A-Za-z0-9_]+/)*)([A-Za-z0-9_]+)\.php$#', $bloc->chemin, $m)) {
            return [sprintf('le chemin « %s » ne suit pas PSR-4 (src/Dossier/Classe.php ou tests/Dossier/Classe.php)', $bloc->chemin)];
        }
        // composer.json : « App\\ » → src/, « App\\Tests\\ » → tests/.
        $racine = 'src' === $m[1] ? 'App' : 'App\\Tests';
        $espaceAttendu = $racine.('' === $m[2] ? '' : '\\'.str_replace('/', '\\', rtrim($m[2], '/')));
        $classes = AnalyseurPhp::analyser($bloc->code)->classes;
        if (1 !== \count($classes)) {
            return [sprintf('un fichier PSR-4 déclare une seule classe, le squelette en déclare %d', \count($classes))];
        }

        $erreurs = [];
        if ($classes[0]->espace !== $espaceAttendu) {
            $erreurs[] = sprintf('espace de noms « %s » au lieu de « %s » (déduit du chemin %s)', $classes[0]->espace, $espaceAttendu, $bloc->chemin);
        }
        if ($classes[0]->nom !== $m[3]) {
            $erreurs[] = sprintf('classe « %s » au lieu de « %s » (nom du fichier %s)', $classes[0]->nom, $m[3], $bloc->chemin);
        }

        return $erreurs;
    }

    /**
     * Un squelette ne donne pas la solution : le corps de chaque méthode est vide (un commentaire « À toi : … »),
     * ou donne seulement le `return` d'une valeur simple (null, true, false, un nombre, une chaîne, `[]`, une constante de classe) ou un `throw new …`.
     *
     * @return list<string>
     */
    public static function erreursDeCorps(BlocSquelette $bloc): array
    {
        if (self::estUnExtrait($bloc)) {
            $methodes = AnalyseurPhp::analyserExtrait($bloc->code, new FichierAnalyse('', [], []));
        } else {
            $methodes = AnalyseurPhp::analyser($bloc->code)->classes[0]->methodes ?? [];
        }

        $erreurs = [];
        foreach ($methodes as $nom => $methode) {
            if ($methode->corpsRempli) {
                $erreurs[] = sprintf('le corps de %s() est rempli : un squelette ne donne que la forme, pas la solution', $nom);
            }
        }

        return $erreurs;
    }

    /**
     * Le squelette dit la même chose que le fichier de même chemin du corrigé. Pour un fichier complet : même classe
     * (nom complet, genre), même parent, mêmes interfaces. Pour un fichier complet comme pour un extrait : chaque méthode
     * publique du squelette existe dans le corrigé, avec la même signature (paramètres, types, valeurs par défaut,
     * type de retour), et ses attributs et ceux de la classe sont aussi dans le corrigé.
     *
     * Le squelette peut donner MOINS que le corrigé (une entité ne donne que sa classe et son identifiant, un
     * contrôleur peut ne pas donner toutes ses routes) ; il ne peut jamais dire autre chose. Les arguments des
     * attributs ne sont pas comparés (un squelette de route peut taire `requirements:`), leurs noms le sont.
     *
     * @return list<string>
     */
    public static function erreursDeCoherence(BlocSquelette $bloc, ?string $codeDuCorrige): array
    {
        if (null === $codeDuCorrige) {
            return [sprintf('le corrigé du sujet n\'a pas de fichier %s', $bloc->chemin)];
        }
        $corrige = AnalyseurPhp::analyser($codeDuCorrige);
        if ([] === $corrige->classes) {
            return [sprintf('le fichier %s du corrigé ne déclare aucune classe', $bloc->chemin)];
        }

        if (self::estUnExtrait($bloc)) {
            $classeDuCorrige = $corrige->classes[0];
            $methodes = AnalyseurPhp::analyserExtrait($bloc->code, $corrige);
            if ([] === $methodes) {
                return ['l\'extrait ne contient aucune méthode publique'];
            }

            return self::erreursDeMethodes($methodes, $classeDuCorrige);
        }

        $classes = AnalyseurPhp::analyser($bloc->code)->classes;
        if (1 !== \count($classes)) {
            return [sprintf('un fichier complet déclare une seule classe, le squelette en déclare %d', \count($classes))];
        }
        $squelette = $classes[0];
        $classeDuCorrige = null;
        foreach ($corrige->classes as $candidate) {
            if ($candidate->nomComplet() === $squelette->nomComplet()) {
                $classeDuCorrige = $candidate;
            }
        }
        if (null === $classeDuCorrige) {
            return [sprintf('le corrigé ne déclare pas %s dans %s (il déclare : %s)', $squelette->nomComplet(), $bloc->chemin, implode(', ', array_map(static fn (ClasseAnalysee $c): string => $c->nomComplet(), $corrige->classes)))];
        }

        $erreurs = [];
        if ($squelette->genre !== $classeDuCorrige->genre) {
            $erreurs[] = sprintf('genre « %s » au lieu de « %s »', $squelette->genre, $classeDuCorrige->genre);
        }
        if ($squelette->parent !== $classeDuCorrige->parent) {
            $erreurs[] = sprintf('parent « %s » au lieu de « %s »', $squelette->parent ?? 'aucun', $classeDuCorrige->parent ?? 'aucun');
        }
        if ($squelette->interfaces !== $classeDuCorrige->interfaces) {
            $erreurs[] = sprintf('interfaces [%s] au lieu de [%s]', implode(', ', $squelette->interfaces), implode(', ', $classeDuCorrige->interfaces));
        }
        foreach (array_diff($squelette->attributs, $classeDuCorrige->attributs) as $attribut) {
            $erreurs[] = sprintf('attribut de classe #[%s] absent du corrigé', $attribut);
        }

        return [...$erreurs, ...self::erreursDeMethodes($squelette->methodes, $classeDuCorrige)];
    }

    /**
     * Le bloc est cohérent avec l'état de DÉPART du sujet (le corrigé du sujet précédent, null pour le premier sujet,
     * où le dépôt est nu) : un squelette ne fait jamais effacer le travail du junior.
     * - un fichier complet ne vise pas un fichier qui existe déjà : il l'écraserait ;
     * - une méthode « à modifier » existait déjà dans ce fichier (sinon c'est une méthode « à ajouter ») ;
     * - une méthode « à ajouter » n'y existait pas encore (sinon c'est une méthode « à modifier »).
     *
     * @return list<string>
     */
    public static function erreursDExistence(BlocSquelette $bloc, ?string $codeDuPrecedent): array
    {
        if (BlocSquelette::COMPLET === $bloc->type) {
            return null === $codeDuPrecedent ? [] : [sprintf(
                'le fichier %s existe déjà à l\'état de départ du sujet (c\'est ton code du sujet précédent) : un bloc complet l\'écraserait et le junior perdrait son travail. Utilise un extrait « à ajouter » (méthode nouvelle) ou « à modifier » (méthode qui change)',
                $bloc->chemin,
            )];
        }
        if (!self::estUnExtrait($bloc)) {
            return [];
        }
        if (null === $codeDuPrecedent) {
            return BlocSquelette::MODIFICATION === $bloc->type ? [sprintf('le corrigé du sujet précédent n\'a pas de fichier %s : il n\'y a rien à modifier, c\'est un fichier complet ou un « à ajouter »', $bloc->chemin)] : [];
        }
        $precedent = AnalyseurPhp::analyser($codeDuPrecedent);
        $methodesDeDepart = $precedent->classes[0]->methodes ?? [];

        $erreurs = [];
        foreach (AnalyseurPhp::analyserExtrait($bloc->code, $precedent) as $nom => $methode) {
            $existait = isset($methodesDeDepart[$nom]);
            if (BlocSquelette::MODIFICATION === $bloc->type && !$existait) {
                $erreurs[] = sprintf('%s() n\'existait pas dans le corrigé du sujet précédent : ce n\'est pas une modification, c\'est un « à ajouter »', $nom);
            }
            if (BlocSquelette::EXTRAIT === $bloc->type && $existait) {
                $erreurs[] = sprintf('%s() existait déjà dans le corrigé du sujet précédent : ce n\'est pas un ajout, c\'est un « à modifier »', $nom);
            }
        }

        return $erreurs;
    }

    private static function estUnExtrait(BlocSquelette $bloc): bool
    {
        return \in_array($bloc->type, [BlocSquelette::EXTRAIT, BlocSquelette::MODIFICATION], true);
    }

    /**
     * @param array<string, MethodeAnalysee> $methodes
     *
     * @return list<string>
     */
    private static function erreursDeMethodes(array $methodes, ClasseAnalysee $corrige): array
    {
        $erreurs = [];
        foreach ($methodes as $nom => $methode) {
            $attendue = $corrige->methodes[$nom] ?? null;
            if (null === $attendue) {
                $erreurs[] = sprintf('la méthode publique %s() n\'existe pas dans le corrigé (méthodes publiques du corrigé : %s)', $nom, implode(', ', array_keys($corrige->methodes)) ?: 'aucune');
                continue;
            }
            if ($methode->signature !== $attendue->signature) {
                $erreurs[] = sprintf("signature de %s() différente de celle du corrigé :\n      squelette : %s\n      corrigé   : %s", $nom, $methode->signature, $attendue->signature);
            }
            foreach (array_diff($methode->attributs, $attendue->attributs) as $attribut) {
                $erreurs[] = sprintf('attribut #[%s] de %s() absent du corrigé', $attribut, $nom);
            }
        }

        return $erreurs;
    }
}
