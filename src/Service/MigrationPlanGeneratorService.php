<?php

namespace App\Service;

use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

final class MigrationPlanGeneratorService
{
    public function __construct(
        private readonly AgentInterface $migrationAgent,
    ) {
    }

    public function generate(array $analysis): string
    {
        $analysisJson = json_encode(
            $analysis,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );

        $prompt = <<<PROMPT
        Analyze the following legacy PHP application.

        Target framework: Symfony 7.4
        Minimum PHP version: 8.2

        Static analysis results:
        {$analysisJson}

        Generate a migration plan covering:
        1. Existing architecture
        2. Detected dependencies
        3. Recommended Symfony architecture
        4. Migration steps in priority order
        5. Risks and compatibility issues
        6. Testing strategy

        Base your recommendations on the provided analysis.
        Clearly distinguish detected facts from assumptions.
        Do not invent application features.
        PROMPT;

        $messages = new MessageBag(
            Message::ofUser($prompt),
        );

        $result = $this->migrationAgent->call($messages);

        return $result->getContent();
    }
}
