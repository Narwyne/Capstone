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
} catch (PDOException $e) { /* silently fall back to static */ }

// ── Fetch grouped services from DB ──────────────────────
$grouped = [];
if ($pdo) {
    $rows = $pdo->query("
        SELECT * FROM emergency_services
        WHERE is_active = 1
        ORDER BY category, sort_order, id
    ")->fetchAll();

    foreach ($rows as $row) {
        $grouped[$row['category']][] = $row;
    }
}

// ── Fallback if DB empty / unavailable ──────────────────
if (empty($grouped)) {
    $grouped = [
        'fire'    => [['name'=>'Bureau of Fire Protection','number'=>'160','address'=>null,'description'=>'National fire emergency hotline']],
        'medical' => [['name'=>'National Emergency Hotline','number'=>'911','address'=>null,'description'=>'All-in-one emergency dispatch']],
        'police'  => [['name'=>'PNP Emergency Hotline','number'=>'117','address'=>null,'description'=>'Philippine National Police']],
        'campus'  => [['name'=>'ACLC Campus Security','number'=>'0917-000-0001','address'=>'Main Gate','description'=>'On-duty 24/7']],
    ];
}

// ── Category meta ────────────────────────────────────────
$categoryMeta = [
    'fire'    => ['label'=>'Fire Department',   'icon'=>'🔥', 'bg'=>'bg-red-600',    'light'=>'bg-red-50',   'border'=>'border-red-200',  'badge'=>'bg-red-100 text-red-700',   'ring'=>'ring-red-400'],
    'medical' => ['label'=>'Medical / Ambulance','icon'=>'🚑', 'bg'=>'bg-emerald-600','light'=>'bg-emerald-50','border'=>'border-emerald-200','badge'=>'bg-emerald-100 text-emerald-700','ring'=>'ring-emerald-400'],
    'police'  => ['label'=>'Police',            'icon'=>'👮', 'bg'=>'bg-blue-600',   'light'=>'bg-blue-50',  'border'=>'border-blue-200',  'badge'=>'bg-blue-100 text-blue-700', 'ring'=>'ring-blue-400'],
    'campus'  => ['label'=>'Campus Services',   'icon'=>'🏫', 'bg'=>'bg-amber-500',  'light'=>'bg-amber-50', 'border'=>'border-amber-200', 'badge'=>'bg-amber-100 text-amber-700','ring'=>'ring-amber-400'],
    'other'   => ['label'=>'Other Services',    'icon'=>'📞', 'bg'=>'bg-gray-600',   'light'=>'bg-gray-50',  'border'=>'border-gray-200',  'badge'=>'bg-gray-100 text-gray-700', 'ring'=>'ring-gray-400'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Emergency Services — ACLC Smart Campus</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=DM+Mono:wght@500&display=swap" rel="stylesheet">

  <style>
    body { font-family: 'DM Sans', sans-serif; }
    .mono { font-family: 'DM Mono', monospace; }

    @keyframes fadeUp {
      from { opacity:0; transform:translateY(16px); }
      to   { opacity:1; transform:translateY(0); }
    }
    .anim { animation: fadeUp 0.4s ease both; }

    body {
      background-color: #f3f4f6;
      background-image: radial-gradient(circle at 1px 1px, rgba(0,0,0,0.04) 1px, transparent 0);
      background-size: 24px 24px;
    }

    /* Call button pulse on mobile tap */
    .call-btn:active { transform: scale(0.97); }
    .call-btn {
      transition: transform 0.1s ease, box-shadow 0.15s ease;
    }
    .call-btn:hover {
      transform: translateY(-1px);
      box-shadow: 0 6px 20px rgba(0,0,0,0.13);
    }

    /* Big emergency SOS button */
    @keyframes pulse-ring {
      0%   { transform: scale(1);    opacity: 0.6; }
      100% { transform: scale(1.55); opacity: 0; }
    }
    .sos-ring::before, .sos-ring::after {
      content: '';
      position: absolute;
      inset: 0;
      border-radius: 9999px;
      background: #dc2626;
      animation: pulse-ring 1.8s ease-out infinite;
    }
    .sos-ring::after { animation-delay: 0.9s; }
  </style>
</head>

<body class="min-h-screen">

<!-- NAVBAR -->
<nav class="bg-red-700 text-white sticky top-0 z-40 shadow-lg">
  <div class="max-w-3xl mx-auto px-4 py-3 flex items-center justify-between">
    <div>
      <span class="text-xs text-red-300 uppercase tracking-widest block leading-none">ACLC Smart Campus</span>
      <span class="font-bold text-lg leading-tight">Emergency Services</span>
    </div>
    <a href="dashboard.php"
       class="bg-white text-red-700 hover:bg-red-50 px-3 py-1.5 rounded-lg text-sm font-semibold transition">
      ← Back
    </a>
  </div>
</nav>

<div class="max-w-3xl mx-auto px-4 py-6 space-y-6">

  <!-- SOS BANNER -->

  <!-- <div class="anim bg-red-700 rounded-2xl p-6 text-white text-center shadow-xl relative overflow-hidden"
       style="animation-delay:0.05s"> -->
    <!-- decorative circles -->
    <!-- <div class="absolute -top-8 -right-8 w-36 h-36 bg-red-600 rounded-full opacity-40"></div>
    <div class="absolute -bottom-6 -left-6 w-24 h-24 bg-red-800 rounded-full opacity-40"></div>

    <div class="relative z-10">

      <p class="text-red-200 text-xs uppercase tracking-widest mb-1">In immediate danger?</p>
      <h2 class="text-2xl font-bold mb-4">Call National Emergency</h2> -->

      <!-- Big 911 button -->

      <!-- <div class="relative inline-block">
        <div class="sos-ring absolute inset-0 rounded-full z-0"></div>
        <a href="tel:911"
           class="relative z-10 inline-flex items-center gap-3 bg-white text-red-700 font-black text-2xl px-10 py-4 rounded-full shadow-2xl hover:bg-red-50 transition">
          📞 <span>911</span>
        </a>
      </div>
      <p class="text-red-200 text-xs mt-4">National Emergency Hotline · Dispatches police, fire & ambulance</p>
    </div>
  </div> -->

  <!-- DISCLAIMER -->
  <div class="anim bg-amber-50 border border-amber-200 rounded-2xl px-5 py-3 flex gap-3 items-start text-sm text-amber-800"
       style="animation-delay:0.1s">
    <span class="text-xl shrink-0">⚠️</span>
    <p>Numbers may vary by region. Please confirm your local emergency hotlines with your barangay or LGU.</p>
  </div>

  <!-- SERVICE CATEGORIES -->
  <?php
  $delay = 0.15;
  foreach ($grouped as $category => $services):
    $meta = $categoryMeta[$category] ?? $categoryMeta['other'];
  ?>

  <div class="anim" style="animation-delay:<?= $delay ?>s">
    <!-- Category header -->
    <div class="flex items-center gap-3 mb-3">
      <div class="<?= $meta['bg'] ?> text-white rounded-xl w-9 h-9 flex items-center justify-center text-lg shadow-sm shrink-0">
        <?= $meta['icon'] ?>
      </div>
      <h3 class="font-bold text-gray-800 text-base"><?= $meta['label'] ?></h3>
      <span class="text-xs <?= $meta['badge'] ?> px-2 py-0.5 rounded-full font-semibold"><?= count($services) ?> contact<?= count($services) > 1 ? 's' : '' ?></span>
    </div>

    <!-- Service cards -->
    <div class="space-y-3">
      <?php foreach ($services as $svc): ?>

      <div class="bg-white rounded-2xl shadow-sm border <?= $meta['border'] ?> overflow-hidden flex items-stretch">

        <!-- Left accent -->
        <div class="<?= $meta['bg'] ?> w-1.5 shrink-0"></div>

        <!-- Content -->
        <div class="flex-1 px-4 py-4 min-w-0">
          <p class="font-bold text-gray-800 text-sm leading-snug">
            <?= htmlspecialchars($svc['name']) ?>
          </p>

          <?php if (!empty($svc['address'])): ?>
          <p class="text-xs text-gray-400 mt-0.5">📍 <?= htmlspecialchars($svc['address']) ?></p>
          <?php endif; ?>

          <?php if (!empty($svc['description'])): ?>
          <p class="text-xs text-gray-400 mt-0.5"><?= htmlspecialchars($svc['description']) ?></p>
          <?php endif; ?>
        </div>

        <!-- Call button -->
        <div class="flex items-center px-3 shrink-0">
          <a href="tel:<?= preg_replace('/[^0-9+]/', '', $svc['number']) ?>"
             class="call-btn <?= $meta['bg'] ?> hover:opacity-90 text-white rounded-xl px-4 py-3 text-center min-w-[72px] shadow-sm">
            <div class="text-lg">📞</div>
            <div class="mono text-xs font-bold leading-tight whitespace-nowrap"><?= htmlspecialchars($svc['number']) ?></div>
          </a>
        </div>

      </div>

      <?php endforeach; ?>
    </div>
  </div>

  <?php $delay += 0.07; endforeach; ?>

  <!-- ADMIN MANAGE LINK -->
  <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
  <div class="anim text-center" style="animation-delay:<?= $delay ?>s">
    <a href="admin.php"
       class="inline-block text-xs text-gray-400 hover:text-red-600 underline transition">
      ⚙️ Manage emergency contacts (Admin)
    </a>
  </div>
  <?php endif; ?>

  <!-- FOOTER NOTE -->
  <p class="text-center text-xs text-gray-400 pb-4 anim" style="animation-delay:<?= $delay + 0.05 ?>s">
    Stay calm · Call the right service · Move to a safe area
  </p>

</div>
</body>
</html>