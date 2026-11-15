<?php

namespace App\Service\Migration;

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
        Analyze the following legacy PHP application and create an actionable migration plan to Symfony 7.4 and PHP 8.4.

        Static analysis results:
        {$analysisJson}

        Generate a structured Markdown report with these sections:

        1. Application Overview
        - Architecture, files, classes, and functions
        - Dependencies and code organization

        2. Legacy Code Assessment
        - Detected removed or deprecated PHP functions
        - Database API usage and detected SQL strings
        - Evidence-based PHP 8.4 compatibility risks

        3. Recommended Symfony Architecture
        - Controllers, services, and repositories
        - Doctrine ORM opportunities
        - Dependency injection and Twig templates

        4. Migration Roadmap
        - Prioritized implementation phases
        - Relevant source files for each phase
        - Expected deliverables and dependencies
        - Use the file-level priority and priorityScore provided by the static analysis.
        - Identify high-priority files and explain which detected indicators justify their priority.
        - Recommend a migration order considering both technical dependencies and review priority.

        5. Risks and Manual Review
        - Confirmed technical findings
        - Potential risks and missing information

        6. Testing Strategy
        - Unit, integration, and functional tests
        - Database migration validation

        Rules:
        - Use only information supported by the static analysis.
        - Reference actual file paths whenever possible.
        - Distinguish detected facts from assumptions.
        - Never invent features, database schemas, or database engines.
        - mysqli_* is supported in PHP 8.4 and is not a compatibility issue by itself.
        - Recommend Doctrine as an architectural improvement.
        - SQL strings do not prove that queries are executed.
        - Method names such as query, prepare, or exec do not necessarily indicate database operations.
        - Do not claim tests are missing unless verified.
        - Do not claim unsupported PHP features were checked.
        - Identify PHP compatibility issues only with evidence.
        - Review database structure and existing behavior before recommending implementation steps.
        - Provide concrete, file-specific recommendations.
        - Avoid generic advice and repetitive explanations.
        - Priority scores are heuristic review indicators, not confirmed complexity or security ratings.
        - Do not assume Low priority means a file is safe or easy to migrate.
        - Do not invent reasons for a file's priority.
        - Always display file priority and priorityScore separately, using the format "Priority: High (score: 20)".
        - Do not describe include paths as dynamic unless the analysis explicitly confirms this.
        - Clearly separate PHP 8.4 compatibility issues from Symfony architectural migration challenges.
        - Do not assume that higher review priority means earlier migration order.
        - Start the migration roadmap with dependency analysis and database schema review before implementing Symfony components.
        PROMPT;

        $messages = new MessageBag(
            Message::ofUser($prompt),
        );

        $result = $this->migrationAgent->call($messages);

        return $result->getContent();
    }
}
