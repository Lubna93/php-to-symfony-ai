<?php

namespace App\Service\Analysis;

class FrameworkDetectorService
{
    public function detect(string $directory): array
    {
        if (!is_dir($directory)) {
            throw new \RuntimeException('Project directory not found.');
        }

        $root = $this->findProjectRoot($directory);
        $composer = $this->readComposer($root);
        $packages = array_merge(
            $composer['require'] ?? [],
            $composer['require-dev'] ?? []
        );

        $framework = 'Plain PHP';
        $version = null;
        $evidence = [];

        if (isset($packages['symfony/framework-bundle'])) {
            $framework = 'Symfony';
            $version = $packages['symfony/framework-bundle'];
            $evidence[] = 'symfony/framework-bundle';
        } elseif (isset($packages['laravel/framework'])) {
            $framework = 'Laravel';
            $version = $packages['laravel/framework'];
            $evidence[] = 'laravel/framework';
        } elseif (
            isset($packages['zendframework/zendframework'])
            || isset($packages['zendframework/zend-mvc'])
            || isset($packages['laminas/laminas-mvc'])
        ) {
            $framework = 'Zend Framework / Laminas';
            $version = $packages['zendframework/zendframework']
                ?? $packages['zendframework/zend-mvc']
                ?? $packages['laminas/laminas-mvc'];
            $evidence[] = 'Zend/Laminas Composer dependency';
        } elseif (is_file($root . '/bin/console')
            && is_dir($root . '/src/Controller')) {
            $framework = 'Symfony';
            $evidence[] = 'bin/console and src/Controller';
        } elseif (is_file($root . '/artisan')
            && is_dir($root . '/app')) {
            $framework = 'Laravel';
            $evidence[] = 'artisan and app directory';
        } elseif (is_dir($root . '/application/controllers')
            && is_dir($root . '/application/models')) {
            $framework = 'Zend Framework';
            $evidence[] = 'application/controllers and application/models';
        }

        return [
            'framework' => $framework,
            'version' => $version,
            'root' => $root,
            'evidence' => $evidence,
        ];
    }

    private function findProjectRoot(string $directory): string
    {
        $entries = array_values(array_filter(
            scandir($directory) ?: [],
            static fn (string $entry): bool =>
                !in_array($entry, ['.', '..', '__MACOSX'], true)
        ));

        if (count($entries) === 1) {
            $candidate = $directory . '/' . $entries[0];

            if (is_dir($candidate)) {
                return $candidate;
            }
        }

        return $directory;
    }

    private function readComposer(string $directory): array
    {
        $path = $directory . '/composer.json';

        if (!is_file($path)) {
            return [];
        }

        $composer = json_decode(
            file_get_contents($path),
            true
        );

        return is_array($composer) ? $composer : [];
    }
}
