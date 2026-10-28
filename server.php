<?php

declare(strict_types=1);

$publicPath = getcwd();
$basePath = dirname($publicPath);

$uri = urldecode(
    parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? ''
);

// 1. If physical file exists in public/, let PHP built-in server handle it directly
if ($uri !== '/' && file_exists($publicPath.$uri)) {
    return false;
}

// 2. Extract tenant subdomain from HTTP_HOST (e.g., kofe.localhost:8000 -> kofe)
$host = $_SERVER['HTTP_HOST'] ?? '';
$tenant = null;
if (preg_match('/^([^.]+)\./', $host, $matches)) {
    $tenant = $matches[1];
}

// 3. Emulate Apache .htaccess rewrite rules for direct tenant images & storage access
if ($tenant) {
    $matchedFile = null;

    if (preg_match('#^/images/(.+)$#', $uri, $matches)) {
        $candidate = $basePath."/storage/tenant{$tenant}/app/images/{$matches[1]}";
        if (file_exists($candidate) && is_file($candidate)) {
            $matchedFile = $candidate;
        }
    } elseif (preg_match('#^/storage/images/(.+)$#', $uri, $matches)) {
        $candidate = $basePath."/storage/tenant{$tenant}/app/images/{$matches[1]}";
        if (file_exists($candidate) && is_file($candidate)) {
            $matchedFile = $candidate;
        }
    } elseif (preg_match('#^/storage/(.+)$#', $uri, $matches)) {
        $candidate = $basePath."/storage/tenant{$tenant}/app/{$matches[1]}";
        if (file_exists($candidate) && is_file($candidate)) {
            $matchedFile = $candidate;
        }
    }

    if ($matchedFile) {
        $ext = mb_strtolower(pathinfo($matchedFile, PATHINFO_EXTENSION));
        $mimeTypes = [
            'webp' => 'image/webp',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'ico' => 'image/x-icon',
            'css' => 'text/css',
            'js' => 'application/javascript',
            'json' => 'application/json',
        ];

        header('Content-Type: '.($mimeTypes[$ext] ?? (function_exists('mime_content_type') ? mime_content_type($matchedFile) : 'application/octet-stream')));
        header('Content-Length: '.(string) filesize($matchedFile));
        header('Cache-Control: public, max-age=31536000, immutable');
        readfile($matchedFile);
        exit;
    }
}

// 4. Default Laravel routing & logging
$formattedDateTime = date('D M j H:i:s Y');
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$remoteAddress = ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1').':'.($_SERVER['REMOTE_PORT'] ?? '0');

file_put_contents('php://stdout', "[$formattedDateTime] $remoteAddress [$requestMethod] URI: $uri\n");

require_once $publicPath.'/index.php';
