<?php

namespace App\Formation\Parcours;

use App\Formation\Contenu\Badge;
use App\Formation\Contenu\Bloc;
use App\Formation\Contenu\Catalogue;
use App\Formation\Contenu\Niveau;
use App\Formation\Contenu\TypeBloc;
use App\Formation\Progression\Lancement;
use App\Formation\Progression\ProgressionStore;

/**
 * Règles du parcours : progression strictement linéaire, séance du jour, badges.
 */
final class Parcours
{
    /** Nombre de lancements non verts à partir duquel l'aide est proposée. */
    public const SEUIL_AIDE = 4;

    public function __construct(
        private readonly Catalogue $catalogue,
        private readonly ProgressionStore $store,
    ) {
    }

    /** Premier bloc non fait, dans l'ordre du parcours ; null quand tout est fait. */
    public function blocCourant(): ?Bloc
    {
        $faits = $this->store->blocsFaits();
        foreach ($this->catalogue->blocsOrdonnes() as $bloc) {
            if (!isset($faits[$bloc->id])) {
                return $bloc;
            }
        }

        return null;
    }

    public function statut(string $blocId): StatutBloc
    {
        $this->catalogue->bloc($blocId);
        if ($this->store->estFait($blocId)) {
            return StatutBloc::Fait;
        }

        return $this->blocCourant()?->id === $blocId ? StatutBloc::EnCours : StatutBloc::Verrouille;
    }

    /**
     * Statut de chaque bloc du catalogue, dans l'ordre du parcours, en une seule lecture des blocs faits.
     *
     * @return array<string, StatutBloc>
     */
    public function statuts(): array
    {
        $faits = $this->store->blocsFaits();
        $statuts = [];
        $courantTrouve = false;
        foreach ($this->catalogue->blocsOrdonnes() as $bloc) {
            if (isset($faits[$bloc->id])) {
                $statuts[$bloc->id] = StatutBloc::Fait;
            } elseif (!$courantTrouve) {
                $courantTrouve = true;
                $statuts[$bloc->id] = StatutBloc::EnCours;
            } else {
                $statuts[$bloc->id] = StatutBloc::Verrouille;
            }
        }

        return $statuts;
    }

    public function seanceDuJour(): Seance
    {
        $courant = $this->blocCourant();
        if (null === $courant) {
            return new Seance([], false, true);
        }

        $reste = $this->store->duree();
        $blocs = [];
        $suivre = false;
        foreach ($this->catalogue->blocsOrdonnes() as $bloc) {
            if ($bloc->id === $courant->id) {
                $suivre = true;
            }
            if (!$suivre) {
                continue;
            }
            if (!self::tient($bloc, $reste, [] === $blocs)) {
                break;
            }
            $blocs[] = $bloc;
            $reste -= $bloc->dureeMinutes;
            if (TypeBloc::Exercice === $bloc->type) {
                // Un exercice clôt toujours la séance.
                break;
            }
        }

        $dernier = end($blocs);
        $exerciceEnCours = TypeBloc::Exercice === $dernier->type && [] !== $this->store->lancements($dernier->id);

        return new Seance($blocs, $exerciceEnCours, false);
    }

    /**
     * Bloc à enchaîner une fois un bloc terminé, quand il reste « $resteMinutes » à la séance ;
     * null si le bloc courant ne tient plus dans ce budget. C'est la même règle que seanceDuJour(),
     * dont seul le bloc qui ouvre la séance est dispensé.
     */
    public function blocSuivantDansLaSeance(int $resteMinutes): ?Bloc
    {
        $courant = $this->blocCourant();

        return null !== $courant && self::tient($courant, $resteMinutes, false) ? $courant : null;
    }

    /** Règle unique de la séance : un bloc y entre s'il tient dans le budget restant, ou s'il l'ouvre. */
    private static function tient(Bloc $bloc, int $resteMinutes, bool $ouvreLaSeance): bool
    {
        return $ouvreLaSeance || $bloc->dureeMinutes <= $resteMinutes;
    }

    public function peutTerminer(string $blocId): bool
    {
        $courant = $this->blocCourant();
        if (null === $courant || $courant->id !== $blocId || TypeBloc::Exercice === $courant->type) {
            return false;
        }

        return TypeBloc::Preparation !== $courant->type || $this->ficheComplete($blocId);
    }

    public function terminer(string $blocId): void
    {
        if (!$this->peutTerminer($blocId)) {
            throw new \LogicException(sprintf('Le bloc « %s » ne peut pas être terminé maintenant.', $blocId));
        }
        $this->store->marquerFait($blocId, new \DateTimeImmutable());
    }

    /** Chaque champ déclaré par le bloc a une réponse non vide après trim. */
    public function ficheComplete(string $blocId): bool
    {
        $champs = $this->catalogue->bloc($blocId)->champsFiche;
        if ([] === $champs) {
            return true;
        }
        $reponses = $this->store->fiche($blocId) ?? [];
        foreach ($champs as $champ) {
            $reponse = $reponses[$champ['cle']] ?? '';
            if (!\is_string($reponse) || '' === trim($reponse)) {
                return false;
            }
        }

        return true;
    }

    /** Enregistre un lancement de tests ; s'il est vert, l'exercice est fait. */
    public function enregistrerResultat(string $blocId, Lancement $l): void
    {
        if (TypeBloc::Exercice !== $this->catalogue->bloc($blocId)->type) {
            throw new \LogicException(sprintf('Le bloc « %s » n\'est pas un exercice : aucun résultat de tests à enregistrer.', $blocId));
        }
        // Le lancement et le bloc fait vont ensemble : un échec au milieu n'écrit rien.
        $this->store->transaction(function () use ($blocId, $l): void {
            $this->store->enregistrerLancement($l);
            if ($l->vert) {
                $this->store->marquerFait($blocId, $l->date);
            }
        });
    }

    /**
     * Badges des sujets dont l'exercice est fait ; le badge d'un niveau suit ceux de ses sujets
     * quand tous les exercices du niveau sont faits.
     *
     * @return list<Badge>
     */
    public function badgesObtenus(): array
    {
        $badges = [];
        foreach ($this->catalogue->niveaux() as $niveau) {
            $nbBadges = 0;
            $nbObtenus = 0;
            foreach ($niveau->sujets as $sujet) {
                if (null === $sujet->badge) {
                    continue;
                }
                ++$nbBadges;
                if ($this->exerciceDuSujetFait($sujet->blocs)) {
                    ++$nbObtenus;
                    $badges[] = $sujet->badge;
                }
            }
            if ($nbBadges > 0 && $nbObtenus === $nbBadges) {
                $badges[] = $niveau->badgeNiveau;
            }
        }

        return $badges;
    }

    /**
     * Blocs faits sur blocs du niveau (rappels et lectures compris).
     *
     * @return array{faits: int, total: int}
     */
    public function avancementDuNiveau(Niveau $niveau): array
    {
        $faitsParId = $this->store->blocsFaits();
        $faits = 0;
        foreach ($niveau->blocs as $bloc) {
            isset($faitsParId[$bloc->id]) && ++$faits;
        }

        return ['faits' => $faits, 'total' => \count($niveau->blocs)];
    }

    /** @return array<string, bool> par identifiant de ligne : vraie si un bloc fait la cite */
    public function lignesCouvertes(): array
    {
        $faits = $this->store->blocsFaits();
        $citees = [];
        foreach ($this->catalogue->blocsOrdonnes() as $bloc) {
            if (isset($faits[$bloc->id])) {
                foreach ($bloc->programme as $ligne) {
                    $citees[$ligne] = true;
                }
            }
        }

        $couvertes = [];
        foreach ($this->catalogue->programme() as $ligne) {
            $couvertes[$ligne->id] = isset($citees[$ligne->id]);
        }

        return $couvertes;
    }

    /**
     * L'étape d'une démonstration à afficher, toujours comprise entre 1 et $nombreDEtapes. Une étape demandée (?etape=)
     * gagne ; sinon on rouvre celle où le junior s'était arrêté, ou la première. Une étape demandée illisible (autre
     * chose que 1 à 3 chiffres) ramène à la première, une étape hors bornes à la dernière (ou à la première si zéro).
     */
    public function etapeAAfficher(string $blocId, mixed $demandee, int $nombreDEtapes): int
    {
        if (null === $demandee) {
            $voulue = $this->store->etapeMemorisee($blocId) ?? 1;
        } else {
            $voulue = \is_string($demandee) && 1 === preg_match('/^\d{1,3}$/', $demandee) ? (int) $demandee : 1;
        }

        return min(max($voulue, 1), max($nombreDEtapes, 1));
    }

    public function besoinDAide(string $blocId): bool
    {
        return $this->store->nombreDeRouges($blocId) >= self::SEUIL_AIDE && !$this->store->estFait($blocId);
    }

    /** @param list<Bloc> $blocs */
    private function exerciceDuSujetFait(array $blocs): bool
    {
        foreach ($blocs as $bloc) {
            if (TypeBloc::Exercice === $bloc->type) {
                return $this->store->estFait($bloc->id);
            }
        }

        return false;
    }
}
