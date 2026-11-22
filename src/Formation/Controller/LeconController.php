<?php

namespace App\Formation\Controller;

use App\Formation\Contenu\Bloc;
use App\Formation\Contenu\Catalogue;
use App\Formation\Contenu\Texte;
use App\Formation\Contenu\TypeBloc;
use App\Formation\Exercice\ListeDesRouges;
use App\Formation\Parcours\Parcours;
use App\Formation\Parcours\StatutBloc;
use App\Formation\Progression\ProgressionStore;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Page d'un bloc : fiche de préparation, démonstration, rappel, lecture ou exercice.
 *
 * Le paramètre « reste » (minutes) suit le budget de la séance en cours : sans lui, ouvrir un
 * bloc démarre une séance de la durée réglée. Il ne sert qu'à enchaîner les blocs d'une séance
 * de 30, 45 ou 60 minutes ; il n'ouvre rien de plus que ce que le parcours autorise.
 */
final class LeconController extends AbstractController
{
    private const REQUIREMENT_BLOC = '[A-Za-z0-9_-]+';
    /** Longueur maximale d'une réponse de fiche (garde-fou, pas une règle pédagogique). */
    private const LONGUEUR_REPONSE = 5000;

    public function __construct(
        private readonly Parcours $parcours,
        private readonly Catalogue $catalogue,
        private readonly ProgressionStore $store,
    ) {
    }

    #[Route('/formation/lecon/{blocId}', name: 'formation_lecon', requirements: ['blocId' => self::REQUIREMENT_BLOC], methods: ['GET'])]
    public function afficher(string $blocId, Request $request): Response
    {
        $bloc = $this->bloc($blocId);
        if (null !== $refus = $this->refuserSiVerrouille($bloc)) {
            return $refus;
        }

        return $this->vue($bloc, $this->resteDe($request->query->all()), $request->query->all()['etape'] ?? null);
    }

    #[Route('/formation/lecon/{blocId}/fiche', name: 'formation_fiche', requirements: ['blocId' => self::REQUIREMENT_BLOC], methods: ['POST'])]
    public function enregistrerFiche(string $blocId, Request $request): Response
    {
        $bloc = $this->bloc($blocId);
        if (null !== $refus = $this->refuserSiVerrouille($bloc)) {
            return $refus;
        }
        if (TypeBloc::Preparation !== $bloc->type) {
            throw $this->createNotFoundException('Ce bloc n\'a pas de fiche.');
        }
        $saisie = $request->request->all();
        $this->verifierJeton('fiche', $saisie);

        $envoi = \is_array($saisie['fiche'] ?? null) ? $saisie['fiche'] : [];
        $brutes = [];
        $erreurs = [];
        foreach ($bloc->champsFiche as $champ) {
            $valeur = $envoi[$champ['cle']] ?? '';
            $brutes[$champ['cle']] = \is_string($valeur) ? $valeur : '';
            if (mb_strlen(trim($brutes[$champ['cle']])) > self::LONGUEUR_REPONSE) {
                $erreurs[] = sprintf('Ta réponse « %s » dépasse 5 000 caractères : raccourcis-la.', $champ['libelle']);
            }
        }
        if ([] !== $erreurs) {
            // Rien n'est enregistré ni tronqué : la saisie revient telle quelle dans le formulaire.
            return $this->vue($bloc, $this->resteDe($saisie), null, saisie: $brutes, erreursFiche: $erreurs);
        }
        $reponses = array_map(static fn (string $valeur): string => trim($valeur), $brutes);
        $this->store->enregistrerFiche($bloc->id, $reponses);

        if (!$this->parcours->ficheComplete($bloc->id)) {
            return $this->vue($bloc, $this->resteDe($saisie), null, ficheIncomplete: true);
        }

        return $this->redirectToRoute('formation_lecon', ['blocId' => $bloc->id] + $this->parametresReste($this->resteDe($saisie)));
    }

    #[Route('/formation/lecon/{blocId}/terminer', name: 'formation_terminer', requirements: ['blocId' => self::REQUIREMENT_BLOC], methods: ['POST'])]
    public function terminer(string $blocId, Request $request): Response
    {
        $bloc = $this->bloc($blocId);
        if (null !== $refus = $this->refuserSiVerrouille($bloc)) {
            return $refus;
        }
        $saisie = $request->request->all();
        $this->verifierJeton('terminer', $saisie);
        $reste = $this->resteDe($saisie);

        if (!$this->parcours->peutTerminer($bloc->id)) {
            $this->addFlash('erreur', $this->raisonDuRefus($bloc));

            return $this->redirectToRoute('formation_lecon', ['blocId' => $bloc->id] + $this->parametresReste($reste));
        }

        $this->parcours->terminer($bloc->id);
        $this->addFlash('ok', 'Bloc terminé.');

        // On enchaîne tant que le budget de la séance n'est pas épuisé : la règle est celle de l'accueil.
        $reste -= $bloc->dureeMinutes;
        $suivant = $this->parcours->blocSuivantDansLaSeance($reste);
        if (null !== $suivant) {
            return $this->redirectToRoute('formation_lecon', ['blocId' => $suivant->id] + $this->parametresReste($reste));
        }

        return $this->redirectToRoute('formation_seance');
    }

    private function bloc(string $blocId): Bloc
    {
        if (!$this->catalogue->existe($blocId)) {
            throw $this->createNotFoundException('Bloc inconnu.');
        }

        return $this->catalogue->bloc($blocId);
    }

    /** Un bloc verrouillé n'est ni affiché ni modifiable : 403, avec le bloc à terminer d'abord. */
    private function refuserSiVerrouille(Bloc $bloc): ?Response
    {
        if (StatutBloc::Verrouille !== $this->parcours->statut($bloc->id)) {
            return null;
        }

        return $this->render('lecon/verrouille.html.twig', [
            'courant' => $this->parcours->blocCourant(),
        ], new Response('', Response::HTTP_FORBIDDEN));
    }

    /** @param array<string, mixed> $saisie */
    private function verifierJeton(string $id, array $saisie): void
    {
        $jeton = $saisie['_token'] ?? null;
        if (!\is_string($jeton) || !$this->isCsrfTokenValid($id, $jeton)) {
            throw new AccessDeniedHttpException('Jeton invalide.');
        }
    }

    private function raisonDuRefus(Bloc $bloc): string
    {
        if (StatutBloc::Fait === $this->parcours->statut($bloc->id)) {
            return 'Ce bloc est déjà terminé.';
        }
        if (TypeBloc::Exercice === $bloc->type) {
            return 'Un exercice se termine quand ses tests passent : lance bin/exercice test dans ton terminal.';
        }
        if (TypeBloc::Preparation === $bloc->type && !$this->parcours->ficheComplete($bloc->id)) {
            return 'Remplis chaque partie de ta fiche avant de continuer.';
        }

        return 'Ce bloc ne peut pas être terminé maintenant.';
    }

    /**
     * Minutes restantes de la séance : celles du paramètre « reste » s'il est valide, sinon la durée réglée.
     *
     * @param array<string, mixed> $parametres
     */
    private function resteDe(array $parametres): int
    {
        $reste = $parametres['reste'] ?? null;
        if (\is_string($reste) && 1 === preg_match('/^\d{1,2}$/', $reste) && (int) $reste <= max(ProgressionStore::DUREES)) {
            return (int) $reste;
        }

        return $this->store->duree();
    }

    /**
     * Paramètre d'URL à ajouter pour garder le budget de la séance ; rien quand c'est la durée réglée.
     *
     * @return array{reste?: int}
     */
    private function parametresReste(int $reste): array
    {
        return $reste === $this->store->duree() ? [] : ['reste' => $reste];
    }

    /**
     * @param array<string, string>|null $saisie       saisie refusée à réafficher dans la fiche (sinon, la fiche enregistrée)
     * @param list<string>               $erreursFiche
     */
    private function vue(Bloc $bloc, int $reste, mixed $etape, bool $ficheIncomplete = false, ?array $saisie = null, array $erreursFiche = []): Response
    {
        $sujet = $this->catalogue->sujetDuBloc($bloc->id);
        $liste = $sujet?->blocs ?? [$bloc];
        $tous = $this->parcours->statuts();
        $statuts = [];
        foreach ($liste as $item) {
            $statuts[$item->id] = $tous[$item->id];
        }
        $statut = $statuts[$bloc->id];
        $peutTerminer = $this->parcours->peutTerminer($bloc->id);

        $contexte = [
            'bloc' => $bloc,
            'sujet' => $sujet,
            'liste' => $liste,
            'statuts' => $statuts,
            'statut' => $statut,
            'peutTerminer' => $peutTerminer,
            // Budget de la séance avant ce bloc : porté par les formulaires, jamais déduit de l'URL seule.
            'reste' => $reste,
            'parametresReste' => $this->parametresReste($reste),
        ];
        $markdown = $this->catalogue->contenuDuBloc($bloc->id);

        switch ($bloc->type) {
            case TypeBloc::Preparation:
                $contexte += [
                    'texte' => $markdown,
                    'champs' => $bloc->champsFiche,
                    'reponses' => $saisie ?? $this->store->fiche($bloc->id) ?? [],
                    // Une saisie refusée ne se compare pas : le formulaire revient, même si l'ancienne fiche était complète.
                    'complete' => null === $saisie && $this->parcours->ficheComplete($bloc->id),
                    'ficheIncomplete' => $ficheIncomplete,
                    'erreursFiche' => $erreursFiche,
                ];

                return $this->render(
                    'lecon/preparation.html.twig',
                    $contexte,
                    [] === $erreursFiche ? null : new Response('', Response::HTTP_UNPROCESSABLE_ENTITY),
                );

            case TypeBloc::Demonstration:
                $etapes = Texte::etapes($markdown);
                $numero = $this->parcours->etapeAAfficher($bloc->id, $etape, \count($etapes));
                // Rouvrir la même étape n'écrit rien.
                if ($numero !== $this->store->etapeMemorisee($bloc->id)) {
                    $this->store->memoriserEtape($bloc->id, $numero);
                }

                return $this->render('lecon/demonstration.html.twig', $contexte + [
                    'etapes' => $etapes,
                    'etape' => $numero,
                ]);

            case TypeBloc::Rappel:
            case TypeBloc::Lecture:
                return $this->render('lecon/texte.html.twig', $contexte + ['texte' => $markdown]);

            case TypeBloc::Exercice:
                [$enonce, $tests] = Texte::separerTestsAttendus($markdown);
                $lancements = $this->store->lancements($bloc->id);

                return $this->render('lecon/exercice.html.twig', $contexte + [
                    'enonce' => $enonce,
                    'testsAttendus' => $tests,
                    'dernier' => $lancements[0] ?? null,
                    'rouges' => isset($lancements[0]) && !$lancements[0]->vert ? ListeDesRouges::depuis($lancements[0]->echecs, (string) $bloc->dossierTests) : null,
                    'historique' => \array_slice($lancements, 0, 20),
                    'nbLancements' => \count($lancements),
                    'besoinDAide' => $this->parcours->besoinDAide($bloc->id),
                    'badge' => $sujet?->badge,
                ]);
        }
    }
}
