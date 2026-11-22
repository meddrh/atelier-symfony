<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

if (method_exists(Dotenv::class, 'bootEnv')) {
    try {
        (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
    } catch (\Throwable $erreur) {
        // Un .env illisible (celui du junior) ne doit pas empêcher les tests de la plateforme de démarrer : elle n'en
        // lit aucun. Les tests d'exercice, eux, échoueront faute de KERNEL_CLASS, et ce message dit pourquoi.
        fwrite(\STDERR, sprintf("Attention : .env illisible, ignoré (%s)\n", $erreur->getMessage()));
        $_SERVER['APP_DEBUG'] = $_ENV['APP_DEBUG'] = '1';
    }
}

if ($_SERVER['APP_DEBUG']) {
    umask(0000);
}
