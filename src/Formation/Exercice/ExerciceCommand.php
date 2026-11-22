<?php

namespace App\Formation\Exercice;

use App\Formation\Contenu\Catalogue;
use App\Formation\Contenu\TypeBloc;
use App\Formation\Parcours\Parcours;
use App\Formation\Progression\Lancement;
use App\Formation\Progression\ProgressionStore;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * `bin/exercice test` : lance les tests de l'exercice en cours et enregistre le résultat.
 */
#[AsCommand(name: 'formation:exercice', description: 'Lance les tests de l\'exercice en cours et enregistre le résultat.')]
final class ExerciceCommand extends Command
{
    /** Message d'aide, partagé avec PointDEntree (code cassé). Argument : nombre de lancements rouges. */
    public const MESSAGE_AIDE = 'C\'est le %de essai sans succès : clique sur « Demander de l\'aide » dans la plateforme.';

    public function __construct(
        private readonly Parcours $parcours,
        private readonly Catalogue $catalogue,
        private readonly ProgressionStore $store,
        private readonly LanceurTests $lanceur,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('action', InputArgument::REQUIRED, 'Action à effectuer : « test » lance les tests de l\'exercice en cours.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $action = (string) $input->getArgument('action');
        if ('test' !== $action) {
            $output->writeln(sprintf('Action inconnue « %s ». Seule l\'action « test » existe : bin/exercice test.', OutputFormatter::escape($action)));

            return Command::INVALID;
        }

        $bloc = $this->parcours->blocCourant();
        if (null === $bloc) {
            $output->writeln('Tout le parcours disponible est terminé.');

            return Command::SUCCESS;
        }
        if (TypeBloc::Exercice !== $bloc->type || null === $bloc->dossierTests) {
            $output->writeln(sprintf('Pas d\'exercice en cours. Ta séance du jour : « %s ».', OutputFormatter::escape($bloc->titre)));

            return Command::FAILURE;
        }

        $resultat = $this->lanceur->lancer($bloc->dossierTests);
        $this->parcours->enregistrerResultat($bloc->id, new Lancement(
            $bloc->id,
            $resultat->vert,
            $resultat->nbTests,
            $resultat->nbReussis,
            $resultat->resume,
            new \DateTimeImmutable(),
            $resultat->echecs,
        ));

        if ($resultat->vert) {
            $badge = $this->catalogue->sujetDuBloc($bloc->id)?->badge;
            $output->writeln(sprintf(
                'Bravo, tous les tests passent (%d/%d). Badge débloqué : %s.',
                $resultat->nbReussis,
                $resultat->nbTests,
                OutputFormatter::escape($badge?->nom ?? ''),
            ));

            return Command::SUCCESS;
        }

        // Les lignes viennent de PHPUnit et peuvent contenir « < » : elles sont protégées du formateur de la console.
        $rouges = ListeDesRouges::depuis($resultat->echecs, $bloc->dossierTests);
        if (null === $rouges) {
            $output->writeln(sprintf('%d/%d tests passent. %s', $resultat->nbReussis, $resultat->nbTests, OutputFormatter::escape($resultat->resume)));
        } else {
            $output->writeln(sprintf('%d/%d tests passent. %s', $resultat->nbReussis, $resultat->nbTests, $rouges->titre()));
            foreach ($rouges->lignes as $ligne) {
                $output->writeln('  - '.OutputFormatter::escape($ligne));
            }
            if (null !== $rouges->suite()) {
                $output->writeln('  '.$rouges->suite());
            }
            $output->writeln(sprintf('%s %s', $rouges->libelleDuDetail(), OutputFormatter::escape($rouges->commandeDuDetail)));
        }
        if ($this->parcours->besoinDAide($bloc->id)) {
            $output->writeln(sprintf(self::MESSAGE_AIDE, $this->store->nombreDeRouges($bloc->id)));
        }

        return Command::FAILURE;
    }
}
