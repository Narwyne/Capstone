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

$toast   = '';
$errors  = [];
$user    = null;
$my_incidents = [];

// ── Fetch current user ────────────────────────────────────────────
if ($pdo) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
}

if (!$user) {
    header("Location: logout.php");
    exit();
}

// ── Fetch this user's incident reports ────────────────────────────
if ($pdo) {
    $stmt = $pdo->prepare("
        SELECT id, incident_type, severity, location, status, reported_at
        FROM incidents
        WHERE reported_by = ?
        ORDER BY reported_at DESC
        LIMIT 10
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $my_incidents = $stmt->fetchAll();
}

// ── Handle POST ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo) {
    $action = $_POST['action'] ?? '';

    // ── Save profile info ──
    if ($action === 'save_profile') {
        $first_name  = trim($_POST['first_name']  ?? '');
        $middle_name = trim($_POST['middle_name'] ?? '');
        $last_name   = trim($_POST['last_name']   ?? '');
        $phone       = trim($_POST['phone']       ?? '');
        $department  = trim($_POST['department']  ?? '');
        $student_id  = trim($_POST['student_id']  ?? '');
        $bio         = trim($_POST['bio']         ?? '');

        if (empty($first_name) || empty($last_name)) {
            $errors[] = 'First name and last name are required.';
        } else {
            // Build display name: First [M.] Last
            $name = $first_name;
            if ($middle_name) $name .= ' ' . strtoupper($middle_name[0]) . '.';
            $name .= ' ' . $last_name;
            $name = trim($name);

            $pdo->prepare("
                UPDATE users
                SET name=?, first_name=?, middle_name=?, last_name=?,
                    phone=?, department=?, student_id=?, bio=?
                WHERE id=?
            ")->execute([$name, $first_name, $middle_name, $last_name,
                         $phone, $department, $student_id, $bio, $user['id']]);

            $_SESSION['user'] = explode(' ', $name)[0]; // keep first-name-only session
            $toast = 'profile_saved';

            // Refresh user data
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$user['id']]);
            $user = $stmt->fetch();
        }
    }

    // ── Save settings ──
    if ($action === 'save_settings') {
        $notif_email = isset($_POST['notif_email']) ? 1 : 0;
        $notif_sms   = isset($_POST['notif_sms'])   ? 1 : 0;
        $theme       = $_POST['theme'] ?? 'light';

        $pdo->prepare("UPDATE users SET notif_email=?, notif_sms=?, theme=? WHERE id=?")
            ->execute([$notif_email, $notif_sms, $theme, $user['id']]);

        $toast = 'settings_saved';

        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$user['id']]);
        $user = $stmt->fetch();
    }

    // ── Change password ──
    if ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password']     ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (!password_verify($current, $user['password'])) {
            $errors[] = 'Current password is incorrect.';
        } elseif (strlen($new) < 8) {
            $errors[] = 'New password must be at least 8 characters.';
        } elseif ($new !== $confirm) {
            $errors[] = 'New passwords do not match.';
        } else {
            $pdo->prepare("UPDATE users SET password=? WHERE id=?")
                ->execute([password_hash($new, PASSWORD_DEFAULT), $user['id']]);
            $toast = 'password_changed';
        }
    }

    // ── Avatar upload ──
    if ($action === 'upload_avatar' && isset($_FILES['avatar'])) {
        $file = $_FILES['avatar'];
        $allowed_mime = ['image/jpeg','image/png','image/gif','image/webp'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mime, $allowed_mime)) {
            $errors[] = 'Invalid file type. Only JPG, PNG, GIF, WEBP allowed.';
        } elseif ($file['size'] > 2 * 1024 * 1024) {
            $errors[] = 'Avatar must be under 2MB.';
        } else {
            $dir = 'uploads/avatars/';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            $ext  = pathinfo($file['name'], PATHINFO_EXTENSION);
            $dest = $dir . 'user_' . $user['id'] . '.' . strtolower($ext);
            if (move_uploaded_file($file['tmp_name'], $dest)) {
                $pdo->prepare("UPDATE users SET avatar=? WHERE id=?")->execute([$dest, $user['id']]);
                $user['avatar'] = $dest;
                $toast = 'avatar_saved';
            }
        }
    }
}

// ── Helpers ───────────────────────────────────────────────────────
function typeIcon($t) {
    return ['fire'=>'🔥','medical'=>'🏥','accident'=>'⚠️','suspicious'=>'👁️',
            'theft'=>'🔓','flooding'=>'🌊','earthquake'=>'🌍','other'=>'📋'][$t] ?? '📋';
}
function timeAgo($dt) {
    $d = time() - strtotime($dt);
    if ($d < 60)    return 'Just now';
    if ($d < 3600)  return floor($d/60)  . ' min ago';
    if ($d < 86400) return floor($d/3600) . ' hr ago';
    return floor($d/86400) . 'd ago';
}

$initials = strtoupper(implode('', array_map(fn($w) => $w[0], explode(' ', trim($user['name'])))));
$initials = substr($initials, 0, 2);
$memberSince = isset($user['created_at']) ? date('F Y', strtotime($user['created_at'])) : 'Unknown';
$incidentCount = count($my_incidents);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Profile — ACLC Smart Campus</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=DM+Mono:wght@500&display=swap" rel="stylesheet">
  <style>
    body { font-family:'DM Sans',sans-serif; }
    .mono { font-family:'DM Mono',monospace; }
    @keyframes fadeUp { from{opacity:0;transform:translateY(14px);}to{opacity:1;transform:translateY(0);} }
    .anim { animation:fadeUp 0.4s ease both; }
    body { background-color:#f3f4f6; background-image:radial-gradient(circle at 1px 1px,rgba(0,0,0,0.04) 1px,transparent 0); background-size:24px 24px; }
    .tab-btn.active { background:#b91c1c; color:#fff; }
    input:focus, select:focus, textarea:focus { outline:none; box-shadow:0 0 0 2px #fca5a5; }
    .toggle-track { transition:background 0.2s; }
    .toggle-thumb { transition:transform 0.2s; }
    input[type=checkbox]:checked + .toggle-track { background:#b91c1c; }
    input[type=checkbox]:checked + .toggle-track .toggle-thumb { transform:translateX(20px); }
  </style>
</head>
<body class="min-h-screen">

<!-- NAVBAR -->
<nav class="bg-red-700 text-white sticky top-0 z-40 shadow-lg">
  <div class="max-w-4xl mx-auto px-4 py-3 flex items-center justify-between">
    <div>
      <span class="text-xs text-red-300 uppercase tracking-widest block leading-none">ACLC Smart Campus</span>
      <span class="font-bold text-lg leading-tight">Profile & Settings</span>
    </div>
    <a href="dashboard.php" class="bg-white text-red-700 hover:bg-red-50 px-3 py-1.5 rounded-lg text-sm font-semibold transition">
      ← Dashboard
    </a>
  </div>
</nav>

<!-- TOAST -->
<?php
$toasts = [
  'profile_saved'   => ['✅', 'Profile updated successfully',   'bg-green-600'],
  'settings_saved'  => ['✅', 'Settings saved',                  'bg-green-600'],
  'password_changed'=> ['🔐', 'Password changed successfully',   'bg-blue-600'],
  'avatar_saved'    => ['🖼️', 'Avatar updated',                  'bg-green-600'],
];
if ($toast && isset($toasts[$toast])):
  [$ti, $tm, $tc] = $toasts[$toast];
?>
<div id="toast" class="fixed top-4 right-4 z-50">
  <div class="<?= $tc ?> text-white px-5 py-3 rounded-xl shadow-xl flex items-center gap-2 text-sm font-medium">
    <?= $ti ?> <?= $tm ?>
  </div>
</div>
<script>setTimeout(()=>document.getElementById('toast')?.remove(), 3500)</script>
<?php endif; ?>

<div class="max-w-4xl mx-auto px-4 py-6 space-y-5">

  <!-- ERROR BANNER -->
  <?php if (!empty($errors)): ?>
  <div class="bg-red-50 border border-red-200 rounded-2xl px-5 py-3 text-sm text-red-700 anim">
    <?php foreach($errors as $e): ?><p>⚠️ <?= htmlspecialchars($e) ?></p><?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- PROFILE CARD -->
  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 flex flex-col sm:flex-row items-center sm:items-start gap-5 anim" style="animation-delay:0.05s">

    <!-- Avatar -->
    <div class="relative shrink-0">
      <?php if (!empty($user['avatar']) && file_exists($user['avatar'])): ?>
        <img src="<?= htmlspecialchars($user['avatar']) ?>" alt="Avatar"
          class="w-24 h-24 rounded-2xl object-cover shadow-md border-4 border-white ring-2 ring-red-200">
      <?php else: ?>
        <div class="w-24 h-24 rounded-2xl bg-gradient-to-br from-red-500 to-red-700 flex items-center justify-center shadow-md text-white text-3xl font-bold">
          <?= htmlspecialchars($initials) ?>
        </div>
      <?php endif; ?>
      <!-- Upload trigger -->
      <form method="POST" enctype="multipart/form-data" id="avatarForm">
        <input type="hidden" name="action" value="upload_avatar">
        <input type="file" name="avatar" id="avatarInput" accept="image/*" class="hidden"
          onchange="document.getElementById('avatarForm').submit()">
        <button type="button" onclick="document.getElementById('avatarInput').click()"
          class="absolute -bottom-2 -right-2 bg-red-600 hover:bg-red-700 text-white w-8 h-8 rounded-xl flex items-center justify-center shadow-md transition text-sm">
          📷
        </button>
      </form>
    </div>

    <!-- Info -->
    <div class="flex-1 text-center sm:text-left">
      <h2 class="text-xl font-bold text-gray-800"><?= htmlspecialchars($user['name']) ?></h2>
      <p class="text-sm text-gray-400"><?= htmlspecialchars($user['email'] ?? '') ?></p>
      <?php if (!empty($user['department'])): ?>
      <p class="text-sm text-gray-500 mt-0.5">🎓 <?= htmlspecialchars($user['department']) ?></p>
      <?php endif; ?>
      <?php if (!empty($user['bio'])): ?>
      <p class="text-sm text-gray-500 mt-2 italic">"<?= htmlspecialchars($user['bio']) ?>"</p>
      <?php endif; ?>

      <div class="flex flex-wrap gap-3 mt-3 justify-center sm:justify-start">
        <span class="inline-flex items-center gap-1 text-xs font-semibold px-2.5 py-1 rounded-full <?= $user['role']==='admin' ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-600' ?>">
          <?= $user['role']==='admin' ? '🛡 Admin' : '👤 User' ?>
        </span>
        <span class="inline-flex items-center gap-1 text-xs text-gray-400">
          📅 Member since <?= $memberSince ?>
        </span>
        <span class="inline-flex items-center gap-1 text-xs text-gray-400">
          📋 <?= $incidentCount ?> report<?= $incidentCount!==1?'s':'' ?> submitted
        </span>
      </div>
    </div>

  </div>

  <!-- TABS -->
  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden anim" style="animation-delay:0.1s">

    <div class="flex border-b border-gray-100 p-1 gap-1 bg-gray-50">
      <button onclick="switchTab('profile')"  id="tab-profile"  class="tab-btn active flex-1 py-2.5 rounded-xl text-sm font-semibold transition">👤 Profile</button>
      <button onclick="switchTab('settings')" id="tab-settings" class="tab-btn flex-1 py-2.5 rounded-xl text-sm font-semibold text-gray-500 hover:text-gray-700 transition">⚙️ Settings</button>
      <button onclick="switchTab('security')" id="tab-security" class="tab-btn flex-1 py-2.5 rounded-xl text-sm font-semibold text-gray-500 hover:text-gray-700 transition">🔐 Security</button>
      <button onclick="switchTab('activity')" id="tab-activity" class="tab-btn flex-1 py-2.5 rounded-xl text-sm font-semibold text-gray-500 hover:text-gray-700 transition">📋 Activity</button>
    </div>

    <!-- ── PROFILE TAB ── -->
    <div id="panel-profile" class="p-5">
      <form method="POST" class="space-y-4">
        <input type="hidden" name="action" value="save_profile">

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

          <!-- Name fields -->
          <div class="sm:col-span-2">
            <p class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">
              Full Name
            </p>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
              <div>
                <input type="text" name="first_name" required
                  value="<?= htmlspecialchars($user['first_name'] ?? '') ?>"
                  placeholder="First Name"
                  class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-300">
                <p class="text-xs text-gray-400 mt-1 text-center">First <span class="text-red-500">*</span></p>
              </div>
              <div>
                <input type="text" name="middle_name"
                  value="<?= htmlspecialchars($user['middle_name'] ?? '') ?>"
                  placeholder="Middle Name"
                  class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-300">
                <p class="text-xs text-gray-400 mt-1 text-center">Middle <span class="text-gray-300">(optional)</span></p>
              </div>
              <div>
                <input type="text" name="last_name" required
                  value="<?= htmlspecialchars($user['last_name'] ?? '') ?>"
                  placeholder="Last Name"
                  class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-300">
                <p class="text-xs text-gray-400 mt-1 text-center">Last <span class="text-red-500">*</span></p>
              </div>
            </div>
            <!-- Live preview -->
            <div id="namePreview" class="mt-2 text-xs text-gray-400 text-center <?= ($user['first_name'] || $user['last_name']) ? '' : 'hidden' ?>">
              Will appear as: <span id="namePreviewText" class="font-semibold text-gray-700">
                <?= htmlspecialchars($user['name'] ?? '') ?>
              </span>
            </div>
          </div>

          <div>
            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Email</label>
            <input type="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" disabled
              class="w-full border border-gray-100 bg-gray-50 rounded-xl px-4 py-2.5 text-sm text-gray-400 cursor-not-allowed">
            <p class="text-xs text-gray-400 mt-1">Email cannot be changed here.</p>
          </div>

          <div>
            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Phone Number</label>
            <input type="text" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>"
              placeholder="e.g. 0917-123-4567"
              class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm">
          </div>

          <div>
            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Department / Course</label>
            <input type="text" name="department" value="<?= htmlspecialchars($user['department'] ?? '') ?>"
              placeholder="e.g. BSIT, BSCS"
              class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm">
          </div>

          <div>
            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Student / Employee ID</label>
            <input type="text" name="student_id" value="<?= htmlspecialchars($user['student_id'] ?? '') ?>"
              placeholder="e.g. 2024-00123"
              class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm">
          </div>

          <div class="sm:col-span-2">
            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Bio</label>
            <textarea name="bio" rows="3" maxlength="200"
              placeholder="A short description about yourself..."
              class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm resize-none"><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
            <p class="text-xs text-gray-400 mt-1">Max 200 characters.</p>
          </div>

        </div>

        <div class="flex justify-end pt-2">
          <button type="submit"
            class="bg-red-700 hover:bg-red-800 text-white font-semibold px-6 py-2.5 rounded-xl transition text-sm">
            💾 Save Profile
          </button>
        </div>
      </form>
    </div>

    <!-- ── SETTINGS TAB ── -->
    <div id="panel-settings" class="p-5 hidden">
      <form method="POST" class="space-y-5">
        <input type="hidden" name="action" value="save_settings">

        <!-- Notifications -->
        <div>
          <h3 class="text-sm font-bold text-gray-700 mb-3">🔔 Notifications</h3>
          <div class="space-y-3">

            <label class="flex items-center justify-between bg-gray-50 rounded-xl px-4 py-3 cursor-pointer">
              <div>
                <p class="text-sm font-medium text-gray-700">Email Notifications</p>
                <p class="text-xs text-gray-400">Receive incident alerts via email</p>
              </div>
              <div class="relative">
                <input type="checkbox" name="notif_email" id="notif_email" class="sr-only"
                  <?= ($user['notif_email'] ?? 1) ? 'checked' : '' ?>>
                <div class="toggle-track w-11 h-6 bg-gray-300 rounded-full relative" onclick="toggleSwitch('notif_email')">
                  <div class="toggle-thumb absolute top-1 left-1 w-4 h-4 bg-white rounded-full shadow transition-transform
                    <?= ($user['notif_email'] ?? 1) ? 'translate-x-5 !bg-white' : '' ?>"></div>
                </div>
              </div>
            </label>

            <label class="flex items-center justify-between bg-gray-50 rounded-xl px-4 py-3 cursor-pointer">
              <div>
                <p class="text-sm font-medium text-gray-700">SMS Notifications</p>
                <p class="text-xs text-gray-400">Receive alerts via text message</p>
              </div>
              <div class="relative">
                <input type="checkbox" name="notif_sms" id="notif_sms" class="sr-only"
                  <?= ($user['notif_sms'] ?? 0) ? 'checked' : '' ?>>
                <div class="toggle-track w-11 h-6 bg-gray-300 rounded-full relative" onclick="toggleSwitch('notif_sms')">
                  <div class="toggle-thumb absolute top-1 left-1 w-4 h-4 bg-white rounded-full shadow transition-transform
                    <?= ($user['notif_sms'] ?? 0) ? 'translate-x-5' : '' ?>"></div>
                </div>
              </div>
            </label>

          </div>
        </div>

        <!-- Theme -->
        <div>
          <h3 class="text-sm font-bold text-gray-700 mb-3">🎨 Appearance</h3>
          <div class="grid grid-cols-2 gap-3">
            <label class="theme-opt cursor-pointer">
              <input type="radio" name="theme" value="light" class="sr-only"
                <?= ($user['theme'] ?? 'light') === 'light' ? 'checked' : '' ?>>
              <div class="theme-card border-2 rounded-xl p-4 text-center transition hover:border-red-300
                <?= ($user['theme'] ?? 'light') === 'light' ? 'border-red-500 bg-red-50' : 'border-gray-200' ?>">
                <div class="text-2xl mb-1">☀️</div>
                <p class="text-sm font-semibold text-gray-700">Light</p>
              </div>
            </label>
            <label class="theme-opt cursor-pointer">
              <input type="radio" name="theme" value="dark" class="sr-only"
                <?= ($user['theme'] ?? 'light') === 'dark' ? 'checked' : '' ?>>
              <div class="theme-card border-2 rounded-xl p-4 text-center transition hover:border-red-300
                <?= ($user['theme'] ?? 'light') === 'dark' ? 'border-red-500 bg-red-50' : 'border-gray-200' ?>">
                <div class="text-2xl mb-1">🌙</div>
                <p class="text-sm font-semibold text-gray-700">Dark</p>
                <p class="text-xs text-gray-400">Coming soon</p>
              </div>
            </label>
          </div>
        </div>

        <div class="flex justify-end pt-2">
          <button type="submit"
            class="bg-red-700 hover:bg-red-800 text-white font-semibold px-6 py-2.5 rounded-xl transition text-sm">
            💾 Save Settings
          </button>
        </div>
      </form>
    </div>

    <!-- ── SECURITY TAB ── -->
    <div id="panel-security" class="p-5 hidden">
      <form method="POST" class="space-y-4">
        <input type="hidden" name="action" value="change_password">

        <div class="bg-amber-50 border border-amber-200 rounded-xl px-4 py-3 text-sm text-amber-700 flex gap-2 items-start">
          <span class="shrink-0">⚠️</span>
          <p>Choose a strong password with at least 8 characters, including numbers and symbols.</p>
        </div>

        <div>
          <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Current Password</label>
          <div class="relative">
            <input type="password" name="current_password" id="cur_pw" required
              class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm pr-10">
            <button type="button" onclick="togglePw('cur_pw', this)"
              class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 text-sm">👁</button>
          </div>
        </div>

        <div>
          <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">New Password</label>
          <div class="relative">
            <input type="password" name="new_password" id="new_pw" required minlength="8"
              oninput="checkStrength(this.value)"
              class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm pr-10">
            <button type="button" onclick="togglePw('new_pw', this)"
              class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 text-sm">👁</button>
          </div>
          <!-- Strength bar -->
          <div class="mt-2 h-1.5 bg-gray-100 rounded-full overflow-hidden">
            <div id="strengthBar" class="h-full rounded-full transition-all duration-300 w-0"></div>
          </div>
          <p id="strengthLabel" class="text-xs text-gray-400 mt-1"></p>
        </div>

        <div>
          <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Confirm New Password</label>
          <div class="relative">
            <input type="password" name="confirm_password" id="con_pw" required
              class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm pr-10">
            <button type="button" onclick="togglePw('con_pw', this)"
              class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 text-sm">👁</button>
          </div>
        </div>

        <div class="flex justify-end pt-2">
          <button type="submit"
            class="bg-red-700 hover:bg-red-800 text-white font-semibold px-6 py-2.5 rounded-xl transition text-sm">
            🔐 Update Password
          </button>
        </div>
      </form>
    </div>

    <!-- ── ACTIVITY TAB ── -->
    <div id="panel-activity" class="p-5 hidden">
      <h3 class="text-sm font-bold text-gray-700 mb-4">📋 My Submitted Reports</h3>

      <?php if (empty($my_incidents)): ?>
      <div class="text-center py-12 text-gray-400">
        <div class="text-4xl mb-2">📭</div>
        <p class="text-sm font-medium">No reports submitted yet.</p>
      </div>
      <?php else: ?>
      <div class="space-y-2">
        <?php foreach ($my_incidents as $inc):
          $sevBadge = ['low'=>'bg-emerald-100 text-emerald-700','medium'=>'bg-amber-100 text-amber-700',
                       'high'=>'bg-orange-100 text-orange-700','critical'=>'bg-red-100 text-red-700'][$inc['severity']] ?? 'bg-gray-100 text-gray-600';
          $staBadge = ['open'=>'bg-red-100 text-red-600','in_progress'=>'bg-blue-100 text-blue-600',
                       'resolved'=>'bg-green-100 text-green-600'][$inc['status']] ?? 'bg-gray-100 text-gray-600';
          $staLabel = ['open'=>'Open','in_progress'=>'In Progress','resolved'=>'Resolved'][$inc['status']] ?? $inc['status'];
          $border   = ['low'=>'border-emerald-300','medium'=>'border-amber-300',
                       'high'=>'border-orange-400','critical'=>'border-red-500'][$inc['severity']] ?? 'border-gray-200';
        ?>
        <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-xl border-l-4 <?= $border ?>">
          <span class="text-xl shrink-0"><?= typeIcon($inc['incident_type']) ?></span>
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 flex-wrap">
              <span class="text-sm font-semibold text-gray-800">
                <?= ucfirst(str_replace('_',' ',$inc['incident_type'])) ?>
              </span>
              <span class="text-xs font-semibold px-2 py-0.5 rounded-full <?= $sevBadge ?>"><?= ucfirst($inc['severity']) ?></span>
              <span class="text-xs font-semibold px-2 py-0.5 rounded-full <?= $staBadge ?>"><?= $staLabel ?></span>
            </div>
            <p class="text-xs text-gray-400 mt-0.5">
              📍 <?= htmlspecialchars(ucwords(str_replace('_',' ',$inc['location']))) ?>
            </p>
          </div>
          <span class="text-xs text-gray-400 mono shrink-0"><?= timeAgo($inc['reported_at']) ?></span>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

  </div>

</div>

<script>
// Live name preview on profile tab
function updateNamePreview() {
  const f  = document.querySelector('[name=first_name]')?.value.trim()  || '';
  const m  = document.querySelector('[name=middle_name]')?.value.trim() || '';
  const l  = document.querySelector('[name=last_name]')?.value.trim()   || '';
  const el = document.getElementById('namePreview');
  const tx = document.getElementById('namePreviewText');
  if (!el || !tx) return;
  if (f || l) {
    let full = f;
    if (m) full += ' ' + m.charAt(0).toUpperCase() + '.';
    if (l) full += ' ' + l;
    tx.textContent = full.trim();
    el.classList.remove('hidden');
  } else {
    el.classList.add('hidden');
  }
}
['first_name','middle_name','last_name'].forEach(n => {
  document.querySelector(`[name=${n}]`)?.addEventListener('input', updateNamePreview);
});
</script>
<script src="js/profile.js"></script>
</body>
</html>