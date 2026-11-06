<!-- includes/admin_update_modal.php — "Update Incident" modal (status + note) -->
<div id="updateModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/60 backdrop-blur-sm p-4">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md max-h-[90vh] overflow-y-auto animate-modal">
    <div class="text-white px-5 py-4 rounded-t-2xl flex justify-between items-center" style="background:linear-gradient(90deg,#152875,#b91c1c);">
      <h3 class="font-bold text-lg" id="updTitle">📝 Update Incident</h3>
      <button onclick="closeUpdateModal()" class="text-white hover:text-red-200 text-2xl leading-none">&times;</button>
    </div>

    <div class="p-5 space-y-4">
      <div>
        <label class="block text-xs font-semibold text-gray-500 mb-1">Status</label>
        <select id="updStatus" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-400 bg-white">
          <option value="open">🔴 Open</option>
          <option value="in_progress">🔵 In Progress</option>
          <option value="resolved">✅ Resolved</option>
        </select>
      </div>

      <div>
        <label class="block text-xs font-semibold text-gray-500 mb-1">Note for the reporter <span class="text-gray-400 font-normal">(optional)</span></label>
        <textarea id="updNote" rows="3" maxlength="500" placeholder="e.g. Security has been dispatched to the location."
          class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm resize-none focus:outline-none focus:ring-2 focus:ring-red-400"></textarea>
        <p class="text-xs text-gray-400 mt-1">The reporter sees this in their Profile → Activity.</p>
      </div>

      <div id="updError" class="hidden bg-red-50 border border-red-200 text-red-700 rounded-xl px-4 py-2 text-sm"></div>

      <div class="flex gap-3">
        <button id="updSaveBtn" onclick="saveIncidentUpdate()"
          class="flex-1 text-white font-semibold py-2.5 rounded-xl transition text-sm hover:opacity-90"
          style="background:linear-gradient(90deg,#152875,#b91c1c);">💾 Save Update</button>
        <button onclick="closeUpdateModal()"
          class="px-5 py-2.5 rounded-xl border border-gray-200 text-gray-500 hover:bg-gray-50 text-sm font-medium transition">Cancel</button>
      </div>

      <div>
        <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">History</p>
        <div id="updHistory" class="space-y-2 text-sm"></div>
      </div>
    </div>
  </div>
</div>

<script>
let updIncidentId = null;
const updEsc = s => String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
const UPD_LABELS = { open:'🔴 Open', in_progress:'🔵 In Progress', resolved:'✅ Resolved' };

async function openUpdateModal(id, status) {
  updIncidentId = id;
  document.getElementById('updTitle').textContent = '📝 Update Incident #' + id;
  document.getElementById('updStatus').value = status;
  document.getElementById('updNote').value = '';
  document.getElementById('updError').classList.add('hidden');
  document.getElementById('updSaveBtn').disabled = false;
  document.getElementById('updHistory').innerHTML = '<p class="text-xs text-gray-400">Loading…</p>';
  const m = document.getElementById('updateModal');
  m.classList.remove('hidden'); m.classList.add('flex');

  try {
    const fd = new FormData();
    fd.append('action', 'get_updates');
    fd.append('incident_id', id);
    const res = await fetch('incident_update_ajax.php', { method: 'POST', body: fd });
    const d = await res.json();
    const box = document.getElementById('updHistory');
    if (!d.success) { box.innerHTML = `<p class="text-xs text-red-500">${updEsc(d.message)}</p>`; return; }
    box.innerHTML = d.updates.length
      ? d.updates.map(u => `
          <div class="bg-gray-50 rounded-xl px-3 py-2">
            <p class="text-xs text-gray-500">${UPD_LABELS[u.status] || updEsc(u.status)} · ${updEsc(u.admin)} · ${updEsc(u.created_at)}</p>
            ${u.note ? `<p class="text-gray-700 mt-0.5">${updEsc(u.note)}</p>` : ''}
          </div>`).join('')
      : '<p class="text-xs text-gray-400">No updates yet.</p>';
  } catch (_) {
    document.getElementById('updHistory').innerHTML = '<p class="text-xs text-red-500">Could not load history.</p>';
  }
}

function closeUpdateModal() {
  const m = document.getElementById('updateModal');
  m.classList.add('hidden'); m.classList.remove('flex');
}
document.getElementById('updateModal').addEventListener('click', function (e) { if (e.target === this) closeUpdateModal(); });

async function saveIncidentUpdate() {
  const btn = document.getElementById('updSaveBtn');
  const err = document.getElementById('updError');
  btn.disabled = true;
  err.classList.add('hidden');

  try {
    const fd = new FormData();
    fd.append('action', 'add_update');
    fd.append('incident_id', updIncidentId);
    fd.append('status', document.getElementById('updStatus').value);
    fd.append('note', document.getElementById('updNote').value);
    const res = await fetch('incident_update_ajax.php', { method: 'POST', body: fd });
    const d = await res.json();

    if (d.success) {
      closeUpdateModal();
      if (typeof showToast === 'function') showToast('📝 Incident updated', 'bg-blue-600');
      setTimeout(() => { location.href = 'admin.php?tab=incidents'; }, 700);
      return;
    }
    err.textContent = d.message || 'Could not save.';
    err.classList.remove('hidden');
  } catch (_) {
    err.textContent = 'Network error. Please try again.';
    err.classList.remove('hidden');
  }
  btn.disabled = false;
}
</script>
