<?php

namespace App\Service;

use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;

class PhpStructureAnalyzerService
{
    public function analyze(string $filePath): array
    {
        $code = file_get_contents($filePath);

        if ($code === false) {
            throw new \RuntimeException('Unable to read PHP file.');
        }

        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $nodes = $parser->parse($code) ?? [];

        $finder = new NodeFinder();

        $namespaces = $finder->findInstanceOf(
            $nodes,
            Node\Stmt\Namespace_::class
        );

        $classes = $finder->find($nodes, static function (Node $node): bool {
            return $node instanceof Node\Stmt\Class_
                && !$node->isAnonymous()
                || $node instanceof Node\Stmt\Interface_
                || $node instanceof Node\Stmt\Trait_;
        });

        $functions = $finder->find($nodes, static function (Node $node): bool {
            return $node instanceof Node\Stmt\Function_
                || $node instanceof Node\Stmt\ClassMethod;
        });

        return [
            'namespace' => isset($namespaces[0])
                ? $namespaces[0]->name?->toString()
                : null,
            'classes' => array_map(
                static fn (Node $node): array => [
                    'name' => $node->name->toString(),
                    'type' => $node->getType(),
                ],
                $classes
            ),
            'functions' => array_map(
                static fn (Node $node): string => $node->name->toString(),
                $functions
            ),
        ];
    }
}
