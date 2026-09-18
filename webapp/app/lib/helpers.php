<?php
// 表示・入力の共通ヘルパー

function h($s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $page, array $params = []): string
{
    $q = array_merge(['page' => $page], $params);
    return 'index.php?' . http_build_query($q);
}

function redirect(string $page, array $params = []): void
{
    header('Location: ' . url($page, $params));
    exit;
}

function flash_set(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function flash_pull(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

/** POST/GET の文字列をトリムして返す。空文字は null */
function input_str(string $key, ?array $src = null, ?int $maxlen = null): ?string
{
    $src = $src ?? $_POST;
    if (!isset($src[$key]) || is_array($src[$key])) {
        return null;
    }
    $v = trim((string)$src[$key]);
    if ($v === '') {
        return null;
    }
    if ($maxlen !== null && mb_strlen($v) > $maxlen) {
        $v = mb_substr($v, 0, $maxlen);
    }
    return $v;
}

/** 整数入力。空や数値でないものは null */
function input_int(string $key, ?array $src = null): ?int
{
    $v = input_str($key, $src);
    if ($v === null) {
        return null;
    }
    $v = mb_convert_kana($v, 'n'); // 全角数字を半角に
    if (!preg_match('/^-?\d+$/', $v)) {
        return null;
    }
    return (int)$v;
}

/** 日付入力(YYYY-MM-DD)。不正なら null */
function input_date(string $key, ?array $src = null): ?string
{
    $v = input_str($key, $src);
    if ($v === null) {
        return null;
    }
    $v = str_replace('/', '-', mb_convert_kana($v, 'n'));
    $d = DateTime::createFromFormat('Y-m-d', $v);
    if (!$d || $d->format('Y-m-d') !== $v) {
        return null;
    }
    return $v;
}

function fmt_date(?string $d): string
{
    if (!$d) {
        return '';
    }
    $t = strtotime($d);
    return $t ? date('Y/m/d', $t) : $d;
}

function fmt_datetime(?string $d): string
{
    if (!$d) {
        return '';
    }
    $t = strtotime($d);
    return $t ? date('Y/m/d H:i', $t) : $d;
}

function now_str(): string
{
    return date('Y-m-d H:i:s');
}

// ---- 区分値のラベル ----
function management_type_label(?string $v): string
{
    return ['quantity' => '数量管理', 'unit' => '個体管理'][$v] ?? (string)$v;
}

function stock_type_label(?string $v): string
{
    return ['internal' => '社内在庫', 'customer_surplus' => '客先備品余り'][$v] ?? (string)$v;
}

function count_status_label(?string $v): string
{
    return ['preparing' => '準備中', 'in_progress' => '棚卸中', 'confirmed' => '確定済'][$v] ?? (string)$v;
}

function confirm_status_label(?string $v): string
{
    return ['unconfirmed' => '未確認', 'confirmed' => '確認済', 'needs_check' => '要確認'][$v] ?? (string)$v;
}

function unit_status_label(?string $v): string
{
    return ['in_stock' => '在庫', 'lent' => '貸出中', 'in_use' => '使用中', 'disposed' => '廃棄'][$v] ?? (string)$v;
}

function unit_result_label(?string $v): string
{
    return ['unchecked' => '未確認', 'present' => '有', 'absent' => '無'][$v] ?? (string)$v;
}

function management_type_options(): array
{
    return ['quantity' => '数量管理', 'unit' => '個体管理'];
}

function stock_type_options(): array
{
    return ['internal' => '社内在庫', 'customer_surplus' => '客先備品余り'];
}

function confirm_status_options(): array
{
    return ['unconfirmed' => '未確認', 'confirmed' => '確認済', 'needs_check' => '要確認'];
}

function unit_status_options(): array
{
    return ['in_stock' => '在庫', 'lent' => '貸出中', 'in_use' => '使用中', 'disposed' => '廃棄'];
}

/** 状態コード → 名称(全件、無効含む) */
function condition_map(): array
{
    static $m = null;
    if ($m === null) {
        $m = [];
        foreach (db_all('SELECT code, name FROM conditions ORDER BY sort_order, code') as $r) {
            $m[$r['code']] = $r['name'];
        }
    }
    return $m;
}

function condition_name(?string $code): string
{
    if ($code === null || $code === '') {
        return '';
    }
    return condition_map()[$code] ?? $code;
}

/** ビューを描画して処理を終了する(レイアウト付き) */
function render(string $view, array $data = []): void
{
    $data['view'] = $view;
    extract($data, EXTR_SKIP);
    require APP_DIR . '/views/layout.php';
    exit;
}

/** レイアウトなしでビューを読み込む */
function view_include(string $view, array $data = []): void
{
    extract($data, EXTR_SKIP);
    require APP_DIR . '/views/' . $view . '.php';
}
