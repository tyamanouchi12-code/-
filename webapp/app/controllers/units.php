<?php
// 内訳(実物の種類・個体)の編集・無効化。追加は品目編集画面から行う

function unit_load(int $id): array
{
    $u = db_row('SELECT * FROM inventory_units WHERE id = ?', [$id]);
    if (!$u) {
        http_response_code(404);
        render('error', ['title' => '内訳が見つかりません', 'message' => '指定された内訳は存在しません。']);
        exit;
    }
    return $u;
}

$locations = db_all('SELECT * FROM locations WHERE is_active = 1 ORDER BY sort_order, id');
$conditions = db_all('SELECT * FROM conditions WHERE is_active = 1 ORDER BY sort_order, code');
$customers = db_all('SELECT * FROM customers WHERE is_active = 1 ORDER BY name');

if ($action === 'new') {
    // 旧リンク互換: 品目編集画面へ
    redirect('item', ['action' => 'edit', 'id' => (int)input_int('item_id', $_GET)]);
}

if ($action === 'edit') {
    $unit = unit_load((int)input_int('id', $_GET));
    $item = db_row('SELECT * FROM inventory_items WHERE id = ?', [$unit['item_id']]);
    render('units/form', ['title' => '内訳編集', 'unit' => $unit, 'item' => $item, 'locations' => $locations, 'conditions' => $conditions, 'customers' => $customers, 'errors' => []]);
}

if ($action === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $unit = unit_load((int)input_int('id'));
    $item = db_row('SELECT * FROM inventory_items WHERE id = ?', [$unit['item_id']]);
    $v = [
        'name'           => input_str('name', null, 200),
        'condition_code' => input_str('condition_code', null, 20),
        'management_no'  => input_str('management_no', null, 50),
        'serial_number'  => input_str('serial_number', null, 100),
        'ip_address'     => input_str('ip_address', null, 100),
        'manufacturer'   => input_str('manufacturer', null, 100),
        'model_number'   => input_str('model_number', null, 100),
        'customer_id'    => input_int('customer_id'),
        'status'         => input_str('status', null, 20),
        'location_id'    => input_int('location_id'),
        'notes'          => input_str('notes', null, 500),
        'sort_order'     => input_int('sort_order'),
    ];
    $errors = [];
    if ($v['name'] === null) {
        $errors[] = '内訳名(品名)を入力してください。';
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
    if ($errors) {
        render('units/form', ['title' => '内訳編集', 'unit' => array_merge($unit, $v), 'item' => $item, 'locations' => $locations, 'conditions' => $conditions, 'customers' => $customers, 'errors' => $errors]);
        exit;
    }
    db_exec('UPDATE inventory_units SET name=?, condition_code=?, management_no=?, serial_number=?, ip_address=?, manufacturer=?, model_number=?, customer_id=?, status=?, location_id=?, notes=?, sort_order=?, updated_by=? WHERE id=?',
        [$v['name'], $v['condition_code'], $v['management_no'], $v['serial_number'], $v['ip_address'], $v['manufacturer'], $v['model_number'], $v['customer_id'], $v['status'], $v['location_id'], $v['notes'],
         $v['sort_order'] ?? (int)$unit['sort_order'], actor_name(), $unit['id']]);
    flash_set('success', '内訳を更新しました。');
    redirect('item', ['id' => $unit['item_id']]);
}

if ($action === 'toggle_active' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_perm('item.deactivate');
    $unit = unit_load((int)input_int('id'));
    $to = (int)$unit['is_active'] === 1 ? 0 : 1;
    db_exec('UPDATE inventory_units SET is_active = ?, updated_by = ? WHERE id = ?', [$to, actor_name(), $unit['id']]);
    flash_set('success', $to ? '内訳を再有効化しました。' : '内訳を無効にしました(棚卸履歴は残ります)。');
    redirect('item', ['id' => $unit['item_id']]);
}

http_response_code(404);
render('error', ['title' => 'ページが見つかりません', 'message' => '不正な操作です。']);
