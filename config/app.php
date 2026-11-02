<?php
/**
 * config/app.php
 * Carregado SEMPRE a partir de public/index.php
 * BASE_URL é sempre calculado relativo ao index.php
 */

// Liga output buffering para evitar "headers already sent" por warnings
if (!ob_get_level()) {
    ob_start();
}

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host     = $_SERVER['HTTP_HOST'] ?? 'localhost';

// SCRIPT_NAME é sempre /pasta/public/index.php
// dirname() retorna /pasta/public
$publicPath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php')), '/');

define('BASE_URL', $protocol . '://' . $host . $publicPath);
define('APP_ROOT', dirname(__DIR__));

date_default_timezone_set('America/Sao_Paulo');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Autoload de models
spl_autoload_register(function (string $class) {
    $map = [
        'UserModel'         => APP_ROOT . '/src/models/UserModel.php',
        'SubjectModel'      => APP_ROOT . '/src/models/SubjectModel.php',
        'TopicModel'        => APP_ROOT . '/src/models/TopicModel.php',
        'SessionModel'      => APP_ROOT . '/src/models/SessionModel.php',
        'ReviewModel'       => APP_ROOT . '/src/models/ReviewModel.php',
        'GamificationModel' => APP_ROOT . '/src/models/GamificationModel.php',
        'ReportModel'       => APP_ROOT . '/src/models/ReportModel.php',
    ];
    if (isset($map[$class])) require_once $map[$class];
});

require_once APP_ROOT . '/config/database.php';
require_once APP_ROOT . '/src/helpers/functions.php';
require_once APP_ROOT . '/src/helpers/auth.php';
