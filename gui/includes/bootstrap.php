<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    session_start();
}
error_reporting(E_ALL);
ini_set('display_errors', '0');

define('GUI_ROOT', dirname(__DIR__));

$configFile = GUI_ROOT . '/config/config.php';
if (!file_exists($configFile)) {
    http_response_code(500);
    die('Missing config/config.php - copy config/config.php.example and fill it in.');
}
$GLOBALS['config'] = require $configFile;

require_once GUI_ROOT . '/includes/db.php';
require_once GUI_ROOT . '/includes/crypto.php';
require_once GUI_ROOT . '/includes/auth.php';
require_once GUI_ROOT . '/includes/shell.php';
require_once GUI_ROOT . '/includes/schedule_writer.php';
require_once GUI_ROOT . '/includes/layout.php';
