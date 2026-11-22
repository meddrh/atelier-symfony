<?php

namespace App\Tests\Exercices\TestsFonctionnels\Defauts;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Défaut volontaire : la création d'un ticket VALIDE répond comme si tout allait bien (redirection vers le matériel)
 * mais n'enregistre rien. Un test qui ne regarde que le code de la réponse ne le voit pas. Les créations refusées
 * (titre vide, titre illisible) ne sont pas touchées. Installé seulement dans une copie jetable.
 */
final class CreationIgnoree implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => 'surRequete'];
    }

    public function surRequete(RequestEvent $evenement): void
    {
        $requete = $evenement->getRequest();
        if ($requete->isMethod('POST') && 1 === preg_match('#^/materiels/(\d+)/tickets/nouveau$#', $requete->getPathInfo(), $morceaux) && $this->titreEstValide($requete->request->all())) {
            $evenement->setResponse(new RedirectResponse('/materiels/'.$morceaux[1]));
        }
    }

    /** Un titre valide : au moins 5 caractères et pas tout en majuscules (les règles du sujet précédent). */
    private function titreEstValide(array $donnees): bool
    {
        foreach ($donnees as $champs) {
            $titre = \is_array($champs) ? trim((string) ($champs['titre'] ?? '')) : '';
            if (mb_strlen($titre) >= 5 && mb_strtoupper($titre) !== $titre) {
                return true;
            }
        }

        return false;
    }
}
