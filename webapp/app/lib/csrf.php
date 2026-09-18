<?php
// CSRF トークン

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . h(csrf_token()) . '">';
}

function csrf_verify(): void
{
    $sent = $_POST['_token'] ?? '';
    if (!is_string($sent) || $sent === '' || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $sent)) {
        http_response_code(400);
        echo '不正なリクエストです(セッションが切れた可能性があります)。ブラウザで戻ってやり直してください。';
        exit;
    }
}
