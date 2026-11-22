<?php

namespace App\Formation\Export;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'formation:exporter', description: 'Écrit un site HTML statique de lecture (parcours, démonstrations, exercices, rappels, programme).')]
final class ExporterCommand extends Command
{
    public function __construct(private readonly SiteExporteur $exporteur)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setHelp("Les pages et atelier/ sont écrits dans le dossier donné (créé au besoin) ; les fichiers du même nom sont remplacés, rien d'autre n'est supprimé : donne un dossier neuf.");
        $this->addArgument('dossier', InputArgument::REQUIRED, 'Dossier où écrire le site');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dossier = (string) $input->getArgument('dossier');

        try {
            $nombre = $this->exporteur->exporter($dossier);
        } catch (\Throwable $e) {
            $io->error(sprintf('Export impossible : %s', $e->getMessage()));

            return Command::FAILURE;
        }

        $io->success(sprintf('%d pages écrites dans %s.', $nombre, $dossier));

        return Command::SUCCESS;
    }
}
