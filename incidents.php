<?php
session_start();

header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

require_once 'includes/db.php';

// ---- Fetch incidents ----
$incidents = [];
if ($pdo) {
    $incidents = $pdo->query("
        SELECT id, incident_type, severity, location, description,
               reported_by, status, photo_path, reported_at
        FROM incidents
        ORDER BY reported_at DESC
    ")->fetchAll();
}

// ---- Helpers ----
function typeIcon($t) {
    return ['fire'=>'🔥','medical'=>'🏥','accident'=>'⚠️','suspicious'=>'👁️',
            'theft'=>'🔓','flooding'=>'🌊','earthquake'=>'🌍','other'=>'📋'][$t] ?? '📋';
}
function timeAgo($datetime) {
    $diff = time() - strtotime($datetime);
    if ($diff < 60)    return 'Just now';
    if ($diff < 3600)  return floor($diff/60)  . ' min ago';
    if ($diff < 86400) return floor($diff/3600) . ' hr ago';
    return floor($diff/86400) . ' days ago';
}

$total    = count($incidents);
$open     = count(array_filter($incidents, fn($i) => $i['status'] === 'open'));
$resolved = count(array_filter($incidents, fn($i) => $i['status'] === 'resolved'));
$critical = count(array_filter($incidents, fn($i) => in_array($i['severity'], ['high','critical']) && $i['status'] === 'open'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Incidents — ACLC Smart Campus</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=DM+Mono:wght@500&display=swap" rel="stylesheet">
  <style>
    body { font-family:'DM Sans',sans-serif; }
    .mono { font-family:'DM Mono',monospace; }
    @keyframes fadeUp { from{opacity:0;transform:translateY(14px);}to{opacity:1;transform:translateY(0);} }
    .anim { animation:fadeUp 0.4s ease both; }
    body { background-color:#f3f4f6; background-image:radial-gradient(circle at 1px 1px,rgba(0,0,0,0.04) 1px,transparent 0); background-size:24px 24px; }
    .inc-card { border-left:4px solid; transition:box-shadow 0.15s ease; }
    .inc-card:hover { box-shadow:0 4px 16px rgba(0,0,0,0.08); }
    .inc-card.low      { border-color:#10b981; }
    .inc-card.medium   { border-color:#f59e0b; }
    .inc-card.high     { border-color:#f97316; }
    .inc-card.critical { border-color:#dc2626; }
    .stat-card { position:relative; overflow:hidden; }
    .stat-card::before { content:''; position:absolute; top:0; left:0; width:4px; height:100%; border-radius:4px 0 0 4px; }
    .stat-card.red::before    { background:#dc2626; }
    .stat-card.amber::before  { background:#f59e0b; }
    .stat-card.green::before  { background:#10b981; }
    .stat-card.orange::before { background:#f97316; }
  </style>
</head>
<body class="min-h-screen">

<!-- NAVBAR -->
<nav class="bg-red-700 text-white sticky top-0 z-40 shadow-lg">
  <div class="max-w-5xl mx-auto px-4 py-3 flex items-center justify-between">
    <div>
      <span class="text-xs text-red-300 uppercase tracking-widest block leading-none">ACLC Smart Campus</span>
      <span class="font-bold text-lg leading-tight">Incident Reports</span>
    </div>
    <a href="dashboard.php" class="bg-white text-red-700 hover:bg-red-50 px-3 py-1.5 rounded-lg text-sm font-semibold transition">
      ← Dashboard
    </a>
  </div>
</nav>

<div class="max-w-5xl mx-auto px-4 py-6 space-y-5">

  <!-- STAT STRIP -->
  <div class="grid grid-cols-2 md:grid-cols-4 gap-3 anim" style="animation-delay:0.05s">
    <div class="stat-card red bg-white rounded-2xl p-4 shadow-sm border border-gray-100">
      <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Total</p>
      <p class="text-3xl font-bold text-red-600"><?= $total ?></p>
    </div>
    <div class="stat-card amber bg-white rounded-2xl p-4 shadow-sm border border-gray-100">
      <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Open</p>
      <p class="text-3xl font-bold text-amber-500"><?= $open ?></p>
    </div>
    <div class="stat-card green bg-white rounded-2xl p-4 shadow-sm border border-gray-100">
      <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Resolved</p>
      <p class="text-3xl font-bold text-emerald-500"><?= $resolved ?></p>
    </div>
    <div class="stat-card orange bg-white rounded-2xl p-4 shadow-sm border border-gray-100">
      <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">High Risk</p>
      <p class="text-3xl font-bold text-orange-500"><?= $critical ?></p>
    </div>
  </div>

  <!-- FILTERS -->
  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 anim" style="animation-delay:0.1s">
    <div class="flex flex-col sm:flex-row gap-2 flex-wrap">

      <!-- Search with inline clear button -->
      <div class="relative flex-1 min-w-0">
        <input type="text" id="searchInput" oninput="onSearchInput()"
          placeholder="🔍 Search type, location, reporter..."
          class="w-full border border-gray-200 rounded-xl px-4 py-2.5 pr-9 text-sm focus:outline-none focus:ring-2 focus:ring-red-400">
        <button id="clearSearchBtn" onclick="clearSearch()"
          class="hidden absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 text-lg leading-none">×</button>
      </div>

      <select id="severityFilter" onchange="filterCards()"
        class="border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-400 bg-white">
        <option value="">All Severities</option>
        <option value="critical">🚨 Critical</option>
        <option value="high">🔴 High</option>
        <option value="medium">🟡 Medium</option>
        <option value="low">🟢 Low</option>
      </select>

      <select id="statusFilter" onchange="filterCards()"
        class="border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-400 bg-white">
        <option value="">All Status</option>
        <option value="open">🔴 Open</option>
        <option value="in_progress">🔵 In Progress</option>
        <option value="resolved">✅ Resolved</option>
      </select>

      <select id="typeFilter" onchange="filterCards()"
        class="border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-400 bg-white">
        <option value="">All Types</option>
        <option value="fire">🔥 Fire</option>
        <option value="medical">🏥 Medical</option>
        <option value="accident">⚠️ Accident</option>
        <option value="suspicious">👁️ Suspicious</option>
        <option value="theft">🔓 Theft</option>
        <option value="flooding">🌊 Flooding</option>
        <option value="earthquake">🌍 Earthquake</option>
        <option value="other">📋 Other</option>
      </select>

      <select id="sortOrder" onchange="filterCards()"
        class="border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-400 bg-white">
        <option value="newest">🕐 Newest</option>
        <option value="oldest">🕐 Oldest</option>
        <option value="severity">🔺 Severity</option>
      </select>

      <button id="clearAllBtn" onclick="clearFilters()"
        class="hidden border border-gray-200 rounded-xl px-4 py-2.5 text-sm text-gray-500 hover:bg-gray-50 transition whitespace-nowrap">
        ✕ Clear All
      </button>
    </div>

    <!-- Active filter chips -->
    <div id="activeChips" class="hidden gap-2 flex-wrap mt-3"></div>

    <p class="text-xs text-gray-400 mt-2 mono" id="resultCount">Showing all <?= $total ?> incidents</p>
  </div>

  <!-- INCIDENT LIST -->
  <?php if (empty($incidents)): ?>
  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 text-center py-20 text-gray-400 anim">
    <div class="text-5xl mb-3">📭</div>
    <p class="font-semibold">No incidents reported yet.</p>
    <p class="text-sm mt-1 text-gray-300">The campus is all clear!</p>
  </div>
  <?php else: ?>

  <div class="space-y-3 anim" style="animation-delay:0.15s" id="incidentList">
    <?php foreach ($incidents as $inc):
      $icon  = typeIcon($inc['incident_type']);
      $label = ucfirst(str_replace('_', ' ', $inc['incident_type']));
      $loc   = ucwords(str_replace('_', ' ', $inc['location']));
      $ago   = timeAgo($inc['reported_at']);
      $date  = date('M d, Y · H:i', strtotime($inc['reported_at']));

      $sevBadge = ['low'=>'bg-emerald-100 text-emerald-700','medium'=>'bg-amber-100 text-amber-700',
                   'high'=>'bg-orange-100 text-orange-700','critical'=>'bg-red-100 text-red-700'][$inc['severity']] ?? 'bg-gray-100 text-gray-600';
      $staBadge = ['open'=>'bg-red-100 text-red-600','in_progress'=>'bg-blue-100 text-blue-600',
                   'resolved'=>'bg-green-100 text-green-600'][$inc['status']] ?? 'bg-gray-100 text-gray-600';
      $staLabel = ['open'=>'Open','in_progress'=>'In Progress','resolved'=>'Resolved'][$inc['status']] ?? $inc['status'];
    ?>
    <div class="inc-card <?= $inc['severity'] ?> bg-white rounded-2xl shadow-sm overflow-hidden"
      data-type="<?= htmlspecialchars($inc['incident_type']) ?>"
      data-location="<?= htmlspecialchars($inc['location']) ?>"
      data-reporter="<?= htmlspecialchars($inc['reported_by'] ?? '') ?>"
      data-description="<?= htmlspecialchars($inc['description'] ?? '') ?>"
      data-date="<?= strtotime($inc['reported_at']) ?>"
      data-severity="<?= htmlspecialchars($inc['severity']) ?>"
      data-status="<?= htmlspecialchars($inc['status']) ?>">

      <div class="p-4">
        <!-- Top row -->
        <div class="flex items-start justify-between gap-3">
          <div class="flex items-start gap-3 flex-1 min-w-0">
            <span class="text-2xl mt-0.5 shrink-0"><?= $icon ?></span>
            <div class="min-w-0">
              <div class="flex items-center gap-2 flex-wrap mb-1">
                <span class="font-bold text-gray-800" data-hl><?= htmlspecialchars($label) ?></span>
                <span class="text-xs font-semibold px-2 py-0.5 rounded-full <?= $sevBadge ?>">
                  <?= ucfirst($inc['severity']) ?>
                </span>
                <span class="text-xs font-semibold px-2 py-0.5 rounded-full <?= $staBadge ?>">
                  <?= $staLabel ?>
                </span>
              </div>
              <p class="text-sm text-gray-500" data-hl>📍 <?= htmlspecialchars($loc) ?></p>
            </div>
          </div>
          <!-- ID + Time -->
          <div class="text-right shrink-0">
            <p class="mono text-xs text-gray-400">#<?= $inc['id'] ?></p>
            <p class="text-xs text-gray-400 mt-0.5"><?= $ago ?></p>
          </div>
        </div>

        <!-- Description -->
        <?php if (!empty($inc['description'])): ?>
        <p class="text-sm text-gray-600 mt-3 leading-relaxed line-clamp-2 pl-9" data-hl>
          <?= htmlspecialchars($inc['description']) ?>
        </p>
        <?php endif; ?>

        <!-- Footer row -->
        <div class="flex items-center justify-between mt-3 pl-9">
          <div class="flex items-center gap-3 text-xs text-gray-400">
            <span data-hl>👤 <?= htmlspecialchars($inc['reported_by'] ?? 'Anonymous') ?></span>
            <span class="hidden sm:inline mono"><?= $date ?></span>
          </div>

          <?php if (!empty($inc['photo_path'])): ?>
          <button onclick="showPhoto('<?= htmlspecialchars($inc['photo_path']) ?>', '<?= htmlspecialchars($label) ?>')"
            class="text-xs bg-gray-100 hover:bg-gray-200 text-gray-500 px-3 py-1 rounded-lg transition flex items-center gap-1">
            📷 <span>Photo</span>
          </button>
          <?php endif; ?>
        </div>
      </div>

    </div>
    <?php endforeach; ?>
  </div>

  <?php endif; ?>

</div>

<!-- PHOTO LIGHTBOX -->
<div id="photoModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/80 p-4"
  onclick="closePhoto()">
  <div class="relative max-w-lg w-full" onclick="event.stopPropagation()">
    <button onclick="closePhoto()" class="absolute -top-10 right-0 text-white hover:text-gray-300 text-3xl leading-none">&times;</button>
    <img id="photoImg" src="" alt="" class="w-full rounded-2xl shadow-2xl">
    <p id="photoCaption" class="text-white text-sm text-center mt-3 opacity-70"></p>
  </div>
</div>

<script src="js/incidents.js"></script>
</body>
</html>