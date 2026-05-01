// js/incidents.js

// ── Constants ─────────────────────────────────────────────────────
const SEV_ORDER = { critical: 4, high: 3, medium: 2, low: 1 };

const CHIP_LABELS = {
  severityFilter: { critical:'🚨 Critical', high:'🔴 High', medium:'🟡 Medium', low:'🟢 Low' },
  statusFilter:   { open:'🔴 Open', in_progress:'🔵 In Progress', resolved:'✅ Resolved' },
  typeFilter:     { fire:'🔥 Fire', medical:'🏥 Medical', accident:'⚠️ Accident',
                    suspicious:'👁️ Suspicious', theft:'🔓 Theft', flooding:'🌊 Flooding',
                    earthquake:'🌍 Earthquake', other:'📋 Other' },
};

let debounceTimer = null;

// ── Debounced search input ─────────────────────────────────────────
function onSearchInput() {
  const btn = document.getElementById('clearSearchBtn');
  btn.classList.toggle('hidden', !document.getElementById('searchInput').value);

  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(filterCards, 180);
}

function clearSearch() {
  document.getElementById('searchInput').value = '';
  document.getElementById('clearSearchBtn').classList.add('hidden');
  filterCards();
}

// ── Main filter + sort ────────────────────────────────────────────
function filterCards() {
  const q   = document.getElementById('searchInput').value.trim().toLowerCase();
  const sev = document.getElementById('severityFilter').value;
  const sta = document.getElementById('statusFilter').value;
  const typ = document.getElementById('typeFilter').value;
  const sort= document.getElementById('sortOrder').value;

  const list  = document.getElementById('incidentList');
  const cards = Array.from(list.querySelectorAll('.inc-card'));
  let visible = 0;

  cards.forEach(card => {
    const type     = (card.dataset.type        || '').toLowerCase();
    const location = (card.dataset.location    || '').toLowerCase();
    const reporter = (card.dataset.reporter    || '').toLowerCase();
    const desc     = (card.dataset.description || '').toLowerCase();
    const severity =  card.dataset.severity    || '';
    const status   =  card.dataset.status      || '';

    // Multi-word: every word must match at least one field
    const words  = q ? q.split(/\s+/).filter(Boolean) : [];
    const matchQ = words.every(w =>
      type.includes(w) || location.includes(w) || reporter.includes(w) || desc.includes(w)
    );
    const matchSev = !sev || severity === sev;
    const matchSta = !sta || status   === sta;
    const matchTyp = !typ || type     === typ;

    const show = matchQ && matchSev && matchSta && matchTyp;
    card.style.display = show ? '' : 'none';
    if (show) visible++;

    highlightCard(card, show ? words : []);
  });

  // Sort visible cards
  const visibleCards = cards.filter(c => c.style.display !== 'none');
  visibleCards.sort((a, b) => {
    if (sort === 'oldest')   return (a.dataset.date || 0) - (b.dataset.date || 0);
    if (sort === 'severity') return (SEV_ORDER[b.dataset.severity] || 0) - (SEV_ORDER[a.dataset.severity] || 0);
    return (b.dataset.date || 0) - (a.dataset.date || 0);
  });
  visibleCards.forEach(card => list.appendChild(card));

  updateCount(visible, cards.length, q || sev || sta || typ);
  updateChips(q, sev, sta, typ);
}

// ── Text highlight ────────────────────────────────────────────────
function highlightCard(card, words) {
  card.querySelectorAll('[data-hl]').forEach(el => {
    // Store the original plain text on first run
    if (!el.dataset.original) el.dataset.original = el.textContent;

    if (!words.length) {
      el.textContent = el.dataset.original;
      return;
    }

    // Escape for safe innerHTML insertion, then wrap matches
    let html = el.dataset.original
      .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');

    words.forEach(w => {
      if (!w) return;
      const re = new RegExp(`(${w.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'gi');
      html = html.replace(re, '<mark style="background:#fef08a;color:#713f12;border-radius:3px;padding:0 2px;">$1</mark>');
    });
    el.innerHTML = html;
  });
}

// ── Result count ──────────────────────────────────────────────────
function updateCount(visible, total, hasFilters) {
  const el = document.getElementById('resultCount');
  const btn = document.getElementById('clearAllBtn');
  if (!el) return;
  if (!hasFilters) {
    el.textContent = `Showing all ${total} incident${total !== 1 ? 's' : ''}`;
    btn.classList.add('hidden');
  } else {
    el.textContent = `${visible} of ${total} incident${total !== 1 ? 's' : ''} match`;
    btn.classList.toggle('hidden', !hasFilters);
  }
}

// ── Active filter chips ───────────────────────────────────────────
function updateChips(q, sev, sta, typ) {
  const wrap = document.getElementById('activeChips');
  wrap.innerHTML = '';
  const chips = [];

  if (q)   chips.push({ label: `"${q}"`,
    clear: () => clearSearch() });
  if (sev) chips.push({ label: CHIP_LABELS.severityFilter[sev] || sev,
    clear: () => { document.getElementById('severityFilter').value = ''; filterCards(); } });
  if (sta) chips.push({ label: CHIP_LABELS.statusFilter[sta] || sta,
    clear: () => { document.getElementById('statusFilter').value = ''; filterCards(); } });
  if (typ) chips.push({ label: CHIP_LABELS.typeFilter[typ] || typ,
    clear: () => { document.getElementById('typeFilter').value = ''; filterCards(); } });

  chips.forEach(chip => {
    const span = document.createElement('span');
    span.className = 'inline-flex items-center gap-1 bg-red-50 text-red-700 border border-red-200 text-xs font-semibold px-2.5 py-1 rounded-full';
    span.innerHTML = `${escHtml(chip.label)} <button class="hover:text-red-900 ml-0.5 text-base leading-none">×</button>`;
    span.querySelector('button').addEventListener('click', chip.clear);
    wrap.appendChild(span);
  });

  wrap.classList.toggle('hidden', chips.length === 0);
  wrap.classList.toggle('flex', chips.length > 0);
}

// ── Clear all ─────────────────────────────────────────────────────
function clearFilters() {
  document.getElementById('searchInput').value    = '';
  document.getElementById('severityFilter').value = '';
  document.getElementById('statusFilter').value   = '';
  document.getElementById('typeFilter').value     = '';
  document.getElementById('sortOrder').value      = 'newest';
  document.getElementById('clearSearchBtn').classList.add('hidden');
  filterCards();
}

// ── Photo lightbox ────────────────────────────────────────────────
function showPhoto(src, caption) {
  document.getElementById('photoImg').src             = src;
  document.getElementById('photoCaption').textContent = caption;
  const m = document.getElementById('photoModal');
  m.classList.remove('hidden');
  m.classList.add('flex');
  document.body.style.overflow = 'hidden';
}

function closePhoto() {
  const m = document.getElementById('photoModal');
  m.classList.add('hidden');
  m.classList.remove('flex');
  document.body.style.overflow = '';
}

function escHtml(s) {
  return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

// ── Keyboard shortcuts ────────────────────────────────────────────
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') { closePhoto(); return; }
  if (e.key === '/' && document.activeElement.tagName !== 'INPUT') {
    e.preventDefault();
    document.getElementById('searchInput').focus();
  }
});