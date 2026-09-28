<div class="page-head">
  <h1>品目一覧</h1>
  <a class="btn btn-primary" href="<?= h(url('item', ['action' => 'new'])) ?>">＋ 品目を登録</a>
</div>

<form method="get" class="filter-bar">
  <input type="hidden" name="page" value="items">
  <input type="text" name="q" value="<?= h($f['q']) ?>" placeholder="品名(内訳名)・管理No・メーカー・型番・備考" class="w-wide">
  <select name="location"><option value="">保管場所: すべて</option><?php foreach ($locations as $l): ?><option value="<?= h($l['id']) ?>" <?= $f['location'] == $l['id'] ? 'selected' : '' ?>><?= h($l['name']) ?></option><?php endforeach; ?></select>
  <select name="condition"><option value="">状態: すべて</option><?php foreach ($conditions as $c): ?><option value="<?= h($c['code']) ?>" <?= $f['condition'] === $c['code'] ? 'selected' : '' ?>><?= h($c['name']) ?></option><?php endforeach; ?><option value="_none" <?= $f['condition'] === '_none' ? 'selected' : '' ?>>(未設定)</option></select>
  <select name="active"><option value="1" <?= $f['active'] === '1' ? 'selected' : '' ?>>有効のみ</option><option value="0" <?= $f['active'] === '0' ? 'selected' : '' ?>>無効のみ</option><option value="all" <?= $f['active'] === 'all' ? 'selected' : '' ?>>すべて</option></select>
  <button type="submit" class="btn">絞り込み</button>
  <a href="<?= h(url('items')) ?>" class="btn btn-ghost">クリア</a>
</form>

<p class="muted"><?= count($items) ?> 品目<?php if ($latest): ?> ／ 「棚卸数」は <?= h($latest['count_name']) ?>(基準日 <?= h(fmt_date($latest['base_date'])) ?>)の確定値 ／ 「現在庫」= 棚卸数 − その後の持ち出し + その後の戻し<?php endif; ?></p>

<div class="table-wrap"><table class="table table-items table-cards">
  <thead><tr><th>品名(内訳名)</th><th>状態</th><th>管理No</th><th>メーカー / 型番</th><th>保管場所</th><th class="num">棚卸数</th><th class="num">現在庫</th><th>持ち出し中</th><th>備考</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($items as $i): $iid = (int)$i['id']; $s = stock_for($stock, $iid); $units = $unitsByItem[$iid] ?? []; ?>
    <tr class="item-head <?= (int)$i['is_active'] ? '' : 'row-inactive' ?>" data-item="<?= $iid ?>">
      <td colspan="4" data-label="品目"><a href="<?= h(url('item', ['id' => $iid])) ?>" class="item-link"><strong><?= h($i['item_name']) ?></strong></a> <small class="muted"><?= h($i['item_code']) ?> ／ 内訳 <?= count($units) ?> 件</small><?= (int)$i['is_active'] ? '' : ' <span class="badge badge-off">無効</span>' ?><?= $i['category_name'] !== null && $i['category_name'] !== $i['item_name'] ? ' <small class="muted">(カテゴリ: ' . h($i['category_name']) . ')</small>' : '' ?></td>
      <td data-label="保管場所(既定)"><?= h($i['location_name']) ?></td>
      <td class="num" data-label="棚卸数(合計)"><?= $s['latest_qty'] === null ? '<span class="muted">–</span>' : h($s['latest_qty']) ?></td>
      <td class="num" data-label="現在庫(合計)"><?= $s['current'] === null ? '<span class="muted">–</span>' : '<strong>' . h($s['current']) . '</strong>' ?></td>
      <td class="checkout-cell" data-label="持ち出し中"><?= $s['open_qty'] ? '<span class="badge badge-out">' . h($s['open_qty']) . ' 件</span>' : '' ?></td>
      <td class="notes-cell" data-label="備考"><?= nl2br(h($i['notes'])) ?></td>
      <td class="nowrap" data-label=""><a class="btn btn-sm" href="<?= h(url('item', ['action' => 'edit', 'id' => $iid])) ?>">編集・内訳追加</a></td>
    </tr>
    <?php if (!$units): ?>
    <tr class="unit-line"><td colspan="10" class="muted" data-label="">内訳はありません。「編集・内訳追加」から追加できます。</td></tr>
    <?php endif; ?>
    <?php foreach ($units as $u): $us = stock_for($unitStock, (int)$u['id']); ?>
      <tr class="unit-line" data-item="<?= $iid ?>">
        <td data-label="品名"><?= $u['name'] === null ? '<span class="muted">(内訳名なし)</span>' : h($u['name']) ?></td>
        <td data-label="状態"><?= h(condition_name($u['condition_code'])) ?></td>
        <td data-label="管理No"><?= h($u['management_no']) ?><?= $u['status'] !== 'in_stock' ? ' <span class="badge">' . h(unit_status_label($u['status'])) . '</span>' : '' ?></td>
        <td data-label="メーカー/型番"><?= h(trim(($u['manufacturer'] ?? '') . ' ' . ($u['model_number'] ?? ''))) ?></td>
        <td data-label="保管場所"><?= h($u['location_name']) ?></td>
        <td class="num" data-label="棚卸数"><?= $us['latest_qty'] === null ? '<span class="muted">–</span>' : h($us['latest_qty']) ?></td>
        <td class="num" data-label="現在庫"><?= $us['current'] === null ? '<span class="muted">–</span>' : '<strong>' . h($us['current']) . '</strong>' ?></td>
        <td class="checkout-cell" data-label="持ち出し中"><?php foreach ($us['open'] as $co): ?><span class="badge badge-out"><?= h(checkout_label($co, false)) ?></span><?php endforeach; ?></td>
        <td class="notes-cell" data-label="備考"><?= nl2br(h($u['notes'])) ?></td>
        <td class="nowrap" data-label=""><a class="btn btn-sm btn-ghost" href="<?= h(url('unit', ['action' => 'edit', 'id' => $u['id']])) ?>">編集</a></td>
      </tr>
    <?php endforeach; ?>
  <?php endforeach; ?>
  </tbody>
</table></div>
