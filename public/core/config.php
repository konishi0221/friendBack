<?php

function loadEnv($path) {
    if (!file_exists($path)) {
        return false;
    }
    
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        
        if (strpos($value, '"') === 0 && strrpos($value, '"') === strlen($value) - 1) {
            $value = substr($value, 1, -1);
        } elseif (strpos($value, "'") === 0 && strrpos($value, "'") === strlen($value) - 1) {
            $value = substr($value, 1, -1);
        }
        
        if (!getenv($name)) {
            putenv("$name=$value");
            $_ENV[$name] = $value;
        }
    }
    
    return true;
}

$envPath = dirname(__DIR__, 2) . '/.env';
loadEnv($envPath);

define('OPENAI_API_KEY', getenv('OPENAI_API_KEY') ?: 'sk-dummy-key-for-testing');

ini_set('session.gc_maxlifetime', 86400); // 24 hours
session_start();

$isProduction = getenv('APP_ENV') === 'production';
ini_set('display_errors', $isProduction ? 0 : 1);
error_reporting(E_ALL);
ini_set('error_log', '/var/log/php/error.log');

date_default_timezone_set('UTC');
