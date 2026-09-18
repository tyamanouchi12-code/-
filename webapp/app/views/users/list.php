<div class="page-head">
  <h1>利用者管理</h1>
  <a class="btn btn-primary" href="<?= h(url('user', ['action' => 'new'])) ?>">＋ 利用者を追加</a>
</div>
<table class="table">
  <thead><tr><th>ログインID</th><th>表示名</th><th>有効</th><th>権限</th><th>最終ログイン</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($users as $u): $codes = $u['perms'] ? explode(',', $u['perms']) : []; ?>
    <tr class="<?= (int)$u['is_active'] ? '' : 'row-inactive' ?>">
      <td><?= h($u['login_id']) ?></td>
      <td><?= h($u['display_name']) ?></td>
      <td><?= (int)$u['is_active'] ? '有効' : '<span class="badge badge-off">無効</span>' ?></td>
      <td><?php foreach ($perms as $code => $label): ?><span class="badge <?= in_array($code, $codes, true) ? 'badge-on' : 'badge-none' ?>"><?= h(mb_substr($label, 0, mb_strpos($label . '(', '('))) ?></span> <?php endforeach; ?></td>
      <td><?= h(fmt_datetime($u['last_login_at'])) ?></td>
      <td><a class="btn btn-sm" href="<?= h(url('user', ['action' => 'edit', 'id' => $u['id']])) ?>">編集</a></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<p class="muted">権限がない操作はメニューやボタンが表示されません。利用者を削除する代わりに「無効」にしてください(登録者・更新者の記録が残ります)。</p>
