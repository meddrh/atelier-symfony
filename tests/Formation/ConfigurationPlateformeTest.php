<?php

namespace App\Tests\Formation;

use App\Formation\FormationKernel;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;

/**
 * La plateforme a son propre noyau (FormationKernel) : elle ne lit rien des fichiers que le junior modifie
 * (config/packages/, config/services.yaml, config/routes/, config/bundles.php, src/ hors src/Formation/,
 * templates/ hors templates/formation/, .env). La preuve : elle démarre dans un projet qui n'en contient aucun.
 * Les casses réelles (serveur, bin/exercice) sont dans Exercice/IsolationDuJuniorTest, test lent.
 */
class ConfigurationPlateformeTest extends TestCase
{
    private const RACINE = __DIR__.'/../..';

    private ?string $projet = null;

    protected function tearDown(): void
    {
        if (null !== $this->projet) {
            (new Filesystem())->remove($this->projet);
        }
    }

    public function testLesFichiersDuJuniorNeMentionnentPasLaPlateforme(): void
    {
        foreach (['src/Kernel.php', 'config/services.yaml', 'config/packages/twig.yaml', 'config/routes/attributes.yaml'] as $fichier) {
            $contenu = (string) file_get_contents(self::RACINE.'/'.$fichier);
            // Seule mention permise : l'exclusion de src/Formation/ de la ressource « App\ ».
            $contenu = str_replace(["'../src/Formation/'", 'src/Formation/FormationKernel.php'], '', $contenu);

            self::assertStringNotContainsStringIgnoringCase('Formation', $contenu, $fichier);
        }
    }

    public function testLeServicesYamlExclutLaPlateformeDeLaRessourceApp(): void
    {
        $contenu = (string) file_get_contents(self::RACINE.'/config/services.yaml');

        self::assertMatchesRegularExpression('#exclude:.*\'\.\./src/Formation/\'#s', $contenu);
    }

    public function testLeNoyauDuJuniorEstCeluiDeLaRecetteSymfony(): void
    {
        $noyau = (string) file_get_contents(self::RACINE.'/src/Kernel.php');

        self::assertStringNotContainsString('configureContainer', $noyau);
        self::assertStringNotContainsString('configureRoutes', $noyau);
    }

    public function testLaPlateformeDemarreDansUnProjetSansRienDuJunior(): void
    {
        $this->projet = $this->projetDeLaPlateformeSeule();
        $noyau = new NoyauDeCopie($this->projet);

        $reponse = $noyau->handle(Request::create('/formation'));

        self::assertSame(200, $reponse->getStatusCode(), 'La page /formation doit répondre 200.');
        self::assertStringContainsString('min</summary>', (string) $reponse->getContent(), 'La durée de la séance s\'affiche dans la barre du haut.');
        self::assertSame(200, $noyau->handle(Request::create('/formation/parcours'))->getStatusCode());
        $noyau->shutdown();
    }

    public function testLaPlateformeDemarreDansChaqueEnvironnement(): void
    {
        // test a son dossier config/formation/test/ ; dev et prod n'ont aucun dossier propre.
        foreach (['test', 'dev', 'prod'] as $environnement) {
            $this->projet = $this->projetDeLaPlateformeSeule();

            $noyau = new NoyauDeCopie($this->projet, $environnement);
            $noyau->boot();

            self::assertTrue($noyau->getContainer()->has('router'), $environnement);
            self::assertFalse($noyau->getContainer()->has('doctrine'), $environnement.' : pas de Doctrine dans la plateforme');
            $noyau->shutdown();
            (new Filesystem())->remove($this->projet);
        }
    }

    /**
     * Le composant des messages est installé pour le niveau Avancé : sans « messenger: false » dans
     * config/formation/framework.yaml, il s'activerait dans la plateforme (bus par défaut, dépréciation). Le service
     * est privé et retiré du conteneur compilé : on l'observe donc pendant la construction du conteneur.
     */
    public function testLaPlateformeNAPasDeBusDeMessages(): void
    {
        foreach (['test', 'dev', 'prod'] as $environnement) {
            $this->projet = $this->projetDeLaPlateformeSeule();

            $noyau = new NoyauDeCopie($this->projet, $environnement);
            $noyau->boot();

            self::assertNotNull($noyau->busDeMessagesDeclare, $environnement.' : la construction du conteneur a bien été observée');
            self::assertFalse($noyau->busDeMessagesDeclare, $environnement.' : la plateforme n\'envoie aucun message, pas de bus');
            $noyau->shutdown();
            (new Filesystem())->remove($this->projet);
        }
    }

    /** Projet minimal : la configuration, les classes, les gabarits et le contenu de la plateforme, rien d'autre. */
    private function projetDeLaPlateformeSeule(): string
    {
        $projet = sys_get_temp_dir().'/plateforme-'.bin2hex(random_bytes(4));
        $fs = new Filesystem();
        $fs->mkdir([$projet.'/var', $projet.'/config', $projet.'/templates', $projet.'/src']);
        $fs->mirror(self::RACINE.'/config/formation', $projet.'/config/formation');
        $fs->mirror(self::RACINE.'/templates/formation', $projet.'/templates/formation');
        $fs->symlink((string) realpath(self::RACINE.'/src/Formation'), $projet.'/src/Formation');
        $fs->symlink((string) realpath(self::RACINE.'/contenu'), $projet.'/contenu');
        // Le contenu de l'environnement de test (config/formation/test/formation.yaml).
        $fs->mkdir($projet.'/tests/Formation');
        $fs->symlink((string) realpath(self::RACINE.'/tests/Formation/fixtures'), $projet.'/tests/Formation/fixtures');

        return $projet;
    }
}

/** Le noyau de la plateforme, posé sur un projet minimal. */
final class NoyauDeCopie extends FormationKernel
{
    /** Ce que la construction du conteneur a vu : le bus de messages par défaut est-il déclaré ? (null : pas observé) */
    public ?bool $busDeMessagesDeclare = null;

    public function __construct(private readonly string $projet, string $environnement = 'test')
    {
        parent::__construct($environnement, 'prod' !== $environnement);
    }

    public function getProjectDir(): string
    {
        return $this->projet;
    }

    protected function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $container->addCompilerPass(new class($this) implements CompilerPassInterface {
            public function __construct(private readonly NoyauDeCopie $noyau)
            {
            }

            public function process(ContainerBuilder $container): void
            {
                $this->noyau->busDeMessagesDeclare = $container->has('messenger.default_bus');
            }
        }, PassConfig::TYPE_BEFORE_OPTIMIZATION, -10);
    }
}
