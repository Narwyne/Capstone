<?php
$error = "";

session_start();
include "includes/otherDB.php";

if (isset($_SESSION['user'])) {
    if ($_SESSION['role'] === 'admin') {
        header("Location: admin.php");
    } else {
        header("Location: dashboard.php");
    }
    exit();
}

// ── Rate limiting settings ────────────────────────────────────────
const LOGIN_MAX_EMAIL_FAILS = 5;    // failed tries per email...
const LOGIN_MAX_IP_FAILS    = 20;   // ...or per IP (higher: a campus shares one IP)
const LOGIN_WINDOW_MINUTES  = 15;   // ...within this window => locked for this long

// Table is created automatically if you haven't imported migration.sql
$conn->query("CREATE TABLE IF NOT EXISTS login_attempts (
    id int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    email varchar(100) NOT NULL,
    ip varchar(45) NOT NULL,
    attempted_at timestamp NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (id),
    KEY idx_email_time (email, attempted_at),
    KEY idx_ip_time (ip, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Returns seconds left on the lockout, or 0 if not locked.
function loginLockSeconds(mysqli $conn, string $email, string $ip): int {
    $window = LOGIN_WINDOW_MINUTES;
    $checks = [
        ['email', $email, LOGIN_MAX_EMAIL_FAILS],
        ['ip',    $ip,    LOGIN_MAX_IP_FAILS],
    ];
    $left = 0;
    foreach ($checks as [$col, $val, $max]) {
        $stmt = $conn->prepare("
            SELECT COUNT(*) AS c,
                   TIMESTAMPDIFF(SECOND, MAX(attempted_at), NOW()) AS since_last
            FROM login_attempts
            WHERE $col = ? AND attempted_at > (NOW() - INTERVAL $window MINUTE)
        ");
        $stmt->bind_param("s", $val);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if ((int)$row['c'] >= $max) {
            $left = max($left, $window * 60 - (int)$row['since_last']);
        }
    }
    return max(0, $left);
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Remembered email
$email_value = isset($_COOKIE['user_email']) ? $_COOKIE['user_email'] : '';
$remember_checked = isset($_COOKIE['user_email']) ? 'checked' : '';

if (isset($_POST['login'])) {

    // CSRF VALIDATION
    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        die("Invalid CSRF token");
    }

    $email    = trim($_POST['email']);
    $password = $_POST['password'];
    $emailKey = strtolower(mb_substr($email, 0, 100));
    $ip       = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    // Clean out old attempts
    $conn->query("DELETE FROM login_attempts WHERE attempted_at < (NOW() - INTERVAL 1 DAY)");

    $lockLeft = loginLockSeconds($conn, $emailKey, $ip);

    if ($lockLeft > 0) {
        // Locked: don't even check the password (and don't extend the lock)
        $mins  = (int)ceil($lockLeft / 60);
        $error = "Too many failed attempts. Please try again in $mins minute" . ($mins !== 1 ? 's' : '') . ".";
    } else {

        $stmt = $conn->prepare("SELECT * FROM users WHERE email=? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        $user = ($result->num_rows === 1) ? $result->fetch_assoc() : null;

        if ($user && password_verify($password, $user['password'])) {

            // Success: forget failed attempts for this email
            $del = $conn->prepare("DELETE FROM login_attempts WHERE email = ?");
            $del->bind_param("s", $emailKey);
            $del->execute();

            session_regenerate_id(true);

            $_SESSION['user'] = $user['name'];
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];

            if (isset($_POST['remember'])) {
                setcookie("user_email", $email, time() + (86400 * 30), "/", "", false, true);
            } else {
                setcookie("user_email", "", time() - 3600, "/");
            }

            if ($user['role'] === 'admin') {
                header("Location: admin.php");
            } else {
                header("Location: dashboard.php");
            }
            exit();

        } else {
            // Record the failure (also for unknown emails, so they can't be probed freely)
            $ins = $conn->prepare("INSERT INTO login_attempts (email, ip) VALUES (?, ?)");
            $ins->bind_param("ss", $emailKey, $ip);
            $ins->execute();

            // Same message for wrong email and wrong password (no account probing)
            $error = "Invalid email or password.";

            if (loginLockSeconds($conn, $emailKey, $ip) > 0) {
                $error = "Too many failed attempts. Please try again in " . LOGIN_WINDOW_MINUTES . " minutes.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login — ACLC Smart Campus</title>
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

<div class="w-full max-w-sm anim">
  <div class="bg-white rounded-3xl shadow-2xl overflow-hidden">

    <!-- BRAND BANNER -->
    <div class="relative bg-gradient-to-br from-[#0d1a52] via-[#152875] to-[#1c3494] pt-8 pb-6 text-center dotbg">
      <img src="assets/aclc_logo.webp" alt="ACLC College"
           class="relative w-20 h-20 mx-auto rounded-full ring-4 ring-white shadow-lg bg-white object-cover">
      <h1 class="relative text-white font-extrabold text-base mt-3 tracking-wide">ACLC COLLEGE</h1>
      <p class="relative text-blue-200 text-[11px] uppercase tracking-[0.25em] mt-0.5">Tacloban City</p>
    </div>
    <div class="h-1.5 bg-gradient-to-r from-red-700 via-red-500 to-red-700"></div>

    <!-- FORM -->
    <div class="px-7 pt-6 pb-7">
      <h2 class="text-xl font-bold text-gray-800">Welcome Back</h2>
      <p class="text-xs text-gray-400 mb-5">Sign in to Smart Campus</p>

      <form method="POST" class="space-y-3">

        <div>
          <label class="block text-xs font-semibold text-gray-500 mb-1">Email</label>
          <input type="email" name="email" required
                 value="<?php echo htmlspecialchars($email_value); ?>"
                 placeholder="you@aclc.edu.ph"
                 class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#1c3494]/30 focus:border-[#1c3494]">
        </div>

        <div>
          <label class="block text-xs font-semibold text-gray-500 mb-1">Password</label>
          <input type="password" name="password" required
                 placeholder="••••••••"
                 class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#1c3494]/30 focus:border-[#1c3494]">
        </div>

        <label class="flex items-center gap-2 text-xs text-gray-500 cursor-pointer pt-1">
          <input type="checkbox" name="remember" <?php echo $remember_checked; ?> class="accent-[#1c3494] w-3.5 h-3.5">
          Remember Me
        </label>

        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

        <button name="login"
          class="w-full text-white font-semibold text-sm py-2.5 rounded-xl mt-2 shadow-md transition hover:opacity-90"
          style="background:linear-gradient(90deg,#152875,#b91c1c);">
          Sign In
        </button>
      </form>

      <p class="text-xs mt-5 text-center text-gray-400">
        No account?
        <a href="register.php" class="text-red-600 font-semibold hover:underline">Register</a>
      </p>
    </div>
  </div>
</div>

<?php if (!empty($error)): ?>
<div id="errorModal" class="fixed inset-0 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
  <div class="bg-white p-6 rounded-2xl shadow-2xl w-80 text-center animate-modal">
    <div class="w-12 h-12 rounded-full bg-red-100 text-red-600 flex items-center justify-center text-2xl mx-auto mb-2">⚠️</div>
    <h2 class="text-lg font-bold text-gray-800 mb-1">Login Error</h2>
    <p class="text-sm text-gray-500 mb-4"><?php echo htmlspecialchars($error); ?></p>
    <button onclick="document.getElementById('errorModal').remove()"
      class="text-white px-5 py-2 rounded-xl text-sm font-semibold" style="background:#b91c1c;">
      OK
    </button>
  </div>
</div>
<?php endif; ?>

</body>
</html>