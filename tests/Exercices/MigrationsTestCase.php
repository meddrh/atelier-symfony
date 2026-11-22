<?php

namespace App\Tests\Exercices;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * Vérifie que les migrations du junior suffisent à construire la base que ses entités décrivent.
 *
 * Les autres tests d'exercice créent leur schéma directement depuis les entités (SchemaTool) : ils restent verts
 * même si la migration demandée n'a jamais été écrite, et l'application plante alors dans le navigateur. Ce test
 * regarde ce que les autres ne regardent pas :
 *
 *  1. il crée une base SQLite vide et jetable (un fichier temporaire, jamais la base de développement) ;
 *  2. il y joue les migrations du junior (`doctrine:migrations:migrate`), comme il le ferait sur une base neuve ;
 *  3. il demande à Doctrine ce qu'il faudrait encore changer pour que cette base corresponde aux entités
 *     (`doctrine:schema:update --dump-sql --complete`). La réponse doit être vide.
 *
 * Pourquoi `schema:update --dump-sql` plutôt que `schema:validate` : les deux comparent la même chose, mais
 * seul le premier donne le SQL manquant, que le message d'échec peut montrer au junior. `--complete` compte aussi
 * les tables que Doctrine ne connaît pas, pour qu'une table laissée par une migration en trop soit signalée.
 * Sous SQLite, la comparaison lit les commentaires de type (`DC2Type…`) des colonnes : une colonne ajoutée par
 * `ALTER TABLE … ADD COLUMN` les perd, et le test le voit (c'est la consigne « recrée la table » des sujets 06 et 07).
 *
 * Chaque dossier d'exercice concerné contient une classe vide `MigrationsTest` qui hérite de celle-ci.
 */
abstract class MigrationsTestCase extends TestCase
{
    private string $fichierBase = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->fichierBase = sys_get_temp_dir().'/formation-migrations-'.bin2hex(random_bytes(6)).'.sqlite';
    }

    protected function tearDown(): void
    {
        foreach (['', '-journal', '-wal', '-shm'] as $suffixe) {
            if (is_file($this->fichierBase.$suffixe)) {
                unlink($this->fichierBase.$suffixe);
            }
        }

        parent::tearDown();
    }

    public function testLesMigrationsConstruisentLaBaseDesEntites(): void
    {
        $migration = $this->console(['doctrine:migrations:migrate', '--no-interaction', '--allow-no-migration']);
        if (!$migration->isSuccessful()) {
            self::fail(
                "Tes migrations plantent quand on les joue depuis une base vide.\n"
                ."Relis ton fichier dans migrations/ : une requête SQL est fausse, ou suppose une table qui n'existe pas encore.\n"
                .'Détail de Doctrine : '.$this->extrait($migration)
            );
        }

        // Garde de la plateforme : si la base jetable n'a pas été utilisée, le processus a ouvert une autre base.
        self::assertGreaterThan(0, is_file($this->fichierBase) ? filesize($this->fichierBase) : 0, "Erreur de la plateforme : la base jetable n'a pas été utilisée.");

        $ecart = $this->console(['doctrine:schema:update', '--dump-sql', '--complete']);
        if (!$ecart->isSuccessful()) {
            self::fail("Doctrine n'a pas pu comparer la base à tes entités.\n".$this->extrait($ecart));
        }

        $sqlManquant = array_values(array_filter(
            array_map('trim', explode("\n", $ecart->getOutput())),
            static fn (string $ligne): bool => '' !== $ligne && str_ends_with($ligne, ';')
        ));

        self::assertSame(
            [],
            $sqlManquant,
            "Ta base, construite uniquement par tes migrations, ne correspond pas à tes entités : "
            ."l'application planterait dans le navigateur, même si les autres tests sont verts.\n"
            ."À faire : écris une nouvelle migration (php bin/console make:migration) ou corrige celle de ce sujet, "
            ."relis-la, puis relance ces tests. Ne modifie jamais une migration d'un sujet précédent.\n"
            ."Ce que Doctrine voudrait encore changer :\n  ".implode("\n  ", $sqlManquant)."\n"
            ."(Sous SQLite, une colonne ajoutée par ALTER TABLE perd son type : recrée alors la table dans la migration, "
            ."comme l'indique la consigne.)"
        );
    }

    /**
     * Lance une commande de la console du junior sur la base jetable. Le DATABASE_URL du processus remplace celui
     * des fichiers .env : la base de développement n'est jamais ouverte (le test le vérifie, voir plus bas).
     * Pas de `--no-debug` : en mode sans débogage, Doctrine ne relit pas les entités modifiées depuis le dernier
     * passage (cache de métadonnées), et une colonne ajoutée à une entité resterait invisible.
     *
     * @param list<string> $arguments
     */
    private function console(array $arguments): Process
    {
        $processus = new Process(
            [\PHP_BINARY, 'bin/console', '--env=test', '--no-ansi', ...$arguments],
            dirname(__DIR__, 2),
            [
                'DATABASE_URL' => 'sqlite:///'.$this->fichierBase,
                'APP_ENV' => 'test',
                // Sans cela, le processus fils croirait que DATABASE_URL vient d'un fichier .env (le processus père
                // l'a lu) et le remplacerait par la valeur de .env.test : il ouvrirait la base de test, pas la base jetable.
                'SYMFONY_DOTENV_VARS' => false,
            ],
            null,
            120
        );
        $processus->run();

        return $processus;
    }

    private function extrait(Process $processus): string
    {
        $texte = trim($processus->getErrorOutput()."\n".$processus->getOutput());
        $lignes = array_slice(explode("\n", $texte), 0, 15);

        return implode("\n", $lignes);
    }
}
