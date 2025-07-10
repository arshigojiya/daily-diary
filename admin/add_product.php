<?php
session_start();

// ✅ Redirect to login if session not set
if (!isset($_SESSION['user_email'])) {
    header("Location: login.php");
    exit();
}

include '../db_connect.php'; // DB connection

$success = "";
$error = "";

// ✅ Handle form submit
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $conn->real_escape_string($_POST['name']);
    $price = $_POST['price'];

    // ✅ Handle image upload
    $image = $_FILES['image']['name'];
    $temp = $_FILES['image']['tmp_name'];
    $upload_folder = "../uploads/";

    if (!is_dir($upload_folder)) {
        mkdir($upload_folder, 0777, true);
    }

    $image_path = $upload_folder . $image;
    $image_db_path = "uploads/" . $image;

    if (move_uploaded_file($temp, $image_path)) {
        $sql = "INSERT INTO products (name, price, image) VALUES ('$name', '$price', '$image_db_path')";
        if ($conn->query($sql)) {
            $success = "✅ Product added successfully!";
        } else {
            $error = "❌ Database error: " . $conn->error;
        }
    } else {
        $error = "❌ Image upload failed.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Add Product - Admin</title>
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: #f1f8e9;
            padding: 40px;
        }
        .box {
            max-width: 500px;
            margin: auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        input, button {
            width: 100%;
            padding: 12px;
            margin: 10px 0;
            border-radius: 6px;
            border: 1px solid #ccc;
        }
        button {
            background: #2e7d32;
            color: white;
            border: none;
            cursor: pointer;
        }
        .msg {
            font-weight: bold;
            color: green;
        }
        .error {
            font-weight: bold;
            color: red;
        }
    </style>
</head>
<body>

<div class="box">
    <h2>Add New Product</h2>

    <?php if ($success): ?>
        <p class="msg"><?= $success ?></p>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <p class="error"><?= $error ?></p>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <input type="text" name="name" placeholder="Product Name" required>
        <input type="number" name="price" placeholder="Price" step="0.01" required>
        <input type="file" name="image" accept="image/*" required>
        <button type="submit">Add Product</button>
    </form>
</div>

</body>
</html>
