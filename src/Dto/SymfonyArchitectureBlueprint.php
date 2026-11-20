<?php

namespace App\Dto;

final class SymfonyArchitectureBlueprint
{
    /**
     * @var list<array{path: string, responsibility: string, sources: list<string>}>
     */
    public array $files = [];

    /** @var list<string> */
    public array $warnings = [];
}
