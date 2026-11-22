<?php

namespace App\Tests\Formation\Exercice;

use App\Formation\Exercice\LanceurTests;
use App\Tests\Formation\AvecCopieDuProjet;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Dette D1 : le junior copie des fichiers en gardant leurs anciennes dates (`cp -p`, `rsync -a`, une archive). Leur
 * date de modification est alors antérieure à celle du cache de test : le cache Twig se croit à jour et sert
 * l'ancienne version du gabarit, d'où un faux rouge dans `bin/exercice test`.
 *
 * Le test chauffe le cache sur une copie du projet, remplace un gabarit Twig en lui redonnant une date de 2020
 * (`touch -d`), puis relance : le lanceur doit voir la nouvelle version.
 *
 * Lent (copie du projet, PHPUnit en sous-processus) : exclu de la suite par défaut, lancé à la demande :
 *   php bin/phpunit --group lent tests/Formation/Exercice/LanceurTestsCachePerimeTest.php
 *
 * @group lent
 */
class LanceurTestsCachePerimeTest extends TestCase
{
    use AvecCopieDuProjet;

    private const DOSSIER_TESTS = 'tests-cache-perime';

    public function testUnGabaritCopieAvecUneAncienneDateEstVuParLesTests(): void
    {
        $copie = $this->copierLeProjetComplet();
        $fs = new Filesystem();
        $fs->mkdir([$copie.'/'.self::DOSSIER_TESTS, $copie.'/src/Controller']);
        $fs->dumpFile($copie.'/src/Controller/CachePerimeController.php', <<<'PHP'
            <?php

            namespace App\Controller;

            use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
            use Symfony\Component\HttpFoundation\Response;
            use Symfony\Component\Routing\Annotation\Route;

            class CachePerimeController extends AbstractController
            {
                #[Route('/cache-perime', name: 'cache_perime')]
                public function index(): Response
                {
                    return $this->render('cache_perime.html.twig');
                }
            }
            PHP);
        $fs->dumpFile($copie.'/templates/cache_perime.html.twig', 'VERSION-ANCIENNE');
        $fs->dumpFile($copie.'/'.self::DOSSIER_TESTS.'/CachePerimeTest.php', <<<'PHP'
            <?php

            use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

            class CachePerimeTest extends WebTestCase
            {
                public function testLeGabaritEstLaVersionNouvelle(): void
                {
                    $client = static::createClient();
                    $client->request('GET', '/cache-perime');

                    self::assertStringContainsString('VERSION-NOUVELLE', (string) $client->getResponse()->getContent());
                }
            }
            PHP);

        $lanceur = new LanceurTests($copie);

        $premier = $lanceur->lancer(self::DOSSIER_TESTS);
        self::assertFalse($premier->vert, 'sans la version nouvelle le test est rouge, et le cache de test est chauffé');
        self::assertStringContainsString('VERSION-NOUVELLE', $premier->resume);

        // Le junior dépose la bonne version avec une date antérieure au cache.
        $fs->dumpFile($copie.'/templates/cache_perime.html.twig', 'VERSION-NOUVELLE');
        $fs->touch($copie.'/templates/cache_perime.html.twig', (int) strtotime('2020-01-01'));

        $second = $lanceur->lancer(self::DOSSIER_TESTS);

        self::assertTrue($second->vert, 'le gabarit a changé : le cache périmé ne doit pas le masquer. '.$second->resume);
    }
}
