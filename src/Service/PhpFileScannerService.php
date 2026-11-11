<?php

namespace App\Service;

use Symfony\Component\Finder\Finder;

class PhpFileScannerService
{
    public function scan(string $directory): array
    {
        if (!is_dir($directory)) {
            throw new \RuntimeException('Project directory not found.');
        }

        $finder = new Finder();

        $finder
            ->files()
            ->in($directory)
            ->name('*.php')
            ->ignoreVCS(true)
            ->ignoreDotFiles(true)
            ->sortByName();

        $files = [];

        foreach ($finder as $file) {
            $files[] = [
                'path' => $file->getRelativePathname(),
                'size' => $file->getSize(),
                'lines' => count(file($file->getRealPath())),
            ];
        }

        return [
            'totalFiles' => count($files),
            'files' => $files,
        ];
    }
}
