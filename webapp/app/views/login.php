<div class="login-box">
  <h1><?= h(APP_NAME) ?></h1>
  <form method="post" action="<?= h(url('login')) ?>">
    <?= csrf_field() ?>
    <label>ログインID<input type="text" name="login_id" value="<?= h($loginId ?? '') ?>" required autofocus autocomplete="username"></label>
    <label>パスワード<input type="password" name="password" required autocomplete="current-password"></label>
    <button type="submit" class="btn btn-primary btn-block">ログイン</button>
  </form>
</div>
