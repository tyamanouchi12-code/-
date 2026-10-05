<?php
// 棚卸入力 / 結果表示(品名ごとに数量を入力し、カテゴリの合計は自動集計)

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

// 対象カテゴリ: 有効なカテゴリ + この棚卸に明細があるカテゴリ
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
// 対象品名: 有効な品名 + この棚卸に結果がある品名
$unitsByItem = [];
$unitById = [];
foreach (db_all("SELECT u.*, COALESCE(l2.name, l1.name) AS location_name, COALESCE(u.location_id, i.location_id) AS eff_location_id,
                        r.id AS result_id, r.counted_quantity, r.result, r.is_checked, r.notes AS result_notes, r.updated_by AS result_by, r.updated_at AS result_at
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


// ---------------------------------------------------------------- 保存(全体 / 品名1行)
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
    $checkedIn = $_POST['checked'] ?? [];
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
        if (unit_is_single($u)) {
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
        $checked = isset($checkedIn[$uid]) ? 1 : 0;
        $changed = ((string)$u['counted_quantity'] !== (string)$qty) || (string)$u['result_notes'] !== (string)$notes || ($u['result'] ?? 'unchecked') !== $res
                   || (int)($u['is_checked'] ?? 0) !== $checked;
        if ($u['result_id']) {
            if ($changed) {
                db_exec('UPDATE inventory_count_unit_results SET result = ?, counted_quantity = ?, is_checked = ?, notes = ?, updated_by = ?, updated_at = NOW() WHERE id = ?',
                    [$res, $qty, $checked, $notes, actor_name(), $u['result_id']]);
            }
        } elseif ($qty !== null || $notes !== null || $checked) {
            db_exec('INSERT INTO inventory_count_unit_results (count_id, unit_id, result, counted_quantity, is_checked, notes, created_by, updated_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [$count['id'], $uid, $res, $qty, $checked, $notes, actor_name(), actor_name()]);
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
        $respond(false, '対象の品名が見つかりません。');
    }
    // 「棚卸を締める」: 保存に続けて全体確定
    if (($_POST['close'] ?? '') === '1') {
        if (!can('count.confirm')) {
            $respond(false, '保存しました(変更 ' . $saved . ' 件)。棚卸を締める権限がないため、締めは行っていません。');
        }
        $unentered = (int)db_val("SELECT COUNT(*) FROM inventory_units u JOIN inventory_items i ON i.id = u.item_id
                                   WHERE u.is_active = 1 AND u.status <> 'disposed' AND i.is_active = 1
                                     AND NOT EXISTS (SELECT 1 FROM inventory_count_unit_results r WHERE r.count_id = ? AND r.unit_id = u.id AND r.counted_quantity IS NOT NULL)", [$count['id']]);
        db_exec("UPDATE inventory_counts SET status = 'confirmed', end_date = COALESCE(end_date, CURDATE()), confirmed_by = ?, confirmed_at = NOW() WHERE id = ?", [actor_name(), $count['id']]);
        $respond(true, '入力内容を登録し、棚卸を締めました(変更 ' . $saved . ' 件)。' . ($unentered > 0 ? "数量未入力の品名が {$unentered} 件あります。未入力の品名は履歴に含まれません。" : ''));
    }
    $respond(true, $saveUnit !== null ? '保存しました。' : '途中保存しました(変更 ' . $saved . ' 件)。', ['units' => $savedUnits, 'items' => $itemsOut]);
}

// ---------------------------------------------------------------- 表示
$locFilter = input_int('location', $_GET);
$itemFilter = input_int('item', $_GET);

// カテゴリ別集計(絞り込み前の全品名で計算)
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
$summary = ['entered' => 0, 'units' => 0, 'total' => 0, 'diff' => 0, 'checked' => 0];
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
        if ((int)($u['is_checked'] ?? 0) === 1) {
            $summary['checked']++;
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
