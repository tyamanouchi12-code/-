// NKC 在庫管理システム 画面補助スクリプト
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
})();

// ---- 棚卸入力画面 ----
(function () {
  'use strict';
  var entryTable = document.getElementById('entry-table');
  if (!entryTable) { return; }
  var form = document.getElementById('entry-form');
  var rows = Array.prototype.slice.call(entryTable.querySelectorAll('tr.entry-row'));

  function rowOf(itemId) { return entryTable.querySelector('tr.entry-row[data-item="' + itemId + '"]'); }

  // 差異の再計算(今回の値が変わったとき)
  function setDiff(row, qty) {
    var cell = row.querySelector('.diff-cell');
    var prev = row.getAttribute('data-prev');
    cell.classList.remove('diff-plus', 'diff-minus');
    row.classList.remove('has-diff');
    if (qty === null || prev === null || prev === '') { cell.textContent = ''; return; }
    var d = qty - parseInt(prev, 10);
    cell.textContent = d > 0 ? '+' + d : String(d);
    if (d > 0) { cell.classList.add('diff-plus'); }
    if (d < 0) { cell.classList.add('diff-minus'); }
    if (d !== 0) { row.classList.add('has-diff'); }
  }
  function markDirty(row) {
    var st = row.querySelector('.save-state');
    if (st) { st.textContent = '未保存'; st.classList.remove('saved'); st.classList.add('dirty'); }
  }

  // 数量入力
  entryTable.querySelectorAll('.qty-input').forEach(function (inp) {
    inp.addEventListener('input', function () {
      var v = inp.value.replace(/[０-９]/g, function (s) { return String.fromCharCode(s.charCodeAt(0) - 0xFEE0); });
      inp.value = v;
      var row = inp.closest('tr');
      markDirty(row);
      if (v === '') { row.classList.add('unentered'); row.classList.remove('entered'); setDiff(row, null); return; }
      if (!/^\d+$/.test(v)) { return; }
      row.classList.remove('unentered'); row.classList.add('entered');
      setDiff(row, parseInt(v, 10));
    });
  });
  entryTable.querySelectorAll('input[name^="dnotes"]').forEach(function (inp) {
    inp.addEventListener('input', function () { markDirty(inp.closest('tr')); });
  });

  // 個体の有/無 → 数量を自動集計
  function recalcUnits(itemId) {
    var row = rowOf(itemId);
    var radios = row.querySelectorAll('input[type=radio][data-unit-of="' + itemId + '"]:checked');
    var present = 0, any = false;
    radios.forEach(function (r) { if (r.value !== 'unchecked') { any = true; } if (r.value === 'present') { present++; } });
    var span = row.querySelector('.qty-auto');
    markDirty(row);
    if (!any) { span.textContent = '–'; row.classList.add('unentered'); row.classList.remove('entered'); setDiff(row, null); return; }
    span.textContent = String(present);
    row.classList.remove('unentered'); row.classList.add('entered');
    setDiff(row, present);
  }
  entryTable.querySelectorAll('input[type=radio][data-unit-of]').forEach(function (r) {
    r.addEventListener('change', function () { recalcUnits(r.getAttribute('data-unit-of')); });
  });

  // ---- 行ごとの「確定」(その行だけ保存。画面は再読み込みしない) ----
  function collectRow(row) {
    var data = new FormData();
    data.append('_token', form.querySelector('input[name=_token]').value);
    data.append('ajax', '1');
    data.append('save_item', row.getAttribute('data-item'));
    row.querySelectorAll('input, select').forEach(function (el) {
      if (!el.name) { return; }
      if (el.type === 'radio' && !el.checked) { return; }
      data.append(el.name, el.value);
    });
    return data;
  }
  function refreshTotals() {
    var entered = 0, total = 0, byCat = {};
    rows.forEach(function (row) {
      var cat = row.getAttribute('data-cat');
      byCat[cat] = byCat[cat] || { entered: 0, total: 0 };
      var q = savedQty[row.getAttribute('data-item')];
      if (q !== null && q !== undefined) { entered++; total += q; byCat[cat].entered++; byCat[cat].total += q; }
    });
    var se = document.getElementById('sum-entered'), st = document.getElementById('sum-total');
    if (se) { se.textContent = entered; }
    if (st) { st.textContent = total; }
    Object.keys(byCat).forEach(function (cat) {
      var e = document.querySelector('.cat-entered[data-cat="' + cat + '"]'), t = document.querySelector('.cat-total[data-cat="' + cat + '"]');
      if (e) { e.textContent = byCat[cat].entered; }
      if (t) { t.textContent = byCat[cat].total; }
    });
  }
  var savedQty = {};
  rows.forEach(function (row) {
    var inp = row.querySelector('.qty-input'), auto = row.querySelector('.qty-auto'), strong = row.querySelector('td[data-label="今回"] strong');
    var v = inp ? inp.value : (auto ? auto.textContent : (strong ? strong.textContent : ''));
    savedQty[row.getAttribute('data-item')] = /^\d+$/.test(v) ? parseInt(v, 10) : null;
  });

  if (form) {
    entryTable.querySelectorAll('.row-save').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        if (!window.fetch || !window.FormData) { return; }   // 古いブラウザは通常送信
        e.preventDefault();
        var row = btn.closest('tr');
        var itemId = row.getAttribute('data-item');
        var st = row.querySelector('.save-state');
        btn.disabled = true; st.textContent = '保存中…'; st.classList.remove('saved', 'dirty', 'error');
        fetch(form.getAttribute('action'), { method: 'POST', body: collectRow(row), credentials: 'same-origin' })
          .then(function (r) { return r.json(); })
          .then(function (res) {
            btn.disabled = false;
            if (!res.ok) { st.textContent = '✕ ' + res.message; st.classList.add('error'); window.alert(res.message); return; }
            var info = res.rows && res.rows[itemId];
            st.textContent = '✓ 保存済'; st.classList.add('saved');
            if (info) {
              savedQty[itemId] = (info.qty === null || info.qty === undefined) ? null : parseInt(info.qty, 10);
              var cc = row.querySelector('.counted-cell');
              cc.innerHTML = '';
              if (info.counted_by) { cc.appendChild(document.createTextNode(info.counted_by)); }
              if (info.counted_at) { cc.appendChild(document.createElement('br')); cc.appendChild(document.createTextNode(info.counted_at)); }
            }
            refreshTotals();
            // 次の行の数量欄へ
            var idx = rows.indexOf(row);
            for (var j = idx + 1; j < rows.length; j++) {
              if (rows[j].style.display !== 'none') { var n = rows[j].querySelector('.qty-input'); if (n) { n.focus(); n.select(); } break; }
            }
          })
          .catch(function () { btn.disabled = false; st.textContent = '✕ 通信エラー'; st.classList.add('error'); });
      });
    });
    // Enter キー: その行を確定
    entryTable.querySelectorAll('.qty-input, input[name^="dnotes"]').forEach(function (inp) {
      inp.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); inp.closest('tr').querySelector('.row-save').click(); }
      });
    });
  }

  // ---- カテゴリ別集計: 行クリックでそのカテゴリだけ表示 / 全件表示 ----
  var catRows = document.querySelectorAll('#cat-table tr.cat-row');
  var scope = document.getElementById('entry-scope');
  var selectedCat = null;
  function applyFilter() {
    var q = (document.getElementById('quick-filter').value || '').toLowerCase().trim();
    var onlyUn = document.getElementById('only-unentered').checked;
    var onlyDiff = document.getElementById('only-diff').checked;
    var shown = 0;
    rows.forEach(function (row) {
      var show = true;
      if (selectedCat !== null && row.getAttribute('data-cat') !== selectedCat) { show = false; }
      if (q && row.getAttribute('data-text').indexOf(q) === -1) { show = false; }
      if (onlyUn && !row.classList.contains('unentered')) { show = false; }
      if (onlyDiff && !row.classList.contains('has-diff')) { show = false; }
      row.style.display = show ? '' : 'none';
      if (show) { shown++; }
    });
    if (scope) {
      var name = '';
      catRows.forEach(function (r) { if (r.getAttribute('data-cat') === selectedCat) { name = r.querySelector('.cat-link').textContent.trim(); } });
      scope.textContent = (selectedCat === null ? '(全件' : '(カテゴリ「' + name + '」') + ' ' + shown + ' 件)';
    }
  }
  catRows.forEach(function (r) {
    r.addEventListener('click', function (e) {
      e.preventDefault();
      var cat = r.getAttribute('data-cat');
      selectedCat = (selectedCat === cat) ? null : cat;
      catRows.forEach(function (x) { x.classList.toggle('selected', x.getAttribute('data-cat') === selectedCat); });
      applyFilter();
      var title = document.getElementById('entry-title');
      if (title && selectedCat !== null) { title.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
    });
  });
  var showAll = document.getElementById('cat-show-all');
  if (showAll) {
    showAll.addEventListener('click', function () {
      selectedCat = null;
      catRows.forEach(function (x) { x.classList.remove('selected'); });
      document.getElementById('quick-filter').value = '';
      document.getElementById('only-unentered').checked = false;
      document.getElementById('only-diff').checked = false;
      applyFilter();
    });
  }
  ['quick-filter', 'only-unentered', 'only-diff'].forEach(function (id) {
    var el = document.getElementById(id);
    if (el) { el.addEventListener(id === 'quick-filter' ? 'input' : 'change', applyFilter); }
  });
  // サーバー側でカテゴリ絞り込み済みなら、その行を選択状態にしておく
  catRows.forEach(function (r) { if (r.classList.contains('selected')) { selectedCat = r.getAttribute('data-cat'); } });
  if (scope && selectedCat !== null) { applyFilter(); }
})();

// ---- 持ち出し画面 ----
(function () {
  'use strict';
  var form = document.getElementById('checkout-form');
  if (!form) { return; }
  var search = document.getElementById('item-search');
  var itemSel = document.getElementById('item-select');
  var unitBox = document.getElementById('unit-box');
  var unitSel = document.getElementById('unit-select');
  var qtyBox = document.getElementById('qty-box');
  var userSel = document.getElementById('user-select');
  var otherBox = document.getElementById('other-box');
  var allItemOptions = Array.prototype.slice.call(itemSel.options);

  function refreshItemMode() {
    var opt = itemSel.options[itemSel.selectedIndex];
    var isUnit = opt && opt.getAttribute('data-type') === 'unit';
    unitBox.hidden = !isUnit;
    qtyBox.hidden = !!isUnit;
    unitSel.required = !!isUnit;
    var itemId = opt ? opt.value : '';
    Array.prototype.forEach.call(unitSel.options, function (o) {
      if (!o.value) { return; }
      var show = o.getAttribute('data-item') === itemId;
      o.hidden = !show;
      if (!show && o.selected) { unitSel.value = ''; }
    });
  }
  function filterItems() {
    var q = (search.value || '').toLowerCase().trim();
    var current = itemSel.value;
    while (itemSel.options.length) { itemSel.remove(0); }
    allItemOptions.forEach(function (o) {
      if (!o.value || !q || o.getAttribute('data-text').indexOf(q) !== -1) { itemSel.add(o); }
    });
    itemSel.value = current;
    if (itemSel.value !== current) { itemSel.selectedIndex = 0; }
    if (q && itemSel.options.length === 2) { itemSel.selectedIndex = 1; }
    refreshItemMode();
  }
  search.addEventListener('input', filterItems);
  itemSel.addEventListener('change', refreshItemMode);
  userSel.addEventListener('change', function () { otherBox.hidden = userSel.value !== '_other'; });
  form.addEventListener('submit', function () {
    if (userSel.value === '_other') { userSel.name = 'user_id_other'; }
  });
  refreshItemMode();
})();
