<?php

namespace App\Formation\Controller;

use App\Formation\Contenu\Catalogue;
use App\Formation\Parcours\Parcours;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class ProgrammeController extends AbstractController
{
    public function __construct(
        private readonly Parcours $parcours,
        private readonly Catalogue $catalogue,
    ) {
    }

    #[Route('/formation/programme', name: 'formation_programme', methods: ['GET'])]
    public function programme(): Response
    {
        $couvertes = $this->parcours->lignesCouvertes();

        // Sections dans l'ordre du programme ; la couverture découle des blocs faits, elle ne se coche pas.
        return $this->render('programme.html.twig', [
            'sections' => $this->catalogue->sectionsDuProgramme($couvertes),
            'nbCouvertes' => \count(array_filter($couvertes)),
            'nbLignes' => \count($couvertes),
        ]);
    }
}
