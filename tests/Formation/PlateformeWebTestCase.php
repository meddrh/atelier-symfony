<?php

namespace App\Tests\Formation;

use App\Formation\FormationKernel;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Base des tests fonctionnels de la plateforme : ils démarrent FormationKernel, jamais App\Kernel (celui du junior,
 * que KERNEL_CLASS désigne pour les tests d'exercice).
 */
abstract class PlateformeWebTestCase extends WebTestCase
{
    protected static function getKernelClass(): string
    {
        return FormationKernel::class;
    }
}
