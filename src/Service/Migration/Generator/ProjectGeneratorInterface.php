<?php

namespace App\Service\Migration\Generator;

use App\Entity\MigrationProject;

interface ProjectGeneratorInterface
{
    public function supports(MigrationProject $project): bool;

    public function generate(MigrationProject $project): array;
}
