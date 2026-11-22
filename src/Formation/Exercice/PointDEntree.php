<?php

namespace App\Formation\Exercice;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Point d'entrée de `bin/exercice`.
 *
 * Le noyau de la plateforme (FormationKernel) ne charge rien du code ni de la configuration du junior : s'il ne
 * démarre pas, la faute est celle de la plateforme, jamais du junior. L'échec est donc dit comme tel (code 2) et
 * n'enregistre aucun lancement. Les erreurs du code du junior se voient dans les tests PHPUnit que la commande lance
 * en sous-processus (LanceurTests), et y sont comptées rouges.
 */
final class PointDEntree
{
    /** @param \Closure(): Application $noyau démarre le noyau de la plateforme et rend la console */
    public function __construct(private readonly \Closure $noyau)
    {
    }

    /** @param list<string> $argv */
    public function executer(array $argv, OutputInterface $sortie): int
    {
        try {
            $application = ($this->noyau)();
        } catch (\Throwable $erreur) {
            $message = rtrim($erreur->getMessage(), '. ');
            // Le message peut contenir « < » : il est protégé du formateur de la console.
            $sortie->writeln(OutputFormatter::escape(sprintf('La plateforme n\'a pas pu démarrer : %s. Ce n\'est pas ton code : préviens Mohammed.', $message)));

            return Command::INVALID;
        }

        return $application->run(new ArgvInput(['bin/exercice', 'formation:exercice', ...\array_slice($argv, 1)]), $sortie);
    }
}
