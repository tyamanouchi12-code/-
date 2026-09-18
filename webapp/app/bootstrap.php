<?php
// 共通初期化: 設定読込・セッション開始・ライブラリ読込
declare(strict_types=0);

mb_internal_encoding('UTF-8');

define('APP_DIR', __DIR__);
$config = require APP_DIR . '/config.php';
define('APP_NAME', $config['app']['name']);
define('APP_VERSION', '2026-09-18.4');   // 画面下部に表示。アップロード漏れの確認用(更新のたびに上げる)
date_default_timezone_set($config['app']['timezone']);

require APP_DIR . '/lib/db.php';
require APP_DIR . '/lib/helpers.php';
require APP_DIR . '/lib/csrf.php';
require APP_DIR . '/lib/permissions.php';
require APP_DIR . '/lib/auth.php';
require APP_DIR . '/lib/stock.php';

db_configure($config['db']);

// セッション(Cookie は HttpOnly / SameSite=Lax)
session_name($config['app']['session_name']);
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Lax',
    'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
]);
session_start();

// 無操作タイムアウト
$lifetime = (int)$config['app']['session_lifetime'];
if ($lifetime > 0 && !empty($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $lifetime) {
    session_unset();
    session_destroy();
    session_start();
}
$_SESSION['last_activity'] = time();
