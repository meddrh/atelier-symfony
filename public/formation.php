<?php

// Front controller de la plateforme de formation (/formation, /atelier) : son noyau est indépendant de celui du
// junior (public/index.php). Il n'utilise pas symfony/runtime, qui lirait le .env du junior.

use App\Formation\FormationKernel;
use Symfony\Component\ErrorHandler\Debug;
use Symfony\Component\HttpFoundation\Request;

require dirname(__DIR__).'/vendor/autoload.php';

$noyau = FormationKernel::parDefaut();
if ($noyau->isDebug()) {
    umask(0000);
    Debug::enable();
}

$requete = Request::createFromGlobals();
$reponse = $noyau->handle($requete);
$reponse->send();
$noyau->terminate($requete, $reponse);
