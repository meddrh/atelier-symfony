<?php

namespace App\Formation\Controller;

use App\Formation\Contenu\Catalogue;
use App\Formation\Parcours\Parcours;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class AccueilController extends AbstractController
{
    public function __construct(
        private readonly Parcours $parcours,
        private readonly Catalogue $catalogue,
    ) {
    }

    #[Route('/formation', name: 'formation_seance', methods: ['GET'])]
    public function seanceDuJour(): Response
    {
        $seance = $this->parcours->seanceDuJour();

        // Niveau affiché : celui du bloc courant, ou le dernier quand tout est fait.
        $courant = $this->parcours->blocCourant();
        $niveaux = $this->catalogue->niveaux();
        $niveau = null !== $courant ? $this->catalogue->niveauDuBloc($courant->id) : end($niveaux);

        ['faits' => $faits, 'total' => $total] = $this->parcours->avancementDuNiveau($niveau);

        $badges = $this->parcours->badgesObtenus();

        return $this->render('seance.html.twig', [
            'seance' => $seance,
            'niveau' => $niveau,
            'faits' => $faits,
            'total' => $total,
            'pourcentage' => $total > 0 ? (int) round($faits * 100 / $total) : 0,
            'dernierBadge' => [] === $badges ? null : end($badges),
        ]);
    }
}
