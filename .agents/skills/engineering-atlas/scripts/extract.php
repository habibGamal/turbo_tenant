<?php
declare(strict_types=1);

// Parse source without loading application classes or running migrations.
require $argv[1].'/vendor/autoload.php';
$root = realpath($argv[1]);
$parser = (new PhpParser\ParserFactory)->createForNewestSupportedVersion();
$finder = new PhpParser\NodeFinder;
$printer = new PhpParser\PrettyPrinter\Standard;
$result = ['classes' => [], 'schema_operations' => [], 'errors' => []];
function literal($node) {
    if ($node instanceof PhpParser\Node\Scalar\String_ || $node instanceof PhpParser\Node\Scalar\Int_) return $node->value;
    if ($node instanceof PhpParser\Node\Expr\Array_) return array_map(fn($x) => $x ? literal($x->value) : null, $node->items);
    if ($node instanceof PhpParser\Node\Expr\ConstFetch) return (string) $node->name;
    return null;
}
function chain($node): array {
    $calls = [];
    while ($node instanceof PhpParser\Node\Expr\MethodCall) {
        array_unshift($calls, ['method' => (string) $node->name, 'args' => array_map(fn($a) => literal($a->value), $node->args)]);
        $node = $node->var;
    }
    return [$node, $calls];
}
$files = [];
foreach (['app', 'database/migrations'] as $dir) {
    if (!is_dir($root.'/'.$dir)) continue;
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$dir, FilesystemIterator::SKIP_DOTS)) as $file) {
        if ($file->getExtension() === 'php') $files[] = $file->getPathname();
    }
}
sort($files);
foreach ($files as $file) {
    $relative = str_replace('\\', '/', substr($file, strlen($root) + 1));
    try {
        $ast = $parser->parse(file_get_contents($file));
        $traverser = new PhpParser\NodeTraverser;
        $traverser->addVisitor(new PhpParser\NodeVisitor\NameResolver);
        $ast = $traverser->traverse($ast);
    } catch (Throwable $e) {
        $result['errors'][] = ['file' => $relative, 'message' => 'PHP parse failed', 'line' => $e->getStartLine()];
        continue;
    }
    foreach ($finder->findInstanceOf($ast, PhpParser\Node\Stmt\ClassLike::class) as $class) {
        if (!$class->name) continue;
        $name = (string) ($class->namespacedName ?? $class->name);
        $refs = [];
        foreach ($finder->findInstanceOf($class, PhpParser\Node\Name::class) as $ref) {
            $value = (string) $ref;
            if (str_starts_with($value, 'App\\') && $value !== $name) $refs[$value] = true;
        }
        $methods = [];
        foreach ($class->getMethods() as $method) {
            $calls = [];
            foreach ($finder->find($method->stmts ?? [], fn($n) => $n instanceof PhpParser\Node\Expr\MethodCall || $n instanceof PhpParser\Node\Expr\StaticCall) as $call) {
                if ($call->name instanceof PhpParser\Node\Identifier) $calls[] = ['name' => (string)$call->name, 'line' => $call->getStartLine()];
            }
            $methods[] = ['name' => (string)$method->name, 'line' => $method->getStartLine(), 'end' => $method->getEndLine(), 'calls' => $calls];
        }
        $result['classes'][] = ['name' => $name, 'file' => $relative, 'line' => $class->getStartLine(), 'kind' => $class->getType(), 'references' => array_keys($refs), 'methods' => $methods];
    }
    if (!str_contains($relative, '/migrations/')) continue;
    foreach ($finder->findInstanceOf($ast, PhpParser\Node\Stmt\ClassMethod::class) as $method) {
        if ((string)$method->name !== 'up') continue;
        foreach ($finder->findInstanceOf($method->stmts ?? [], PhpParser\Node\Expr\StaticCall::class) as $call) {
            if (!$call->class instanceof PhpParser\Node\Name || !str_ends_with((string)$call->class, '\\Schema')) continue;
            if (!in_array((string)$call->name, ['create', 'table', 'dropIfExists', 'rename'])) continue;
            $table = isset($call->args[0]) ? literal($call->args[0]->value) : null;
            $ops = [];
            $closure = $call->args[1]->value ?? null;
            if ($closure instanceof PhpParser\Node\Expr\Closure) {
                $varName = $closure->params[0]->var->name ?? 'table';
                foreach ($finder->findInstanceOf($closure->stmts, PhpParser\Node\Stmt\Expression::class) as $expression) {
                    [$base, $parts] = chain($expression->expr);
                    if ($base instanceof PhpParser\Node\Expr\Variable && $base->name === $varName && $parts) {
                        $op = ['chain' => $parts, 'line' => $expression->getStartLine()];
                        if (in_array($parts[0]['method'], ['foreignId','foreignUuid']) && is_string($parts[0]['args'][0] ?? null)) {
                            $op['convention_target'] = Illuminate\Support\Str::plural(Illuminate\Support\Str::beforeLast($parts[0]['args'][0], '_'));
                        }
                        $ops[] = $op;
                    }
                }
            }
            $result['schema_operations'][] = ['table' => $table, 'operation' => (string)$call->name, 'file' => $relative, 'line' => $call->getStartLine(), 'operations' => $ops];
        }
    }
}
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
