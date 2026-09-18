<?php
// マスタ管理(保管場所 / カテゴリ / 客先 / 状態)
require_perm('master.manage');

$types = [
    'locations'  => ['label' => '保管場所', 'table' => 'locations',  'key' => 'id',   'has_sort' => true],
    'categories' => ['label' => 'カテゴリ', 'table' => 'categories', 'key' => 'id',   'has_sort' => true],
    'customers'  => ['label' => '客先',     'table' => 'customers',  'key' => 'id',   'has_sort' => false],
    'conditions' => ['label' => '状態',     'table' => 'conditions', 'key' => 'code', 'has_sort' => true],
];
$type = input_str('type', $_GET, 20) ?? 'locations';
if (!isset($types[$type])) {
    $type = 'locations';
}
$def = $types[$type];
$tbl = $def['table'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'save') {
    $key = input_str('key', null, 50);
    $name = input_str('name', null, 100);
    $sort = input_int('sort_order') ?? 0;
    $active = input_int('is_active') === 0 ? 0 : 1;
    $code = input_str('code', null, 20);
    $errors = [];
    if ($name === null) {
        $errors[] = '名称を入力してください。';
    }
    if ($type === 'conditions' && $key === null) {
        if ($code === null || !preg_match('/^[a-z0-9_]{1,20}$/', $code)) {
            $errors[] = '状態コードは半角小文字英数字と _ で1〜20文字にしてください(例: defective)。';
        } elseif (db_val('SELECT 1 FROM conditions WHERE code = ?', [$code])) {
            $errors[] = 'その状態コードはすでに使われています。';
        }
    }
    if (!$errors) {
        $dup = db_val("SELECT {$def['key']} FROM $tbl WHERE name = ?" . ($key !== null ? " AND {$def['key']} <> ?" : ''), $key !== null ? [$name, $key] : [$name]);
        if ($dup) {
            $errors[] = '同じ名称がすでに登録されています。';
        }
    }
    if ($errors) {
        flash_set('error', implode("\n", $errors));
        redirect('masters', ['type' => $type]);
    }
    if ($key !== null) {
        if ($def['has_sort']) {
            db_exec("UPDATE $tbl SET name = ?, sort_order = ?, is_active = ? WHERE {$def['key']} = ?", [$name, $sort, $active, $key]);
        } else {
            db_exec("UPDATE $tbl SET name = ?, is_active = ? WHERE {$def['key']} = ?", [$name, $active, $key]);
        }
        flash_set('success', $def['label'] . 'を更新しました。');
    } else {
        if ($type === 'conditions') {
            db_exec('INSERT INTO conditions (code, name, sort_order, is_active) VALUES (?, ?, ?, 1)', [$code, $name, $sort]);
        } elseif ($def['has_sort']) {
            if ($sort === 0) {
                $sort = (int)db_val("SELECT COALESCE(MAX(sort_order),0) FROM $tbl") + 1;
            }
            db_exec("INSERT INTO $tbl (name, sort_order, is_active) VALUES (?, ?, 1)", [$name, $sort]);
        } else {
            db_exec("INSERT INTO $tbl (name, is_active) VALUES (?, 1)", [$name]);
        }
        flash_set('success', $def['label'] . 'を追加しました。');
    }
    redirect('masters', ['type' => $type]);
}

$order = $def['has_sort'] ? 'sort_order, ' . $def['key'] : 'name';
$rows = db_all("SELECT * FROM $tbl ORDER BY $order");
// 使用件数(品目での参照数)
$usage = [];
$refCol = ['locations' => 'location_id', 'categories' => 'category_id', 'customers' => 'customer_id', 'conditions' => 'condition_code'][$type];
foreach (db_all("SELECT $refCol AS k, COUNT(*) AS c FROM inventory_items WHERE $refCol IS NOT NULL GROUP BY $refCol") as $r) {
    $usage[(string)$r['k']] = (int)$r['c'];
}
render('masters/list', ['title' => 'マスタ管理', 'types' => $types, 'type' => $type, 'def' => $def, 'rows' => $rows, 'usage' => $usage]);
