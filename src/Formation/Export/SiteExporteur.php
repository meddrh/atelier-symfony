<?php

namespace App\Formation\Export;

use App\Formation\Contenu\Bloc;
use App\Formation\Contenu\Catalogue;
use App\Formation\Contenu\Texte;
use App\Formation\Contenu\TypeBloc;
use App\Formation\Parcours\StatutBloc;
use Symfony\Component\Filesystem\Filesystem;
use Twig\Environment;

/**
 * Écrit le site de lecture : les pages de la plateforme rendues avec les mêmes gabarits et le même moteur Markdown,
 * sans progression, sans verrou et sans formulaire (le gabarit reçoit « export » vrai).
 * Seuls les pages et public/atelier/ sont écrits : jamais outils/corriges/, docs/, .claude/ ni les tests.
 */
final class SiteExporteur
{
    public function __construct(
        private readonly Catalogue $catalogue,
        private readonly Environment $twig,
        private readonly string $dossierProjet,
    ) {
    }

    /** @return int le nombre de pages écrites */
    public function exporter(string $dossier): int
    {
        $systemeDeFichiers = new Filesystem();
        $systemeDeFichiers->mkdir($dossier);

        $pages = $this->pages();
        foreach ($pages as $fichier => $html) {
            $systemeDeFichiers->dumpFile($dossier.'/'.$fichier, $html);
        }
        $systemeDeFichiers->dumpFile($dossier.'/robots.txt', "User-agent: *\nDisallow: /\n");
        $systemeDeFichiers->mirror($this->dossierProjet.'/public/atelier', $dossier.'/atelier');

        return \count($pages);
    }

    /** @return array<string, string> le HTML de chaque page, par nom de fichier */
    private function pages(): array
    {
        $niveaux = $this->catalogue->niveaux();
        $groupes = [];
        foreach ($niveaux as $niveau) {
            $groupes[$niveau->id] = $this->catalogue->groupesDuNiveau($niveau);
        }

        $pages = [
            NomsDePages::ACCUEIL => $this->rendre('parcours.html.twig', 'formation_parcours', [
                'niveaux' => $niveaux,
                'groupes' => $groupes,
                'statuts' => [],
            ]),
            NomsDePages::BADGES => $this->rendre('badges.html.twig', 'formation_badges', [
                'cartes' => $this->catalogue->cartesDeBadges(),
            ]),
        ];

        $sections = $this->catalogue->sectionsDuProgramme();
        $pages[NomsDePages::PROGRAMME] = $this->rendre('programme.html.twig', 'formation_programme', [
            'sections' => $sections,
            'nbLignes' => array_sum(array_map(static fn (array $section): int => \count($section['lignes']), $sections)),
        ]);

        foreach ($this->catalogue->blocsOrdonnes() as $bloc) {
            $pages += $this->pagesDuBloc($bloc);
        }

        return $pages;
    }

    /** @return array<string, string> une page, ou une par étape pour une démonstration */
    private function pagesDuBloc(Bloc $bloc): array
    {
        $sujet = $this->catalogue->sujetDuBloc($bloc->id);
        // Les gabarits de leçon lisent ces variables ; rien de la progression : un bloc « en cours » qu'on ne peut pas terminer.
        $contexte = [
            'bloc' => $bloc,
            'sujet' => $sujet,
            'liste' => $sujet?->blocs ?? [$bloc],
            'statut' => StatutBloc::EnCours,
            'peutTerminer' => false,
            'reste' => 0,
            'parametresReste' => [],
        ];
        $markdown = $this->catalogue->contenuDuBloc($bloc->id);

        switch ($bloc->type) {
            case TypeBloc::Preparation:
                return [NomsDePages::bloc($bloc->id) => $this->rendre('lecon/preparation.html.twig', 'formation_lecon', $contexte + [
                    'texte' => $markdown,
                    'champs' => $bloc->champsFiche,
                ])];

            case TypeBloc::Demonstration:
                $etapes = Texte::etapes($markdown);
                $pages = [];
                foreach ($etapes as $indice => $_) {
                    $pages[NomsDePages::etape($bloc->id, $indice + 1)] = $this->rendre('lecon/demonstration.html.twig', 'formation_lecon', $contexte + [
                        'etapes' => $etapes,
                        'etape' => $indice + 1,
                    ]);
                }

                return $pages;

            case TypeBloc::Rappel:
            case TypeBloc::Lecture:
                return [NomsDePages::bloc($bloc->id) => $this->rendre('lecon/texte.html.twig', 'formation_lecon', $contexte + ['texte' => $markdown])];

            case TypeBloc::Exercice:
                [$enonce, $tests] = Texte::separerTestsAttendus($markdown);

                return [NomsDePages::bloc($bloc->id) => $this->rendre('lecon/exercice.html.twig', 'formation_lecon', $contexte + [
                    'enonce' => $enonce,
                    'testsAttendus' => $tests,
                ])];
        }
    }

    /** @param array<string, mixed> $contexte */
    private function rendre(string $gabarit, string $route, array $contexte): string
    {
        return $this->twig->render($gabarit, $contexte + ['export' => true, 'page_courante' => $route]);
    }
}
