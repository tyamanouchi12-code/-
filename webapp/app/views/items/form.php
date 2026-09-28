<div class="page-head">
  <h1><?= h($title) ?></h1>
  <?php if ($item['id']): ?><a class="btn btn-ghost" href="<?= h(url('item', ['id' => $item['id']])) ?>">詳細へ戻る</a><?php else: ?><a class="btn btn-ghost" href="<?= h(url('items')) ?>">一覧へ戻る</a><?php endif; ?>
</div>
<form method="post" action="<?= h(url('item', ['action' => 'save'])) ?>" class="form-grid" data-dirty-check>
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= h($item['id']) ?>">
  <label>品目コード<input type="text" value="<?= h($item['item_code']) ?>" disabled></label>
  <label>表示順<input type="number" name="sort_order" value="<?= h($item['sort_order']) ?>" placeholder="未入力なら末尾"></label>
  <label class="span2">品名 <span class="req">必須</span><input type="text" name="item_name" value="<?= h($item['item_name']) ?>" required maxlength="200"></label>
  <label>状態<select name="condition_code"><option value="">(未設定)</option><?php foreach ($conditions as $c): ?><option value="<?= h($c['code']) ?>" <?= $item['condition_code'] === $c['code'] ? 'selected' : '' ?>><?= h($c['name']) ?></option><?php endforeach; ?></select></label>
  <label>管理方法<select name="management_type"><?php foreach (management_type_options() as $k => $v): ?><option value="<?= h($k) ?>" <?= $item['management_type'] === $k ? 'selected' : '' ?>><?= h($v) ?></option><?php endforeach; ?></select><small class="hint">個体管理: 管理Noを持つ実物1台ごとに記録(PC・ネットワーク機器など)。数量管理: 個数だけを数える(ケーブル・マウスなど)</small></label>
  <label>カテゴリ <span class="req">必須</span><select name="category_id" required><option value="">選択してください</option><?php foreach ($categories as $c): ?><option value="<?= h($c['id']) ?>" <?= $item['category_id'] == $c['id'] ? 'selected' : '' ?>><?= h($c['name']) ?></option><?php endforeach; ?></select></label>
  <label>保管場所<select name="location_id"><option value="">(未設定)</option><?php foreach ($locations as $l): ?><option value="<?= h($l['id']) ?>" <?= $item['location_id'] == $l['id'] ? 'selected' : '' ?>><?= h($l['name']) ?></option><?php endforeach; ?></select></label>
  <label>在庫区分<select name="stock_type"><?php foreach (stock_type_options() as $k => $v): ?><option value="<?= h($k) ?>" <?= $item['stock_type'] === $k ? 'selected' : '' ?>><?= h($v) ?></option><?php endforeach; ?></select></label>
  <label>客先<select name="customer_id"><option value="">(なし)</option><?php foreach ($customers as $c): ?><option value="<?= h($c['id']) ?>" <?= $item['customer_id'] == $c['id'] ? 'selected' : '' ?>><?= h($c['name']) ?></option><?php endforeach; ?></select></label>
  <label>メーカー<input type="text" name="manufacturer" value="<?= h($item['manufacturer']) ?>" maxlength="100"></label>
  <label>型番<input type="text" name="model_number" value="<?= h($item['model_number']) ?>" maxlength="100"></label>
  <label>シリアル番号<small class="hint">(個体管理品は個体側に登録)</small><input type="text" name="serial_number" value="<?= h($item['serial_number']) ?>" maxlength="100"></label>
  <label>IPアドレス・ネットワーク情報<input type="text" name="network_info" value="<?= h($item['network_info']) ?>" maxlength="255"></label>
  <label class="span2">備考<textarea name="notes" rows="4"><?= h($item['notes']) ?></textarea></label>

  <fieldset class="span2" id="unit-section" <?= $item['management_type'] === 'unit' ? '' : 'hidden' ?>>
    <legend>個体情報(管理方法が「個体管理」のとき)</legend>
    <p class="hint">実物1台ごとに1行入力します。管理No またはシリアル番号のどちらかは必須です。空の行は無視されます。<?php if ($item['id']): ?>登録済みの個体を無効にするには、品目詳細の個体一覧から「編集」→「無効にする」を使ってください。<?php endif; ?></p>
    <div class="table-wrap"><table class="table table-units" id="unit-table">
      <thead><tr><th>管理No</th><th>シリアル番号</th><th>IPアドレス</th><th>状態</th><th>保管場所</th><th>備考</th><th></th></tr></thead>
      <tbody>
      <?php $unitRows = $units ?? []; $ri = 0; ?>
      <?php foreach ($unitRows as $u): ?>
        <tr class="unit-input-row">
          <td><input type="hidden" name="units[<?= $ri ?>][id]" value="<?= h($u['id'] ?? '') ?>"><input type="text" name="units[<?= $ri ?>][management_no]" value="<?= h($u['management_no'] ?? '') ?>" maxlength="50" placeholder="例: NKC：0006"></td>
          <td><input type="text" name="units[<?= $ri ?>][serial_number]" value="<?= h($u['serial_number'] ?? '') ?>" maxlength="100"></td>
          <td><input type="text" name="units[<?= $ri ?>][ip_address]" value="<?= h($u['ip_address'] ?? '') ?>" maxlength="100"></td>
          <td><select name="units[<?= $ri ?>][status]"><?php foreach (unit_status_options() as $k => $v): ?><option value="<?= h($k) ?>" <?= ($u['status'] ?? 'in_stock') === $k ? 'selected' : '' ?>><?= h($v) ?></option><?php endforeach; ?></select></td>
          <td><select name="units[<?= $ri ?>][location_id]"><option value="">(品目と同じ)</option><?php foreach ($locations as $l): ?><option value="<?= h($l['id']) ?>" <?= ($u['location_id'] ?? null) == $l['id'] ? 'selected' : '' ?>><?= h($l['name']) ?></option><?php endforeach; ?></select></td>
          <td><input type="text" name="units[<?= $ri ?>][notes]" value="<?= h($u['notes'] ?? '') ?>" maxlength="500"></td>
          <td class="nowrap"><?= !empty($u['id']) ? '<span class="muted small">登録済</span>' : '<button type="button" class="btn btn-sm unit-remove">削除</button>' ?></td>
        </tr>
      <?php $ri++; endforeach; ?>
      </tbody>
    </table></div>
    <template id="unit-row-template">
      <tr class="unit-input-row">
        <td><input type="hidden" name="units[__i__][id]" value=""><input type="text" name="units[__i__][management_no]" maxlength="50" placeholder="例: NKC：0006"></td>
        <td><input type="text" name="units[__i__][serial_number]" maxlength="100"></td>
        <td><input type="text" name="units[__i__][ip_address]" maxlength="100"></td>
        <td><select name="units[__i__][status]"><?php foreach (unit_status_options() as $k => $v): ?><option value="<?= h($k) ?>"><?= h($v) ?></option><?php endforeach; ?></select></td>
        <td><select name="units[__i__][location_id]"><option value="">(品目と同じ)</option><?php foreach ($locations as $l): ?><option value="<?= h($l['id']) ?>"><?= h($l['name']) ?></option><?php endforeach; ?></select></td>
        <td><input type="text" name="units[__i__][notes]" maxlength="500"></td>
        <td class="nowrap"><button type="button" class="btn btn-sm unit-remove">削除</button></td>
      </tr>
    </template>
    <button type="button" class="btn btn-sm" id="unit-add">＋ 個体の行を追加</button>
  </fieldset>

  <div class="span2 form-actions">
    <button type="submit" class="btn btn-primary">保存</button>
  </div>
</form>
