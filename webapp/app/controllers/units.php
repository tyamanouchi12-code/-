<?php
// 個体の登録・編集・無効化

function unit_load(int $id): array
{
    $u = db_row('SELECT * FROM inventory_units WHERE id = ?', [$id]);
    if (!$u) {
        http_response_code(404);
        render('error', ['title' => '個体が見つかりません', 'message' => '指定された個体は存在しません。']);
        exit;
    }
    return $u;
}

$locations = db_all('SELECT * FROM locations WHERE is_active = 1 ORDER BY sort_order, id');

if ($action === 'new' || $action === 'edit') {
    if ($action === 'edit') {
        $unit = unit_load((int)input_int('id', $_GET));
        $item = db_row('SELECT * FROM inventory_items WHERE id = ?', [$unit['item_id']]);
    } else {
        $item = db_row('SELECT * FROM inventory_items WHERE id = ?', [(int)input_int('item_id', $_GET)]);
        if (!$item) {
            http_response_code(404);
            render('error', ['title' => '品目が見つかりません', 'message' => '指定された品目は存在しません。']);
            exit;
        }
        $unit = ['id' => null, 'item_id' => $item['id'], 'management_no' => '', 'serial_number' => '', 'ip_address' => '',
                 'status' => 'in_stock', 'location_id' => $item['location_id'], 'notes' => '', 'is_active' => 1];
    }
    render('units/form', ['title' => $action === 'edit' ? '個体編集' : '個体追加', 'unit' => $unit, 'item' => $item, 'locations' => $locations, 'errors' => []]);
}

if ($action === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = input_int('id');
    $existing = $id ? unit_load($id) : null;
    $itemId = $existing ? (int)$existing['item_id'] : (int)input_int('item_id');
    $item = db_row('SELECT * FROM inventory_items WHERE id = ?', [$itemId]);
    if (!$item) {
        http_response_code(404);
        render('error', ['title' => '品目が見つかりません', 'message' => '指定された品目は存在しません。']);
        exit;
    }
    $v = [
        'management_no' => input_str('management_no', null, 50),
        'serial_number' => input_str('serial_number', null, 100),
        'ip_address'    => input_str('ip_address', null, 100),
        'status'        => input_str('status', null, 20),
        'location_id'   => input_int('location_id'),
        'notes'         => input_str('notes', null, 5000),
    ];
    $errors = [];
    if ($v['management_no'] === null && $v['serial_number'] === null) {
        $errors[] = '管理No またはシリアル番号のどちらかは入力してください。';
    }
    if (!isset(unit_status_options()[$v['status']])) {
        $errors[] = '状態が不正です。';
    }
    if ($v['location_id'] !== null && !db_val('SELECT 1 FROM locations WHERE id = ?', [$v['location_id']])) {
        $errors[] = '保管場所が存在しません。';
    }
    if ($errors) {
        $unit = array_merge($existing ?? ['id' => null, 'item_id' => $itemId, 'is_active' => 1], $v, ['id' => $id]);
        render('units/form', ['title' => $id ? '個体編集' : '個体追加', 'unit' => $unit, 'item' => $item, 'locations' => $locations, 'errors' => $errors]);
        exit;
    }
    if ($existing) {
        db_exec('UPDATE inventory_units SET management_no=?, serial_number=?, ip_address=?, status=?, location_id=?, notes=?, updated_by=? WHERE id=?',
            [$v['management_no'], $v['serial_number'], $v['ip_address'], $v['status'], $v['location_id'], $v['notes'], actor_name(), $id]);
        flash_set('success', '個体を更新しました。');
    } else {
        db_insert('INSERT INTO inventory_units (item_id, management_no, serial_number, ip_address, status, location_id, notes, is_active, created_by, updated_by)
                   VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, ?)',
            [$itemId, $v['management_no'], $v['serial_number'], $v['ip_address'], $v['status'], $v['location_id'], $v['notes'], actor_name(), actor_name()]);
        if ($item['management_type'] !== 'unit') {
            db_exec("UPDATE inventory_items SET management_type = 'unit', updated_by = ? WHERE id = ?", [actor_name(), $itemId]);
            flash_set('info', '個体を登録したため、品目の管理方法を「個体管理」に変更しました。');
        }
        flash_set('success', '個体を追加しました。');
    }
    redirect('item', ['id' => $itemId]);
}

if ($action === 'toggle_active' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_perm('item.deactivate');
    $unit = unit_load((int)input_int('id'));
    $to = (int)$unit['is_active'] === 1 ? 0 : 1;
    db_exec('UPDATE inventory_units SET is_active = ?, updated_by = ? WHERE id = ?', [$to, actor_name(), $unit['id']]);
    flash_set('success', $to ? '個体を再有効化しました。' : '個体を無効にしました(棚卸履歴は残ります)。');
    redirect('item', ['id' => $unit['item_id']]);
}

http_response_code(404);
render('error', ['title' => 'ページが見つかりません', 'message' => '不正な操作です。']);
