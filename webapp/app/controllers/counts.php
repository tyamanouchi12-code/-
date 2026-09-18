<?php
// 棚卸の一覧 / 登録・編集 / 状態変更(開始・確定・確定解除)

function count_load(int $id): array
{
    $c = db_row('SELECT * FROM inventory_counts WHERE id = ?', [$id]);
    if (!$c) {
        http_response_code(404);
        render('error', ['title' => '棚卸が見つかりません', 'message' => '指定された棚卸は存在しません。']);
        exit;
    }
    return $c;
}

if ($page === 'counts') {
    $counts = db_all('SELECT c.*,
                        (SELECT COUNT(*) FROM inventory_count_details d WHERE d.count_id = c.id) AS detail_count,
                        (SELECT COALESCE(SUM(d.count_quantity),0) FROM inventory_count_details d WHERE d.count_id = c.id) AS total_qty,
                        (SELECT COUNT(*) FROM inventory_count_details d WHERE d.count_id = c.id AND d.confirm_status = \'needs_check\') AS needs_check
                      FROM inventory_counts c ORDER BY c.base_date DESC, c.id DESC');
    render('counts/list', ['title' => '棚卸一覧', 'counts' => $counts]);
}

if ($page === 'count' && ($action === 'new' || $action === 'edit')) {
    $count = $action === 'edit' ? count_load((int)input_int('id', $_GET)) : [
        'id' => null, 'count_name' => date('Y/n/j') . ' 棚卸', 'base_date' => date('Y-m-d'), 'count_period' => '', 'entry_date' => '',
        'start_date' => '', 'end_date' => '', 'status' => 'preparing', 'notes' => '',
    ];
    render('counts/form', ['title' => $action === 'edit' ? '棚卸編集' : '棚卸登録', 'count' => $count, 'errors' => []]);
}

if ($page === 'count' && $action === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = input_int('id');
    $existing = $id ? count_load($id) : null;
    $v = [
        'count_name'   => input_str('count_name', null, 100),
        'base_date'    => input_date('base_date'),
        'count_period' => input_str('count_period', null, 100),
        'entry_date'   => input_date('entry_date'),
        'start_date'   => input_date('start_date'),
        'end_date'     => input_date('end_date'),
        'notes'        => input_str('notes', null, 5000),
    ];
    $errors = [];
    if ($v['count_name'] === null) {
        $errors[] = '棚卸名称は必須です。';
    }
    if ($v['base_date'] === null) {
        $errors[] = '棚卸基準日は YYYY-MM-DD 形式で入力してください。';
    }
    foreach (['entry_date' => '入力日付', 'start_date' => '棚卸開始日', 'end_date' => '棚卸終了日'] as $k => $label) {
        if (input_str($k) !== null && $v[$k] === null) {
            $errors[] = $label . 'は YYYY-MM-DD 形式で入力してください。';
        }
    }
    if ($errors) {
        $count = array_merge($existing ?? ['id' => null, 'status' => 'preparing'], $v, ['id' => $id]);
        render('counts/form', ['title' => $id ? '棚卸編集' : '棚卸登録', 'count' => $count, 'errors' => $errors]);
        exit;
    }
    if ($existing) {
        db_exec('UPDATE inventory_counts SET count_name=?, base_date=?, count_period=?, entry_date=?, start_date=?, end_date=?, notes=? WHERE id=?',
            [$v['count_name'], $v['base_date'], $v['count_period'], $v['entry_date'], $v['start_date'], $v['end_date'], $v['notes'], $id]);
        flash_set('success', '棚卸を更新しました。');
        redirect('count_entry', ['id' => $id]);
    }
    $newId = db_insert('INSERT INTO inventory_counts (count_name, base_date, count_period, entry_date, start_date, end_date, status, notes, created_by)
                        VALUES (?, ?, ?, ?, ?, ?, \'preparing\', ?, ?)',
        [$v['count_name'], $v['base_date'], $v['count_period'], $v['entry_date'], $v['start_date'], $v['end_date'], $v['notes'], actor_name()]);
    flash_set('success', '棚卸を登録しました。「棚卸を開始」を押すと入力できるようになります。');
    redirect('count_entry', ['id' => $newId]);
}

if ($page === 'count' && $action === 'status' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $count = count_load((int)input_int('id'));
    $to = input_str('to', null, 20);
    $from = $count['status'];
    if ($to === 'in_progress' && $from === 'preparing') {
        db_exec("UPDATE inventory_counts SET status = 'in_progress', start_date = COALESCE(start_date, CURDATE()) WHERE id = ?", [$count['id']]);
        flash_set('success', '棚卸を開始しました。');
    } elseif ($to === 'confirmed' && $from === 'in_progress') {
        require_perm('count.confirm');
        $unentered = (int)db_val('SELECT COUNT(*) FROM inventory_items i WHERE i.is_active = 1 AND NOT EXISTS (SELECT 1 FROM inventory_count_details d WHERE d.count_id = ? AND d.item_id = i.id AND d.count_quantity IS NOT NULL)', [$count['id']]);
        db_exec("UPDATE inventory_counts SET status = 'confirmed', end_date = COALESCE(end_date, CURDATE()), confirmed_by = ?, confirmed_at = NOW() WHERE id = ?", [actor_name(), $count['id']]);
        flash_set('success', '棚卸を確定しました。' . ($unentered > 0 ? "(数量未入力の品目が {$unentered} 件あります。未入力の品目は履歴に含まれません)" : ''));
    } elseif ($to === 'in_progress' && $from === 'confirmed') {
        require_perm('count.confirm');
        db_exec("UPDATE inventory_counts SET status = 'in_progress', confirmed_by = NULL, confirmed_at = NULL WHERE id = ?", [$count['id']]);
        flash_set('success', '確定を解除しました。再度入力できます。');
    } else {
        flash_set('error', 'その状態変更はできません。');
    }
    redirect('count_entry', ['id' => $count['id']]);
}

http_response_code(404);
render('error', ['title' => 'ページが見つかりません', 'message' => '不正な操作です。']);
