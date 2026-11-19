<?php

namespace App\Service\Analysis;

use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use PhpParser\Node\Expr\BinaryOp\Concat;
use PhpParser\Error;

class PhpStructureAnalyzerService
{
    public function analyze(string $filePath, array $projectConstants = []): array
    {
        $code = file_get_contents($filePath);

        if ($code === false) {
            throw new \RuntimeException('Unable to read PHP file.');
        }

        $parser = (new ParserFactory())->createForNewestSupportedVersion();

        try {
            $nodes = $parser->parse($code) ?? [];
        } catch (Error $e) {
            error_log(sprintf(
                'PHP parsing failed for %s: %s',
                $filePath,
                $e->getMessage()
            ));

            return [
                'fileType' => 'unknown',
                'namespace' => null,
                'classes' => [],
                'functions' => [],
                'dependencies' => [
                    'uses' => [],
                    'includes' => [],
                    'extends' => [],
                    'implements' => [],
                ],
                'databaseCalls' => [],
                'legacyCalls' => [],
                'sqlOperations' => [],
            ];
        }
        $finder = new NodeFinder();
        $constants = array_merge(
            $projectConstants,
            $this->extractPathConstants($nodes, $filePath)
        );

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
            $path = $this->resolveIncludePath($include->expr, $filePath, $constants);

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
            'fileType' => $this->detectFileType($nodes),
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

    public function extractProjectPathConstants(string $directory): array
    {
        $constants = [];
        $configPath = realpath($directory . DIRECTORY_SEPARATOR . 'config.php');
        $rootPath = realpath($directory);

        if (
            $configPath === false
            || $rootPath === false
            || !str_starts_with($configPath, $rootPath . DIRECTORY_SEPARATOR)
            || !is_file($configPath)
        ) {
            return [];
        }

        $code = file_get_contents($configPath);

        if ($code === false) {
            return [];
        }

        $parser = (new ParserFactory())->createForNewestSupportedVersion();

        try {
            $nodes = $parser->parse($code) ?? [];
        } catch (Error $e) {
            error_log(sprintf(
                'PHP config parsing failed for %s: %s',
                $configPath,
                $e->getMessage()
            ));

            return [];
        }

        return $this->extractPathConstants($nodes, $configPath);
    }

    private function extractPathConstants(array $nodes, string $filePath): array
    {
        $constants = [];
        $finder = new NodeFinder();

        $definitions = $finder->find($nodes, static function (Node $node): bool {
            return $node instanceof Node\Expr\FuncCall
                && $node->name instanceof Node\Name
                && strtolower($node->name->toString()) === 'define';
        });

        foreach ($definitions as $definition) {
            if (count($definition->args) < 2) {
                continue;
            }

            $name = $definition->args[0]->value;

            if (!$name instanceof Node\Scalar\String_) {
                continue;
            }

            $value = $this->resolveIncludePath(
                $definition->args[1]->value,
                $filePath,
                $constants
            );

            if ($value !== null) {
                $constants[$name->value] = $value;
            }
        }

        return $constants;
    }

    private function resolveIncludePath(
        Node\Expr $expr,
        string $filePath,
        array $constants = []
    ): ?string {
        if ($expr instanceof Node\Scalar\String_) {
            return $expr->value;
        }

        if ($expr instanceof Node\Scalar\MagicConst\Dir) {
            return dirname($filePath);
        }

        if ($expr instanceof Node\Scalar\MagicConst\File) {
            return $filePath;
        }

        if ($expr instanceof Node\Expr\ConstFetch) {
            $name = $expr->name->toString();

            return $constants[$name] ?? null;
        }

        if ($expr instanceof Concat) {
            $left = $this->resolveIncludePath(
                $expr->left,
                $filePath,
                $constants
            );

            $right = $this->resolveIncludePath(
                $expr->right,
                $filePath,
                $constants
            );

            if ($left !== null && $right !== null) {
                return $left . $right;
            }
        }
        if (
            $expr instanceof Node\Expr\FuncCall
            && $expr->name instanceof Node\Name
            && count($expr->args) === 1
        ) {
            $functionName = strtolower($expr->name->toString());

            if (in_array($functionName, ['dirname', 'realpath'], true)) {
                $argument = $this->resolveIncludePath(
                    $expr->args[0]->value,
                    $filePath,
                    $constants
                );

                if ($argument === null) {
                    return null;
                }

                if ($functionName === 'dirname') {
                    return dirname($argument);
                }

                $resolved = realpath($argument);

                return $resolved !== false ? $resolved : null;
            }
        }
        return null;
    }


    private function detectFileType(array $nodes): string
    {
        $finder = new NodeFinder();

        $classes = $finder->findInstanceOf(
            $nodes,
            Node\Stmt\Class_::class
        );

        foreach ($classes as $class) {
            if ($class->isAnonymous()) {
                continue;
            }

            $name = $class->name?->toString() ?? '';
            $parent = $class->extends?->toString() ?? '';

            $attributes = $class->attrGroups;

            foreach ($attributes as $group) {
                foreach ($group->attrs as $attribute) {
                    $attributeName = $attribute->name->toString();

                    if (
                        $attributeName === 'ORM\Entity'
                        || $attributeName === 'Doctrine\ORM\Mapping\Entity'
                    ) {
                        return 'entity';
                    }
                }
            }

            if (
                str_ends_with($name, 'Controller')
                || str_ends_with($parent, 'Controller')
            ) {
                return 'controller';
            }

            if (str_ends_with($name, 'Repository')) {
                return 'repository';
            }

            if (str_ends_with($name, 'Service')) {
                return 'service';
            }
        }

        $hasHtml = $finder->findFirstInstanceOf(
            $nodes,
            Node\Stmt\InlineHTML::class
        ) !== null;

        $hasExecutablePhp = $finder->findFirst(
            $nodes,
            static fn (Node $node): bool =>
                $node instanceof Node\Stmt\Class_
                || $node instanceof Node\Stmt\Function_
                || $node instanceof Node\Expr\FuncCall
                || $node instanceof Node\Expr\MethodCall
                || $node instanceof Node\Expr\Assign
        ) !== null;

        if ($hasHtml && !$hasExecutablePhp) {
            return 'template';
        }

        if ($hasHtml) {
            return 'mixed';
        }

        return 'php';
    }

}
