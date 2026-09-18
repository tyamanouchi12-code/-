// NKC 棚卸システム 画面補助スクリプト
(function () {
  'use strict';

  // 確認ダイアログ(data-confirm を持つ form / button)
  document.querySelectorAll('form[data-confirm]').forEach(function (f) {
    f.addEventListener('submit', function (e) {
      if (!window.confirm(f.getAttribute('data-confirm'))) { e.preventDefault(); }
    });
  });
  document.querySelectorAll('button[data-confirm]').forEach(function (b) {
    b.addEventListener('click', function (e) {
      if (!window.confirm(b.getAttribute('data-confirm'))) { e.preventDefault(); }
    });
  });

  // 未保存の変更がある状態でページを離れるときに警告
  document.querySelectorAll('form[data-dirty-check]').forEach(function (f) {
    var dirty = false;
    f.addEventListener('input', function () { dirty = true; });
    f.addEventListener('change', function () { dirty = true; });
    f.addEventListener('submit', function () { dirty = false; });
    window.addEventListener('beforeunload', function (e) {
      if (dirty) { e.preventDefault(); e.returnValue = ''; }
    });
  });

  // ---- 棚卸入力画面 ----
  var entryTable = document.querySelector('.table-entry');
  if (!entryTable) { return; }

  function setDiff(itemId, qty) {
    var cell = entryTable.querySelector('.diff-cell[data-item="' + itemId + '"]');
    if (!cell) { return; }
    var row = cell.closest('tr');
    var prevEl = row.querySelector('.qty-input[data-item="' + itemId + '"]');
    var prev = prevEl ? prevEl.getAttribute('data-prev') : row.getAttribute('data-prev');
    cell.classList.remove('diff-plus', 'diff-minus');
    row.classList.remove('has-diff');
    if (qty === null || prev === null || prev === '' || prev === undefined) { cell.textContent = ''; return; }
    var d = qty - parseInt(prev, 10);
    cell.textContent = d > 0 ? '+' + d : String(d);
    if (d > 0) { cell.classList.add('diff-plus'); }
    if (d < 0) { cell.classList.add('diff-minus'); }
    if (d !== 0) { row.classList.add('has-diff'); }
  }

  // 数量入力 → 差異を即時計算、未入力クラスを更新
  entryTable.querySelectorAll('.qty-input').forEach(function (inp) {
    inp.addEventListener('input', function () {
      var v = inp.value.replace(/[０-９]/g, function (s) { return String.fromCharCode(s.charCodeAt(0) - 0xFEE0); });
      inp.value = v;
      var row = inp.closest('tr');
      if (v === '') { row.classList.add('unentered'); setDiff(inp.getAttribute('data-item'), null); return; }
      if (!/^\d+$/.test(v)) { return; }
      row.classList.remove('unentered');
      setDiff(inp.getAttribute('data-item'), parseInt(v, 10));
    });
  });

  // 個体の有/無 → 数量を自動集計
  var prevOfItem = {};
  entryTable.querySelectorAll('.qty-auto').forEach(function (span) {
    var row = span.closest('tr');
    var prevCell = row.children[5];
    prevOfItem[span.getAttribute('data-item')] = prevCell ? prevCell.textContent.trim() : '';
  });
  function recalcUnits(itemId) {
    var radios = entryTable.querySelectorAll('input[type=radio][data-unit-of="' + itemId + '"]:checked');
    var present = 0, any = false;
    radios.forEach(function (r) { if (r.value !== 'unchecked') { any = true; } if (r.value === 'present') { present++; } });
    var span = entryTable.querySelector('.qty-auto[data-item="' + itemId + '"]');
    var row = span.closest('tr');
    if (!any) { span.textContent = '–'; row.classList.add('unentered'); setDiffUnit(itemId, null, row); return; }
    span.textContent = String(present);
    row.classList.remove('unentered');
    setDiffUnit(itemId, present, row);
  }
  function setDiffUnit(itemId, qty, row) {
    var cell = row.querySelector('.diff-cell');
    var prev = prevOfItem[itemId];
    cell.classList.remove('diff-plus', 'diff-minus');
    row.classList.remove('has-diff');
    if (qty === null || prev === '' || prev === '–') { cell.textContent = ''; return; }
    var d = qty - parseInt(prev, 10);
    cell.textContent = d > 0 ? '+' + d : String(d);
    if (d > 0) { cell.classList.add('diff-plus'); }
    if (d < 0) { cell.classList.add('diff-minus'); }
    if (d !== 0) { row.classList.add('has-diff'); }
  }
  entryTable.querySelectorAll('input[type=radio][data-unit-of]').forEach(function (r) {
    r.addEventListener('change', function () { recalcUnits(r.getAttribute('data-unit-of')); });
  });

  // 表内の絞り込み(テキスト / 未入力のみ / 差異ありのみ)
  var q = document.getElementById('quick-filter');
  var onlyUn = document.getElementById('only-unentered');
  var onlyDiff = document.getElementById('only-diff');
  function applyFilter() {
    var text = (q.value || '').toLowerCase().trim();
    entryTable.querySelectorAll('tr.entry-row').forEach(function (row) {
      var show = true;
      if (text && row.getAttribute('data-text').indexOf(text) === -1) { show = false; }
      if (onlyUn.checked && !row.classList.contains('unentered')) { show = false; }
      if (onlyDiff.checked && !row.classList.contains('has-diff')) { show = false; }
      row.style.display = show ? '' : 'none';
    });
  }
  if (q) { q.addEventListener('input', applyFilter); onlyUn.addEventListener('change', applyFilter); onlyDiff.addEventListener('change', applyFilter); }

  // Enter キーで次の数量欄へ移動(フォーム送信を防ぐ)
  var inputs = Array.prototype.slice.call(entryTable.querySelectorAll('.qty-input'));
  inputs.forEach(function (inp, i) {
    inp.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        for (var j = i + 1; j < inputs.length; j++) {
          if (inputs[j].closest('tr').style.display !== 'none') { inputs[j].focus(); inputs[j].select(); break; }
        }
      }
    });
  });
})();
