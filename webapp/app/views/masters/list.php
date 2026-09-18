<h1>マスタ管理</h1>
<div class="tabs">
  <?php foreach ($types as $k => $t): ?><a href="<?= h(url('masters', ['type' => $k])) ?>" class="<?= $k === $type ? 'active' : '' ?>"><?= h($t['label']) ?></a><?php endforeach; ?>
</div>

<section>
  <h2><?= h($def['label']) ?>を追加</h2>
  <form method="post" action="<?= h(url('masters', ['type' => $type, 'action' => 'save'])) ?>" class="inline-form">
    <?= csrf_field() ?>
    <?php if ($type === 'conditions'): ?><input type="text" name="code" placeholder="コード(半角英字 例: defective)" required pattern="[a-z0-9_]{1,20}"><?php endif; ?>
    <input type="text" name="name" placeholder="名称" required maxlength="100" class="w-wide">
    <?php if ($def['has_sort']): ?><input type="number" name="sort_order" placeholder="表示順(空欄で末尾)" class="w-num"><?php endif; ?>
    <button type="submit" class="btn btn-primary">追加</button>
  </form>
</section>

<section>
  <h2><?= h($def['label']) ?>一覧</h2>
  <table class="table">
    <thead><tr><th><?= $type === 'conditions' ? 'コード' : 'ID' ?></th><th>名称</th><?php if ($def['has_sort']): ?><th class="num">表示順</th><?php endif; ?><th>有効</th><th class="num">品目での使用数</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): $k = (string)$r[$def['key']]; ?>
      <tr class="<?= (int)$r['is_active'] ? '' : 'row-inactive' ?>">
        <form method="post" action="<?= h(url('masters', ['type' => $type, 'action' => 'save'])) ?>">
        <?= csrf_field() ?><input type="hidden" name="key" value="<?= h($k) ?>">
        <td><?= h($k) ?></td>
        <td><input type="text" name="name" value="<?= h($r['name']) ?>" required maxlength="100" class="w-wide"></td>
        <?php if ($def['has_sort']): ?><td class="num"><input type="number" name="sort_order" value="<?= h($r['sort_order']) ?>" class="w-num"></td><?php endif; ?>
        <td><select name="is_active"><option value="1" <?= (int)$r['is_active'] ? 'selected' : '' ?>>有効</option><option value="0" <?= (int)$r['is_active'] ? '' : 'selected' ?>>無効</option></select></td>
        <td class="num"><?= h($usage[$k] ?? 0) ?></td>
        <td><button type="submit" class="btn btn-sm">保存</button></td>
        </form>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <p class="muted">名称の変更は、その値を使っている全品目に反映されます。使わなくなった値は削除せず「無効」にしてください(選択肢に出なくなります)。</p>
</section>
