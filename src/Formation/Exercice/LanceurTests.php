<?php

namespace App\Formation\Exercice;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

/**
 * Lance PHPUnit sur le dossier de tests d'un exercice et lit le journal JUnit.
 *
 * Vert si et seulement si PHPUnit sort avec le code 0, qu'au moins un test a tourné et que
 * tous ont réussi : un plantage, une erreur de syntaxe ou un dossier vide ne sont jamais verts.
 */
final class LanceurTests
{
    private const JOURNAL = 'var/formation/derniere-execution.xml';

    private readonly DiagnosticDuCodeDuJunior $diagnostic;

    public function __construct(
        private readonly string $dossierProjet,
        private readonly int $delaiSecondes = 600,
    ) {
        $this->diagnostic = new DiagnosticDuCodeDuJunior($dossierProjet);
    }

    /**
     * @param string $dossierTests relatif au projet (ou absolu)
     *
     * Un rouge dont la cause est un fichier du junior (syntaxe, configuration, noyau qui ne démarre pas) est expliqué
     * par ce fichier : le résumé de PHPUnit dit souvent seulement « la réponse n'est pas un succès ». Le diagnostic ne
     * change jamais le verdict, seulement son message.
     */
    public function lancer(string $dossierTests): ResultatTests
    {
        $resultat = $this->executer($dossierTests);
        if ($resultat->vert) {
            return $resultat;
        }

        $cause = $this->diagnostic->chercher($this->environnementDeTest());

        return null === $cause ? $resultat : new ResultatTests(false, $resultat->nbTests, $resultat->nbReussis, $cause);
    }

    private function executer(string $dossierTests): ResultatTests
    {
        $journal = Path::join($this->dossierProjet, self::JOURNAL);
        $this->preparerJournal($journal);
        $this->viderLeCacheDeTest();

        $dossier = Path::isAbsolute($dossierTests) ? $dossierTests : Path::join($this->dossierProjet, $dossierTests);
        $processus = new Process([\PHP_BINARY, 'bin/phpunit', $dossier, '--log-junit', $journal], $this->dossierProjet, $this->environnementDeTest());
        $processus->setTimeout($this->delaiSecondes);

        try {
            $processus->run();
        } catch (ProcessTimedOutException) {
            return new ResultatTests(false, 0, 0, sprintf('Délai dépassé (%d s) : les tests ont été interrompus.', $this->delaiSecondes));
        }

        $sortie = $processus->getOutput().$processus->getErrorOutput();
        $code = $processus->getExitCode();
        $derniereLigne = $this->derniereLigne($sortie);

        $bilan = $this->lireJournal($journal);
        if (null === $bilan) {
            return new ResultatTests(false, 0, 0, $derniereLigne);
        }

        ['nbTests' => $nbTests, 'nbReussis' => $nbReussis, 'echecs' => $echecs] = $bilan;

        if (0 === $nbTests) {
            return new ResultatTests(false, 0, 0, 0 !== $code ? $derniereLigne : 'Aucun test exécuté.');
        }
        if (0 === $code && $nbReussis === $nbTests) {
            return new ResultatTests(true, $nbTests, $nbReussis, 'Tous les tests passent.');
        }

        return new ResultatTests(false, $nbTests, $nbReussis, $echecs[0] ?? $derniereLigne, $echecs);
    }

    /**
     * Environnement du processus PHPUnit : celui d'un lancement direct, pas celui de la console.
     *
     * Lancé depuis bin/console en dev, le processus enfant hériterait d'APP_ENV=dev, de SYMFONY_DOTENV_VARS et des
     * variables du .env : Dotenv::bootEnv considérerait alors .env.test comme déjà chargé et KERNEL_CLASS manquerait.
     * On retire donc ces variables (valeur false = supprimée par Process) et on force APP_ENV=test.
     *
     * @return array<string, string|false>
     */
    private function environnementDeTest(): array
    {
        $chargees = $_SERVER['SYMFONY_DOTENV_VARS'] ?? $_ENV['SYMFONY_DOTENV_VARS'] ?? getenv('SYMFONY_DOTENV_VARS') ?: '';

        $env = ['SYMFONY_DOTENV_VARS' => false];
        foreach (explode(',', (string) $chargees) as $nom) {
            if ('' !== $nom) {
                $env[$nom] = false;
            }
        }
        // APP_DEBUG est recalculé par Dotenv à partir de l'environnement de test.
        $env['APP_DEBUG'] = false;
        $env['APP_ENV'] = 'test';

        return $env;
    }

    /**
     * Vide le cache de test de l'application du junior avant chaque lancement.
     *
     * Symfony et Twig jugent le cache à jour en comparant la date de modification des fichiers à celle du cache. Un
     * fichier copié en gardant son ancienne date (`cp -p`, `rsync -a`, une archive) paraît donc plus vieux que le
     * cache : l'ancienne version du gabarit reste servie et les tests affichent un faux rouge. Le cache se reconstruit
     * en quelques secondes ; la justesse du verdict prime. Seul `var/cache/test` est touché, jamais le cache de la
     * plateforme (`var/cache/formation_*`).
     */
    private function viderLeCacheDeTest(): void
    {
        (new Filesystem())->remove(Path::join($this->dossierProjet, 'var/cache/test'));
    }

    /** Supprime le journal d'un lancement précédent : il ne doit jamais être relu à la place du nouveau. */
    private function preparerJournal(string $journal): void
    {
        $dossier = \dirname($journal);
        if (!is_dir($dossier) && !mkdir($dossier, 0777, true) && !is_dir($dossier)) {
            throw new \RuntimeException(sprintf('Impossible de créer le dossier « %s ».', $dossier));
        }
        if (is_file($journal)) {
            unlink($journal);
        }
    }

    /** @return array{nbTests: int, nbReussis: int, echecs: list<string>}|null null si absent ou illisible */
    private function lireJournal(string $journal): ?array
    {
        if (!is_file($journal)) {
            return null;
        }
        $precedent = libxml_use_internal_errors(true);
        try {
            $xml = simplexml_load_file($journal);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($precedent);
        }
        if (false === $xml) {
            return null;
        }

        $nbTests = 0;
        $nbReussis = 0;
        $echecs = [];
        foreach ($xml->xpath('//testcase') ?: [] as $cas) {
            ++$nbTests;
            $probleme = null;
            foreach (['failure', 'error', 'warning', 'skipped'] as $nature) {
                if (isset($cas->{$nature})) {
                    $probleme = $cas->{$nature};
                    break;
                }
            }
            if (null === $probleme) {
                ++$nbReussis;
            } else {
                $echecs[] = $this->resumerEchec((string) $cas['name'], (string) $probleme);
            }
        }

        return ['nbTests' => $nbTests, 'nbReussis' => $nbReussis, 'echecs' => $echecs];
    }

    private function resumerEchec(string $nomDuTest, string $texte): string
    {
        $lignes = array_values(array_filter(array_map('trim', explode("\n", $texte)), static fn (string $l) => '' !== $l));
        // PHPUnit commence par « Classe::méthode » : seule la raison de l'échec est utile.
        if (\count($lignes) > 1 && str_contains($lignes[0], '::')) {
            array_shift($lignes);
        }

        return '' === ($lignes[0] ?? '') ? $nomDuTest : sprintf('%s : %s', $nomDuTest, $lignes[0]);
    }

    private function derniereLigne(string $sortie): string
    {
        // On écarte la pile d'appels et le « thrown in » final : ils cachent la cause (ex. erreur de syntaxe).
        $lignes = array_values(array_filter(
            array_map('trim', explode("\n", $sortie)),
            static fn (string $l) => '' !== $l && 1 !== preg_match('/^(#\d+ |Stack trace:|thrown in |\{main\})/', $l)
        ));
        if ([] === $lignes) {
            return 'Les tests n\'ont produit aucune sortie.';
        }

        $ligne = preg_replace(['/^Next /', '/ in \/\S+:\d+$/'], '', $lignes[\count($lignes) - 1]);

        return $ligne;
    }
}
