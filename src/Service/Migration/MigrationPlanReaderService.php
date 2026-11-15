<?php

namespace App\Service\Migration;

use App\Entity\MigrationProject;
use League\CommonMark\CommonMarkConverter;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class MigrationPlanReaderService
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    public function read(MigrationProject $project): ?string
    {
        $path = sprintf(
            '%s/var/storage/projects/%d/migration-plan.md',
            $this->projectDir,
            $project->getId()
        );

        return is_file($path) ? file_get_contents($path) : null;
    }

    public function renderHtml(string $markdown): string
    {
        $converter = new CommonMarkConverter([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);

        return $converter->convert($markdown)->getContent();
    }
}
