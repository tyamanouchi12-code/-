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

$action = isset($_GET['action']) && is_string($_GET['action']) ? preg_replace('/[^a-z_]/', '', $_GET['action']) : '';

try {
    if ($page !== 'login') {
        require_login();
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_verify();
    }
    require APP_DIR . '/controllers/' . $routes[$page] . '.php';
} catch (PDOException $e) {
    http_response_code(500);
    error_log('[nkc_inventory] ' . $e->getMessage());
    $msg = $e->getMessage();
    if (str_contains($msg, 'Access denied') || str_contains($msg, 'SQLSTATE[HY000] [2002]') || str_contains($msg, 'Unknown database') || str_contains($msg, 'getaddrinfo')) {
        $hint = "データベースに接続できません。app/config.local.php(なければ app/config.php)のホスト名・データベース名・ユーザー名・パスワードを確認してください。\n"
              . "プログラムを上書きアップロードした直後にこのエラーが出た場合は、接続情報が初期値に戻っています。app/config.local.php に接続情報を書いておくと、次回以降は上書きされません。";
    } else {
        $hint = 'データベース処理でエラーが発生しました。時間をおいて再度お試しください。';
    }
    render('error', ['title' => 'データベースエラー', 'message' => $hint . "\n\n(詳細) " . $msg]);
}
