<?php

namespace App\Service\Migration\Prompt;

use App\Service\Migration\Prompt\Strategy\LaravelMigrationPrompt;
use App\Service\Migration\Prompt\Strategy\PlainPhpMigrationPrompt;
use App\Service\Migration\Prompt\Strategy\PromptStrategyInterface;
use App\Service\Migration\Prompt\Strategy\SymfonyImprovementPrompt;
use App\Service\Migration\Prompt\Strategy\ZendMigrationPrompt;

final class MigrationPromptBuilder
{
    public function __construct(
        private readonly SymfonyImprovementPrompt $symfonyPrompt,
        private readonly ZendMigrationPrompt $zendPrompt,
        private readonly LaravelMigrationPrompt $laravelPrompt,
        private readonly PlainPhpMigrationPrompt $plainPhpPrompt,
    ) {
    }

    private function getStrategy(string $source, string $target): PromptStrategyInterface
    {
        if (!in_array($target, ['Symfony 6.4', 'Symfony 7.4', 'Symfony 8.0'], true)) {
            throw new \InvalidArgumentException(
                sprintf('Unsupported target framework: %s', $target)
            );
        }
        return match ($source) {
            'Symfony', 'Symfony Legacy' => $this->symfonyPrompt,
            'Zend Framework', 'Zend Framework / Laminas' => $this->zendPrompt,
            'Laravel' => $this->laravelPrompt,
            'Plain PHP' => $this->plainPhpPrompt,
            default => throw new \InvalidArgumentException(
                sprintf('Unsupported source framework: %s', $source)
            ),
        };
    }

    public function buildPlanPrompt(
        array $analysis,
        string $source,
        string $target
    ): string {
        return $this->getStrategy($source, $target)->buildPlanPrompt(
            $analysis,
            $source,
            $target
        );
    }

    public function buildCodePrompt(
        string $sourceCode,
        string $filePath,
        array $analysis,
        array $dependencyContext,
        string $source,
        string $target
    ): string {

        return $this->getStrategy($source, $target)->buildCodePrompt(
            $sourceCode,
            $filePath,
            $analysis,
            $dependencyContext,
            $source,
            $target
        );
    }
}
