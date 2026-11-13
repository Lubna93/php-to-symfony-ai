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
        $totalDatabaseCalls = 0;
        $totalLegacyCalls = 0;
        $totalSqlOperations = 0;

        foreach ($finder as $file) {
            $structure = $this->structureAnalyzer->analyze($file->getRealPath());

            $classCount = count($structure['classes']);
            $functionCount = count($structure['functions']);

            $totalClasses += $classCount;
            $totalFunctions += $functionCount;
            $totalDatabaseCalls += count($structure['databaseCalls']);
            $totalLegacyCalls += count($structure['legacyCalls']);
            $totalSqlOperations += count($structure['sqlOperations']);

            $databaseCallCount = count($structure['databaseCalls']);
            $legacyCallCount = count($structure['legacyCalls']);
            $sqlOperationCount = count($structure['sqlOperations']);

            $priorityScore = ($legacyCallCount * 5)
                + ($databaseCallCount * 2)
                + $sqlOperationCount;

            $priority = match (true) {
                $priorityScore >= 10 => 'High',
                $priorityScore >= 4 => 'Medium',
                default => 'Low',
            };

            $files[] = [
                'path' => $file->getRelativePathname(),
                'size' => $file->getSize(),
                'lines' => count(file($file->getRealPath())),
                'namespace' => $structure['namespace'],
                'classes' => $structure['classes'],
                'functions' => $structure['functions'],
                'dependencies' => $structure['dependencies'],
                'databaseCalls' => $structure['databaseCalls'],
                'legacyCalls' => $structure['legacyCalls'],
                'sqlOperations' => $structure['sqlOperations'],
                'priority' => $priority,
                'priorityScore' => $priorityScore,
            ];
        }

        return [
            'totalFiles' => count($files),
            'totalClasses' => $totalClasses,
            'totalFunctions' => $totalFunctions,
            'totalDatabaseCalls' => $totalDatabaseCalls,
            'totalLegacyCalls' => $totalLegacyCalls,
            'files' => $files,
            'totalSqlOperations' => $totalSqlOperations,
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
