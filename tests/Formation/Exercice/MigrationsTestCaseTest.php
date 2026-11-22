<?php

namespace App\Tests\Formation\Exercice;

use App\Tests\Formation\AvecCopieDuProjet;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

/**
 * Le test des migrations du junior (tests/Exercices/MigrationsTestCase.php), joué sur une copie jetable du projet
 * avec une petite entité et un faux sujet :
 *  - il voit une colonne ajoutée à l'entité entre deux exécutions (la console fille ne doit pas lire un cache
 *    de métadonnées périmé, ce que fait `--no-debug`) ;
 *  - un sujet qui démarre sans aucune migration reçoit « écris une nouvelle migration », pas « tes migrations plantent ».
 *
 * Lent (copie du projet, phpunit en sous-processus) : exclu de la suite par défaut :
 *   php bin/phpunit --group lent tests/Formation/Exercice/MigrationsTestCaseTest.php
 *
 * @group lent
 */
class MigrationsTestCaseTest extends \PHPUnit\Framework\TestCase
{
    use AvecCopieDuProjet;

    private const TEST = 'tests/Exercices/Essai/MigrationsTest.php';

    private string $copie;

    protected function setUp(): void
    {
        $this->copie = $this->copierLeProjetComplet();
        (new Filesystem())->mkdir([$this->copie.'/tests/Exercices/Essai', $this->copie.'/src/Entity', $this->copie.'/migrations']);
        file_put_contents($this->copie.'/'.self::TEST, <<<'PHP'
            <?php

            namespace App\Tests\Exercices\Essai;

            use App\Tests\Exercices\MigrationsTestCase;

            class MigrationsTest extends MigrationsTestCase
            {
            }
            PHP);
    }

    public function testUneColonneAjouteeALEntiteEntreDeuxExecutionsEstVue(): void
    {
        $this->ecrireEntite(false);
        file_put_contents($this->copie.'/migrations/Version20260101000000.php', <<<'PHP'
            <?php

            namespace DoctrineMigrations;

            use Doctrine\DBAL\Schema\Schema;
            use Doctrine\Migrations\AbstractMigration;

            final class Version20260101000000 extends AbstractMigration
            {
                public function up(Schema $schema): void
                {
                    $this->addSql('CREATE TABLE essai_cache (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, nom VARCHAR(50) NOT NULL)');
                }

                public function down(Schema $schema): void
                {
                    $this->addSql('DROP TABLE essai_cache');
                }
            }
            PHP);

        [$code, $sortie] = $this->lancerLeTest();
        self::assertSame(0, $code, 'point de départ : entité et migration d\'accord. '.$sortie);

        // Le junior ajoute une colonne à son entité sans écrire la migration : la deuxième exécution doit le voir.
        $this->ecrireEntite(true);
        [$code, $sortie] = $this->lancerLeTest();

        self::assertNotSame(0, $code, 'la colonne ajoutée est invisible (cache de métadonnées périmé) : '.$sortie);
        self::assertStringContainsString('nouvelle', $sortie);
    }

    public function testSansAucuneMigrationOnDemandeD_EcrireUneNouvelleMigration(): void
    {
        $this->ecrireEntite(false);

        [$code, $sortie] = $this->lancerLeTest();

        self::assertNotSame(0, $code, $sortie);
        self::assertStringContainsString('nouvelle migration', $sortie);
        self::assertStringNotContainsString('plantent', $sortie, 'pas de migration n\'est pas une migration qui plante');
    }

    private function ecrireEntite(bool $avecNouvelleColonne): void
    {
        $colonne = $avecNouvelleColonne ? "\n    #[ORM\\Column(type: 'integer')]\n    private int \$nouvelle = 0;\n" : '';
        file_put_contents($this->copie.'/src/Entity/EssaiCache.php', <<<PHP
            <?php

            namespace App\Entity;

            use Doctrine\ORM\Mapping as ORM;

            #[ORM\Entity]
            #[ORM\Table(name: 'essai_cache')]
            class EssaiCache
            {
                #[ORM\Id]
                #[ORM\GeneratedValue]
                #[ORM\Column(type: 'integer')]
                private ?int \$id = null;

                #[ORM\Column(length: 50)]
                private string \$nom = '';
            {$colonne}}
            PHP);
    }

    /** @return array{int, string} */
    private function lancerLeTest(): array
    {
        $processus = new Process([\PHP_BINARY, 'bin/phpunit', self::TEST], $this->copie, null, null, 180);
        $processus->run();

        return [(int) $processus->getExitCode(), $processus->getOutput().$processus->getErrorOutput()];
    }
}
