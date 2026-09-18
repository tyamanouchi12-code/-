<?php
// ログイン / ログアウト

if ($page === 'logout') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        logout();
    }
    redirect('login');
}

if (current_user()) {
    redirect('dashboard');
}

$errors = [];
$loginId = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $loginId = (string)input_str('login_id', null, 50);
    $password = (string)($_POST['password'] ?? '');
    if ($loginId === '' || $password === '') {
        $errors[] = 'ログインIDとパスワードを入力してください。';
    } elseif (!login_attempt($loginId, $password)) {
        usleep(300000);
        $errors[] = 'ログインIDまたはパスワードが正しくありません。';
    } else {
        $to = $_SESSION['after_login'] ?? '';
        unset($_SESSION['after_login']);
        $q = is_string($to) ? (parse_url($to, PHP_URL_QUERY) ?? '') : '';
        parse_str($q, $qs);
        if (!empty($qs['page']) && $qs['page'] !== 'login' && $qs['page'] !== 'logout') {
            header('Location: index.php?' . $q);
            exit;
        }
        redirect('dashboard');
    }
}
// 利用者が0人なら初期設定へ案内
try {
    if ((int)db_val('SELECT COUNT(*) FROM users') === 0) {
        $errors[] = '利用者が登録されていません。setup.php を開いて最初の管理者を作成してください。';
    }
} catch (PDOException $e) {
    $errors[] = 'データベースに接続できません。app/config.php を確認してください。';
}
render('login', ['title' => 'ログイン', 'errors' => $errors, 'loginId' => $loginId]);
