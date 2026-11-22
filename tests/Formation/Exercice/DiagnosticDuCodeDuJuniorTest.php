<?php

namespace App\Tests\Formation\Exercice;

use App\Formation\Exercice\DiagnosticDuCodeDuJunior;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Le diagnostic nomme le fichier du junior qui explique un rouge, sans jamais en accuser un qui est valide ni un
 * fichier de la plateforme. (Le démarrage du noyau du junior est couvert par IsolationDuJuniorTest, test lent.)
 */
class DiagnosticDuCodeDuJuniorTest extends TestCase
{
    private string $projet;

    protected function setUp(): void
    {
        $this->projet = sys_get_temp_dir().'/diagnostic-'.bin2hex(random_bytes(4));
        (new Filesystem())->mkdir($this->projet);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->projet);
    }

    public function testUneErreurDeSyntaxePhpEstNommeeAvecSaLigne(): void
    {
        $this->ecrire('src/Controller/Casse.php', "<?php\nclass Casse\n{ public function x( {\n}\n");

        $cause = $this->diagnostic();

        self::assertStringContainsString('src/Controller/Casse.php', (string) $cause);
        self::assertStringContainsString('ligne 3', (string) $cause);
        self::assertStringStartsWith('Erreur dans ton code, dans ', (string) $cause);
    }

    public function testUnYamlInvalideEstNomme(): void
    {
        $this->ecrire('config/services.yaml', "services:\n  _defaults: [oups\n");

        self::assertStringContainsString('config/services.yaml', (string) $this->diagnostic());
    }

    public function testUnGabaritTwigInvalideEstNomme(): void
    {
        $this->ecrire('templates/base.html.twig', "<html>{% block corps %}{{ oups \n");

        self::assertStringContainsString('templates/base.html.twig', (string) $this->diagnostic());
    }

    public function testDuCodeValideNEstJamaisAccuse(): void
    {
        $this->ecrire('src/Controller/Bon.php', "<?php\nnamespace App\\Controller;\nclass Bon {}\n");
        // Balises Twig et fonctions d'une extension du junior : inconnues ici, donc ignorées. Étiquette YAML de Symfony.
        $this->ecrire('templates/liste.html.twig', "{{ form_start(form) }}{% maballise %}{{ x|monfiltre }}{% endmaballise %}\n");
        $this->ecrire('config/services.yaml', "services:\n  App\\Machin:\n    arguments: [!tagged_iterator app.truc]\n");

        self::assertNull($this->diagnostic());
    }

    public function testLaPlateformeNEstJamaisExaminee(): void
    {
        $this->ecrire('src/Formation/Casse.php', "<?php\nclass {{\n");
        $this->ecrire('config/formation/services.yaml', "services: [oups\n");
        $this->ecrire('templates/formation/base.html.twig', "{{ oups \n");

        self::assertNull($this->diagnostic());
    }

    private function diagnostic(): ?string
    {
        return (new DiagnosticDuCodeDuJunior($this->projet))->chercher();
    }

    private function ecrire(string $chemin, string $contenu): void
    {
        (new Filesystem())->dumpFile($this->projet.'/'.$chemin, $contenu);
    }
}
