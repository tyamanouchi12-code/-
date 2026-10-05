<?php
// 棚卸明細の再集計など、複数の画面から使う在庫まわりの処理

/** カテゴリの明細を品名結果から再集計して保存 */
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

/** 品名(内訳)を別のカテゴリへ移す。持ち出し記録と、この品名が含まれる全棚卸のカテゴリ明細を付け替える */
function move_unit_to_item(int $unitId, int $fromItemId, int $toItemId): void
{
    if ($fromItemId === $toItemId) {
        return;
    }
    db_exec('UPDATE inventory_units SET item_id = ?, updated_by = ? WHERE id = ?', [$toItemId, actor_name(), $unitId]);
    db_exec('UPDATE item_checkouts SET item_id = ? WHERE unit_id = ?', [$toItemId, $unitId]);
    $fromLoc = db_val('SELECT location_id FROM inventory_items WHERE id = ?', [$fromItemId]);
    $toLoc = db_val('SELECT location_id FROM inventory_items WHERE id = ?', [$toItemId]);
    foreach (db_all('SELECT DISTINCT count_id FROM inventory_count_unit_results WHERE unit_id = ?', [$unitId]) as $r) {
        recompute_item_detail((int)$r['count_id'], $fromItemId, $fromLoc === null ? null : (int)$fromLoc);
        recompute_item_detail((int)$r['count_id'], $toItemId, $toLoc === null ? null : (int)$toLoc);
    }
}

/** 次の管理No(重複しない連番) */
function next_management_no(): int
{
    return (int)db_val('SELECT COALESCE(MAX(management_no), 0) + 1 FROM inventory_units');
}

/** 数え方の選択肢 */
function count_mode_options(): array
{
    return ['quantity' => '個数で数える', 'single' => '1台ずつ(有/無)'];
}

/** 1台ずつ(有/無)で数える品名か */
function unit_is_single(array $u): bool
{
    return ($u['count_mode'] ?? 'quantity') === 'single';
}
