<?php

// Script routeur du serveur intégré de PHP (php -S ... -t public public/routeur.php) :
//  - /formation… et /atelier/… vont à la plateforme (public/formation.php), noyau indépendant ;
//  - les fichiers statiques de public/ sont servis par le serveur ;
//  - tout le reste va à l'application du junior (public/index.php).
// Une erreur du junior ne peut donc jamais atteindre la plateforme.

$chemin = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', \PHP_URL_PATH));

// Un fichier statique existant (hors scripts PHP et hors dossiers parents) est servi tel quel.
if (!str_contains($chemin, '..') && '/' !== $chemin && is_file(__DIR__.$chemin) && 'php' !== pathinfo($chemin, \PATHINFO_EXTENSION)) {
    return false;
}

$frontal = 1 === preg_match('#^/(formation|atelier)(/|$)#', $chemin) ? 'formation.php' : 'index.php';
$_SERVER['SCRIPT_FILENAME'] = __DIR__.'/'.$frontal;
$_SERVER['SCRIPT_NAME'] = '/'.$frontal;
$_SERVER['PHP_SELF'] = '/'.$frontal;

require __DIR__.'/'.$frontal;

return true;
