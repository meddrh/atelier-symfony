<?php

namespace App\Tests\Formation\Exercice;

use App\Formation\Exercice\PointDEntree;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Le noyau de la plateforme ne charge rien du code du junior : s'il ne démarre pas, c'est la faute de la plateforme.
 * Le code du junior, lui, ne tourne que dans les tests PHPUnit lancés en sous-processus (voir LanceurTests et
 * IsolationDuJuniorTest).
 */
class PointDEntreeTest extends TestCase
{
    public function testUnNoyauQuiNeDemarrePasEstUnePanneDeLaPlateforme(): void
    {
        $sortie = new BufferedOutput();

        $code = (new PointDEntree(static fn (): Application => throw new \RuntimeException('Base illisible.')))->executer(['bin/exercice', 'test'], $sortie);

        self::assertSame(2, $code);
        self::assertStringContainsString(
            'La plateforme n\'a pas pu démarrer : Base illisible. Ce n\'est pas ton code : préviens Mohammed.',
            $sortie->fetch()
        );
    }

    public function testLeMessageDeLaPanneEstProtegeDuFormateurDeLaConsole(): void
    {
        $sortie = new BufferedOutput();

        (new PointDEntree(static fn (): Application => throw new \RuntimeException('classe <info> introuvable')))->executer(['bin/exercice', 'test'], $sortie);

        self::assertStringContainsString('classe <info> introuvable', $sortie->fetch());
    }

    public function testUnNoyauQuiDemarreExecuteLaCommande(): void
    {
        $sortie = new BufferedOutput();
        $application = new Application();
        $application->setAutoExit(false);
        $application->add((new Command('formation:exercice'))->addArgument('action')->setCode(static function ($entree, $sortie): int {
            $sortie->writeln('commande lancée '.$entree->getArgument('action'));

            return Command::SUCCESS;
        }));

        $code = (new PointDEntree(static fn (): Application => $application))->executer(['bin/exercice', 'test'], $sortie);

        self::assertSame(0, $code);
        self::assertStringContainsString('commande lancée test', $sortie->fetch());
    }
}
