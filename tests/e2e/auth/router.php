<?php

// Dedicated local-only browser-test server; never use this as a public router.
$host = strtolower(explode(':', $_SERVER['HTTP_HOST'] ?? '')[0]);
if ($host !== 'e2e.localhost' || ! getenv('E2E_AUTH_MAIL_LOG')) {
    http_response_code(403);
    exit;
}
header('X-E2E-Auth-Server: '.getenv('E2E_AUTH_SERVER_ID'));
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = realpath(__DIR__.'/../../../public'.$path);
$public = realpath(__DIR__.'/../../../public');
if ($file && str_starts_with($file, $public.DIRECTORY_SEPARATOR) && is_file($file) && strtolower(pathinfo($file, PATHINFO_EXTENSION)) !== 'php') {
    return false;
}
chdir(__DIR__.'/../../..');
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->booted(function () use ($app): void {
if (! $app->environment('local', 'testing')) abort(403);
Illuminate\Support\Facades\Event::listen(Stancl\Tenancy\Events\TenancyInitialized::class, function (): void {
    if ((string) tenant('id') !== 'e2e') {
        abort(403);
    }
    config([
        'logging.channels.e2e-auth-mail' => ['driver' => 'single', 'path' => getenv('E2E_AUTH_MAIL_LOG'), 'level' => 'debug'],
        'mail.default' => 'log',
        'mail.mailers.log' => ['transport' => 'log', 'channel' => 'e2e-auth-mail'],
    ]);
    Illuminate\Support\Facades\Mail::purge();
});
});
$request = Illuminate\Http\Request::capture();
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
