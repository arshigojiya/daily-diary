<?php
session_start();

// 🔒 Protect page if not logged in
if (!isset($_SESSION['user_email'])) {
    header("Location: login.php");
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard</title>
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: #e8f5e9;
            padding: 40px;
            text-align: center;
        }
        .box {
            background: white;
            padding: 30px;
            max-width: 500px;
            margin: 0 auto;
            border-radius: 10px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
        }
        .logout {
            margin-top: 20px;
        }
        .logout a {
            color: red;
            text-decoration: none;
        }
    </style>
</head>
<body>

<div class="box">
    <h2>👋 Welcome, <?= $_SESSION['user_name']; ?>!</h2>
    <p>This is your admin dashboard.</p>

    <div class="logout">
        <a href="logout.php">Logout</a>
    </div>
</div>

</body>
</html>
