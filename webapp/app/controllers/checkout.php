<?php
// 備品の持ち出し / 戻し(スマホ向け)

$mode = input_str('mode', $_GET, 10) === 'in' ? 'in' : 'out';

// ---------------------------------------------------------------- 持ち出し登録
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'out') {
    $itemId = input_int('item_id');
    $unitId = input_int('unit_id');
    $qty = input_int('quantity') ?? 1;
    $userId = input_int('user_id');
    $otherName = input_str('other_name', null, 100);
    $notes = input_str('notes', null, 500);
    $item = $itemId ? db_row('SELECT * FROM inventory_items WHERE id = ? AND is_active = 1', [$itemId]) : null;
    $errors = [];
    if (!$item) {
        $errors[] = '品目を選択してください。';
    }
    $unit = null;
    if ($item && $item['management_type'] === 'unit') {
        $unit = $unitId ? db_row('SELECT * FROM inventory_units WHERE id = ? AND item_id = ? AND is_active = 1', [$unitId, $item['id']]) : null;
        if (!$unit) {
            $errors[] = '個体管理の品目です。管理No(個体)を選択してください。';
        } elseif (db_val('SELECT id FROM item_checkouts WHERE unit_id = ? AND returned_at IS NULL', [$unit['id']])) {
            $errors[] = 'その個体(' . $unit['management_no'] . ')はすでに持ち出し中です。';
        }
        $qty = 1;
    } elseif ($qty < 1 || $qty > 9999) {
        $errors[] = '数量は 1 以上で入力してください。';
    }
    $name = null;
    if ($userId !== null) {
        $name = db_val('SELECT display_name FROM users WHERE id = ?', [$userId]);
        if ($name === null) {
            $errors[] = '持ち出した人の選択が不正です。';
        }
    } elseif ($otherName !== null) {
        $name = $otherName;
    }
    if ($errors) {
        flash_set('error', implode("\n", $errors));
        redirect('checkout', ['mode' => 'out', 'item_id' => $itemId]);
    }
    $pdo = db();
    $pdo->beginTransaction();
    db_insert('INSERT INTO item_checkouts (item_id, unit_id, quantity, checked_out_user_id, checked_out_by_name, checked_out_at, notes, created_by, updated_by)
               VALUES (?, ?, ?, ?, ?, NOW(), ?, ?, ?)',
        [$item['id'], $unit['id'] ?? null, $qty, $userId, $name, $notes, actor_name(), actor_name()]);
    if ($unit) {
        db_exec("UPDATE inventory_units SET status = 'lent', updated_by = ? WHERE id = ?", [actor_name(), $unit['id']]);
    }
    $pdo->commit();
    flash_set('success', '持ち出しを登録しました: ' . ($item['item_name'] ?? $item['item_code']) . ($unit ? '(' . $unit['management_no'] . ')' : ' ×' . $qty) . ($name ? ' / ' . $name : ''));
    redirect('checkout', ['mode' => 'out']);
}

// ---------------------------------------------------------------- 戻し登録
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'in') {
    $id = input_int('checkout_id');
    $co = $id ? db_row('SELECT c.*, i.item_name, i.item_code FROM item_checkouts c JOIN inventory_items i ON i.id = c.item_id WHERE c.id = ?', [$id]) : null;
    if (!$co) {
        flash_set('error', '持ち出しの記録が見つかりません。');
        redirect('checkout', ['mode' => 'in']);
    }
    if ($co['returned_at'] !== null) {
        flash_set('info', 'すでに戻し済みです。');
        redirect('checkout', ['mode' => 'in']);
    }
    $pdo = db();
    $pdo->beginTransaction();
    db_exec('UPDATE item_checkouts SET returned_at = NOW(), returned_by_name = ?, updated_by = ? WHERE id = ?', [actor_name(), actor_name(), $co['id']]);
    if ($co['unit_id']) {
        db_exec("UPDATE inventory_units SET status = 'in_stock', updated_by = ? WHERE id = ? AND status = 'lent'", [actor_name(), $co['unit_id']]);
    }
    $pdo->commit();
    flash_set('success', '戻しを登録しました: ' . ($co['item_name'] ?? $co['item_code']));
    redirect('checkout', ['mode' => 'in']);
}

// ---------------------------------------------------------------- 表示
$items = db_all('SELECT i.id, i.item_code, i.item_name, i.management_type, c.name AS condition_name
                 FROM inventory_items i LEFT JOIN conditions c ON c.code = i.condition_code
                 WHERE i.is_active = 1 ORDER BY i.sort_order, i.id');
$units = [];
foreach (db_all("SELECT u.id, u.item_id, u.management_no, u.serial_number,
                        (SELECT COUNT(*) FROM item_checkouts c WHERE c.unit_id = u.id AND c.returned_at IS NULL) AS is_out
                 FROM inventory_units u WHERE u.is_active = 1 AND u.status <> 'disposed' ORDER BY u.id") as $u) {
    $units[(int)$u['item_id']][] = $u;
}
$users = db_all('SELECT id, display_name FROM users WHERE is_active = 1 ORDER BY display_name');
$open = db_all('SELECT c.*, i.item_code, i.item_name, u.management_no
                FROM item_checkouts c JOIN inventory_items i ON i.id = c.item_id LEFT JOIN inventory_units u ON u.id = c.unit_id
                WHERE c.returned_at IS NULL ORDER BY c.checked_out_at DESC');
$recent = db_all('SELECT c.*, i.item_code, i.item_name, u.management_no
                  FROM item_checkouts c JOIN inventory_items i ON i.id = c.item_id LEFT JOIN inventory_units u ON u.id = c.unit_id
                  WHERE c.returned_at IS NOT NULL ORDER BY c.returned_at DESC LIMIT 20');
$me = current_user();
render('checkout/index', [
    'title' => '持ち出し・戻し', 'mode' => $mode, 'items' => $items, 'units' => $units, 'users' => $users,
    'open' => $open, 'recent' => $recent, 'preItem' => input_int('item_id', $_GET), 'me' => $me,
]);
