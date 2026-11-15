<?php

namespace App\Service\Project;

use App\Entity\MigrationProject;
use Symfony\Component\Filesystem\Filesystem;

class ProjectExtractionService
{
    private const MAX_FILES = 2000;
    private const MAX_SIZE = 100 * 1024 * 1024;

    public function __construct(
        private readonly string $projectDir,
    ) {
    }

    public function extract(MigrationProject $project): string
    {
        if (!$project->getArchiveFilename() || !$project->getId()) {
            throw new \RuntimeException('Project archive is missing.');
        }

        $basePath = sprintf(
            '%s/var/storage/projects/%d',
            $this->projectDir,
            $project->getId()
        );

        $archivePath = $basePath . '/original/' . $project->getArchiveFilename();
        $extractPath = $basePath . '/extracted';

        $zip = new \ZipArchive();

        if ($zip->open($archivePath) !== true) {
            throw new \RuntimeException('Unable to open ZIP archive.');
        }

        $filesystem = new Filesystem();
        $tempPath = $basePath . '/extracted-' . bin2hex(random_bytes(8));

        try {
            if ($zip->numFiles > self::MAX_FILES) {
                throw new \RuntimeException('ZIP contains too many files.');
            }

            $filesystem->mkdir($tempPath);

            $totalSize = 0;

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entry = $zip->statIndex($i);

                if ($entry === false) {
                    throw new \RuntimeException('Invalid ZIP entry.');
                }

                $name = $entry['name'];

                if (
                    str_contains($name, '\\') ||
                    str_contains($name, "\0") ||
                    str_starts_with($name, '/') ||
                    preg_match('/^[a-zA-Z]:/', $name) ||
                    in_array('..', explode('/', $name), true)
                ) {
                    throw new \RuntimeException('Unsafe ZIP path detected.');
                }

                $isDirectory = str_ends_with($name, '/');

                $attributes = $zip->getExternalAttributesIndex($i, $opsys, $attr);

                if ($attributes && $opsys === \ZipArchive::OPSYS_UNIX) {
                    $fileType = ($attr >> 16) & 0170000;

                    if ($fileType === 0120000) {
                        throw new \RuntimeException('Symbolic links are not allowed.');
                    }
                }

                $destination = $tempPath . '/' . $name;

                if ($isDirectory) {
                    $filesystem->mkdir($destination);
                    continue;
                }

                $filesystem->mkdir(dirname($destination));

                $source = $zip->getStream($name);
                $target = fopen($destination, 'wb');

                if ($source === false || $target === false) {
                    throw new \RuntimeException('Unable to extract ZIP entry.');
                }

                try {
                    while (!feof($source)) {
                        $chunk = fread($source, 8192);

                        if ($chunk === false) {
                            throw new \RuntimeException('ZIP read failed.');
                        }

                        $totalSize += strlen($chunk);

                        if ($totalSize > self::MAX_SIZE) {
                            throw new \RuntimeException('ZIP exceeds extraction size limit.');
                        }

                        if (fwrite($target, $chunk) !== strlen($chunk)) {
                            throw new \RuntimeException('ZIP write failed.');
                        }
                    }
                } finally {
                    fclose($source);
                    fclose($target);
                }
            }

            if ($filesystem->exists($extractPath)) {
                $filesystem->remove($extractPath);
            }

            $filesystem->rename($tempPath, $extractPath);

            return $extractPath;
        } finally {
            $zip->close();

            if ($filesystem->exists($tempPath)) {
                $filesystem->remove($tempPath);
            }
        }
    }
}
