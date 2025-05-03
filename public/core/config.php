<?php

define('OPENAI_API_KEY', getenv('OPENAI_API_KEY') ?: 'sk-dummy-key-for-testing');

ini_set('session.gc_maxlifetime', 86400); // 24 hours
session_start();

$isProduction = getenv('APP_ENV') === 'production';
ini_set('display_errors', $isProduction ? 0 : 1);
error_reporting(E_ALL);
ini_set('error_log', '/var/log/php/error.log');

date_default_timezone_set('UTC');
