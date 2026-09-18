<div class="page-head">
  <h1><?= h($title) ?></h1>
  <a class="btn btn-ghost" href="<?= $count['id'] ? h(url('count_entry', ['id' => $count['id']])) : h(url('counts')) ?>">戻る</a>
</div>
<form method="post" action="<?= h(url('count', ['action' => 'save'])) ?>" class="form-grid" data-dirty-check>
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= h($count['id']) ?>">
  <label class="span2">棚卸名称 <span class="req">必須</span><input type="text" name="count_name" value="<?= h($count['count_name']) ?>" required maxlength="100"></label>
  <label>棚卸基準日 <span class="req">必須</span><input type="date" name="base_date" value="<?= h($count['base_date']) ?>" required></label>
  <label>棚卸期間<input type="text" name="count_period" value="<?= h($count['count_period']) ?>" maxlength="100" placeholder="例: 2025/12/26"></label>
  <label>入力日付<input type="date" name="entry_date" value="<?= h($count['entry_date']) ?>"></label>
  <label>状態<input type="text" value="<?= h(count_status_label($count['status'])) ?>" disabled></label>
  <label>棚卸開始日<input type="date" name="start_date" value="<?= h($count['start_date']) ?>"><small class="hint">空欄なら「開始」時に自動設定</small></label>
  <label>棚卸終了日<input type="date" name="end_date" value="<?= h($count['end_date']) ?>"><small class="hint">空欄なら「確定」時に自動設定</small></label>
  <label class="span2">備考<textarea name="notes" rows="3"><?= h($count['notes']) ?></textarea></label>
  <div class="span2 form-actions"><button type="submit" class="btn btn-primary">保存</button></div>
</form>
