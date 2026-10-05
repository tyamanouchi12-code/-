<div class="page-head">
  <h1><?= h($title) ?><?php if ($item): ?> <small class="muted">— カテゴリ: <?= h($item['item_name']) ?></small><?php endif; ?></h1>
  <div>
    <?php if ($item): ?>
      <a class="btn btn-ghost" href="<?= h(url('item', ['id' => $item['id']])) ?>">戻る</a>
    <?php elseif (!empty($unit['item_id'])): ?>
      <a class="btn btn-ghost" href="<?= h(url('item', ['id' => $unit['item_id']])) ?>">戻る</a>
    <?php else: ?>
      <a class="btn btn-ghost" href="<?= h(url('items')) ?>">戻る</a>
    <?php endif; ?>
  </div>
</div>
<form method="post" action="<?= h(url('unit', ['action' => $unit['id'] ? 'save' : 'create'])) ?>" class="form-grid" data-dirty-check>
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= h($unit['id']) ?>">
  <label>カテゴリ <span class="req">必須</span><select name="item_id" required><option value="">選択してください</option><?php foreach ($itemsForMove as $i): ?><option value="<?= h($i['id']) ?>" <?= (int)$i['id'] === (int)($unit['item_id'] ?? 0) ? 'selected' : '' ?>><?= h($i['item_name']) ?></option><?php endforeach; ?></select><?php if ($unit['id']): ?><small class="hint">変更すると、この品名をそのカテゴリへ移します(棚卸履歴・持ち出し記録も一緒に移ります)</small><?php endif; ?></label>
  <label>品名 <span class="req">必須</span><input type="text" name="name" value="<?= h($unit['name']) ?>" required maxlength="200"></label>
  <label>状態(新品/中古)<select name="condition_code"><option value="">(未設定)</option><?php foreach ($conditions as $c): ?><option value="<?= h($c['code']) ?>" <?= ($unit['condition_code'] ?? null) === $c['code'] ? 'selected' : '' ?>><?= h($c['name']) ?></option><?php endforeach; ?></select></label>
  <label>管理Noで管理する<span class="inline-check"><input type="checkbox" name="use_management_no" value="1" <?= (array_key_exists('use_management_no', $unit) ? $unit['use_management_no'] : !empty($unit['management_no'])) ? 'checked' : '' ?>></span><?php if (!empty($unit['management_no'])): ?><strong>No.<?= h(fmt_management_no($unit['management_no'])) ?></strong><?php endif; ?><small class="hint">チェックすると4桁の番号(0001〜)を自動で振ります。外すと番号は消えます</small></label>
  <label>数え方<select name="count_mode"><?php foreach (count_mode_options() as $k => $v): ?><option value="<?= h($k) ?>" <?= ($unit['count_mode'] ?? 'quantity') === $k ? 'selected' : '' ?>><?= h($v) ?></option><?php endforeach; ?></select><small class="hint">「1台ずつ」は棚卸で有/無を選び、持ち出しも1台単位になります。ケーブルなど個数で数える物は「個数で数える」</small></label>
  <label>保管場所<select name="location_id"><option value="">(カテゴリと同じ)</option><?php foreach ($locations as $l): ?><option value="<?= h($l['id']) ?>" <?= ($unit['location_id'] ?? null) == $l['id'] ? 'selected' : '' ?>><?= h($l['name']) ?></option><?php endforeach; ?></select></label>
  <label>客先<select name="customer_id"><option value="">(なし)</option><?php foreach ($customers as $c): ?><option value="<?= h($c['id']) ?>" <?= ($unit['customer_id'] ?? null) == $c['id'] ? 'selected' : '' ?>><?= h($c['name']) ?></option><?php endforeach; ?></select></label>
  <label>所在の状態<select name="status"><?php foreach (unit_status_options() as $k => $v): ?><option value="<?= h($k) ?>" <?= ($unit['status'] ?? 'in_stock') === $k ? 'selected' : '' ?>><?= h($v) ?></option><?php endforeach; ?></select></label>
  <?php if ($unit['id']): ?><label>表示順<input type="number" name="sort_order" value="<?= h($unit['sort_order']) ?>"></label><?php endif; ?>
  <label class="span2">備考<textarea name="notes" rows="3"><?= h($unit['notes']) ?></textarea></label>
  <?php if (!empty($unit['source_excel_rows'])): ?><p class="span2 muted small">移行元: Excel シート1 <?= h(str_replace(';', '、', $unit['source_excel_rows'])) ?> 行目</p><?php endif; ?>
  <div class="span2 form-actions">
    <button type="submit" class="btn btn-primary"><?= $unit['id'] ? '保存' : '追加' ?></button>
    <?php if (!$unit['id']): ?><button type="submit" class="btn" name="continue" value="1">追加して続けて入力</button><?php endif; ?>
    <?php if ($unit['id'] && can('item.deactivate')): ?>
      <button type="submit" class="btn" formaction="<?= h(url('unit', ['action' => 'toggle_active'])) ?>" formnovalidate data-confirm="<?= (int)$unit['is_active'] ? 'この品名を無効にします。棚卸履歴は残ります。よろしいですか?' : 'この品名を再有効化します。よろしいですか?' ?>"><?= (int)$unit['is_active'] ? '無効にする' : '再有効化' ?></button>
      <?php if (empty($deleteBlockers)): ?>
        <button type="submit" class="btn btn-danger" formaction="<?= h(url('unit', ['action' => 'delete'])) ?>" formnovalidate data-confirm="この品名を削除します。元に戻せません。よろしいですか?">削除</button>
      <?php else: ?>
        <span class="muted small">削除できません(<?= h(implode('、', $deleteBlockers)) ?>)。使わなくする場合は「無効にする」を使ってください</span>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</form>
