<?php
session_start();
include "includes/otherDB.php";

if (isset($_SESSION['user'])) {
    header("Location: dashboard.php");
    exit();
}

// ── Settings ──────────────────────────────────────────────────────
const REG_MAX_PER_IP_HOUR = 5;      // max sign-up attempts per IP per hour
const REG_SCHOOL_DOMAIN   = '';     // e.g. 'aclc.edu.ph' to allow school emails only ('' = any email)

$conn->query("CREATE TABLE IF NOT EXISTS register_attempts (
    id int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    ip varchar(45) NOT NULL,
    attempted_at timestamp NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (id),
    KEY idx_ip_time (ip, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$success = "";
$error   = "";
$f = ['first_name'=>'','middle_name'=>'','last_name'=>'','email'=>'','phone'=>'','department'=>'','student_id'=>''];

if (isset($_POST['register'])) {
    foreach ($f as $k => $_) $f[$k] = trim($_POST[$k] ?? '');
    $password_input = $_POST['password'] ?? '';
    $confirm_input  = $_POST['confirm_password'] ?? '';
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    $conn->query("DELETE FROM register_attempts WHERE attempted_at < (NOW() - INTERVAL 1 DAY)");
    $cnt = $conn->prepare("SELECT COUNT(*) FROM register_attempts WHERE ip=? AND attempted_at > (NOW() - INTERVAL 1 HOUR)");
    $cnt->bind_param("s", $ip);
    $cnt->execute();
    $cnt->bind_result($recent);
    $cnt->fetch();
    $cnt->close();

    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = "Session expired. Please refresh the page and try again.";
    } elseif ($recent >= REG_MAX_PER_IP_HOUR) {
        $error = "Too many sign-up attempts. Please try again later.";
    } else {
        $ins = $conn->prepare("INSERT INTO register_attempts (ip) VALUES (?)");
        $ins->bind_param("s", $ip);
        $ins->execute();
        $ins->close();

        $email = strtolower($f['email']);

        if ($f['first_name'] === '' || $f['last_name'] === '') {
            $error = "First name and last name are required.";
        } elseif (mb_strlen($f['first_name']) > 80 || mb_strlen($f['middle_name']) > 80 || mb_strlen($f['last_name']) > 80) {
            $error = "Names must be 80 characters or less.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) {
            $error = "Please enter a valid email address.";
        } elseif (REG_SCHOOL_DOMAIN !== '' && !str_ends_with($email, '@' . REG_SCHOOL_DOMAIN)) {
            $error = "Please use your school email (@" . REG_SCHOOL_DOMAIN . ").";
        } elseif (strlen($password_input) < 8 || !preg_match('/[A-Za-z]/', $password_input) || !preg_match('/[0-9]/', $password_input)) {
            $error = "Password must be at least 8 characters and include a letter and a number.";
        } elseif ($password_input !== $confirm_input) {
            $error = "Passwords do not match.";
        } elseif (empty($_POST['terms'])) {
            $error = "You must agree to the Terms and Privacy Policy.";
        } else {
            $check = $conn->prepare("SELECT id FROM users WHERE email=?");
            $check->bind_param("s", $email);
            $check->execute();
            $exists = $check->get_result()->num_rows > 0;
            $check->close();

            if ($exists) {
                $error = "Email already registered!";
            } else {
                $name = $f['first_name'] . ' ' . ($f['middle_name'] ? $f['middle_name'] . ' ' : '') . $f['last_name'];
                $hash = password_hash($password_input, PASSWORD_DEFAULT);

                $stmt = $conn->prepare("INSERT INTO users (name, first_name, middle_name, last_name, email, password, phone, department, student_id) VALUES (?,?,?,?,?,?,?,?,?)");
                $stmt->bind_param("sssssssss", $name, $f['first_name'], $f['middle_name'], $f['last_name'], $email, $hash,
                                  $f['phone'], $f['department'], $f['student_id']);

                if ($stmt->execute()) {
                    $success = "Account successfully created!";
                    $f = array_map(fn($v) => '', $f);   // clear the form
                } else {
                    $error = "Something went wrong!";
                }
            }
        }
    }
}
$v = fn($k) => htmlspecialchars($f[$k]);
$inp = 'w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#1c3494]/30 focus:border-[#1c3494]';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Register — ACLC Smart Campus</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
  body{font-family:'DM Sans',sans-serif;background:radial-gradient(circle at 20% 20%,#1c2b6e 0%,#0d1638 55%,#0a0f28 100%);}
  .dotbg{background-image:radial-gradient(circle,rgba(255,255,255,.08) 1px,transparent 1px);background-size:18px 18px;}
  @keyframes modalIn{from{opacity:0;transform:scale(.95) translateY(10px)}to{opacity:1;transform:scale(1) translateY(0)}}
  .animate-modal{animation:modalIn .2s ease-out;}
  @keyframes floatUp{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:translateY(0)}}
  .anim{animation:floatUp .5s ease both;}
</style>
</head>
<body class="min-h-screen flex items-center justify-center p-4">

<div class="w-full max-w-sm anim my-6">
  <div class="bg-white rounded-3xl shadow-2xl overflow-hidden">

    <div class="relative bg-gradient-to-br from-[#0d1a52] via-[#152875] to-[#1c3494] pt-8 pb-6 text-center dotbg">
      <img src="assets/aclc_logo.webp" alt="ACLC College"
           class="relative w-20 h-20 mx-auto rounded-full ring-4 ring-white shadow-lg bg-white object-cover">
      <h1 class="relative text-white font-extrabold text-base mt-3 tracking-wide">ACLC COLLEGE</h1>
      <p class="relative text-blue-200 text-[11px] uppercase tracking-[0.25em] mt-0.5">Tacloban City</p>
    </div>
    <div class="h-1.5 bg-gradient-to-r from-red-700 via-red-500 to-red-700"></div>

    <div class="px-7 pt-6 pb-7">
      <h2 class="text-xl font-bold text-gray-800">Create Account</h2>
      <p class="text-xs text-gray-400 mb-5">Join Smart Campus</p>

      <form method="POST" class="space-y-3">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

        <div class="grid grid-cols-2 gap-2">
          <input type="text" name="first_name" placeholder="First Name" required maxlength="80" value="<?= $v('first_name') ?>" class="<?= $inp ?>">
          <input type="text" name="last_name" placeholder="Last Name" required maxlength="80" value="<?= $v('last_name') ?>" class="<?= $inp ?>">
        </div>

        <input type="text" name="middle_name" placeholder="Middle Name (optional)" maxlength="80" value="<?= $v('middle_name') ?>" class="<?= $inp ?>">

        <input type="email" name="email" placeholder="Email<?= REG_SCHOOL_DOMAIN ? ' (@' . REG_SCHOOL_DOMAIN . ')' : '' ?>" required maxlength="100" value="<?= $v('email') ?>" class="<?= $inp ?>">

        <div class="grid grid-cols-2 gap-2">
          <input type="text" name="student_id" placeholder="ID No. (optional)" maxlength="50" value="<?= $v('student_id') ?>" class="<?= $inp ?>">
          <input type="text" name="department" placeholder="Course (optional)" maxlength="100" value="<?= $v('department') ?>" class="<?= $inp ?>">
        </div>

        <input type="tel" name="phone" placeholder="Phone (optional)" maxlength="30" value="<?= $v('phone') ?>" class="<?= $inp ?>">

        <div>
          <input type="password" name="password" id="pw" placeholder="Password" minlength="8" required oninput="checkStrength(this.value)" class="<?= $inp ?>">
          <div class="mt-2 h-1.5 bg-gray-100 rounded-full overflow-hidden">
            <div id="strengthBar" class="h-full rounded-full transition-all duration-300" style="width:0"></div>
          </div>
          <p id="strengthLabel" class="text-[11px] text-gray-400 mt-1">At least 8 characters, with a letter and a number</p>
        </div>

        <div>
          <input type="password" name="confirm_password" id="pw2" placeholder="Confirm Password" required oninput="checkMatch()" class="<?= $inp ?>">
          <p id="matchLabel" class="text-[11px] mt-1"></p>
        </div>

        <label class="flex items-start gap-2 text-[11px] text-gray-500 cursor-pointer pt-1">
          <input type="checkbox" name="terms" required class="accent-[#1c3494] w-3.5 h-3.5 mt-0.5">
          <a href="privacy.php" onclick="openPrivacy(); return false;" class="text-red-600 underline">Privacy Policy</a>
        </label>

        <button name="register"
          class="w-full text-white font-semibold text-sm py-2.5 rounded-xl mt-2 shadow-md transition hover:opacity-90"
          style="background:linear-gradient(90deg,#152875,#b91c1c);">
          Register
        </button>
      </form>

      <p class="text-xs mt-5 text-center text-gray-400">
        Already have an account?
        <a href="login.php" class="text-red-600 font-semibold hover:underline">Login</a>
      </p>
    </div>
  </div>
</div>

<?php if (!empty($error)): ?>
<div id="errorModal" class="fixed inset-0 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
  <div class="bg-white p-6 rounded-2xl shadow-2xl w-80 text-center animate-modal">
    <div class="w-12 h-12 rounded-full bg-red-100 text-red-600 flex items-center justify-center text-2xl mx-auto mb-2">⚠️</div>
    <h2 class="text-lg font-bold text-gray-800 mb-1">Error</h2>
    <p class="text-sm text-gray-500 mb-4"><?= htmlspecialchars($error) ?></p>
    <button onclick="this.closest('#errorModal').remove()"
      class="text-white px-5 py-2 rounded-xl text-sm font-semibold" style="background:#b91c1c;">OK</button>
  </div>
</div>
<?php endif; ?>

<?php if (!empty($success)): ?>
<div id="successModal" class="fixed inset-0 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
  <div class="bg-white p-6 rounded-2xl shadow-2xl w-80 text-center animate-modal">
    <div class="w-12 h-12 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center text-2xl mx-auto mb-2">✅</div>
    <h2 class="text-lg font-bold text-gray-800 mb-1">Success</h2>
    <p class="text-sm text-gray-500 mb-4"><?= htmlspecialchars($success) ?></p>
    <a href="login.php" class="inline-block text-white px-5 py-2 rounded-xl text-sm font-semibold" style="background:#152875;">Go to Login</a>
  </div>
</div>
<?php endif; ?>

<script>
function checkStrength(val) {
  const bar = document.getElementById('strengthBar'), label = document.getElementById('strengthLabel');
  let score = 0;
  if (val.length >= 8) score++;
  if (val.length >= 12) score++;
  if (/[A-Z]/.test(val)) score++;
  if (/[0-9]/.test(val)) score++;
  if (/[^A-Za-z0-9]/.test(val)) score++;
  const lv = [['0%','',''],['25%','#ef4444','Weak'],['50%','#f97316','Fair'],['75%','#f59e0b','Good'],['90%','#10b981','Strong'],['100%','#059669','Very strong']][Math.min(score, 5)];
  bar.style.width = val ? lv[0] : '0'; bar.style.background = lv[1];
  label.textContent = val ? lv[2] : 'At least 8 characters, with a letter and a number';
  label.style.color = val ? lv[1] : '';
  checkMatch();
}
function checkMatch() {
  const a = document.getElementById('pw').value, b = document.getElementById('pw2').value, l = document.getElementById('matchLabel');
  if (!b) { l.textContent = ''; return; }
  l.textContent = a === b ? '✓ Passwords match' : '✗ Passwords do not match';
  l.style.color = a === b ? '#059669' : '#ef4444';
}
</script>
<?php include 'includes/privacy_modal.php'; ?>
</body>
</html>