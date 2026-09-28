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
  <strong>入力の流れ</strong>: ① 内訳ごとに今回の数量(管理Noのある物は有/無)を入れて右端の<span class="btn btn-sm btn-primary btn-fake">確定</span>を押す(その行だけ保存されます) → ② 全部終わったら右上の「棚卸を締める(全体確定)」を押す
</div>
<?php endif; ?>

<div class="cards cards-3">
  <div class="card"><div class="card-label">基準日</div><div class="card-value small"><?= h(fmt_date($count['base_date'])) ?></div><div class="card-sub">期間: <?= h($count['count_period']) ?: '–' ?> / 入力日付: <?= h(fmt_date($count['entry_date'])) ?: '–' ?></div></div>
  <div class="card"><div class="card-label">入力済み / 表示中の内訳</div><div class="card-value small"><span id="sum-entered"><?= h($summary['entered']) ?></span> / <?= h($summary['units']) ?></div><div class="card-sub">数量合計 <span id="sum-total"><?= h($summary['total']) ?></span></div></div>
  <div class="card"><div class="card-label">前回との差異あり</div><div class="card-value small"><?= h($summary['diff']) ?></div><div class="card-sub">前回: <?= $prevCount ? h($prevCount['count_name']) . '(' . h(fmt_date($prevCount['base_date'])) . ')' : 'なし' ?></div></div>
</div>

<section class="cat-summary">
  <div class="page-head"><h2>品目(カテゴリ)別集計</h2><div><span class="muted">行をクリックすると、その品目の内訳だけが下の表に表示されます</span> <button type="button" class="btn btn-sm" id="cat-show-all">全件表示</button></div></div>
  <div class="table-wrap"><table class="table table-catsum" id="cat-table">
    <thead><tr><th>品目</th><th class="num">内訳数</th><th class="num">入力済み</th><th class="num">今回合計</th><th class="num">前回合計</th><th class="num">差異</th></tr></thead>
    <tbody>
    <?php $gt = ['unit_count' => 0, 'entered' => 0, 'total' => 0, 'prev_total' => 0, 'diff' => 0]; ?>
    <?php foreach ($byItem as $g): foreach ($gt as $k => $v) { $gt[$k] += $g[$k]; } ?>
      <tr class="cat-row <?= $itemFilter === $g['item_id'] ? 'selected' : '' ?>" data-cat="<?= h($g['item_id']) ?>" title="クリックでこの品目だけ表示">
        <td><a href="<?= h(url('count_entry', ['id' => $count['id'], 'item' => $g['item_id']])) ?>" class="cat-link"><?= h($g['name']) ?></a></td>
        <td class="num"><?= h($g['unit_count']) ?></td>
        <td class="num"><span class="cat-entered" data-cat="<?= h($g['item_id']) ?>"><?= h($g['entered']) ?></span><?= $g['entered'] < $g['unit_count'] ? ' <small class="muted">/ ' . h($g['unit_count']) . '</small>' : '' ?></td>
        <td class="num"><strong class="cat-total" data-cat="<?= h($g['item_id']) ?>"><?= h($g['total']) ?></strong></td>
        <td class="num"><?= $g['prev_count'] ? h($g['prev_total']) : '<span class="muted">–</span>' ?></td>
        <td class="num <?= $g['diff'] > 0 ? 'diff-plus' : ($g['diff'] < 0 ? 'diff-minus' : '') ?>"><?= $g['prev_count'] ? h(($g['diff'] > 0 ? '+' : '') . $g['diff']) : '' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot><tr><th>合計</th><th class="num"><?= h($gt['unit_count']) ?></th><th class="num"><?= h($gt['entered']) ?></th><th class="num"><?= h($gt['total']) ?></th><th class="num"><?= h($gt['prev_total']) ?></th><th class="num <?= $gt['diff'] > 0 ? 'diff-plus' : ($gt['diff'] < 0 ? 'diff-minus' : '') ?>"><?= h(($gt['diff'] > 0 ? '+' : '') . $gt['diff']) ?></th></tr></tfoot>
  </table></div>
</section>

<form method="get" class="filter-bar">
  <input type="hidden" name="page" value="count_entry"><input type="hidden" name="id" value="<?= h($count['id']) ?>">
  <select name="item" onchange="this.form.submit()">
    <option value="">品目: すべて</option>
    <?php foreach ($items as $it): ?><option value="<?= h($it['id']) ?>" <?= $itemFilter === (int)$it['id'] ? 'selected' : '' ?>><?= h($it['item_name']) ?></option><?php endforeach; ?>
  </select>
  <select name="location" onchange="this.form.submit()">
    <option value="">保管場所: すべて</option>
    <?php foreach ($locations as $l): ?><option value="<?= h($l['id']) ?>" <?= $locFilter === (int)$l['id'] ? 'selected' : '' ?>><?= h($l['name']) ?></option><?php endforeach; ?>
    <option value="0" <?= $locFilter === 0 ? 'selected' : '' ?>>(保管場所 未設定)</option>
  </select>
  <input type="text" id="quick-filter" placeholder="この表の中を絞り込み(内訳名・管理No)" class="w-wide">
  <label class="inline-check"><input type="checkbox" id="only-unentered"> 未入力のみ表示</label>
  <label class="inline-check"><input type="checkbox" id="only-diff"> 差異ありのみ表示</label>
</form>

<h2 id="entry-title">内訳ごとの入力 <span class="muted" id="entry-scope"><?= $itemFilter !== null ? '(品目で絞り込み中)' : '(全件)' ?></span></h2>
<?php if ($editable): ?>
<form method="post" action="<?= h(url('count_entry', ['action' => 'save', 'id' => $count['id']] + ($locFilter !== null ? ['location' => $locFilter] : []) + ($itemFilter !== null ? ['item' => $itemFilter] : []))) ?>" id="entry-form">
  <?= csrf_field() ?>
<?php endif; ?>
<div class="table-wrap"><table class="table table-entry table-cards" id="entry-table">
  <thead><tr><th>#</th><th>内訳名(品名)</th><th>状態</th><th>保管場所</th><th class="num">前回</th><th class="num">今回</th><th class="num">差異</th><th>メモ</th><th>担当 / 日時</th><th></th></tr></thead>
  <tbody>
  <?php $n = 0; foreach ($rows as $grp): $it = $grp['item']; $iid = (int)$it['id']; ?>
    <tr class="item-head" data-item="<?= $iid ?>">
      <td colspan="4" data-label="品目"><strong><?= h($it['item_name']) ?></strong> <small class="muted"><?= h($it['item_code']) ?> ／ 内訳 <?= count($grp['units']) ?> 件</small></td>
      <td class="num" data-label="前回合計"><?= $grp['prev_total'] === null ? '<span class="muted">–</span>' : h($grp['prev_total']) ?></td>
      <td class="num" data-label="今回合計"><strong class="item-total" data-item="<?= $iid ?>"><?= $it['count_quantity'] === null ? '–' : h($it['count_quantity']) ?></strong></td>
      <td colspan="4" data-label=""></td>
    </tr>
    <?php foreach ($grp['units'] as $u): $uid = (int)$u['id']; $isUnit = $u['management_no'] !== null && $u['management_no'] !== ''; $n++; ?>
    <tr class="entry-row <?= $u['counted_quantity'] === null ? 'unentered' : 'entered' ?> <?= $u['diff'] ? 'has-diff' : '' ?> <?= (int)$u['is_active'] ? '' : 'row-inactive' ?>" data-unit="<?= $uid ?>" data-item="<?= $iid ?>" data-cat="<?= $iid ?>" data-prev="<?= h($u['prev_quantity']) ?>" data-text="<?= h(mb_strtolower(($u['name'] ?? '') . ' ' . ($u['management_no'] ?? '') . ' ' . $it['item_name'])) ?>">
      <td class="num" data-label="#"><?= $n ?></td>
      <td data-label="内訳名"><span class="item-name"><?= $u['name'] === null ? '<span class="muted">(内訳名なし)</span>' : h($u['name']) ?></span><?= $isUnit ? ' <span class="badge">' . h($u['management_no']) . '</span>' : '' ?><?= (int)$u['is_active'] ? '' : ' <span class="badge badge-off">無効</span>' ?>
        <?php if ($editable): ?><input type="hidden" name="touched[<?= $uid ?>]" value="1"><?php endif; ?></td>
      <td data-label="状態"><?= h(condition_name($u['condition_code'])) ?></td>
      <td data-label="保管場所"><?= h($u['location_name']) ?></td>
      <td class="num" data-label="前回"><?= $u['prev_quantity'] === null ? '<span class="muted">–</span>' : h($u['prev_quantity']) ?></td>
      <td class="num" data-label="今回">
        <?php if ($editable && $isUnit): $res = $u['result'] ?? 'unchecked'; ?>
          <span class="unit-radios">
            <label><input type="radio" name="unit[<?= $uid ?>]" value="present" <?= $res === 'present' ? 'checked' : '' ?> data-unit-of="<?= $uid ?>"> 有</label>
            <label><input type="radio" name="unit[<?= $uid ?>]" value="absent" <?= $res === 'absent' ? 'checked' : '' ?> data-unit-of="<?= $uid ?>"> 無</label>
            <label><input type="radio" name="unit[<?= $uid ?>]" value="unchecked" <?= $res === 'unchecked' ? 'checked' : '' ?> data-unit-of="<?= $uid ?>"> 未確認</label>
          </span>
        <?php elseif ($editable): ?>
          <input type="text" inputmode="numeric" name="qty[<?= $uid ?>]" value="<?= h($u['counted_quantity']) ?>" class="qty-input" data-unit="<?= $uid ?>">
        <?php elseif ($isUnit): ?>
          <span class="badge badge-unit-<?= h($u['result'] ?? 'unchecked') ?>"><?= h(unit_result_label($u['result'] ?? 'unchecked')) ?></span>
        <?php else: ?>
          <?= $u['counted_quantity'] === null ? '<span class="muted">–</span>' : '<strong>' . h($u['counted_quantity']) . '</strong>' ?>
        <?php endif; ?>
      </td>
      <td class="num diff-cell <?= $u['diff'] === null ? '' : ($u['diff'] > 0 ? 'diff-plus' : ($u['diff'] < 0 ? 'diff-minus' : '')) ?>" data-label="差異"><?= $u['diff'] === null ? '' : h(($u['diff'] > 0 ? '+' : '') . $u['diff']) ?></td>
      <td data-label="メモ"><?php if ($editable): ?><input type="text" name="unotes[<?= $uid ?>]" value="<?= h($u['result_notes']) ?>" class="w-notes" placeholder="この棚卸だけのメモ"><?php else: ?><?= nl2br(h($u['result_notes'])) ?><?php endif; ?></td>
      <td class="small counted-cell" data-label="担当 / 日時"><?= $u['result_id'] ? h($u['result_by']) . '<br>' . h(fmt_datetime($u['result_at'])) : '' ?></td>
      <td class="nowrap row-action" data-label="">
        <?php if ($editable): ?>
          <button type="submit" name="save_unit" value="<?= $uid ?>" class="btn btn-sm btn-primary row-save" data-unit="<?= $uid ?>">確定</button>
          <span class="save-state <?= $u['counted_quantity'] === null ? '' : 'saved' ?>" data-unit="<?= $uid ?>"><?= $u['counted_quantity'] === null ? '' : '✓ 保存済' ?></span>
        <?php else: ?>
          <?= $u['counted_quantity'] === null ? '<span class="muted">未入力</span>' : '<span class="badge badge-on">済</span>' ?>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
  <?php endforeach; ?>
  </tbody>
</table></div>
<?php if ($editable): ?>
  <div class="form-actions"><button type="submit" class="btn" id="save-all">表示中の行をまとめて保存</button> <span class="muted">通常は各行の「確定」で保存してください</span></div>
</form>
<?php endif; ?>
