<?php

// Démarre le noyau de l'application du junior (App\Kernel) en environnement de test, comme le font ses tests
// d'exercice, et dit pourquoi il ne démarre pas : une ligne JSON {message, fichier, ligne}, code 1. Rien en cas
// de succès. Lancé par DiagnosticDuCodeDuJunior, dans un processus à part : une erreur du junior ne peut pas
// atteindre la plateforme.

use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;

$projet = dirname(__DIR__);

try {
    require $projet.'/vendor/autoload.php';
    (new Dotenv())->bootEnv($projet.'/.env');

    $noyau = new Kernel('test', (bool) ($_SERVER['APP_DEBUG'] ?? false));
    $noyau->boot();
    // Les routes ne se chargent qu'à la demande : on les lit ici, une erreur de route doit apparaître.
    if ($noyau->getContainer()->has('router')) {
        $noyau->getContainer()->get('router')->getRouteCollection();
    }
} catch (\Throwable $erreur) {
    echo json_encode(['message' => $erreur->getMessage(), 'fichier' => $erreur->getFile(), 'ligne' => $erreur->getLine()], \JSON_UNESCAPED_UNICODE | \JSON_PARTIAL_OUTPUT_ON_ERROR);
    exit(1);
}
