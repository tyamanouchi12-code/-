<?php $me = current_user(); ?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($title ?? '') ?> - <?= h(APP_NAME) ?></title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header class="topbar">
  <div class="topbar-inner">
    <a class="brand" href="<?= h(url('dashboard')) ?>"><?= h(APP_NAME) ?></a>
    <?php if ($me): ?>
    <nav class="nav">
      <a href="<?= h(url('items')) ?>" class="<?= ($view ?? '') === 'items/list' || str_starts_with($view ?? '', 'items/') || str_starts_with($view ?? '', 'units/') ? 'active' : '' ?>">品目</a>
      <a href="<?= h(url('counts')) ?>" class="<?= str_starts_with($view ?? '', 'counts/') ? 'active' : '' ?>">棚卸</a>
      <?php if (can('master.manage')): ?><a href="<?= h(url('masters')) ?>" class="<?= str_starts_with($view ?? '', 'masters/') ? 'active' : '' ?>">マスタ</a><?php endif; ?>
      <?php if (can('user.manage')): ?><a href="<?= h(url('users')) ?>" class="<?= str_starts_with($view ?? '', 'users/') ? 'active' : '' ?>">利用者</a><?php endif; ?>
    </nav>
    <div class="userbox">
      <span><?= h($me['display_name']) ?></span>
      <a href="<?= h(url('password')) ?>">パスワード変更</a>
      <form method="post" action="<?= h(url('logout')) ?>" class="inline"><?= csrf_field() ?><button type="submit" class="linklike">ログアウト</button></form>
    </div>
    <?php endif; ?>
  </div>
</header>
<main class="container">
  <?php foreach (flash_pull() as $f): ?>
    <div class="flash flash-<?= h($f['type']) ?>"><?= nl2br(h($f['message'])) ?></div>
  <?php endforeach; ?>
  <?php if (!empty($errors ?? [])): ?>
    <div class="flash flash-error"><ul><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul></div>
  <?php endif; ?>
  <?php view_include($view, get_defined_vars()); ?>
</main>
<script src="assets/app.js"></script>
</body>
</html>
