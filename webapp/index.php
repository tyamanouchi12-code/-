<?php
// フロントコントローラ: index.php?page=xxx&action=yyy
require __DIR__ . '/app/bootstrap.php';

$page = isset($_GET['page']) && is_string($_GET['page']) ? preg_replace('/[^a-z_]/', '', $_GET['page']) : 'dashboard';
if ($page === '') {
    $page = 'dashboard';
}

// page → controller ファイル
$routes = [
    'login'      => 'auth',
    'logout'     => 'auth',
    'dashboard'  => 'dashboard',
    'items'      => 'items',
    'item'       => 'items',
    'unit'       => 'units',
    'counts'     => 'counts',
    'count'      => 'counts',
    'count_entry'=> 'count_entry',
    'checkout'   => 'checkout',
    'masters'    => 'masters',
    'users'      => 'users',
    'user'       => 'users',
    'password'   => 'password',
];

if (!isset($routes[$page])) {
    http_response_code(404);
    render('error', ['title' => 'ページが見つかりません', 'message' => '指定されたページ(' . $page . ')は存在しません。サーバー上の index.php が古い可能性があります(画面下部の版を確認してください)。']);
    exit;
}

// 初期設定が済んでいない(利用者0人)場合は setup.php へ誘導
if ($page !== 'login') {
    require_login();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
}

$action = isset($_GET['action']) && is_string($_GET['action']) ? preg_replace('/[^a-z_]/', '', $_GET['action']) : '';

try {
    require APP_DIR . '/controllers/' . $routes[$page] . '.php';
} catch (PDOException $e) {
    http_response_code(500);
    error_log('[nkc_inventory] ' . $e->getMessage());
    render('error', ['title' => 'データベースエラー', 'message' => 'データベース処理でエラーが発生しました。時間をおいて再度お試しください。' . "\n" . $e->getMessage()]);
}
