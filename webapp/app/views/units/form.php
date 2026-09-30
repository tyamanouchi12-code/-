<div class="page-head">
  <h1><?= h($title) ?> <small class="muted">— カテゴリ: <?= h($item['item_code']) ?> <?= h($item['item_name']) ?></small></h1>
  <a class="btn btn-ghost" href="<?= h(url('item', ['id' => $item['id']])) ?>">カテゴリ詳細へ戻る</a>
</div>
<form method="post" action="<?= h(url('unit', ['action' => 'save'])) ?>" class="form-grid" data-dirty-check>
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= h($unit['id']) ?>">
  <label>品名 <span class="req">必須</span><input type="text" name="name" value="<?= h($unit['name']) ?>" required maxlength="200"></label>
  <label>カテゴリ<select name="item_id"><?php foreach ($itemsForMove as $i): ?><option value="<?= h($i['id']) ?>" <?= (int)$i['id'] === (int)$unit['item_id'] ? 'selected' : '' ?>><?= h($i['item_name']) ?></option><?php endforeach; ?></select><small class="hint">変更すると、この品名をそのカテゴリへ移します(棚卸履歴・持ち出し記録も一緒に移ります)</small></label>
  <label>状態(新品/中古)<select name="condition_code"><option value="">(未設定)</option><?php foreach ($conditions as $c): ?><option value="<?= h($c['code']) ?>" <?= $unit['condition_code'] === $c['code'] ? 'selected' : '' ?>><?= h($c['name']) ?></option><?php endforeach; ?></select></label>
  <label>管理No<input type="text" name="management_no" value="<?= h($unit['management_no']) ?>" maxlength="50" placeholder="1台ずつ管理する物のみ(例: NKC：0006)"><small class="hint">管理No を入れた品名は「1台」として扱い、棚卸では有/無で確認します</small></label>
  <label>シリアル番号<input type="text" name="serial_number" value="<?= h($unit['serial_number']) ?>" maxlength="100"></label>
  <label>IPアドレス<input type="text" name="ip_address" value="<?= h($unit['ip_address']) ?>" maxlength="100"></label>
  <label>メーカー<input type="text" name="manufacturer" value="<?= h($unit['manufacturer']) ?>" maxlength="100"></label>
  <label>型番<input type="text" name="model_number" value="<?= h($unit['model_number']) ?>" maxlength="100"></label>
  <label>客先<select name="customer_id"><option value="">(なし)</option><?php foreach ($customers as $c): ?><option value="<?= h($c['id']) ?>" <?= $unit['customer_id'] == $c['id'] ? 'selected' : '' ?>><?= h($c['name']) ?></option><?php endforeach; ?></select></label>
  <label>所在の状態<select name="status"><?php foreach (unit_status_options() as $k => $v): ?><option value="<?= h($k) ?>" <?= $unit['status'] === $k ? 'selected' : '' ?>><?= h($v) ?></option><?php endforeach; ?></select></label>
  <label>保管場所<select name="location_id"><option value="">(カテゴリと同じ)</option><?php foreach ($locations as $l): ?><option value="<?= h($l['id']) ?>" <?= $unit['location_id'] == $l['id'] ? 'selected' : '' ?>><?= h($l['name']) ?></option><?php endforeach; ?></select></label>
  <label>表示順<input type="number" name="sort_order" value="<?= h($unit['sort_order']) ?>"></label>
  <label class="span2">備考<textarea name="notes" rows="3"><?= h($unit['notes']) ?></textarea></label>
  <?php if (!empty($unit['source_excel_rows'])): ?><p class="span2 muted small">移行元: Excel シート1 <?= h(str_replace(';', '、', $unit['source_excel_rows'])) ?> 行目</p><?php endif; ?>
  <div class="span2 form-actions">
    <button type="submit" class="btn btn-primary">保存</button>
    <?php if (can('item.deactivate')): ?>
      <button type="submit" class="btn <?= (int)$unit['is_active'] ? 'btn-danger' : '' ?>" formaction="<?= h(url('unit', ['action' => 'toggle_active'])) ?>" formnovalidate data-confirm="<?= (int)$unit['is_active'] ? 'この品名を無効にします。棚卸履歴は残ります。よろしいですか?' : 'この品名を再有効化します。よろしいですか?' ?>"><?= (int)$unit['is_active'] ? '無効にする' : '再有効化' ?></button>
    <?php endif; ?>
  </div>
</form>
