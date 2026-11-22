<?php

namespace App\Formation;

use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * Le noyau de la plateforme de formation (/formation, /atelier, bin/exercice, bin/formation).
 *
 * Il est indépendant de l'application du junior (App\Kernel) : ses bundles sont écrits ici, sa configuration vit
 * dans config/formation/, ses services dans src/Formation/, ses gabarits dans templates/formation/, son cache dans
 * var/cache/formation_<environnement>. Il ne lit ni config/packages/, ni config/services.yaml, ni config/routes/,
 * ni config/bundles.php, ni le reste de src/, ni le reste de templates/, ni aucun fichier .env : une erreur du junior
 * dans l'un de ces fichiers ne peut donc pas l'empêcher de démarrer.
 */
class FormationKernel extends BaseKernel
{
    /** Valeur par défaut de la base de progression (variable d'environnement FORMATION_DB), partagée avec bin/exercice. */
    public const BASE_PAR_DEFAUT = '%kernel.project_dir%/var/formation.sqlite';

    use MicroKernelTrait;

    /** Le noyau de la plateforme, dans l'environnement réglé par config/formation/environnement.php. */
    public static function parDefaut(): self
    {
        /** @var array{environnement: string, debug: bool} $reglages */
        $reglages = require \dirname(__DIR__, 2).'/config/formation/environnement.php';

        return new self($reglages['environnement'], $reglages['debug']);
    }

    /** Fixé à la position de ce fichier : ne dépend pas de composer.json, que le junior pourrait casser. */
    public function getProjectDir(): string
    {
        return \dirname(__DIR__, 2);
    }

    public function getCacheDir(): string
    {
        return $this->getProjectDir().'/var/cache/formation_'.$this->environment;
    }

    public function getLogDir(): string
    {
        return $this->getProjectDir().'/var/log';
    }

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new TwigBundle();
    }

    private function getConfigDir(): string
    {
        return $this->getProjectDir().'/config/formation';
    }

    /** Le trait y surveille un fichier de bundles : ici, les bundles sont dans la classe. */
    private function getBundlesPath(): string
    {
        return __FILE__;
    }

    private function configureContainer(ContainerConfigurator $container, LoaderInterface $loader, ContainerBuilder $builder): void
    {
        $configuration = $this->getConfigDir();

        $container->parameters()->set('env(FORMATION_DB)', self::BASE_PAR_DEFAUT);
        $container->import($configuration.'/framework.yaml');
        $container->import($configuration.'/twig.yaml');
        $container->import($configuration.'/services.yaml');
        // Les accolades forcent la lecture par motif (comme « {packages} » dans la recette) : sans elles, un
        // environnement sans dossier propre (dev, prod) ferait échouer l'import.
        $container->import($configuration.'/{'.$this->environment.'}/*.yaml');
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import($this->getConfigDir().'/routes.yaml');
    }
}
