<?php
session_start();

header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

if (!isset($_SESSION['user']) || $_SESSION['role'] != 'admin') {
    header("Location: dashboard.php");
    exit();
}

$host   = 'localhost';
$dbname = 'campus_system';
$dbuser = 'root';
$dbpass = '';

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

// ── POST handlers ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo) {
    $action = $_POST['action'] ?? '';

    if ($action === 'resolve_incident' && isset($_POST['id'])) {
        $s = $pdo->prepare("UPDATE incidents SET status='resolved' WHERE id=?");
        $s->execute([(int)$_POST['id']]);
        header("Location: admin.php?toast=resolved"); exit();
    }
    if ($action === 'delete_incident' && isset($_POST['id'])) {
        $s = $pdo->prepare("DELETE FROM incidents WHERE id=?");
        $s->execute([(int)$_POST['id']]);
        header("Location: admin.php?toast=deleted"); exit();
    }
    if ($action === 'delete_user' && isset($_POST['id'])) {
        if ($_POST['id'] != ($_SESSION['user_id'] ?? -1)) {
            $s = $pdo->prepare("DELETE FROM users WHERE id=?");
            $s->execute([(int)$_POST['id']]);
        }
        header("Location: admin.php?toast=user_deleted"); exit();
    }

    // Emergency contacts
    if ($action === 'add_emergency') {
        $s = $pdo->prepare("INSERT INTO emergency_services (category,name,number,address,description,is_active,sort_order) VALUES (?,?,?,?,?,1,0)");
        $s->execute([
            $_POST['category']       ?? 'other',
            trim($_POST['ec_name']        ?? ''),
            trim($_POST['number']         ?? ''),
            trim($_POST['address']        ?? ''),
            trim($_POST['ec_description'] ?? ''),
        ]);
        header("Location: admin.php?tab=emergency&toast=ec_added"); exit();
    }
    if ($action === 'delete_emergency' && isset($_POST['id'])) {
        $s = $pdo->prepare("DELETE FROM emergency_services WHERE id=?");
        $s->execute([(int)$_POST['id']]);
        header("Location: admin.php?tab=emergency&toast=ec_deleted"); exit();
    }
    if ($action === 'toggle_emergency' && isset($_POST['id'])) {
        $s = $pdo->prepare("UPDATE emergency_services SET is_active = NOT is_active WHERE id=?");
        $s->execute([(int)$_POST['id']]);
        header("Location: admin.php?tab=emergency&toast=ec_updated"); exit();
    }
}

// ── Fetch stats ───────────────────────────────────────────────────
$stats = ['users'=>0,'total_reports'=>0,'resolved'=>0,'high_risk'=>0];
if ($pdo) {
    $stats['users']         = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $stats['total_reports'] = $pdo->query("SELECT COUNT(*) FROM incidents")->fetchColumn();
    $stats['resolved']      = $pdo->query("SELECT COUNT(*) FROM incidents WHERE status='resolved'")->fetchColumn();
    $stats['high_risk']     = $pdo->query("SELECT COUNT(*) FROM incidents WHERE severity IN ('high','critical') AND status='open'")->fetchColumn();
}

// ── Fetch data ────────────────────────────────────────────────────
$users = $pdo ? $pdo->query("SELECT id,name,email,role,created_at FROM users ORDER BY id DESC")->fetchAll() : [];

$incidents = $pdo ? $pdo->query("SELECT id,incident_type,severity,location,description,reported_by,status,photo_path,reported_at FROM incidents ORDER BY reported_at DESC")->fetchAll() : [];

$emergency_contacts = [];
if ($pdo) {
    try { $emergency_contacts = $pdo->query("SELECT * FROM emergency_services ORDER BY category,sort_order,id")->fetchAll(); }
    catch (PDOException $e) {}
}

// ── Helpers ───────────────────────────────────────────────────────
function severityBadge($s) {
    $map = ['low'=>['bg-emerald-100 text-emerald-700','🟢 Low'],'medium'=>['bg-amber-100 text-amber-700','🟡 Medium'],'high'=>['bg-orange-100 text-orange-700','🔴 High'],'critical'=>['bg-red-100 text-red-700','🚨 Critical']];
    [$cls,$label] = $map[$s] ?? ['bg-gray-100 text-gray-600',$s];
    return "<span class='inline-block text-xs font-semibold px-2 py-0.5 rounded-full $cls'>$label</span>";
}
function statusBadge($s) {
    $map = ['open'=>['bg-red-100 text-red-600','Open'],'in_progress'=>['bg-blue-100 text-blue-600','In Progress'],'resolved'=>['bg-green-100 text-green-600','Resolved']];
    [$cls,$label] = $map[$s] ?? ['bg-gray-100 text-gray-600',$s];
    return "<span class='inline-block text-xs font-semibold px-2 py-0.5 rounded-full $cls'>$label</span>";
}
function typeIcon($t) {
    return ['fire'=>'🔥','medical'=>'🏥','accident'=>'⚠️','suspicious'=>'👁️','theft'=>'🔓','flooding'=>'🌊','earthquake'=>'🌍','other'=>'📋'][$t] ?? '📋';
}

$ec_categories = [
    'fire'    => ['label'=>'Fire Department',    'icon'=>'🔥','badge'=>'bg-red-100 text-red-700',      'dot'=>'bg-red-500'],
    'medical' => ['label'=>'Medical / Ambulance','icon'=>'🚑','badge'=>'bg-emerald-100 text-emerald-700','dot'=>'bg-emerald-500'],
    'police'  => ['label'=>'Police',             'icon'=>'👮','badge'=>'bg-blue-100 text-blue-700',     'dot'=>'bg-blue-500'],
    'campus'  => ['label'=>'Campus Services',    'icon'=>'🏫','badge'=>'bg-amber-100 text-amber-700',   'dot'=>'bg-amber-500'],
    'other'   => ['label'=>'Other',              'icon'=>'📞','badge'=>'bg-gray-100 text-gray-600',     'dot'=>'bg-gray-400'],
];

$active_tab = $_GET['tab'] ?? 'incidents';
$toast      = $_GET['toast'] ?? '';

$toast_map = [
    'resolved'   =>['✅','Incident marked as resolved','bg-green-600'],
    'deleted'    =>['🗑️','Incident deleted','bg-gray-700'],
    'user_deleted'=>['🗑️','User deleted','bg-gray-700'],
    'ec_added'   =>['✅','Emergency contact added','bg-green-600'],
    'ec_deleted' =>['🗑️','Contact deleted','bg-gray-700'],
    'ec_updated' =>['🔄','Contact updated','bg-blue-600'],
];
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
    @keyframes slideIn { from{opacity:0;transform:translateY(-12px)} to{opacity:1;transform:translateY(0)} }
    @keyframes fadeUp  { from{opacity:0;transform:translateY(16px)}  to{opacity:1;transform:translateY(0)} }
    .animate-slide { animation: slideIn 0.3s ease; }
    .card-anim     { animation: fadeUp  0.4s ease both; }
    .tab-btn.active { background:#b91c1c; color:white; }
    tr.incident-row:hover td { background:#fef2f2; }
    tr.user-row:hover td     { background:#fef2f2; }
    tr.ec-row:hover td        { background:#fef2f2; }
    .inc-card { border-left:4px solid; }
    .inc-card.low      { border-color:#10b981; }
    .inc-card.medium   { border-color:#f59e0b; }
    .inc-card.high     { border-color:#f97316; }
    .inc-card.critical { border-color:#dc2626; }
    @keyframes modalIn { from{opacity:0;transform:scale(.95) translateY(10px)} to{opacity:1;transform:scale(1) translateY(0)} }
    .animate-modal { animation: modalIn 0.2s ease-out; }
  </style>
</head>
<body class="bg-gray-50 min-h-screen">

<!-- TOAST -->
<?php if ($toast && isset($toast_map[$toast])): ?>
<?php [$ti,$tm,$tb] = $toast_map[$toast]; ?>
<div id="toast" class="fixed top-4 right-4 z-50 animate-slide">
  <div class="<?= $tb ?> text-white px-5 py-3 rounded-xl shadow-xl flex items-center gap-2 text-sm font-medium">
    <?= $ti ?> <?= $tm ?>
  </div>
</div>
<script>setTimeout(()=>document.getElementById('toast')?.remove(),3500);</script>
<?php endif; ?>

<!-- NAVBAR -->
<nav class="bg-red-700 text-white sticky top-0 z-40 shadow-lg">
  <div class="max-w-7xl mx-auto px-4 py-3 flex items-center justify-between">
    <div>
      <span class="text-xs text-red-300 uppercase tracking-widest block leading-none">ACLC Smart Campus</span>
      <span class="font-bold text-lg leading-tight">Admin Panel</span>
    </div>
    <div class="flex items-center gap-2">
      <span class="hidden sm:block text-red-200 text-sm">👤 <?= htmlspecialchars($_SESSION['user']) ?></span>
      <a href="dashboard.php" class="bg-white/20 hover:bg-white/30 px-3 py-1.5 rounded-lg text-sm font-medium transition">Dashboard</a>
      <a href="logout.php"    class="bg-white text-red-700 hover:bg-red-50 px-3 py-1.5 rounded-lg text-sm font-semibold transition">Logout</a>
    </div>
  </div>
</nav>

<?php if ($db_error): ?>
<div class="bg-red-50 border-b border-red-200 text-red-700 px-4 py-3 text-sm text-center">
  ⚠️ Database connection failed. <code class="mono text-xs ml-2 text-red-500"><?= htmlspecialchars($db_error) ?></code>
</div>
<?php endif; ?>

<div class="max-w-7xl mx-auto px-4 py-6 space-y-6">

  <!-- STAT CARDS -->
  <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
    <?php foreach ([
      ['Total Users','users','text-red-600','bg-red-50','👥'],
      ['Reports','total_reports','text-amber-600','bg-amber-50','📋'],
      ['Resolved','resolved','text-emerald-600','bg-emerald-50','✅'],
      ['High Risk Open','high_risk','text-red-600','bg-red-50','🚨'],
    ] as $i=>[$label,$key,$color,$bg,$icon]): ?>
    <div class="bg-white rounded-2xl p-4 shadow-sm border border-gray-100 card-anim" style="animation-delay:<?= $i*0.06 ?>s">
      <div class="flex items-center justify-between mb-2">
        <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider"><?= $label ?></span>
        <span class="text-xl <?= $bg ?> p-1.5 rounded-lg"><?= $icon ?></span>
      </div>
      <p class="text-3xl font-bold <?= $color ?>"><?= $stats[$key] ?></p>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- TABS -->
  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">

    <!-- Tab bar -->
    <div class="flex border-b border-gray-100 p-1 gap-1 bg-gray-50 overflow-x-auto">
      <?php
      $tabs = [
        'incidents' => ['🚨','Incident Reports', count($incidents)],
        'users'     => ['👥','Users',             count($users)],
        'emergency' => ['📞','Emergency Contacts', count($emergency_contacts)],
      ];
      foreach ($tabs as $tid => [$ticon,$tlabel,$tcount]):
        $isActive = ($active_tab === $tid);
      ?>
      <button onclick="switchTab('<?= $tid ?>')" id="tab-<?= $tid ?>"
        class="tab-btn <?= $isActive ? 'active' : 'text-gray-500 hover:text-gray-700' ?> flex-shrink-0 flex-1 py-2.5 rounded-xl text-sm font-semibold transition whitespace-nowrap">
        <?= $ticon ?> <span class="hidden sm:inline"><?= $tlabel ?></span>
        <span class="ml-1 <?= $isActive ? 'bg-white/30 text-inherit' : 'bg-gray-200 text-gray-500' ?> text-xs px-1.5 py-0.5 rounded-full"><?= $tcount ?></span>
      </button>
      <?php endforeach; ?>
    </div>

    <!-- ══════════════════════════════════════
         INCIDENTS PANEL
         ══════════════════════════════════════ -->
    <div id="panel-incidents" class="p-4 <?= $active_tab !== 'incidents' ? 'hidden' : '' ?>">
      <div class="flex flex-col sm:flex-row gap-2 mb-4">
        <input type="text" id="incidentSearch" oninput="filterIncidents()" placeholder="🔍 Search type, location, reporter..."
          class="flex-1 border border-gray-200 rounded-xl px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-400">
        <select id="severityFilter" onchange="filterIncidents()" class="border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-400 bg-white">
          <option value="">All Severities</option>
          <option value="critical">🚨 Critical</option><option value="high">🔴 High</option>
          <option value="medium">🟡 Medium</option><option value="low">🟢 Low</option>
        </select>
        <select id="statusFilter" onchange="filterIncidents()" class="border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-400 bg-white">
          <option value="">All Status</option><option value="open">Open</option><option value="resolved">Resolved</option>
        </select>
      </div>

      <?php if (empty($incidents)): ?>
      <div class="text-center py-16 text-gray-400"><div class="text-5xl mb-3">📭</div><p class="font-medium">No incidents reported yet.</p></div>
      <?php else: ?>

      <!-- Desktop table -->
      <div class="hidden md:block overflow-x-auto">
        <table class="w-full text-sm">
          <thead><tr class="text-left text-xs font-semibold text-gray-400 uppercase tracking-wider border-b border-gray-100">
            <th class="pb-3 pl-2">ID</th><th class="pb-3">Type</th><th class="pb-3">Location</th>
            <th class="pb-3">Severity</th><th class="pb-3">Status</th><th class="pb-3">Reported By</th>
            <th class="pb-3">Time</th><th class="pb-3">Actions</th>
          </tr></thead>
          <tbody id="incidentTbody">
            <?php foreach ($incidents as $inc): ?>
            <tr class="incident-row border-b border-gray-50 transition"
              data-type="<?= htmlspecialchars($inc['incident_type']) ?>"
              data-location="<?= htmlspecialchars($inc['location']) ?>"
              data-reporter="<?= htmlspecialchars($inc['reported_by']) ?>"
              data-severity="<?= htmlspecialchars($inc['severity']) ?>"
              data-status="<?= htmlspecialchars($inc['status']) ?>">
              <td class="py-3 pl-2 mono text-gray-400">#<?= $inc['id'] ?></td>
              <td class="py-3 font-medium text-gray-700"><?= typeIcon($inc['incident_type']) ?> <?= ucfirst(str_replace('_',' ',$inc['incident_type'])) ?></td>
              <td class="py-3 text-gray-500 capitalize"><?= htmlspecialchars(str_replace('_',' ',$inc['location'])) ?></td>
              <td class="py-3"><?= severityBadge($inc['severity']) ?></td>
              <td class="py-3"><?= statusBadge($inc['status']) ?></td>
              <td class="py-3 text-gray-500 text-xs"><?= htmlspecialchars($inc['reported_by']) ?></td>
              <td class="py-3 text-gray-400 text-xs mono whitespace-nowrap"><?= date('M d, H:i',strtotime($inc['reported_at'])) ?></td>
              <td class="py-3">
                <div class="flex gap-1 items-center">
                  <button onclick='showDetail(<?= json_encode($inc) ?>)' class="text-xs bg-gray-100 hover:bg-gray-200 text-gray-600 px-2 py-1 rounded-lg transition">👁 View</button>
                  <?php if ($inc['status'] !== 'resolved'): ?>
                  <form method="POST" class="inline">
                    <input type="hidden" name="action" value="resolve_incident">
                    <input type="hidden" name="id" value="<?= $inc['id'] ?>">
                    <button type="submit" class="text-xs bg-emerald-100 hover:bg-emerald-200 text-emerald-700 px-2 py-1 rounded-lg transition">✅ Resolve</button>
                  </form>
                  <?php endif; ?>
                  <form method="POST" class="inline" onsubmit="return confirm('Delete this incident?')">
                    <input type="hidden" name="action" value="delete_incident">
                    <input type="hidden" name="id" value="<?= $inc['id'] ?>">
                    <button type="submit" class="text-xs bg-red-100 hover:bg-red-200 text-red-600 px-2 py-1 rounded-lg transition">🗑</button>
                  </form>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- Mobile cards -->
      <div class="md:hidden space-y-3" id="incidentCards">
        <?php foreach ($incidents as $inc): ?>
        <div class="inc-card <?= $inc['severity'] ?> bg-white rounded-xl p-4 shadow-sm"
          data-type="<?= htmlspecialchars($inc['incident_type']) ?>"
          data-location="<?= htmlspecialchars($inc['location']) ?>"
          data-reporter="<?= htmlspecialchars($inc['reported_by']) ?>"
          data-severity="<?= htmlspecialchars($inc['severity']) ?>"
          data-status="<?= htmlspecialchars($inc['status']) ?>">
          <div class="flex items-start justify-between mb-2">
            <div>
              <span class="font-bold text-gray-800"><?= typeIcon($inc['incident_type']) ?> <?= ucfirst(str_replace('_',' ',$inc['incident_type'])) ?></span>
              <p class="text-xs text-gray-400 mono">#<?= $inc['id'] ?> · <?= date('M d, H:i',strtotime($inc['reported_at'])) ?></p>
            </div>
            <?= severityBadge($inc['severity']) ?>
          </div>
          <div class="text-sm text-gray-600 space-y-1 mb-3">
            <p>📍 <?= htmlspecialchars(ucwords(str_replace('_',' ',$inc['location']))) ?></p>
            <p>👤 <?= htmlspecialchars($inc['reported_by']) ?></p>
            <p><?= statusBadge($inc['status']) ?></p>
          </div>
          <p class="text-xs text-gray-500 mb-3 line-clamp-2"><?= htmlspecialchars($inc['description']) ?></p>
          <div class="flex gap-2">
            <button onclick='showDetail(<?= json_encode($inc) ?>)' class="flex-1 text-xs bg-gray-100 hover:bg-gray-200 text-gray-600 py-2 rounded-lg font-medium transition">👁 Details</button>
            <?php if ($inc['status'] !== 'resolved'): ?>
            <form method="POST" class="flex-1">
              <input type="hidden" name="action" value="resolve_incident">
              <input type="hidden" name="id" value="<?= $inc['id'] ?>">
              <button type="submit" class="w-full text-xs bg-emerald-100 hover:bg-emerald-200 text-emerald-700 py-2 rounded-lg font-medium transition">✅ Resolve</button>
            </form>
            <?php endif; ?>
            <form method="POST" onsubmit="return confirm('Delete this incident?')">
              <input type="hidden" name="action" value="delete_incident">
              <input type="hidden" name="id" value="<?= $inc['id'] ?>">
              <button type="submit" class="text-xs bg-red-100 hover:bg-red-200 text-red-600 px-3 py-2 rounded-lg font-medium transition">🗑</button>
            </form>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <p class="text-xs text-gray-400 mt-3 text-right mono" id="incidentCount">Showing <?= count($incidents) ?> incident<?= count($incidents)!==1?'s':'' ?></p>
      <?php endif; ?>
    </div>

    <!-- ══════════════════════════════════════
         USERS PANEL
         ══════════════════════════════════════ -->
    <div id="panel-users" class="p-4 <?= $active_tab !== 'users' ? 'hidden' : '' ?>">
      <div class="mb-4">
        <input type="text" id="userSearch" oninput="filterUsers()" placeholder="🔍 Search by name, email, or role..."
          class="w-full border border-gray-200 rounded-xl px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-400">
      </div>

      <?php if (empty($users)): ?>
      <div class="text-center py-16 text-gray-400"><div class="text-5xl mb-3">👥</div><p class="font-medium">No users found.</p></div>
      <?php else: ?>

      <!-- Desktop -->
      <div class="hidden md:block overflow-x-auto">
        <table class="w-full text-sm">
          <thead><tr class="text-left text-xs font-semibold text-gray-400 uppercase tracking-wider border-b border-gray-100">
            <th class="pb-3 pl-2">ID</th><th class="pb-3">Name</th><th class="pb-3">Email</th>
            <th class="pb-3">Role</th><th class="pb-3">Joined</th><th class="pb-3">Action</th>
          </tr></thead>
          <tbody id="userTbody">
            <?php foreach ($users as $u): ?>
            <tr class="user-row border-b border-gray-50 transition"
              data-name="<?= htmlspecialchars($u['name']) ?>"
              data-email="<?= htmlspecialchars($u['email']??'') ?>"
              data-role="<?= htmlspecialchars($u['role']) ?>">
              <td class="py-3 pl-2 mono text-gray-400">#<?= $u['id'] ?></td>
              <td class="py-3 font-medium text-gray-700"><?= htmlspecialchars($u['name']) ?></td>
              <td class="py-3 text-gray-500 text-xs"><?= htmlspecialchars($u['email']??'—') ?></td>
              <td class="py-3">
                <span class="text-xs font-semibold px-2 py-0.5 rounded-full <?= $u['role']==='admin'?'bg-red-100 text-red-600':'bg-gray-100 text-gray-600' ?>">
                  <?= $u['role']==='admin'?'🛡 Admin':'👤 User' ?>
                </span>
              </td>
              <td class="py-3 text-gray-400 text-xs mono"><?= isset($u['created_at'])?date('M d, Y',strtotime($u['created_at'])):'—' ?></td>
              <td class="py-3">
                <?php if ($u['name'] !== $_SESSION['user']): ?>
                <form method="POST" onsubmit="return confirm('Delete user <?= htmlspecialchars($u['name']) ?>?')">
                  <input type="hidden" name="action" value="delete_user">
                  <input type="hidden" name="id" value="<?= $u['id'] ?>">
                  <button type="submit" class="text-xs bg-red-100 hover:bg-red-200 text-red-600 px-3 py-1 rounded-lg transition">🗑 Delete</button>
                </form>
                <?php else: ?><span class="text-xs text-gray-300 italic">You</span><?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- Mobile -->
      <div class="md:hidden space-y-3" id="userCards">
        <?php foreach ($users as $u): ?>
        <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100 flex items-center justify-between"
          data-name="<?= htmlspecialchars($u['name']) ?>"
          data-email="<?= htmlspecialchars($u['email']??'') ?>"
          data-role="<?= htmlspecialchars($u['role']) ?>">
          <div>
            <p class="font-semibold text-gray-800"><?= htmlspecialchars($u['name']) ?></p>
            <p class="text-xs text-gray-400"><?= htmlspecialchars($u['email']??'—') ?></p>
            <span class="inline-block mt-1 text-xs font-semibold px-2 py-0.5 rounded-full <?= $u['role']==='admin'?'bg-red-100 text-red-600':'bg-gray-100 text-gray-600' ?>">
              <?= $u['role']==='admin'?'🛡 Admin':'👤 User' ?>
            </span>
          </div>
          <?php if ($u['name'] !== $_SESSION['user']): ?>
          <form method="POST" onsubmit="return confirm('Delete <?= htmlspecialchars($u['name']) ?>?')">
            <input type="hidden" name="action" value="delete_user">
            <input type="hidden" name="id" value="<?= $u['id'] ?>">
            <button type="submit" class="text-xs bg-red-100 hover:bg-red-200 text-red-600 px-3 py-2 rounded-lg font-medium transition">🗑</button>
          </form>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <!-- ══════════════════════════════════════
         EMERGENCY CONTACTS PANEL
         ══════════════════════════════════════ -->
    <div id="panel-emergency" class="p-4 <?= $active_tab !== 'emergency' ? 'hidden' : '' ?>">

      <!-- ADD FORM -->
      <div class="bg-gray-50 border border-gray-200 rounded-2xl p-4 mb-5">
        <h3 class="font-bold text-gray-700 mb-3 text-sm">➕ Add New Contact</h3>
        <form id="addEcForm" class="grid grid-cols-1 sm:grid-cols-2 gap-3">

          <div>
            <label class="block text-xs font-semibold text-gray-500 mb-1">Category <span class="text-red-500">*</span></label>
            <select name="category" required class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-400 bg-white">
              <option value="fire">🔥 Fire Department</option>
              <option value="medical">🚑 Medical / Ambulance</option>
              <option value="police">👮 Police</option>
              <option value="campus">🏫 Campus Services</option>
              <option value="other">📞 Other</option>
            </select>
          </div>

          <div>
            <label class="block text-xs font-semibold text-gray-500 mb-1">Contact Name <span class="text-red-500">*</span></label>
            <input type="text" name="ec_name" required placeholder="e.g. BFP Quezon City"
              class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-400">
          </div>

          <div>
            <label class="block text-xs font-semibold text-gray-500 mb-1">Phone Number <span class="text-red-500">*</span></label>
            <input type="text" name="number" required placeholder="e.g. 0917-123-4567 or 160"
              class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-400">
          </div>

          <div>
            <label class="block text-xs font-semibold text-gray-500 mb-1">Address</label>
            <input type="text" name="address" placeholder="e.g. Batasan Hills, QC"
              class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-400">
          </div>

          <div class="sm:col-span-2">
            <label class="block text-xs font-semibold text-gray-500 mb-1">Description / Notes</label>
            <input type="text" name="ec_description" placeholder="e.g. On-duty 24/7, covers northern QC"
              class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-400">
          </div>

          <div class="sm:col-span-2">
            <button type="submit" id="addEcBtn"
              class="w-full sm:w-auto bg-red-700 hover:bg-red-800 text-white font-semibold px-6 py-2.5 rounded-xl transition text-sm flex items-center gap-2">
              <span id="addEcBtnText">➕ Add Contact</span>
              <svg id="addEcSpinner" class="hidden animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
              </svg>
            </button>
          </div>
        </form>
      </div>

      <!-- CONTACTS LIST -->
      <div id="ecListWrap">
      <?php if (empty($emergency_contacts)): ?>
      <div id="ecEmptyState" class="text-center py-12 text-gray-400">
        <div class="text-5xl mb-3">📞</div>
        <p class="font-medium">No emergency contacts yet.</p>
        <p class="text-xs mt-1">Use the form above to add your first contact.</p>
      </div>
      <?php else: ?>

      <!-- Desktop table -->
      <div class="hidden md:block overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="text-left text-xs font-semibold text-gray-400 uppercase tracking-wider border-b border-gray-100">
              <th class="pb-3 pl-2">Category</th>
              <th class="pb-3">Name</th>
              <th class="pb-3">Number</th>
              <th class="pb-3">Address</th>
              <th class="pb-3">Description</th>
              <th class="pb-3">Status</th>
              <th class="pb-3">Actions</th>
            </tr>
          </thead>
          <tbody id="ecTableBody">
            <?php foreach ($emergency_contacts as $ec):
              $meta = $ec_categories[$ec['category']] ?? $ec_categories['other'];
            ?>
            <tr class="ec-row border-b border-gray-50 transition" id="ec-row-<?= $ec['id'] ?>">
              <td class="py-3 pl-2">
                <span class="inline-block text-xs font-semibold px-2 py-0.5 rounded-full <?= $meta['badge'] ?>">
                  <?= $meta['icon'] ?> <?= $meta['label'] ?>
                </span>
              </td>
              <td class="py-3 font-medium text-gray-700"><?= htmlspecialchars($ec['name']) ?></td>
              <td class="py-3 mono text-gray-600 font-semibold"><?= htmlspecialchars($ec['number']) ?></td>
              <td class="py-3 text-gray-400 text-xs"><?= htmlspecialchars($ec['address'] ?? '—') ?></td>
              <td class="py-3 text-gray-400 text-xs"><?= htmlspecialchars($ec['description'] ?? '—') ?></td>
              <td class="py-3" id="ec-status-<?= $ec['id'] ?>">
                <?php if ($ec['is_active']): ?>
                  <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-green-100 text-green-700">● Active</span>
                <?php else: ?>
                  <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-gray-100 text-gray-400">● Hidden</span>
                <?php endif; ?>
              </td>
              <td class="py-3">
                <div class="flex gap-1">
                  <button onclick="openEditModal(<?= htmlspecialchars(json_encode($ec), ENT_QUOTES) ?>)"
                    class="text-xs bg-amber-100 hover:bg-amber-200 text-amber-700 px-2 py-1 rounded-lg transition">✏️ Edit</button>
                  <button onclick="ajaxToggle(<?= $ec['id'] ?>)" id="toggle-btn-<?= $ec['id'] ?>"
                    class="text-xs bg-blue-100 hover:bg-blue-200 text-blue-600 px-2 py-1 rounded-lg transition">
                    <?= $ec['is_active'] ? '🙈 Hide' : '👁 Show' ?>
                  </button>
                  <button onclick="ajaxDelete(<?= $ec['id'] ?>, '<?= htmlspecialchars($ec['name'], ENT_QUOTES) ?>')"
                    class="text-xs bg-red-100 hover:bg-red-200 text-red-600 px-2 py-1 rounded-lg transition">🗑</button>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- Mobile cards -->
      <div class="md:hidden space-y-3" id="ecCardList">
        <?php foreach ($emergency_contacts as $ec):
          $meta = $ec_categories[$ec['category']] ?? $ec_categories['other'];
        ?>
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden flex" id="ec-card-<?= $ec['id'] ?>">
          <div class="w-1.5 shrink-0 <?= $meta['dot'] ?>"></div>
          <div class="flex-1 p-4">
            <div class="flex items-start justify-between gap-2 mb-1">
              <div>
                <span class="inline-block text-xs font-semibold px-2 py-0.5 rounded-full <?= $meta['badge'] ?> mb-1">
                  <?= $meta['icon'] ?> <?= $meta['label'] ?>
                </span>
                <p class="font-bold text-gray-800 text-sm"><?= htmlspecialchars($ec['name']) ?></p>
              </div>
              <span id="ec-card-status-<?= $ec['id'] ?>" class="text-xs font-semibold px-2 py-0.5 rounded-full shrink-0 <?= $ec['is_active'] ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-400' ?>">
                <?= $ec['is_active'] ? 'Active' : 'Hidden' ?>
              </span>
            </div>
            <p class="mono text-sm font-bold text-gray-700 mb-1">📞 <?= htmlspecialchars($ec['number']) ?></p>
            <?php if (!empty($ec['address'])): ?><p class="text-xs text-gray-400">📍 <?= htmlspecialchars($ec['address']) ?></p><?php endif; ?>
            <?php if (!empty($ec['description'])): ?><p class="text-xs text-gray-400"><?= htmlspecialchars($ec['description']) ?></p><?php endif; ?>
            <div class="flex gap-2 mt-3">
              <button onclick="openEditModal(<?= htmlspecialchars(json_encode($ec), ENT_QUOTES) ?>)"
                class="flex-1 text-xs bg-amber-100 hover:bg-amber-200 text-amber-700 py-2 rounded-lg font-medium transition">✏️ Edit</button>
              <button onclick="ajaxToggle(<?= $ec['id'] ?>)" id="toggle-btn-mobile-<?= $ec['id'] ?>"
                class="flex-1 text-xs bg-blue-100 hover:bg-blue-200 text-blue-600 py-2 rounded-lg font-medium transition">
                <?= $ec['is_active'] ? '🙈 Hide' : '👁 Show' ?>
              </button>
              <button onclick="ajaxDelete(<?= $ec['id'] ?>, '<?= htmlspecialchars($ec['name'], ENT_QUOTES) ?>')"
                class="text-xs bg-red-100 hover:bg-red-200 text-red-600 px-3 py-2 rounded-lg font-medium transition">🗑</button>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

      <p class="text-xs text-gray-400 mt-3 text-right mono" id="ecCount">
        <?= count($emergency_contacts) ?> contact<?= count($emergency_contacts)!==1?'s':'' ?> total
      </p>
      <?php endif; ?>
      </div><!-- end ecListWrap -->

    </div>

  </div><!-- end tabs wrapper -->
</div><!-- end main -->

<!-- EDIT EMERGENCY CONTACT MODAL -->
<div id="editEcModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/60 backdrop-blur-sm p-4">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto animate-modal">
    <div class="bg-amber-500 text-white px-5 py-4 rounded-t-2xl flex justify-between items-center">
      <h3 class="font-bold text-lg">✏️ Edit Emergency Contact</h3>
      <button onclick="closeEditModal()" class="text-white hover:text-amber-100 text-2xl leading-none">&times;</button>
    </div>
    <form id="editEcForm" class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
      <input type="hidden" id="edit_id" name="id">

      <div>
        <label class="block text-xs font-semibold text-gray-500 mb-1">Category <span class="text-red-500">*</span></label>
        <select id="edit_category" name="category" required
          class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400 bg-white">
          <option value="fire">🔥 Fire Department</option>
          <option value="medical">🚑 Medical / Ambulance</option>
          <option value="police">👮 Police</option>
          <option value="campus">🏫 Campus Services</option>
          <option value="other">📞 Other</option>
        </select>
      </div>

      <div>
        <label class="block text-xs font-semibold text-gray-500 mb-1">Contact Name <span class="text-red-500">*</span></label>
        <input type="text" id="edit_name" name="ec_name" required
          class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400">
      </div>

      <div>
        <label class="block text-xs font-semibold text-gray-500 mb-1">Phone Number <span class="text-red-500">*</span></label>
        <input type="text" id="edit_number" name="number" required
          class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400">
      </div>

      <div>
        <label class="block text-xs font-semibold text-gray-500 mb-1">Address</label>
        <input type="text" id="edit_address" name="address"
          class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400">
      </div>

      <div class="sm:col-span-2">
        <label class="block text-xs font-semibold text-gray-500 mb-1">Description / Notes</label>
        <input type="text" id="edit_description" name="ec_description"
          class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400">
      </div>

      <!-- Error msg -->
      <div id="editEcError" class="sm:col-span-2 hidden bg-red-50 border border-red-200 text-red-700 rounded-xl px-4 py-2 text-sm"></div>

      <div class="sm:col-span-2 flex gap-3">
        <button type="submit" id="editEcSaveBtn"
          class="flex-1 bg-amber-500 hover:bg-amber-600 text-white font-semibold py-2.5 rounded-xl transition text-sm flex items-center justify-center gap-2">
          <span id="editEcSaveText">💾 Save Changes</span>
          <svg id="editEcSpinner" class="hidden animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
          </svg>
        </button>
        <button type="button" onclick="closeEditModal()"
          class="px-5 py-2.5 rounded-xl border border-gray-200 text-gray-500 hover:bg-gray-50 text-sm font-medium transition">
          Cancel
        </button>
      </div>
    </form>
  </div>
</div>

<!-- INCIDENT DETAIL MODAL -->
<div id="detailModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/60 backdrop-blur-sm p-4">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md max-h-[90vh] overflow-y-auto animate-modal">
    <div class="bg-red-700 text-white px-5 py-4 rounded-t-2xl flex justify-between items-center">
      <h3 class="font-bold text-lg">Incident Details</h3>
      <button onclick="closeDetail()" class="text-white hover:text-red-200 text-2xl leading-none">&times;</button>
    </div>
    <div class="p-5 space-y-3 text-sm" id="detailContent"></div>
  </div>
</div>

<script>
const ACTIVE_TAB = '<?= $active_tab ?>';

// ── Tab switching ──────────────────────────────────────
function switchTab(tab) {
  ['incidents','users','emergency'].forEach(t => {
    document.getElementById('panel-'+t).classList.toggle('hidden', t!==tab);
    const btn = document.getElementById('tab-'+t);
    btn.classList.toggle('active', t===tab);
    btn.classList.toggle('text-gray-500', t!==tab);
  });
  const url = new URL(window.location);
  url.searchParams.set('tab', tab);
  history.replaceState(null,'',url);
}
switchTab(ACTIVE_TAB);

// ── Toast helper ──────────────────────────────────────
function showToast(msg, color='bg-green-600') {
  document.getElementById('toast')?.remove();
  const t = document.createElement('div');
  t.id = 'toast';
  t.className = 'fixed top-4 right-4 z-50 animate-slide';
  t.innerHTML = `<div class="${color} text-white px-5 py-3 rounded-xl shadow-xl flex items-center gap-2 text-sm font-medium">${msg}</div>`;
  document.body.appendChild(t);
  setTimeout(() => t.remove(), 3500);
}

// ── EC category meta (for DOM rebuild) ────────────────
const EC_META = {
  fire:    {label:'Fire Department',    icon:'🔥', badge:'bg-red-100 text-red-700',       dot:'bg-red-500'},
  medical: {label:'Medical / Ambulance',icon:'🚑', badge:'bg-emerald-100 text-emerald-700',dot:'bg-emerald-500'},
  police:  {label:'Police',             icon:'👮', badge:'bg-blue-100 text-blue-700',      dot:'bg-blue-500'},
  campus:  {label:'Campus Services',    icon:'🏫', badge:'bg-amber-100 text-amber-700',    dot:'bg-amber-500'},
  other:   {label:'Other',              icon:'📞', badge:'bg-gray-100 text-gray-600',      dot:'bg-gray-400'},
};

// ── AJAX: Toggle hide/show ─────────────────────────────
async function ajaxToggle(id) {
  const btn    = document.getElementById('toggle-btn-'+id);
  const btnMob = document.getElementById('toggle-btn-mobile-'+id);
  if (btn) btn.disabled = true;
  if (btnMob) btnMob.disabled = true;

  try {
    const fd = new FormData();
    fd.append('action','toggle_emergency');
    fd.append('id', id);
    const res  = await fetch('ec_ajax.php', {method:'POST', body:fd});
    const data = await res.json();

    if (data.success) {
      const active = data.is_active;

      // Update status badge — desktop
      const statusCell = document.getElementById('ec-status-'+id);
      if (statusCell) {
        statusCell.innerHTML = active
          ? `<span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-green-100 text-green-700">● Active</span>`
          : `<span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-gray-100 text-gray-400">● Hidden</span>`;
      }

      // Update status badge — mobile
      const cardStatus = document.getElementById('ec-card-status-'+id);
      if (cardStatus) {
        cardStatus.className = `text-xs font-semibold px-2 py-0.5 rounded-full shrink-0 ${active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-400'}`;
        cardStatus.textContent = active ? 'Active' : 'Hidden';
      }

      // Update toggle button labels
      const label = active ? '🙈 Hide' : '👁 Show';
      if (btn)    btn.textContent    = label;
      if (btnMob) btnMob.textContent = label;

      showToast(active ? '👁 Contact is now visible' : '🙈 Contact hidden', active ? 'bg-green-600' : 'bg-gray-700');
    } else {
      showToast('⚠️ Failed to update', 'bg-red-600');
    }
  } catch(e) {
    showToast('⚠️ Network error', 'bg-red-600');
  }

  if (btn)    btn.disabled = false;
  if (btnMob) btnMob.disabled = false;
}

// ── AJAX: Delete ──────────────────────────────────────
async function ajaxDelete(id, name) {
  if (!confirm(`Delete "${name}"?`)) return;

  try {
    const fd = new FormData();
    fd.append('action','delete_emergency');
    fd.append('id', id);
    const res  = await fetch('ec_ajax.php', {method:'POST', body:fd});
    const data = await res.json();

    if (data.success) {
      // Remove row + mobile card with fade
      ['ec-row-'+id, 'ec-card-'+id].forEach(eid => {
        const el = document.getElementById(eid);
        if (el) { el.style.transition='opacity 0.3s'; el.style.opacity='0'; setTimeout(()=>el.remove(), 300); }
      });
      showToast('🗑 Contact deleted', 'bg-gray-700');
      // Update count
      setTimeout(updateCount, 350);
    } else {
      showToast('⚠️ Failed to delete', 'bg-red-600');
    }
  } catch(e) {
    showToast('⚠️ Network error', 'bg-red-600');
  }
}

function updateCount() {
  const rows = document.querySelectorAll('#ecTableBody tr, #ecCardList > div');
  const c = document.getElementById('ecCount');
  if (c) { const n=rows.length; c.textContent=`${n} contact${n!==1?'s':''} total`; }
}

// ── AJAX: Add new contact ─────────────────────────────
document.getElementById('addEcForm').addEventListener('submit', async function(e) {
  e.preventDefault();
  const btn = document.getElementById('addEcBtn');
  const spinner = document.getElementById('addEcSpinner');
  const btnText = document.getElementById('addEcBtnText');
  btn.disabled = true; spinner.classList.remove('hidden'); btnText.textContent = 'Adding...';

  try {
    const fd = new FormData(this);
    fd.append('action','add_emergency');
    const res  = await fetch('ec_ajax.php', {method:'POST', body:fd});
    const data = await res.json();

    if (data.success && data.contact) {
      appendNewContact(data.contact);
      this.reset();
      showToast('✅ Contact added successfully');
      updateCount();
    } else {
      showToast('⚠️ ' + (data.message||'Failed to add'), 'bg-red-600');
    }
  } catch(e) {
    showToast('⚠️ Network error', 'bg-red-600');
  }

  btn.disabled = false; spinner.classList.add('hidden'); btnText.textContent = '➕ Add Contact';
});

function appendNewContact(ec) {
  const meta = EC_META[ec.category] || EC_META.other;
  const isActive = ec.is_active == 1;

  // Hide empty state if showing
  document.getElementById('ecEmptyState')?.remove();

  // Desktop row
  const tbody = document.getElementById('ecTableBody');
  if (tbody) {
    const tr = document.createElement('tr');
    tr.className = 'ec-row border-b border-gray-50 transition';
    tr.id = 'ec-row-'+ec.id;
    tr.innerHTML = `
      <td class="py-3 pl-2"><span class="inline-block text-xs font-semibold px-2 py-0.5 rounded-full ${meta.badge}">${meta.icon} ${meta.label}</span></td>
      <td class="py-3 font-medium text-gray-700">${escHtml(ec.name)}</td>
      <td class="py-3 mono text-gray-600 font-semibold">${escHtml(ec.number)}</td>
      <td class="py-3 text-gray-400 text-xs">${escHtml(ec.address||'—')}</td>
      <td class="py-3 text-gray-400 text-xs">${escHtml(ec.description||'—')}</td>
      <td class="py-3" id="ec-status-${ec.id}">
        <span class="text-xs font-semibold px-2 py-0.5 rounded-full ${isActive?'bg-green-100 text-green-700':'bg-gray-100 text-gray-400'}">
          ${isActive?'● Active':'● Hidden'}
        </span>
      </td>
      <td class="py-3">
        <div class="flex gap-1">
          <button onclick="openEditModal(${escAttr(JSON.stringify(ec))})" class="text-xs bg-amber-100 hover:bg-amber-200 text-amber-700 px-2 py-1 rounded-lg transition">✏️ Edit</button>
          <button onclick="ajaxToggle(${ec.id})" id="toggle-btn-${ec.id}" class="text-xs bg-blue-100 hover:bg-blue-200 text-blue-600 px-2 py-1 rounded-lg transition">${isActive?'🙈 Hide':'👁 Show'}</button>
          <button onclick="ajaxDelete(${ec.id},'${escHtml(ec.name)}')" class="text-xs bg-red-100 hover:bg-red-200 text-red-600 px-2 py-1 rounded-lg transition">🗑</button>
        </div>
      </td>`;
    tbody.appendChild(tr);
  }

  // Mobile card
  const cardList = document.getElementById('ecCardList');
  if (cardList) {
    const div = document.createElement('div');
    div.className = 'bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden flex';
    div.id = 'ec-card-'+ec.id;
    div.innerHTML = `
      <div class="w-1.5 shrink-0 ${meta.dot}"></div>
      <div class="flex-1 p-4">
        <div class="flex items-start justify-between gap-2 mb-1">
          <div>
            <span class="inline-block text-xs font-semibold px-2 py-0.5 rounded-full ${meta.badge} mb-1">${meta.icon} ${meta.label}</span>
            <p class="font-bold text-gray-800 text-sm">${escHtml(ec.name)}</p>
          </div>
          <span id="ec-card-status-${ec.id}" class="text-xs font-semibold px-2 py-0.5 rounded-full shrink-0 ${isActive?'bg-green-100 text-green-700':'bg-gray-100 text-gray-400'}">${isActive?'Active':'Hidden'}</span>
        </div>
        <p class="mono text-sm font-bold text-gray-700 mb-1">📞 ${escHtml(ec.number)}</p>
        ${ec.address ? `<p class="text-xs text-gray-400">📍 ${escHtml(ec.address)}</p>` : ''}
        ${ec.description ? `<p class="text-xs text-gray-400">${escHtml(ec.description)}</p>` : ''}
        <div class="flex gap-2 mt-3">
          <button onclick="openEditModal(${escAttr(JSON.stringify(ec))})" class="flex-1 text-xs bg-amber-100 hover:bg-amber-200 text-amber-700 py-2 rounded-lg font-medium transition">✏️ Edit</button>
          <button onclick="ajaxToggle(${ec.id})" id="toggle-btn-mobile-${ec.id}" class="flex-1 text-xs bg-blue-100 hover:bg-blue-200 text-blue-600 py-2 rounded-lg font-medium transition">${isActive?'🙈 Hide':'👁 Show'}</button>
          <button onclick="ajaxDelete(${ec.id},'${escHtml(ec.name)}')" class="text-xs bg-red-100 hover:bg-red-200 text-red-600 px-3 py-2 rounded-lg font-medium transition">🗑</button>
        </div>
      </div>`;
    cardList.appendChild(div);
  }
}

function escHtml(s) {
  return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function escAttr(s) {
  return String(s||'').replace(/'/g,'&#39;').replace(/"/g,'&quot;');
}

// ── Edit Modal ────────────────────────────────────────
function openEditModal(ec) {
  document.getElementById('edit_id').value          = ec.id;
  document.getElementById('edit_category').value    = ec.category;
  document.getElementById('edit_name').value        = ec.name;
  document.getElementById('edit_number').value      = ec.number;
  document.getElementById('edit_address').value     = ec.address || '';
  document.getElementById('edit_description').value = ec.description || '';
  document.getElementById('editEcError').classList.add('hidden');
  document.getElementById('editEcSaveText').textContent = '💾 Save Changes';
  document.getElementById('editEcSpinner').classList.add('hidden');
  document.getElementById('editEcSaveBtn').disabled = false;
  const m = document.getElementById('editEcModal');
  m.classList.remove('hidden'); m.classList.add('flex');
}
function closeEditModal() {
  const m = document.getElementById('editEcModal');
  m.classList.add('hidden'); m.classList.remove('flex');
}
document.getElementById('editEcModal').addEventListener('click', function(e){ if(e.target===this) closeEditModal(); });

// ── AJAX: Save edit ───────────────────────────────────
document.getElementById('editEcForm').addEventListener('submit', async function(e) {
  e.preventDefault();
  const btn     = document.getElementById('editEcSaveBtn');
  const spinner = document.getElementById('editEcSpinner');
  const btnText = document.getElementById('editEcSaveText');
  const errDiv  = document.getElementById('editEcError');
  btn.disabled = true; spinner.classList.remove('hidden'); btnText.textContent = 'Saving...';
  errDiv.classList.add('hidden');

  try {
    const fd = new FormData(this);
    fd.append('action','edit_emergency');
    const res  = await fetch('ec_ajax.php', {method:'POST', body:fd});
    const data = await res.json();

    if (data.success && data.contact) {
      updateContactInDOM(data.contact);
      closeEditModal();
      showToast('✅ Contact updated successfully');
    } else {
      errDiv.textContent = data.message || 'Failed to save changes.';
      errDiv.classList.remove('hidden');
      btn.disabled = false; spinner.classList.add('hidden'); btnText.textContent = '💾 Save Changes';
    }
  } catch(err) {
    errDiv.textContent = 'Network error. Please try again.';
    errDiv.classList.remove('hidden');
    btn.disabled = false; spinner.classList.add('hidden'); btnText.textContent = '💾 Save Changes';
  }
});

function updateContactInDOM(ec) {
  const meta = EC_META[ec.category] || EC_META.other;
  const isActive = ec.is_active == 1;

  // Desktop row cells (re-render inner TDs)
  const row = document.getElementById('ec-row-'+ec.id);
  if (row) {
    const tds = row.querySelectorAll('td');
    if (tds[0]) tds[0].innerHTML = `<span class="inline-block text-xs font-semibold px-2 py-0.5 rounded-full ${meta.badge}">${meta.icon} ${meta.label}</span>`;
    if (tds[1]) tds[1].textContent = ec.name;
    if (tds[2]) tds[2].textContent = ec.number;
    if (tds[3]) tds[3].textContent = ec.address || '—';
    if (tds[4]) tds[4].textContent = ec.description || '—';
    // Update edit button data
    const editBtn = row.querySelector('button[onclick^="openEditModal"]');
    if (editBtn) editBtn.setAttribute('onclick', `openEditModal(${escAttr(JSON.stringify(ec))})`);
  }

  // Mobile card — rebuild inner content
  const card = document.getElementById('ec-card-'+ec.id);
  if (card) {
    const inner = card.querySelector('.flex-1');
    if (inner) {
      inner.innerHTML = `
        <div class="flex items-start justify-between gap-2 mb-1">
          <div>
            <span class="inline-block text-xs font-semibold px-2 py-0.5 rounded-full ${meta.badge} mb-1">${meta.icon} ${meta.label}</span>
            <p class="font-bold text-gray-800 text-sm">${escHtml(ec.name)}</p>
          </div>
          <span id="ec-card-status-${ec.id}" class="text-xs font-semibold px-2 py-0.5 rounded-full shrink-0 ${isActive?'bg-green-100 text-green-700':'bg-gray-100 text-gray-400'}">${isActive?'Active':'Hidden'}</span>
        </div>
        <p class="mono text-sm font-bold text-gray-700 mb-1">📞 ${escHtml(ec.number)}</p>
        ${ec.address ? `<p class="text-xs text-gray-400">📍 ${escHtml(ec.address)}</p>` : ''}
        ${ec.description ? `<p class="text-xs text-gray-400">${escHtml(ec.description)}</p>` : ''}
        <div class="flex gap-2 mt-3">
          <button onclick="openEditModal(${escAttr(JSON.stringify(ec))})" class="flex-1 text-xs bg-amber-100 hover:bg-amber-200 text-amber-700 py-2 rounded-lg font-medium transition">✏️ Edit</button>
          <button onclick="ajaxToggle(${ec.id})" id="toggle-btn-mobile-${ec.id}" class="flex-1 text-xs bg-blue-100 hover:bg-blue-200 text-blue-600 py-2 rounded-lg font-medium transition">${isActive?'🙈 Hide':'👁 Show'}</button>
          <button onclick="ajaxDelete(${ec.id},'${escHtml(ec.name)}')" class="text-xs bg-red-100 hover:bg-red-200 text-red-600 px-3 py-2 rounded-lg font-medium transition">🗑</button>
        </div>`;
      // Also update the color dot
      const dot = card.querySelector('.w-1\\.5');
      if (dot) dot.className = `w-1.5 shrink-0 ${meta.dot}`;
    }
  }
}

// ── Incident filters ──────────────────────────────────
function filterIncidents() {
  const q   = document.getElementById('incidentSearch').value.toLowerCase();
  const sev = document.getElementById('severityFilter').value;
  const sta = document.getElementById('statusFilter').value;
  let visible = 0;
  document.querySelectorAll('#incidentTbody tr').forEach(row => {
    const m = matchEl(row,q,sev,sta); row.style.display=m?'':'none'; if(m)visible++;
  });
  document.querySelectorAll('#incidentCards > div').forEach(card => {
    card.style.display = matchEl(card,q,sev,sta)?'':'none';
  });
  const c = document.getElementById('incidentCount');
  if(c) c.textContent=`Showing ${visible} incident${visible!==1?'s':''}`;
}
function matchEl(el,q,sev,sta) {
  if(q && !['type','location','reporter'].some(k=>(el.dataset[k]||'').toLowerCase().includes(q))) return false;
  if(sev && (el.dataset.severity||'')!==sev) return false;
  if(sta && (el.dataset.status||'')!==sta) return false;
  return true;
}
function filterUsers() {
  const q = document.getElementById('userSearch').value.toLowerCase();
  document.querySelectorAll('#userTbody tr, #userCards > div').forEach(el => {
    el.style.display = (!q||['name','email','role'].some(k=>(el.dataset[k]||'').toLowerCase().includes(q)))?'':'none';
  });
}

// ── Incident detail modal ─────────────────────────────
function showDetail(inc) {
  const sev={low:'🟢',medium:'🟡',high:'🔴',critical:'🚨'};
  const sta={open:'🔴 Open',in_progress:'🔵 In Progress',resolved:'✅ Resolved'};
  const icons={fire:'🔥',medical:'🏥',accident:'⚠️',suspicious:'👁️',theft:'🔓',flooding:'🌊',earthquake:'🌍',other:'📋'};
  let html=`<div class="grid grid-cols-2 gap-3">
    <div class="col-span-2 bg-gray-50 rounded-xl p-3"><p class="text-xs text-gray-400 uppercase tracking-wider mb-1">Type</p>
      <p class="font-semibold text-gray-800">${icons[inc.incident_type]||'📋'} ${inc.incident_type.replace(/_/g,' ').replace(/\b\w/g,c=>c.toUpperCase())}</p></div>
    <div class="bg-gray-50 rounded-xl p-3"><p class="text-xs text-gray-400 uppercase tracking-wider mb-1">Severity</p>
      <p class="font-semibold">${sev[inc.severity]||''} ${inc.severity.charAt(0).toUpperCase()+inc.severity.slice(1)}</p></div>
    <div class="bg-gray-50 rounded-xl p-3"><p class="text-xs text-gray-400 uppercase tracking-wider mb-1">Status</p>
      <p class="font-semibold">${sta[inc.status]||inc.status}</p></div>
    <div class="col-span-2 bg-gray-50 rounded-xl p-3"><p class="text-xs text-gray-400 uppercase tracking-wider mb-1">Location</p>
      <p class="font-semibold">📍 ${inc.location.replace(/_/g,' ').replace(/\b\w/g,c=>c.toUpperCase())}</p></div>
    <div class="col-span-2 bg-gray-50 rounded-xl p-3"><p class="text-xs text-gray-400 uppercase tracking-wider mb-1">Description</p>
      <p class="text-gray-700 leading-relaxed">${inc.description}</p></div>
    <div class="bg-gray-50 rounded-xl p-3"><p class="text-xs text-gray-400 uppercase tracking-wider mb-1">Reported By</p>
      <p class="font-semibold">👤 ${inc.reported_by}</p></div>
    <div class="bg-gray-50 rounded-xl p-3"><p class="text-xs text-gray-400 uppercase tracking-wider mb-1">Report #</p>
      <p class="font-semibold mono text-gray-500">#${inc.id}</p></div>
    <div class="col-span-2 bg-gray-50 rounded-xl p-3"><p class="text-xs text-gray-400 uppercase tracking-wider mb-1">Time Reported</p>
      <p class="font-semibold mono text-gray-600">${inc.reported_at}</p></div>`;
  if(inc.photo_path) html+=`<div class="col-span-2"><p class="text-xs text-gray-400 uppercase tracking-wider mb-2">Photo Evidence</p>
    <img src="${inc.photo_path}" class="rounded-xl w-full object-cover max-h-48 border border-gray-100" alt="Evidence"></div>`;
  html+=`</div>`;
  document.getElementById('detailContent').innerHTML=html;
  const m=document.getElementById('detailModal');
  m.classList.remove('hidden'); m.classList.add('flex');
}
function closeDetail() {
  const m=document.getElementById('detailModal');
  m.classList.add('hidden'); m.classList.remove('flex');
}
document.getElementById('detailModal').addEventListener('click',function(e){if(e.target===this)closeDetail();});
</script>
</body>
</html>