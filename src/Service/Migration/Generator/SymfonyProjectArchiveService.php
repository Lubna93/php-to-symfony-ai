<?php

namespace App\Service\Migration\Generator;

final class SymfonyProjectArchiveService
{
    public function create(
        string $sourceDirectory,
        string $zipPath
    ): void {
        if (!is_dir($sourceDirectory)) {
            throw new \RuntimeException('Generated project directory not found.');
        }

        $zip = new \ZipArchive();

        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Cannot create ZIP archive.');
        }

        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(
                    $sourceDirectory,
                    \FilesystemIterator::SKIP_DOTS
                )
            );

            foreach ($iterator as $file) {
                if (!$file->isFile() || $file->isLink()) {
                    continue;
                }

                $relativePath = substr(
                    $file->getPathname(),
                    strlen(rtrim($sourceDirectory, '/\\')) + 1
                );

                $relativePath = str_replace('\\', '/', $relativePath);

                if (!$zip->addFile($file->getPathname(), $relativePath)) {
                    throw new \RuntimeException(
                        sprintf('Cannot add file to ZIP: %s', $relativePath)
                    );
                }
            }
        } finally {
            $zip->close();
        }
    }
}
