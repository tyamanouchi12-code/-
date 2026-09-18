<?php
// 品目一覧 / 登録・編集 / 詳細 / 無効化

function item_load(int $id): array
{
    $it = db_row('SELECT * FROM inventory_items WHERE id = ?', [$id]);
    if (!$it) {
        http_response_code(404);
        render('error', ['title' => '品目が見つかりません', 'message' => '指定された品目は存在しません。']);
        exit;
    }
    return $it;
}

function item_masters(): array
{
    return [
        'locations'  => db_all('SELECT * FROM locations WHERE is_active = 1 ORDER BY sort_order, id'),
        'categories' => db_all('SELECT * FROM categories WHERE is_active = 1 ORDER BY sort_order, id'),
        'customers'  => db_all('SELECT * FROM customers WHERE is_active = 1 ORDER BY name'),
        'conditions' => db_all('SELECT * FROM conditions WHERE is_active = 1 ORDER BY sort_order, code'),
    ];
}

/** 直近の確定済み棚卸の数量(品目ID → 数量) */
function latest_confirmed_quantities(): array
{
    $c = db_row("SELECT id, count_name, base_date FROM inventory_counts WHERE status = 'confirmed' ORDER BY base_date DESC, id DESC LIMIT 1");
    if (!$c) {
        return ['count' => null, 'qty' => []];
    }
    $qty = [];
    foreach (db_all('SELECT item_id, count_quantity FROM inventory_count_details WHERE count_id = ?', [$c['id']]) as $r) {
        $qty[(int)$r['item_id']] = $r['count_quantity'];
    }
    return ['count' => $c, 'qty' => $qty];
}

// ---------------------------------------------------------------- 一覧
if ($page === 'items') {
    $f = [
        'q'          => input_str('q', $_GET, 100),
        'category'   => input_int('category', $_GET),
        'location'   => input_int('location', $_GET),
        'condition'  => input_str('condition', $_GET, 20),
        'mtype'      => input_str('mtype', $_GET, 20),
        'stock_type' => input_str('stock_type', $_GET, 30),
        'active'     => input_str('active', $_GET, 5) ?? '1',
    ];
    $where = [];
    $params = [];
    if ($f['q'] !== null) {
        $where[] = '(i.item_name LIKE ? OR i.item_code LIKE ? OR i.manufacturer LIKE ? OR i.model_number LIKE ? OR i.notes LIKE ?
                     OR EXISTS (SELECT 1 FROM inventory_units u WHERE u.item_id = i.id AND (u.management_no LIKE ? OR u.serial_number LIKE ?)))';
        $like = '%' . $f['q'] . '%';
        array_push($params, $like, $like, $like, $like, $like, $like, $like);
    }
    if ($f['category'] !== null) { $where[] = 'i.category_id = ?'; $params[] = $f['category']; }
    if ($f['location'] !== null) { $where[] = 'i.location_id = ?'; $params[] = $f['location']; }
    if ($f['condition'] !== null) {
        if ($f['condition'] === '_none') { $where[] = 'i.condition_code IS NULL'; }
        else { $where[] = 'i.condition_code = ?'; $params[] = $f['condition']; }
    }
    if ($f['mtype'] !== null) { $where[] = 'i.management_type = ?'; $params[] = $f['mtype']; }
    if ($f['stock_type'] !== null) { $where[] = 'i.stock_type = ?'; $params[] = $f['stock_type']; }
    if ($f['active'] === '1') { $where[] = 'i.is_active = 1'; }
    elseif ($f['active'] === '0') { $where[] = 'i.is_active = 0'; }

    $sql = 'SELECT i.*, l.name AS location_name, c.name AS category_name, cu.name AS customer_name,
                   (SELECT COUNT(*) FROM inventory_units u WHERE u.item_id = i.id AND u.is_active = 1) AS unit_count,
                   (SELECT GROUP_CONCAT(u.management_no ORDER BY u.id SEPARATOR ", ") FROM inventory_units u WHERE u.item_id = i.id AND u.is_active = 1) AS unit_nos
            FROM inventory_items i
            LEFT JOIN locations l ON l.id = i.location_id
            LEFT JOIN categories c ON c.id = i.category_id
            LEFT JOIN customers cu ON cu.id = i.customer_id'
         . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
         . ' ORDER BY i.sort_order, i.id';
    $items = db_all($sql, $params);
    $latest = latest_confirmed_quantities();
    $stock = stock_summary(array_map(fn($r) => (int)$r['id'], $items));
    render('items/list', ['title' => '品目一覧', 'items' => $items, 'f' => $f, 'latest' => $latest, 'stock' => $stock] + item_masters());
}

// ---------------------------------------------------------------- 詳細
if ($page === 'item' && $action === '') {
    $id = input_int('id', $_GET);
    $item = item_load((int)$id);
    $units = db_all('SELECT u.*, l.name AS location_name FROM inventory_units u LEFT JOIN locations l ON l.id = u.location_id WHERE u.item_id = ? ORDER BY u.is_active DESC, u.id', [$item['id']]);
    // 棚卸履歴(確定済みを基準に前回との差異を計算)
    $history = db_all('SELECT c.id AS count_id, c.count_name, c.base_date, c.status, d.count_quantity, d.confirm_status, d.notes, d.counted_by, d.counted_at,
                              l.name AS location_name
                       FROM inventory_count_details d
                       JOIN inventory_counts c ON c.id = d.count_id
                       LEFT JOIN locations l ON l.id = d.location_id_at_count
                       WHERE d.item_id = ? ORDER BY c.base_date, c.id', [$item['id']]);
    $prev = null;
    foreach ($history as &$hrow) {
        $hrow['prev_quantity'] = $prev;
        $hrow['diff'] = ($prev === null || $hrow['count_quantity'] === null) ? null : ((int)$hrow['count_quantity'] - (int)$prev);
        if ($hrow['status'] === 'confirmed' && $hrow['count_quantity'] !== null) {
            $prev = $hrow['count_quantity'];
        }
    }
    unset($hrow);
    $history = array_reverse($history);
    $unitHistory = [];
    if ($units) {
        foreach (db_all('SELECT r.unit_id, r.count_id, r.result FROM inventory_count_unit_results r JOIN inventory_units u ON u.id = r.unit_id WHERE u.item_id = ?', [$item['id']]) as $r) {
            $unitHistory[(int)$r['count_id']][(int)$r['unit_id']] = $r['result'];
        }
    }
    $stock = stock_for(stock_summary([(int)$item['id']]), (int)$item['id']);
    $checkouts = db_all('SELECT c.*, u.management_no FROM item_checkouts c LEFT JOIN inventory_units u ON u.id = c.unit_id WHERE c.item_id = ? ORDER BY c.checked_out_at DESC LIMIT 50', [$item['id']]);
    $latestCount = latest_confirmed_count();
    $m = item_masters();
    $lookup = [
        'location' => db_val('SELECT name FROM locations WHERE id = ?', [$item['location_id']]),
        'category' => db_val('SELECT name FROM categories WHERE id = ?', [$item['category_id']]),
        'customer' => db_val('SELECT name FROM customers WHERE id = ?', [$item['customer_id']]),
    ];
    render('items/detail', ['title' => '品目詳細', 'item' => $item, 'units' => $units, 'history' => $history, 'unitHistory' => $unitHistory, 'lookup' => $lookup, 'stock' => $stock, 'checkouts' => $checkouts, 'latestCount' => $latestCount] + $m);
}

// ---------------------------------------------------------------- 登録・編集フォーム
if ($page === 'item' && ($action === 'new' || $action === 'edit')) {
    $item = $action === 'edit' ? item_load((int)input_int('id', $_GET)) : [
        'id' => null, 'item_code' => '(自動採番)', 'item_name' => '', 'management_type' => 'quantity', 'category_id' => null,
        'condition_code' => null, 'stock_type' => 'internal', 'customer_id' => null, 'manufacturer' => '', 'model_number' => '',
        'serial_number' => '', 'network_info' => '', 'location_id' => null, 'notes' => '', 'is_active' => 1, 'sort_order' => null,
    ];
    render('items/form', ['title' => $action === 'edit' ? '品目編集' : '品目登録', 'item' => $item, 'errors' => []] + item_masters());
}

// ---------------------------------------------------------------- 保存
if ($page === 'item' && $action === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = input_int('id');
    $existing = $id ? item_load($id) : null;
    $v = [
        'item_name'       => input_str('item_name', null, 200),
        'management_type' => input_str('management_type', null, 20),
        'category_id'     => input_int('category_id'),
        'condition_code'  => input_str('condition_code', null, 20),
        'stock_type'      => input_str('stock_type', null, 20),
        'customer_id'     => input_int('customer_id'),
        'manufacturer'    => input_str('manufacturer', null, 100),
        'model_number'    => input_str('model_number', null, 100),
        'serial_number'   => input_str('serial_number', null, 100),
        'network_info'    => input_str('network_info', null, 255),
        'location_id'     => input_int('location_id'),
        'notes'           => input_str('notes', null, 5000),
        'sort_order'      => input_int('sort_order'),
    ];
    $errors = [];
    if ($v['item_name'] === null) {
        $errors[] = '品名は必須です。';
    }
    if (!isset(management_type_options()[$v['management_type']])) {
        $errors[] = '管理方法が不正です。';
    }
    if (!isset(stock_type_options()[$v['stock_type']])) {
        $errors[] = '在庫区分が不正です。';
    }
    if ($v['condition_code'] !== null && !db_val('SELECT 1 FROM conditions WHERE code = ?', [$v['condition_code']])) {
        $errors[] = '状態が不正です。';
    }
    foreach (['category_id' => 'categories', 'location_id' => 'locations', 'customer_id' => 'customers'] as $k => $tbl) {
        if ($v[$k] !== null && !db_val("SELECT 1 FROM $tbl WHERE id = ?", [$v[$k]])) {
            $errors[] = '選択したマスタ値が存在しません(' . $k . ')。';
        }
    }
    if ($errors) {
        $item = array_merge($existing ?? ['id' => null, 'item_code' => '(自動採番)', 'is_active' => 1], $v, ['id' => $id]);
        render('items/form', ['title' => $id ? '品目編集' : '品目登録', 'item' => $item, 'errors' => $errors] + item_masters());
        exit;
    }
    if ($existing) {
        db_exec('UPDATE inventory_items SET item_name=?, management_type=?, category_id=?, condition_code=?, stock_type=?, customer_id=?,
                    manufacturer=?, model_number=?, serial_number=?, network_info=?, location_id=?, notes=?, sort_order=?, updated_by=?
                 WHERE id=?',
            [$v['item_name'], $v['management_type'], $v['category_id'], $v['condition_code'], $v['stock_type'], $v['customer_id'],
             $v['manufacturer'], $v['model_number'], $v['serial_number'], $v['network_info'], $v['location_id'], $v['notes'],
             $v['sort_order'] ?? (int)$existing['sort_order'], actor_name(), $id]);
        flash_set('success', '品目を更新しました。');
        redirect('item', ['id' => $id]);
    }
    $pdo = db();
    $pdo->beginTransaction();
    $sort = $v['sort_order'] ?? ((int)db_val('SELECT COALESCE(MAX(sort_order),0) FROM inventory_items') + 1);
    $newId = db_insert('INSERT INTO inventory_items (item_code, item_name, management_type, category_id, condition_code, stock_type, customer_id,
                            manufacturer, model_number, serial_number, network_info, location_id, notes, is_active, sort_order, created_by, updated_by)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?)',
        ['TMP', $v['item_name'], $v['management_type'], $v['category_id'], $v['condition_code'], $v['stock_type'], $v['customer_id'],
         $v['manufacturer'], $v['model_number'], $v['serial_number'], $v['network_info'], $v['location_id'], $v['notes'], $sort,
         actor_name(), actor_name()]);
    db_exec('UPDATE inventory_items SET item_code = ? WHERE id = ?', [sprintf('ITEM-%06d', $newId), $newId]);
    $pdo->commit();
    flash_set('success', '品目を登録しました(品目コード ' . sprintf('ITEM-%06d', $newId) . ')。');
    redirect('item', ['id' => $newId]);
}

// ---------------------------------------------------------------- 無効化 / 再有効化
if ($page === 'item' && $action === 'toggle_active' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_perm('item.deactivate');
    $item = item_load((int)input_int('id'));
    $to = (int)$item['is_active'] === 1 ? 0 : 1;
    db_exec('UPDATE inventory_items SET is_active = ?, updated_by = ? WHERE id = ?', [$to, actor_name(), $item['id']]);
    flash_set('success', $to ? '品目を再有効化しました。' : '品目を無効にしました(棚卸履歴は残ります)。');
    redirect('item', ['id' => $item['id']]);
}

http_response_code(404);
render('error', ['title' => 'ページが見つかりません', 'message' => '不正な操作です。']);
