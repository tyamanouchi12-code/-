<?php
// 棚卸入力 / 結果表示(内訳ごとに数量を入力し、品目の合計は自動集計)

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
$prevItemQty = [];
$prevUnitQty = [];
if ($prevCount) {
    foreach (db_all('SELECT item_id, count_quantity FROM inventory_count_details WHERE count_id = ?', [$prevCount['id']]) as $r) {
        $prevItemQty[(int)$r['item_id']] = $r['count_quantity'];
    }
    foreach (db_all('SELECT unit_id, counted_quantity FROM inventory_count_unit_results WHERE count_id = ?', [$prevCount['id']]) as $r) {
        $prevUnitQty[(int)$r['unit_id']] = $r['counted_quantity'];
    }
}

// 対象品目: 有効な品目 + この棚卸に明細がある品目
$items = db_all('SELECT i.*, l.name AS location_name, d.id AS detail_id, d.count_quantity, d.counted_by, d.counted_at
                 FROM inventory_items i
                 LEFT JOIN locations l ON l.id = i.location_id
                 LEFT JOIN inventory_count_details d ON d.count_id = ? AND d.item_id = i.id
                 WHERE i.is_active = 1 OR d.id IS NOT NULL
                 ORDER BY i.sort_order, i.id', [$count['id']]);
$itemById = [];
foreach ($items as $it) {
    $itemById[(int)$it['id']] = $it;
}
// 対象内訳: 有効な内訳 + この棚卸に結果がある内訳
$unitsByItem = [];
$unitById = [];
foreach (db_all("SELECT u.*, COALESCE(l2.name, l1.name) AS location_name, COALESCE(u.location_id, i.location_id) AS eff_location_id,
                        r.id AS result_id, r.counted_quantity, r.result, r.notes AS result_notes, r.updated_by AS result_by, r.updated_at AS result_at
                 FROM inventory_units u
                 JOIN inventory_items i ON i.id = u.item_id
                 LEFT JOIN locations l1 ON l1.id = i.location_id LEFT JOIN locations l2 ON l2.id = u.location_id
                 LEFT JOIN inventory_count_unit_results r ON r.count_id = ? AND r.unit_id = u.id
                 WHERE (u.is_active = 1 AND u.status <> 'disposed') OR r.id IS NOT NULL
                 ORDER BY u.sort_order, u.id", [$count['id']]) as $u) {
    if (!isset($itemById[(int)$u['item_id']])) {
        continue;
    }
    $unitsByItem[(int)$u['item_id']][] = $u;
    $unitById[(int)$u['id']] = $u;
}

/** 品目の明細を内訳結果から再集計して保存 */
function recompute_item_detail(int $countId, int $itemId, ?int $locationId): array
{
    $agg = db_row('SELECT SUM(r.counted_quantity IS NOT NULL) AS n, SUM(COALESCE(r.counted_quantity,0)) AS total
                   FROM inventory_count_unit_results r JOIN inventory_units u ON u.id = r.unit_id WHERE r.count_id = ? AND u.item_id = ?', [$countId, $itemId]);
    $qty = (int)($agg['n'] ?? 0) > 0 ? (int)$agg['total'] : null;
    $cs = $qty === null ? 'unconfirmed' : 'confirmed';
    $existing = db_row('SELECT id, count_quantity FROM inventory_count_details WHERE count_id = ? AND item_id = ?', [$countId, $itemId]);
    if ($existing) {
        $changed = (string)$existing['count_quantity'] !== (string)$qty;
        db_exec('UPDATE inventory_count_details SET count_quantity = ?, confirm_status = ?, location_id_at_count = ?, updated_by = ?,
                    counted_by = ?, counted_at = NOW() WHERE id = ?', [$qty, $cs, $locationId, actor_name(), actor_name(), $existing['id']]);
    } else {
        db_exec('INSERT INTO inventory_count_details (count_id, item_id, count_quantity, confirm_status, location_id_at_count, counted_by, counted_at, created_by, updated_by)
                 VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, ?)', [$countId, $itemId, $qty, $cs, $locationId, actor_name(), actor_name(), actor_name()]);
    }
    return ['qty' => $qty, 'counted_by' => actor_name(), 'counted_at' => fmt_datetime(now_str())];
}

// ---------------------------------------------------------------- 保存(全体 / 内訳1行)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'save') {
    $isAjax = ($_POST['ajax'] ?? '') === '1';
    $saveUnit = input_int('save_unit');
    $respond = function (bool $ok, string $message, array $extra = []) use ($isAjax, $count) {
        if ($isAjax) {
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['ok' => $ok, 'message' => $message] + $extra, JSON_UNESCAPED_UNICODE);
            exit;
        }
        flash_set($ok ? 'success' : 'error', $message);
        $back = ['id' => $count['id']];
        foreach (['location', 'item'] as $k) {
            if (input_int($k, $_GET) !== null) {
                $back[$k] = input_int($k, $_GET);
            }
        }
        redirect('count_entry', $back);
    };
    if (!$editable) {
        $respond(false, 'この棚卸は「棚卸中」ではないため入力できません。');
    }
    $qtyIn = $_POST['qty'] ?? [];
    $unitIn = $_POST['unit'] ?? [];
    $notesIn = $_POST['unotes'] ?? [];
    $touchedIn = $_POST['touched'] ?? [];
    $pdo = db();
    $pdo->beginTransaction();
    $saved = 0;
    $savedUnits = [];
    $affectedItems = [];
    foreach ($unitById as $uid => $u) {
        if ($saveUnit !== null ? $uid !== $saveUnit : !isset($touchedIn[$uid])) {
            continue;
        }
        if ($u['management_no'] !== null && $u['management_no'] !== '') {
            $res = $unitIn[$uid] ?? 'unchecked';
            if (!in_array($res, ['unchecked', 'present', 'absent'], true)) {
                $res = 'unchecked';
            }
            $qty = $res === 'present' ? 1 : ($res === 'absent' ? 0 : null);
        } else {
            $raw = isset($qtyIn[$uid]) && is_string($qtyIn[$uid]) ? trim(mb_convert_kana($qtyIn[$uid], 'n')) : '';
            $qty = null;
            if ($raw !== '') {
                if (!preg_match('/^\d{1,9}$/', $raw)) {
                    $pdo->rollBack();
                    $respond(false, '棚卸数は 0 以上の整数で入力してください(' . unit_label($u) . ')。');
                }
                $qty = (int)$raw;
            }
            $res = $qty === null ? 'unchecked' : ($qty > 0 ? 'present' : 'absent');
        }
        $notes = isset($notesIn[$uid]) && is_string($notesIn[$uid]) ? trim($notesIn[$uid]) : '';
        $notes = $notes === '' ? null : mb_substr($notes, 0, 2000);
        $changed = ((string)$u['counted_quantity'] !== (string)$qty) || (string)$u['result_notes'] !== (string)$notes || ($u['result'] ?? 'unchecked') !== $res;
        if ($u['result_id']) {
            if ($changed) {
                db_exec('UPDATE inventory_count_unit_results SET result = ?, counted_quantity = ?, notes = ?, updated_by = ?, updated_at = NOW() WHERE id = ?',
                    [$res, $qty, $notes, actor_name(), $u['result_id']]);
            }
        } elseif ($qty !== null || $notes !== null) {
            db_exec('INSERT INTO inventory_count_unit_results (count_id, unit_id, result, counted_quantity, notes, created_by, updated_by) VALUES (?, ?, ?, ?, ?, ?, ?)',
                [$count['id'], $uid, $res, $qty, $notes, actor_name(), actor_name()]);
            $changed = true;
        }
        if ($changed) {
            $saved++;
            $affectedItems[(int)$u['item_id']] = true;
        }
        $savedUnits[$uid] = ['qty' => $qty, 'by' => $changed ? actor_name() : $u['result_by'], 'at' => $changed ? fmt_datetime(now_str()) : fmt_datetime($u['result_at'])];
    }
    $itemsOut = [];
    foreach (array_keys($affectedItems) as $iid) {
        $itemsOut[$iid] = recompute_item_detail((int)$count['id'], $iid, $itemById[$iid]['location_id'] === null ? null : (int)$itemById[$iid]['location_id']);
    }
    $pdo->commit();
    if ($saveUnit !== null && !isset($savedUnits[$saveUnit])) {
        $respond(false, '対象の内訳が見つかりません。');
    }
    $respond(true, $saveUnit !== null ? '保存しました。' : '保存しました(変更 ' . $saved . ' 件)。', ['units' => $savedUnits, 'items' => $itemsOut]);
}

// ---------------------------------------------------------------- 表示
$locFilter = input_int('location', $_GET);
$itemFilter = input_int('item', $_GET);

// 品目別集計(絞り込み前の全内訳で計算)
$byItem = [];
foreach ($items as $it) {
    $iid = (int)$it['id'];
    $g = ['item_id' => $iid, 'name' => $it['item_name'], 'unit_count' => 0, 'entered' => 0, 'total' => 0, 'prev_total' => 0, 'prev_count' => 0, 'diff' => 0];
    foreach ($unitsByItem[$iid] ?? [] as $u) {
        $prev = $prevUnitQty[(int)$u['id']] ?? null;
        $g['unit_count']++;
        if ($u['counted_quantity'] !== null) {
            $g['entered']++;
            $g['total'] += (int)$u['counted_quantity'];
        }
        if ($prev !== null) {
            $g['prev_total'] += (int)$prev;
            $g['prev_count']++;
        }
        if ($prev !== null && $u['counted_quantity'] !== null) {
            $g['diff'] += (int)$u['counted_quantity'] - (int)$prev;
        }
    }
    if ($g['unit_count'] > 0 || $it['detail_id']) {
        $byItem[$iid] = $g;
    }
}

// 絞り込み(表示用)
$rows = [];   // [item, units]
$summary = ['entered' => 0, 'units' => 0, 'total' => 0, 'diff' => 0];
foreach ($items as $it) {
    $iid = (int)$it['id'];
    if ($itemFilter !== null && $iid !== $itemFilter) {
        continue;
    }
    $units = $unitsByItem[$iid] ?? [];
    if ($locFilter !== null) {
        $units = array_values(array_filter($units, fn($u) => (int)$u['eff_location_id'] === $locFilter || ($locFilter === 0 && $u['eff_location_id'] === null)));
    }
    if (!$units && $locFilter !== null) {
        continue;
    }
    foreach ($units as &$u) {
        $u['prev_quantity'] = $prevUnitQty[(int)$u['id']] ?? null;
        $u['diff'] = ($u['prev_quantity'] === null || $u['counted_quantity'] === null) ? null : ((int)$u['counted_quantity'] - (int)$u['prev_quantity']);
        $summary['units']++;
        if ($u['counted_quantity'] !== null) {
            $summary['entered']++;
            $summary['total'] += (int)$u['counted_quantity'];
        }
        if ($u['diff'] !== null && $u['diff'] !== 0) {
            $summary['diff']++;
        }
    }
    unset($u);
    $rows[] = ['item' => $it, 'units' => $units, 'prev_total' => $prevItemQty[$iid] ?? null];
}
$locations = db_all('SELECT * FROM locations WHERE is_active = 1 ORDER BY sort_order, id');

render('counts/entry', [
    'title' => ($editable ? '棚卸入力' : '棚卸結果') . ' ' . $count['count_name'],
    'count' => $count, 'editable' => $editable, 'rows' => $rows, 'prevCount' => $prevCount,
    'locations' => $locations, 'locFilter' => $locFilter, 'itemFilter' => $itemFilter, 'byItem' => $byItem, 'summary' => $summary, 'items' => $items,
]);
