// js/profile.js

// ── Tab switching ─────────────────────────────────────────────────
function switchTab(tab) {
  ['profile', 'settings', 'security', 'activity'].forEach(t => {
    document.getElementById('panel-' + t).classList.toggle('hidden', t !== tab);
    const btn = document.getElementById('tab-' + t);
    btn.classList.toggle('active', t === tab);
    btn.classList.toggle('text-gray-500', t !== tab);
  });
}

// ── Toggle switch (settings) ──────────────────────────────────────
function toggleSwitch(id) {
  const cb    = document.getElementById(id);
  const track = cb.nextElementSibling;
  const thumb = track.querySelector('.toggle-thumb');

  cb.checked = !cb.checked;

  if (cb.checked) {
    track.style.background = '#b91c1c';
    thumb.style.transform  = 'translateX(20px)';
  } else {
    track.style.background = '';
    thumb.style.transform  = '';
  }
}

// Sync toggle visual state on page load
document.addEventListener('DOMContentLoaded', () => {
  ['notif_email', 'notif_sms'].forEach(id => {
    const cb = document.getElementById(id);
    if (!cb) return;
    const track = cb.nextElementSibling;
    const thumb = track?.querySelector('.toggle-thumb');
    if (!track || !thumb) return;
    if (cb.checked) {
      track.style.background = '#b91c1c';
      thumb.style.transform  = 'translateX(20px)';
    }
  });

  // Theme card highlight on change
  document.querySelectorAll('.theme-opt input').forEach(radio => {
    radio.addEventListener('change', () => {
      document.querySelectorAll('.theme-card').forEach(c => {
        c.classList.remove('border-red-500', 'bg-red-50');
        c.classList.add('border-gray-200');
      });
      const card = radio.nextElementSibling;
      card.classList.add('border-red-500', 'bg-red-50');
      card.classList.remove('border-gray-200');
    });
  });
});

// ── Password visibility toggle ────────────────────────────────────
function togglePw(inputId, btn) {
  const input = document.getElementById(inputId);
  if (input.type === 'password') {
    input.type = 'text';
    btn.textContent = '🙈';
  } else {
    input.type = 'password';
    btn.textContent = '👁';
  }
}

// ── Password strength meter ───────────────────────────────────────
function checkStrength(val) {
  const bar   = document.getElementById('strengthBar');
  const label = document.getElementById('strengthLabel');
  if (!bar || !label) return;

  let score = 0;
  if (val.length >= 8)               score++;
  if (val.length >= 12)              score++;
  if (/[A-Z]/.test(val))            score++;
  if (/[0-9]/.test(val))            score++;
  if (/[^A-Za-z0-9]/.test(val))    score++;

  const levels = [
    { pct: '0%',   color: '',          text: '' },
    { pct: '25%',  color: '#ef4444',   text: 'Weak' },
    { pct: '50%',  color: '#f97316',   text: 'Fair' },
    { pct: '75%',  color: '#f59e0b',   text: 'Good' },
    { pct: '90%',  color: '#10b981',   text: 'Strong' },
    { pct: '100%', color: '#059669',   text: 'Very strong' },
  ];

  const lvl = levels[Math.min(score, 5)];
  bar.style.width      = val.length ? lvl.pct   : '0%';
  bar.style.background = lvl.color;
  label.textContent    = val.length ? lvl.text  : '';
  label.style.color    = lvl.color;
}
