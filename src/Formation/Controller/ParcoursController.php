<?php

namespace App\Formation\Controller;

use App\Formation\Contenu\Bloc;
use App\Formation\Contenu\Catalogue;
use App\Formation\Contenu\Sujet;
use App\Formation\Parcours\Parcours;
use App\Formation\Parcours\StatutBloc;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class ParcoursController extends AbstractController
{
    public function __construct(
        private readonly Parcours $parcours,
        private readonly Catalogue $catalogue,
    ) {
    }

    #[Route('/formation/parcours', name: 'formation_parcours', methods: ['GET'])]
    public function parcours(): Response
    {
        $statuts = $this->parcours->statuts();
        $groupes = [];
        foreach ($this->catalogue->niveaux() as $niveau) {
            $groupes[$niveau->id] = array_map(fn (array $groupe) => $this->decrire($groupe['sujet'], $groupe['blocs'], $statuts), $this->catalogue->groupesDuNiveau($niveau));
        }

        return $this->render('parcours.html.twig', [
            'niveaux' => $this->catalogue->niveaux(),
            'groupes' => $groupes,
            'statuts' => $statuts,
        ]);
    }

    /**
     * @param list<Bloc>                $blocs
     * @param array<string, StatutBloc> $statuts
     *
     * @return array{sujet: ?Sujet, blocs: list<Bloc>, faits: int, ouvert: bool}
     */
    private function decrire(?Sujet $sujet, array $blocs, array $statuts): array
    {
        $faits = 0;
        $ouvert = false;
        foreach ($blocs as $bloc) {
            StatutBloc::Fait === $statuts[$bloc->id] && ++$faits;
            StatutBloc::EnCours === $statuts[$bloc->id] && $ouvert = true;
        }

        return ['sujet' => $sujet, 'blocs' => $blocs, 'faits' => $faits, 'ouvert' => $ouvert];
    }
}
