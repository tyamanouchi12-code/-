<div class="page-head">
  <h1>品目一覧</h1>
  <a class="btn btn-primary" href="<?= h(url('item', ['action' => 'new'])) ?>">＋ 品目を登録</a>
</div>

<form method="get" class="filter-bar">
  <input type="hidden" name="page" value="items">
  <input type="text" name="q" value="<?= h($f['q']) ?>" placeholder="品名・品目コード・管理No・メーカー・型番・備考" class="w-wide">
  <select name="category"><option value="">カテゴリ: すべて</option><?php foreach ($categories as $c): ?><option value="<?= h($c['id']) ?>" <?= $f['category'] == $c['id'] ? 'selected' : '' ?>><?= h($c['name']) ?></option><?php endforeach; ?></select>
  <select name="location"><option value="">保管場所: すべて</option><?php foreach ($locations as $l): ?><option value="<?= h($l['id']) ?>" <?= $f['location'] == $l['id'] ? 'selected' : '' ?>><?= h($l['name']) ?></option><?php endforeach; ?></select>
  <select name="condition"><option value="">状態: すべて</option><?php foreach ($conditions as $c): ?><option value="<?= h($c['code']) ?>" <?= $f['condition'] === $c['code'] ? 'selected' : '' ?>><?= h($c['name']) ?></option><?php endforeach; ?><option value="_none" <?= $f['condition'] === '_none' ? 'selected' : '' ?>>(未設定)</option></select>
  <select name="mtype"><option value="">管理方法: すべて</option><?php foreach (management_type_options() as $k => $v): ?><option value="<?= h($k) ?>" <?= $f['mtype'] === $k ? 'selected' : '' ?>><?= h($v) ?></option><?php endforeach; ?></select>
  <select name="stock_type"><option value="">在庫区分: すべて</option><?php foreach (stock_type_options() as $k => $v): ?><option value="<?= h($k) ?>" <?= $f['stock_type'] === $k ? 'selected' : '' ?>><?= h($v) ?></option><?php endforeach; ?></select>
  <select name="active"><option value="1" <?= $f['active'] === '1' ? 'selected' : '' ?>>有効のみ</option><option value="0" <?= $f['active'] === '0' ? 'selected' : '' ?>>無効のみ</option><option value="all" <?= $f['active'] === 'all' ? 'selected' : '' ?>>すべて</option></select>
  <button type="submit" class="btn">絞り込み</button>
  <a href="<?= h(url('items')) ?>" class="btn btn-ghost">クリア</a>
</form>

<p class="muted"><?= count($items) ?> 件<?php if ($latest['count']): ?> ／ 「最新棚卸数」は <?= h($latest['count']['count_name']) ?>(基準日 <?= h(fmt_date($latest['count']['base_date'])) ?>)の確定値<?php endif; ?></p>

<table class="table table-items">
  <thead><tr><th>表示順</th><th>品目コード</th><th>品名</th><th>状態</th><th>管理方法 / 管理No</th><th>カテゴリ</th><th>保管場所</th><th class="num">最新棚卸数</th><th>備考</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($items as $i): ?>
    <tr class="<?= (int)$i['is_active'] ? '' : 'row-inactive' ?>">
      <td class="num"><?= h($i['sort_order']) ?></td>
      <td class="nowrap"><a href="<?= h(url('item', ['id' => $i['id']])) ?>"><?= h($i['item_code']) ?></a></td>
      <td><?= $i['item_name'] === null ? '<span class="muted">(品名なし)</span>' : h($i['item_name']) ?><?= (int)$i['is_active'] ? '' : ' <span class="badge badge-off">無効</span>' ?></td>
      <td><?= h(condition_name($i['condition_code'])) ?></td>
      <td><?= h(management_type_label($i['management_type'])) ?><?php if ($i['management_type'] === 'unit'): ?><br><small><?= h($i['unit_nos'] ?: '(個体未登録)') ?></small><?php endif; ?></td>
      <td><?= h($i['category_name']) ?></td>
      <td><?= h($i['location_name']) ?></td>
      <td class="num"><?= isset($latest['qty'][(int)$i['id']]) ? h($latest['qty'][(int)$i['id']]) : '<span class="muted">–</span>' ?></td>
      <td class="notes-cell"><?= nl2br(h($i['notes'])) ?></td>
      <td class="nowrap"><a class="btn btn-sm" href="<?= h(url('item', ['action' => 'edit', 'id' => $i['id']])) ?>">編集</a></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
