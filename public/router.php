<?php
declare(strict_types=1);

// Development router only. Serve assets from the public directory, never the project root.
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');
$file = realpath(__DIR__ . $path);
$root = realpath(__DIR__) . DIRECTORY_SEPARATOR;
if ($file !== false && str_starts_with($file, $root) && is_file($file) && str_starts_with($path, '/assets/')) {
    $types = ['css'=>'text/css; charset=utf-8','js'=>'application/javascript; charset=utf-8','woff2'=>'font/woff2','png'=>'image/png','webp'=>'image/webp','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','svg'=>'image/svg+xml'];
    $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    if (isset($types[$extension])) {
        if (!in_array($_SERVER['REQUEST_METHOD'], ['GET','HEAD'], true)) {
            header('Allow: GET, HEAD'); http_response_code(405); return;
        }
        $etag = '"' . hash_file('sha256', $file) . '"';
        header('Content-Type: ' . $types[$extension]);
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: public, max-age=31536000, immutable');
        header('ETag: ' . $etag);
        if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
            http_response_code(304); return;
        }
        header('Content-Length: ' . filesize($file));
        if ($_SERVER['REQUEST_METHOD'] !== 'HEAD') { readfile($file); }
        return;
    }
}
require __DIR__ . '/index.php';
