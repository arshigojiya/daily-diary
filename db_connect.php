<?php
require_once 'config.php';

try {
    $conn = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("ERROR: Could not connect. " . $e->getMessage());
}

// Create admins table if it doesn't exist
$tableName = 'admins';
$sql = "CREATE TABLE IF NOT EXISTS $tableName (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(50) NOT NULL,
    password VARCHAR(255) NOT NULL,
    reg_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

try {
    $conn->exec($sql);
} catch(PDOException $e) {
    // Do nothing, table likely already exists
}

// Check if default admin exists
$email = 'admin@gmail.com';
$password = 'admin123';
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

$sql = "SELECT id FROM $tableName WHERE email = :email";
$stmt = $conn->prepare($sql);
$stmt->bindParam(':email', $email, PDO::PARAM_STR);
$stmt->execute();

if ($stmt->rowCount() == 0) {
    $sql = "INSERT INTO $tableName (email, password) VALUES (:email, :password)";
    $stmt_insert = $conn->prepare($sql);
    $stmt_insert->bindParam(':email', $email, PDO::PARAM_STR);
    $stmt_insert->bindParam(':password', $hashed_password, PDO::PARAM_STR);
    $stmt_insert->execute();
}
?>