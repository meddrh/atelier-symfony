<?php

namespace App\Tests\Exercices\TestsFonctionnels\Defauts;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Défaut volontaire : le filtre de statut de la liste des tickets est ignoré (le paramètre de l'adresse disparaît).
 * Installé seulement dans une copie jetable.
 */
final class FiltreIgnore implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => 'surRequete'];
    }

    public function surRequete(RequestEvent $evenement): void
    {
        $requete = $evenement->getRequest();
        if (1 === preg_match('#^/(fr|en)/tickets$#', $requete->getPathInfo())) {
            $requete->query->remove('statut');
        }
    }
}
