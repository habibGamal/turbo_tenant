<?php

declare(strict_types=1);

$publicPath = getcwd();
$basePath = dirname($publicPath);

$uri = urldecode(
    parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? ''
);

// Match production delivery for content-hashed Vite assets in the local server.
if (str_starts_with($uri, '/build/assets/')) {
    $assetRoot = realpath($publicPath.'/build/assets');
    $asset = realpath($publicPath.$uri);
    $extension = mb_strtolower(pathinfo($asset ?: '', PATHINFO_EXTENSION));
    $types = ['js' => 'application/javascript', 'css' => 'text/css', 'woff2' => 'font/woff2', 'svg' => 'image/svg+xml'];
    if ($assetRoot && $asset && str_starts_with($asset, $assetRoot.DIRECTORY_SEPARATOR)
        && is_file($asset) && isset($types[$extension])
        && preg_match('/-[a-zA-Z0-9_-]{8,}\.[a-z0-9]+$/', basename($asset))) {
        $content = file_get_contents($asset);
        $gzip = false;
        foreach (explode(',', $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '') as $encoding) {
            if (preg_match('/^\s*gzip\s*(?:;\s*q=([0-9.]+))?\s*$/i', $encoding, $match)
                && (! isset($match[1]) || (float) $match[1] > 0)) {
                $gzip = in_array($extension, ['js', 'css', 'svg'], true) && function_exists('gzencode');
            }
        }
        if ($gzip) {
            $content = gzencode($content, 6);
            header('Content-Encoding: gzip');
        }
        $etag = '"'.hash('sha256', $content).'"';
        header('Content-Type: '.$types[$extension]);
        header('Cache-Control: public, max-age=31536000, immutable');
        header('Vary: Accept-Encoding');
        header('ETag: '.$etag);
        if (mb_trim($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
            http_response_code(304);
            exit;
        }
        header('Content-Length: '.mb_strlen($content, '8bit'));
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD') {
            echo $content;
        }
        exit;
    }
}

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
if (function_exists('ob_gzhandler') && ! ini_get('zlib.output_compression')) {
    ob_start('ob_gzhandler');
}
$formattedDateTime = date('D M j H:i:s Y');
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$remoteAddress = ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1').':'.($_SERVER['REMOTE_PORT'] ?? '0');

file_put_contents('php://stdout', "[$formattedDateTime] $remoteAddress [$requestMethod] URI: $uri\n");

require_once $publicPath.'/index.php';
