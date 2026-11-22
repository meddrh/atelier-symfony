<?php

namespace App\Tests\Formation;

use Symfony\Component\Filesystem\Filesystem;

/**
 * Pour les tests lents qui exécutent un script du projet (bin/exercice, outils/verifier-corriges.sh) sur une
 * copie complète : le noyau Symfony y démarre, et le script peut y écrire sans toucher au vrai projet.
 *
 * La copie est sous var/ du projet (même disque que vendor/, recopié en liens physiques), hors .git, .claude,
 * var/ et outils/corriges ; elle est supprimée en fin de test (hook après-test, même si le test échoue) ; une garde de fin de classe échoue si une copie subsiste.
 */
trait AvecCopieDuProjet
{
    /** @var list<string> */
    private array $copiesDuProjet = [];

    /** @var list<string> toutes les copies créées par la classe de test (une valeur par classe qui utilise le trait) */
    private static array $copiesDeLaClasse = [];

    /**
     * Garde : à la fin de la classe, aucune copie créée par elle ne subsiste. Échoue si une copie fuit.
     *
     * @afterClass
     */
    public static function assertAucuneCopieNeFuit(): void
    {
        $fuites = array_values(array_filter(self::$copiesDeLaClasse, 'file_exists'));
        (new Filesystem())->remove($fuites);
        self::$copiesDeLaClasse = [];
        self::assertSame([], $fuites, 'copies du projet laissées sous var/ par '.static::class);
    }

    /**
     * Hook d après-test (et non tearDown) : une classe qui définit son propre tearDown ne remplace pas ce nettoyage,
     * qui s'exécute aussi quand le test échoue.
     *
     * @after
     */
    protected function supprimerLesCopiesDuProjet(): void
    {
        (new Filesystem())->remove($this->copiesDuProjet);
        $this->copiesDuProjet = [];
    }

    /** @return string le dossier de la copie */
    private function copierLeProjetComplet(): string
    {
        $racine = (string) realpath(\dirname(__DIR__, 2));
        $copie = $racine.'/var/tests-lents-'.bin2hex(random_bytes(4));
        $this->copiesDuProjet[] = $copie;
        self::$copiesDeLaClasse[] = $copie;
        (new Filesystem())->mkdir($copie.'/var');
        exec(sprintf(
            'tar -C %s --exclude=./var --exclude=./vendor --exclude=./.git --exclude=./.claude --exclude=./outils/corriges -cf - . | tar -C %s -xf - && %s',
            escapeshellarg($racine),
            escapeshellarg($copie),
            $this->commandeCopieDeVendor($racine, $copie),
        ), $sortie, $code);
        self::assertSame(0, $code, 'copie du projet : '.implode("\n", $sortie));

        return $copie;
    }

    /**
     * Copie de vendor/ : en liens physiques (instantanée, sans duplication), sinon en copie ordinaire. Les liens
     * physiques échouent entre deux disques ou sur certains dossiers montés (Docker Desktop).
     */
    private function commandeCopieDeVendor(string $racine, string $copie): string
    {
        $source = escapeshellarg($racine.'/vendor');
        $destination = escapeshellarg($copie.'/vendor');

        return sprintf('(cp -al %1$s %2$s 2>/dev/null || { rm -rf %2$s && cp -a %1$s %2$s; })', $source, $destination);
    }
}
