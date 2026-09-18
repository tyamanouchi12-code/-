<?php
// 棚卸入力 / 結果表示

$count = db_row('SELECT * FROM inventory_counts WHERE id = ?', [(int)input_int('id', $_GET) ?: (int)input_int('id')]);
if (!$count) {
    http_response_code(404);
    render('error', ['title' => '棚卸が見つかりません', 'message' => '指定された棚卸は存在しません。']);
    exit;
}
$editable = $count['status'] === 'in_progress';

// 前回棚卸(この棚卸より基準日が前の、直近の確定済み棚卸)
$prevCount = db_row("SELECT id, count_name, base_date FROM inventory_counts
                     WHERE status = 'confirmed' AND id <> ? AND (base_date < ? OR (base_date = ? AND id < ?))
                     ORDER BY base_date DESC, id DESC LIMIT 1", [$count['id'], $count['base_date'], $count['base_date'], $count['id']]);
$prevQty = [];
$prevUnit = [];
if ($prevCount) {
    foreach (db_all('SELECT item_id, count_quantity FROM inventory_count_details WHERE count_id = ?', [$prevCount['id']]) as $r) {
        $prevQty[(int)$r['item_id']] = $r['count_quantity'];
    }
    foreach (db_all('SELECT unit_id, result FROM inventory_count_unit_results WHERE count_id = ?', [$prevCount['id']]) as $r) {
        $prevUnit[(int)$r['unit_id']] = $r['result'];
    }
}

// 対象品目: 有効な品目すべて + この棚卸に明細がある無効品目(履歴表示のため)
$items = db_all('SELECT i.*, l.name AS location_name, c.name AS category_name,
                        d.id AS detail_id, d.count_quantity, d.confirm_status, d.notes AS detail_notes, d.counted_by, d.counted_at, d.location_id_at_count
                 FROM inventory_items i
                 LEFT JOIN locations l ON l.id = i.location_id
                 LEFT JOIN categories c ON c.id = i.category_id
                 LEFT JOIN inventory_count_details d ON d.count_id = ? AND d.item_id = i.id
                 WHERE i.is_active = 1 OR d.id IS NOT NULL
                 ORDER BY i.sort_order, i.id', [$count['id']]);
$unitsByItem = [];
foreach (db_all('SELECT u.*, r.result FROM inventory_units u
                 LEFT JOIN inventory_count_unit_results r ON r.count_id = ? AND r.unit_id = u.id
                 WHERE (u.is_active = 1 AND u.status <> \'disposed\') OR r.id IS NOT NULL
                 ORDER BY u.id', [$count['id']]) as $u) {
    $unitsByItem[(int)$u['item_id']][] = $u;
}
$locations = db_all('SELECT * FROM locations WHERE is_active = 1 ORDER BY sort_order, id');

// ---------------------------------------------------------------- 保存
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'save') {
    if (!$editable) {
        flash_set('error', 'この棚卸は「棚卸中」ではないため入力できません。');
        redirect('count_entry', ['id' => $count['id']]);
    }
    $qtyIn = $_POST['qty'] ?? [];
    $statusIn = $_POST['confirm_status'] ?? [];
    $notesIn = $_POST['dnotes'] ?? [];
    $unitIn = $_POST['unit'] ?? [];
    $touchedIn = $_POST['touched'] ?? [];   // 画面に表示されていた品目ID(絞り込み表示中は一部のみ)
    $pdo = db();
    $pdo->beginTransaction();
    $saved = 0;
    foreach ($items as $it) {
        $iid = (int)$it['id'];
        if (!isset($touchedIn[$iid])) {
            continue;
        }
        $isUnit = $it['management_type'] === 'unit' && !empty($unitsByItem[$iid]);
        $qty = null;
        if ($isUnit) {
            $present = 0;
            $anyChecked = false;
            foreach ($unitsByItem[$iid] as $u) {
                $uid = (int)$u['id'];
                $res = $unitIn[$uid] ?? 'unchecked';
                if (!in_array($res, ['unchecked', 'present', 'absent'], true)) {
                    $res = 'unchecked';
                }
                if ($res !== 'unchecked') {
                    $anyChecked = true;
                }
                if ($res === 'present') {
                    $present++;
                }
                $exists = db_val('SELECT id FROM inventory_count_unit_results WHERE count_id = ? AND unit_id = ?', [$count['id'], $uid]);
                if ($exists) {
                    db_exec('UPDATE inventory_count_unit_results SET result = ?, updated_by = ? WHERE id = ?', [$res, actor_name(), $exists]);
                } elseif ($res !== 'unchecked') {
                    db_exec('INSERT INTO inventory_count_unit_results (count_id, unit_id, result, created_by, updated_by) VALUES (?, ?, ?, ?, ?)',
                        [$count['id'], $uid, $res, actor_name(), actor_name()]);
                }
            }
            $qty = $anyChecked ? $present : null;
        } else {
            $raw = isset($qtyIn[$iid]) && is_string($qtyIn[$iid]) ? trim(mb_convert_kana($qtyIn[$iid], 'n')) : '';
            if ($raw !== '') {
                if (!preg_match('/^\d{1,9}$/', $raw)) {
                    $pdo->rollBack();
                    flash_set('error', '棚卸数は 0 以上の整数で入力してください(' . ($it['item_code']) . ' ' . $it['item_name'] . ')。');
                    redirect('count_entry', ['id' => $count['id']]);
                }
                $qty = (int)$raw;
            }
        }
        $cs = $statusIn[$iid] ?? null;
        if (!is_string($cs) || !isset(confirm_status_options()[$cs])) {
            $cs = $qty === null ? 'unconfirmed' : 'confirmed';
        }
        $dn = isset($notesIn[$iid]) && is_string($notesIn[$iid]) ? trim($notesIn[$iid]) : '';
        $dn = $dn === '' ? null : mb_substr($dn, 0, 5000);

        if ($it['detail_id']) {
            $changed = ((string)$it['count_quantity'] !== (string)$qty) || $it['confirm_status'] !== $cs || (string)$it['detail_notes'] !== (string)$dn;
            db_exec('UPDATE inventory_count_details SET count_quantity = ?, confirm_status = ?, notes = ?, location_id_at_count = ?,
                        counted_by = CASE WHEN ? THEN ? ELSE counted_by END, counted_at = CASE WHEN ? THEN NOW() ELSE counted_at END, updated_by = ?
                     WHERE id = ?',
                [$qty, $cs, $dn, $it['location_id'], $changed ? 1 : 0, actor_name(), $changed ? 1 : 0, actor_name(), $it['detail_id']]);
            $saved += $changed ? 1 : 0;
        } elseif ($qty !== null || $cs !== 'unconfirmed' || $dn !== null) {
            db_exec('INSERT INTO inventory_count_details (count_id, item_id, count_quantity, confirm_status, location_id_at_count, counted_by, counted_at, notes, created_by, updated_by)
                     VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?)',
                [$count['id'], $iid, $qty, $cs, $it['location_id'], actor_name(), $dn, actor_name(), actor_name()]);
            $saved++;
        }
    }
    $pdo->commit();
    flash_set('success', '保存しました(変更 ' . $saved . ' 件)。');
    $back = ['id' => $count['id']];
    if (input_int('location', $_GET) !== null) {
        $back['location'] = input_int('location', $_GET);
    }
    redirect('count_entry', $back);
}

// ---------------------------------------------------------------- 表示
$locFilter = input_int('location', $_GET);
$showLoc = input_str('loc', $_GET);
if ($locFilter !== null) {
    $items = array_values(array_filter($items, fn($it) => (int)$it['location_id'] === $locFilter || ($locFilter === 0 && $it['location_id'] === null)));
}
$summary = ['entered' => 0, 'total' => 0, 'needs_check' => 0, 'diff' => 0];
foreach ($items as &$it) {
    $iid = (int)$it['id'];
    $it['units'] = $unitsByItem[$iid] ?? [];
    $it['prev_quantity'] = $prevQty[$iid] ?? null;
    $it['diff'] = ($it['prev_quantity'] === null || $it['count_quantity'] === null) ? null : ((int)$it['count_quantity'] - (int)$it['prev_quantity']);
    if ($it['count_quantity'] !== null) {
        $summary['entered']++;
        $summary['total'] += (int)$it['count_quantity'];
    }
    if ($it['confirm_status'] === 'needs_check') {
        $summary['needs_check']++;
    }
    if ($it['diff'] !== null && $it['diff'] !== 0) {
        $summary['diff']++;
    }
}
unset($it);

render('counts/entry', [
    'title' => ($editable ? '棚卸入力' : '棚卸結果') . ' ' . $count['count_name'],
    'count' => $count, 'editable' => $editable, 'items' => $items, 'prevCount' => $prevCount, 'prevUnit' => $prevUnit,
    'locations' => $locations, 'locFilter' => $locFilter, 'summary' => $summary,
]);
