<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

Auth::startSession();

$uri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($uri, PHP_URL_PATH) ?: '/';
$basePath = rtrim((string) ($config['base_path'] ?? ''), '/');
if ($basePath !== '' && str_starts_with($path, $basePath)) {
    $path = substr($path, strlen($basePath)) ?: '/';
}

if (str_starts_with($path, '/api/')) {
    $apiPath = substr($path, strlen('/api/'));
    Api::dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $apiPath);
}

$spaIndex = __DIR__ . '/spa/index.html';
if (is_readable($spaIndex)) {
    $wantSpa = $path === '/' || $path === '' || $path === '/index.php';
    if ($wantSpa) {
        $target = ($basePath === '' ? '' : $basePath) . '/spa/';
        header('Location: ' . $target, true, 302);
        exit;
    }
}

http_response_code(503);
header('Content-Type: text/plain; charset=utf-8');
echo "Frontend не е build-нат. В папка frontend изпълни: npm install && npm run build\n";
echo "След това отвори: …/public/spa/ (виж README)\n";
