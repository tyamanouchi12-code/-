<div class="page-head">
  <h1><?= h($item['item_code']) ?> <?= $item['item_name'] === null ? '<span class="muted">(品名なし)</span>' : h($item['item_name']) ?>
    <?= (int)$item['is_active'] ? '' : '<span class="badge badge-off">無効</span>' ?></h1>
  <div>
    <a class="btn" href="<?= h(url('item', ['action' => 'edit', 'id' => $item['id']])) ?>">編集</a>
    <?php if (can('item.deactivate')): ?>
    <form method="post" action="<?= h(url('item', ['action' => 'toggle_active'])) ?>" class="inline" data-confirm="<?= (int)$item['is_active'] ? 'この品目を無効にします。棚卸履歴は残り、一覧では「無効のみ」で表示できます。よろしいですか?' : 'この品目を再有効化します。よろしいですか?' ?>">
      <?= csrf_field() ?><input type="hidden" name="id" value="<?= h($item['id']) ?>">
      <button type="submit" class="btn <?= (int)$item['is_active'] ? 'btn-danger' : '' ?>"><?= (int)$item['is_active'] ? '無効にする' : '再有効化' ?></button>
    </form>
    <?php endif; ?>
    <a class="btn btn-ghost" href="<?= h(url('items')) ?>">一覧へ</a>
  </div>
</div>

<div class="cards cards-4 stock-cards">
  <div class="card"><div class="card-label">現在庫(計算)</div><div class="card-value"><?= $stock['current'] === null ? '<span class="muted">–</span>' : h($stock['current']) ?></div><div class="card-sub">棚卸数 − 持ち出し + 戻し</div></div>
  <div class="card"><div class="card-label">最新棚卸数</div><div class="card-value"><?= $stock['latest_qty'] === null ? '<span class="muted">–</span>' : h($stock['latest_qty']) ?></div><div class="card-sub"><?= $latestCount ? h($latestCount['count_name']) : '確定済み棚卸なし' ?></div></div>
  <div class="card"><div class="card-label">持ち出し中</div><div class="card-value"><?= h($stock['open_qty']) ?></div><div class="card-sub"><?= $stock['open'] ? h(implode(' / ', array_map('checkout_label', $stock['open']))) : 'なし' ?></div></div>
  <div class="card"><div class="card-label">持ち出し登録</div><div class="card-sub"><a class="btn btn-sm btn-primary" href="<?= h(url('checkout', ['mode' => 'out', 'item_id' => $item['id']])) ?>">この品目を持ち出す</a> <a class="btn btn-sm" href="<?= h(url('checkout', ['mode' => 'in'])) ?>">戻す</a></div></div>
</div>

<div class="detail-grid">
  <dl>
    <dt>状態</dt><dd><?= h(condition_name($item['condition_code'])) ?: '<span class="muted">(未設定)</span>' ?></dd>
    <dt>管理方法</dt><dd><?= h(management_type_label($item['management_type'])) ?></dd>
    <dt>カテゴリ</dt><dd><?= h($lookup['category']) ?: '<span class="muted">(未設定)</span>' ?></dd>
    <dt>保管場所</dt><dd><?= h($lookup['location']) ?: '<span class="muted">(未設定)</span>' ?></dd>
    <dt>在庫区分</dt><dd><?= h(stock_type_label($item['stock_type'])) ?></dd>
    <dt>客先</dt><dd><?= h($lookup['customer']) ?></dd>
  </dl>
  <dl>
    <dt>メーカー</dt><dd><?= h($item['manufacturer']) ?></dd>
    <dt>型番</dt><dd><?= h($item['model_number']) ?></dd>
    <dt>シリアル番号</dt><dd><?= h($item['serial_number']) ?></dd>
    <dt>ネットワーク情報</dt><dd><?= h($item['network_info']) ?></dd>
    <dt>表示順</dt><dd><?= h($item['sort_order']) ?></dd>
    <dt>登録 / 更新</dt><dd><?= h(fmt_datetime($item['created_at'])) ?> <?= h($item['created_by']) ?><br><?= h(fmt_datetime($item['updated_at'])) ?> <?= h($item['updated_by']) ?></dd>
  </dl>
  <dl class="span2">
    <dt>備考</dt><dd class="pre"><?= nl2br(h($item['notes'])) ?></dd>
    <?php if ($item['source_excel_rows']): ?><dt>移行元Excel行</dt><dd>シート1 <?= h(str_replace(';', '、', $item['source_excel_rows'])) ?> 行目</dd><?php endif; ?>
  </dl>
</div>

<section>
  <div class="page-head">
    <h2>個体(管理No)</h2>
    <?php if ($item['management_type'] === 'unit'): ?><a class="btn btn-sm btn-primary" href="<?= h(url('unit', ['action' => 'new', 'item_id' => $item['id']])) ?>">＋ 個体を追加</a><?php endif; ?>
  </div>
  <?php if ($item['management_type'] !== 'unit' && !$units): ?>
    <p class="muted">数量管理の品目です。個体を登録するには、編集で管理方法を「個体管理」にしてください。</p>
  <?php elseif (!$units): ?>
    <p class="muted">個体はまだ登録されていません。</p>
  <?php else: ?>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>管理No</th><th>シリアル番号</th><th>IPアドレス</th><th>状態</th><th>保管場所</th><th>備考</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($units as $u): ?>
      <tr class="<?= (int)$u['is_active'] ? '' : 'row-inactive' ?>">
        <td><?= h($u['management_no']) ?><?= (int)$u['is_active'] ? '' : ' <span class="badge badge-off">無効</span>' ?></td>
        <td><?= h($u['serial_number']) ?></td>
        <td><?= h($u['ip_address']) ?></td>
        <td><?= h(unit_status_label($u['status'])) ?></td>
        <td><?= h($u['location_name']) ?></td>
        <td class="notes-cell"><?= nl2br(h($u['notes'])) ?></td>
        <td class="nowrap"><a class="btn btn-sm" href="<?= h(url('unit', ['action' => 'edit', 'id' => $u['id']])) ?>">編集</a></td>
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
    <thead><tr><th>状態</th><th>個体 / 数量</th><th>持ち出した人</th><th>持ち出し日時</th><th>戻し日時</th><th>戻し登録者</th><th>メモ</th><th>登録者</th></tr></thead>
    <tbody>
    <?php foreach ($checkouts as $co): ?>
      <tr>
        <td><?= $co['returned_at'] === null ? '<span class="badge badge-out">持ち出し中</span>' : '<span class="badge badge-on">戻し済</span>' ?></td>
        <td><?= $co['management_no'] ? h($co['management_no']) : '×' . h($co['quantity']) ?></td>
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
  <h2>棚卸履歴</h2>
  <?php if (!$history): ?>
    <p class="muted">棚卸履歴はありません。</p>
  <?php else: ?>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>棚卸</th><th>基準日</th><th>状態</th><th class="num">棚卸数</th><th class="num">前回</th><th class="num">差異</th><th>棚卸時保管場所</th><?php if ($units): ?><th>個体結果</th><?php endif; ?><th>担当者</th><th>明細備考</th></tr></thead>
    <tbody>
    <?php foreach ($history as $hrow): ?>
      <tr>
        <td><a href="<?= h(url('count_entry', ['id' => $hrow['count_id']])) ?>"><?= h($hrow['count_name']) ?></a></td>
        <td><?= h(fmt_date($hrow['base_date'])) ?></td>
        <td><span class="badge badge-<?= h($hrow['status']) ?>"><?= h(count_status_label($hrow['status'])) ?></span></td>
        <td class="num"><?= $hrow['count_quantity'] === null ? '<span class="muted">–</span>' : h($hrow['count_quantity']) ?></td>
        <td class="num"><?= $hrow['prev_quantity'] === null ? '<span class="muted">–</span>' : h($hrow['prev_quantity']) ?></td>
        <td class="num <?= $hrow['diff'] === null ? '' : ($hrow['diff'] > 0 ? 'diff-plus' : ($hrow['diff'] < 0 ? 'diff-minus' : '')) ?>"><?= $hrow['diff'] === null ? '' : h(($hrow['diff'] > 0 ? '+' : '') . $hrow['diff']) ?></td>
        <td><?= h($hrow['location_name']) ?></td>
        <?php if ($units): ?><td><?php $parts = []; foreach ($units as $u) { if (isset($unitHistory[(int)$hrow['count_id']][(int)$u['id']])) { $parts[] = h($u['management_no']) . ':' . h(unit_result_label($unitHistory[(int)$hrow['count_id']][(int)$u['id']])); } } echo implode('<br>', $parts); ?></td><?php endif; ?>
        <td><?= h($hrow['counted_by']) ?></td>
        <td class="notes-cell"><?= nl2br(h($hrow['notes'])) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</section>
