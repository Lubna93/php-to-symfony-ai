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


        $uses = $finder->findInstanceOf(
            $nodes,
            Node\Stmt\Use_::class
        );

        $includes = $finder->find($nodes, static function (Node $node): bool {
            return $node instanceof Node\Expr\Include_;
        });

        $dependencies = [
            'uses' => [],
            'includes' => [],
            'extends' => [],
            'implements' => [],
        ];

        foreach ($uses as $use) {
            foreach ($use->uses as $item) {
                $dependencies['uses'][] = $item->name->toString();
            }
        }

        foreach ($includes as $include) {
            $dependencies['includes'][] = [
                'type' => match ($include->type) {
                    Node\Expr\Include_::TYPE_REQUIRE => 'require',
                    Node\Expr\Include_::TYPE_REQUIRE_ONCE => 'require_once',
                    Node\Expr\Include_::TYPE_INCLUDE => 'include',
                    Node\Expr\Include_::TYPE_INCLUDE_ONCE => 'include_once',
                },
                'path' => $include->expr instanceof Node\Scalar\String_
                    ? $include->expr->value
                    : '[dynamic]',
            ];
        }

        foreach ($classes as $class) {
            if ($class instanceof Node\Stmt\Class_ && $class->extends) {
                $dependencies['extends'][] = $class->extends->toString();
            }

            if ($class instanceof Node\Stmt\Class_ || $class instanceof Node\Stmt\Interface_) {
                foreach ($class->implements ?? [] as $interface) {
                    $dependencies['implements'][] = $interface->toString();
                }
            }
        }

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
            'dependencies' => $dependencies,
        ];
    }
}
