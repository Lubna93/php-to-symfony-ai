<?php

namespace App\Service\Migration;

use App\Service\Migration\Prompt\MigrationPromptBuilder;
use App\Service\Security\SensitiveDataFilterService;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

final class PhpCodeMigrationService
{
    public function __construct(
        private readonly AgentInterface $migrationAgent,
        private readonly SensitiveDataFilterService $sensitiveDataFilter,
        private readonly MigrationPromptBuilder $promptBuilder,
    ) {
    }

    public function migrate(
        string $sourceCode,
        string $filePath,
        array $analysis,
        array $dependencyContext,
        string $sourceFramework,
        string $targetFramework
    ): string {
        if ($this->sensitiveDataFilter->isSensitiveFile($filePath)) {
            throw new \RuntimeException(
                'This file may contain sensitive configuration and cannot be sent to AI.'
            );
        }

        $sourceCode = $this->sensitiveDataFilter->redact($sourceCode);

        $dependencyContext = array_values(array_filter(
            $dependencyContext,
            fn (array $dependency): bool =>
                isset($dependency['path'])
                && !$this->sensitiveDataFilter->isSensitiveFile($dependency['path'])
        ));

        foreach ($dependencyContext as &$dependency) {
            $dependency['sourceCode'] = $this->sensitiveDataFilter->redact(
                $dependency['sourceCode'] ?? ''
            );
        }

        unset($dependency);

        if (isset($analysis['resolvedDependencies'])) {
            $analysis['resolvedDependencies'] = array_values(array_filter(
                $analysis['resolvedDependencies'],
                fn (array $dependency): bool =>
                    isset($dependency['path'])
                    && !$this->sensitiveDataFilter->isSensitiveFile($dependency['path'])
            ));
        }

        $prompt = $this->promptBuilder->buildCodePrompt(
            $sourceCode,
            $filePath,
            $analysis,
            $dependencyContext,
            $sourceFramework,
            $targetFramework
        );

        $prompt = $this->sensitiveDataFilter->redact($prompt);

        $messages = new MessageBag(
            Message::ofUser($prompt),
        );

        $result = $this->migrationAgent->call($messages);

        return $result->getContent();
    }
}
