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

    $email = trim($_POST['email']);
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT * FROM users WHERE email=? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        if (password_verify($password, $user['password'])) {

            session_regenerate_id(true);

            // SESSION DATA
            $_SESSION['user'] = $user['name']; // Full name
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];

            // REMEMBER ME FUNCTION
            if (isset($_POST['remember'])) {

                // Save email for 30 days
                setcookie(
                    "user_email",
                    $email,
                    time() + (86400 * 30),
                    "/",
                    "",
                    false,
                    true
                );

            } else {

                // Remove cookie if unchecked
                setcookie(
                    "user_email",
                    "",
                    time() - 3600,
                    "/"
                );
            }

            // Redirect by role
            if ($user['role'] === 'admin') {
                header("Location: admin.php");
            } else {
                header("Location: dashboard.php");
            }
            exit();

        } else {
            $error = "Wrong password!";
        }

    } else {
        $error = "User not found!";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100 flex items-center justify-center h-screen">

<form method="POST" class="bg-white p-6 rounded shadow w-80">

    <h2 class="text-xl font-bold mb-4 text-center text-red-600">Login</h2>

    <!-- Email -->
    <input
        type="email"
        name="email"
        placeholder="Email"
        value="<?php echo htmlspecialchars($email_value); ?>"
        required
        class="w-full mb-3 p-2 border rounded"
    >

    <!-- Password -->
    <input
        type="password"
        name="password"
        placeholder="Password"
        required
        class="w-full mb-3 p-2 border rounded"
    >

    <!-- Remember Me -->
    <label class="flex items-center gap-2 text-sm mb-3 cursor-pointer">
        <input
            type="checkbox"
            name="remember"
            <?php echo $remember_checked; ?>
        >
        Remember Me
    </label>

    <!-- CSRF -->
    <input
        type="hidden"
        name="csrf_token"
        value="<?php echo $_SESSION['csrf_token']; ?>"
    >

    <!-- Login Button -->
    <button
        name="login"
        class="w-full bg-red-600 text-white p-2 rounded hover:bg-red-700 transition"
    >
        Login
    </button>

    <!-- Register -->
    <p class="text-sm mt-3 text-center">
        No account?
        <a href="register.php" class="text-red-600">Register</a>
    </p>

</form>

<!-- ERROR MODAL -->
<?php if (!empty($error)): ?>
<div id="errorModal" class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50">
    <div class="bg-white p-6 rounded-xl shadow w-80 text-center">

        <h2 class="text-lg font-bold text-red-600 mb-3">Login Error</h2>

        <p class="mb-4"><?php echo $error; ?></p>

        <button
            onclick="document.getElementById('errorModal').remove()"
            class="bg-red-600 text-white px-4 py-2 rounded"
        >
            OK
        </button>

    </div>
</div>
<?php endif; ?>

</body>
</html>