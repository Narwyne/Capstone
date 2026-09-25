<?php
session_start();
include "includes/otherDB.php";

$success = "";
$error = "";

if (isset($_SESSION['user'])) {
    header("Location: dashboard.php");
    exit();
}

if (isset($_POST['register'])) {

    // Get form inputs
    $first = trim($_POST['first_name']);
    $middle = trim($_POST['middle_name']);
    $last = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $password_input = $_POST['password'];

    // Combine full name
    $name = $first . ' ' . ($middle ? $middle . ' ' : '') . $last;

    // Password validation
    if (strlen($password_input) < 6) {
        $error = "Password too short! Must be at least 6 characters.";
    } else {

        // Check if email already exists
        $check = $conn->prepare("SELECT id FROM users WHERE email=?");
        $check->bind_param("s", $email);
        $check->execute();
        $result = $check->get_result();

        if ($result->num_rows > 0) {
            $error = "Email already registered!";
        } else {

            // Hash password
            $password = password_hash($password_input, PASSWORD_DEFAULT);

            // Insert user into database
            $stmt = $conn->prepare("INSERT INTO users (name, first_name, middle_name, last_name, email, password) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssss", $name, $first, $middle, $last, $email, $password);

            if ($stmt->execute()) {
                $success = "Account successfully created!";
            } else {
                $error = "Something went wrong!";
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
      <h2 class="text-xl font-bold text-gray-800">Create Account</h2>
      <p class="text-xs text-gray-400 mb-5">Join Smart Campus</p>

      <form method="POST" class="space-y-3">

        <div class="grid grid-cols-2 gap-2">
          <input type="text" name="first_name" placeholder="First Name" required
            class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#1c3494]/30 focus:border-[#1c3494]">
          <input type="text" name="last_name" placeholder="Last Name" required
            class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#1c3494]/30 focus:border-[#1c3494]">
        </div>

        <input type="text" name="middle_name" placeholder="Middle Name (optional)"
          class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#1c3494]/30 focus:border-[#1c3494]">

        <input type="email" name="email" placeholder="Email" required
          class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#1c3494]/30 focus:border-[#1c3494]">

        <div>
          <input type="password" name="password" placeholder="Password" minlength="6" required
            class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#1c3494]/30 focus:border-[#1c3494]">
          <p class="text-[11px] text-gray-400 mt-1">Must be at least 6 characters</p>
        </div>

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

<!-- ERROR -->
<?php if (!empty($error)): ?>
<div id="errorModal" class="fixed inset-0 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
  <div class="bg-white p-6 rounded-2xl shadow-2xl w-80 text-center animate-modal">
    <div class="w-12 h-12 rounded-full bg-red-100 text-red-600 flex items-center justify-center text-2xl mx-auto mb-2">⚠️</div>
    <h2 class="text-lg font-bold text-gray-800 mb-1">Error</h2>
    <p class="text-sm text-gray-500 mb-4"><?php echo $error; ?></p>
    <button onclick="this.closest('#errorModal').remove()"
      class="text-white px-5 py-2 rounded-xl text-sm font-semibold" style="background:#b91c1c;">
      OK
    </button>
  </div>
</div>
<?php endif; ?>

<!-- SUCCESS -->
<?php if (!empty($success)): ?>
<div id="successModal" class="fixed inset-0 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
  <div class="bg-white p-6 rounded-2xl shadow-2xl w-80 text-center animate-modal">
    <div class="w-12 h-12 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center text-2xl mx-auto mb-2">✅</div>
    <h2 class="text-lg font-bold text-gray-800 mb-1">Success</h2>
    <p class="text-sm text-gray-500 mb-4"><?php echo $success; ?></p>
    <a href="login.php"
       class="inline-block text-white px-5 py-2 rounded-xl text-sm font-semibold" style="background:#152875;">
      Go to Login
    </a>
  </div>
</div>
<?php endif; ?>

</body>
</html>