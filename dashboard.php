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
      <span class="hidden sm:block text-red-200 text-sm">
        👤 <?= htmlspecialchars($_SESSION['user']) ?>
      </span>

      <?php if ($_SESSION['role'] === 'admin'): ?>
        <a href="admin.php"
           class="bg-white/20 hover:bg-white/30 text-white px-3 py-1.5 rounded-lg text-sm font-medium transition">
          🛡 Admin
        </a>
      <?php endif; ?>

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

    <button
      class="action-btn bg-white text-gray-700 rounded-2xl p-5 shadow-sm border border-gray-100 flex flex-col items-start gap-2">
      <span class="text-2xl">📍</span>
      <span class="font-semibold text-sm leading-tight">View Incidents</span>
    </button>

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
      <button class="text-xs font-semibold text-red-600 hover:text-red-700 transition">
        View all incidents →
      </button>
    </div>
    <?php endif; ?>

  </div>

</div>

<?php include 'report_incident_modal.php'; ?>

</body>
</html>