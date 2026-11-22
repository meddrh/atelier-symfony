<?php

// Environnement du noyau de la plateforme (src/Formation/FormationKernel.php).
// Réglé ici, jamais par un fichier .env ni par APP_ENV : ceux-ci appartiennent à l'application du junior.
// « dev » : les pages d'erreur détaillées et le rechargement des gabarits (la plateforme n'écoute que la boucle locale).

return [
    'environnement' => 'dev',
    'debug' => true,
];
