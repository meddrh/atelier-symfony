<?php

namespace App\Tests\Formation\Exercice;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Le vrai script bin/exercice, avant même le démarrage du noyau : une base illisible ou un contenu
 * invalide donnent un message clair et le code 2, sans trace brute ni rien d'enregistré.
 */
class BinExerciceErreursTest extends TestCase
{
    use LanceBinExercice;

    private const RACINE = __DIR__.'/../../..';

    private string $projet;

    protected function setUp(): void
    {
        $this->projet = sys_get_temp_dir().'/bin-exercice-'.bin2hex(random_bytes(4));
        $fs = new Filesystem();
        $fs->mkdir($this->projet.'/bin');
        $fs->copy(self::RACINE.'/bin/exercice', $this->projet.'/bin/exercice');
        $fs->symlink((string) realpath(self::RACINE.'/vendor'), $this->projet.'/vendor');
        // Ni .env ni config/ : bin/exercice n'en lit aucun avant le noyau ; FORMATION_DB vient de l'environnement.
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->projet);
    }

    public function testUneBaseIllisibleDonneUnMessageClairEtLeCode2(): void
    {
        $base = $this->projet.'/progression.sqlite';
        file_put_contents($base, str_repeat('ceci n\'est pas une base SQLite. ', 40));
        $avant = file_get_contents($base);

        [$code, $sortie] = $this->lancerBinExercice($this->projet, ['FORMATION_DB' => $base]);

        self::assertSame(2, $code, $sortie);
        self::assertStringContainsString('La base de progression est illisible : ', $sortie);
        self::assertStringContainsString('Ce n\'est pas ton code : préviens Mohammed.', $sortie);
        self::assertStringNotContainsString('Stack trace', $sortie);
        self::assertStringNotContainsString('Fatal error', $sortie);
        self::assertSame($avant, file_get_contents($base), 'rien n\'est enregistré');
    }

    public function testUnContenuInvalideDonneUnMessageClairEtLeCode2(): void
    {
        mkdir($this->projet.'/contenu');
        file_put_contents($this->projet.'/contenu/parcours.yaml', "niveaux: []\n");
        file_put_contents($this->projet.'/contenu/programme.yaml', "sections:\n  - id: s\n    titre: S\n    lignes:\n      - { id: l1, libelle: L1 }\n");
        $base = $this->projet.'/progression.sqlite';

        [$code, $sortie] = $this->lancerBinExercice($this->projet, ['FORMATION_DB' => $base]);

        self::assertSame(2, $code, $sortie);
        self::assertStringContainsString('Le contenu de la formation est invalide : parcours.yaml : la liste « niveaux » est absente ou vide.', $sortie);
        self::assertStringContainsString('Ce n\'est pas ton code : préviens Mohammed.', $sortie);
        self::assertStringNotContainsString('Stack trace', $sortie);
        $pdo = new \PDO('sqlite:'.$base);
        self::assertSame('0', (string) $pdo->query('SELECT COUNT(*) FROM lancement')->fetchColumn());
        self::assertSame('0', (string) $pdo->query('SELECT COUNT(*) FROM bloc_fait')->fetchColumn());
    }
}
