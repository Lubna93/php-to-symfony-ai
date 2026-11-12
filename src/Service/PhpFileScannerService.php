<?php

namespace App\Service;

use Symfony\Component\Finder\Finder;
use App\Entity\MigrationProject;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class PhpFileScannerService
{
    public function __construct(
        private readonly PhpStructureAnalyzerService $structureAnalyzer,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    public function scan(string $directory): array
    {
        if (!is_dir($directory)) {
            throw new \RuntimeException('Project directory not found.');
        }

        $finder = new Finder();

        $finder
            ->files()
            ->in($directory)
            ->name('*.php')
            ->ignoreVCS(true)
            ->ignoreDotFiles(true)
            ->sortByName();

        $files = [];
        $totalClasses = 0;
        $totalFunctions = 0;

        foreach ($finder as $file) {
            $structure = $this->structureAnalyzer->analyze($file->getRealPath());

            $classCount = count($structure['classes']);
            $functionCount = count($structure['functions']);

            $totalClasses += $classCount;
            $totalFunctions += $functionCount;

            $files[] = [
                'path' => $file->getRelativePathname(),
                'size' => $file->getSize(),
                'lines' => count(file($file->getRealPath())),
                'namespace' => $structure['namespace'],
                'classes' => $structure['classes'],
                'functions' => $structure['functions'],
                'dependencies' => $structure['dependencies'],
            ];
        }

        return [
            'totalFiles' => count($files),
            'totalClasses' => $totalClasses,
            'totalFunctions' => $totalFunctions,
            'files' => $files,
        ];
    }

    public function scanProject(MigrationProject $project): array
    {
        $directory = sprintf(
            '%s/var/storage/projects/%d/extracted',
            $this->projectDir,
            $project->getId()
        );

        return $this->scan($directory);
    }
}
