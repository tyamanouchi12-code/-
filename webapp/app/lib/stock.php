<?php
// 現在庫と持ち出し状況の計算(カテゴリ単位・品名単位)
//
// 現在庫 = 最新の確定済み棚卸の数量
//          − その棚卸の確定後に持ち出された数量
//          + その棚卸の確定後に戻された数量

/** 最新の確定済み棚卸(なければ null) */
function latest_confirmed_count(): ?array
{
    static $c = false;
    if ($c === false) {
        $c = db_row("SELECT id, count_name, base_date, confirmed_at FROM inventory_counts WHERE status = 'confirmed' ORDER BY base_date DESC, id DESC LIMIT 1");
    }
    return $c;
}

function stock_since(): ?string
{
    $c = latest_confirmed_count();
    return $c ? ($c['confirmed_at'] ?: ($c['base_date'] . ' 23:59:59')) : null;
}

function _stock_finalize(array &$result): void
{
    foreach ($result as $k => &$s) {
        $s += ['latest_qty' => null, 'out_after' => 0, 'in_after' => 0, 'open_qty' => 0, 'open' => []];
        $s['current'] = $s['latest_qty'] === null ? null : $s['latest_qty'] - $s['out_after'] + $s['in_after'];
    }
    unset($s);
}

/** カテゴリID → 在庫情報 */
function stock_summary(?array $itemIds = null): array
{
    $c = latest_confirmed_count();
    $since = stock_since();
    $where = '';
    $params = [];
    if ($itemIds !== null) {
        if (!$itemIds) {
            return [];
        }
        $where = ' item_id IN (' . implode(',', array_fill(0, count($itemIds), '?')) . ')';
        $params = array_map('intval', $itemIds);
    }
    $result = [];
    if ($c) {
        foreach (db_all('SELECT item_id, count_quantity FROM inventory_count_details WHERE count_id = ?' . ($where ? ' AND' . $where : ''), array_merge([$c['id']], $params)) as $r) {
            $result[(int)$r['item_id']]['latest_qty'] = $r['count_quantity'] === null ? null : (int)$r['count_quantity'];
        }
    }
    if ($since !== null) {
        foreach (db_all("SELECT item_id, SUM(CASE WHEN checked_out_at > ? THEN quantity ELSE 0 END) AS out_after,
                                SUM(CASE WHEN returned_at IS NOT NULL AND returned_at > ? THEN quantity ELSE 0 END) AS in_after
                         FROM item_checkouts" . ($where ? ' WHERE' . $where : '') . ' GROUP BY item_id', array_merge([$since, $since], $params)) as $r) {
            $result[(int)$r['item_id']]['out_after'] = (int)$r['out_after'];
            $result[(int)$r['item_id']]['in_after'] = (int)$r['in_after'];
        }
    }
    foreach (db_all('SELECT c.*, u.management_no, u.name AS unit_name FROM item_checkouts c LEFT JOIN inventory_units u ON u.id = c.unit_id WHERE c.returned_at IS NULL'
                    . ($where ? ' AND c.' . ltrim($where) : '') . ' ORDER BY c.checked_out_at DESC', $params) as $r) {
        $result[(int)$r['item_id']]['open'][] = $r;
        $result[(int)$r['item_id']]['open_qty'] = ($result[(int)$r['item_id']]['open_qty'] ?? 0) + (int)$r['quantity'];
    }
    _stock_finalize($result);
    return $result;
}

/** 品名ID → 在庫情報(品名単位) */
function unit_stock_summary(?array $unitIds = null): array
{
    $c = latest_confirmed_count();
    $since = stock_since();
    $where = '';
    $params = [];
    if ($unitIds !== null) {
        if (!$unitIds) {
            return [];
        }
        $where = ' unit_id IN (' . implode(',', array_fill(0, count($unitIds), '?')) . ')';
        $params = array_map('intval', $unitIds);
    }
    $result = [];
    if ($c) {
        foreach (db_all('SELECT unit_id, counted_quantity FROM inventory_count_unit_results WHERE count_id = ?' . ($where ? ' AND' . $where : ''), array_merge([$c['id']], $params)) as $r) {
            $result[(int)$r['unit_id']]['latest_qty'] = $r['counted_quantity'] === null ? null : (int)$r['counted_quantity'];
        }
    }
    if ($since !== null) {
        foreach (db_all("SELECT unit_id, SUM(CASE WHEN checked_out_at > ? THEN quantity ELSE 0 END) AS out_after,
                                SUM(CASE WHEN returned_at IS NOT NULL AND returned_at > ? THEN quantity ELSE 0 END) AS in_after
                         FROM item_checkouts WHERE unit_id IS NOT NULL" . ($where ? ' AND' . $where : '') . ' GROUP BY unit_id', array_merge([$since, $since], $params)) as $r) {
            $result[(int)$r['unit_id']]['out_after'] = (int)$r['out_after'];
            $result[(int)$r['unit_id']]['in_after'] = (int)$r['in_after'];
        }
    }
    foreach (db_all('SELECT c.*, u.management_no, u.name AS unit_name FROM item_checkouts c JOIN inventory_units u ON u.id = c.unit_id WHERE c.returned_at IS NULL'
                    . ($where ? ' AND c.' . ltrim($where) : '') . ' ORDER BY c.checked_out_at DESC', $params) as $r) {
        $result[(int)$r['unit_id']]['open'][] = $r;
        $result[(int)$r['unit_id']]['open_qty'] = ($result[(int)$r['unit_id']]['open_qty'] ?? 0) + (int)$r['quantity'];
    }
    _stock_finalize($result);
    return $result;
}

function stock_for(array $summary, int $id): array
{
    return $summary[$id] ?? ['latest_qty' => null, 'out_after' => 0, 'in_after' => 0, 'current' => null, 'open_qty' => 0, 'open' => []];
}

/** 持ち出し中の表示用文字列 */
function checkout_label(array $co, bool $withUnit = true): string
{
    $who = $co['checked_out_by_name'] ?: '(名前なし)';
    $s = $who . ' ' . date('n/j H:i', strtotime($co['checked_out_at']));
    if ((int)$co['quantity'] !== 1) {
        $s .= ' ×' . (int)$co['quantity'];
    }
    if ($withUnit) {
        $u = $co['management_no'] ?: ($co['unit_name'] ?? '');
        if ($u !== '' && $u !== null) {
            $s = $u . ' → ' . $s;
        }
    }
    return $s;
}

/** 品名の表示名(品名 + 管理No) */
function unit_label(array $u): string
{
    $name = $u['name'] ?? '';
    if ($name === null || $name === '') {
        $name = '(品名なし)';
    }
    if (!empty($u['management_no'])) {
        $name .= ' [' . $u['management_no'] . ']';
    }
    return $name;
}
