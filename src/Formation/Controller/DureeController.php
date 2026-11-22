<?php

namespace App\Formation\Controller;

use App\Formation\Progression\ProgressionStore;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

final class DureeController extends AbstractController
{
    public function __construct(private readonly ProgressionStore $store)
    {
    }

    #[Route('/formation/duree', name: 'formation_duree', methods: ['POST'])]
    public function changer(Request $request): RedirectResponse
    {
        $saisie = $request->request->all();
        $minutes = $saisie['minutes'] ?? null;
        $jeton = $saisie['_token'] ?? null;

        $valide = \is_string($jeton)
            && $this->isCsrfTokenValid('duree', $jeton)
            && \is_string($minutes)
            && 1 === preg_match('/^\d+$/', $minutes)
            && \in_array((int) $minutes, ProgressionStore::DUREES, true);

        if ($valide) {
            $this->store->changerDuree((int) $minutes);
        } else {
            $this->addFlash('erreur', 'Durée non prise en compte.');
        }

        return $this->redirectToRoute('formation_seance');
    }
}
