<?php

namespace App\Service\Migration;

use App\Service\Migration\Prompt\MigrationPromptBuilder;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

final class MigrationPlanGeneratorService
{
    public function __construct(
        private readonly AgentInterface $migrationAgent,
        private readonly MigrationPromptBuilder $promptBuilder,
    ) {
    }

    public function generate(
        array $analysis,
        string $sourceFramework,
        string $targetFramework
    ): string {
        $prompt = $this->promptBuilder->buildPlanPrompt(
            $analysis,
            $sourceFramework,
            $targetFramework
        );

        $messages = new MessageBag(
            Message::ofUser($prompt),
        );

        return $this->migrationAgent
            ->call($messages)
            ->getContent();
    }
}
