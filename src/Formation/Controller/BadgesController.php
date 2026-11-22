<?php

namespace App\Formation\Controller;

use App\Formation\Contenu\Catalogue;
use App\Formation\Parcours\Parcours;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class BadgesController extends AbstractController
{
    public function __construct(
        private readonly Parcours $parcours,
        private readonly Catalogue $catalogue,
    ) {
    }

    #[Route('/formation/badges', name: 'formation_badges', methods: ['GET'])]
    public function badges(): Response
    {
        $obtenus = [];
        foreach ($this->parcours->badgesObtenus() as $badge) {
            $obtenus[$badge->id] = true;
        }

        // Un badge par exercice, puis le badge du niveau.
        $cartes = [];
        foreach ($this->catalogue->niveaux() as $niveau) {
            foreach ($niveau->sujets as $sujet) {
                if (null !== $sujet->badge) {
                    $cartes[] = [
                        'badge' => $sujet->badge,
                        'contexte' => sprintf('Sujet %d · %s', $sujet->numero, $sujet->titre),
                        'obtenu' => isset($obtenus[$sujet->badge->id]),
                    ];
                }
            }
            $cartes[] = [
                'badge' => $niveau->badgeNiveau,
                'contexte' => sprintf('Niveau %s', $niveau->nom),
                'obtenu' => isset($obtenus[$niveau->badgeNiveau->id]),
            ];
        }

        return $this->render('badges.html.twig', [
            'cartes' => $cartes,
            'nbObtenus' => \count(array_filter($cartes, static fn (array $c): bool => $c['obtenu'])),
        ]);
    }
}
