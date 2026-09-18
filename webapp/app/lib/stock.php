<?php
// 現在庫と持ち出し状況の計算
//
// 現在庫 = 最新の確定済み棚卸の数量
//          − その棚卸の確定後に持ち出された数量
//          + その棚卸の確定後に戻された数量
// (確定済み棚卸がない品目は「–」。持ち出し中の件数はいつでも表示)

/** 最新の確定済み棚卸(なければ null) */
function latest_confirmed_count(): ?array
{
    static $c = false;
    if ($c === false) {
        $c = db_row("SELECT id, count_name, base_date, confirmed_at FROM inventory_counts WHERE status = 'confirmed' ORDER BY base_date DESC, id DESC LIMIT 1");
    }
    return $c;
}

/**
 * 品目ID → ['latest_qty'=>?int, 'out_after'=>int, 'in_after'=>int, 'current'=>?int, 'open_qty'=>int, 'open'=>[持ち出し中の行...]]
 * $itemIds が null なら全品目
 */
function stock_summary(?array $itemIds = null): array
{
    $c = latest_confirmed_count();
    $since = $c ? ($c['confirmed_at'] ?: ($c['base_date'] . ' 23:59:59')) : null;

    $where = '';
    $params = [];
    if ($itemIds !== null) {
        if (!$itemIds) {
            return [];
        }
        $where = ' WHERE item_id IN (' . implode(',', array_fill(0, count($itemIds), '?')) . ')';
        $params = array_map('intval', $itemIds);
    }

    $result = [];
    if ($c) {
        foreach (db_all('SELECT item_id, count_quantity FROM inventory_count_details WHERE count_id = ?' . ($itemIds !== null ? ' AND item_id IN (' . implode(',', array_fill(0, count($itemIds), '?')) . ')' : ''),
                        array_merge([$c['id']], $params)) as $r) {
            $result[(int)$r['item_id']]['latest_qty'] = $r['count_quantity'] === null ? null : (int)$r['count_quantity'];
        }
    }
    if ($since !== null) {
        foreach (db_all("SELECT item_id, SUM(CASE WHEN checked_out_at > ? THEN quantity ELSE 0 END) AS out_after,
                                        SUM(CASE WHEN returned_at IS NOT NULL AND returned_at > ? THEN quantity ELSE 0 END) AS in_after
                         FROM item_checkouts" . $where . ' GROUP BY item_id', array_merge([$since, $since], $params)) as $r) {
            $result[(int)$r['item_id']]['out_after'] = (int)$r['out_after'];
            $result[(int)$r['item_id']]['in_after'] = (int)$r['in_after'];
        }
    }
    foreach (db_all('SELECT c.*, u.management_no FROM item_checkouts c LEFT JOIN inventory_units u ON u.id = c.unit_id'
                    . ($where ? str_replace('item_id', 'c.item_id', $where) . ' AND' : ' WHERE') . ' c.returned_at IS NULL ORDER BY c.checked_out_at DESC', $params) as $r) {
        $result[(int)$r['item_id']]['open'][] = $r;
        $result[(int)$r['item_id']]['open_qty'] = ($result[(int)$r['item_id']]['open_qty'] ?? 0) + (int)$r['quantity'];
    }
    foreach ($result as $iid => &$s) {
        $s += ['latest_qty' => null, 'out_after' => 0, 'in_after' => 0, 'open_qty' => 0, 'open' => []];
        $s['current'] = $s['latest_qty'] === null ? null : $s['latest_qty'] - $s['out_after'] + $s['in_after'];
    }
    unset($s);
    return $result;
}

function stock_for(array $summary, int $itemId): array
{
    return $summary[$itemId] ?? ['latest_qty' => null, 'out_after' => 0, 'in_after' => 0, 'current' => null, 'open_qty' => 0, 'open' => []];
}

/** 持ち出し中の表示用文字列(名前 と 日時) */
function checkout_label(array $co): string
{
    $who = $co['checked_out_by_name'] ?: '(名前なし)';
    $s = $who . ' ' . date('n/j H:i', strtotime($co['checked_out_at']));
    if (!empty($co['management_no'])) {
        $s = $co['management_no'] . ' → ' . $s;
    } elseif ((int)$co['quantity'] !== 1) {
        $s .= ' ×' . (int)$co['quantity'];
    }
    return $s;
}
