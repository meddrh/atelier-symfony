<?php

namespace App\Tests\Exercices\ServicesExternesCacheVerrou;

use App\Kernel;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\Lock\Store\InMemoryStore;

/**
 * Le noyau du test de l'archivage : le même que celui du projet, avec une seule différence, en test seulement : la
 * fabrique de verrous est FabriqueDeVerrousGardes, sur un stockage en mémoire (aucun fichier, aucune variable
 * d'environnement). La commande du junior reçoit cette fabrique par autowiring, comme la vraie.
 */
class KernelAvecVerrousGardes extends Kernel
{
    protected function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new class() implements CompilerPassInterface {
            public function process(ContainerBuilder $container): void
            {
                $fabrique = $container->getDefinition('lock.default.factory');
                $fabrique->setClass(FabriqueDeVerrousGardes::class);
                $fabrique->replaceArgument(0, new Definition(InMemoryStore::class));
            }
        });
    }
}
