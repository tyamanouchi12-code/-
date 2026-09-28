<?php
// 品目(=カテゴリ単位)の一覧 / 登録・編集 / 詳細 / 無効化
// 品目の下に「内訳」(inventory_units: 実物の種類・個体)がぶら下がる

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

/** 品目の内訳(有効のみ、または全部) */
function item_units(int $itemId, bool $activeOnly = true): array
{
    return db_all('SELECT u.*, l.name AS location_name, cu.name AS customer_name FROM inventory_units u
                   LEFT JOIN locations l ON l.id = u.location_id LEFT JOIN customers cu ON cu.id = u.customer_id
                   WHERE u.item_id = ?' . ($activeOnly ? ' AND u.is_active = 1' : '') . ' ORDER BY u.is_active DESC, u.sort_order, u.id', [$itemId]);
}

// ---------------------------------------------------------------- 一覧
if ($page === 'items') {
    $f = [
        'q'         => input_str('q', $_GET, 100),
        'location'  => input_int('location', $_GET),
        'condition' => input_str('condition', $_GET, 20),
        'active'    => input_str('active', $_GET, 5) ?? '1',
    ];
    $where = [];
    $params = [];
    if ($f['active'] === '1') { $where[] = 'i.is_active = 1'; }
    elseif ($f['active'] === '0') { $where[] = 'i.is_active = 0'; }
    $items = db_all('SELECT i.*, c.name AS category_name, l.name AS location_name FROM inventory_items i
                     LEFT JOIN categories c ON c.id = i.category_id LEFT JOIN locations l ON l.id = i.location_id'
                    . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY i.sort_order, i.id', $params);
    // 内訳(絞り込み条件は内訳に適用)
    $uw = ['u.is_active = 1'];
    $up = [];
    if ($f['q'] !== null) {
        $like = '%' . $f['q'] . '%';
        $uw[] = '(u.name LIKE ? OR u.management_no LIKE ? OR u.serial_number LIKE ? OR u.manufacturer LIKE ? OR u.model_number LIKE ? OR u.notes LIKE ? OR i.item_name LIKE ? OR i.item_code LIKE ?)';
        array_push($up, $like, $like, $like, $like, $like, $like, $like, $like);
    }
    if ($f['location'] !== null) { $uw[] = 'COALESCE(u.location_id, i.location_id) = ?'; $up[] = $f['location']; }
    if ($f['condition'] !== null) {
        if ($f['condition'] === '_none') { $uw[] = 'u.condition_code IS NULL'; }
        else { $uw[] = 'u.condition_code = ?'; $up[] = $f['condition']; }
    }
    $unitsByItem = [];
    foreach (db_all('SELECT u.*, COALESCE(l2.name, l1.name) AS location_name FROM inventory_units u JOIN inventory_items i ON i.id = u.item_id
                     LEFT JOIN locations l1 ON l1.id = i.location_id LEFT JOIN locations l2 ON l2.id = u.location_id
                     WHERE ' . implode(' AND ', $uw) . ' ORDER BY u.sort_order, u.id', $up) as $u) {
        $unitsByItem[(int)$u['item_id']][] = $u;
    }
    $filtering = $f['q'] !== null || $f['location'] !== null || $f['condition'] !== null;
    if ($filtering) {
        $items = array_values(array_filter($items, fn($i) => !empty($unitsByItem[(int)$i['id']])));
    }
    $stock = stock_summary(array_map(fn($r) => (int)$r['id'], $items));
    $allUnitIds = [];
    foreach ($unitsByItem as $list) { foreach ($list as $u) { $allUnitIds[] = (int)$u['id']; } }
    $unitStock = unit_stock_summary($allUnitIds);
    $latest = latest_confirmed_count();
    render('items/list', ['title' => '品目一覧', 'items' => $items, 'unitsByItem' => $unitsByItem, 'f' => $f, 'latest' => $latest,
                          'stock' => $stock, 'unitStock' => $unitStock] + item_masters());
}

// ---------------------------------------------------------------- 詳細
if ($page === 'item' && $action === '') {
    $item = item_load((int)input_int('id', $_GET));
    $units = item_units((int)$item['id'], false);
    $unitStock = unit_stock_summary(array_map(fn($u) => (int)$u['id'], $units));
    // 棚卸履歴(品目合計)
    $history = db_all('SELECT c.id AS count_id, c.count_name, c.base_date, c.status, d.count_quantity, d.counted_by, d.counted_at
                       FROM inventory_count_details d JOIN inventory_counts c ON c.id = d.count_id
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
    // 内訳ごとの棚卸結果 [count_id][unit_id] => counted_quantity
    $unitHistory = [];
    foreach (db_all('SELECT r.unit_id, r.count_id, r.counted_quantity, r.result FROM inventory_count_unit_results r JOIN inventory_units u ON u.id = r.unit_id WHERE u.item_id = ?', [$item['id']]) as $r) {
        $unitHistory[(int)$r['count_id']][(int)$r['unit_id']] = $r['counted_quantity'];
    }
    $stock = stock_for(stock_summary([(int)$item['id']]), (int)$item['id']);
    $checkouts = db_all('SELECT c.*, u.management_no, u.name AS unit_name FROM item_checkouts c LEFT JOIN inventory_units u ON u.id = c.unit_id WHERE c.item_id = ? ORDER BY c.checked_out_at DESC LIMIT 50', [$item['id']]);
    $lookup = [
        'location' => db_val('SELECT name FROM locations WHERE id = ?', [$item['location_id']]),
        'category' => db_val('SELECT name FROM categories WHERE id = ?', [$item['category_id']]),
    ];
    render('items/detail', ['title' => '品目詳細', 'item' => $item, 'units' => $units, 'unitStock' => $unitStock, 'history' => $history, 'unitHistory' => $unitHistory,
                            'lookup' => $lookup, 'stock' => $stock, 'checkouts' => $checkouts, 'latestCount' => latest_confirmed_count()] + item_masters());
}

// ---------------------------------------------------------------- 登録・編集フォーム
if ($page === 'item' && ($action === 'new' || $action === 'edit')) {
    $item = $action === 'edit' ? item_load((int)input_int('id', $_GET)) : [
        'id' => null, 'item_code' => '(自動採番)', 'item_name' => '', 'category_id' => null, 'stock_type' => 'internal',
        'location_id' => null, 'notes' => '', 'is_active' => 1, 'sort_order' => null,
    ];
    $units = $item['id'] ? item_units((int)$item['id']) : [];
    render('items/form', ['title' => $action === 'edit' ? '品目編集' : '品目登録', 'item' => $item, 'units' => $units, 'errors' => []] + item_masters());
}

/** フォームの内訳行を正規化して返す(空行は除外)。エラーがあれば $errors に追加 */
function item_units_from_post(array &$errors): array
{
    $rows = [];
    $in = $_POST['units'] ?? [];
    if (!is_array($in)) {
        return $rows;
    }
    $n = 0;
    foreach ($in as $r) {
        if (!is_array($r)) {
            continue;
        }
        $n++;
        $u = [
            'id'             => input_int('id', $r),
            'name'           => input_str('name', $r, 200),
            'condition_code' => input_str('condition_code', $r, 20),
            'management_no'  => input_str('management_no', $r, 50),
            'serial_number'  => input_str('serial_number', $r, 100),
            'ip_address'     => input_str('ip_address', $r, 100),
            'manufacturer'   => input_str('manufacturer', $r, 100),
            'model_number'   => input_str('model_number', $r, 100),
            'status'         => input_str('status', $r, 20) ?? 'in_stock',
            'location_id'    => input_int('location_id', $r),
            'notes'          => input_str('notes', $r, 500),
        ];
        $empty = $u['name'] === null && $u['management_no'] === null && $u['serial_number'] === null && $u['notes'] === null && $u['model_number'] === null;
        if ($empty && $u['id'] === null) {
            continue;   // 空行
        }
        if ($u['name'] === null) {
            $errors[] = "内訳の {$n} 行目: 内訳名(品名)を入力してください。";
        }
        if ($u['condition_code'] !== null && !db_val('SELECT 1 FROM conditions WHERE code = ?', [$u['condition_code']])) {
            $errors[] = "内訳の {$n} 行目: 状態が不正です。";
        }
        if (!isset(unit_status_options()[$u['status']])) {
            $errors[] = "内訳の {$n} 行目: 状態(在庫/貸出中…)が不正です。";
        }
        if ($u['location_id'] !== null && !db_val('SELECT 1 FROM locations WHERE id = ?', [$u['location_id']])) {
            $errors[] = "内訳の {$n} 行目: 保管場所が存在しません。";
        }
        $rows[] = $u;
    }
    return $rows;
}

/** 内訳行を保存(既存は更新、新規は追加)。追加件数を返す */
function item_units_save(int $itemId, array $rows): int
{
    $added = 0;
    $sort = (int)db_val('SELECT COALESCE(MAX(sort_order),0) FROM inventory_units WHERE item_id = ?', [$itemId]);
    foreach ($rows as $u) {
        if ($u['id'] !== null) {
            db_exec('UPDATE inventory_units SET name=?, condition_code=?, management_no=?, serial_number=?, ip_address=?, manufacturer=?, model_number=?, status=?, location_id=?, notes=?, updated_by=?
                     WHERE id=? AND item_id=?',
                [$u['name'], $u['condition_code'], $u['management_no'], $u['serial_number'], $u['ip_address'], $u['manufacturer'], $u['model_number'], $u['status'], $u['location_id'], $u['notes'], actor_name(), $u['id'], $itemId]);
        } else {
            $sort += 10;
            db_insert('INSERT INTO inventory_units (item_id, name, condition_code, management_no, serial_number, ip_address, manufacturer, model_number, status, location_id, notes, is_active, sort_order, created_by, updated_by)
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?)',
                [$itemId, $u['name'], $u['condition_code'], $u['management_no'], $u['serial_number'], $u['ip_address'], $u['manufacturer'], $u['model_number'], $u['status'], $u['location_id'], $u['notes'], $sort, actor_name(), actor_name()]);
            $added++;
        }
    }
    return $added;
}

// ---------------------------------------------------------------- 保存
if ($page === 'item' && $action === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = input_int('id');
    $existing = $id ? item_load($id) : null;
    $v = [
        'item_name'   => input_str('item_name', null, 200),
        'category_id' => input_int('category_id'),
        'stock_type'  => input_str('stock_type', null, 20),
        'location_id' => input_int('location_id'),
        'notes'       => input_str('notes', null, 5000),
        'sort_order'  => input_int('sort_order'),
    ];
    $errors = [];
    if ($v['category_id'] === null) {
        $errors[] = 'カテゴリは必須です。';
    } elseif (!db_val('SELECT 1 FROM categories WHERE id = ?', [$v['category_id']])) {
        $errors[] = '選択したカテゴリが存在しません。';
    }
    if ($v['item_name'] === null && $v['category_id'] !== null) {
        $v['item_name'] = db_val('SELECT name FROM categories WHERE id = ?', [$v['category_id']]);   // 品目名はカテゴリ名を既定にする
    }
    if (!isset(stock_type_options()[$v['stock_type']])) {
        $errors[] = '在庫区分が不正です。';
    }
    if ($v['location_id'] !== null && !db_val('SELECT 1 FROM locations WHERE id = ?', [$v['location_id']])) {
        $errors[] = '保管場所が存在しません。';
    }
    $unitRows = item_units_from_post($errors);
    if ($errors) {
        $item = array_merge($existing ?? ['id' => null, 'item_code' => '(自動採番)', 'is_active' => 1], $v, ['id' => $id]);
        render('items/form', ['title' => $id ? '品目編集' : '品目登録', 'item' => $item, 'units' => $unitRows, 'errors' => $errors] + item_masters());
        exit;
    }
    $pdo = db();
    $pdo->beginTransaction();
    if ($existing) {
        db_exec('UPDATE inventory_items SET item_name=?, category_id=?, stock_type=?, location_id=?, notes=?, sort_order=?, updated_by=? WHERE id=?',
            [$v['item_name'], $v['category_id'], $v['stock_type'], $v['location_id'], $v['notes'], $v['sort_order'] ?? (int)$existing['sort_order'], actor_name(), $id]);
        $added = item_units_save($id, $unitRows);
        $pdo->commit();
        flash_set('success', '品目を更新しました。' . ($added ? "(内訳を {$added} 件追加)" : ''));
        redirect('item', ['id' => $id]);
    }
    $sort = $v['sort_order'] ?? ((int)db_val('SELECT COALESCE(MAX(sort_order),0) FROM inventory_items') + 1);
    $newId = db_insert("INSERT INTO inventory_items (item_code, item_name, management_type, category_id, stock_type, location_id, notes, is_active, sort_order, created_by, updated_by)
                        VALUES ('TMP', ?, 'unit', ?, ?, ?, ?, 1, ?, ?, ?)",
        [$v['item_name'], $v['category_id'], $v['stock_type'], $v['location_id'], $v['notes'], $sort, actor_name(), actor_name()]);
    // 品目コードは既存の最大番号 + 1(内部IDとは別)
    $codeNo = (int)db_val("SELECT COALESCE(MAX(CAST(SUBSTRING(item_code, 6) AS UNSIGNED)), 0) FROM inventory_items WHERE item_code REGEXP '^ITEM-[0-9]+$'") + 1;
    $code = sprintf('ITEM-%06d', $codeNo);
    db_exec('UPDATE inventory_items SET item_code = ? WHERE id = ?', [$code, $newId]);
    $added = item_units_save($newId, $unitRows);
    $pdo->commit();
    flash_set('success', '品目を登録しました(品目コード ' . $code . ')。' . ($added ? "内訳を {$added} 件登録しました。" : ''));
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
