<?php
// ダッシュボード

$stats = [
    'items_active'   => (int)db_val('SELECT COUNT(*) FROM inventory_items WHERE is_active = 1'),
    'items_inactive' => (int)db_val('SELECT COUNT(*) FROM inventory_items WHERE is_active = 0'),
    'units_active'   => (int)db_val('SELECT COUNT(*) FROM inventory_units WHERE is_active = 1'),
];
$openCounts = db_all("SELECT * FROM inventory_counts WHERE status <> 'confirmed' ORDER BY base_date DESC, id DESC");
$lastConfirmed = db_row("SELECT * FROM inventory_counts WHERE status = 'confirmed' ORDER BY base_date DESC, id DESC LIMIT 1");
$lastSummary = null;
if ($lastConfirmed) {
    $lastSummary = db_row('SELECT COUNT(*) AS detail_count, COALESCE(SUM(count_quantity),0) AS total_qty,
                                  SUM(CASE WHEN confirm_status = \'needs_check\' THEN 1 ELSE 0 END) AS needs_check
                           FROM inventory_count_details WHERE count_id = ?', [$lastConfirmed['id']]);
}
$openCheckouts = db_all('SELECT c.*, i.item_name, i.item_code, u.management_no FROM item_checkouts c JOIN inventory_items i ON i.id = c.item_id LEFT JOIN inventory_units u ON u.id = c.unit_id WHERE c.returned_at IS NULL ORDER BY c.checked_out_at DESC');
$recentItems = db_all('SELECT i.*, l.name AS location_name FROM inventory_items i LEFT JOIN locations l ON l.id = i.location_id
                       ORDER BY i.updated_at DESC, i.id DESC LIMIT 8');

render('dashboard', [
    'title' => 'ダッシュボード',
    'stats' => $stats,
    'openCounts' => $openCounts,
    'lastConfirmed' => $lastConfirmed,
    'lastSummary' => $lastSummary,
    'recentItems' => $recentItems,
    'openCheckouts' => $openCheckouts,
]);
