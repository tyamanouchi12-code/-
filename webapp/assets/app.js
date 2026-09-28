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

// ---- 棚卸入力画面(内訳ごとに入力、品目合計は自動) ----
(function () {
  'use strict';
  var entryTable = document.getElementById('entry-table');
  if (!entryTable) { return; }
  var form = document.getElementById('entry-form');
  var rows = Array.prototype.slice.call(entryTable.querySelectorAll('tr.entry-row'));
  var heads = Array.prototype.slice.call(entryTable.querySelectorAll('tr.item-head'));

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
  function setEntered(row, on) {
    row.classList.toggle('unentered', !on); row.classList.toggle('entered', on);
  }

  entryTable.querySelectorAll('.qty-input').forEach(function (inp) {
    inp.addEventListener('input', function () {
      var v = inp.value.replace(/[０-９]/g, function (s) { return String.fromCharCode(s.charCodeAt(0) - 0xFEE0); });
      inp.value = v;
      var row = inp.closest('tr');
      markDirty(row);
      if (v === '') { setEntered(row, false); setDiff(row, null); return; }
      if (!/^\d+$/.test(v)) { return; }
      setEntered(row, true);
      setDiff(row, parseInt(v, 10));
    });
  });
  entryTable.querySelectorAll('input[name^="unotes"]').forEach(function (inp) {
    inp.addEventListener('input', function () { markDirty(inp.closest('tr')); });
  });
  entryTable.querySelectorAll('input[type=radio][data-unit-of]').forEach(function (r) {
    r.addEventListener('change', function () {
      var row = r.closest('tr');
      markDirty(row);
      if (r.value === 'unchecked') { setEntered(row, false); setDiff(row, null); }
      else { setEntered(row, true); setDiff(row, r.value === 'present' ? 1 : 0); }
    });
  });

  // 保存済みの数量(集計用)
  var savedQty = {};
  rows.forEach(function (row) {
    var inp = row.querySelector('.qty-input'), strong = row.querySelector('td[data-label="今回"] strong');
    var v = '';
    if (inp) { v = inp.value; }
    else if (row.querySelector('.unit-radios')) { var c = row.querySelector('.unit-radios input:checked'); v = c ? (c.value === 'present' ? '1' : (c.value === 'absent' ? '0' : '')) : ''; }
    else if (strong) { v = strong.textContent; }
    else { var b = row.querySelector('.badge-unit-present'); v = b ? '1' : (row.querySelector('.badge-unit-absent') ? '0' : ''); }
    savedQty[row.getAttribute('data-unit')] = /^\d+$/.test(v) ? parseInt(v, 10) : null;
  });
  function refreshTotals() {
    var entered = 0, total = 0, byItem = {};
    rows.forEach(function (row) {
      var item = row.getAttribute('data-item');
      byItem[item] = byItem[item] || { entered: 0, total: 0 };
      var q = savedQty[row.getAttribute('data-unit')];
      if (q !== null && q !== undefined) { entered++; total += q; byItem[item].entered++; byItem[item].total += q; }
    });
    var se = document.getElementById('sum-entered'), st = document.getElementById('sum-total');
    if (se) { se.textContent = entered; }
    if (st) { st.textContent = total; }
    Object.keys(byItem).forEach(function (item) {
      var e = document.querySelector('.cat-entered[data-cat="' + item + '"]'), t = document.querySelector('.cat-total[data-cat="' + item + '"]');
      var h = entryTable.querySelector('.item-total[data-item="' + item + '"]');
      if (e) { e.textContent = byItem[item].entered; }
      if (t) { t.textContent = byItem[item].total; }
      if (h) { h.textContent = byItem[item].entered ? byItem[item].total : '–'; }
    });
  }

  function collectRow(row) {
    var data = new FormData();
    data.append('_token', form.querySelector('input[name=_token]').value);
    data.append('ajax', '1');
    data.append('save_unit', row.getAttribute('data-unit'));
    row.querySelectorAll('input, select').forEach(function (el) {
      if (!el.name) { return; }
      if (el.type === 'radio' && !el.checked) { return; }
      data.append(el.name, el.value);
    });
    return data;
  }
  if (form) {
    entryTable.querySelectorAll('.row-save').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        if (!window.fetch || !window.FormData) { return; }
        e.preventDefault();
        var row = btn.closest('tr');
        var uid = row.getAttribute('data-unit');
        var st = row.querySelector('.save-state');
        btn.disabled = true; st.textContent = '保存中…'; st.classList.remove('saved', 'dirty', 'error');
        fetch(form.getAttribute('action'), { method: 'POST', body: collectRow(row), credentials: 'same-origin' })
          .then(function (r) { return r.json(); })
          .then(function (res) {
            btn.disabled = false;
            if (!res.ok) { st.textContent = '✕ ' + res.message; st.classList.add('error'); window.alert(res.message); return; }
            var info = res.units && res.units[uid];
            st.textContent = '✓ 保存済'; st.classList.add('saved');
            if (info) {
              savedQty[uid] = (info.qty === null || info.qty === undefined) ? null : parseInt(info.qty, 10);
              var cc = row.querySelector('.counted-cell');
              cc.innerHTML = '';
              if (info.by) { cc.appendChild(document.createTextNode(info.by)); }
              if (info.at) { cc.appendChild(document.createElement('br')); cc.appendChild(document.createTextNode(info.at)); }
            }
            refreshTotals();
            var idx = rows.indexOf(row);
            for (var j = idx + 1; j < rows.length; j++) {
              if (rows[j].style.display !== 'none') { var n = rows[j].querySelector('.qty-input'); if (n) { n.focus(); n.select(); } break; }
            }
          })
          .catch(function () { btn.disabled = false; st.textContent = '✕ 通信エラー'; st.classList.add('error'); });
      });
    });
    entryTable.querySelectorAll('.qty-input, input[name^="unotes"]').forEach(function (inp) {
      inp.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); inp.closest('tr').querySelector('.row-save').click(); }
      });
    });
  }

  // ---- 品目別集計: 行クリックでその品目だけ表示 / 全件表示 ----
  var catRows = document.querySelectorAll('#cat-table tr.cat-row');
  var scope = document.getElementById('entry-scope');
  var selectedCat = null;
  function applyFilter() {
    var q = (document.getElementById('quick-filter').value || '').toLowerCase().trim();
    var onlyUn = document.getElementById('only-unentered').checked;
    var onlyDiff = document.getElementById('only-diff').checked;
    var shown = 0, visibleItems = {};
    rows.forEach(function (row) {
      var show = true;
      if (selectedCat !== null && row.getAttribute('data-cat') !== selectedCat) { show = false; }
      if (q && row.getAttribute('data-text').indexOf(q) === -1) { show = false; }
      if (onlyUn && !row.classList.contains('unentered')) { show = false; }
      if (onlyDiff && !row.classList.contains('has-diff')) { show = false; }
      row.style.display = show ? '' : 'none';
      if (show) { shown++; visibleItems[row.getAttribute('data-item')] = true; }
    });
    heads.forEach(function (h) { h.style.display = visibleItems[h.getAttribute('data-item')] ? '' : 'none'; });
    if (scope) {
      var name = '';
      catRows.forEach(function (r) { if (r.getAttribute('data-cat') === selectedCat) { name = r.querySelector('.cat-link').textContent.trim(); } });
      scope.textContent = (selectedCat === null ? '(全件' : '(品目「' + name + '」') + ' ' + shown + ' 件)';
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
  var unitSel = document.getElementById('unit-select');
  var qtyBox = document.getElementById('qty-box');
  var userSel = document.getElementById('user-select');
  var otherBox = document.getElementById('other-box');
  var allUnitOptions = Array.prototype.slice.call(unitSel.options).filter(function (o) { return o.value; });
  var placeholder = unitSel.options[0];

  function rebuildUnits() {
    var itemId = itemSel.value;
    var q = (search.value || '').toLowerCase().trim();
    var current = unitSel.value;
    while (unitSel.options.length) { unitSel.remove(0); }
    placeholder.textContent = itemId ? '選択してください' : '先に品目を選択してください';
    unitSel.add(placeholder);
    var n = 0, last = null;
    allUnitOptions.forEach(function (o) {
      if (itemId && o.getAttribute('data-item') !== itemId) { return; }
      if (!itemId && !q) { return; }
      if (q && o.getAttribute('data-text').indexOf(q) === -1) { return; }
      unitSel.add(o); n++; last = o;
    });
    unitSel.value = current;
    if (unitSel.value !== current) { unitSel.selectedIndex = 0; }
    if (n === 1 && !last.disabled) { unitSel.value = last.value; }
    refreshQty();
  }
  function refreshQty() {
    var opt = unitSel.options[unitSel.selectedIndex];
    var isUnit = opt && opt.getAttribute('data-unit') === '1';
    qtyBox.hidden = !!isUnit;
    // 内訳を選んだら品目も合わせる(検索から選んだ場合)
    if (opt && opt.value && itemSel.value !== opt.getAttribute('data-item')) { itemSel.value = opt.getAttribute('data-item'); }
  }
  search.addEventListener('input', function () { if (search.value.trim()) { itemSel.value = ''; } rebuildUnits(); });
  itemSel.addEventListener('change', function () { search.value = ''; rebuildUnits(); });
  unitSel.addEventListener('change', refreshQty);
  userSel.addEventListener('change', function () { otherBox.hidden = userSel.value !== '_other'; });
  form.addEventListener('submit', function () {
    if (userSel.value === '_other') { userSel.name = 'user_id_other'; }
  });
  rebuildUnits();
})();

// ---- 品目フォーム: 内訳の入力欄 ----
(function () {
  'use strict';
  var section = document.getElementById('unit-section');
  if (!section) { return; }
  var table = document.getElementById('unit-table').querySelector('tbody');
  var tpl = document.getElementById('unit-row-template');
  var addBtn = document.getElementById('unit-add');
  var catSel = document.getElementById('category-select');
  var nameInp = document.getElementById('item-name');
  function nextIndex() {
    var max = -1;
    table.querySelectorAll('input[name^="units["]').forEach(function (i) {
      var m = i.name.match(/^units\[(\d+)\]/); if (m) { max = Math.max(max, parseInt(m[1], 10)); }
    });
    return max + 1;
  }
  function addRow() {
    var html = tpl.innerHTML.replace(/__i__/g, String(nextIndex()));
    var tmp = document.createElement('tbody'); tmp.innerHTML = html.trim();
    var row = tmp.firstElementChild;
    table.appendChild(row);
    row.querySelector('input[type=text]').focus();
  }
  addBtn.addEventListener('click', addRow);
  table.addEventListener('click', function (e) {
    if (e.target.classList.contains('unit-remove')) { e.target.closest('tr').remove(); }
  });
  if (table.querySelectorAll('tr').length === 0) { addRow(); }
  // カテゴリを選んだら品目名を自動で入れる(未入力のとき、または前のカテゴリ名と同じとき)
  if (catSel && nameInp) {
    var prevCatName = catSel.options[catSel.selectedIndex] ? catSel.options[catSel.selectedIndex].textContent.trim() : '';
    catSel.addEventListener('change', function () {
      var name = catSel.options[catSel.selectedIndex].textContent.trim();
      if (!nameInp.value.trim() || nameInp.value.trim() === prevCatName) { nameInp.value = catSel.value ? name : ''; }
      prevCatName = name;
    });
  }
})();

