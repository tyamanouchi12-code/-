<h1>パスワード変更</h1>
<form method="post" action="<?= h(url('password')) ?>" class="form-grid narrow">
  <?= csrf_field() ?>
  <label class="span2">現在のパスワード<input type="password" name="current_password" required autocomplete="current-password"></label>
  <label class="span2">新しいパスワード(8文字以上)<input type="password" name="password" required autocomplete="new-password"></label>
  <label class="span2">新しいパスワード(確認)<input type="password" name="password2" required autocomplete="new-password"></label>
  <div class="span2 form-actions"><button type="submit" class="btn btn-primary">変更する</button></div>
</form>
