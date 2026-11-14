<?php

namespace App\Service;

use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use PhpParser\Node\Expr\BinaryOp\Concat;

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
            $path = $this->resolveIncludePath($include->expr, $filePath);

            $dependencies['includes'][] = [
                'type' => match ($include->type) {
                    Node\Expr\Include_::TYPE_REQUIRE => 'require',
                    Node\Expr\Include_::TYPE_REQUIRE_ONCE => 'require_once',
                    Node\Expr\Include_::TYPE_INCLUDE => 'include',
                    Node\Expr\Include_::TYPE_INCLUDE_ONCE => 'include_once',
                },
                'path' => $path ?? '[dynamic]',
                'dynamic' => $path === null,
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

        $legacyFunctions = [
            'mysql_query',
            'mysql_connect',
            'mysql_select_db',
            'mysql_fetch_assoc',
            'ereg',
            'eregi',
            'split',
            'each',
            'create_function',
            'get_magic_quotes_gpc',
        ];

        $databaseCalls = [];
        $legacyCalls = [];

        $functionCalls = $finder->findInstanceOf(
            $nodes,
            Node\Expr\FuncCall::class
        );

        foreach ($functionCalls as $call) {
            if (!$call->name instanceof Node\Name) {
                continue;
            }

            $name = strtolower($call->name->toString());

            if (in_array($name, $legacyFunctions, true)) {
                $legacyCalls[] = $name;
            }

            if (str_starts_with($name, 'mysql_') || str_starts_with($name, 'mysqli_')) {
                $databaseCalls[] = $name;
            }
        }

        $methodCalls = $finder->find($nodes, static function (Node $node): bool {
            return $node instanceof Node\Expr\MethodCall
                || $node instanceof Node\Expr\StaticCall;
        });

        foreach ($methodCalls as $call) {
            if (!$call->name instanceof Node\Identifier) {
                continue;
            }

            $method = strtolower($call->name->toString());

            if (in_array($method, ['query', 'prepare', 'exec'], true)) {
                $databaseCalls[] = $method;
            }
        }


        $sqlOperations = [];

        $strings = $finder->findInstanceOf(
            $nodes,
            Node\Scalar\String_::class
        );

        foreach ($strings as $string) {
            if (preg_match(
                '/^\s*(SELECT|INSERT|UPDATE|DELETE)\b/i',
                $string->value,
                $matches
            )) {
                $sqlOperations[] = strtoupper($matches[1]);
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
            'databaseCalls' => $databaseCalls,
            'legacyCalls' => $legacyCalls,
            'sqlOperations' => $sqlOperations,
        ];
    }

    private function resolveIncludePath(Node\Expr $expr, string $filePath): ?string
    {
        $path = null;

        if ($expr instanceof Node\Scalar\String_) {
            $path = $expr->value;
        } elseif ($expr instanceof Node\Scalar\MagicConst\Dir) {
            $path = dirname($filePath);
        } elseif ($expr instanceof Node\Scalar\MagicConst\File) {
            $path = $filePath;
        } elseif ($expr instanceof Concat) {
            $left = $this->resolveIncludePath($expr->left, $filePath);
            $right = $this->resolveIncludePath($expr->right, $filePath);

            if ($left !== null && $right !== null) {
                $path = $left . $right;
            }
        }

        return $path;
    }
}
