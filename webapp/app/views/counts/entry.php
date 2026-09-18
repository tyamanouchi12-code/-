<div class="page-head">
  <h1><?= h($count['count_name']) ?> <span class="badge badge-<?= h($count['status']) ?>"><?= h(count_status_label($count['status'])) ?></span></h1>
  <div>
    <?php if ($count['status'] === 'preparing'): ?>
      <form method="post" action="<?= h(url('count', ['action' => 'status'])) ?>" class="inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= h($count['id']) ?>"><input type="hidden" name="to" value="in_progress"><button class="btn btn-primary" type="submit">棚卸を開始</button></form>
    <?php elseif ($count['status'] === 'in_progress'): ?>
      <?php if (can('count.confirm')): ?>
      <form method="post" action="<?= h(url('count', ['action' => 'status'])) ?>" class="inline" data-confirm="この棚卸を締めます(全体確定)。締めた後は各行の入力ができなくなります(権限があれば解除できます)。よろしいですか?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= h($count['id']) ?>"><input type="hidden" name="to" value="confirmed"><button class="btn btn-success" type="submit">棚卸を締める(全体確定)</button></form>
      <?php else: ?><span class="muted">棚卸を締めるのは権限のある利用者が行います</span><?php endif; ?>
    <?php elseif ($count['status'] === 'confirmed' && can('count.confirm')): ?>
      <form method="post" action="<?= h(url('count', ['action' => 'status'])) ?>" class="inline" data-confirm="締めを解除して再入力できるようにします。よろしいですか?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= h($count['id']) ?>"><input type="hidden" name="to" value="in_progress"><button class="btn" type="submit">締めを解除</button></form>
    <?php endif; ?>
    <a class="btn btn-ghost" href="<?= h(url('count', ['action' => 'edit', 'id' => $count['id']])) ?>">棚卸情報を編集</a>
    <a class="btn btn-ghost" href="<?= h(url('counts')) ?>">一覧へ</a>
  </div>
</div>

<?php if ($editable): ?>
<div class="flow-hint">
  <strong>入力の流れ</strong>: ① 各行に今回の数量を入れて右端の<span class="btn btn-sm btn-primary btn-fake">確定</span>を押す(その行だけ保存されます) → ② 全部終わったら右上の「棚卸を締める(全体確定)」を押す
</div>
<?php endif; ?>

<div class="cards cards-3">
  <div class="card"><div class="card-label">基準日</div><div class="card-value small"><?= h(fmt_date($count['base_date'])) ?></div><div class="card-sub">期間: <?= h($count['count_period']) ?: '–' ?> / 入力日付: <?= h(fmt_date($count['entry_date'])) ?: '–' ?></div></div>
  <div class="card"><div class="card-label">入力済み / 表示中の品目</div><div class="card-value small"><span id="sum-entered"><?= h($summary['entered']) ?></span> / <?= count($items) ?></div><div class="card-sub">数量合計 <span id="sum-total"><?= h($summary['total']) ?></span></div></div>
  <div class="card"><div class="card-label">前回との差異あり</div><div class="card-value small"><?= h($summary['diff']) ?></div><div class="card-sub">前回: <?= $prevCount ? h($prevCount['count_name']) . '(' . h(fmt_date($prevCount['base_date'])) . ')' : 'なし' ?></div></div>
</div>

<section class="cat-summary">
  <div class="page-head"><h2>カテゴリ別集計</h2><div><span class="muted">行をクリックすると、そのカテゴリの品目だけが下の表に表示されます</span> <button type="button" class="btn btn-sm" id="cat-show-all">全件表示</button></div></div>
  <div class="table-wrap"><table class="table table-catsum" id="cat-table">
    <thead><tr><th>カテゴリ</th><th class="num">品目数</th><th class="num">入力済み</th><th class="num">今回合計</th><th class="num">前回合計</th><th class="num">差異</th></tr></thead>
    <tbody>
    <?php $gt = ['item_count' => 0, 'entered' => 0, 'total' => 0, 'prev_total' => 0, 'diff' => 0]; ?>
    <?php foreach ($byCat as $g): foreach ($gt as $k => $v) { $gt[$k] += $g[$k]; } ?>
      <tr class="cat-row <?= $catFilter === $g['category_id'] ? 'selected' : '' ?>" data-cat="<?= h($g['category_id']) ?>" title="クリックでこのカテゴリだけ表示">
        <td><a href="<?= h(url('count_entry', ['id' => $count['id'], 'category' => $g['category_id']])) ?>" class="cat-link"><?= $g['category_id'] === 0 ? '<span class="badge badge-warn">' . h($g['name']) . '</span>' : h($g['name']) ?></a></td>
        <td class="num"><?= h($g['item_count']) ?></td>
        <td class="num"><span class="cat-entered" data-cat="<?= h($g['category_id']) ?>"><?= h($g['entered']) ?></span><?= $g['entered'] < $g['item_count'] ? ' <small class="muted">/ ' . h($g['item_count']) . '</small>' : '' ?></td>
        <td class="num"><strong class="cat-total" data-cat="<?= h($g['category_id']) ?>"><?= h($g['total']) ?></strong></td>
        <td class="num"><?= $g['prev_count'] ? h($g['prev_total']) : '<span class="muted">–</span>' ?></td>
        <td class="num <?= $g['diff'] > 0 ? 'diff-plus' : ($g['diff'] < 0 ? 'diff-minus' : '') ?>"><?= $g['prev_count'] ? h(($g['diff'] > 0 ? '+' : '') . $g['diff']) : '' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot><tr><th>合計</th><th class="num"><?= h($gt['item_count']) ?></th><th class="num"><?= h($gt['entered']) ?></th><th class="num"><?= h($gt['total']) ?></th><th class="num"><?= h($gt['prev_total']) ?></th><th class="num <?= $gt['diff'] > 0 ? 'diff-plus' : ($gt['diff'] < 0 ? 'diff-minus' : '') ?>"><?= h(($gt['diff'] > 0 ? '+' : '') . $gt['diff']) ?></th></tr></tfoot>
  </table></div>
</section>

<form method="get" class="filter-bar">
  <input type="hidden" name="page" value="count_entry"><input type="hidden" name="id" value="<?= h($count['id']) ?>">
  <select name="category" onchange="this.form.submit()">
    <option value="">カテゴリ: すべて</option>
    <?php foreach ($categories as $c): ?><option value="<?= h($c['id']) ?>" <?= $catFilter === (int)$c['id'] ? 'selected' : '' ?>><?= h($c['name']) ?></option><?php endforeach; ?>
    <option value="0" <?= $catFilter === 0 ? 'selected' : '' ?>>(カテゴリ未設定)</option>
  </select>
  <select name="location" onchange="this.form.submit()">
    <option value="">保管場所: すべて</option>
    <?php foreach ($locations as $l): ?><option value="<?= h($l['id']) ?>" <?= $locFilter === (int)$l['id'] ? 'selected' : '' ?>><?= h($l['name']) ?></option><?php endforeach; ?>
    <option value="0" <?= $locFilter === 0 ? 'selected' : '' ?>>(保管場所 未設定)</option>
  </select>
  <input type="text" id="quick-filter" placeholder="この表の中を絞り込み(品名・管理No)" class="w-wide">
  <label class="inline-check"><input type="checkbox" id="only-unentered"> 未入力のみ表示</label>
  <label class="inline-check"><input type="checkbox" id="only-diff"> 差異ありのみ表示</label>
</form>

<h2 id="entry-title">品目ごとの入力 <span class="muted" id="entry-scope"><?= $catFilter !== null ? '(カテゴリで絞り込み中)' : '(全件)' ?></span></h2>
<?php if ($editable): ?>
<form method="post" action="<?= h(url('count_entry', ['action' => 'save', 'id' => $count['id']] + ($locFilter !== null ? ['location' => $locFilter] : []) + ($catFilter !== null ? ['category' => $catFilter] : []))) ?>" id="entry-form">
  <?= csrf_field() ?>
<?php endif; ?>
<div class="table-wrap"><table class="table table-entry table-cards" id="entry-table">
  <thead><tr><th>#</th><th>品目コード</th><th>品名</th><th>カテゴリ</th><th>状態</th><th>保管場所</th><th class="num">前回</th><th class="num">今回</th><th class="num">差異</th><th>明細備考</th><th>担当 / 日時</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($items as $n => $it): $iid = (int)$it['id']; $isUnit = $it['management_type'] === 'unit' && $it['units']; ?>
    <tr class="entry-row <?= $it['count_quantity'] === null ? 'unentered' : 'entered' ?> <?= $it['diff'] ? 'has-diff' : '' ?> <?= (int)$it['is_active'] ? '' : 'row-inactive' ?>" data-item="<?= $iid ?>" data-cat="<?= h($it['category_id'] === null ? 0 : (int)$it['category_id']) ?>" data-prev="<?= h($it['prev_quantity']) ?>" data-text="<?= h(mb_strtolower(($it['item_name'] ?? '') . ' ' . $it['item_code'] . ' ' . implode(' ', array_column($it['units'], 'management_no')))) ?>">
      <td class="num" data-label="#"><?= $n + 1 ?></td>
      <td class="nowrap" data-label="品目コード"><a href="<?= h(url('item', ['id' => $iid])) ?>" target="_blank"><?= h($it['item_code']) ?></a><?php if ($editable): ?><input type="hidden" name="touched[<?= $iid ?>]" value="1"><?php endif; ?></td>
      <td data-label="品名"><span class="item-name"><?= $it['item_name'] === null ? '<span class="muted">(品名なし)</span>' : h($it['item_name']) ?></span><?= (int)$it['is_active'] ? '' : ' <span class="badge badge-off">無効</span>' ?>
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
      <td data-label="カテゴリ"><?= $it['category_name'] === null ? '<span class="badge badge-warn">未設定</span>' : h($it['category_name']) ?></td>
      <td data-label="状態"><?= h(condition_name($it['condition_code'])) ?></td>
      <td data-label="保管場所"><?= h($it['location_name']) ?></td>
      <td class="num" data-label="前回"><?= $it['prev_quantity'] === null ? '<span class="muted">–</span>' : h($it['prev_quantity']) ?></td>
      <td class="num" data-label="今回">
        <?php if ($editable && !$isUnit): ?>
          <input type="text" inputmode="numeric" name="qty[<?= $iid ?>]" value="<?= h($it['count_quantity']) ?>" class="qty-input" data-prev="<?= h($it['prev_quantity']) ?>" data-item="<?= $iid ?>">
        <?php elseif ($editable && $isUnit): ?>
          <span class="qty-auto" data-item="<?= $iid ?>"><?= $it['count_quantity'] === null ? '–' : h($it['count_quantity']) ?></span><small class="muted"> (自動)</small>
        <?php else: ?>
          <?= $it['count_quantity'] === null ? '<span class="muted">–</span>' : '<strong>' . h($it['count_quantity']) . '</strong>' ?>
        <?php endif; ?>
      </td>
      <td class="num diff-cell <?= $it['diff'] === null ? '' : ($it['diff'] > 0 ? 'diff-plus' : ($it['diff'] < 0 ? 'diff-minus' : '')) ?>" data-item="<?= $iid ?>" data-label="差異"><?= $it['diff'] === null ? '' : h(($it['diff'] > 0 ? '+' : '') . $it['diff']) ?></td>
      <td data-label="明細備考"><?php if ($editable): ?><input type="text" name="dnotes[<?= $iid ?>]" value="<?= h($it['detail_notes']) ?>" class="w-notes" placeholder="この棚卸だけのメモ"><?php else: ?><?= nl2br(h($it['detail_notes'])) ?><?php endif; ?></td>
      <td class="small counted-cell" data-label="担当 / 日時"><?= h($it['counted_by']) ?><?= $it['counted_at'] ? '<br>' . h(fmt_datetime($it['counted_at'])) : '' ?></td>
      <td class="nowrap row-action" data-label="">
        <?php if ($editable): ?>
          <button type="submit" name="save_item" value="<?= $iid ?>" class="btn btn-sm btn-primary row-save" data-item="<?= $iid ?>">確定</button>
          <span class="save-state <?= $it['count_quantity'] === null ? '' : 'saved' ?>" data-item="<?= $iid ?>"><?= $it['count_quantity'] === null ? '' : '✓ 保存済' ?></span>
        <?php else: ?>
          <?= $it['count_quantity'] === null ? '<span class="muted">未入力</span>' : '<span class="badge badge-on">済</span>' ?>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<?php if ($editable): ?>
  <div class="form-actions"><button type="submit" class="btn" id="save-all">表示中の行をまとめて保存</button> <span class="muted">通常は各行の「確定」で保存してください</span></div>
</form>
<?php endif; ?>
