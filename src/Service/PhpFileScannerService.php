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
        $constantsCache = [];

        foreach ($finder as $file) {
            $filePath = $file->getRealPath();
            $pathConstants = $this->getPathConstantsForFile($filePath, $directory, $constantsCache);
            $structure = $this->structureAnalyzer->analyze($filePath, $pathConstants);

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

            $resolvedDependencies = $this->resolveDependencies(
                $structure['dependencies'],
                $file->getRealPath(),
                $directory
            );

            $files[] = [
                'path' => $file->getRelativePathname(),
                'size' => $file->getSize(),
                'lines' => count(file($file->getRealPath())),
                'namespace' => $structure['namespace'],
                'classes' => $structure['classes'],
                'functions' => $structure['functions'],
                'dependencies' => $structure['dependencies'],
                'resolvedDependencies' => $resolvedDependencies,
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

    private function resolveDependencies(array $dependencies, string $filePath, string $rootDirectory): array
    {
        $resolved = [];

        $rootPath = realpath($rootDirectory);

        foreach ($dependencies['includes'] as $include) {
            $path = $include['path'];

            if ($include['dynamic'] ?? false) {
                continue;
            }

            $candidate = str_starts_with($path, '/')
                || preg_match('/^[A-Za-z]:[\/\\\\]/', $path)
                    ? $path
                    : dirname($filePath) . DIRECTORY_SEPARATOR . $path;

            $absolutePath = realpath($candidate);

            if ($absolutePath === false || $rootPath === false) {
                continue;
            }

            $relativePath = substr($absolutePath, strlen($rootPath) + 1);

            if (!str_starts_with($absolutePath, $rootPath . DIRECTORY_SEPARATOR)) {
                continue;
            }

            if (!is_file($absolutePath)) {
                continue;
            }

            $resolved[] = [
                'type' => $include['type'],
                'path' => str_replace('\\', '/', $relativePath),
            ];
        }

        return $resolved;
    }

    private function getPathConstantsForFile(
        string $filePath,
        string $rootDirectory,
        array &$cache
    ): array {
        $root = realpath($rootDirectory);
        $current = realpath(dirname($filePath));

        if ($root === false || $current === false) {
            return [];
        }

        if (
            $current !== $root
            && !str_starts_with($current, $root . DIRECTORY_SEPARATOR)
        ) {
            return [];
        }

        while (true) {
            if (is_file($current . DIRECTORY_SEPARATOR . 'config.php')) {
                if (!array_key_exists($current, $cache)) {
                    $cache[$current] = $this->structureAnalyzer
                        ->extractProjectPathConstants($current);
                }

                return $cache[$current];
            }

            if ($current === $root) {
                break;
            }

            $parent = dirname($current);

            if ($parent === $current) {
                break;
            }

            $current = $parent;
        }

        return [];
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
