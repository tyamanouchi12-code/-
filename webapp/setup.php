<?php
// =====================================================================
// 初期設定(最初の管理者を作成)  ※利用者が1人もいないときだけ動作します
// 使い方: ブラウザで https://(ドメイン)/(設置フォルダ)/setup.php を開き、管理者を作成
//         作成後はこのファイルをサーバーから削除してください
// =====================================================================
require __DIR__ . '/app/bootstrap.php';

$errors = [];
$done = false;
$dbError = null;
$userCount = null;
try {
    $userCount = (int)db_val('SELECT COUNT(*) FROM users');
} catch (PDOException $e) {
    $dbError = $e->getMessage();
}

if ($dbError === null && $userCount > 0) {
    $errors[] = '利用者がすでに登録されているため、初期設定は実行できません。このファイル(setup.php)を削除してください。';
} elseif ($dbError === null && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $loginId = input_str('login_id', null, 50);
    $name = input_str('display_name', null, 100);
    $pw = (string)($_POST['password'] ?? '');
    $pw2 = (string)($_POST['password2'] ?? '');
    if ($loginId === null || !preg_match('/^[A-Za-z0-9_.@-]{3,50}$/', $loginId)) {
        $errors[] = 'ログインIDは半角英数字と _ . @ - で3〜50文字にしてください。';
    }
    if ($name === null) {
        $errors[] = '表示名を入力してください。';
    }
    if (mb_strlen($pw) < 8) {
        $errors[] = 'パスワードは8文字以上にしてください。';
    }
    if ($pw !== $pw2) {
        $errors[] = 'パスワード(確認)が一致しません。';
    }
    if (!$errors) {
        $pdo = db();
        $pdo->beginTransaction();
        $id = db_insert('INSERT INTO users (login_id, display_name, password_hash, is_active) VALUES (?, ?, ?, 1)',
            [$loginId, $name, password_hash($pw, PASSWORD_DEFAULT)]);
        foreach (array_keys(permission_definitions()) as $code) {
            db_exec('INSERT INTO user_permissions (user_id, permission_code) VALUES (?, ?)', [$id, $code]);
        }
        $pdo->commit();
        $done = true;
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<title>初期設定 - <?= h(APP_NAME) ?></title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body class="login-body">
<div class="login-box">
  <h1><?= h(APP_NAME) ?> 初期設定</h1>
  <?php if ($dbError !== null): ?>
    <div class="flash flash-error">データベースに接続できません。app/config.php の接続情報と、sql/001_schema.sql が実行済みかを確認してください。<br><small><?= h($dbError) ?></small></div>
  <?php elseif ($done): ?>
    <div class="flash flash-success">管理者「<?= h($name) ?>」を作成しました。<strong>このファイル(setup.php)をサーバーから削除</strong>してから、<a href="index.php?page=login">ログイン画面</a>へ進んでください。</div>
  <?php else: ?>
    <?php foreach ($errors as $e): ?><div class="flash flash-error"><?= h($e) ?></div><?php endforeach; ?>
    <?php if ($userCount === 0): ?>
    <p>最初の管理者(全権限)を作成します。</p>
    <form method="post">
      <?= csrf_field() ?>
      <label>ログインID<input type="text" name="login_id" value="<?= h($_POST['login_id'] ?? '') ?>" required autocomplete="username"></label>
      <label>表示名<input type="text" name="display_name" value="<?= h($_POST['display_name'] ?? '') ?>" required></label>
      <label>パスワード(8文字以上)<input type="password" name="password" required autocomplete="new-password"></label>
      <label>パスワード(確認)<input type="password" name="password2" required autocomplete="new-password"></label>
      <button type="submit" class="btn btn-primary">管理者を作成</button>
    </form>
    <?php endif; ?>
  <?php endif; ?>
</div>
</body>
</html>
