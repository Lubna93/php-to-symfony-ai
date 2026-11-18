<?php

namespace App\Command;

use App\Repository\MigrationProjectRepository;
use App\Service\Migration\Generator\ProjectGenerationManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:migration:generate-project',
    description: 'Test Symfony project generation for Plain PHP.'
)]
final class GenerateSymfonyProjectCommand extends Command
{
    public function __construct(
        private readonly MigrationProjectRepository $projectRepository,
        private readonly ProjectGenerationManager $generator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument(
            'projectId',
            InputArgument::REQUIRED,
            'Migration project ID'
        );
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ): int {
        $io = new SymfonyStyle($input, $output);

        $projectId = filter_var(
            $input->getArgument('projectId'),
            FILTER_VALIDATE_INT
        );

        if ($projectId === false || $projectId <= 0) {
            $io->error('Invalid project ID.');

            return Command::FAILURE;
        }

        $project = $this->projectRepository->find($projectId);

        if ($project === null) {
            $io->error('Migration project not found.');

            return Command::FAILURE;
        }

        try {
            $files = $this->generator->generate($project);

            $io->success(sprintf(
                '%d Symfony file(s) generated.',
                count($files)
            ));

            $io->listing(array_keys($files));

            foreach ($files as $path => $content) {
                $io->section($path);
                $io->writeln($content);
            }

            return Command::SUCCESS;
        } catch (\Throwable $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }
    }
}
