<?php

namespace App\Service\Migration\Prompt\Strategy;

interface PromptStrategyInterface
{
    public function buildPlanPrompt(
        array $analysis,
        string $source,
        string $target
    ): string;

    public function buildCodePrompt(
        string $sourceCode,
        string $filePath,
        array $analysis,
        array $dependencyContext,
        string $source,
        string $target
    ): string;
}
