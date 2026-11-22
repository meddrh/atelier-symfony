<?php

namespace App\Tests\Formation;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

/**
 * Pour les tests qui altèrent le contenu : une copie jetable de fixtures/contenu-mini, modifiée
 * avant d'être lue, et supprimée en fin de test.
 *
 * Le dossier de la dernière copie est dans $this->temporaire.
 */
trait AvecContenuModifiable
{
    private ?string $temporaire = null;
    /** @var list<string> */
    private array $copiesDeContenu = [];

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->copiesDeContenu);
        $this->copiesDeContenu = [];
        $this->temporaire = null;
        parent::tearDown();
    }

    /** Copie le contenu de test, puis applique $mutation (tableau YAML -> tableau YAML) au fichier donné. */
    private function modifier(string $fichier, callable $mutation): void
    {
        $this->copierContenu();
        $chemin = $this->temporaire.'/'.$fichier;
        file_put_contents($chemin, Yaml::dump($mutation(Yaml::parseFile($chemin)), 6));
    }

    /** Copie le contenu de test, puis remplace le texte du fichier donné (YAML invalide ou vide compris). */
    private function ecrire(string $fichier, string $contenu): void
    {
        $this->copierContenu();
        file_put_contents($this->temporaire.'/'.$fichier, $contenu);
    }

    private function copierContenu(): void
    {
        $this->temporaire = sys_get_temp_dir().'/contenu-'.bin2hex(random_bytes(4));
        $this->copiesDeContenu[] = $this->temporaire;
        (new Filesystem())->mirror(__DIR__.'/fixtures/contenu-mini', $this->temporaire);
    }
}
