<div class="page-head">
  <h1><?= h($title) ?> <small class="muted">— <?= h($item['item_code']) ?> <?= h($item['item_name']) ?></small></h1>
  <a class="btn btn-ghost" href="<?= h(url('item', ['id' => $item['id']])) ?>">品目詳細へ戻る</a>
</div>
<form method="post" action="<?= h(url('unit', ['action' => 'save'])) ?>" class="form-grid" data-dirty-check>
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= h($unit['id']) ?>">
  <input type="hidden" name="item_id" value="<?= h($unit['item_id']) ?>">
  <label>管理No<input type="text" name="management_no" value="<?= h($unit['management_no']) ?>" maxlength="50" placeholder="例: NKC：0006"></label>
  <label>シリアル番号<input type="text" name="serial_number" value="<?= h($unit['serial_number']) ?>" maxlength="100"></label>
  <label>IPアドレス<input type="text" name="ip_address" value="<?= h($unit['ip_address']) ?>" maxlength="100"></label>
  <label>状態<select name="status"><?php foreach (unit_status_options() as $k => $v): ?><option value="<?= h($k) ?>" <?= $unit['status'] === $k ? 'selected' : '' ?>><?= h($v) ?></option><?php endforeach; ?></select></label>
  <label>保管場所<select name="location_id"><option value="">(未設定)</option><?php foreach ($locations as $l): ?><option value="<?= h($l['id']) ?>" <?= $unit['location_id'] == $l['id'] ? 'selected' : '' ?>><?= h($l['name']) ?></option><?php endforeach; ?></select></label>
  <label class="span2">備考<textarea name="notes" rows="3"><?= h($unit['notes']) ?></textarea></label>
  <div class="span2 form-actions">
    <button type="submit" class="btn btn-primary">保存</button>
    <?php if ($unit['id'] && can('item.deactivate')): ?>
      <button type="submit" class="btn <?= (int)$unit['is_active'] ? 'btn-danger' : '' ?>" formaction="<?= h(url('unit', ['action' => 'toggle_active'])) ?>" formnovalidate data-confirm="<?= (int)$unit['is_active'] ? 'この個体を無効にします。よろしいですか?' : 'この個体を再有効化します。よろしいですか?' ?>"><?= (int)$unit['is_active'] ? '無効にする' : '再有効化' ?></button>
    <?php endif; ?>
  </div>
</form>
