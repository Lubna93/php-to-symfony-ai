<?php

namespace App\Service\Migration\Generator\PlainPhp;

use App\Dto\GeneratedSymfonyProject;
use App\Dto\SymfonyArchitectureBlueprint;
use App\Entity\MigrationProject;
use App\Service\Migration\Generator\GeneratedFilesParserService;
use App\Service\Migration\Generator\ProjectGeneratorInterface;
use App\Service\Project\ProjectSourceFileService;
use App\Service\Security\SensitiveDataFilterService;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use App\Dto\SymfonyBlueprintFile;

final class PlainPhpProjectGenerator implements ProjectGeneratorInterface
{
    public function __construct(
        private readonly ProjectSourceFileService $sourceFileService,
        private readonly PlainPhpProjectPromptBuilder $promptBuilder,
        private readonly GeneratedFilesParserService $filesParser,
        private readonly SensitiveDataFilterService $sensitiveDataFilter,
        private readonly AgentInterface $migrationAgent,
    ) {
    }

    public function supports(MigrationProject $project): bool
    {
        return $project->getSourceFramework() === 'Plain PHP';
    }

    public function generate(MigrationProject $project): array
    {
        if (!$this->supports($project)) {
            throw new \InvalidArgumentException(
                'Full project generation is currently available for Plain PHP only.'
            );
        }

        $filePaths = $this->sourceFileService->getPhpFilePaths($project);

        if ($filePaths === []) {
            throw new \RuntimeException('No PHP files found in the project.');
        }

        $sources = [];

        foreach ($filePaths as $filePath) {
            if ($this->sensitiveDataFilter->isSensitiveFile($filePath)) {
                continue;
            }

            $file = $this->sourceFileService->getSelectedPhpFile(
                $project,
                $filePath
            );

            $sources[$filePath] = [
                'analysis' => $file['analysis'],
                'sourceCode' => $this->sensitiveDataFilter->redact(
                    $file['sourceCode']
                ),
            ];
        }

        if ($sources === []) {
            throw new \RuntimeException('No eligible PHP source files found.');
        }

        // Step 1: Generate or reuse the architecture blueprint.
        $projectDirectory = sprintf(
            '%s/var/storage/projects/%d',
            dirname(__DIR__, 5),
            $project->getId()
        );

        $blueprintPath = $projectDirectory . '/architecture-blueprint.json';

        if (is_file($blueprintPath)) {
            $blueprintData = json_decode(
                file_get_contents($blueprintPath),
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } else {
            $blueprintPrompt = $this->promptBuilder->buildBlueprint(
                $sources,
                $project->getTargetFramework()
            );

            $blueprintResponse = $this->migrationAgent->call(
                new MessageBag(Message::ofUser($blueprintPrompt)),
                ['response_format' => SymfonyArchitectureBlueprint::class]
            );

            $blueprint = $blueprintResponse->asObject(
                SymfonyArchitectureBlueprint::class
            );

            $blueprintData = [
                'files' => $blueprint->files,
                'warnings' => $blueprint->warnings,
            ];

            if (!is_dir($projectDirectory)) {
                mkdir($projectDirectory, 0775, true);
            }

            file_put_contents(
                $blueprintPath,
                json_encode(
                    $blueprintData,
                    JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR
                ),
                LOCK_EX
            );
        }

        $architecture = [];
        $seenPaths = [];

        foreach ($blueprintData['files'] as $plannedFile) {
            if ($plannedFile instanceof SymfonyBlueprintFile) {
                $plannedFile = [
                    'path' => $plannedFile->path,
                    'responsibility' => $plannedFile->responsibility,
                    'sources' => $plannedFile->sources,
                ];
            }

            if (is_array($plannedFile) && array_is_list($plannedFile)) {
                if (count($plannedFile) !== 3) {
                    throw new \RuntimeException('Invalid blueprint file structure.');
                }

                $plannedFile = [
                    'path' => $plannedFile[0],
                    'responsibility' => $plannedFile[1],
                    'sources' => $plannedFile[2],
                ];
            }

            if (
                !is_array($plannedFile) ||
                !isset(
                    $plannedFile['path'],
                    $plannedFile['responsibility'],
                    $plannedFile['sources']
                ) ||
                !is_string($plannedFile['path']) ||
                !is_string($plannedFile['responsibility']) ||
                !is_array($plannedFile['sources'])
            ) {
                throw new \RuntimeException('Invalid blueprint file metadata.');
            }

            $path = $plannedFile['path'];

            if (
                !preg_match('~^(src|templates)/[A-Za-z0-9_./-]+$~D', $path) ||
                str_contains($path, '..')
            ) {
                throw new \RuntimeException(
                    sprintf('Invalid blueprint file path: %s', $path)
                );
            }

            if (isset($seenPaths[$path])) {
                throw new \RuntimeException(
                    sprintf('Duplicate blueprint file: %s', $path)
                );
            }

            $seenPaths[$path] = true;
            $architecture[] = $plannedFile;
        }

        if ($architecture === []) {
            throw new \RuntimeException('The architecture blueprint is empty.');
        }

        // Step 2: Generate each planned Symfony file once.
        $generatedFiles = [];

        foreach ($architecture as $plannedFile) {
            $path = $plannedFile['path'];

            $relatedSources = [];

            foreach ($plannedFile['sources'] ?? [] as $sourcePath) {
                if (isset($sources[$sourcePath])) {
                    $relatedSources[$sourcePath] = $sources[$sourcePath];
                }
            }

            $prompt = $this->promptBuilder->buildFile(
                $path,
                $plannedFile['responsibility'] ?? '',
                $relatedSources,
                $architecture,
                $project->getTargetFramework()
            );

            $response = $this->migrationAgent->call(
                new MessageBag(Message::ofUser($prompt)),
                ['response_format' => GeneratedSymfonyProject::class]
            );

            $result = $response->asObject(GeneratedSymfonyProject::class);

            $json = json_encode($result, JSON_THROW_ON_ERROR);
            $files = $this->filesParser->parse($json);

            if (count($files) !== 1 || !array_key_exists($path, $files)) {
                throw new \RuntimeException(
                    sprintf('Unexpected generated output for: %s', $path)
                );
            }

            $generatedFiles[$path] = $files[$path];
        }

        return $generatedFiles;
    }
}
