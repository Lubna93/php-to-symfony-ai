<?php

namespace App\Service\Project;

use App\Entity\MigrationProject;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ProjectUploadService
{
    public function __construct(
        private readonly string $projectDir,
    ) {
    }

    public function upload(
        UploadedFile $file,
        MigrationProject $project
    ): string {
        if ($file->getSize() > 100 * 1024 * 1024) {
            throw new \InvalidArgumentException(
                'The ZIP file must not exceed 100 MB.'
            );
        }
        
        if ($file->getMimeType() !== 'application/zip') {
            throw new \InvalidArgumentException(
                'Only ZIP files are allowed.'
            );
        }
        // TODO: Manage uploaded ZIP files with VichUploaderBundle.
        // @see https://github.com/dustin10/VichUploaderBundle
        $directory = sprintf(
            '%s/var/storage/projects/%d/original',
            $this->projectDir,
            $project->getId()
        );

        $filename = bin2hex(random_bytes(16)) . '.zip';

        $file->move($directory, $filename);

        return $filename;
    }
}
