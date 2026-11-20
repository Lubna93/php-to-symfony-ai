<?php

namespace App\Service\Migration\Generator;

final class GeneratedProjectWriterService
{
    public function write(
        array $files,
        string $outputDirectory
    ): void {
        if (!is_dir($outputDirectory)) {
            if (!mkdir($outputDirectory, 0775, true) && !is_dir($outputDirectory)) {
                throw new \RuntimeException('Cannot create output directory.');
            }
        }

        foreach ($files as $path => $content) {
            if (
                !is_string($path)
                || !is_string($content)
                || !preg_match('~^(src|templates)/[A-Za-z0-9_./-]+$~D', $path)
                || str_contains($path, '..')
            ) {
                throw new \RuntimeException('Invalid generated file path or content.');
            }

            $destination = $outputDirectory . '/' . $path;
            $directory = dirname($destination);

            if (!is_dir($directory)) {
                if (!mkdir($directory, 0775, true) && !is_dir($directory)) {
                    throw new \RuntimeException('Cannot create generated file directory.');
                }
            }

            if (file_put_contents($destination, $content) === false) {
                throw new \RuntimeException(
                    sprintf('Cannot write generated file: %s', $path)
                );
            }
        }
    }
}
