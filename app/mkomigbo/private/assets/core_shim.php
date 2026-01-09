<?php
declare(strict_types=1);

/**
 * Temporary shim for restoration.
 * Remove once real helpers are restored.
 */

if (!function_exists('h')) {
    function h(?string $s): string {
        return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('url_for')) {
    function url_for(string $path): string {
        // Basic: ensure leading slash
        if ($path === '') return '/';
        return ($path[0] === '/') ? $path : '/' . $path;
    }
}

if (!function_exists('db')) {
    function db(): PDO {
        // Expect environment variables or constants set by initialize/config.
        $dsn  = getenv('DB_DSN') ?: (defined('DB_DSN') ? DB_DSN : '');
        $user = getenv('DB_USER') ?: (defined('DB_USER') ? DB_USER : '');
        $pass = getenv('DB_PASS') ?: (defined('DB_PASS') ? DB_PASS : '');

        if ($dsn === '') {
            throw new RuntimeException('DB_DSN is not configured.');
        }

        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        return $pdo;
    }
}
