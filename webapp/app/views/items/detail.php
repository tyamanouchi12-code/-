<div class="page-head">
  <h1><?= h($item['item_code']) ?> <?= h($item['item_name']) ?> <?= (int)$item['is_active'] ? '' : '<span class="badge badge-off">無効</span>' ?></h1>
  <div>
    <a class="btn" href="<?= h(url('item', ['action' => 'edit', 'id' => $item['id']])) ?>">編集</a>
    <a class="btn" href="<?= h(url('unit', ['action' => 'new', 'item_id' => $item['id']])) ?>">＋ 品名を追加</a>
    <?php if (can('item.deactivate')): ?>
    <form method="post" action="<?= h(url('item', ['action' => 'toggle_active'])) ?>" class="inline" data-confirm="<?= (int)$item['is_active'] ? 'このカテゴリを無効にします。棚卸履歴は残ります。よろしいですか?' : 'このカテゴリを再有効化します。よろしいですか?' ?>">
      <?= csrf_field() ?><input type="hidden" name="id" value="<?= h($item['id']) ?>">
      <button type="submit" class="btn"><?= (int)$item['is_active'] ? '無効にする' : '再有効化' ?></button>
    </form>
    <form method="post" action="<?= h(url('item', ['action' => 'delete'])) ?>" class="inline" data-confirm="このカテゴリを品名ごと削除します。元に戻せません。よろしいですか?">
      <?= csrf_field() ?><input type="hidden" name="id" value="<?= h($item['id']) ?>">
      <button type="submit" class="btn btn-danger">削除</button>
    </form>
    <?php if (!empty($deleteBlockers)): ?><span class="muted small">※削除できません(<?= h(implode('、', $deleteBlockers)) ?>)。使わなくする場合は「無効にする」</span><?php endif; ?>
    <?php endif; ?>
    <a class="btn btn-ghost" href="<?= h(url('items')) ?>">戻る</a>
  </div>
</div>

<div class="cards cards-3 stock-cards">
  <div class="card"><div class="card-label">現在庫(計算・合計)</div><div class="card-value"><?= $stock['current'] === null ? '<span class="muted">–</span>' : h($stock['current']) ?></div><div class="card-sub">棚卸数 − 持ち出し + 戻し</div></div>
  <div class="card"><div class="card-label">最新棚卸数(合計)</div><div class="card-value"><?= $stock['latest_qty'] === null ? '<span class="muted">–</span>' : h($stock['latest_qty']) ?></div><div class="card-sub"><?= $latestCount ? h($latestCount['count_name']) : '確定済み棚卸なし' ?></div></div>
  <div class="card"><div class="card-label">持ち出し中</div><div class="card-value"><?= h($stock['open_qty']) ?></div><div class="card-sub"><?= $stock['open'] ? h(implode(' / ', array_map('checkout_label', $stock['open']))) : 'なし' ?></div></div>
</div>

<div class="detail-grid">
  <dl>
    <dt>保管場所(既定)</dt><dd><?= h($lookup['location']) ?: '<span class="muted">(未設定)</span>' ?></dd>
    <dt>在庫区分</dt><dd><?= h(stock_type_label($item['stock_type'])) ?></dd>
  </dl>
  <dl>
    <dt>表示順</dt><dd><?= h($item['sort_order']) ?></dd>
    <dt>登録 / 更新</dt><dd><?= h(fmt_datetime($item['created_at'])) ?> <?= h($item['created_by']) ?><br><?= h(fmt_datetime($item['updated_at'])) ?> <?= h($item['updated_by']) ?></dd>
    <dt>備考</dt><dd class="pre"><?= nl2br(h($item['notes'])) ?></dd>
  </dl>
</div>

<section>
  <div class="page-head">
    <h2>品名(実物の種類・個体) <?= count($units) ?> 件</h2>
    <a class="btn btn-sm btn-primary" href="<?= h(url('unit', ['action' => 'new', 'item_id' => $item['id']])) ?>">＋ 品名を追加</a>
  </div>
  <?php if (!$units): ?>
    <p class="muted">品名はまだ登録されていません。</p>
  <?php else: ?>
  <div class="table-wrap"><table class="table table-cards">
    <thead><tr><th>品名</th><th>状態</th><th>管理No</th><th>保管場所</th><th class="num">棚卸数</th><th class="num">現在庫</th><th>持ち出し中</th><th>備考</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($units as $u): $us = stock_for($unitStock, (int)$u['id']); ?>
      <tr class="<?= (int)$u['is_active'] ? '' : 'row-inactive' ?>">
        <td data-label="品名"><?= $u['name'] === null ? '<span class="muted">(品名なし)</span>' : h($u['name']) ?><?= (int)$u['is_active'] ? '' : ' <span class="badge badge-off">無効</span>' ?><?= $u['source_excel_rows'] ? '<br><small class="muted">Excel ' . h(str_replace(';', '、', $u['source_excel_rows'])) . ' 行目</small>' : '' ?></td>
        <td data-label="状態"><?= h(condition_name($u['condition_code'])) ?></td>
        <td data-label="管理No"><?= h($u['management_no']) ?><?= $u['status'] !== 'in_stock' ? ' <span class="badge">' . h(unit_status_label($u['status'])) . '</span>' : '' ?></td>
        <td data-label="保管場所"><?= h($u['location_name'] ?? '') ?: '<span class="muted">(カテゴリと同じ)</span>' ?></td>
        <td class="num" data-label="棚卸数"><?= $us['latest_qty'] === null ? '<span class="muted">–</span>' : h($us['latest_qty']) ?></td>
        <td class="num" data-label="現在庫"><?= $us['current'] === null ? '<span class="muted">–</span>' : '<strong>' . h($us['current']) . '</strong>' ?></td>
        <td class="checkout-cell" data-label="持ち出し中"><?php foreach ($us['open'] as $co): ?><span class="badge badge-out"><?= h(checkout_label($co, false)) ?></span><?php endforeach; ?></td>
        <td class="notes-cell" data-label="備考"><?= nl2br(h($u['notes'])) ?></td>
        <td class="nowrap" data-label=""><a class="btn btn-sm" href="<?= h(url('unit', ['action' => 'edit', 'id' => $u['id']])) ?>">編集</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</section>

<section>
  <h2>持ち出し・戻しの記録</h2>
  <?php if (!$checkouts): ?>
    <p class="muted">持ち出しの記録はありません。</p>
  <?php else: ?>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>状態</th><th>品名</th><th>数量</th><th>持ち出した人</th><th>持ち出し日時</th><th>戻し日時</th><th>戻し登録者</th><th>メモ</th><th>登録者</th></tr></thead>
    <tbody>
    <?php foreach ($checkouts as $co): ?>
      <tr>
        <td><?= $co['returned_at'] === null ? '<span class="badge badge-out">持ち出し中</span>' : '<span class="badge badge-on">戻し済</span>' ?></td>
        <td><?= h(unit_label(['name' => $co['unit_name'], 'management_no' => $co['management_no']])) ?></td>
        <td><?= h($co['quantity']) ?></td>
        <td><?= h($co['checked_out_by_name']) ?></td>
        <td><?= h(fmt_datetime($co['checked_out_at'])) ?></td>
        <td><?= h(fmt_datetime($co['returned_at'])) ?></td>
        <td><?= h($co['returned_by_name']) ?></td>
        <td><?= h($co['notes']) ?></td>
        <td><?= h($co['created_by']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</section>

<section>
  <h2>棚卸履歴(カテゴリ合計と品名)</h2>
  <?php if (!$history): ?>
    <p class="muted">棚卸履歴はありません。</p>
  <?php else: ?>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>棚卸</th><th>基準日</th><th>状態</th><th class="num">合計</th><th class="num">前回</th><th class="num">差異</th><th>品名ごとの数量</th><th>担当者</th></tr></thead>
    <tbody>
    <?php foreach ($history as $hrow): ?>
      <tr>
        <td><a href="<?= h(url('count_entry', ['id' => $hrow['count_id']])) ?>"><?= h($hrow['count_name']) ?></a></td>
        <td><?= h(fmt_date($hrow['base_date'])) ?></td>
        <td><span class="badge badge-<?= h($hrow['status']) ?>"><?= h(count_status_label($hrow['status'])) ?></span></td>
        <td class="num"><?= $hrow['count_quantity'] === null ? '<span class="muted">–</span>' : h($hrow['count_quantity']) ?></td>
        <td class="num"><?= $hrow['prev_quantity'] === null ? '<span class="muted">–</span>' : h($hrow['prev_quantity']) ?></td>
        <td class="num <?= $hrow['diff'] === null ? '' : ($hrow['diff'] > 0 ? 'diff-plus' : ($hrow['diff'] < 0 ? 'diff-minus' : '')) ?>"><?= $hrow['diff'] === null ? '' : h(($hrow['diff'] > 0 ? '+' : '') . $hrow['diff']) ?></td>
        <td class="small"><?php $parts = []; foreach ($units as $u) { if (array_key_exists((int)$u['id'], $unitHistory[(int)$hrow['count_id']] ?? [])) { $q = $unitHistory[(int)$hrow['count_id']][(int)$u['id']]; $parts[] = h(unit_label($u)) . ': ' . ($q === null ? '–' : h($q)); } } echo implode('<br>', $parts); ?></td>
        <td><?= h($hrow['counted_by']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</section>
