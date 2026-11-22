<?php

namespace App\Tests\Formation\Exercice;

/**
 * Exécute le VRAI script bin/exercice (pas une fermeture factice), dans un sous-processus,
 * depuis le projet donné : le script se repère par sa propre position (dirname(__DIR__)).
 */
trait LanceBinExercice
{
    /**
     * @param array<string, string> $env variables d'environnement ajoutées (FORMATION_DB, APP_ENV, …)
     *
     * @return array{int, string} code de sortie, sortie standard et erreurs mêlées
     */
    private function lancerBinExercice(string $projet, array $env = []): array
    {
        $processus = proc_open(
            [\PHP_BINARY, $projet.'/bin/exercice', 'test'],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $tuyaux,
            $projet,
            ['PATH' => (string) getenv('PATH'), 'APP_ENV' => 'dev', 'APP_DEBUG' => '0', 'SHELL_VERBOSITY' => '0'] + $env,
        );
        self::assertIsResource($processus);
        $sortie = (string) stream_get_contents($tuyaux[1]).(string) stream_get_contents($tuyaux[2]);
        fclose($tuyaux[1]);
        fclose($tuyaux[2]);

        return [proc_close($processus), $sortie];
    }
}
