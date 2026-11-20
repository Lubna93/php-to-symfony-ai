<?php

namespace App\Service\Migration\Generator;

final class GeneratedFilesParserService
{
    public function parse(string $response): array
    {
        try {
            $data = json_decode(
                $response,
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (\JsonException $exception) {
            throw new \RuntimeException(
                'Invalid AI JSON response: ' . $exception->getMessage(),
                previous: $exception
            );
        }

        if (
            !is_array($data)
            || !isset($data['files'])
            || !is_array($data['files'])
        ) {
            throw new \RuntimeException(
                'Invalid generated files response.'
            );
        }

        $files = [];

        foreach ($data['files'] as $file) {
            if (is_array($file) && array_is_list($file)) {
                if (
                    count($file) !== 2 ||
                    !is_string($file[0]) ||
                    !is_string($file[1])
                ) {
                    throw new \RuntimeException(
                        'Invalid generated file structure.'
                    );
                }

                $file = [
                    'path' => $file[0],
                    'content' => $file[1],
                ];
            }

            if (
                !is_array($file)
                || !isset($file['path'], $file['content'])
                || !is_string($file['path'])
                || !is_string($file['content'])
            ) {
                throw new \RuntimeException(
                    'Invalid generated file.'
                );
            }

            $path = str_replace('\\', '/', $file['path']);

            if (
                str_starts_with($path, '/')
                || str_contains($path, '..')
                || str_contains($path, ':')
                || str_contains($path, "\0")
                || !preg_match(
                    '~^(src|templates)/[A-Za-z0-9_./-]+$~D',
                    $path
                )
            ) {
                throw new \RuntimeException(
                    'Invalid generated file path.'
                );
            }

            if (isset($files[$path])) {
                throw new \RuntimeException(
                    'Duplicate generated file path.'
                );
            }

            $files[$path] = $file['content'];
        }

        if ($files === []) {
            throw new \RuntimeException(
                'No generated files found.'
            );
        }

        return $files;
    }
}
