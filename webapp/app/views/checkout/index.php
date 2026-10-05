<div class="page-head">
  <h1>持ち出し・戻し</h1>
</div>
<div class="mode-toggle">
  <a href="<?= h(url('checkout', ['mode' => 'out'])) ?>" class="<?= $mode === 'out' ? 'active' : '' ?>">持ち出し</a>
  <a href="<?= h(url('checkout', ['mode' => 'in'])) ?>" class="<?= $mode === 'in' ? 'active' : '' ?>">戻し <?= $open ? '<span class="count">' . count($open) . '</span>' : '' ?></a>
</div>

<?php if ($mode === 'out'): ?>
<form method="post" action="<?= h(url('checkout', ['action' => 'out'])) ?>" class="mobile-form" id="checkout-form">
  <?= csrf_field() ?>
  <label>カテゴリ <span class="req">必須</span>
    <select name="item_id" id="item-select" required>
      <option value="">選択してください</option>
      <?php foreach ($items as $i): ?>
        <option value="<?= h($i['id']) ?>" <?= $preItem === (int)$i['id'] ? 'selected' : '' ?>><?= h($i['item_name']) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>品名(個体) <span class="req">必須</span>
    <select name="unit_id" id="unit-select" required>
      <option value="">先にカテゴリを選択してください</option>
      <?php foreach ($units as $iid => $list): foreach ($list as $u): $isUnit = unit_is_single($u); ?>
        <option value="<?= h($u['id']) ?>" data-item="<?= h($iid) ?>" data-unit="<?= $isUnit ? '1' : '0' ?>" data-text="<?= h(mb_strtolower(($u['name'] ?? '') . ' ' . fmt_management_no($u['management_no'] ?? null))) ?>" <?= ($isUnit && (int)$u['open_count']) ? 'disabled' : '' ?>>
          <?= h($u['name'] ?? '(品名なし)') ?><?= $u['condition_name'] ? '(' . h($u['condition_name']) . ')' : '' ?><?= $u['management_no'] ? ' [No.' . h(fmt_management_no($u['management_no'])) . ']' : '' ?><?= ($isUnit && (int)$u['open_count']) ? '(持ち出し中)' : '' ?>
        </option>
      <?php endforeach; endforeach; ?>
    </select>
  </label>
  <div id="qty-box">
    <label>数量<input type="number" name="quantity" value="1" min="1" max="9999" inputmode="numeric"></label>
  </div>
  <label>持ち出した人(任意)
    <select name="user_id" id="user-select">
      <option value="">(未選択)</option>
      <?php foreach ($users as $u): ?><option value="<?= h($u['id']) ?>" <?= (int)$u['id'] === (int)$me['id'] ? 'selected' : '' ?>><?= h($u['display_name']) ?></option><?php endforeach; ?>
      <option value="_other">その他(名前を入力)</option>
    </select>
  </label>
  <label id="other-box" hidden>名前<input type="text" name="other_name" maxlength="100" placeholder="例: 大村"></label>
  <label>メモ(任意)<input type="text" name="notes" maxlength="500" placeholder="持ち出し先など"></label>
  <button type="submit" class="btn btn-primary btn-block btn-lg">持ち出しを登録</button>
</form>

<?php else: ?>
<section>
  <h2>持ち出し中(<?= count($open) ?> 件)</h2>
  <?php if (!$open): ?><p class="muted">持ち出し中の備品はありません。</p><?php endif; ?>
  <div class="card-list">
  <?php foreach ($open as $co): ?>
    <div class="card card-row">
      <div>
        <div class="card-title"><?= h($co['unit_name'] ?? $co['item_name']) ?><?= $co['management_no'] ? ' <span class="badge">No.' . h(fmt_management_no($co['management_no'])) . '</span>' : '' ?><?= (int)$co['quantity'] !== 1 ? ' ×' . (int)$co['quantity'] : '' ?> <small class="muted"><?= h($co['item_name']) ?></small></div>
        <div class="card-sub"><?= h($co['checked_out_by_name'] ?: '(名前なし)') ?> ・ <?= h(fmt_datetime($co['checked_out_at'])) ?><?= $co['notes'] ? ' ・ ' . h($co['notes']) : '' ?></div>
      </div>
      <form method="post" action="<?= h(url('checkout', ['action' => 'in'])) ?>" data-confirm="「<?= h($co['unit_name'] ?? $co['item_name']) ?>」を戻しにしますか?">
        <?= csrf_field() ?><input type="hidden" name="checkout_id" value="<?= h($co['id']) ?>">
        <button type="submit" class="btn btn-success">戻す</button>
      </form>
    </div>
  <?php endforeach; ?>
  </div>
</section>
<section>
  <h2>最近の戻し</h2>
  <?php if (!$recent): ?><p class="muted">まだありません。</p><?php endif; ?>
  <div class="card-list">
  <?php foreach ($recent as $co): ?>
    <div class="card card-row">
      <div>
        <div class="card-title"><?= h($co['unit_name'] ?? $co['item_name']) ?><?= $co['management_no'] ? ' <span class="badge">No.' . h(fmt_management_no($co['management_no'])) . '</span>' : '' ?><?= (int)$co['quantity'] !== 1 ? ' ×' . (int)$co['quantity'] : '' ?></div>
        <div class="card-sub"><?= h($co['checked_out_by_name'] ?: '(名前なし)') ?> ・ 持出 <?= h(fmt_datetime($co['checked_out_at'])) ?> → 戻し <?= h(fmt_datetime($co['returned_at'])) ?></div>
      </div>
    </div>
  <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>
