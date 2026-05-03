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

    $first = trim($_POST['first_name']);
    $middle = trim($_POST['middle_name']);
    $last = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $password_input = $_POST['password'];

    // Combine name
    $name = $first . ' ' . ($middle ? $middle . ' ' : '') . $last;

    // Password validation
    if (strlen($password_input) < 6) {
        $error = "Password too short! Must be at least 6 characters.";
    } else {

        $check = $conn->prepare("SELECT id FROM users WHERE email=?");
        $check->bind_param("s", $email);
        $check->execute();
        $result = $check->get_result();

        if ($result->num_rows > 0) {
            $error = "Email already registered!";
        } else {

            $password = password_hash($password_input, PASSWORD_DEFAULT);

            $stmt = $conn->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $name, $email, $password);

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
<html>
<head>
<title>Register</title>
<script src="https://cdn.tailwindcss.com"></script>

<style>
@keyframes scale {
  from { transform: scale(0.8); opacity: 0; }
  to { transform: scale(1); opacity: 1; }
}
.animate-scale {
  animation: scale 0.2s ease;
}
</style>
</head>

<body class="bg-gray-100 flex items-center justify-center h-screen">

<form method="POST" class="bg-white p-6 rounded shadow w-80">
    <h2 class="text-xl font-bold mb-4 text-center text-red-600">Register</h2>

    <input type="text" name="first_name" placeholder="First Name" required
    class="w-full mb-3 p-2 border rounded">

    <input type="text" name="middle_name" placeholder="Middle Name (optional)"
    class="w-full mb-3 p-2 border rounded">

    <input type="text" name="last_name" placeholder="Last Name" required
    class="w-full mb-3 p-2 border rounded">

    <input type="email" name="email" placeholder="Email" required
    class="w-full mb-3 p-2 border rounded">

    <input type="password" name="password" placeholder="Password" minlength="6" required
    class="w-full mb-1 p-2 border rounded">

    <p class="text-xs text-gray-500 mb-3">Password must be at least 6 characters</p>

    <button name="register" class="w-full bg-red-600 text-white p-2 rounded">
        Register
    </button>

    <p class="text-sm mt-3 text-center">
        Already have an account? 
        <a href="login.php" class="text-red-600">Login</a>
    </p>
</form>

<!-- ERROR -->
<?php if (!empty($error)): ?>
<div class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50">
    <div class="bg-white p-6 rounded-xl shadow w-80 text-center animate-scale">
        <h2 class="text-lg font-bold text-red-600 mb-3">Error</h2>
        <p class="mb-4"><?php echo $error; ?></p>
        <button onclick="this.parentElement.parentElement.remove()" 
        class="bg-red-600 text-white px-4 py-2 rounded">
            OK
        </button>
    </div>
</div>
<?php endif; ?>

<!-- SUCCESS -->
<?php if (!empty($success)): ?>
<div class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50">
    <div class="bg-white p-6 rounded-xl shadow w-80 text-center animate-scale">
        <h2 class="text-lg font-bold text-green-600 mb-3">Success</h2>
        <p class="mb-4"><?php echo $success; ?></p>
        <a href="login.php" 
        class="bg-green-600 text-white px-4 py-2 rounded">
            Go to Login
        </a>
    </div>
</div>
<?php endif; ?>

</body>
</html>