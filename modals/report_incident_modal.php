<!-- =============================================
     REPORT INCIDENT MODAL
     Place this file in: modals/report_incident_modal.php
     ============================================= -->

<?php
require_once __DIR__ . '/../includes/types.php';
$incidentTypeList = incidentTypes($pdo ?? null);
$branchLocations = [];
if (isset($pdo) && $pdo) {
    try {
        $rows = $pdo->query("
            SELECT l.name, b.name AS branch
            FROM locations l
            JOIN branches b ON b.id = l.branch_id
            WHERE l.is_active = 1
            ORDER BY b.name, l.sort_order, l.name
        ")->fetchAll();
        foreach ($rows as $r) {
            $b = $r['branch'] ?: 'Main Campus';
            $branchLocations[$b][] = $r['name'];
        }
    } catch (PDOException $e) {
        $branchLocations = [];
    }
}
if (empty($branchLocations)) {
    $branchLocations = ['Main Campus' => ['engineering','admin','library','cafeteria','gymnasium','parking',
                         'entrance','laboratory','clinic','comfort_room','grounds','other']];
}
?>

<!-- MODAL BACKDROP -->
<div id="reportModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/60 backdrop-blur-sm p-4">

  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto animate-modal">

    <!-- MODAL HEADER -->
    <div class="text-white px-6 py-4 rounded-t-2xl flex items-center justify-between" style="background:linear-gradient(90deg,#152875,#b91c1c);">
      <div class="flex items-center gap-3">
        <span class="text-2xl">🚨</span>
        <div>
          <h2 class="text-lg font-bold leading-tight">Report an Incident</h2>
          <p class="text-red-200 text-xs">Your report helps keep the campus safe</p>
        </div>
      </div>
      <button onclick="closeModal()" class="text-white hover:text-red-200 text-2xl leading-none">&times;</button>
    </div>

    <div class="p-6">

      <div id="successMsg" class="hidden bg-green-50 border border-green-300 text-green-700 rounded-xl p-4 mb-4 text-center">
        <div class="text-3xl mb-1">✅</div>
        <p class="font-semibold">Incident Reported Successfully!</p>
        <p class="text-sm text-green-600 mt-1">Authorities have been notified. Stay safe.</p>
      </div>

      <div id="errorMsg" class="hidden bg-red-50 border border-red-300 text-red-700 rounded-xl p-4 mb-4">
        <p class="font-semibold">⚠️ Submission Failed</p>
        <p class="text-sm mt-1" id="errorText">Please check your entries and try again.</p>
      </div>

      <form id="incidentForm" enctype="multipart/form-data" class="space-y-4">

        <!-- INCIDENT TYPE -->
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1">
            Incident Type <span class="text-red-500">*</span>
          </label>
          <select name="incident_type" id="incident_type" required
            class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-500 bg-white">
            <option value="">— Select Type —</option>
            <?php foreach ($incidentTypeList as $t): ?>
            <option value="<?= htmlspecialchars($t['slug']) ?>"><?= htmlspecialchars($t['icon'] . ' ' . $t['label']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- SEVERITY -->
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">
            Severity Level <span class="text-red-500">*</span>
          </label>

          <div id="severityManual" class="flex gap-2">
            <label class="severity-btn flex-1 cursor-pointer">
              <input type="radio" name="severity" value="low" class="sr-only">
              <div class="severity-label border-2 border-gray-200 rounded-xl p-2 text-center text-sm transition hover:border-green-400">
                <div class="text-lg">🟢</div><div class="font-medium text-gray-700">Low</div>
              </div>
            </label>
            <label class="severity-btn flex-1 cursor-pointer">
              <input type="radio" name="severity" value="medium" class="sr-only">
              <div class="severity-label border-2 border-gray-200 rounded-xl p-2 text-center text-sm transition hover:border-yellow-400">
                <div class="text-lg">🟡</div><div class="font-medium text-gray-700">Medium</div>
              </div>
            </label>
            <label class="severity-btn flex-1 cursor-pointer">
              <input type="radio" name="severity" value="high" class="sr-only">
              <div class="severity-label border-2 border-gray-200 rounded-xl p-2 text-center text-sm transition hover:border-red-400">
                <div class="text-lg">🔴</div><div class="font-medium text-gray-700">High</div>
              </div>
            </label>
            <label class="severity-btn flex-1 cursor-pointer">
              <input type="radio" name="severity" value="critical" class="sr-only">
              <div class="severity-label border-2 border-gray-200 rounded-xl p-2 text-center text-sm transition hover:border-red-700">
                <div class="text-lg">🚨</div><div class="font-medium text-gray-700">Critical</div>
              </div>
            </label>
          </div>

          <div id="severityAI" class="hidden border-2 border-blue-200 rounded-xl p-4 text-center bg-blue-50/50">
            <div class="text-lg" id="aiSeverityIcon"></div>
            <div id="aiSeverityText" class="font-semibold text-gray-700"></div>
            <p id="aiSeverityReason" class="text-xs text-gray-500 mt-1"></p>
            <input type="hidden" name="severity_ai" id="severity_ai_input" value="">
          </div>

          <div id="severityWaiting" class="hidden text-sm text-gray-500 mt-2 text-center">
            🤖 AI will determine severity after you write a description…
          </div>

          <!-- AI CLASSIFY TOGGLE -->
          <div class="flex items-center gap-3 bg-blue-50 rounded-xl p-3 mt-2">
            <input type="checkbox" id="aiClassify" checked class="w-4 h-4 accent-blue-600">
            <div>
              <label for="aiClassify" class="text-sm font-medium text-gray-700 cursor-pointer">🤖 Let AI suggest severity</label>
              <p class="text-xs text-gray-400">AI will auto-select based on your description</p>
            </div>
          </div>
        </div>

        <!-- SCHOOL BRANCH -->
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1">
            School Branch <span class="text-red-500">*</span>
          </label>
          <select id="branch" required
            class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-500 bg-white">
            <option value="">— Select Branch —</option>
            <?php foreach (array_keys($branchLocations) as $b): ?>
            <option value="<?= htmlspecialchars($b) ?>"><?= htmlspecialchars($b) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- LOCATION / ROOM -->
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1">
            Location / Room <span class="text-red-500">*</span>
          </label>
          <select name="location" id="location" required disabled
            class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-500 bg-white disabled:bg-gray-100 disabled:text-gray-400">
            <option value="">— Select a branch first —</option>
          </select>
        </div>

        <!-- DESCRIPTION -->
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1">
            Description <span class="text-red-500">*</span>
          </label>
          <textarea name="description" id="description" rows="3" required minlength="10"
            placeholder="Describe what happened in detail — who, what, when, where..."
            class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-500 resize-none"></textarea>
          <p class="text-xs text-gray-400 mt-1">Minimum 10 characters</p>
        </div>

        <!-- PHOTO UPLOAD -->
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1">
            Photo Evidence <span class="text-gray-400 font-normal">(optional)</span>
          </label>
          <div id="dropzone"
            class="border-2 border-dashed border-gray-300 rounded-xl p-4 text-center cursor-pointer hover:border-red-400 hover:bg-red-50 transition"
            onclick="document.getElementById('photoInput').click()">
            <div id="dropzoneContent">
              <div class="text-3xl mb-1">📷</div>
              <p class="text-sm text-gray-500">Click to upload a photo</p>
              <p class="text-xs text-gray-400">JPG, PNG, GIF up to 5MB</p>
            </div>
            <img id="previewImg" class="hidden mx-auto max-h-32 rounded-lg mt-2 object-contain" alt="Preview">
          </div>
          <input type="file" name="photo" id="photoInput" accept="image/*" class="hidden">
        </div>

        <!-- ANONYMOUS TOGGLE -->
        <div class="flex items-center gap-3 bg-gray-50 rounded-xl p-3">
          <input type="checkbox" name="anonymous" id="anonymous" value="1" class="w-4 h-4 accent-red-600">
          <div>
            <label for="anonymous" class="text-sm font-medium text-gray-700 cursor-pointer">Submit anonymously</label>
            <p class="text-xs text-gray-400">Your name will not appear on this report</p>
          </div>
        </div>

        <!-- SUBMIT -->
        <button type="submit" id="submitBtn"
          class="w-full text-white font-bold py-3 rounded-xl transition flex items-center justify-center gap-2 hover:opacity-90"
          style="background:linear-gradient(90deg,#152875,#b91c1c);">
          <span id="submitText">🚨 Submit Report</span>
          <svg id="submitSpinner" class="hidden animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
          </svg>
        </button>

      </form>
    </div>

  </div>
</div>

<style>
  @keyframes modalIn {
    from { opacity: 0; transform: scale(0.95) translateY(10px); }
    to   { opacity: 1; transform: scale(1) translateY(0); }
  }
  .animate-modal { animation: modalIn 0.2s ease-out; }
  .severity-btn input:checked + .severity-label { border-color: #b91c1c; background-color: #fef2f2; }
</style>

<script>
const BRANCH_LOCATIONS = <?= json_encode($branchLocations) ?>;
const fmtName = r => r.replace(/_/g,' ').replace(/\b\w/g,c=>c.toUpperCase());

function populateRoomsForBranch(branchName) {
  const locSel = document.getElementById('location');
  const rooms  = BRANCH_LOCATIONS[branchName] || [];
  if (!branchName || rooms.length === 0) {
    locSel.innerHTML = '<option value="">— Select a branch first —</option>';
    locSel.disabled  = true;
    return;
  }
  locSel.innerHTML = '<option value="">— Select Building/Area —</option>' +
    rooms.map(r => `<option value="${r}">${fmtName(r)}</option>`).join('');
  locSel.disabled = false;
}

document.getElementById('branch').addEventListener('change', function() {
  populateRoomsForBranch(this.value);
});

// ---- Open / Close ----
function openReportModal() {
  const modal = document.getElementById('reportModal');
  modal.classList.remove('hidden');
  modal.classList.add('flex');
  document.body.style.overflow = 'hidden';
  toggleSeverityView();
}

function closeModal() {
  const modal = document.getElementById('reportModal');
  modal.classList.add('hidden');
  modal.classList.remove('flex');
  document.body.style.overflow = '';
  resetForm();
}

function resetForm() {
  document.getElementById('incidentForm').reset();
  document.getElementById('successMsg').classList.add('hidden');
  document.getElementById('errorMsg').classList.add('hidden');
  document.getElementById('previewImg').classList.add('hidden');
  document.getElementById('dropzoneContent').classList.remove('hidden');
  document.getElementById('submitBtn').disabled = false;
  document.getElementById('submitText').textContent = '🚨 Submit Report';
  document.getElementById('submitSpinner').classList.add('hidden');
  document.getElementById('severity_ai_input').value = '';
  document.getElementById('severityAI').classList.add('hidden');
  document.getElementById('severityWaiting').classList.add('hidden');
  document.getElementById('branch').value = '';
  populateRoomsForBranch('');
  toggleSeverityView();
}

document.getElementById('reportModal').addEventListener('click', function(e) {
  if (e.target === this) closeModal();
});

// ---- Photo Preview ----
document.getElementById('photoInput').addEventListener('change', function() {
  const file = this.files[0];
  if (!file) return;
  if (file.size > 5 * 1024 * 1024) { alert('File too large. Max 5MB.'); this.value = ''; return; }
  const reader = new FileReader();
  reader.onload = (e) => {
    document.getElementById('previewImg').src = e.target.result;
    document.getElementById('previewImg').classList.remove('hidden');
    document.getElementById('dropzoneContent').classList.add('hidden');
  };
  reader.readAsDataURL(file);
});

// ---- Form Submit ----
document.getElementById('incidentForm').addEventListener('submit', async function(e) {
  e.preventDefault();

  const type     = document.getElementById('incident_type').value;
  const branch   = document.getElementById('branch').value;
  const location = document.getElementById('location').value;
  const desc     = document.getElementById('description').value.trim();

  if (!type || !branch || !location || !desc) { showError('Please fill in all required fields.'); return; }
  if (desc.length < 10) { showError('Description must be at least 10 characters.'); return; }

  let severity = null;
  if (document.getElementById('aiClassify').checked) {
    severity = document.getElementById('severity_ai_input').value;
    if (!severity) { showError('AI has not yet determined severity. Please wait or disable AI to select manually.'); return; }
  } else {
    const radio = document.querySelector('input[name="severity"]:checked');
    if (!radio) { showError('Please select a severity level.'); return; }
    severity = radio.value;
  }

  const btn = document.getElementById('submitBtn');
  btn.disabled = true;
  document.getElementById('submitText').textContent = 'Submitting...';
  document.getElementById('submitSpinner').classList.remove('hidden');
  document.getElementById('errorMsg').classList.add('hidden');

  const formData = new FormData(this);
  formData.append('severity', severity);
  formData.append('branch', branch);

  try {
    const response = await fetch('submit_incident.php', { method: 'POST', body: formData });
    const result = await response.json();

    if (result.success) {
      document.getElementById('incidentForm').classList.add('hidden');
      document.getElementById('successMsg').classList.remove('hidden');
      setTimeout(() => {
        closeModal();
        document.getElementById('incidentForm').classList.remove('hidden');
        if (typeof refreshIncidents === 'function') refreshIncidents();
      }, 3000);
    } else {
      showError(result.message || 'Submission failed. Please try again.');
      btn.disabled = false;
      document.getElementById('submitText').textContent = '🚨 Submit Report';
      document.getElementById('submitSpinner').classList.add('hidden');
    }
  } catch (err) {
    showError('Network error. Please check your connection and try again.');
    btn.disabled = false;
    document.getElementById('submitText').textContent = '🚨 Submit Report';
    document.getElementById('submitSpinner').classList.add('hidden');
  }
});

function showError(msg) {
  document.getElementById('errorText').textContent = msg;
  document.getElementById('errorMsg').classList.remove('hidden');
}

// ---- AI Severity Classification ----
let classifyTimer = null;
const SEV_ICONS  = { low: '🟢', medium: '🟡', high: '🔴', critical: '🚨' };
const SEV_COLORS = { low:'text-green-700', medium:'text-yellow-700', high:'text-orange-700', critical:'text-red-700' };

function showAISeverity(sev, reason) {
  document.getElementById('severityWaiting').classList.add('hidden');
  document.getElementById('severityAI').classList.remove('hidden');
  document.getElementById('severity_ai_input').value = sev;
  document.getElementById('aiSeverityIcon').textContent = SEV_ICONS[sev] || '🤖';
  document.getElementById('aiSeverityText').textContent = sev.toUpperCase();
  document.getElementById('aiSeverityText').className = `font-semibold ${SEV_COLORS[sev] || 'text-gray-700'}`;
  document.getElementById('aiSeverityReason').textContent = reason || '';
}

let classifyReq = 0;
async function classifySeverity() {
  if (!document.getElementById('aiClassify').checked) return;
  const reqId = ++classifyReq;

  const type = document.getElementById('incident_type').value;
  const desc = document.getElementById('description').value.trim();

  document.getElementById('severity_ai_input').value = '';
  document.getElementById('severityAI').classList.add('hidden');
  document.getElementById('severityWaiting').classList.remove('hidden');
  document.getElementById('severityWaiting').textContent = '🤖 AI will determine severity after you write a description…';

  if (!type || desc.length < 15) return;

  document.getElementById('severityWaiting').textContent = '🤖 AI is analyzing…';

  try {
    const fd = new FormData();
    fd.append('incident_type', type);
    fd.append('description', desc);
    const res  = await fetch('classify_incident.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (reqId !== classifyReq) return; // newer typing superseded this response

    if (data.severity) {
      showAISeverity(data.severity, data.reason);
    } else {
      document.getElementById('severityWaiting').classList.remove('hidden');
      document.getElementById('severityWaiting').textContent = '⚠️ ' + (data.reason || 'Could not classify') + ' – turn off AI to select manually.';
    }
  } catch (_) {
    document.getElementById('severityWaiting').classList.remove('hidden');
    document.getElementById('severityWaiting').textContent = '⚠️ AI unavailable – turn off AI to select manually.';
  }
}

function toggleSeverityView() {
  const aiOn = document.getElementById('aiClassify').checked;
  const manualDiv = document.getElementById('severityManual');
  const aiDiv = document.getElementById('severityAI');
  const waitingDiv = document.getElementById('severityWaiting');
  const radios = document.querySelectorAll('input[name="severity"]');

  if (aiOn) {
    manualDiv.classList.add('hidden');
    radios.forEach(r => r.checked = false);
    if (document.getElementById('severity_ai_input').value) {
      aiDiv.classList.remove('hidden');
      waitingDiv.classList.add('hidden');
    } else {
      aiDiv.classList.add('hidden');
      waitingDiv.classList.remove('hidden');
      const desc = document.getElementById('description').value.trim();
      const type = document.getElementById('incident_type').value;
      if (type && desc.length >= 15) classifySeverity();
    }
  } else {
    manualDiv.classList.remove('hidden');
    aiDiv.classList.add('hidden');
    waitingDiv.classList.add('hidden');
    document.getElementById('severity_ai_input').value = '';
  }
}

document.getElementById('aiClassify').addEventListener('change', toggleSeverityView);

document.getElementById('description').addEventListener('input', function() {
  clearTimeout(classifyTimer);
  classifyTimer = setTimeout(classifySeverity, 500);
});

document.getElementById('incident_type').addEventListener('change', function() {
  clearTimeout(classifyTimer);
  classifyTimer = setTimeout(classifySeverity, 400);
});
</script>