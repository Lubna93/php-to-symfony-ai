<?php

namespace App\Dto;

final class SymfonyBlueprintFile
{
    public string $path = '';

    public string $responsibility = '';

    /** @var list<string> */
    public array $sources = [];
}
