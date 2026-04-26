<?php
session_start();

header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

if (!isset($_SESSION['user']) || $_SESSION['role'] != 'admin') {
    header("Location: dashboard.php");
    exit();
}

// -------------------------------------------------------
// DB CONFIG — update to match yours
// -------------------------------------------------------
$host   = 'localhost';
$dbname = 'campus_system';
$dbuser = 'root';
$dbpass = '';
// -------------------------------------------------------

$pdo = null;
$db_error = null;

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $dbuser, $dbpass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    $db_error = $e->getMessage();
}

// ---- Handle quick actions (resolve/delete) via POST ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo) {

    $action = $_POST['action'] ?? '';

    if ($action === 'resolve_incident' && isset($_POST['id'])) {
        $stmt = $pdo->prepare("UPDATE incidents SET status='resolved' WHERE id=?");
        $stmt->execute([(int)$_POST['id']]);
        header("Location: admin.php?toast=resolved");
        exit();
    }

    if ($action === 'delete_incident' && isset($_POST['id'])) {
        $stmt = $pdo->prepare("DELETE FROM incidents WHERE id=?");
        $stmt->execute([(int)$_POST['id']]);
        header("Location: admin.php?toast=deleted");
        exit();
    }

    if ($action === 'delete_user' && isset($_POST['id'])) {
        // Prevent deleting yourself
        if ($_POST['id'] != $_SESSION['user_id']) {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id=?");
            $stmt->execute([(int)$_POST['id']]);
        }
        header("Location: admin.php?toast=user_deleted");
        exit();
    }
}

// ---- Fetch stats ----
$stats = ['users' => 0, 'total_reports' => 0, 'resolved' => 0, 'high_risk' => 0];
if ($pdo) {
    $stats['users']        = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $stats['total_reports']= $pdo->query("SELECT COUNT(*) FROM incidents")->fetchColumn();
    $stats['resolved']     = $pdo->query("SELECT COUNT(*) FROM incidents WHERE status='resolved'")->fetchColumn();
    $stats['high_risk']    = $pdo->query("SELECT COUNT(*) FROM incidents WHERE severity IN ('high','critical') AND status='open'")->fetchColumn();
}

// ---- Fetch users ----
$users = [];
if ($pdo) {
    $users = $pdo->query("SELECT id, name, email, role, created_at FROM users ORDER BY id DESC")->fetchAll();
}

// ---- Fetch incidents ----
$incidents = [];
if ($pdo) {
    $incidents = $pdo->query("
        SELECT id, incident_type, severity, location, description, reported_by, status, photo_path, reported_at
        FROM incidents
        ORDER BY reported_at DESC
    ")->fetchAll();
}

// ---- Helper: severity badge ----
function severityBadge($s) {
    $map = [
        'low'      => ['bg-emerald-100 text-emerald-700', '🟢 Low'],
        'medium'   => ['bg-amber-100 text-amber-700',    '🟡 Medium'],
        'high'     => ['bg-orange-100 text-orange-700',  '🔴 High'],
        'critical' => ['bg-red-100 text-red-700',        '🚨 Critical'],
    ];
    [$cls, $label] = $map[$s] ?? ['bg-gray-100 text-gray-600', $s];
    return "<span class='inline-block text-xs font-semibold px-2 py-0.5 rounded-full $cls'>$label</span>";
}

function statusBadge($s) {
    $map = [
        'open'        => ['bg-red-100 text-red-600',    'Open'],
        'in_progress' => ['bg-blue-100 text-blue-600',  'In Progress'],
        'resolved'    => ['bg-green-100 text-green-600','Resolved'],
    ];
    [$cls, $label] = $map[$s] ?? ['bg-gray-100 text-gray-600', $s];
    return "<span class='inline-block text-xs font-semibold px-2 py-0.5 rounded-full $cls'>$label</span>";
}

function typeIcon($t) {
    $map = [
        'fire'       => '🔥',
        'medical'    => '🏥',
        'accident'   => '⚠️',
        'suspicious' => '👁️',
        'theft'      => '🔓',
        'flooding'   => '🌊',
        'earthquake' => '🌍',
        'other'      => '📋',
    ];
    return $map[$t] ?? '📋';
}

$toast = $_GET['toast'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Panel — ACLC Smart Campus</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
  <style>
    body { font-family: 'DM Sans', sans-serif; }
    .mono { font-family: 'DM Mono', monospace; }

    @keyframes slideIn {
      from { opacity: 0; transform: translateY(-12px); }
      to   { opacity: 1; transform: translateY(0); }
    }
    @keyframes fadeUp {
      from { opacity: 0; transform: translateY(16px); }
      to   { opacity: 1; transform: translateY(0); }
    }
    .animate-slide { animation: slideIn 0.3s ease; }
    .card-anim { animation: fadeUp 0.4s ease both; }

    /* Tab active */
    .tab-btn.active {
      background: #b91c1c;
      color: white;
    }

    /* Table row hover */
    tr.incident-row:hover td { background: #fef2f2; }
    tr.user-row:hover td     { background: #fef2f2; }

    /* Mobile card for incidents */
    .inc-card { border-left: 4px solid; }
    .inc-card.low      { border-color: #10b981; }
    .inc-card.medium   { border-color: #f59e0b; }
    .inc-card.high     { border-color: #f97316; }
    .inc-card.critical { border-color: #dc2626; }
  </style>
</head>
<body class="bg-gray-50 min-h-screen">

<!-- ============================================================
     TOAST NOTIFICATION
     ============================================================ -->
<?php if ($toast): ?>
<div id="toast" class="fixed top-4 right-4 z-50 animate-slide">
  <?php
    $msgs = [
      'resolved'     => ['✅', 'Incident marked as resolved', 'bg-green-600'],
      'deleted'      => ['🗑️', 'Incident deleted',           'bg-gray-700'],
      'user_deleted' => ['🗑️', 'User deleted',               'bg-gray-700'],
    ];
    [$icon, $msg, $bg] = $msgs[$toast] ?? ['ℹ️', 'Done', 'bg-blue-600'];
  ?>
  <div class="<?= $bg ?> text-white px-5 py-3 rounded-xl shadow-xl flex items-center gap-2 text-sm font-medium">
    <span><?= $icon ?></span> <?= $msg ?>
  </div>
</div>
<script>setTimeout(() => document.getElementById('toast')?.remove(), 3500);</script>
<?php endif; ?>

<!-- ============================================================
     NAVBAR
     ============================================================ -->
<nav class="bg-red-700 text-white sticky top-0 z-40 shadow-lg">
  <div class="max-w-7xl mx-auto px-4 py-3 flex items-center justify-between">
    
    <div class="flex items-center gap-3">
      <!-- Mobile menu toggle -->
      <button onclick="toggleSidebar()" class="md:hidden p-1 rounded hover:bg-red-800">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
        </svg>
      </button>
      <div>
        <span class="text-xs text-red-300 uppercase tracking-widest block leading-none">ACLC Smart Campus</span>
        <span class="font-bold text-lg leading-tight">Admin Panel</span>
      </div>
    </div>

    <div class="flex items-center gap-2">
      <span class="hidden sm:block text-red-200 text-sm">👤 <?= htmlspecialchars($_SESSION['user']) ?></span>
      <a href="dashboard.php" class="bg-white/20 hover:bg-white/30 px-3 py-1.5 rounded-lg text-sm font-medium transition">
        Dashboard
      </a>
      <a href="logout.php" class="bg-white text-red-700 hover:bg-red-50 px-3 py-1.5 rounded-lg text-sm font-semibold transition">
        Logout
      </a>
    </div>

  </div>
</nav>

<!-- ============================================================
     DB ERROR BANNER
     ============================================================ -->
<?php if ($db_error): ?>
<div class="bg-red-50 border-b border-red-200 text-red-700 px-4 py-3 text-sm text-center">
  ⚠️ Database connection failed. Check your credentials in admin.php.
  <code class="mono text-xs ml-2 text-red-500"><?= htmlspecialchars($db_error) ?></code>
</div>
<?php endif; ?>

<!-- ============================================================
     MAIN CONTENT
     ============================================================ -->
<div class="max-w-7xl mx-auto px-4 py-6 space-y-6">

  <!-- STAT CARDS -->
  <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
    <?php
    $cards = [
      ['label' => 'Total Users',    'value' => $stats['users'],         'color' => 'text-red-600',    'bg' => 'bg-red-50',    'icon' => '👥'],
      ['label' => 'Reports',        'value' => $stats['total_reports'], 'color' => 'text-amber-600',  'bg' => 'bg-amber-50',  'icon' => '📋'],
      ['label' => 'Resolved',       'value' => $stats['resolved'],      'color' => 'text-emerald-600','bg' => 'bg-emerald-50','icon' => '✅'],
      ['label' => 'High Risk Open', 'value' => $stats['high_risk'],     'color' => 'text-red-600',    'bg' => 'bg-red-50',    'icon' => '🚨'],
    ];
    foreach ($cards as $i => $c):
    ?>
    <div class="bg-white rounded-2xl p-4 shadow-sm border border-gray-100 card-anim" style="animation-delay:<?= $i * 0.06 ?>s">
      <div class="flex items-center justify-between mb-2">
        <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider"><?= $c['label'] ?></span>
        <span class="text-xl <?= $c['bg'] ?> p-1.5 rounded-lg"><?= $c['icon'] ?></span>
      </div>
      <p class="text-3xl font-bold <?= $c['color'] ?>"><?= $c['value'] ?></p>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- TABS -->
  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
    
    <!-- Tab bar -->
    <div class="flex border-b border-gray-100 p-1 gap-1 bg-gray-50">
      <button onclick="switchTab('incidents')" id="tab-incidents"
        class="tab-btn active flex-1 py-2.5 rounded-xl text-sm font-semibold transition">
        🚨 Incident Reports <span class="ml-1 bg-white/30 text-inherit text-xs px-1.5 py-0.5 rounded-full"><?= count($incidents) ?></span>
      </button>
      <button onclick="switchTab('users')" id="tab-users"
        class="tab-btn flex-1 py-2.5 rounded-xl text-sm font-semibold text-gray-500 hover:text-gray-700 transition">
        👥 Users <span class="ml-1 bg-gray-200 text-gray-500 text-xs px-1.5 py-0.5 rounded-full"><?= count($users) ?></span>
      </button>
    </div>

    <!-- ---- INCIDENTS PANEL ---- -->
    <div id="panel-incidents" class="p-4">

      <!-- Search + Filter -->
      <div class="flex flex-col sm:flex-row gap-2 mb-4">
        <input type="text" id="incidentSearch" oninput="filterIncidents()"
          placeholder="🔍 Search type, location, reporter..."
          class="flex-1 border border-gray-200 rounded-xl px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-400">
        <select id="severityFilter" onchange="filterIncidents()"
          class="border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-400 bg-white">
          <option value="">All Severities</option>
          <option value="critical">🚨 Critical</option>
          <option value="high">🔴 High</option>
          <option value="medium">🟡 Medium</option>
          <option value="low">🟢 Low</option>
        </select>
        <select id="statusFilter" onchange="filterIncidents()"
          class="border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-400 bg-white">
          <option value="">All Status</option>
          <option value="open">Open</option>
          <option value="resolved">Resolved</option>
        </select>
      </div>

      <?php if (empty($incidents)): ?>
      <div class="text-center py-16 text-gray-400">
        <div class="text-5xl mb-3">📭</div>
        <p class="font-medium">No incidents reported yet.</p>
      </div>
      <?php else: ?>

      <!-- DESKTOP TABLE (hidden on mobile) -->
      <div class="hidden md:block overflow-x-auto">
        <table class="w-full text-sm" id="incidentTable">
          <thead>
            <tr class="text-left text-xs font-semibold text-gray-400 uppercase tracking-wider border-b border-gray-100">
              <th class="pb-3 pl-2">ID</th>
              <th class="pb-3">Type</th>
              <th class="pb-3">Location</th>
              <th class="pb-3">Severity</th>
              <th class="pb-3">Status</th>
              <th class="pb-3">Reported By</th>
              <th class="pb-3">Time</th>
              <th class="pb-3">Actions</th>
            </tr>
          </thead>
          <tbody id="incidentTbody">
            <?php foreach ($incidents as $inc): ?>
            <tr class="incident-row border-b border-gray-50 transition"
              data-type="<?= htmlspecialchars($inc['incident_type']) ?>"
              data-location="<?= htmlspecialchars($inc['location']) ?>"
              data-reporter="<?= htmlspecialchars($inc['reported_by']) ?>"
              data-severity="<?= htmlspecialchars($inc['severity']) ?>"
              data-status="<?= htmlspecialchars($inc['status']) ?>">
              
              <td class="py-3 pl-2 mono text-gray-400">#<?= $inc['id'] ?></td>
              
              <td class="py-3">
                <span class="font-medium text-gray-700">
                  <?= typeIcon($inc['incident_type']) ?> <?= ucfirst(str_replace('_',' ',$inc['incident_type'])) ?>
                </span>
              </td>
              
              <td class="py-3 text-gray-500 capitalize"><?= htmlspecialchars(str_replace('_',' ',$inc['location'])) ?></td>
              
              <td class="py-3"><?= severityBadge($inc['severity']) ?></td>
              
              <td class="py-3"><?= statusBadge($inc['status']) ?></td>
              
              <td class="py-3 text-gray-500 text-xs"><?= htmlspecialchars($inc['reported_by']) ?></td>
              
              <td class="py-3 text-gray-400 text-xs mono whitespace-nowrap">
                <?= date('M d, H:i', strtotime($inc['reported_at'])) ?>
              </td>

              <td class="py-3">
                <div class="flex gap-1 items-center">

                  <!-- View Details -->
                  <button onclick='showDetail(<?= json_encode($inc) ?>)'
                    class="text-xs bg-gray-100 hover:bg-gray-200 text-gray-600 px-2 py-1 rounded-lg transition">
                    👁 View
                  </button>

                  <?php if ($inc['status'] !== 'resolved'): ?>
                  <!-- Resolve -->
                  <form method="POST" class="inline">
                    <input type="hidden" name="action" value="resolve_incident">
                    <input type="hidden" name="id" value="<?= $inc['id'] ?>">
                    <button type="submit"
                      class="text-xs bg-emerald-100 hover:bg-emerald-200 text-emerald-700 px-2 py-1 rounded-lg transition">
                      ✅ Resolve
                    </button>
                  </form>
                  <?php endif; ?>

                  <!-- Delete -->
                  <form method="POST" class="inline" onsubmit="return confirm('Delete this incident report?')">
                    <input type="hidden" name="action" value="delete_incident">
                    <input type="hidden" name="id" value="<?= $inc['id'] ?>">
                    <button type="submit"
                      class="text-xs bg-red-100 hover:bg-red-200 text-red-600 px-2 py-1 rounded-lg transition">
                      🗑
                    </button>
                  </form>

                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- MOBILE CARDS (hidden on desktop) -->
      <div class="md:hidden space-y-3" id="incidentCards">
        <?php foreach ($incidents as $inc): ?>
        <div class="inc-card <?= $inc['severity'] ?> bg-white rounded-xl p-4 shadow-sm"
          data-type="<?= htmlspecialchars($inc['incident_type']) ?>"
          data-location="<?= htmlspecialchars($inc['location']) ?>"
          data-reporter="<?= htmlspecialchars($inc['reported_by']) ?>"
          data-severity="<?= htmlspecialchars($inc['severity']) ?>"
          data-status="<?= htmlspecialchars($inc['status']) ?>">

          <!-- Header row -->
          <div class="flex items-start justify-between mb-2">
            <div>
              <span class="font-bold text-gray-800">
                <?= typeIcon($inc['incident_type']) ?> <?= ucfirst(str_replace('_',' ',$inc['incident_type'])) ?>
              </span>
              <p class="text-xs text-gray-400 mono">#<?= $inc['id'] ?> · <?= date('M d, H:i', strtotime($inc['reported_at'])) ?></p>
            </div>
            <?= severityBadge($inc['severity']) ?>
          </div>

          <!-- Details -->
          <div class="text-sm text-gray-600 space-y-1 mb-3">
            <p>📍 <?= htmlspecialchars(ucwords(str_replace('_',' ',$inc['location']))) ?></p>
            <p>👤 <?= htmlspecialchars($inc['reported_by']) ?></p>
            <p><?= statusBadge($inc['status']) ?></p>
          </div>

          <!-- Description preview -->
          <p class="text-xs text-gray-500 mb-3 line-clamp-2"><?= htmlspecialchars($inc['description']) ?></p>

          <!-- Actions -->
          <div class="flex gap-2">
            <button onclick='showDetail(<?= json_encode($inc) ?>)'
              class="flex-1 text-xs bg-gray-100 hover:bg-gray-200 text-gray-600 py-2 rounded-lg font-medium transition">
              👁 Details
            </button>
            <?php if ($inc['status'] !== 'resolved'): ?>
            <form method="POST" class="flex-1">
              <input type="hidden" name="action" value="resolve_incident">
              <input type="hidden" name="id" value="<?= $inc['id'] ?>">
              <button type="submit" class="w-full text-xs bg-emerald-100 hover:bg-emerald-200 text-emerald-700 py-2 rounded-lg font-medium transition">
                ✅ Resolve
              </button>
            </form>
            <?php endif; ?>
            <form method="POST" onsubmit="return confirm('Delete this incident?')">
              <input type="hidden" name="action" value="delete_incident">
              <input type="hidden" name="id" value="<?= $inc['id'] ?>">
              <button type="submit" class="text-xs bg-red-100 hover:bg-red-200 text-red-600 px-3 py-2 rounded-lg font-medium transition">
                🗑
              </button>
            </form>
          </div>

        </div>
        <?php endforeach; ?>
      </div>

      <p class="text-xs text-gray-400 mt-3 text-right mono" id="incidentCount">
        Showing <?= count($incidents) ?> incident<?= count($incidents) !== 1 ? 's' : '' ?>
      </p>

      <?php endif; ?>
    </div>

    <!-- ---- USERS PANEL ---- -->
    <div id="panel-users" class="p-4 hidden">

      <div class="mb-4">
        <input type="text" id="userSearch" oninput="filterUsers()"
          placeholder="🔍 Search by name, email, or role..."
          class="w-full border border-gray-200 rounded-xl px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-400">
      </div>

      <?php if (empty($users)): ?>
      <div class="text-center py-16 text-gray-400">
        <div class="text-5xl mb-3">👥</div>
        <p class="font-medium">No users found.</p>
      </div>
      <?php else: ?>

      <!-- DESKTOP -->
      <div class="hidden md:block overflow-x-auto">
        <table class="w-full text-sm" id="userTable">
          <thead>
            <tr class="text-left text-xs font-semibold text-gray-400 uppercase tracking-wider border-b border-gray-100">
              <th class="pb-3 pl-2">ID</th>
              <th class="pb-3">Username</th>
              <th class="pb-3">Email</th>
              <th class="pb-3">Role</th>
              <th class="pb-3">Joined</th>
              <th class="pb-3">Action</th>
            </tr>
          </thead>
          <tbody id="userTbody">
            <?php foreach ($users as $u): ?>
            <tr class="user-row border-b border-gray-50 transition"
              data-name="<?= htmlspecialchars($u['name']) ?>"
              data-email="<?= htmlspecialchars($u['email'] ?? '') ?>"
              data-role="<?= htmlspecialchars($u['role']) ?>">
              <td class="py-3 pl-2 mono text-gray-400">#<?= $u['id'] ?></td>
              <td class="py-3 font-medium text-gray-700"><?= htmlspecialchars($u['name']) ?></td>
              <td class="py-3 text-gray-500 text-xs"><?= htmlspecialchars($u['email'] ?? '—') ?></td>
              <td class="py-3">
                <span class="text-xs font-semibold px-2 py-0.5 rounded-full <?= $u['role'] === 'admin' ? 'bg-red-100 text-red-600' : 'bg-gray-100 text-gray-600' ?>">
                  <?= $u['role'] === 'admin' ? '🛡 Admin' : '👤 User' ?>
                </span>
              </td>
              <td class="py-3 text-gray-400 text-xs mono">
                <?= isset($u['created_at']) ? date('M d, Y', strtotime($u['created_at'])) : '—' ?>
              </td>
              <td class="py-3">
                <?php if ($u['name'] !== $_SESSION['user']): ?>
                <form method="POST" onsubmit="return confirm('Delete user <?= htmlspecialchars($u['name']) ?>?')">
                  <input type="hidden" name="action" value="delete_user">
                  <input type="hidden" name="id" value="<?= $u['id'] ?>">
                  <button type="submit" class="text-xs bg-red-100 hover:bg-red-200 text-red-600 px-3 py-1 rounded-lg transition">
                    🗑 Delete
                  </button>
                </form>
                <?php else: ?>
                <span class="text-xs text-gray-300 italic">You</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- MOBILE -->
      <div class="md:hidden space-y-3" id="userCards">
        <?php foreach ($users as $u): ?>
        <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100 flex items-center justify-between"
          data-name="<?= htmlspecialchars($u['name']) ?>"
          data-email="<?= htmlspecialchars($u['email'] ?? '') ?>"
          data-role="<?= htmlspecialchars($u['role']) ?>">
          <div>
            <p class="font-semibold text-gray-800"><?= htmlspecialchars($u['name']) ?></p>
            <p class="text-xs text-gray-400"><?= htmlspecialchars($u['email'] ?? '—') ?></p>
            <span class="inline-block mt-1 text-xs font-semibold px-2 py-0.5 rounded-full <?= $u['role'] === 'admin' ? 'bg-red-100 text-red-600' : 'bg-gray-100 text-gray-600' ?>">
              <?= $u['role'] === 'admin' ? '🛡 Admin' : '👤 User' ?>
            </span>
          </div>
          <?php if ($u['name'] !== $_SESSION['user']): ?>
          <form method="POST" onsubmit="return confirm('Delete <?= htmlspecialchars($u['name']) ?>?')">
            <input type="hidden" name="action" value="delete_user">
            <input type="hidden" name="id" value="<?= $u['id'] ?>">
            <button type="submit" class="text-xs bg-red-100 hover:bg-red-200 text-red-600 px-3 py-2 rounded-lg font-medium transition">
              🗑
            </button>
          </form>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>

      <?php endif; ?>
    </div>

  </div>
</div>

<!-- ============================================================
     INCIDENT DETAIL MODAL
     ============================================================ -->
<div id="detailModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/60 backdrop-blur-sm p-4">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md max-h-[90vh] overflow-y-auto">
    
    <div class="bg-red-700 text-white px-5 py-4 rounded-t-2xl flex justify-between items-center">
      <h3 class="font-bold text-lg">Incident Details</h3>
      <button onclick="closeDetail()" class="text-white hover:text-red-200 text-2xl leading-none">&times;</button>
    </div>

    <div class="p-5 space-y-3 text-sm" id="detailContent"></div>

  </div>
</div>

<!-- ============================================================
     JAVASCRIPT
     ============================================================ -->
<script>
// ---- Tabs ----
function switchTab(tab) {
  ['incidents','users'].forEach(t => {
    document.getElementById('panel-' + t).classList.toggle('hidden', t !== tab);
    document.getElementById('tab-' + t).classList.toggle('active', t === tab);
    if (t !== tab) document.getElementById('tab-' + t).classList.add('text-gray-500');
    else document.getElementById('tab-' + t).classList.remove('text-gray-500');
  });
}

// ---- Incident filter ----
function filterIncidents() {
  const q   = document.getElementById('incidentSearch').value.toLowerCase();
  const sev = document.getElementById('severityFilter').value;
  const sta = document.getElementById('statusFilter').value;

  let visible = 0;

  // Desktop rows
  document.querySelectorAll('#incidentTbody tr').forEach(row => {
    const match = matchRow(row, q, sev, sta);
    row.style.display = match ? '' : 'none';
    if (match) visible++;
  });

  // Mobile cards
  document.querySelectorAll('#incidentCards > div').forEach(card => {
    card.style.display = matchRow(card, q, sev, sta) ? '' : 'none';
  });

  const c = document.getElementById('incidentCount');
  if (c) c.textContent = `Showing ${visible} incident${visible !== 1 ? 's' : ''}`;
}

function matchRow(el, q, sev, sta) {
  const type     = (el.dataset.type     || '').toLowerCase();
  const location = (el.dataset.location || '').toLowerCase();
  const reporter = (el.dataset.reporter || '').toLowerCase();
  const severity = (el.dataset.severity || '').toLowerCase();
  const status   = (el.dataset.status   || '').toLowerCase();

  if (q   && !type.includes(q) && !location.includes(q) && !reporter.includes(q)) return false;
  if (sev && severity !== sev) return false;
  if (sta && status   !== sta) return false;
  return true;
}

// ---- User filter ----
function filterUsers() {
  const q = document.getElementById('userSearch').value.toLowerCase();
  document.querySelectorAll('#userTbody tr, #userCards > div').forEach(el => {
    const n = (el.dataset.name  || '').toLowerCase();
    const e = (el.dataset.email || '').toLowerCase();
    const r = (el.dataset.role  || '').toLowerCase();
    el.style.display = (!q || n.includes(q) || e.includes(q) || r.includes(q)) ? '' : 'none';
  });
}

// ---- Detail Modal ----
function showDetail(inc) {
  const sev = { low:'🟢', medium:'🟡', high:'🔴', critical:'🚨' };
  const sta = { open:'🔴 Open', in_progress:'🔵 In Progress', resolved:'✅ Resolved' };
  const types = { fire:'🔥',medical:'🏥',accident:'⚠️',suspicious:'👁️',theft:'🔓',flooding:'🌊',earthquake:'🌍',other:'📋' };

  let html = `
    <div class="grid grid-cols-2 gap-3">
      <div class="col-span-2 bg-gray-50 rounded-xl p-3">
        <p class="text-xs text-gray-400 uppercase tracking-wider mb-1">Type</p>
        <p class="font-semibold text-gray-800">${types[inc.incident_type]||'📋'} ${inc.incident_type.replace(/_/g,' ').replace(/\b\w/g,c=>c.toUpperCase())}</p>
      </div>
      <div class="bg-gray-50 rounded-xl p-3">
        <p class="text-xs text-gray-400 uppercase tracking-wider mb-1">Severity</p>
        <p class="font-semibold">${sev[inc.severity]||''} ${inc.severity.charAt(0).toUpperCase()+inc.severity.slice(1)}</p>
      </div>
      <div class="bg-gray-50 rounded-xl p-3">
        <p class="text-xs text-gray-400 uppercase tracking-wider mb-1">Status</p>
        <p class="font-semibold">${sta[inc.status]||inc.status}</p>
      </div>
      <div class="col-span-2 bg-gray-50 rounded-xl p-3">
        <p class="text-xs text-gray-400 uppercase tracking-wider mb-1">Location</p>
        <p class="font-semibold">📍 ${inc.location.replace(/_/g,' ').replace(/\b\w/g,c=>c.toUpperCase())}</p>
      </div>
      <div class="col-span-2 bg-gray-50 rounded-xl p-3">
        <p class="text-xs text-gray-400 uppercase tracking-wider mb-1">Description</p>
        <p class="text-gray-700 leading-relaxed">${inc.description}</p>
      </div>
      <div class="bg-gray-50 rounded-xl p-3">
        <p class="text-xs text-gray-400 uppercase tracking-wider mb-1">Reported By</p>
        <p class="font-semibold">👤 ${inc.reported_by}</p>
      </div>
      <div class="bg-gray-50 rounded-xl p-3">
        <p class="text-xs text-gray-400 uppercase tracking-wider mb-1">Report #</p>
        <p class="font-semibold mono text-gray-500">#${inc.id}</p>
      </div>
      <div class="col-span-2 bg-gray-50 rounded-xl p-3">
        <p class="text-xs text-gray-400 uppercase tracking-wider mb-1">Time Reported</p>
        <p class="font-semibold mono text-gray-600">${inc.reported_at}</p>
      </div>
  `;

  if (inc.photo_path) {
    html += `
      <div class="col-span-2">
        <p class="text-xs text-gray-400 uppercase tracking-wider mb-2">Photo Evidence</p>
        <img src="${inc.photo_path}" class="rounded-xl w-full object-cover max-h-48 border border-gray-100" alt="Evidence photo">
      </div>
    `;
  }

  html += `</div>`;

  document.getElementById('detailContent').innerHTML = html;
  const modal = document.getElementById('detailModal');
  modal.classList.remove('hidden');
  modal.classList.add('flex');
}

function closeDetail() {
  const modal = document.getElementById('detailModal');
  modal.classList.add('hidden');
  modal.classList.remove('flex');
}

document.getElementById('detailModal').addEventListener('click', function(e) {
  if (e.target === this) closeDetail();
});
</script>

</body>
</html>