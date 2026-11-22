<?php

namespace App\Formation\Twig;

use App\Formation\Progression\ProgressionStore;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Fonction Twig `duree_seance()` : la durée de séance réglée (minutes). C'est l'unique façon
 * dont les gabarits de la plateforme la lisent (barre du haut, accueil).
 */
final class DureeSeanceExtension extends AbstractExtension
{
    public function __construct(private readonly ProgressionStore $store)
    {
    }

    public function getFunctions(): array
    {
        return [new TwigFunction('duree_seance', $this->store->duree(...))];
    }
}
