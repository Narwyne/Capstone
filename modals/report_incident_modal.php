<!-- =============================================
     REPORT INCIDENT MODAL
     Place this file in: modal/your_modal.php (or include from there)
     The PHP scripts are assumed to be in the parent folder.
     ============================================= -->

<!-- MODAL BACKDROP -->
<div id="reportModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/60 backdrop-blur-sm p-4">
  
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto animate-modal">
    
    <!-- MODAL HEADER -->
    <div class="bg-red-700 text-white px-6 py-4 rounded-t-2xl flex items-center justify-between">
      <div class="flex items-center gap-3">
        <span class="text-2xl">🚨</span>
        <div>
          <h2 class="text-lg font-bold leading-tight">Report an Incident</h2>
          <p class="text-red-200 text-xs">Your report helps keep the campus safe</p>
        </div>
      </div>
      <button onclick="closeModal()" class="text-white hover:text-red-200 text-2xl leading-none">&times;</button>
    </div>

    <!-- FORM -->
    <div class="p-6">

      <!-- SUCCESS MESSAGE (hidden by default) -->
      <div id="successMsg" class="hidden bg-green-50 border border-green-300 text-green-700 rounded-xl p-4 mb-4 text-center">
        <div class="text-3xl mb-1">✅</div>
        <p class="font-semibold">Incident Reported Successfully!</p>
        <p class="text-sm text-green-600 mt-1">Authorities have been notified. Stay safe.</p>
      </div>

      <!-- ERROR MESSAGE (hidden by default) -->
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
            <option value="fire">🔥 Fire / Smoke</option>
            <option value="medical">🏥 Medical Emergency</option>
            <option value="accident">⚠️ Accident / Injury</option>
            <option value="suspicious">👁️ Suspicious Activity</option>
            <option value="theft">🔓 Theft / Robbery</option>
            <option value="flooding">🌊 Flooding</option>
            <option value="earthquake">🌍 Earthquake Damage</option>
            <option value="other">📋 Other</option>
          </select>
        </div>

        <!-- SEVERITY (with AI auto‑hide) -->
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">
            Severity Level <span class="text-red-500">*</span>
          </label>

          <!-- Manual severity radios (hidden when AI is on) -->
          <div id="severityManual" class="flex gap-2">
            <label class="severity-btn flex-1 cursor-pointer">
              <input type="radio" name="severity" value="low" class="sr-only">
              <div class="severity-label border-2 border-gray-200 rounded-xl p-2 text-center text-sm transition hover:border-green-400">
                <div class="text-lg">🟢</div>
                <div class="font-medium text-gray-700">Low</div>
              </div>
            </label>
            <label class="severity-btn flex-1 cursor-pointer">
              <input type="radio" name="severity" value="medium" class="sr-only">
              <div class="severity-label border-2 border-gray-200 rounded-xl p-2 text-center text-sm transition hover:border-yellow-400">
                <div class="text-lg">🟡</div>
                <div class="font-medium text-gray-700">Medium</div>
              </div>
            </label>
            <label class="severity-btn flex-1 cursor-pointer">
              <input type="radio" name="severity" value="high" class="sr-only">
              <div class="severity-label border-2 border-gray-200 rounded-xl p-2 text-center text-sm transition hover:border-red-400">
                <div class="text-lg">🔴</div>
                <div class="font-medium text-gray-700">High</div>
              </div>
            </label>
            <label class="severity-btn flex-1 cursor-pointer">
              <input type="radio" name="severity" value="critical" class="sr-only">
              <div class="severity-label border-2 border-gray-200 rounded-xl p-2 text-center text-sm transition hover:border-red-700">
                <div class="text-lg">🚨</div>
                <div class="font-medium text-gray-700">Critical</div>
              </div>
            </label>
          </div>

          <!-- AI-selected severity display (hidden when AI is off) -->
          <div id="severityAI" class="hidden border-2 border-blue-200 rounded-xl p-4 text-center bg-blue-50/50">
            <div class="text-lg" id="aiSeverityIcon"></div>
            <div id="aiSeverityText" class="font-semibold text-gray-700"></div>
            <p id="aiSeverityReason" class="text-xs text-gray-500 mt-1"></p>
            <!-- Hidden input to hold the actual value for form submission -->
            <input type="hidden" name="severity_ai" id="severity_ai_input" value="">
          </div>

          <!-- AI Waiting message (shown before prediction) -->
          <div id="severityWaiting" class="hidden text-sm text-gray-500 mt-2 text-center">
            🤖 AI will determine severity after you write a description…
          </div>

          <!-- AI CLASSIFY TOGGLE -->
          <div class="flex items-center gap-3 bg-blue-50 rounded-xl p-3 mt-2">
            <input type="checkbox" id="aiClassify" checked
              class="w-4 h-4 accent-blue-600">
            <div>
              <label for="aiClassify" class="text-sm font-medium text-gray-700 cursor-pointer">
                🤖 Let AI suggest severity
              </label>
              <p class="text-xs text-gray-400">AI will auto-select based on your description</p>
            </div>
          </div>
        </div>

        <!-- LOCATION -->
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1">
            Location <span class="text-red-500">*</span>
          </label>
          <select name="location" id="location" required
            class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-500 bg-white">
            <option value="">— Select Building/Area —</option>
            <option value="engineering">Engineering Building</option>
            <option value="admin">Administration Building</option>
            <option value="library">Library</option>
            <option value="cafeteria">Cafeteria</option>
            <option value="gymnasium">Gymnasium</option>
            <option value="parking">Parking Area</option>
            <option value="entrance">Main Entrance / Gate</option>
            <option value="laboratory">Computer Laboratory</option>
            <option value="clinic">School Clinic</option>
            <option value="comfort_room">Comfort Room</option>
            <option value="grounds">School Grounds / Open Area</option>
            <option value="other">Other (specify in description)</option>
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
          <input type="checkbox" name="anonymous" id="anonymous" value="1"
            class="w-4 h-4 accent-red-600">
          <div>
            <label for="anonymous" class="text-sm font-medium text-gray-700 cursor-pointer">
              Submit anonymously
            </label>
            <p class="text-xs text-gray-400">Your name will not appear on this report</p>
          </div>
        </div>

        <!-- SUBMIT -->
        <button type="submit" id="submitBtn"
          class="w-full bg-red-700 hover:bg-red-800 text-white font-bold py-3 rounded-xl transition flex items-center justify-center gap-2">
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

<!-- =============================================
     STYLES
     ============================================= -->
<style>
  @keyframes modalIn {
    from { opacity: 0; transform: scale(0.95) translateY(10px); }
    to   { opacity: 1; transform: scale(1) translateY(0); }
  }
  .animate-modal { animation: modalIn 0.2s ease-out; }

  /* Severity radio highlight */
  .severity-btn input:checked + .severity-label {
    border-color: #b91c1c;
    background-color: #fef2f2;
  }
</style>

<!-- =============================================
     JAVASCRIPT
     ============================================= -->
<script>
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
  // Clear AI state
  document.getElementById('severity_ai_input').value = '';
  document.getElementById('severityAI').classList.add('hidden');
  document.getElementById('severityWaiting').classList.add('hidden');
  toggleSeverityView();
}

// Close on backdrop click
document.getElementById('reportModal').addEventListener('click', function(e) {
  if (e.target === this) closeModal();
});

// ---- Photo Preview ----
document.getElementById('photoInput').addEventListener('change', function() {
  const file = this.files[0];
  if (!file) return;
  if (file.size > 5 * 1024 * 1024) {
    alert('File too large. Max 5MB.'); 
    this.value = ''; 
    return;
  }
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
  const location = document.getElementById('location').value;
  const desc     = document.getElementById('description').value.trim();

  if (!type || !location || !desc) {
    showError('Please fill in all required fields.');
    return;
  }
  if (desc.length < 10) {
    showError('Description must be at least 10 characters.');
    return;
  }

  let severity = null;
  if (document.getElementById('aiClassify').checked) {
    severity = document.getElementById('severity_ai_input').value;
    if (!severity) {
      showError('AI has not yet determined severity. Please wait or disable AI to select manually.');
      return;
    }
  } else {
    const radio = document.querySelector('input[name="severity"]:checked');
    if (!radio) {
      showError('Please select a severity level.');
      return;
    }
    severity = radio.value;
  }

  const btn = document.getElementById('submitBtn');
  btn.disabled = true;
  document.getElementById('submitText').textContent = 'Submitting...';
  document.getElementById('submitSpinner').classList.remove('hidden');
  document.getElementById('errorMsg').classList.add('hidden');

  const formData = new FormData(this);
  formData.append('severity', severity);

  try {
    // *** PATH CORRECTION: go up one level to find submit_incident.php ***
    const response = await fetch('../submit_incident.php', {
      method: 'POST',
      body: formData
    });

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

async function classifySeverity() {
  if (!document.getElementById('aiClassify').checked) return;

  const type = document.getElementById('incident_type').value;
  const desc = document.getElementById('description').value.trim();

  document.getElementById('severity_ai_input').value = '';
  document.getElementById('severityAI').classList.add('hidden');
  document.getElementById('severityWaiting').classList.remove('hidden');
  document.getElementById('severityWaiting').textContent = '🤖 AI will determine severity after you write a description…';

  if (!type || desc.length < 15) return;

  try {
    const fd = new FormData();
    fd.append('incident_type', type);
    fd.append('description', desc);

    // *** PATH CORRECTION: go up one level to find classify_incident.php ***
    const res  = await fetch('../classify_incident.php', {
      method: 'POST',
      body: fd
    });
    const data = await res.json();

    if (data.severity) {
      document.getElementById('severityWaiting').classList.add('hidden');
      document.getElementById('severityAI').classList.remove('hidden');
      document.getElementById('severity_ai_input').value = data.severity;

      const icons = { low: '🟢', medium: '🟡', high: '🔴', critical: '🚨' };
      const colors = {
        low: 'text-green-700', medium: 'text-yellow-700',
        high: 'text-orange-700', critical: 'text-red-700'
      };
      document.getElementById('aiSeverityIcon').textContent = icons[data.severity] || '🤖';
      document.getElementById('aiSeverityText').textContent = data.severity.toUpperCase();
      document.getElementById('aiSeverityText').className = `font-semibold ${colors[data.severity] || 'text-gray-700'}`;
      document.getElementById('aiSeverityReason').textContent = data.reason || '';
    } else {
      document.getElementById('severityWaiting').classList.remove('hidden');
      document.getElementById('severityWaiting').textContent = '⚠️ Could not classify – please select manually.';
    }
  } catch (_) {
    document.getElementById('severityWaiting').classList.remove('hidden');
    document.getElementById('severityWaiting').textContent = '⚠️ AI unavailable – select severity below.';
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
  classifyTimer = setTimeout(classifySeverity, 800);
});

document.getElementById('incident_type').addEventListener('change', function() {
  clearTimeout(classifyTimer);
  classifyTimer = setTimeout(classifySeverity, 400);
});
</script>