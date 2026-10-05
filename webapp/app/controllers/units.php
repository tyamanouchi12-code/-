<?php
// 品名(実物の種類・個体)の追加・編集・無効化・削除

function unit_load(int $id): array
{
    $u = db_row('SELECT * FROM inventory_units WHERE id = ?', [$id]);
    if (!$u) {
        http_response_code(404);
        render('error', ['title' => '品名が見つかりません', 'message' => '指定された品名は存在しません。']);
        exit;
    }
    return $u;
}

/** 品名を削除してよいか(棚卸結果・持ち出し記録があれば不可) */
function unit_delete_blockers(int $unitId): array
{
    $b = [];
    $n = (int)db_val('SELECT COUNT(*) FROM inventory_count_unit_results WHERE unit_id = ? AND counted_quantity IS NOT NULL', [$unitId]);
    if ($n > 0) {
        $b[] = "棚卸の結果が {$n} 件あります";
    }
    $n = (int)db_val('SELECT COUNT(*) FROM item_checkouts WHERE unit_id = ?', [$unitId]);
    if ($n > 0) {
        $b[] = "持ち出しの記録が {$n} 件あります";
    }
    return $b;
}

$locations = db_all('SELECT * FROM locations WHERE is_active = 1 ORDER BY sort_order, id');
$conditions = db_all('SELECT * FROM conditions WHERE is_active = 1 ORDER BY sort_order, code');
$customers = db_all('SELECT * FROM customers WHERE is_active = 1 ORDER BY name');
$itemsForMove = db_all('SELECT id, item_code, item_name FROM inventory_items WHERE is_active = 1 ORDER BY sort_order, id');

/** POST から品名の値を取り出して検証 */
function unit_values_from_post(array &$errors): array
{
    $v = [
        'item_id'        => input_int('item_id'),
        'name'           => input_str('name', null, 200),
        'condition_code' => input_str('condition_code', null, 20),
        'count_mode'     => input_str('count_mode', null, 20) ?? 'quantity',
        'use_management_no' => isset($_POST['use_management_no']),
        'customer_id'    => input_int('customer_id'),
        'status'         => input_str('status', null, 20) ?? 'in_stock',
        'location_id'    => input_int('location_id'),
        'notes'          => input_str('notes', null, 2000),
        'sort_order'     => input_int('sort_order'),
    ];
    if ($v['item_id'] === null || !db_val('SELECT 1 FROM inventory_items WHERE id = ? AND is_active = 1', [$v['item_id']])) {
        $errors[] = 'カテゴリを選択してください。';
    }
    if ($v['name'] === null) {
        $errors[] = '品名を入力してください。';
    }
    if (!isset(count_mode_options()[$v['count_mode']])) {
        $errors[] = '数え方が不正です。';
    }
    if ($v['condition_code'] !== null && !db_val('SELECT 1 FROM conditions WHERE code = ?', [$v['condition_code']])) {
        $errors[] = '状態が不正です。';
    }
    if (!isset(unit_status_options()[$v['status']])) {
        $errors[] = '状態(在庫/貸出中…)が不正です。';
    }
    if ($v['location_id'] !== null && !db_val('SELECT 1 FROM locations WHERE id = ?', [$v['location_id']])) {
        $errors[] = '保管場所が存在しません。';
    }
    if ($v['customer_id'] !== null && !db_val('SELECT 1 FROM customers WHERE id = ?', [$v['customer_id']])) {
        $errors[] = '客先が存在しません。';
    }
    return $v;
}

// ---------------------------------------------------------------- 追加
if ($action === 'new') {
    $unit = ['id' => null, 'item_id' => input_int('item_id', $_GET), 'name' => '', 'condition_code' => null, 'management_no' => null, 'count_mode' => 'quantity', 'customer_id' => null,
             'status' => 'in_stock', 'location_id' => null, 'notes' => '', 'sort_order' => null, 'is_active' => 1];
    render('units/form', ['title' => '品名追加', 'unit' => $unit, 'item' => null, 'locations' => $locations, 'conditions' => $conditions, 'customers' => $customers, 'itemsForMove' => $itemsForMove, 'errors' => []]);
}

if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];
    $v = unit_values_from_post($errors);
    if ($errors) {
        render('units/form', ['title' => '品名追加', 'unit' => $v + ['id' => null, 'is_active' => 1, 'management_no' => null], 'item' => null, 'locations' => $locations, 'conditions' => $conditions, 'customers' => $customers, 'itemsForMove' => $itemsForMove, 'errors' => $errors]);
        exit;
    }
    $sort = $v['sort_order'] ?? ((int)db_val('SELECT COALESCE(MAX(sort_order),0) FROM inventory_units WHERE item_id = ?', [$v['item_id']]) + 10);
    $no = management_no_for($v['use_management_no'], null);
    db_insert('INSERT INTO inventory_units (item_id, name, condition_code, count_mode, management_no, customer_id, status, location_id, notes, is_active, sort_order, created_by, updated_by)
               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?)',
        [$v['item_id'], $v['name'], $v['condition_code'], $v['count_mode'], $no, $v['customer_id'], $v['status'], $v['location_id'], $v['notes'], $sort, actor_name(), actor_name()]);
    flash_set('success', '品名「' . $v['name'] . '」を追加しました。' . ($no ? '(管理No ' . fmt_management_no($no) . ')' : ''));
    if (input_str('continue') !== null) {
        redirect('unit', ['action' => 'new', 'item_id' => $v['item_id']]);
    }
    redirect('item', ['id' => $v['item_id']]);
}

// ---------------------------------------------------------------- 編集
if ($action === 'edit') {
    $unit = unit_load((int)input_int('id', $_GET));
    $item = db_row('SELECT * FROM inventory_items WHERE id = ?', [$unit['item_id']]);
    render('units/form', ['title' => '品名編集', 'unit' => $unit, 'item' => $item, 'locations' => $locations, 'conditions' => $conditions, 'customers' => $customers, 'itemsForMove' => $itemsForMove,
                          'deleteBlockers' => unit_delete_blockers((int)$unit['id']), 'errors' => []]);
}

if ($action === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $unit = unit_load((int)input_int('id'));
    $item = db_row('SELECT * FROM inventory_items WHERE id = ?', [$unit['item_id']]);
    $errors = [];
    $v = unit_values_from_post($errors);
    if ($errors) {
        render('units/form', ['title' => '品名編集', 'unit' => array_merge($unit, $v), 'item' => $item, 'locations' => $locations, 'conditions' => $conditions, 'customers' => $customers, 'itemsForMove' => $itemsForMove,
                              'deleteBlockers' => unit_delete_blockers((int)$unit['id']), 'errors' => $errors]);
        exit;
    }
    db_exec('UPDATE inventory_units SET name=?, condition_code=?, count_mode=?, management_no=?, customer_id=?, status=?, location_id=?, notes=?, sort_order=?, updated_by=? WHERE id=?',
        [$v['name'], $v['condition_code'], $v['count_mode'], management_no_for($v['use_management_no'], $unit['management_no']), $v['customer_id'], $v['status'], $v['location_id'], $v['notes'],
         $v['sort_order'] ?? (int)$unit['sort_order'], actor_name(), $unit['id']]);
    $moved = (int)$v['item_id'] !== (int)$unit['item_id'];
    if ($moved) {
        $pdo = db();
        $pdo->beginTransaction();
        move_unit_to_item((int)$unit['id'], (int)$unit['item_id'], (int)$v['item_id']);
        $pdo->commit();
    }
    flash_set('success', '品名を更新しました。' . ($moved ? '(カテゴリ「' . db_val('SELECT item_name FROM inventory_items WHERE id = ?', [$v['item_id']]) . '」へ移動しました。棚卸履歴と持ち出し記録も移動先に付け替えました)' : ''));
    redirect('item', ['id' => $v['item_id']]);
}

// ---------------------------------------------------------------- 無効化 / 再有効化
if ($action === 'toggle_active' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_perm('item.deactivate');
    $unit = unit_load((int)input_int('id'));
    $to = (int)$unit['is_active'] === 1 ? 0 : 1;
    db_exec('UPDATE inventory_units SET is_active = ?, updated_by = ? WHERE id = ?', [$to, actor_name(), $unit['id']]);
    flash_set('success', $to ? '品名を再有効化しました。' : '品名を無効にしました(棚卸履歴は残ります)。');
    redirect('item', ['id' => $unit['item_id']]);
}

// ---------------------------------------------------------------- 削除(棚卸結果・持ち出し記録が無い場合のみ)
if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_perm('item.deactivate');
    $unit = unit_load((int)input_int('id'));
    $blockers = unit_delete_blockers((int)$unit['id']);
    if ($blockers) {
        flash_set('error', 'この品名は削除できません(' . implode('、', $blockers) . ')。履歴を残したまま使わなくする場合は「無効にする」を使ってください。');
        redirect('unit', ['action' => 'edit', 'id' => $unit['id']]);
    }
    $pdo = db();
    $pdo->beginTransaction();
    db_exec('DELETE FROM inventory_count_unit_results WHERE unit_id = ?', [$unit['id']]);   // 数量未入力の空結果のみ残っている可能性
    db_exec('DELETE FROM inventory_units WHERE id = ?', [$unit['id']]);
    $pdo->commit();
    flash_set('success', '品名「' . ($unit['name'] ?? '') . '」を削除しました。');
    redirect('item', ['id' => $unit['item_id']]);
}

http_response_code(404);
render('error', ['title' => 'ページが見つかりません', 'message' => '不正な操作です。']);
