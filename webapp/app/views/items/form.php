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
  <label>カテゴリ<select name="category_id"><option value="">(未設定)</option><?php foreach ($categories as $c): ?><option value="<?= h($c['id']) ?>" <?= $item['category_id'] == $c['id'] ? 'selected' : '' ?>><?= h($c['name']) ?></option><?php endforeach; ?></select></label>
  <label>保管場所<select name="location_id"><option value="">(未設定)</option><?php foreach ($locations as $l): ?><option value="<?= h($l['id']) ?>" <?= $item['location_id'] == $l['id'] ? 'selected' : '' ?>><?= h($l['name']) ?></option><?php endforeach; ?></select></label>
  <label>在庫区分<select name="stock_type"><?php foreach (stock_type_options() as $k => $v): ?><option value="<?= h($k) ?>" <?= $item['stock_type'] === $k ? 'selected' : '' ?>><?= h($v) ?></option><?php endforeach; ?></select></label>
  <label>客先<select name="customer_id"><option value="">(なし)</option><?php foreach ($customers as $c): ?><option value="<?= h($c['id']) ?>" <?= $item['customer_id'] == $c['id'] ? 'selected' : '' ?>><?= h($c['name']) ?></option><?php endforeach; ?></select></label>
  <label>メーカー<input type="text" name="manufacturer" value="<?= h($item['manufacturer']) ?>" maxlength="100"></label>
  <label>型番<input type="text" name="model_number" value="<?= h($item['model_number']) ?>" maxlength="100"></label>
  <label>シリアル番号<small class="hint">(個体管理品は個体側に登録)</small><input type="text" name="serial_number" value="<?= h($item['serial_number']) ?>" maxlength="100"></label>
  <label>IPアドレス・ネットワーク情報<input type="text" name="network_info" value="<?= h($item['network_info']) ?>" maxlength="255"></label>
  <label class="span2">備考<textarea name="notes" rows="4"><?= h($item['notes']) ?></textarea></label>
  <div class="span2 form-actions">
    <button type="submit" class="btn btn-primary">保存</button>
  </div>
</form>
