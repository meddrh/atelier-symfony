<?php

namespace App\Tests\Exercices\TestsFonctionnels\Defauts;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Défaut volontaire : la création d'un ticket enregistre bien le ticket, mais ne redirige plus (la page répond 200).
 * Installé seulement dans une copie jetable de l'application, par TestsFonctionnelsTest.
 */
final class RedirectionPerdue implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::RESPONSE => 'surReponse'];
    }

    public function surReponse(ResponseEvent $evenement): void
    {
        $requete = $evenement->getRequest();
        if ($requete->isMethod('POST') && 1 === preg_match('#/tickets/nouveau$#', $requete->getPathInfo()) && $evenement->getResponse()->isRedirect()) {
            $evenement->setResponse(new Response('Ticket créé.', 200));
        }
    }
}
