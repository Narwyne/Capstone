<!-- =============================================
     REPORT INCIDENT MODAL
     Include this anywhere in your dashboard.php body
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

        <!-- SEVERITY -->
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">
            Severity Level <span class="text-red-500">*</span>
          </label>
          <div class="flex gap-2">
            <label class="severity-btn flex-1 cursor-pointer">
              <input type="radio" name="severity" value="low" class="sr-only" required>
              <div class="severity-label border-2 border-gray-200 rounded-xl p-2 text-center text-sm transition hover:border-green-400 peer-checked:bg-green-500">
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

  // Basic validation
  const type     = document.getElementById('incident_type').value;
  const location = document.getElementById('location').value;
  const desc     = document.getElementById('description').value.trim();
  const severity = document.querySelector('input[name="severity"]:checked');

  if (!type || !location || !desc || !severity) {
    showError('Please fill in all required fields.');
    return;
  }
  if (desc.length < 10) {
    showError('Description must be at least 10 characters.');
    return;
  }

  // Loading state
  const btn = document.getElementById('submitBtn');
  btn.disabled = true;
  document.getElementById('submitText').textContent = 'Submitting...';
  document.getElementById('submitSpinner').classList.remove('hidden');
  document.getElementById('errorMsg').classList.add('hidden');

  // Build form data
  const formData = new FormData(this);

  try {
    const response = await fetch('submit_incident.php', {
      method: 'POST',
      body: formData
    });

    const result = await response.json();

    if (result.success) {
      document.getElementById('incidentForm').classList.add('hidden');
      document.getElementById('successMsg').classList.remove('hidden');
      // Auto-close after 3s and refresh incident list
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
</script>