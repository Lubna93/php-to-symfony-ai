<?php

namespace App\Service\Migration\Generator;

use App\Entity\MigrationProject;
use App\Service\Migration\Generator\PlainPhp\PlainPhpProjectGenerator;

final class ProjectGenerationManager
{
    public function __construct(
        private readonly PlainPhpProjectGenerator $plainPhpGenerator,
        private readonly GeneratedProjectWriterService $writer,
        private readonly SymfonyProjectArchiveService $archive,
    ) {
    }

    public function generate(MigrationProject $project): array
    {
        if ($this->plainPhpGenerator->supports($project)) {
            return $this->plainPhpGenerator->generate($project);
        }

        throw new \InvalidArgumentException(sprintf(
            'Project generation is not supported for "%s" yet.',
            $project->getSourceFramework()
        ));
    }

  
    public function generateArchive(
        MigrationProject $project,
        string $outputDirectory,
        bool $regenerate = false
    ): string {
        $generatedDirectory = $outputDirectory . '/generated';
        $zipPath = $outputDirectory . '/symfony-generated.zip';

        if ($regenerate) {
            $filesystem = new \Symfony\Component\Filesystem\Filesystem();

            $filesystem->remove([
                $generatedDirectory,
                $zipPath,
                $outputDirectory . '/architecture-blueprint.json',
            ]);
        } elseif (file_exists($generatedDirectory) || file_exists($zipPath)) {
            throw new \RuntimeException(
                'Generated output already exists.'
            );
        }

        $files = $this->generate($project);

        if ($files === []) {
            throw new \RuntimeException('No files were generated.');
        }

        $this->writer->write($files, $generatedDirectory);
        $this->archive->create($generatedDirectory, $zipPath);

        return $zipPath;
    }

}
