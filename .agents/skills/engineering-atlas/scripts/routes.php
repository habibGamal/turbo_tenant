<?php
declare(strict_types=1);
// Optional configured-application observation. No requests or tenant activation.
$root = realpath($argv[1]);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$http = $app->make(Illuminate\Contracts\Http\Kernel::class);
$router = $app->make('router');
$routes = [];
function middlewareNames(array $middleware): array {
    return array_map(fn($item) => is_string($item) ? $item : (is_object($item) ? get_class($item) : '{unresolved middleware}'), $middleware);
}
foreach ($router->getRoutes() as $route) {
    $action = $route->getActionName();
    $source = null;
    try {
        $uses = $route->getAction('uses');
        if ($uses instanceof Closure) $reflection = new ReflectionFunction($uses);
        else {
            [$class, $method] = array_pad(explode('@', $action, 2), 2, '__invoke');
            $reflection = new ReflectionMethod($class, $method);
        }
        $path = str_replace('\\', '/', $reflection->getFileName());
        $prefix = str_replace('\\', '/', $root).'/';
        if (str_starts_with($path, $prefix)) $source = ['file' => substr($path, strlen($prefix)), 'line' => $reflection->getStartLine()];
    } catch (Throwable) {}
    $routes[] = ['methods' => $route->methods(), 'uri' => $route->uri(), 'name' => $route->getName(), 'action' => $action, 'middleware' => middlewareNames($route->gatherMiddleware()), 'expanded_middleware' => middlewareNames($router->gatherRouteMiddleware($route)), 'excluded_middleware' => middlewareNames($route->excludedMiddleware()), 'domain_bound' => $route->getDomain() !== null, 'source' => $source];
}
usort($routes, fn($a, $b) => [$a['uri'], $a['methods'], $a['action']] <=> [$b['uri'], $b['methods'], $b['action']]);
// Paths and class names only. Never emit environment, credentials, or domains.
echo json_encode(['mode' => 'configured CLI route registration; no tenant request executed', 'routes' => $routes], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
