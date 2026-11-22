<?php

namespace App\Tests\Exercices;

use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Base des tests d'exercice : avant chaque test, le schéma de la base de test est recréé
 * à partir des entités du junior (src/Entity). Sans entité, il n'y a rien à faire.
 *
 * Ces tests ne font pas partie de la suite par défaut : ils se lancent avec `bin/exercice test`.
 */
abstract class ExerciceTestCase extends WebTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        self::bootKernel();
        $this->recreerLeSchema();
        // Le test appellera createClient() : le noyau ne doit pas être déjà démarré.
        self::ensureKernelShutdown();
    }

    private function recreerLeSchema(): void
    {
        $conteneur = static::getContainer();
        if (!$conteneur->has('doctrine')) {
            return;
        }

        $em = $conteneur->get('doctrine')->getManager();
        $metadonnees = $em->getMetadataFactory()->getAllMetadata();
        if ([] === $metadonnees) {
            return;
        }

        $outil = new SchemaTool($em);
        $outil->dropSchema($metadonnees);
        $outil->createSchema($metadonnees);
        $em->clear();
    }
}
