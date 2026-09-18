<div class="page-head">
  <h1>棚卸一覧</h1>
  <a class="btn btn-primary" href="<?= h(url('count', ['action' => 'new'])) ?>">＋ 棚卸を作成</a>
</div>
<div class="table-wrap"><table class="table">
  <thead><tr><th>棚卸名称</th><th>基準日</th><th>棚卸期間</th><th>入力日付</th><th>開始日</th><th>終了日</th><th>状態</th><th class="num">明細数</th><th class="num">数量合計</th><th class="num">要確認</th><th>確定者</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($counts as $c): ?>
    <tr>
      <td><a href="<?= h(url('count_entry', ['id' => $c['id']])) ?>"><?= h($c['count_name']) ?></a></td>
      <td><?= h(fmt_date($c['base_date'])) ?></td>
      <td><?= h($c['count_period']) ?></td>
      <td><?= h(fmt_date($c['entry_date'])) ?></td>
      <td><?= h(fmt_date($c['start_date'])) ?></td>
      <td><?= h(fmt_date($c['end_date'])) ?></td>
      <td><span class="badge badge-<?= h($c['status']) ?>"><?= h(count_status_label($c['status'])) ?></span></td>
      <td class="num"><?= h($c['detail_count']) ?></td>
      <td class="num"><?= h($c['total_qty']) ?></td>
      <td class="num"><?= (int)$c['needs_check'] ? '<span class="badge badge-warn">' . h($c['needs_check']) . '</span>' : '0' ?></td>
      <td><?= h($c['confirmed_by']) ?><?= $c['confirmed_at'] ? '<br><small>' . h(fmt_datetime($c['confirmed_at'])) . '</small>' : '' ?></td>
      <td class="nowrap"><a class="btn btn-sm" href="<?= h(url('count_entry', ['id' => $c['id']])) ?>"><?= $c['status'] === 'confirmed' ? '結果' : '入力' ?></a> <a class="btn btn-sm btn-ghost" href="<?= h(url('count', ['action' => 'edit', 'id' => $c['id']])) ?>">編集</a></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$counts): ?><tr><td colspan="12" class="muted">棚卸はまだありません。</td></tr><?php endif; ?>
  </tbody>
</table></div>
