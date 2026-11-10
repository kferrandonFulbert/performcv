<?php

declare(strict_types=1);

// Router for `php -S 127.0.0.1:8000 -t public public/local.php` only.
if (PHP_SAPI !== 'cli-server') {
    http_response_code(404);
    exit;
}

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = realpath(__DIR__.rawurldecode($path ?: '/'));
if ($file && str_starts_with($file, __DIR__.DIRECTORY_SEPARATOR) && is_file($file) && !str_ends_with($file, '.php')) {
    return false;
}

require dirname(__DIR__).'/vendor/autoload.php';
foreach ([
    'APP_ENV' => 'local',
    'APP_SECRET' => 'performcv-local-development-only',
    'DATABASE_URL' => 'sqlite:///'.str_replace('\\', '/', dirname(__DIR__)).'/.runtime/performcv_local.db',
] as $key => $value) {
    $_SERVER[$key] = $_ENV[$key] = $value;
}
$kernel = new App\Kernel('local', true);
$request = Symfony\Component\HttpFoundation\Request::createFromGlobals();
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
