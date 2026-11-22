<?php

/**
 * Fixture de LanceurTestsTest : rejoue ce que fait la console en développement (bin/console) —
 * Dotenv charge .env en APP_ENV=dev, puis LanceurTests est appelé depuis ce processus.
 * Usage : php lanceur-en-dev.php <dossier de tests> ; affiche le résultat en JSON.
 */

use App\Formation\Exercice\LanceurTests;
use Symfony\Component\Dotenv\Dotenv;

$projet = dirname(__DIR__, 3);
require $projet.'/vendor/autoload.php';

(new Dotenv())->bootEnv($projet.'/.env', 'dev', ['test']);

$resultat = (new LanceurTests($projet))->lancer($argv[1]);

echo json_encode([
    'vert' => $resultat->vert,
    'nbTests' => $resultat->nbTests,
    'nbReussis' => $resultat->nbReussis,
    'resume' => $resultat->resume,
    'appEnvParent' => $_SERVER['APP_ENV'] ?? null,
], \JSON_THROW_ON_ERROR);
