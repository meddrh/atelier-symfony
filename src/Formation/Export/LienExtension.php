<?php

namespace App\Formation\Export;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Fonction Twig `lien(route, paramètres, export)` : l'adresse d'une page de la plateforme, ou, quand « export » est
 * vrai, le nom du fichier relatif de la même page dans le site exporté. Les gabarits n'ont ainsi qu'un seul appel.
 */
final class LienExtension extends AbstractExtension
{
    public function __construct(private readonly UrlGeneratorInterface $routeur)
    {
    }

    public function getFunctions(): array
    {
        return [new TwigFunction('lien', $this->lien(...))];
    }

    /** @param array<string, mixed> $parametres */
    public function lien(string $route, array $parametres = [], bool $export = false): string
    {
        return $export ? NomsDePages::depuisRoute($route, $parametres) : $this->routeur->generate($route, $parametres);
    }
}
