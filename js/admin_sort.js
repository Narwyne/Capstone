// js/admin_sort.js — adds sort dropdowns to every admin tab.
// Sorts the DOM only (works with your existing search/filters).
(function () {
  const SEV = { critical: 4, high: 3, medium: 2, low: 1 };
  const STA = { open: 0, in_progress: 1, resolved: 2 };
  const cls = 'border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-400 bg-white shrink-0';
  const str = (a, b) => String(a).localeCompare(String(b), undefined, { sensitivity: 'base' });
  const txt = (el, sel) => (el.querySelector(sel)?.textContent || '').trim();

  // Remember the server's original order (incidents/users: newest first)
  function tag(els) { els.forEach((e, i) => { if (e.dataset.orig === undefined) e.dataset.orig = i; }); }
  const orig = e => +e.dataset.orig;

  function apply(parent, els, cmp) {
    if (!parent) return;
    tag(els);
    els.sort(cmp).forEach(e => parent.appendChild(e));
  }

  function makeSelect(options, onChange) {
    const s = document.createElement('select');
    s.className = cls;
    s.innerHTML = options.map(([v, l]) => `<option value="${v}">${l}</option>`).join('');
    s.addEventListener('change', () => onChange(s.value));
    return s;
  }

  function sortBar(select, label) {
    const d = document.createElement('div');
    d.className = 'flex items-center justify-end gap-2 mb-3 text-xs text-gray-400';
    d.append(label, select);
    return d;
  }

  // ── Incidents ───────────────────────────────────────
  const incCmp = {
    newest:   (a, b) => orig(a) - orig(b),
    oldest:   (a, b) => orig(b) - orig(a),
    sev_desc: (a, b) => (SEV[b.dataset.severity] || 0) - (SEV[a.dataset.severity] || 0) || orig(a) - orig(b),
    sev_asc:  (a, b) => (SEV[a.dataset.severity] || 0) - (SEV[b.dataset.severity] || 0) || orig(a) - orig(b),
    status:   (a, b) => (STA[a.dataset.status] ?? 9) - (STA[b.dataset.status] ?? 9) || orig(a) - orig(b),
    type:     (a, b) => str(a.dataset.type, b.dataset.type) || orig(a) - orig(b),
    location: (a, b) => str(a.dataset.location, b.dataset.location) || orig(a) - orig(b),
  };
  function sortIncidents(k) {
    const tb = document.getElementById('incidentTbody'), cards = document.getElementById('incidentCards');
    if (tb)    apply(tb, Array.from(tb.children), incCmp[k]);
    if (cards) apply(cards, Array.from(cards.children), incCmp[k]);
  }

  // ── Users ───────────────────────────────────────────
  const userCmp = {
    newest: (a, b) => orig(a) - orig(b),
    oldest: (a, b) => orig(b) - orig(a),
    name:   (a, b) => str(a.dataset.name, b.dataset.name),
    name_z: (a, b) => str(b.dataset.name, a.dataset.name),
    email:  (a, b) => str(a.dataset.email, b.dataset.email),
    role:   (a, b) => (b.dataset.role === 'admin') - (a.dataset.role === 'admin') || str(a.dataset.name, b.dataset.name),
  };
  function sortUsers(k) {
    const tb = document.getElementById('userTbody'), cards = document.getElementById('userCards');
    if (tb)    apply(tb, Array.from(tb.children), userCmp[k]);
    if (cards) apply(cards, Array.from(cards.children), userCmp[k]);
  }

  // ── Emergency contacts (rows and mobile cards) ──────
  function ecInfo(el) {
    if (el.tagName === 'TR') {
      const td = el.querySelectorAll('td');
      return { cat: td[0].textContent.trim(), name: td[1].textContent.trim(), active: /Active/.test(td[5].textContent) };
    }
    return { cat: txt(el, 'span.inline-block'), name: txt(el, 'p.font-bold'), active: /^Active/.test(txt(el, '[id^="ec-card-status-"]')) };
  }
  const ecCmp = {
    default:  (a, b) => orig(a) - orig(b),
    category: (a, b) => str(ecInfo(a).cat, ecInfo(b).cat) || str(ecInfo(a).name, ecInfo(b).name),
    name:     (a, b) => str(ecInfo(a).name, ecInfo(b).name),
    name_z:   (a, b) => str(ecInfo(b).name, ecInfo(a).name),
    active:   (a, b) => ecInfo(b).active - ecInfo(a).active || str(ecInfo(a).name, ecInfo(b).name),
  };
  function sortEc(k) {
    const tb = document.getElementById('ecTableBody'), cards = document.getElementById('ecCardList');
    if (tb)    apply(tb, Array.from(tb.children), ecCmp[k]);
    if (cards) apply(cards, Array.from(cards.children), ecCmp[k]);
  }

  // ── Locations (sorted within each branch group) ─────
  const locName = e => txt(e, '[id^="loc-name-"]');
  const locOn   = e => /Active/.test(txt(e, '[id^="loc-status-"]'));
  const locCmp = {
    default: (a, b) => orig(a) - orig(b),
    name:    (a, b) => str(locName(a), locName(b)),
    name_z:  (a, b) => str(locName(b), locName(a)),
    active:  (a, b) => locOn(b) - locOn(a) || str(locName(a), locName(b)),
  };
  function sortLocs(k) {
    document.querySelectorAll('.loc-branch-list').forEach(list => apply(list, Array.from(list.children), locCmp[k]));
  }

  // ── Shared UI helpers ───────────────────────────────
  const inputCls = 'flex-1 min-w-0 border border-gray-200 rounded-xl px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-400';
  function makeSearch(ph, onInput) {
    const i = document.createElement('input');
    i.type = 'text'; i.placeholder = ph; i.className = inputCls;
    i.addEventListener('input', onInput);
    return i;
  }
  function filterBar(controls) {
    const wrap = document.createElement('div');
    wrap.className = 'mb-4';
    const row = document.createElement('div');
    row.className = 'flex flex-col sm:flex-row gap-2 flex-wrap';
    row.append(...controls);
    const count = document.createElement('p');
    count.className = 'text-xs text-gray-400 mt-2 mono';
    wrap.append(row, count);
    return { wrap, count };
  }
  const show = (els, fn) => { let n = 0; els.forEach(e => { const ok = fn(e); e.style.display = ok ? '' : 'none'; if (ok) n++; }); return n; };

  // Wrap an existing global filter so an extra select also applies
  function extendFilter(name, extra) {
    const base = window[name];
    window[name] = function () { base(); extra(); };
  }

  document.addEventListener('DOMContentLoaded', () => {
    // ── Incidents: + Type filter + Sort ──
    const sf = document.getElementById('statusFilter');
    if (sf) {
      const typeSel = makeSelect([['', 'All Types'], ['fire','🔥 Fire'], ['medical','🏥 Medical'], ['accident','⚠️ Accident'],
        ['suspicious','👁️ Suspicious'], ['theft','🔓 Theft'], ['flooding','🌊 Flooding'], ['earthquake','🌍 Earthquake'], ['other','📋 Other']],
        () => window.filterIncidents());
      const sortSel = makeSelect([
        ['newest', '🕐 Newest first'], ['oldest', '🕐 Oldest first'],
        ['sev_desc', '🔺 Severity: High → Low'], ['sev_asc', '🔻 Severity: Low → High'],
        ['status', '📌 Status: Open first'], ['type', '🔤 Type A–Z'], ['location', '📍 Location A–Z'],
      ], sortIncidents);
      sf.after(typeSel, sortSel);
      const lf = new FormData(); lf.append('action', 'list');
      fetch('types_ajax.php', { method: 'POST', body: lf }).then(r => r.json()).then(d => {
        if (!d.success) return;
        typeSel.innerHTML = '<option value="">All Types</option>' + d.types.map(t =>
          `<option value="${t.slug}">${t.icon} ${t.label.replace(/</g, '&lt;')}</option>`).join('');
      }).catch(() => {});
      extendFilter('filterIncidents', () => {
        const t = typeSel.value;
        if (!t) return;
        const els = document.querySelectorAll('#incidentTbody tr, #incidentCards > div');
        els.forEach(e => { if (e.dataset.type !== t) e.style.display = 'none'; });
        const c = document.getElementById('incidentCount');
        const n = document.querySelectorAll('#incidentTbody tr').length
          ? Array.from(document.querySelectorAll('#incidentTbody tr')).filter(r => r.style.display !== 'none').length
          : Array.from(document.querySelectorAll('#incidentCards > div')).filter(r => r.style.display !== 'none').length;
        if (c) c.textContent = `Showing ${n} incident${n !== 1 ? 's' : ''}`;
      });
    }

    // ── Users: + Role filter + Sort ──
    const us = document.getElementById('userSearch');
    if (us) {
      us.parentElement.classList.add('flex', 'gap-2', 'flex-col', 'sm:flex-row');
      const roleSel = makeSelect([['', 'All Roles'], ['admin', '🛡 Admin'], ['user', '👤 User']], () => window.filterUsers());
      const sortSel = makeSelect([
        ['newest', '🕐 Newest first'], ['oldest', '🕐 Oldest first'], ['name', '🔤 Name A–Z'],
        ['name_z', '🔤 Name Z–A'], ['email', '✉️ Email A–Z'], ['role', '🛡 Admins first'],
      ], sortUsers);
      us.after(roleSel, sortSel);
      extendFilter('filterUsers', () => {
        if (!roleSel.value) return;
        document.querySelectorAll('#userTbody tr, #userCards > div').forEach(e => {
          if (e.dataset.role !== roleSel.value) e.style.display = 'none';
        });
      });
    }

    // ── Emergency contacts: Search + Category + Status + Sort ──
    const ec = document.getElementById('ecListWrap');
    if (ec) {
      const q = makeSearch('🔍 Search name, number, address...', runEc);
      const cat = makeSelect([['', 'All Categories'], ['Fire Department','🔥 Fire'], ['Medical','🚑 Medical'],
        ['Police','👮 Police'], ['Campus','🏫 Campus'], ['Other','📞 Other']], runEc);
      const sta = makeSelect([['', 'All Status'], ['active', '● Active'], ['hidden', '● Hidden']], runEc);
      const srt = makeSelect([['default','Default order'], ['category','Category'], ['name','Name A–Z'],
        ['name_z','Name Z–A'], ['active','Active first']], runEc);
      const { wrap, count } = filterBar([q, cat, sta, srt]);
      ec.before(wrap);
      function runEc() {
        sortEc(srt.value);
        const term = q.value.trim().toLowerCase();
        const els = document.querySelectorAll('#ecTableBody tr, #ecCardList > div');
        show(els, e => {
          const i = ecInfo(e);
          return (!term || e.textContent.toLowerCase().includes(term))
            && (!cat.value || i.cat.includes(cat.value))
            && (!sta.value || (sta.value === 'active') === i.active);
        });
        const rows = document.querySelectorAll('#ecTableBody tr');
        const base = rows.length ? Array.from(rows) : Array.from(document.querySelectorAll('#ecCardList > div'));
        const n = base.filter(e => e.style.display !== 'none').length;
        count.textContent = `Showing ${n} of ${base.length} contact${base.length !== 1 ? 's' : ''}`;
      }
      runEc();
    }

    // ── Locations: Search + Branch + Status + Sort ──
    const lc = document.getElementById('locListWrap');
    if (lc) {
      const q = makeSearch('🔍 Search location...', runLoc);
      const br = document.createElement('select'); br.className = cls;
      const buildBranches = () => {
        const prev = br.value;
        const names = Array.from(document.querySelectorAll('.loc-branch-group')).map(g => g.dataset.branch);
        br.innerHTML = '<option value="">All Branches</option>' + names.map(n =>
          `<option value="${n.replace(/"/g, '&quot;')}">${n.replace(/</g, '&lt;')}</option>`).join('');
        br.value = names.includes(prev) ? prev : '';
      };
      buildBranches();
      br.addEventListener('focus', buildBranches);
      br.addEventListener('change', runLoc);
      const sta = makeSelect([['', 'All Status'], ['active', '● Active'], ['hidden', '● Hidden']], runLoc);
      const srt = makeSelect([['default','Default order'], ['name','Name A–Z'], ['name_z','Name Z–A'], ['active','Active first']], runLoc);
      const { wrap, count } = filterBar([q, br, sta, srt]);
      lc.before(wrap);
      function runLoc() {
        sortLocs(srt.value);
        const term = q.value.trim().toLowerCase();
        let shown = 0, total = 0;
        document.querySelectorAll('.loc-branch-group').forEach(g => {
          const rows = Array.from(g.querySelectorAll('.loc-branch-list > div'));
          total += rows.length;
          const branchOk = !br.value || g.dataset.branch === br.value;
          const n = show(rows, e => branchOk
            && (!term || locName(e).toLowerCase().includes(term))
            && (!sta.value || (sta.value === 'active') === locOn(e)));
          shown += n;
          g.style.display = n ? '' : 'none';
        });
        count.textContent = `Showing ${shown} of ${total} location${total !== 1 ? 's' : ''}`;
      }
      runLoc();
    }
    // Auto-load the Incident Types tab (no admin.php edit needed)
    const sc = document.createElement('script');
    sc.src = 'js/admin_types.js';
    document.body.appendChild(sc);
  });
})();