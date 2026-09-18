<h1>ダッシュボード</h1>
<div class="cards">
  <div class="card"><div class="card-label">有効な品目</div><div class="card-value"><?= h($stats['items_active']) ?></div><div class="card-sub">無効: <?= h($stats['items_inactive']) ?></div></div>
  <div class="card"><div class="card-label">個体(管理No あり)</div><div class="card-value"><?= h($stats['units_active']) ?></div></div>
  <div class="card">
    <div class="card-label">最後に確定した棚卸</div>
    <?php if ($lastConfirmed): ?>
      <div class="card-value small"><a href="<?= h(url('count_entry', ['id' => $lastConfirmed['id']])) ?>"><?= h($lastConfirmed['count_name']) ?></a></div>
      <div class="card-sub">基準日 <?= h(fmt_date($lastConfirmed['base_date'])) ?> / 明細 <?= h($lastSummary['detail_count']) ?> 件 / 合計 <?= h($lastSummary['total_qty']) ?> / 要確認 <?= h($lastSummary['needs_check']) ?> 件</div>
    <?php else: ?>
      <div class="card-sub">なし</div>
    <?php endif; ?>
  </div>
</div>

<section>
  <h2>進行中の棚卸</h2>
  <?php if (!$openCounts): ?>
    <p class="muted">進行中の棚卸はありません。<a href="<?= h(url('count', ['action' => 'new'])) ?>">新しい棚卸を作成</a></p>
  <?php else: ?>
  <table class="table">
    <thead><tr><th>棚卸名称</th><th>基準日</th><th>状態</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($openCounts as $c): ?>
      <tr>
        <td><?= h($c['count_name']) ?></td>
        <td><?= h(fmt_date($c['base_date'])) ?></td>
        <td><span class="badge badge-<?= h($c['status']) ?>"><?= h(count_status_label($c['status'])) ?></span></td>
        <td><a class="btn btn-sm" href="<?= h(url('count_entry', ['id' => $c['id']])) ?>">棚卸入力</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</section>

<section>
  <h2>最近更新された品目</h2>
  <table class="table">
    <thead><tr><th>品目コード</th><th>品名</th><th>状態</th><th>保管場所</th><th>更新日時</th><th>更新者</th></tr></thead>
    <tbody>
    <?php foreach ($recentItems as $i): ?>
      <tr>
        <td class="nowrap"><a href="<?= h(url('item', ['id' => $i['id']])) ?>"><?= h($i['item_code']) ?></a></td>
        <td><?= $i['item_name'] === null ? '<span class="muted">(品名なし)</span>' : h($i['item_name']) ?></td>
        <td><?= h(condition_name($i['condition_code'])) ?></td>
        <td><?= h($i['location_name']) ?></td>
        <td><?= h(fmt_datetime($i['updated_at'])) ?></td>
        <td><?= h($i['updated_by']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</section>
