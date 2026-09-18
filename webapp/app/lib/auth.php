<?php
// ログイン・権限チェック

function current_user(): ?array
{
    static $user = false;
    if ($user === false) {
        $user = null;
        if (!empty($_SESSION['user_id'])) {
            $u = db_row('SELECT id, login_id, display_name, is_active FROM users WHERE id = ?', [(int)$_SESSION['user_id']]);
            if ($u && (int)$u['is_active'] === 1) {
                $u['permissions'] = array_column(
                    db_all('SELECT permission_code FROM user_permissions WHERE user_id = ?', [$u['id']]),
                    'permission_code'
                );
                $user = $u;
            }
        }
    }
    return $user;
}

function require_login(): void
{
    if (!current_user()) {
        $_SESSION['after_login'] = $_SERVER['REQUEST_URI'] ?? '';
        redirect('login');
    }
}

function can(string $permission): bool
{
    $u = current_user();
    return $u ? in_array($permission, $u['permissions'], true) : false;
}

function require_perm(string $permission): void
{
    if (!can($permission)) {
        http_response_code(403);
        render('error', ['title' => '権限がありません', 'message' => 'この操作を行う権限がありません(' . (permission_definitions()[$permission] ?? $permission) . ')。管理者に権限の付与を依頼してください。']);
        exit;
    }
}

function login_attempt(string $loginId, string $password): bool
{
    $u = db_row('SELECT * FROM users WHERE login_id = ?', [$loginId]);
    if (!$u || (int)$u['is_active'] !== 1 || !password_verify($password, $u['password_hash'])) {
        return false;
    }
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$u['id'];
    unset($_SESSION['csrf_token']);
    db_exec('UPDATE users SET last_login_at = NOW() WHERE id = ?', [$u['id']]);
    return true;
}

function logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/** 登録者・更新者に記録する名前 */
function actor_name(): string
{
    $u = current_user();
    return $u ? mb_substr($u['display_name'], 0, 100) : 'system';
}
