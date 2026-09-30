<div class="page-head">
  <h1><?= h($title) ?></h1>
  <?php if ($item['id']): ?><a class="btn btn-ghost" href="<?= h(url('item', ['id' => $item['id']])) ?>">詳細へ戻る</a><?php else: ?><a class="btn btn-ghost" href="<?= h(url('items')) ?>">一覧へ戻る</a><?php endif; ?>
</div>
<form method="post" action="<?= h(url('item', ['action' => 'save'])) ?>" class="form-grid wide" data-dirty-check>
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= h($item['id']) ?>">
  <label>カテゴリコード<input type="text" value="<?= h($item['item_code']) ?>" disabled></label>
  <label>表示順<input type="number" name="sort_order" value="<?= h($item['sort_order']) ?>" placeholder="未入力なら末尾"></label>
  <label class="span2">カテゴリ名 <span class="req">必須</span><input type="text" name="item_name" id="item-name" value="<?= h($item['item_name']) ?>" maxlength="200" required placeholder="例: 電源関連"></label>
  <label>保管場所(既定)<select name="location_id"><option value="">(未設定)</option><?php foreach ($locations as $l): ?><option value="<?= h($l['id']) ?>" <?= $item['location_id'] == $l['id'] ? 'selected' : '' ?>><?= h($l['name']) ?></option><?php endforeach; ?></select><small class="hint">品名で保管場所を空にした場合に使われます</small></label>
  <label>在庫区分<select name="stock_type"><?php foreach (stock_type_options() as $k => $v): ?><option value="<?= h($k) ?>" <?= $item['stock_type'] === $k ? 'selected' : '' ?>><?= h($v) ?></option><?php endforeach; ?></select></label>
  <label class="span2">カテゴリの備考<textarea name="notes" rows="2"><?= h($item['notes']) ?></textarea></label>

  <fieldset class="span2" id="unit-section">
    <legend>品名(実物の種類・個体)</legend>
    <p class="hint">1行が「数える単位」です。マウスやケーブルのように個数で数える物は種類ごとに1行(状態が新品と中古で分かれる場合は2行)、PCや無線APのように1台ずつ管理する物は<strong>管理Noを入れて1台1行</strong>にします。空の行は無視されます。<?php if ($item['id']): ?>登録済みの品名を無効にするには、行の「編集」から行います。<?php endif; ?></p>
    <div class="table-wrap"><table class="table table-units" id="unit-table">
      <thead><tr><th>品名 <span class="req">必須</span></th><th>状態</th><th>管理No</th><th>保管場所</th><th>備考</th><th></th></tr></thead>
      <tbody>
      <?php $ri = 0; foreach (($units ?? []) as $u): ?>
        <tr class="unit-input-row">
          <td><input type="hidden" name="units[<?= $ri ?>][id]" value="<?= h($u['id'] ?? '') ?>"><input type="text" name="units[<?= $ri ?>][name]" value="<?= h($u['name'] ?? '') ?>" maxlength="200" class="w-name"></td>
          <td><select name="units[<?= $ri ?>][condition_code]"><option value="">(未設定)</option><?php foreach ($conditions as $c): ?><option value="<?= h($c['code']) ?>" <?= ($u['condition_code'] ?? null) === $c['code'] ? 'selected' : '' ?>><?= h($c['name']) ?></option><?php endforeach; ?></select></td>
          <td><input type="text" name="units[<?= $ri ?>][management_no]" value="<?= h($u['management_no'] ?? '') ?>" maxlength="50" placeholder="1台ずつ管理する物のみ"></td>
          <td><select name="units[<?= $ri ?>][location_id]"><option value="">(カテゴリと同じ)</option><?php foreach ($locations as $l): ?><option value="<?= h($l['id']) ?>" <?= ($u['location_id'] ?? null) == $l['id'] ? 'selected' : '' ?>><?= h($l['name']) ?></option><?php endforeach; ?></select></td>
          <td><input type="text" name="units[<?= $ri ?>][notes]" value="<?= h($u['notes'] ?? '') ?>" maxlength="500"><input type="hidden" name="units[<?= $ri ?>][status]" value="<?= h($u['status'] ?? 'in_stock') ?>"></td>
          <td class="nowrap"><?= !empty($u['id']) ? '<a class="small" href="' . h(url('unit', ['action' => 'edit', 'id' => $u['id']])) . '">編集</a>' : '<button type="button" class="btn btn-sm unit-remove">削除</button>' ?></td>
        </tr>
      <?php $ri++; endforeach; ?>
      </tbody>
    </table></div>
    <template id="unit-row-template">
      <tr class="unit-input-row">
        <td><input type="hidden" name="units[__i__][id]" value=""><input type="text" name="units[__i__][name]" maxlength="200" class="w-name"></td>
        <td><select name="units[__i__][condition_code]"><option value="">(未設定)</option><?php foreach ($conditions as $c): ?><option value="<?= h($c['code']) ?>"><?= h($c['name']) ?></option><?php endforeach; ?></select></td>
        <td><input type="text" name="units[__i__][management_no]" maxlength="50" placeholder="1台ずつ管理する物のみ"></td>
        <td><select name="units[__i__][location_id]"><option value="">(カテゴリと同じ)</option><?php foreach ($locations as $l): ?><option value="<?= h($l['id']) ?>"><?= h($l['name']) ?></option><?php endforeach; ?></select></td>
        <td><input type="text" name="units[__i__][notes]" maxlength="500"><input type="hidden" name="units[__i__][status]" value="in_stock"></td>
        <td class="nowrap"><button type="button" class="btn btn-sm unit-remove">削除</button></td>
      </tr>
    </template>
    <button type="button" class="btn btn-sm" id="unit-add">＋ 品名の行を追加</button>
  </fieldset>

  <div class="span2 form-actions">
    <button type="submit" class="btn btn-primary">保存</button>
  </div>
</form>
