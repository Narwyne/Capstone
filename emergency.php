<?php
session_start();

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Emergency Services</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100">

<!-- NAVBAR -->
<nav class="bg-red-700 text-white p-4 flex justify-between items-center">
  <h1 class="text-xl font-bold">Emergency Services</h1>
  <a href="dashboard.php" class="bg-white text-red-700 px-3 py-1 rounded">Back</a>
</nav>

<!-- MAIN -->
<div class="p-6 space-y-6">

  <h2 class="text-lg font-bold">Choose a Service to Call</h2>

  <div class="grid grid-cols-1 md:grid-cols-3 gap-4">

    <!-- Fire Department -->
    <a href="tel:160" 
       class="bg-red-600 text-white p-6 rounded-xl shadow hover:bg-red-700 text-center">
      🔥 Call Fire Department (160)
    </a>

    <!-- Ambulance -->
    <a href="tel:166" 
       class="bg-green-600 text-white p-6 rounded-xl shadow hover:bg-green-700 text-center">
      🚑 Call Ambulance (166)
    </a>

    <!-- Police -->
    <a href="tel:117" 
       class="bg-blue-600 text-white p-6 rounded-xl shadow hover:bg-blue-700 text-center">
      👮 Call Police (117)
    </a>

  </div>

  <p class="text-sm text-gray-600 mt-4">
    ⚠️ Note: Numbers may vary by region. Please confirm your local emergency hotlines.
  </p>

</div>

</body>
</html>
