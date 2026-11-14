// js/admin_types.js — "Incident Types" admin tab (loaded automatically by admin_sort.js)
(function () {
  if (document.getElementById('panel-types') || !document.getElementById('panel-locations')) return;
  const PANEL_HTML = `<div id="panel-types" class="p-4 hidden">
  <div class="bg-gray-50 border border-gray-200 rounded-2xl p-4 mb-5">
    <h3 class="font-bold text-gray-700 mb-3 text-sm">➕ Add Incident Type</h3>
    <form id="addTypeForm" class="flex flex-col sm:flex-row gap-2">
      <input type="text" id="newTypeIcon" maxlength="8" placeholder="📋" title="Emoji icon"
        class="w-full sm:w-20 text-center border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-400">
      <input type="text" id="newTypeLabel" required maxlength="100" placeholder="e.g. Vandalism"
        class="flex-1 border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-400">
      <button type="submit" class="text-white font-semibold px-6 py-2.5 rounded-xl text-sm hover:opacity-90 shrink-0"
        style="background:linear-gradient(90deg,#152875,#b91c1c);">➕ Add Type</button>
    </form>
    <p class="text-xs text-gray-400 mt-2">These are the options in the "Incident Type" dropdown of the report form. Hiding a type keeps old reports intact. A type already used by reports can only be hidden, not deleted.</p>
  </div>
  <div id="typeList" class="space-y-2"></div>
</div>
`;
  const tmp = document.createElement('div');
  tmp.innerHTML = PANEL_HTML;
  const panel = tmp.firstElementChild;
  document.getElementById('panel-locations').after(panel);

  const btn = document.createElement('button');
  btn.id = 'tab-types';
  btn.className = 'tab-btn text-gray-500 hover:text-gray-700 flex-shrink-0 flex-1 py-2.5 rounded-xl text-sm font-semibold transition whitespace-nowrap';
  btn.innerHTML = '🏷️ <span class="hidden sm:inline">Incident Types</span>';
  btn.onclick = () => switchTab('types');
  document.getElementById('tab-locations').after(btn);

  const orig = window.switchTab;
  let loaded = false;
  window.switchTab = function (tab) {
    orig(tab);
    panel.classList.toggle('hidden', tab !== 'types');
    btn.classList.toggle('active', tab === 'types');
    btn.classList.toggle('text-gray-500', tab !== 'types');
    if (tab === 'types' && !loaded) { loaded = true; load(); }
  };
  if (typeof ACTIVE_TAB !== 'undefined' && ACTIVE_TAB === 'types') switchTab('types');

  let types = [], editing = null;
  const esc = s => String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  const toast = (m, c) => (typeof showToast === 'function') && showToast(m, c);

  async function call(data) {
    const fd = new FormData();
    Object.entries(data).forEach(([k, v]) => fd.append(k, v));
    try { return await (await fetch('types_ajax.php', { method: 'POST', body: fd })).json(); }
    catch (_) { return { success: false, message: 'Network error' }; }
  }

  async function load() {
    const d = await call({ action: 'list' });
    if (!d.success) { document.getElementById('typeList').innerHTML = `<p class="text-sm text-red-500">${esc(d.message)}</p>`; return; }
    types = d.types; render();
  }

  function render() {
    document.getElementById('typeList').innerHTML = types.map(t => {
      const on = t.is_active == 1, locked = t.slug === 'other';
      if (editing === t.id) return `
        <div class="flex flex-col sm:flex-row gap-2 bg-gray-50 rounded-xl px-4 py-2.5">
          <input id="eIcon" value="${esc(t.icon)}" maxlength="8" class="w-full sm:w-16 text-center border border-gray-200 rounded-lg px-2 py-1.5 text-sm">
          <input id="eLabel" value="${esc(t.label)}" maxlength="100" class="flex-1 border border-gray-200 rounded-lg px-3 py-1.5 text-sm">
          <button data-a="save" data-id="${t.id}" class="text-xs bg-emerald-100 hover:bg-emerald-200 text-emerald-700 px-3 py-1.5 rounded-lg">💾 Save</button>
          <button data-a="cancel" class="text-xs bg-gray-100 hover:bg-gray-200 text-gray-600 px-3 py-1.5 rounded-lg">Cancel</button>
        </div>`;
      return `
        <div class="flex items-center justify-between bg-gray-50 rounded-xl px-4 py-2.5 ${on ? '' : 'opacity-60'}">
          <span class="text-sm font-medium text-gray-700">${esc(t.icon)} ${esc(t.label)} <span class="mono text-xs text-gray-400 ml-1">${esc(t.slug)}</span></span>
          <div class="flex items-center gap-2">
            <span class="text-xs font-semibold px-2 py-0.5 rounded-full ${on ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-400'}">${on ? '● Active' : '● Hidden'}</span>
            <button data-a="edit" data-id="${t.id}" class="text-xs bg-amber-100 hover:bg-amber-200 text-amber-700 px-2 py-1 rounded-lg">✏️ Edit</button>
            ${locked ? '' : `<button data-a="toggle" data-id="${t.id}" class="text-xs bg-blue-100 hover:bg-blue-200 text-blue-600 px-2 py-1 rounded-lg">${on ? '🙈 Hide' : '👁 Show'}</button>
            <button data-a="delete" data-id="${t.id}" class="text-xs bg-red-100 hover:bg-red-200 text-red-600 px-2 py-1 rounded-lg">🗑</button>`}
          </div>
        </div>`;
    }).join('') || '<p class="text-sm text-gray-400">No types yet.</p>';
  }

  const put = t => { const i = types.findIndex(x => x.id == t.id); i < 0 ? types.push(t) : (types[i] = t); };

  document.getElementById('typeList').addEventListener('click', async e => {
    const b = e.target.closest('button[data-a]'); if (!b) return;
    const id = +b.dataset.id, a = b.dataset.a;
    if (a === 'edit')   { editing = id; render(); return; }
    if (a === 'cancel') { editing = null; render(); return; }
    if (a === 'delete' && !confirm('Delete this incident type?')) return;
    const data = { action: a === 'save' ? 'edit' : a, id };
    if (a === 'save') { data.label = document.getElementById('eLabel').value; data.icon = document.getElementById('eIcon').value; }
    const d = await call(data);
    if (!d.success) { toast('⚠️ ' + (d.message || 'Failed'), 'bg-red-600'); return; }
    if (a === 'delete') types = types.filter(t => t.id != id); else put(d.type);
    editing = null; render();
    toast(a === 'delete' ? '🗑 Type deleted' : '✅ Saved', a === 'delete' ? 'bg-gray-700' : 'bg-green-600');
  });

  document.getElementById('addTypeForm').addEventListener('submit', async e => {
    e.preventDefault();
    const d = await call({ action: 'add', label: document.getElementById('newTypeLabel').value, icon: document.getElementById('newTypeIcon').value });
    if (!d.success) { toast('⚠️ ' + (d.message || 'Failed'), 'bg-red-600'); return; }
    put(d.type); render(); e.target.reset(); toast('✅ Incident type added');
  });
})();
