<?php

namespace App\Tests\Exercices\TestsFonctionnels\Defauts;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Défaut volontaire : la liste des tickets plante (erreur 500). Installé seulement dans une copie jetable.
 */
final class ListeEnPanne implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => 'surRequete'];
    }

    public function surRequete(RequestEvent $evenement): void
    {
        if (1 === preg_match('#^/(fr|en)/tickets$#', $evenement->getRequest()->getPathInfo())) {
            throw new \RuntimeException('Défaut volontaire : la liste des tickets est en panne.');
        }
    }
}
