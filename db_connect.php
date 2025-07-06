<?php
$host = "localhost";
$username = "root";
$password = "";
$database = "dailydiary";

// Create connection
$conn = new mysqli($host, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("❌ Connection failed: " . $conn->connect_error);
}

// ✅ Create default admin only if the users table and admin user don't exist
$admin_email = "admin@gmail.com";
$admin_password = password_hash("admin123", PASSWORD_DEFAULT);

// Check if users table exists
$table_check = $conn->query("SHOW TABLES LIKE 'users'");
if ($table_check && $table_check->num_rows > 0) {
    $check_admin = $conn->query("SELECT * FROM users WHERE email = '$admin_email'");
    if ($check_admin && $check_admin->num_rows == 0) {
        $conn->query("INSERT INTO users (name, email, password) VALUES ('Admin', '$admin_email', '$admin_password')");
    }
}
?>
