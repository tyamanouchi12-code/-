<?php
// 備品の持ち出し / 戻し(スマホ向け)。品名(実物の種類・個体)単位で記録する

$mode = input_str('mode', $_GET, 10) === 'in' ? 'in' : 'out';

// ---------------------------------------------------------------- 持ち出し登録
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'out') {
    $unitId = input_int('unit_id');
    $qty = input_int('quantity') ?? 1;
    $userId = input_int('user_id');
    $otherName = input_str('other_name', null, 100);
    $notes = input_str('notes', null, 500);
    $unit = $unitId ? db_row('SELECT u.*, i.item_name, i.is_active AS item_active FROM inventory_units u JOIN inventory_items i ON i.id = u.item_id WHERE u.id = ? AND u.is_active = 1', [$unitId]) : null;
    $errors = [];
    if (!$unit) {
        $errors[] = 'カテゴリと品名を選択してください。';
    } else {
        $isUnit = $unit['management_no'] !== null && $unit['management_no'] !== '';
        if ($isUnit) {
            if (db_val('SELECT id FROM item_checkouts WHERE unit_id = ? AND returned_at IS NULL', [$unit['id']])) {
                $errors[] = 'その個体(' . $unit['management_no'] . ')はすでに持ち出し中です。';
            }
            $qty = 1;
        } elseif ($qty < 1 || $qty > 9999) {
            $errors[] = '数量は 1 以上で入力してください。';
        }
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
        redirect('checkout', ['mode' => 'out', 'item_id' => input_int('item_id')]);
    }
    $pdo = db();
    $pdo->beginTransaction();
    db_insert('INSERT INTO item_checkouts (item_id, unit_id, quantity, checked_out_user_id, checked_out_by_name, checked_out_at, notes, created_by, updated_by)
               VALUES (?, ?, ?, ?, ?, NOW(), ?, ?, ?)',
        [$unit['item_id'], $unit['id'], $qty, $userId, $name, $notes, actor_name(), actor_name()]);
    if ($isUnit) {
        db_exec("UPDATE inventory_units SET status = 'lent', updated_by = ? WHERE id = ?", [actor_name(), $unit['id']]);
    }
    $pdo->commit();
    flash_set('success', '持ち出しを登録しました: ' . unit_label($unit) . ($isUnit ? '' : ' ×' . $qty) . ($name ? ' / ' . $name : ''));
    redirect('checkout', ['mode' => 'out']);
}

// ---------------------------------------------------------------- 戻し登録
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'in') {
    $id = input_int('checkout_id');
    $co = $id ? db_row('SELECT c.*, u.name AS unit_name, u.management_no FROM item_checkouts c LEFT JOIN inventory_units u ON u.id = c.unit_id WHERE c.id = ?', [$id]) : null;
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
    if ($co['unit_id'] && $co['management_no']) {
        db_exec("UPDATE inventory_units SET status = 'in_stock', updated_by = ? WHERE id = ? AND status = 'lent'", [actor_name(), $co['unit_id']]);
    }
    $pdo->commit();
    flash_set('success', '戻しを登録しました: ' . unit_label(['name' => $co['unit_name'], 'management_no' => $co['management_no']]));
    redirect('checkout', ['mode' => 'in']);
}

// ---------------------------------------------------------------- 表示
$items = db_all('SELECT id, item_code, item_name FROM inventory_items WHERE is_active = 1 ORDER BY sort_order, id');
$units = [];
foreach (db_all("SELECT u.id, u.item_id, u.name, u.management_no, u.serial_number, c.name AS condition_name,
                        (SELECT COUNT(*) FROM item_checkouts k WHERE k.unit_id = u.id AND k.returned_at IS NULL) AS open_count
                 FROM inventory_units u LEFT JOIN conditions c ON c.code = u.condition_code
                 WHERE u.is_active = 1 AND u.status <> 'disposed' ORDER BY u.sort_order, u.id") as $u) {
    $units[(int)$u['item_id']][] = $u;
}
$users = db_all('SELECT id, display_name FROM users WHERE is_active = 1 ORDER BY display_name');
$open = db_all('SELECT c.*, i.item_code, i.item_name, u.management_no, u.name AS unit_name
                FROM item_checkouts c JOIN inventory_items i ON i.id = c.item_id LEFT JOIN inventory_units u ON u.id = c.unit_id
                WHERE c.returned_at IS NULL ORDER BY c.checked_out_at DESC');
$recent = db_all('SELECT c.*, i.item_code, i.item_name, u.management_no, u.name AS unit_name
                  FROM item_checkouts c JOIN inventory_items i ON i.id = c.item_id LEFT JOIN inventory_units u ON u.id = c.unit_id
                  WHERE c.returned_at IS NOT NULL ORDER BY c.returned_at DESC LIMIT 20');
render('checkout/index', [
    'title' => '持ち出し・戻し', 'mode' => $mode, 'items' => $items, 'units' => $units, 'users' => $users,
    'open' => $open, 'recent' => $recent, 'preItem' => input_int('item_id', $_GET), 'me' => current_user(),
]);
