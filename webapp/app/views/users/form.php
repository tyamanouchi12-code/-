<div class="page-head">
  <h1><?= h($title) ?></h1>
  <a class="btn btn-ghost" href="<?= h(url('users')) ?>">一覧へ戻る</a>
</div>
<form method="post" action="<?= h(url('user', ['action' => 'save'])) ?>" class="form-grid" data-dirty-check>
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= h($user['id']) ?>">
  <label>ログインID <span class="req">必須</span><input type="text" name="login_id" value="<?= h($user['login_id']) ?>" required maxlength="50" autocomplete="off"></label>
  <label>表示名 <span class="req">必須</span><input type="text" name="display_name" value="<?= h($user['display_name']) ?>" required maxlength="100"><small class="hint">登録者・更新者としてこの名前が記録されます</small></label>
  <label>パスワード<?= $user['id'] ? '<small class="hint">(変更する場合のみ入力)</small>' : ' <span class="req">必須</span>' ?><input type="password" name="password" autocomplete="new-password" <?= $user['id'] ? '' : 'required' ?>></label>
  <label>パスワード(確認)<input type="password" name="password2" autocomplete="new-password"></label>
  <label>有効/無効<select name="is_active"><option value="1" <?= (int)$user['is_active'] ? 'selected' : '' ?>>有効</option><option value="0" <?= (int)$user['is_active'] ? '' : 'selected' ?>>無効(ログイン不可)</option></select></label>
  <fieldset class="span2">
    <legend>権限</legend>
    <?php foreach ($perms as $code => $label): ?>
      <label class="check"><input type="checkbox" name="permissions[]" value="<?= h($code) ?>" <?= in_array($code, $user['permissions'], true) ? 'checked' : '' ?>> <?= h($label) ?></label>
    <?php endforeach; ?>
    <small class="hint">品目・個体・棚卸の登録・編集・入力は全員が行えます。上の権限は追加の操作に必要です。</small>
  </fieldset>
  <div class="span2 form-actions"><button type="submit" class="btn btn-primary">保存</button></div>
</form>
