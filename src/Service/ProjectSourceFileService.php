<?php

namespace App\Service;

use App\Entity\MigrationProject;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ProjectSourceFileService
{
    public function __construct(
        private readonly PhpFileScannerService $scanner,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
        private readonly SensitiveDataFilterService $sensitiveDataFilter,
    ) {
    }

    public function getSelectedPhpFile(
        MigrationProject $project,
        mixed $requestedPath
    ): array {
        if (!is_string($requestedPath) || $requestedPath === '') {
            throw new BadRequestHttpException('No PHP file selected.');
        }

        $directory = sprintf(
            '%s/var/storage/projects/%d/extracted',
            $this->projectDir,
            $project->getId()
        );

        $rootPath = realpath($directory);

        if ($rootPath === false) {
            throw new NotFoundHttpException('Project directory not found.');
        }

        $analysis = $this->scanner->scan($rootPath);
        $selectedFile = null;

        foreach ($analysis['files'] as $file) {
            if ($file['path'] === $requestedPath) {
                $selectedFile = $file;
                break;
            }
        }

        if ($selectedFile === null) {
            throw new NotFoundHttpException('PHP file not found in project analysis.');
        }

        $filePath = realpath(
            $rootPath . DIRECTORY_SEPARATOR . $selectedFile['path']
        );

        if (
            $filePath === false
            || !str_starts_with($filePath, $rootPath . DIRECTORY_SEPARATOR)
            || !is_file($filePath)
        ) {
            throw new BadRequestHttpException('Invalid PHP file path.');
        }

        $sourceCode = file_get_contents($filePath);

        $dependencyContext = $this->getDependencyContext(
            $selectedFile['resolvedDependencies'] ?? [],
            $rootPath,
            $selectedFile['path']
        );

        if ($sourceCode === false) {
            throw new \RuntimeException('Unable to read PHP file.');
        }

        return [
            'path' => $selectedFile['path'],
            'sourceCode' => $sourceCode,
            'analysis' => $selectedFile,
            'dependencyContext' => $dependencyContext,
        ];
    }

    private function getDependencyContext(
        array $dependencies,
        string $rootPath,
        string $selectedPath
    ): array {
        $context = [];
        $totalBytes = 0;
        $maxFiles = 5;
        $maxFileBytes = 20_000;
        $maxTotalBytes = 60_000;

        foreach ($dependencies as $dependency) {
            if (count($context) >= $maxFiles) {
                break;
            }

            $relativePath = $dependency['path'] ?? null;

            if (
                !is_string($relativePath)
                || $relativePath === $selectedPath
                || $this->sensitiveDataFilter->isSensitiveFile($relativePath)
            ) {
                continue;
            }

            $absolutePath = realpath(
                $rootPath . DIRECTORY_SEPARATOR . $relativePath
            );

            if (
                $absolutePath === false
                || !str_starts_with($absolutePath, $rootPath . DIRECTORY_SEPARATOR)
                || !is_file($absolutePath)
                || strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION)) !== 'php'
            ) {
                continue;
            }

            $size = filesize($absolutePath);

            if (
                $size === false
                || $size > $maxFileBytes
                || $totalBytes + $size > $maxTotalBytes
            ) {
                continue;
            }

            $content = file_get_contents($absolutePath);

            if ($content === false) {
                continue;
            }

            $context[] = [
                'path' => $relativePath,
                'type' => $dependency['type'],
                'sourceCode' => $content,
            ];

            $totalBytes += $size;
        }

        return $context;
    }

}
