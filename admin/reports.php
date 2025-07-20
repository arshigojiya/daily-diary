<?php
session_start();

if (!isset($_SESSION['admin_loggedin']) || $_SESSION['admin_loggedin'] !== true) {
    header("location: login.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Analytics & Reports</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="sidebar">
        <h2>Admin Panel</h2>
        <ul>
            <li><a href="dashboard.php">Dashboard</a></li>
            <li><a href="products.php">Manage Products</a></li>
            <li><a href="categories.php">Categories</a></li>
            <li><a href="inventory.php">Inventory / Stock</a></li>
            <li><a href="orders.php">Orders</a></li>
            <li><a href="delivery.php">Delivery Management</a></li>
            <li><a href="customers.php">Customers</a></li>
            <li><a href="payments.php">Payments / Transactions</a></li>
            <li><a href="reports.php">Analytics & Reports</a></li>
            <li><a href="settings.php">Settings</a></li>
            <li><a href="logout.php">Logout</a></li>
        </ul>
    </div>

    <div class="main-content">
        <header>
            <h2>
                Analytics & Reports
            </h2>
            <div class="user-wrapper">
                <div>
                    <h4><?php echo $_SESSION['email']; ?></h4>
                    <small>Admin</small>
                </div>
            </div>
        </header>

        <main>
            <h2>Analytics & Reports</h2>
            <p>This is where you can view analytics and reports.</p>
        </main>
    </div>
</body>
</html>