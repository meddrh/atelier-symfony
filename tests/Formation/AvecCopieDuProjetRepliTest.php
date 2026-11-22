<?php

namespace App\Tests\Formation;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Sous Docker Desktop (Windows, macOS), les liens physiques sur le dossier monté peuvent être refusés :
 * la copie de vendor/ doit alors se faire en copie ordinaire plutôt que d'échouer.
 */
class AvecCopieDuProjetRepliTest extends TestCase
{
    private string $dossier;

    protected function setUp(): void
    {
        $this->dossier = sys_get_temp_dir().'/repli-'.bin2hex(random_bytes(4));
        $fs = new Filesystem();
        $fs->mkdir([$this->dossier.'/racine/vendor/paquet', $this->dossier.'/copie', $this->dossier.'/faux']);
        file_put_contents($this->dossier.'/racine/vendor/paquet/a.php', '<?php // a');
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->dossier);
    }

    public function testLaCopieEnLiensPhysiquesEstPreferee(): void
    {
        $this->executer($this->outil()->commande($this->dossier.'/racine', $this->dossier.'/copie'));

        self::assertFileExists($this->dossier.'/copie/vendor/paquet/a.php');
        self::assertSame(
            fileinode($this->dossier.'/racine/vendor/paquet/a.php'),
            fileinode($this->dossier.'/copie/vendor/paquet/a.php'),
            'même fichier sur le disque : lien physique',
        );
    }

    public function testSiLesLiensPhysiquesSontRefusesOnCopieNormalement(): void
    {
        // Un faux « cp » qui refuse -l, comme un disque monté qui ne gère pas les liens physiques.
        file_put_contents($this->dossier.'/faux/cp', "#!/bin/sh\ncase \"\$1\" in *l*) echo 'cp: liens refusés' >&2; exit 1;; esac\nexec /bin/cp \"\$@\"\n");
        chmod($this->dossier.'/faux/cp', 0755);

        $this->executer($this->outil()->commande($this->dossier.'/racine', $this->dossier.'/copie'), ['PATH' => $this->dossier.'/faux:'.getenv('PATH')]);

        self::assertFileExists($this->dossier.'/copie/vendor/paquet/a.php');
        self::assertNotSame(
            fileinode($this->dossier.'/racine/vendor/paquet/a.php'),
            fileinode($this->dossier.'/copie/vendor/paquet/a.php'),
            'copie ordinaire : un autre fichier sur le disque',
        );
    }

    /** @param array<string, string> $env */
    private function executer(string $commande, array $env = []): void
    {
        $env += ['PATH' => (string) getenv('PATH')];
        $processus = proc_open(['sh', '-c', $commande], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $tuyaux, null, $env);
        stream_get_contents($tuyaux[1]);
        $erreur = stream_get_contents($tuyaux[2]);
        self::assertSame(0, proc_close($processus), $erreur);
    }

    private function outil(): object
    {
        return new class {
            use AvecCopieDuProjet {
                commandeCopieDeVendor as private commandeInterne;
            }

            public function commande(string $racine, string $copie): string
            {
                return $this->commandeInterne($racine, $copie);
            }
        };
    }
}
