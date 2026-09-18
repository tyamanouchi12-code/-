<?php
// 利用者管理
require_perm('user.manage');
$perms = permission_definitions();

function user_load(int $id): array
{
    $u = db_row('SELECT * FROM users WHERE id = ?', [$id]);
    if (!$u) {
        http_response_code(404);
        render('error', ['title' => '利用者が見つかりません', 'message' => '指定された利用者は存在しません。']);
        exit;
    }
    $u['permissions'] = array_column(db_all('SELECT permission_code FROM user_permissions WHERE user_id = ?', [$id]), 'permission_code');
    return $u;
}

if ($page === 'users') {
    $users = db_all('SELECT u.*, (SELECT GROUP_CONCAT(permission_code ORDER BY permission_code SEPARATOR ",") FROM user_permissions p WHERE p.user_id = u.id) AS perms
                     FROM users u ORDER BY u.is_active DESC, u.id');
    render('users/list', ['title' => '利用者管理', 'users' => $users, 'perms' => $perms]);
}

if ($page === 'user' && ($action === 'new' || $action === 'edit')) {
    $user = $action === 'edit' ? user_load((int)input_int('id', $_GET)) : ['id' => null, 'login_id' => '', 'display_name' => '', 'is_active' => 1, 'permissions' => []];
    render('users/form', ['title' => $action === 'edit' ? '利用者編集' : '利用者追加', 'user' => $user, 'perms' => $perms, 'errors' => []]);
}

if ($page === 'user' && $action === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = input_int('id');
    $existing = $id ? user_load($id) : null;
    $me = current_user();
    $loginId = input_str('login_id', null, 50);
    $name = input_str('display_name', null, 100);
    $active = input_int('is_active') === 0 ? 0 : 1;
    $pw = (string)($_POST['password'] ?? '');
    $pw2 = (string)($_POST['password2'] ?? '');
    $selected = array_values(array_intersect(array_keys($perms), array_map('strval', (array)($_POST['permissions'] ?? []))));
    $errors = [];
    if ($loginId === null || !preg_match('/^[A-Za-z0-9_.@-]{3,50}$/', $loginId)) {
        $errors[] = 'ログインIDは半角英数字と _ . @ - で3〜50文字にしてください。';
    } elseif (db_val('SELECT id FROM users WHERE login_id = ?' . ($id ? ' AND id <> ?' : ''), $id ? [$loginId, $id] : [$loginId])) {
        $errors[] = 'そのログインIDはすでに使われています。';
    }
    if ($name === null) {
        $errors[] = '表示名を入力してください。';
    }
    if (!$existing && mb_strlen($pw) < 8) {
        $errors[] = 'パスワードは8文字以上にしてください。';
    }
    if ($pw !== '' && mb_strlen($pw) < 8) {
        $errors[] = 'パスワードは8文字以上にしてください。';
    }
    if ($pw !== $pw2) {
        $errors[] = 'パスワード(確認)が一致しません。';
    }
    if ($existing && (int)$existing['id'] === (int)$me['id']) {
        if ($active === 0) {
            $errors[] = '自分自身を無効にはできません。';
        }
        if (!in_array('user.manage', $selected, true)) {
            $errors[] = '自分自身から「利用者管理」権限を外すことはできません。';
        }
    }
    if ($errors) {
        $user = ['id' => $id, 'login_id' => $loginId, 'display_name' => $name, 'is_active' => $active, 'permissions' => $selected];
        render('users/form', ['title' => $id ? '利用者編集' : '利用者追加', 'user' => $user, 'perms' => $perms, 'errors' => $errors]);
        exit;
    }
    $pdo = db();
    $pdo->beginTransaction();
    if ($existing) {
        db_exec('UPDATE users SET login_id = ?, display_name = ?, is_active = ? WHERE id = ?', [$loginId, $name, $active, $id]);
        if ($pw !== '') {
            db_exec('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($pw, PASSWORD_DEFAULT), $id]);
        }
        db_exec('DELETE FROM user_permissions WHERE user_id = ?', [$id]);
        $uid = $id;
    } else {
        $uid = db_insert('INSERT INTO users (login_id, display_name, password_hash, is_active) VALUES (?, ?, ?, ?)', [$loginId, $name, password_hash($pw, PASSWORD_DEFAULT), $active]);
    }
    foreach ($selected as $code) {
        db_exec('INSERT INTO user_permissions (user_id, permission_code) VALUES (?, ?)', [$uid, $code]);
    }
    $pdo->commit();
    flash_set('success', $existing ? '利用者を更新しました。' : '利用者を追加しました。');
    redirect('users');
}

http_response_code(404);
render('error', ['title' => 'ページが見つかりません', 'message' => '不正な操作です。']);
