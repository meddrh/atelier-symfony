<?php

namespace App\Tests\Formation\Contenu;

use App\Formation\Contenu\ChargeurCatalogue;
use App\Formation\Contenu\Texte;
use App\Formation\Contenu\TypeBloc;
use PHPUnit\Framework\TestCase;

/**
 * Un exercice dont le dossier de tests contient un MigrationsTest (voir tests/Exercices/MigrationsTestCase.php) doit
 * l'annoncer dans « Tests attendus » : sinon le junior découvre un test rouge que sa consigne ne mentionne pas.
 */
class MigrationsAnnonceesTest extends TestCase
{
    public function testChaqueExerciceAvecUnMigrationsTestLAnnonce(): void
    {
        $racine = \dirname(__DIR__, 3);
        $catalogue = (new ChargeurCatalogue($racine.'/contenu', $racine))->charger();

        $sansPuce = [];
        $avecMigrationsTest = 0;
        foreach ($catalogue->blocsOrdonnes() as $bloc) {
            if (TypeBloc::Exercice !== $bloc->type || null === $bloc->dossierTests || !is_file($racine.'/'.$bloc->dossierTests.'/MigrationsTest.php')) {
                continue;
            }
            ++$avecMigrationsTest;
            [, $testsAttendus] = Texte::separerTestsAttendus($catalogue->contenuDuBloc($bloc->id));
            if (!str_contains((string) $testsAttendus, '`MigrationsTest`')) {
                $sansPuce[] = $bloc->id;
            }
        }

        self::assertGreaterThan(0, $avecMigrationsTest, 'garde du test : au moins un exercice a un MigrationsTest');
        self::assertSame([], $sansPuce, 'exercices dont « Tests attendus » ne cite pas `MigrationsTest` : '.implode(', ', $sansPuce));
    }
}
