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
        $sections = [];
        foreach ($this->catalogue->programme() as $ligne) {
            $sections[$ligne->sectionId] ??= [
                'titre' => $ligne->section,
                'horsProgramme' => $ligne->horsProgramme,
                'lignes' => [],
                'couvertes' => 0,
            ];
            $couverte = $couvertes[$ligne->id] ?? false;
            $sections[$ligne->sectionId]['lignes'][] = ['libelle' => $ligne->libelle, 'couverte' => $couverte];
            $couverte && ++$sections[$ligne->sectionId]['couvertes'];
        }

        return $this->render('programme.html.twig', [
            'sections' => array_values($sections),
            'nbCouvertes' => \count(array_filter($couvertes)),
            'nbLignes' => \count($couvertes),
        ]);
    }
}
