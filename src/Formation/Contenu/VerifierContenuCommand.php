<?php

namespace App\Formation\Contenu;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'formation:contenu:verifier', description: 'Vérifie que le contenu pédagogique est cohérent.')]
final class VerifierContenuCommand extends Command
{
    public function __construct(private readonly ChargeurCatalogue $chargeur)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $catalogue = $this->chargeur->charger();
        } catch (ContenuInvalide $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $rattachees = [];
        $demandees = [];
        $montrees = [];
        foreach ($catalogue->blocsOrdonnes() as $bloc) {
            foreach ($bloc->programme as $idLigne) {
                $rattachees[$idLigne] = true;
                if (TypeBloc::Exercice === $bloc->type) {
                    $demandees[$idLigne] = true;
                } elseif (TypeBloc::Demonstration === $bloc->type || TypeBloc::Rappel === $bloc->type) {
                    $montrees[$idLigne] = true;
                }
            }
        }
        $lignes = $catalogue->programme();
        $orphelines = array_values(array_filter($lignes, static fn (LigneProgramme $l) => !isset($rattachees[$l->id])));

        $io->writeln(sprintf(
            'Contenu valide : %d blocs, %d lignes de programme rattachées sur %d.',
            \count($catalogue->blocsOrdonnes()),
            \count($lignes) - \count($orphelines),
            \count($lignes),
        ));

        if ([] !== $orphelines) {
            $io->writeln('Lignes de programme non rattachées :');
            foreach ($orphelines as $ligne) {
                $io->writeln(sprintf('  - %s : %s', $ligne->id, $ligne->libelle));
            }
        }

        // Rattachée ne veut pas dire montrée : une ligne rattachée au seul exercice est demandée sans avoir été enseignée.
        $demandeesNonMontrees = array_values(array_filter($lignes, static fn (LigneProgramme $l) => isset($demandees[$l->id]) && !isset($montrees[$l->id])));
        $io->writeln(sprintf('Lignes demandées par un exercice : %d.', \count($demandees)));
        $io->writeln(sprintf(
            'Lignes demandées et montrées (démonstration ou rappel) : %d sur %d.',
            \count($demandees) - \count($demandeesNonMontrees),
            \count($demandees),
        ));
        if ([] !== $demandeesNonMontrees) {
            $io->writeln('Lignes demandées jamais montrées :');
            foreach ($demandeesNonMontrees as $ligne) {
                $io->writeln(sprintf('  - %s : %s', $ligne->id, $ligne->libelle));
            }
        }

        return Command::SUCCESS;
    }
}
