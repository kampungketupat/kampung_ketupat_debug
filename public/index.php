<?php

session_start();

define('BASE_PATH', dirname(__DIR__));

// Deteksi BASE_URL yang aman untuk:
// 1) virtual host (contoh: mysite.test)
// 2) subfolder localhost (contoh: /Kampung-Ketupat-Warna-Warni-main)
$scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$phpSelf    = str_replace('\\', '/', $_SERVER['PHP_SELF'] ?? '');
$requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$projectDir = '/' . basename(BASE_PATH);

$baseUrl = '';
$candidatePaths = [$scriptName, $phpSelf];

foreach ($candidatePaths as $path) {
    if (!$path) {
        continue;
    }

    // Normalisasi path yang mengandung PATH_INFO seperti /index.php/admin/login
    // agar base path tidak ikut tercemar segmen route.
    $indexPos = strpos($path, '/index.php');
    if ($indexPos !== false) {
        $path = substr($path, 0, $indexPos);
    }

    $dir = str_replace('\\', '/', dirname($path));
    if ($dir === '/' || $dir === '.') {
        $dir = '';
    }

    if (str_ends_with($dir, '/public')) {
        $dir = substr($dir, 0, -7);
    }

    if ($dir !== '') {
        $baseUrl = rtrim($dir, '/');
        break;
    }
}

// Fallback kuat untuk localhost subfolder bila SCRIPT_NAME/PHP_SELF tidak memuat nama folder project.
if ($baseUrl === '' && $projectDir !== '/' && str_starts_with($requestUri, $projectDir)) {
    $baseUrl = $projectDir;
}

define('BASE_URL', rtrim($baseUrl, '/'));

// Error handling dasar: log selalu aktif, tampilan error bisa diatur via APP_DEBUG.
$debug = filter_var($_ENV['APP_DEBUG'] ?? getenv('APP_DEBUG') ?? true, FILTER_VALIDATE_BOOL);
error_reporting(E_ALL);
ini_set('display_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');

$logDir = BASE_PATH . '/storage/logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0777, true);
}
ini_set('error_log', $logDir . '/app.log');

set_exception_handler(function (Throwable $e) use ($debug) {
    error_log('[UNCAUGHT] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    if ($debug) {
        echo '<h1>Internal Server Error</h1><pre>' . htmlspecialchars((string)$e) . '</pre>';
        return;
    }
    echo '<h1>Terjadi kesalahan pada server.</h1><p>Silakan coba lagi beberapa saat.</p>';
});

// Load core
require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/app/core/Router.php';
require_once BASE_PATH . '/app/core/Controller.php';

// Router
$router = new Router();

// Routes
require_once BASE_PATH . '/routes/web.php';

// Run
$router->dispatch();
