<div class="page-head">
  <h1>品目一覧</h1>
  <a class="btn btn-primary" href="<?= h(url('item', ['action' => 'new'])) ?>">＋ 品目を登録</a>
</div>

<form method="get" class="filter-bar">
  <input type="hidden" name="page" value="items">
  <input type="text" name="q" value="<?= h($f['q']) ?>" placeholder="内訳名(品名)・管理No・メーカー・型番・備考" class="w-wide">
  <select name="location"><option value="">保管場所: すべて</option><?php foreach ($locations as $l): ?><option value="<?= h($l['id']) ?>" <?= $f['location'] == $l['id'] ? 'selected' : '' ?>><?= h($l['name']) ?></option><?php endforeach; ?></select>
  <select name="condition"><option value="">状態: すべて</option><?php foreach ($conditions as $c): ?><option value="<?= h($c['code']) ?>" <?= $f['condition'] === $c['code'] ? 'selected' : '' ?>><?= h($c['name']) ?></option><?php endforeach; ?><option value="_none" <?= $f['condition'] === '_none' ? 'selected' : '' ?>>(未設定)</option></select>
  <select name="active"><option value="1" <?= $f['active'] === '1' ? 'selected' : '' ?>>有効のみ</option><option value="0" <?= $f['active'] === '0' ? 'selected' : '' ?>>無効のみ</option><option value="all" <?= $f['active'] === 'all' ? 'selected' : '' ?>>すべて</option></select>
  <button type="submit" class="btn">絞り込み</button>
  <a href="<?= h(url('items')) ?>" class="btn btn-ghost">クリア</a>
  <button type="button" class="btn btn-ghost" id="toggle-all-units">内訳をすべて開く / 閉じる</button>
</form>

<p class="muted"><?= count($items) ?> 品目<?php if ($latest): ?> ／ 「棚卸数」は <?= h($latest['count_name']) ?>(基準日 <?= h(fmt_date($latest['base_date'])) ?>)の確定値 ／ 「現在庫」= 棚卸数 − その後の持ち出し + その後の戻し<?php endif; ?></p>

<div class="table-wrap"><table class="table table-items table-cards">
  <thead><tr><th></th><th>品目コード</th><th>品目</th><th class="num">内訳数</th><th>保管場所(既定)</th><th class="num">棚卸数</th><th class="num">現在庫</th><th>備考</th><th>持ち出し中</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($items as $i): $iid = (int)$i['id']; $s = stock_for($stock, $iid); $units = $unitsByItem[$iid] ?? []; ?>
    <tr class="item-row <?= (int)$i['is_active'] ? '' : 'row-inactive' ?>" data-item="<?= $iid ?>">
      <td data-label=""><button type="button" class="btn btn-sm unit-toggle" data-item="<?= $iid ?>" aria-expanded="<?= $expandAll ? 'true' : 'false' ?>"><?= $expandAll ? '▼' : '▶' ?></button></td>
      <td class="nowrap" data-label="品目コード"><a href="<?= h(url('item', ['id' => $iid])) ?>"><?= h($i['item_code']) ?></a></td>
      <td data-label="品目"><strong><?= h($i['item_name']) ?></strong><?= (int)$i['is_active'] ? '' : ' <span class="badge badge-off">無効</span>' ?><?= $i['category_name'] !== null && $i['category_name'] !== $i['item_name'] ? '<br><small class="muted">カテゴリ: ' . h($i['category_name']) . '</small>' : '' ?></td>
      <td class="num" data-label="内訳数"><?= count($units) ?></td>
      <td data-label="保管場所"><?= h($i['location_name']) ?></td>
      <td class="num" data-label="棚卸数"><?= $s['latest_qty'] === null ? '<span class="muted">–</span>' : h($s['latest_qty']) ?></td>
      <td class="num" data-label="現在庫"><?= $s['current'] === null ? '<span class="muted">–</span>' : '<strong>' . h($s['current']) . '</strong>' ?></td>
      <td class="notes-cell" data-label="備考"><?= nl2br(h($i['notes'])) ?></td>
      <td class="checkout-cell" data-label="持ち出し中"><?php foreach ($s['open'] as $co): ?><span class="badge badge-out"><?= h(checkout_label($co)) ?></span><?php endforeach; ?></td>
      <td class="nowrap" data-label=""><a class="btn btn-sm" href="<?= h(url('item', ['action' => 'edit', 'id' => $iid])) ?>">編集</a></td>
    </tr>
    <tr class="unit-rows" data-item="<?= $iid ?>" <?= $expandAll ? '' : 'hidden' ?>>
      <td colspan="10" data-label="">
        <?php if (!$units): ?><span class="muted">内訳はありません。<a href="<?= h(url('item', ['action' => 'edit', 'id' => $iid])) ?>">編集</a>から追加できます。</span><?php else: ?>
        <table class="table table-inner table-cards">
          <thead><tr><th>内訳名(品名)</th><th>状態</th><th>管理No</th><th>メーカー / 型番</th><th>保管場所</th><th class="num">棚卸数</th><th class="num">現在庫</th><th>持ち出し中</th><th>備考</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($units as $u): $us = stock_for($unitStock, (int)$u['id']); ?>
            <tr>
              <td data-label="内訳名"><?= $u['name'] === null ? '<span class="muted">(内訳名なし)</span>' : h($u['name']) ?></td>
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
          </tbody>
        </table>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
