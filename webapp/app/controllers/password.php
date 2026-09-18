<?php
// 自分のパスワード変更
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $me = current_user();
    $cur = (string)($_POST['current_password'] ?? '');
    $pw = (string)($_POST['password'] ?? '');
    $pw2 = (string)($_POST['password2'] ?? '');
    $row = db_row('SELECT password_hash FROM users WHERE id = ?', [$me['id']]);
    if (!password_verify($cur, $row['password_hash'])) {
        $errors[] = '現在のパスワードが正しくありません。';
    }
    if (mb_strlen($pw) < 8) {
        $errors[] = '新しいパスワードは8文字以上にしてください。';
    }
    if ($pw !== $pw2) {
        $errors[] = '新しいパスワード(確認)が一致しません。';
    }
    if (!$errors) {
        db_exec('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($pw, PASSWORD_DEFAULT), $me['id']]);
        flash_set('success', 'パスワードを変更しました。');
        redirect('dashboard');
    }
}
render('password', ['title' => 'パスワード変更', 'errors' => $errors]);
