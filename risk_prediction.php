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
require_once 'includes/risk_engine.php';

$scored     = $pdo ? fetchScoredIncidents($pdo) : [];
$byLocation = computeLocationRisk($scored);
$byType     = computeTypeRisk($scored);
$totalScore = array_sum(array_column($byLocation, 'score'));
$campus     = riskLevelFor($totalScore);

function riskBadge($color, $label) {
    return "<span class='inline-block text-xs font-semibold px-2.5 py-1 rounded-full bg-{$color}-100 text-{$color}-700'>{$label}</span>";
}
function fmtLoc($l) { return ucwords(str_replace('_',' ',$l)); }
function fmtType($t) { return ucfirst(str_replace('_',' ',$t)); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Risk Prediction — ACLC Smart Campus</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=DM+Mono:wght@500&display=swap" rel="stylesheet">
  <style>
    body { font-family:'DM Sans',sans-serif; background-color:#f3f4f6; background-image:radial-gradient(circle at 1px 1px,rgba(0,0,0,0.04) 1px,transparent 0); background-size:24px 24px; }
    .mono { font-family:'DM Mono',monospace; }
    @keyframes fadeUp { from{opacity:0;transform:translateY(14px);} to{opacity:1;transform:translateY(0);} }
    .anim { animation:fadeUp 0.4s ease both; }
    .bar-track { background:#f3f4f6; border-radius:9999px; overflow:hidden; }
    .bar-fill { height:100%; border-radius:9999px; }
  </style>
</head>
<body class="min-h-screen">

<nav class="bg-red-700 text-white sticky top-0 z-40 shadow-lg">
  <div class="max-w-5xl mx-auto px-4 py-3 flex items-center justify-between">
    <div>
      <span class="text-xs text-red-300 uppercase tracking-widest block leading-none">ACLC Smart Campus</span>
      <span class="font-bold text-lg leading-tight">Risk Prediction</span>
    </div>
    <a href="dashboard.php" class="bg-white text-red-700 hover:bg-red-50 px-3 py-1.5 rounded-lg text-sm font-semibold transition">← Dashboard</a>
  </div>
</nav>

<div class="max-w-5xl mx-auto px-4 py-6 space-y-5">

  <!-- CAMPUS OVERALL RISK -->
  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 anim">
    <div>
      <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Campus-wide Risk (last <?= RISK_LOOKBACK_DAYS ?> days)</p>
      <div class="flex items-center gap-3">
        <span class="text-3xl font-bold text-gray-800"><?= round($totalScore,1) ?></span>
        <?= riskBadge($campus['color'], $campus['label']) ?>
      </div>
    </div>
    <p class="text-xs text-gray-400 max-w-xs">Score = severity × open/resolved status × recency, summed across <?= count($scored) ?> incident<?= count($scored)!==1?'s':'' ?>.</p>
  </div>

  <?php if (empty($byLocation)): ?>
  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 text-center py-16 text-gray-400 anim">
    <div class="text-5xl mb-3">📊</div>
    <p class="font-semibold">Not enough data yet.</p>
    <p class="text-sm mt-1 text-gray-300">No incidents reported in the last <?= RISK_LOOKBACK_DAYS ?> days.</p>
  </div>
  <?php else: ?>

  <!-- TOP RISK LOCATIONS -->
  <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 anim" style="animation-delay:0.05s">
    <?php foreach (array_slice($byLocation, 0, 3) as $i => $l): ?>
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4">
      <div class="flex items-center justify-between mb-1">
        <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider">#<?= $i+1 ?> Highest Risk</span>
        <?= riskBadge($l['color'], $l['level']) ?>
      </div>
      <p class="font-bold text-gray-800">📍 <?= htmlspecialchars(fmtLoc($l['location'])) ?></p>
      <p class="text-xs text-gray-400 mt-1">Score <?= $l['score'] ?> · <?= $l['count'] ?> incident<?= $l['count']!==1?'s':'' ?> · mostly <?= fmtType($l['top_type']) ?></p>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- FULL LOCATION TABLE -->
  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 anim" style="animation-delay:0.1s">
    <h3 class="font-bold text-gray-700 mb-3 text-sm">📍 Risk by Location</h3>
    <div class="space-y-2">
      <?php $maxScore = max(array_column($byLocation, 'score')) ?: 1; ?>
      <?php foreach ($byLocation as $l): ?>
      <div class="flex items-center gap-3 text-sm">
        <span class="w-32 shrink-0 text-gray-600 capitalize"><?= htmlspecialchars(fmtLoc($l['location'])) ?></span>
        <div class="flex-1 h-2.5 bar-track">
          <div class="bar-fill bg-<?= $l['color'] ?>-500" style="width: <?= max(4, round($l['score']/$maxScore*100)) ?>%"></div>
        </div>
        <span class="w-10 text-right mono text-xs text-gray-400"><?= $l['score'] ?></span>
        <?= riskBadge($l['color'], $l['level']) ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- TYPE BREAKDOWN -->
  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 anim" style="animation-delay:0.15s">
    <h3 class="font-bold text-gray-700 mb-3 text-sm">🗂 Risk by Incident Type</h3>
    <div class="flex flex-wrap gap-2">
      <?php foreach ($byType as $t): ?>
      <span class="inline-flex items-center gap-1.5 text-xs font-medium px-3 py-1.5 rounded-full bg-<?= $t['color'] ?>-50 text-<?= $t['color'] ?>-700 border border-<?= $t['color'] ?>-200">
        <?= fmtType($t['type']) ?> · <?= $t['score'] ?> pts
      </span>
      <?php endforeach; ?>
    </div>
  </div>

  <?php endif; ?>

  <!-- AI INSIGHT -->
  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 anim" style="animation-delay:0.2s">
    <div class="flex items-center justify-between mb-3">
      <h3 class="font-bold text-gray-700 text-sm">🤖 AI Risk Briefing</h3>
      <button id="genBtn" onclick="generateInsight()" class="text-xs bg-red-700 hover:bg-red-800 text-white font-semibold px-3 py-1.5 rounded-lg transition">Generate</button>
    </div>
    <div id="insightBox" class="text-sm text-gray-500 whitespace-pre-wrap leading-relaxed">Click "Generate" for an AI-written summary of the risk data above.</div>
  </div>

</div>

<script>
async function generateInsight() {
  const btn = document.getElementById('genBtn');
  const box = document.getElementById('insightBox');
  btn.disabled = true; btn.textContent = 'Thinking…';
  box.textContent = '🤖 Analyzing risk data…';
  try {
    const res  = await fetch('risk_insight.php', { method: 'POST' });
    const data = await res.json();
    box.innerHTML = data.success
      ? data.text.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/\*\*(.*?)\*\*/g,'<strong>$1</strong>').replace(/\n/g,'<br>')
      : `⚠️ ${data.message || 'Could not generate insight.'}`;
  } catch {
    box.textContent = '⚠️ Network error. Please try again.';
  }
  btn.disabled = false; btn.textContent = 'Regenerate';
}
</script>
</body>
</html>