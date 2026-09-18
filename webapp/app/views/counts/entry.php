<div class="page-head">
  <h1><?= h($count['count_name']) ?> <span class="badge badge-<?= h($count['status']) ?>"><?= h(count_status_label($count['status'])) ?></span></h1>
  <div>
    <?php if ($count['status'] === 'preparing'): ?>
      <form method="post" action="<?= h(url('count', ['action' => 'status'])) ?>" class="inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= h($count['id']) ?>"><input type="hidden" name="to" value="in_progress"><button class="btn btn-primary" type="submit">棚卸を開始</button></form>
    <?php elseif ($count['status'] === 'in_progress'): ?>
      <?php if (can('count.confirm')): ?>
      <form method="post" action="<?= h(url('count', ['action' => 'status'])) ?>" class="inline" data-confirm="この棚卸を確定します。確定後は入力できなくなります(権限があれば確定解除できます)。よろしいですか?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= h($count['id']) ?>"><input type="hidden" name="to" value="confirmed"><button class="btn btn-success" type="submit">棚卸を確定</button></form>
      <?php else: ?><span class="muted">確定は権限のある利用者が行います</span><?php endif; ?>
    <?php elseif ($count['status'] === 'confirmed' && can('count.confirm')): ?>
      <form method="post" action="<?= h(url('count', ['action' => 'status'])) ?>" class="inline" data-confirm="確定を解除して再入力できるようにします。よろしいですか?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= h($count['id']) ?>"><input type="hidden" name="to" value="in_progress"><button class="btn" type="submit">確定を解除</button></form>
    <?php endif; ?>
    <a class="btn btn-ghost" href="<?= h(url('count', ['action' => 'edit', 'id' => $count['id']])) ?>">棚卸情報を編集</a>
    <a class="btn btn-ghost" href="<?= h(url('counts')) ?>">一覧へ</a>
  </div>
</div>

<div class="cards cards-4">
  <div class="card"><div class="card-label">基準日</div><div class="card-value small"><?= h(fmt_date($count['base_date'])) ?></div><div class="card-sub">期間: <?= h($count['count_period']) ?: '–' ?> / 入力日付: <?= h(fmt_date($count['entry_date'])) ?: '–' ?></div></div>
  <div class="card"><div class="card-label">入力済み / 表示中の品目</div><div class="card-value small"><?= h($summary['entered']) ?> / <?= count($items) ?></div><div class="card-sub">数量合計 <?= h($summary['total']) ?></div></div>
  <div class="card"><div class="card-label">要確認</div><div class="card-value small"><?= h($summary['needs_check']) ?></div></div>
  <div class="card"><div class="card-label">前回との差異あり</div><div class="card-value small"><?= h($summary['diff']) ?></div><div class="card-sub">前回: <?= $prevCount ? h($prevCount['count_name']) . '(' . h(fmt_date($prevCount['base_date'])) . ')' : 'なし' ?></div></div>
</div>

<form method="get" class="filter-bar">
  <input type="hidden" name="page" value="count_entry"><input type="hidden" name="id" value="<?= h($count['id']) ?>">
  <select name="location" onchange="this.form.submit()">
    <option value="">保管場所: すべて</option>
    <?php foreach ($locations as $l): ?><option value="<?= h($l['id']) ?>" <?= $locFilter === (int)$l['id'] ? 'selected' : '' ?>><?= h($l['name']) ?></option><?php endforeach; ?>
    <option value="0" <?= $locFilter === 0 ? 'selected' : '' ?>>(保管場所 未設定)</option>
  </select>
  <input type="text" id="quick-filter" placeholder="この表の中を絞り込み(品名・管理No)" class="w-wide">
  <label class="inline-check"><input type="checkbox" id="only-unentered"> 未入力のみ表示</label>
  <label class="inline-check"><input type="checkbox" id="only-diff"> 差異ありのみ表示</label>
</form>

<?php if ($editable): ?>
<form method="post" action="<?= h(url('count_entry', ['action' => 'save', 'id' => $count['id']] + ($locFilter !== null ? ['location' => $locFilter] : []))) ?>" id="entry-form" data-dirty-check>
  <?= csrf_field() ?>
  <div class="sticky-actions"><button type="submit" class="btn btn-primary">入力内容を保存</button> <span class="muted">表示中の品目のみ保存されます</span></div>
<?php endif; ?>
<table class="table table-entry">
  <thead><tr><th>#</th><th>品目コード</th><th>品名</th><th>状態</th><th>保管場所</th><th class="num">前回</th><th class="num">今回</th><th class="num">差異</th><th>確認状態</th><th>明細備考</th><th>担当 / 日時</th></tr></thead>
  <tbody>
  <?php foreach ($items as $n => $it): $iid = (int)$it['id']; $isUnit = $it['management_type'] === 'unit' && $it['units']; ?>
    <tr class="entry-row <?= $it['count_quantity'] === null ? 'unentered' : '' ?> <?= $it['diff'] ? 'has-diff' : '' ?> <?= (int)$it['is_active'] ? '' : 'row-inactive' ?>" data-text="<?= h(mb_strtolower(($it['item_name'] ?? '') . ' ' . $it['item_code'] . ' ' . implode(' ', array_column($it['units'], 'management_no')))) ?>">
      <td class="num"><?= $n + 1 ?></td>
      <td class="nowrap"><a href="<?= h(url('item', ['id' => $iid])) ?>" target="_blank"><?= h($it['item_code']) ?></a><?php if ($editable): ?><input type="hidden" name="touched[<?= $iid ?>]" value="1"><?php endif; ?></td>
      <td><?= $it['item_name'] === null ? '<span class="muted">(品名なし)</span>' : h($it['item_name']) ?><?= (int)$it['is_active'] ? '' : ' <span class="badge badge-off">無効</span>' ?>
        <?php if ($isUnit): ?>
          <div class="unit-list">
          <?php foreach ($it['units'] as $u): $uid = (int)$u['id']; $res = $u['result'] ?? 'unchecked'; ?>
            <div class="unit-row">
              <span class="unit-no"><?= h($u['management_no'] ?: ('S/N ' . $u['serial_number'])) ?></span>
              <?php if ($editable): ?>
                <label><input type="radio" name="unit[<?= $uid ?>]" value="present" <?= $res === 'present' ? 'checked' : '' ?> data-unit-of="<?= $iid ?>"> 有</label>
                <label><input type="radio" name="unit[<?= $uid ?>]" value="absent" <?= $res === 'absent' ? 'checked' : '' ?> data-unit-of="<?= $iid ?>"> 無</label>
                <label><input type="radio" name="unit[<?= $uid ?>]" value="unchecked" <?= $res === 'unchecked' ? 'checked' : '' ?> data-unit-of="<?= $iid ?>"> 未確認</label>
              <?php else: ?>
                <span class="badge badge-unit-<?= h($res) ?>"><?= h(unit_result_label($res)) ?></span>
              <?php endif; ?>
              <?php if (isset($prevUnit[$uid])): ?><small class="muted">(前回: <?= h(unit_result_label($prevUnit[$uid])) ?>)</small><?php endif; ?>
            </div>
          <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </td>
      <td><?= h(condition_name($it['condition_code'])) ?></td>
      <td><?= h($it['location_name']) ?></td>
      <td class="num"><?= $it['prev_quantity'] === null ? '<span class="muted">–</span>' : h($it['prev_quantity']) ?></td>
      <td class="num">
        <?php if ($editable && !$isUnit): ?>
          <input type="text" inputmode="numeric" name="qty[<?= $iid ?>]" value="<?= h($it['count_quantity']) ?>" class="qty-input" data-prev="<?= h($it['prev_quantity']) ?>" data-item="<?= $iid ?>">
        <?php elseif ($editable && $isUnit): ?>
          <span class="qty-auto" data-item="<?= $iid ?>"><?= $it['count_quantity'] === null ? '–' : h($it['count_quantity']) ?></span><small class="muted"> (自動)</small>
        <?php else: ?>
          <?= $it['count_quantity'] === null ? '<span class="muted">–</span>' : '<strong>' . h($it['count_quantity']) . '</strong>' ?>
        <?php endif; ?>
      </td>
      <td class="num diff-cell <?= $it['diff'] === null ? '' : ($it['diff'] > 0 ? 'diff-plus' : ($it['diff'] < 0 ? 'diff-minus' : '')) ?>" data-item="<?= $iid ?>"><?= $it['diff'] === null ? '' : h(($it['diff'] > 0 ? '+' : '') . $it['diff']) ?></td>
      <td>
        <?php if ($editable): ?>
          <select name="confirm_status[<?= $iid ?>]"><?php foreach (confirm_status_options() as $k => $v): ?><option value="<?= h($k) ?>" <?= ($it['confirm_status'] ?? 'unconfirmed') === $k ? 'selected' : '' ?>><?= h($v) ?></option><?php endforeach; ?></select>
        <?php else: ?>
          <?= $it['confirm_status'] ? '<span class="badge badge-cs-' . h($it['confirm_status']) . '">' . h(confirm_status_label($it['confirm_status'])) . '</span>' : '' ?>
        <?php endif; ?>
      </td>
      <td><?php if ($editable): ?><input type="text" name="dnotes[<?= $iid ?>]" value="<?= h($it['detail_notes']) ?>" class="w-notes" placeholder="この棚卸だけのメモ"><?php else: ?><?= nl2br(h($it['detail_notes'])) ?><?php endif; ?></td>
      <td class="small"><?= h($it['counted_by']) ?><?= $it['counted_at'] ? '<br>' . h(fmt_datetime($it['counted_at'])) : '' ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php if ($editable): ?>
  <div class="form-actions"><button type="submit" class="btn btn-primary">入力内容を保存</button></div>
</form>
<?php endif; ?>
