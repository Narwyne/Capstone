<?php
session_start();

header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

// -------------------------------------------------------
// DB CONFIG
// -------------------------------------------------------
$host   = 'localhost';
$dbname = 'campus_system';
$dbuser = 'root';
$dbpass = '';
// -------------------------------------------------------

$pdo = null;
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $dbuser, $dbpass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    // silently fail — UI will show zeroes
}

// ---- Stats ----
$total_reports   = 0;
$active_incidents = 0;
$resolved        = 0;
$high_risk       = 0;
$recent_incidents = [];

if ($pdo) {
    $total_reports    = $pdo->query("SELECT COUNT(*) FROM incidents")->fetchColumn();
    $active_incidents = $pdo->query("SELECT COUNT(*) FROM incidents WHERE status='open'")->fetchColumn();
    $resolved         = $pdo->query("SELECT COUNT(*) FROM incidents WHERE status='resolved'")->fetchColumn();
    $high_risk        = $pdo->query("SELECT COUNT(*) FROM incidents WHERE severity IN ('high','critical') AND status='open'")->fetchColumn();

    $recent_incidents = $pdo->query("
        SELECT incident_type, severity, location, description, reported_at, status
        FROM incidents
        ORDER BY reported_at DESC
        LIMIT 5
    ")->fetchAll();
}

// ---- Helpers ----
function typeIcon($t) {
    return ['fire'=>'🔥','medical'=>'🏥','accident'=>'⚠️','suspicious'=>'👁️',
            'theft'=>'🔓','flooding'=>'🌊','earthquake'=>'🌍','other'=>'📋'][$t] ?? '📋';
}
function severityBorderColor($s) {
    return ['low'=>'border-emerald-400','medium'=>'border-amber-400',
            'high'=>'border-orange-500','critical'=>'border-red-600'][$s] ?? 'border-gray-300';
}
function timeAgo($datetime) {
    $diff = time() - strtotime($datetime);
    if ($diff < 60)     return 'Just now';
    if ($diff < 3600)   return floor($diff/60) . ' min ago';
    if ($diff < 86400)  return floor($diff/3600) . ' hr ago';
    return floor($diff/86400) . ' days ago';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard — ACLC Smart Campus</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="backpageScript.js"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">

  <style>
    body { font-family: 'DM Sans', sans-serif; }
    .mono { font-family: 'DM Mono', monospace; }

    /* Page load animations */
    @keyframes fadeUp {
      from { opacity: 0; transform: translateY(18px); }
      to   { opacity: 1; transform: translateY(0); }
    }
    @keyframes fadeIn {
      from { opacity: 0; }
      to   { opacity: 1; }
    }
    .anim { animation: fadeUp 0.45s ease both; }
    .nav-anim { animation: fadeIn 0.3s ease both; }

    /* Stat card accent line */
    .stat-card { position: relative; overflow: hidden; }
    .stat-card::before {
      content: '';
      position: absolute;
      top: 0; left: 0;
      width: 4px; height: 100%;
      border-radius: 4px 0 0 4px;
    }
    .stat-card.red::before    { background: #dc2626; }
    .stat-card.amber::before  { background: #f59e0b; }
    .stat-card.green::before  { background: #10b981; }
    .stat-card.orange::before { background: #f97316; }

    /* Action button hover lift */
    .action-btn {
      transition: transform 0.15s ease, box-shadow 0.15s ease;
    }
    .action-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 24px rgba(0,0,0,0.12);
    }
    .action-btn:active {
      transform: translateY(0);
    }

    /* Incident row hover */
    .incident-item:hover {
      background: #fafafa;
    }

    /* Subtle background texture */
    body {
      background-color: #f3f4f6;
      background-image: radial-gradient(circle at 1px 1px, rgba(0,0,0,0.04) 1px, transparent 0);
      background-size: 24px 24px;
    }
  </style>
</head>

<body class="min-h-screen">

<!-- ============================================================
     NAVBAR
     ============================================================ -->
<nav class="bg-red-700 text-white sticky top-0 z-40 shadow-lg nav-anim">
  <div class="max-w-6xl mx-auto px-4 py-3 flex items-center justify-between">

    <div>
      <span class="text-xs text-red-300 uppercase tracking-widest block leading-none">ACLC Smart Campus</span>
      <span class="font-bold text-lg leading-tight">Dashboard</span>
    </div>

    <div class="flex items-center gap-2">
      <a href="profile.php" class="hidden sm:flex items-center gap-1.5 text-red-200 hover:text-white text-sm transition">
        👤 <?= htmlspecialchars($_SESSION['user']) ?>
      </a>

      <?php if ($_SESSION['role'] === 'admin'): ?>
        <a href="admin.php"
           class="bg-white/20 hover:bg-white/30 text-white px-3 py-1.5 rounded-lg text-sm font-medium transition">
          🛡 Admin
        </a>
      <?php endif; ?>

      <a href="profile.php"
         class="sm:hidden bg-white/20 hover:bg-white/30 text-white px-3 py-1.5 rounded-lg text-sm font-medium transition">
        👤
      </a>

      <a href="logout.php"
         class="bg-white text-red-700 hover:bg-red-50 px-3 py-1.5 rounded-lg text-sm font-semibold transition">
        Logout
      </a>
    </div>

  </div>
</nav>

<!-- ============================================================
     MAIN
     ============================================================ -->
<div class="max-w-6xl mx-auto px-4 py-6 space-y-6">

  <!-- WELCOME BANNER -->
  <div class="anim" style="animation-delay:0.05s">
    <h2 class="text-xl font-bold text-gray-800">
      Good <?= (date('H') < 12) ? 'morning' : ((date('H') < 18) ? 'afternoon' : 'evening') ?>,
      <span class="text-red-600"><?= htmlspecialchars(explode(' ', $_SESSION['user'])[0]) ?></span> 👋
    </h2>
    <p class="text-sm text-gray-400 mt-0.5">Here's your campus safety overview for today.</p>
  </div>

  <!-- QUICK ACTIONS -->
  <div class="grid grid-cols-2 md:grid-cols-4 gap-3 anim" style="animation-delay:0.1s">

    <button onclick="openReportModal()"
      class="action-btn bg-red-600 text-white rounded-2xl p-5 shadow-md flex flex-col items-start gap-2 col-span-2 md:col-span-1">
      <span class="text-2xl">🚨</span>
      <span class="font-bold text-sm leading-tight">Report Incident</span>
    </button>

    <!-- ✅ FUNCTIONAL -->
    <a href="incidents.php"
      class="action-btn bg-white text-gray-700 rounded-2xl p-5 shadow-sm border border-gray-100 flex flex-col items-start gap-2">
      <span class="text-2xl">📍</span>
      <span class="font-semibold text-sm leading-tight">View Incidents</span>
    </a>

    <a href="emergency.php"
      class="action-btn bg-red-800 text-white rounded-2xl p-5 shadow-md flex flex-col items-start gap-2 no-underline">
      <span class="text-2xl">📞</span>
      <span class="font-bold text-sm leading-tight">Emergency Service</span>
    </a>

    <button
      class="action-btn bg-white text-gray-700 rounded-2xl p-5 shadow-sm border border-gray-100 flex flex-col items-start gap-2">
      <span class="text-2xl">📊</span>
      <span class="font-semibold text-sm leading-tight">Risk Prediction</span>
    </button>

  </div>

  <!-- STAT CARDS -->
  <div class="grid grid-cols-2 md:grid-cols-4 gap-3 anim" style="animation-delay:0.16s">

    <div class="stat-card red bg-white rounded-2xl p-4 shadow-sm border border-gray-100">
      <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Total Reports</p>
      <p class="text-3xl font-bold text-red-600"><?= $total_reports ?></p>
    </div>

    <div class="stat-card amber bg-white rounded-2xl p-4 shadow-sm border border-gray-100">
      <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Active</p>
      <p class="text-3xl font-bold text-amber-500"><?= $active_incidents ?></p>
    </div>

    <div class="stat-card green bg-white rounded-2xl p-4 shadow-sm border border-gray-100">
      <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Resolved</p>
      <p class="text-3xl font-bold text-emerald-500"><?= $resolved ?></p>
    </div>

    <div class="stat-card orange bg-white rounded-2xl p-4 shadow-sm border border-gray-100">
      <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">High Risk</p>
      <p class="text-3xl font-bold text-orange-500"><?= $high_risk ?></p>
    </div>

  </div>

  <!-- RECENT INCIDENTS -->
  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden anim" style="animation-delay:0.22s">

    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
      <h2 class="font-bold text-gray-800">Recent Incidents</h2>
      <span class="text-xs text-gray-400 mono"><?= date('M d, Y') ?></span>
    </div>

    <div class="divide-y divide-gray-50">

      <?php if (empty($recent_incidents)): ?>
      <div class="text-center py-14 text-gray-400">
        <div class="text-4xl mb-2">📭</div>
        <p class="text-sm font-medium">No incidents reported yet.</p>
        <p class="text-xs text-gray-300 mt-1">The campus is all clear!</p>
      </div>

      <?php else: ?>
      <?php foreach ($recent_incidents as $inc): ?>

      <?php
        $borderColor = severityBorderColor($inc['severity']);
        $icon = typeIcon($inc['incident_type']);
        $label = ucfirst(str_replace('_', ' ', $inc['incident_type']));
        $location = ucwords(str_replace('_', ' ', $inc['location']));
        $ago = timeAgo($inc['reported_at']);

        $severityColor = [
          'low'      => 'bg-emerald-100 text-emerald-700',
          'medium'   => 'bg-amber-100 text-amber-700',
          'high'     => 'bg-orange-100 text-orange-700',
          'critical' => 'bg-red-100 text-red-700',
        ][$inc['severity']] ?? 'bg-gray-100 text-gray-600';
      ?>

      <div class="incident-item flex items-start gap-4 px-5 py-4 border-l-4 <?= $borderColor ?> transition">
        <div class="text-2xl mt-0.5 shrink-0"><?= $icon ?></div>
        <div class="flex-1 min-w-0">
          <div class="flex items-center gap-2 flex-wrap">
            <span class="font-semibold text-gray-800 text-sm"><?= $label ?></span>
            <span class="text-xs font-semibold px-2 py-0.5 rounded-full <?= $severityColor ?>">
              <?= ucfirst($inc['severity']) ?>
            </span>
          </div>
          <p class="text-xs text-gray-500 mt-0.5">📍 <?= htmlspecialchars($location) ?></p>
          <?php if (!empty($inc['description'])): ?>
          <p class="text-xs text-gray-400 mt-1 line-clamp-1"><?= htmlspecialchars($inc['description']) ?></p>
          <?php endif; ?>
        </div>
        <span class="text-xs text-gray-400 mono shrink-0 mt-0.5"><?= $ago ?></span>
      </div>

      <?php endforeach; ?>
      <?php endif; ?>

    </div>

    <?php if (!empty($recent_incidents)): ?>
    <div class="px-5 py-3 bg-gray-50 text-center">
      <a href="incidents.php" class="text-xs font-semibold text-red-600 hover:text-red-700 transition">
        View all incidents →
      </a>
    </div>
    <?php endif; ?>

  </div>

</div>

<?php include 'modals/report_incident_modal.php'; ?>

<!-- ============================================================
     MEDAI CHATBOT FLOATING BUTTON
     ============================================================ -->
<button id="medai-toggle"
  onclick="toggleMedai()"
  class="fixed bottom-6 right-6 z-50 bg-red-700 hover:bg-red-800 text-white rounded-full w-14 h-14 flex items-center justify-center shadow-xl transition-all duration-200 hover:scale-105 active:scale-95"
  title="Ask Medai">
  <span id="medai-icon" class="text-2xl">🤖</span>
</button>

<!-- Unread dot -->
<span id="medai-dot" class="fixed bottom-[68px] right-[18px] z-50 w-3 h-3 bg-amber-400 rounded-full border-2 border-white hidden"></span>

<!-- ============================================================
     MEDAI CHAT PANEL
     ============================================================ -->
<div id="medai-panel"
  class="fixed bottom-24 right-4 z-50 w-[340px] max-w-[calc(100vw-2rem)] bg-white rounded-2xl shadow-2xl border border-gray-100 flex flex-col overflow-hidden transition-all duration-300 origin-bottom-right"
  style="display:none; max-height: 520px;">

  <!-- Header -->
  <div class="bg-red-700 text-white px-4 py-3 flex items-center gap-3">
    <div class="w-9 h-9 rounded-full bg-white/20 flex items-center justify-center text-xl shrink-0">🤖</div>
    <div class="flex-1 min-w-0">
      <p class="font-bold text-sm leading-tight">Medai</p>
      <p class="text-xs text-red-200">Campus Safety Assistant</p>
    </div>
    <button onclick="toggleMedai()" class="text-red-200 hover:text-white text-lg leading-none transition">✕</button>
  </div>

  <!-- Messages -->
  <div id="medai-messages" class="flex-1 overflow-y-auto px-4 py-3 space-y-3" style="max-height: 340px;">

    <!-- Welcome message -->
    <div class="flex gap-2 items-start">
      <div class="w-7 h-7 rounded-full bg-red-100 flex items-center justify-center text-sm shrink-0 mt-0.5">🤖</div>
      <div class="bg-gray-100 rounded-2xl rounded-tl-sm px-3 py-2 text-sm text-gray-700 max-w-[85%]">
        Hi! I'm <strong>Medai</strong>, your campus safety assistant. Ask me anything about incidents, safety tips, or emergency procedures! 🏫
      </div>
    </div>

  </div>

  <!-- Typing indicator (hidden by default) -->
  <div id="medai-typing" class="px-4 pb-1 hidden">
    <div class="flex gap-2 items-start">
      <div class="w-7 h-7 rounded-full bg-red-100 flex items-center justify-center text-sm shrink-0">🤖</div>
      <div class="bg-gray-100 rounded-2xl rounded-tl-sm px-3 py-2">
        <span class="flex gap-1">
          <span class="w-1.5 h-1.5 bg-gray-400 rounded-full animate-bounce" style="animation-delay:0s"></span>
          <span class="w-1.5 h-1.5 bg-gray-400 rounded-full animate-bounce" style="animation-delay:0.15s"></span>
          <span class="w-1.5 h-1.5 bg-gray-400 rounded-full animate-bounce" style="animation-delay:0.3s"></span>
        </span>
      </div>
    </div>
  </div>

  <!-- Suggested questions (shown initially) -->
  <div id="medai-suggestions" class="px-4 pb-2 flex flex-wrap gap-1.5">
    <button onclick="sendSuggestion('What incidents are active right now?')"
      class="text-xs bg-red-50 text-red-700 border border-red-200 rounded-full px-2.5 py-1 hover:bg-red-100 transition">
      Active incidents?
    </button>
    <button onclick="sendSuggestion('What should I do in a fire emergency?')"
      class="text-xs bg-red-50 text-red-700 border border-red-200 rounded-full px-2.5 py-1 hover:bg-red-100 transition">
      Fire emergency steps
    </button>
    <button onclick="sendSuggestion('How do I report an incident?')"
      class="text-xs bg-red-50 text-red-700 border border-red-200 rounded-full px-2.5 py-1 hover:bg-red-100 transition">
      How to report
    </button>
  </div>

  <!-- Input -->
  <div class="border-t border-gray-100 px-3 py-2 flex gap-2 items-end">
    <textarea id="medai-input"
      rows="1"
      placeholder="Ask about incidents or safety…"
      class="flex-1 resize-none text-sm border border-gray-200 rounded-xl px-3 py-2 focus:outline-none focus:border-red-400 transition"
      style="max-height:80px;"
      onkeydown="medaiKeydown(event)"
      oninput="this.style.height='auto';this.style.height=this.scrollHeight+'px'"
    ></textarea>
    <button id="medai-send-btn"
      onclick="sendMedai()"
      class="bg-red-600 hover:bg-red-700 text-white rounded-xl w-9 h-9 flex items-center justify-center shrink-0 transition disabled:opacity-50">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4">
        <path d="M3.105 2.288a.75.75 0 0 0-.826.95l1.414 4.926A1.5 1.5 0 0 0 5.135 9.25h6.115a.75.75 0 0 1 0 1.5H5.135a1.5 1.5 0 0 0-1.442 1.086l-1.414 4.926a.75.75 0 0 0 .826.95 28.896 28.896 0 0 0 15.293-7.154.75.75 0 0 0 0-1.115A28.897 28.897 0 0 0 3.105 2.288Z"/>
      </svg>
    </button>
  </div>

</div>

<!-- ============================================================
     MEDAI SCRIPT
     ============================================================ -->
<script>
  let medaiHistory = [];
  let medaiOpen    = false;
  let medaiData    = null;

  function buildSystemPrompt(d) {
    const s = d.stats;
    const breakdown = d.byType.length
      ? d.byType.reduce((acc, r) => {
          acc[r.incident_type] = acc[r.incident_type] || [];
          acc[r.incident_type].push(`${r.severity}:${r.cnt}`);
          return acc;
        }, {})
      : null;
    const breakdownStr = breakdown
      ? Object.entries(breakdown).map(([t,v]) => `${t}(${v.join(',')})`).join('; ')
      : 'none';
    const recentStr = d.recent.length
      ? d.recent.map(r => `${r.incident_type}|${r.severity}|${r.location}|${r.reported_at.slice(0,16)}`).join('\n')
      : 'none';

    return `You are Medai, ACLC Smart Campus safety assistant.
STATS: total=${s.total} active=${s.active} resolved=${s.resolved} high_risk=${s.high_risk}
OPEN BY TYPE: ${breakdownStr}
RECENT OPEN (type|sev|loc|time):\n${recentStr}
Rules: Answer campus safety/incident questions using above data. To file a report collect: incident_type(fire/medical/accident/suspicious/theft/flooding/earthquake/other), severity(low/medium/high/critical), location, description(optional). When ready output exactly one line: SUBMIT_REPORT:{"incident_type":"...","severity":"...","location":"...","description":"..."}
Be concise. Bullets for steps. Never invent data.`;
  }

  async function loadMedaiData() {
    try {
      const res = await fetch('medai_incidents.php');
      medaiData = await res.json();
    } catch {
      medaiData = { stats:{total:0,active:0,resolved:0,high_risk:0}, byType:[], recent:[] };
    }
  }

  function toggleMedai() {
    medaiOpen = !medaiOpen;
    const panel = document.getElementById('medai-panel');
    if (medaiOpen) {
      panel.style.display = 'flex';
      panel.style.flexDirection = 'column';
      document.getElementById('medai-dot').classList.add('hidden');
      if (!medaiData) loadMedaiData();
      setTimeout(() => document.getElementById('medai-input').focus(), 100);
    } else {
      panel.style.display = 'none';
    }
  }

  function medaiKeydown(e) {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMedai(); }
  }

  function sendSuggestion(text) {
    document.getElementById('medai-input').value = text;
    document.getElementById('medai-suggestions').classList.add('hidden');
    sendMedai();
  }

  function appendMessage(role, html, isHtml = false) {
    const container = document.getElementById('medai-messages');
    const isUser    = role === 'user';
    const wrapper   = document.createElement('div');
    wrapper.className = isUser ? 'flex gap-2 items-start justify-end' : 'flex gap-2 items-start';
    const bubble = document.createElement('div');
    bubble.className = isUser
      ? 'bg-red-600 text-white rounded-2xl rounded-tr-sm px-3 py-2 text-sm max-w-[85%] whitespace-pre-wrap'
      : 'bg-gray-100 rounded-2xl rounded-tl-sm px-3 py-2 text-sm text-gray-700 max-w-[85%]';
    bubble.innerHTML = isHtml ? html : html
      .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
      .replace(/\*\*(.*?)\*\*/g,'<strong>$1</strong>')
      .replace(/\n/g,'<br>');
    if (!isUser) {
      const av = document.createElement('div');
      av.className = 'w-7 h-7 rounded-full bg-red-100 flex items-center justify-center text-sm shrink-0 mt-0.5';
      av.textContent = '🤖';
      wrapper.appendChild(av);
    }
    wrapper.appendChild(bubble);
    container.appendChild(wrapper);
    container.scrollTop = container.scrollHeight;
  }

  async function submitMedaiReport(payload) {
    appendMessage('assistant',
      `<p class="font-semibold">📋 Submitting report…</p>
       <p class="text-xs text-gray-500 mt-0.5">${payload.incident_type} · ${payload.severity} · ${payload.location}</p>`, true);
    try {
      const res  = await fetch('medai_report.php', {
        method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(payload)
      });
      const data = await res.json();
      if (data.success) {
        appendMessage('assistant',
          `<p class="font-semibold text-emerald-700">✅ Report submitted!</p>
           <p class="text-xs text-gray-500 mt-0.5">Logged and marked active. Stay safe!</p>`, true);
        await loadMedaiData();
      } else {
        appendMessage('assistant', `⚠️ Error: ${data.error || 'Could not submit.'}`);
      }
    } catch {
      appendMessage('assistant', '⚠️ Network error submitting report. Try again.');
    }
  }

  async function sendMedai() {
    const input = document.getElementById('medai-input');
    const text  = input.value.trim();
    if (!text) return;
    document.getElementById('medai-suggestions').classList.add('hidden');
    input.value = '';
    input.style.height = 'auto';
    appendMessage('user', text);
    medaiHistory.push({ role:'user', content:text });

    // Sliding window — keep last 6 messages only
    if (medaiHistory.length > 6) medaiHistory = medaiHistory.slice(-6);

    const typing = document.getElementById('medai-typing');
    const btn    = document.getElementById('medai-send-btn');
    typing.classList.remove('hidden');
    document.getElementById('medai-messages').scrollTop = 9999;
    btn.disabled = true;

    if (!medaiData) await loadMedaiData();

    try {
      const res = await fetch('medai_proxy.php', {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ system: buildSystemPrompt(medaiData), messages: medaiHistory })
      });
      const data  = await res.json();
      let   reply = data.content?.map(b => b.text||'').join('') || 'No response. Please try again.';
      typing.classList.add('hidden');

      const match = reply.match(/SUBMIT_REPORT:(\{.*?\})/s);
      if (match) {
        try {
          const payload    = JSON.parse(match[1]);
          const cleanReply = reply.replace(/SUBMIT_REPORT:\{.*?\}/s,'').trim();
          if (cleanReply) appendMessage('assistant', cleanReply);
          medaiHistory.push({ role:'assistant', content:reply });
          await submitMedaiReport(payload);
        } catch {
          appendMessage('assistant', reply);
          medaiHistory.push({ role:'assistant', content:reply });
        }
      } else {
        appendMessage('assistant', reply);
        medaiHistory.push({ role:'assistant', content:reply });
      }
    } catch {
      typing.classList.add('hidden');
      appendMessage('assistant', "Sorry, I'm having trouble connecting. Please try again.");
    }
    btn.disabled = false;
    document.getElementById('medai-messages').scrollTop = 9999;
  }

  setTimeout(() => {
    if (!medaiOpen) document.getElementById('medai-dot').classList.remove('hidden');
  }, 2000);
</script>

</body>
</html>