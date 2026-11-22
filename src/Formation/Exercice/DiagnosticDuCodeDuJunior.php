<?php

namespace App\Formation\Exercice;

use Symfony\Component\Filesystem\Path;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;
use Twig\Environment;
use Twig\Error\SyntaxError;
use Twig\Loader\ArrayLoader;
use Twig\Source;

/**
 * Cherche, dans le code et la configuration du junior, le fichier qui explique un essai rouge.
 *
 * Elle ne sert qu'à NOMMER la cause d'un rouge déjà constaté par PHPUnit : jamais à en décider un. Les tests du junior
 * tournent dans un sous-processus où une erreur de syntaxe ou de configuration n'apparaît souvent que comme « la réponse
 * n'est pas un succès » : ici, on la retrouve.
 *  1. Syntaxe : PHP (src/, config/), YAML (config/), Twig (templates/). Les fonctions, filtres, balises et tests Twig
 *     inconnus sont ignorés : ils viennent peut-être d'une extension du junior.
 *  2. À défaut, démarrage du noyau du junior dans un processus à part (outils/noyau-du-junior.php) : un service mal
 *     câblé, une route vers un dossier absent…
 * La plateforme elle-même (src/Formation/, config/formation/, templates/formation/) n'est jamais examinée.
 */
final class DiagnosticDuCodeDuJunior
{
    private const DOSSIERS = ['src' => ['php'], 'config' => ['php', 'yaml', 'yml'], 'templates' => ['twig']];

    public function __construct(
        private readonly string $dossierProjet,
        private readonly int $delaiSecondes = 120,
    ) {
    }

    /**
     * @param array<string, string|false> $environnement environnement du processus qui démarre le noyau (voir LanceurTests)
     *
     * @return string|null une phrase qui nomme le fichier en cause, null si rien n'est trouvé
     */
    public function chercher(array $environnement = []): ?string
    {
        return $this->chercherUneErreurDeSyntaxe() ?? $this->chercherUnNoyauQuiNeDemarrePas($environnement);
    }

    private function chercherUneErreurDeSyntaxe(): ?string
    {
        $twig = new Environment(new ArrayLoader());
        foreach (self::DOSSIERS as $dossier => $extensions) {
            foreach ($this->fichiers($dossier, $extensions) as $chemin) {
                $relatif = Path::makeRelative($chemin, $this->dossierProjet);
                $contenu = (string) @file_get_contents($chemin);
                $erreur = match (pathinfo($chemin, \PATHINFO_EXTENSION)) {
                    'php' => $this->erreurPhp($contenu),
                    'twig' => $this->erreurTwig($twig, $contenu, $relatif),
                    default => $this->erreurYaml($contenu),
                };
                if (null !== $erreur) {
                    return sprintf('Erreur dans ton code, dans %s : %s', $relatif, $erreur);
                }
            }
        }

        return null;
    }

    private function erreurPhp(string $contenu): ?string
    {
        try {
            token_get_all($contenu, \TOKEN_PARSE);
        } catch (\ParseError $erreur) {
            return sprintf('%s (ligne %d)', $erreur->getMessage(), $erreur->getLine());
        }

        return null;
    }

    private function erreurYaml(string $contenu): ?string
    {
        try {
            Yaml::parse($contenu, Yaml::PARSE_CUSTOM_TAGS);
        } catch (ParseException $erreur) {
            return rtrim($erreur->getMessage(), '.');
        }

        return null;
    }

    private function erreurTwig(Environment $twig, string $contenu, string $nom): ?string
    {
        try {
            $twig->parse($twig->tokenize(new Source($contenu, $nom)));
        } catch (SyntaxError $erreur) {
            // Une fonction, un filtre, une balise ou un test inconnus : peut-être ceux d'une extension du junior.
            if (1 === preg_match('/^Unknown "[^"]*" (function|filter|tag|test)\b/', $erreur->getRawMessage())) {
                return null;
            }

            return sprintf('%s (ligne %d)', rtrim($erreur->getRawMessage(), '.'), $erreur->getTemplateLine());
        }

        return null;
    }

    /**
     * @param list<string> $extensions
     *
     * @return list<string> chemins absolus, triés, hors dossier de la plateforme
     */
    private function fichiers(string $dossier, array $extensions): array
    {
        $racine = Path::join($this->dossierProjet, $dossier);
        if (!is_dir($racine)) {
            return [];
        }
        $plateforme = Path::join($racine, 'formation');
        $fichiers = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($racine, \FilesystemIterator::SKIP_DOTS)) as $fichier) {
            $chemin = Path::normalize($fichier->getPathname());
            // « src/Formation/ » (avec sa majuscule) et « config/formation/ », « templates/formation/ » : la plateforme.
            if (str_starts_with($chemin, $plateforme.'/') || str_starts_with($chemin, Path::join($racine, 'Formation').'/')) {
                continue;
            }
            if ($fichier->isFile() && \in_array(strtolower($fichier->getExtension()), $extensions, true)) {
                $fichiers[] = $chemin;
            }
        }
        sort($fichiers);

        return $fichiers;
    }

    /** @param array<string, string|false> $environnement */
    private function chercherUnNoyauQuiNeDemarrePas(array $environnement): ?string
    {
        $script = Path::join($this->dossierProjet, 'outils/noyau-du-junior.php');
        if (!is_file($script) || !is_file(Path::join($this->dossierProjet, 'vendor/autoload.php'))) {
            return null;
        }

        $processus = new Process([\PHP_BINARY, $script], $this->dossierProjet, $environnement);
        $processus->setTimeout($this->delaiSecondes);
        try {
            $processus->run();
        } catch (\Throwable) {
            return null;
        }

        $erreur = json_decode(trim($processus->getOutput()), true);
        if (!\is_array($erreur) || !isset($erreur['message'], $erreur['fichier'], $erreur['ligne'])) {
            return null;
        }
        $message = str_replace($this->dossierProjet.'/', '', (string) $erreur['message']);
        $fichier = str_replace($this->dossierProjet.'/', '', (string) $erreur['fichier']);

        return sprintf('Erreur dans ton code : ton application ne démarre pas : %s (%s:%d)', rtrim($message, '.'), $fichier, (int) $erreur['ligne']);
    }
}
