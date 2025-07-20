    <?php
    session_start();
    require_once '../db_connect.php';

    if (!isset($_SESSION['admin_loggedin']) || $_SESSION['admin_loggedin'] !== true) {
        header("location: login.php");
        exit;
    }

    // Create customers table if not exists
    $sql = "CREATE TABLE IF NOT EXISTS customers (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(100) UNIQUE,
        phone VARCHAR(20),
        address TEXT,
        city VARCHAR(50),
        state VARCHAR(50),
        pincode VARCHAR(10),
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $conn->exec($sql);

    // Create orders table if not exists
    $sql = "CREATE TABLE IF NOT EXISTS orders (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        order_number VARCHAR(20) UNIQUE NOT NULL,
        customer_id INT(6) UNSIGNED,
        customer_name VARCHAR(100) NOT NULL,
        customer_email VARCHAR(100),
        customer_phone VARCHAR(20),
        customer_address TEXT,
        total_amount DECIMAL(10,2) NOT NULL,
        discount_amount DECIMAL(10,2) DEFAULT 0,
        final_amount DECIMAL(10,2) NOT NULL,
        payment_status ENUM('pending', 'paid', 'failed', 'refunded') DEFAULT 'pending',
        order_status ENUM('pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled') DEFAULT 'pending',
        payment_method ENUM('cash', 'card', 'upi', 'bank_transfer') DEFAULT 'cash',
        delivery_method ENUM('home_delivery', 'pickup') DEFAULT 'home_delivery',
        delivery_charge DECIMAL(10,2) DEFAULT 0,
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
    )";
    $conn->exec($sql);

    // Create order_items table if not exists
    $sql = "CREATE TABLE IF NOT EXISTS order_items (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        order_id INT(6) UNSIGNED NOT NULL,
        product_id INT(6) UNSIGNED NOT NULL,
        product_name VARCHAR(255) NOT NULL,
        product_sku VARCHAR(50) NOT NULL,
        quantity INT NOT NULL,
        unit_price DECIMAL(10,2) NOT NULL,
        discount_percent DECIMAL(5,2) DEFAULT 0,
        final_price DECIMAL(10,2) NOT NULL,
        total_price DECIMAL(10,2) NOT NULL,
        FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
    )";
    $conn->exec($sql);

    // Create order_status_logs table if not exists
    $sql = "CREATE TABLE IF NOT EXISTS order_status_logs (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        order_id INT(6) UNSIGNED NOT NULL,
        status_from ENUM('pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled'),
        status_to ENUM('pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled') NOT NULL,
        notes TEXT,
        performed_by VARCHAR(100),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
    )";
    $conn->exec($sql);

    // Handle CRUD operations
    $message = '';
    $message_type = '';

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'create_order':
                    try {
                        $conn->beginTransaction();
                        
                        // Generate order number
                        $order_number = 'ORD-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
                        
                        $customer_name = trim($_POST['customer_name']);
                        $customer_email = trim($_POST['customer_email']);
                        $customer_phone = trim($_POST['customer_phone']);
                        $customer_address = trim($_POST['customer_address']);
                        $payment_method = $_POST['payment_method'];
                        $delivery_method = $_POST['delivery_method'];
                        $delivery_charge = floatval($_POST['delivery_charge']);
                        $notes = trim($_POST['notes']);
                        
                        // Calculate totals
                        $total_amount = 0;
                        $discount_amount = 0;
                        $final_amount = 0;
                        
                        foreach ($_POST['items'] as $item) {
                            if (empty($item['product_id']) || empty($item['quantity'])) continue;
                            
                            $product_id = intval($item['product_id']);
                            $quantity = intval($item['quantity']);
                            
                            // Get product details
                            $sql = "SELECT name, sku, final_price, discount_percent FROM products WHERE id = :id";
                            $stmt = $conn->prepare($sql);
                            $stmt->execute([':id' => $product_id]);
                            $product = $stmt->fetch(PDO::FETCH_ASSOC);
                            
                            if (!$product) continue;
                            
                            $unit_price = $product['final_price'];
                            $item_total = $unit_price * $quantity;
                            $total_amount += $item_total;
                        }
                        
                        $final_amount = $total_amount + $delivery_charge - $discount_amount;
                        
                        // Insert order
                        $sql = "INSERT INTO orders (order_number, customer_name, customer_email, customer_phone, customer_address, 
                                total_amount, discount_amount, final_amount, payment_method, delivery_method, delivery_charge, notes) 
                                VALUES (:order_number, :customer_name, :customer_email, :customer_phone, :customer_address, 
                                :total_amount, :discount_amount, :final_amount, :payment_method, :delivery_method, :delivery_charge, :notes)";
                        
                        $stmt = $conn->prepare($sql);
                        $stmt->execute([
                            ':order_number' => $order_number,
                            ':customer_name' => $customer_name,
                            ':customer_email' => $customer_email,
                            ':customer_phone' => $customer_phone,
                            ':customer_address' => $customer_address,
                            ':total_amount' => $total_amount,
                            ':discount_amount' => $discount_amount,
                            ':final_amount' => $final_amount,
                            ':payment_method' => $payment_method,
                            ':delivery_method' => $delivery_method,
                            ':delivery_charge' => $delivery_charge,
                            ':notes' => $notes
                        ]);
                        
                        $order_id = $conn->lastInsertId();
                        
                        // Insert order items
                        foreach ($_POST['items'] as $item) {
                            if (empty($item['product_id']) || empty($item['quantity'])) continue;
                            
                            $product_id = intval($item['product_id']);
                            $quantity = intval($item['quantity']);
                            
                            // Get product details
                            $sql = "SELECT name, sku, final_price, discount_percent FROM products WHERE id = :id";
                            $stmt = $conn->prepare($sql);
                            $stmt->execute([':id' => $product_id]);
                            $product = $stmt->fetch(PDO::FETCH_ASSOC);
                            
                            if (!$product) continue;
                            
                            $unit_price = $product['final_price'];
                            $item_total = $unit_price * $quantity;
                            
                            $sql = "INSERT INTO order_items (order_id, product_id, product_name, product_sku, quantity, 
                                    unit_price, discount_percent, final_price, total_price) 
                                    VALUES (:order_id, :product_id, :product_name, :product_sku, :quantity, 
                                    :unit_price, :discount_percent, :final_price, :total_price)";
                            
                            $stmt = $conn->prepare($sql);
                            $stmt->execute([
                                ':order_id' => $order_id,
                                ':product_id' => $product_id,
                                ':product_name' => $product['name'],
                                ':product_sku' => $product['sku'],
                                ':quantity' => $quantity,
                                ':unit_price' => $unit_price,
                                ':discount_percent' => $product['discount_percent'],
                                ':final_price' => $unit_price,
                                ':total_price' => $item_total
                            ]);
                            
                            // Update product stock
                            $sql = "UPDATE products SET stock_quantity = stock_quantity - :quantity WHERE id = :id";
                            $stmt = $conn->prepare($sql);
                            $stmt->execute([':quantity' => $quantity, ':id' => $product_id]);
                        }
                        
                        // Log initial status
                        $sql = "INSERT INTO order_status_logs (order_id, status_to, notes, performed_by) 
                                VALUES (:order_id, 'pending', 'Order created', :performed_by)";
                        $stmt = $conn->prepare($sql);
                        $stmt->execute([':order_id' => $order_id, ':performed_by' => $_SESSION['email']]);
                        
                        $conn->commit();
                        
                        $message = "Order created successfully! Order #: $order_number";
                        $message_type = "success";
                    } catch (Exception $e) {
                        $conn->rollBack();
                        $message = "Error: " . $e->getMessage();
                        $message_type = "error";
                    }
                    break;

                case 'update_status':
                    try {
                        $order_id = intval($_POST['order_id']);
                        $new_status = $_POST['new_status'];
                        $notes = trim($_POST['status_notes']);
                        
                        // Get current status
                        $sql = "SELECT order_status FROM orders WHERE id = :id";
                        $stmt = $conn->prepare($sql);
                        $stmt->execute([':id' => $order_id]);
                        $order = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if (!$order) {
                            throw new Exception("Order not found");
                        }
                        
                        $old_status = $order['order_status'];
                        
                        // Update order status
                        $sql = "UPDATE orders SET order_status = :status WHERE id = :id";
                        $stmt = $conn->prepare($sql);
                        $stmt->execute([':status' => $new_status, ':id' => $order_id]);
                        
                        // Log status change
                        $sql = "INSERT INTO order_status_logs (order_id, status_from, status_to, notes, performed_by) 
                                VALUES (:order_id, :status_from, :status_to, :notes, :performed_by)";
                        $stmt = $conn->prepare($sql);
                        $stmt->execute([
                            ':order_id' => $order_id,
                            ':status_from' => $old_status,
                            ':status_to' => $new_status,
                            ':notes' => $notes,
                            ':performed_by' => $_SESSION['email']
                        ]);
                        
                        $message = "Order status updated successfully!";
                        $message_type = "success";
                    } catch (Exception $e) {
                        $message = "Error: " . $e->getMessage();
                        $message_type = "error";
                    }
                    break;

                case 'update_payment':
                    try {
                        $order_id = intval($_POST['order_id']);
                        $payment_status = $_POST['payment_status'];
                        
                        $sql = "UPDATE orders SET payment_status = :status WHERE id = :id";
                        $stmt = $conn->prepare($sql);
                        $stmt->execute([':status' => $payment_status, ':id' => $order_id]);
                        
                        $message = "Payment status updated successfully!";
                        $message_type = "success";
                    } catch (Exception $e) {
                        $message = "Error: " . $e->getMessage();
                        $message_type = "error";
                    }
                    break;
            }
        }
    }

    // Fetch orders with customer and item details
    $orders = [];
    try {
        $sql = "SELECT o.*, 
                COUNT(oi.id) as item_count,
                GROUP_CONCAT(oi.product_name SEPARATOR ', ') as products
                FROM orders o 
                LEFT JOIN order_items oi ON o.id = oi.order_id 
                GROUP BY o.id 
                ORDER BY o.created_at DESC";
        $stmt = $conn->query($sql);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Handle error
    }

    // Fetch products for order creation
    $products = [];
    try {
        $sql = "SELECT id, name, sku, final_price, stock_quantity, quantity_unit FROM products WHERE status = 'active' ORDER BY name";
        $stmt = $conn->query($sql);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Handle error
    }

    // Calculate order statistics
    $total_orders = count($orders);
    $pending_orders = 0;
    $confirmed_orders = 0;
    $delivered_orders = 0;
    $total_revenue = 0;

    foreach ($orders as $order) {
        switch ($order['order_status']) {
            case 'pending':
                $pending_orders++;
                break;
            case 'confirmed':
            case 'processing':
            case 'shipped':
                $confirmed_orders++;
                break;
            case 'delivered':
                $delivered_orders++;
                break;
        }
        if ($order['payment_status'] === 'paid') {
            $total_revenue += $order['final_amount'];
        }
    }
    ?>

    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Order Management - Admin Panel</title>
        <link rel="stylesheet" href="style.css">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
        <style>
            .orders-container {
                display: grid;
                gap: 20px;
            }

            .stats-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 20px;
                margin-bottom: 20px;
            }

            .stat-card {
                background: white;
                padding: 20px;
                border-radius: 10px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                text-align: center;
                border-left: 4px solid #3498db;
            }

            .stat-card.warning {
                border-left-color: #f39c12;
            }

            .stat-card.success {
                border-left-color: #27ae60;
            }

            .stat-card.info {
                border-left-color: #17a2b8;
            }

            .stat-number {
                font-size: 2.5em;
                font-weight: bold;
                margin-bottom: 5px;
            }

            .stat-label {
                color: #7f8c8d;
                font-size: 14px;
            }

            .orders-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 20px;
            }

            .btn-primary {
                background: #3498db;
                color: white;
                padding: 10px 20px;
                border: none;
                border-radius: 5px;
                cursor: pointer;
                text-decoration: none;
                display: inline-flex;
                align-items: center;
                gap: 8px;
                font-size: 14px;
            }

            .btn-primary:hover {
                background: #2980b9;
            }

            .btn-success {
                background: #27ae60;
            }

            .btn-success:hover {
                background: #229954;
            }

            .btn-warning {
                background: #f39c12;
            }

            .btn-warning:hover {
                background: #e67e22;
            }

            .btn-danger {
                background: #e74c3c;
            }

            .btn-danger:hover {
                background: #c0392b;
            }

            .orders-grid {
                display: grid;
                gap: 20px;
            }

            .order-card {
                background: white;
                border-radius: 10px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                overflow: hidden;
                transition: transform 0.3s ease, box-shadow 0.3s ease;
            }

            .order-card:hover {
                transform: translateY(-2px);
                box-shadow: 0 5px 20px rgba(0,0,0,0.15);
            }

            .order-header {
                padding: 20px;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }

            .order-number {
                font-size: 18px;
                font-weight: bold;
            }

            .order-date {
                font-size: 14px;
                opacity: 0.9;
            }

            .order-content {
                padding: 20px;
            }

            .order-customer {
                margin-bottom: 15px;
                padding-bottom: 15px;
                border-bottom: 1px solid #eee;
            }

            .customer-name {
                font-weight: bold;
                color: #2c3e50;
                margin-bottom: 5px;
            }

            .customer-details {
                font-size: 14px;
                color: #7f8c8d;
            }

            .order-items {
                margin-bottom: 15px;
            }

            .item-list {
                background: #f8f9fa;
                padding: 10px;
                border-radius: 5px;
                font-size: 14px;
                color: #34495e;
            }

            .order-summary {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
                gap: 15px;
                margin-bottom: 15px;
            }

            .summary-item {
                text-align: center;
                padding: 10px;
                background: #f8f9fa;
                border-radius: 5px;
            }

            .summary-label {
                font-size: 12px;
                color: #7f8c8d;
                margin-bottom: 5px;
            }

            .summary-value {
                font-weight: bold;
                color: #2c3e50;
            }

            .order-status {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 15px;
            }

            .status-badge {
                padding: 6px 12px;
                border-radius: 20px;
                font-size: 12px;
                font-weight: bold;
            }

            .status-pending {
                background: #fff3cd;
                color: #856404;
            }

            .status-confirmed {
                background: #d1ecf1;
                color: #0c5460;
            }

            .status-processing {
                background: #d4edda;
                color: #155724;
            }

            .status-shipped {
                background: #cce5ff;
                color: #004085;
            }

            .status-delivered {
                background: #d4edda;
                color: #155724;
            }

            .status-cancelled {
                background: #f8d7da;
                color: #721c24;
            }

            .payment-status {
                padding: 4px 8px;
                border-radius: 12px;
                font-size: 11px;
                font-weight: bold;
            }

            .payment-pending {
                background: #fff3cd;
                color: #856404;
            }

            .payment-paid {
                background: #d4edda;
                color: #155724;
            }

            .payment-failed {
                background: #f8d7da;
                color: #721c24;
            }

            .payment-refunded {
                background: #e2e3e5;
                color: #383d41;
            }

            .order-actions {
                display: flex;
                gap: 8px;
                flex-wrap: wrap;
            }

            .btn-small {
                padding: 6px 12px;
                font-size: 12px;
                border-radius: 4px;
            }

            .modal {
                display: none;
                position: fixed;
                z-index: 1000;
                left: 0;
                top: 0;
                width: 100%;
                height: 100%;
                background-color: rgba(0,0,0,0.5);
            }

            .modal-content {
                background-color: white;
                margin: 2% auto;
                padding: 20px;
                border-radius: 10px;
                width: 95%;
                max-width: 800px;
                max-height: 90vh;
                overflow-y: auto;
            }

            .modal-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 20px;
                padding-bottom: 10px;
                border-bottom: 1px solid #eee;
            }

            .close {
                color: #aaa;
                font-size: 28px;
                font-weight: bold;
                cursor: pointer;
            }

            .close:hover {
                color: #000;
            }

            .form-group {
                margin-bottom: 15px;
            }

            .form-group label {
                display: block;
                margin-bottom: 5px;
                font-weight: bold;
                color: #2c3e50;
            }

            .form-group input,
            .form-group select,
            .form-group textarea {
                width: 100%;
                padding: 10px;
                border: 1px solid #ddd;
                border-radius: 5px;
                font-size: 14px;
                box-sizing: border-box;
            }

            .form-group textarea {
                height: 80px;
                resize: vertical;
            }

            .form-group input:focus,
            .form-group select:focus,
            .form-group textarea:focus {
                outline: none;
                border-color: #3498db;
                box-shadow: 0 0 5px rgba(52, 152, 219, 0.3);
            }

            .form-row {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 15px;
            }

            .alert {
                padding: 12px 20px;
                margin-bottom: 20px;
                border-radius: 5px;
                font-weight: bold;
            }

            .alert-success {
                background-color: #d4edda;
                color: #155724;
                border: 1px solid #c3e6cb;
            }

            .alert-error {
                background-color: #f8d7da;
                color: #721c24;
                border: 1px solid #f5c6cb;
            }

            .search-filter {
                display: flex;
                gap: 15px;
                margin-bottom: 20px;
                align-items: center;
            }

            .search-box {
                flex: 1;
                position: relative;
            }

            .search-box input {
                width: 100%;
                padding: 10px 15px 10px 40px;
                border: 1px solid #ddd;
                border-radius: 25px;
                font-size: 14px;
            }

            .search-box i {
                position: absolute;
                left: 15px;
                top: 50%;
                transform: translateY(-50%);
                color: #7f8c8d;
            }

            .filter-dropdown {
                padding: 10px 15px;
                border: 1px solid #ddd;
                border-radius: 5px;
                background: white;
            }

            .order-items-form {
                background: #f8f9fa;
                padding: 15px;
                border-radius: 5px;
                margin-bottom: 20px;
            }

            .item-row {
                display: grid;
                grid-template-columns: 2fr 1fr 1fr 1fr auto;
                gap: 10px;
                align-items: center;
                margin-bottom: 10px;
                padding: 10px;
                background: white;
                border-radius: 5px;
            }

            .item-row input,
            .item-row select {
                padding: 8px;
                border: 1px solid #ddd;
                border-radius: 4px;
                font-size: 14px;
            }

            .remove-item {
                background: #e74c3c;
                color: white;
                border: none;
                border-radius: 4px;
                padding: 8px 12px;
                cursor: pointer;
                font-size: 12px;
            }

            .remove-item:hover {
                background: #c0392b;
            }

            .add-item {
                background: #27ae60;
                color: white;
                border: none;
                border-radius: 4px;
                padding: 10px 15px;
                cursor: pointer;
                font-size: 14px;
                margin-top: 10px;
            }

            .add-item:hover {
                background: #229954;
            }

            .order-timeline {
                background: #f8f9fa;
                padding: 15px;
                border-radius: 5px;
                margin-top: 15px;
            }

            .timeline-item {
                display: flex;
                align-items: center;
                margin-bottom: 10px;
                padding: 8px;
                background: white;
                border-radius: 4px;
            }

            .timeline-status {
                padding: 4px 8px;
                border-radius: 12px;
                font-size: 11px;
                font-weight: bold;
                margin-right: 10px;
            }

            .timeline-time {
                font-size: 12px;
                color: #7f8c8d;
                margin-left: auto;
            }

            @media (max-width: 768px) {
                .form-row {
                    grid-template-columns: 1fr;
                }
                
                .search-filter {
                    flex-direction: column;
                    align-items: stretch;
                }
                
                .stats-grid {
                    grid-template-columns: repeat(2, 1fr);
                }
                
                .item-row {
                    grid-template-columns: 1fr;
                    gap: 5px;
                }
                
                .order-summary {
                    grid-template-columns: repeat(2, 1fr);
                }
            }
        </style>
    </head>
    <body>
        <div class="sidebar">
            <h2>Admin Panel</h2>
            <ul>
                <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="products.php"><i class="fas fa-box"></i> Manage Products</a></li>
                <li><a href="categories.php"><i class="fas fa-tags"></i> Categories</a></li>
                <li><a href="inventory.php"><i class="fas fa-warehouse"></i> Inventory / Stock</a></li>
                <li><a href="orders.php" class="active"><i class="fas fa-shopping-cart"></i> Orders</a></li>
                <li><a href="delivery.php"><i class="fas fa-truck"></i> Delivery Management</a></li>
                <li><a href="customers.php"><i class="fas fa-users"></i> Customers</a></li>
                <li><a href="payments.php"><i class="fas fa-credit-card"></i> Payments / Transactions</a></li>
                <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Analytics & Reports</a></li>
                <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </div>

        <div class="main-content">
            <header>
                <h2><i class="fas fa-shopping-cart"></i> Order Management</h2>
                <div class="user-wrapper">
                    <div>
                        <h4><?php echo $_SESSION['email']; ?></h4>
                        <small>Admin</small>
                    </div>
                </div>
            </header>

            <main>
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type; ?>">
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>

                <div class="orders-container">
                    <!-- Statistics Cards -->
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-number"><?php echo $total_orders; ?></div>
                            <div class="stat-label">Total Orders</div>
                        </div>
                        <div class="stat-card warning">
                            <div class="stat-number"><?php echo $pending_orders; ?></div>
                            <div class="stat-label">Pending Orders</div>
                        </div>
                        <div class="stat-card info">
                            <div class="stat-number"><?php echo $confirmed_orders; ?></div>
                            <div class="stat-label">Processing Orders</div>
                        </div>
                        <div class="stat-card success">
                            <div class="stat-number">₹<?php echo number_format($total_revenue, 2); ?></div>
                            <div class="stat-label">Total Revenue</div>
                        </div>
                    </div>

                    <!-- Orders Header -->
                    <div class="orders-header">
                        <h3>Order Management</h3>
                        <button class="btn-primary" onclick="openModal('create_order')">
                            <i class="fas fa-plus"></i> Create New Order
                        </button>
                    </div>

                    <!-- Search and Filter -->
                    <div class="search-filter">
                        <div class="search-box">
                            <i class="fas fa-search"></i>
                            <input type="text" id="searchInput" placeholder="Search orders..." onkeyup="filterOrders()">
                        </div>
                        <select class="filter-dropdown" id="statusFilter" onchange="filterOrders()">
                            <option value="">All Status</option>
                            <option value="pending">Pending</option>
                            <option value="confirmed">Confirmed</option>
                            <option value="processing">Processing</option>
                            <option value="shipped">Shipped</option>
                            <option value="delivered">Delivered</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                        <select class="filter-dropdown" id="paymentFilter" onchange="filterOrders()">
                            <option value="">All Payments</option>
                            <option value="pending">Pending</option>
                            <option value="paid">Paid</option>
                            <option value="failed">Failed</option>
                            <option value="refunded">Refunded</option>
                        </select>
                    </div>

                    <!-- Orders Grid -->
                    <div class="orders-grid">
                        <?php if (empty($orders)): ?>
                            <div style="text-align: center; padding: 60px 20px; color: #7f8c8d;">
                                <i class="fas fa-shopping-cart fa-3x" style="margin-bottom: 20px;"></i>
                                <h3>No Orders Found</h3>
                                <p>Create your first order to get started.</p>
                                <button class="btn-primary" type="button" onclick="openModal('create_order')">
                                    <i class="fas fa-plus"></i> Create New Order
                                </button>
                            </div>
                        <?php else: ?>
                            <?php foreach ($orders as $order): ?>
                                <div class="order-card" 
                                     data-status="<?php echo strtolower($order['order_status']); ?>" 
                                     data-payment="<?php echo strtolower($order['payment_status']); ?>"
                                     data-customer="<?php echo htmlspecialchars($order['customer_name']); ?>"
                                     data-id="<?php echo $order['id']; ?>">
                                    <div class="order-header">
                                        <span class="order-id">#<?php echo $order['id']; ?></span>
                                        <span class="order-status status-<?php echo strtolower($order['order_status']); ?>">
                                            <?php echo ucfirst($order['order_status']); ?>
                                        </span>
                                    </div>
                                    <div class="order-details">
                                        <div>
                                            <strong>Customer:</strong> <?php echo htmlspecialchars($order['customer_name']); ?>
                                        </div>
                                        <div>
                                            <strong>Phone:</strong> <?php echo htmlspecialchars($order['customer_phone']); ?>
                                        </div>
                                        <div>
                                            <strong>Address:</strong> <?php echo htmlspecialchars($order['delivery_address']); ?>
                                        </div>
                                        <div>
                                            <strong>Order Date:</strong> <?php echo date('d M Y, H:i', strtotime($order['created_at'])); ?>
                                        </div>
                                        <div>
                                            <strong>Total:</strong> ₹<?php echo number_format($order['total_amount'], 2); ?>
                                        </div>
                                        <div>
                                            <strong>Payment:</strong> 
                                            <span class="payment-status status-<?php echo strtolower($order['payment_status']); ?>">
                                                <?php echo ucfirst($order['payment_status']); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="order-actions">
                                        <button class="btn-secondary" onclick="openOrderDetails(<?php echo $order['id']; ?>)">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                        <button class="btn-success" onclick="openModal('update_order', <?php echo $order['id']; ?>)">
                                            <i class="fas fa-edit"></i> Update
                                        </button>
                                        <button class="btn-danger" onclick="deleteOrder(<?php echo $order['id']; ?>)">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>

                        <!-- Order Creation Modal -->
                        <div id="createOrderModal" class="modal" style="display:none;">
                            <div class="modal-content" style="max-width: 500px;">
                                <span class="close" onclick="closeCreateOrderModal()">&times;</span>
                                <h3>Create New Order</h3>
                                <form id="createOrderForm" method="post" action="">
                                    <input type="hidden" name="action" value="create_order">
                                    <div class="form-group">
                                        <label for="customer_name">Customer Name *</label>
                                        <input type="text" id="customer_name" name="customer_name" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="customer_phone">Customer Phone *</label>
                                        <input type="text" id="customer_phone" name="customer_phone" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="delivery_address">Delivery Address *</label>
                                        <textarea id="delivery_address" name="delivery_address" required></textarea>
                                    </div>
                                    <div class="form-group">
                                        <label for="total_amount">Total Amount (₹) *</label>
                                        <input type="number" step="0.01" id="total_amount" name="total_amount" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="payment_method">Payment Method *</label>
                                        <select id="payment_method" name="payment_method" required>
                                            <option value="Cash on Delivery">Cash on Delivery</option>
                                            <option value="Online Payment">Online Payment</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="order_status">Order Status *</label>
                                        <select id="order_status" name="order_status" required>
                                            <option value="Pending">Pending</option>
                                            <option value="Processing">Processing</option>
                                            <option value="Shipped">Shipped</option>
                                            <option value="Delivered">Delivered</option>
                                            <option value="Cancelled">Cancelled</option>
                                        </select>
                                    </div>
                                    <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                                        <button type="button" class="btn btn-warning" onclick="closeCreateOrderModal()">Cancel</button>
                                        <button type="submit" class="btn btn-success">Create Order</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        <button class="btn btn-primary" style="margin-top: 20px;" onclick="openCreateOrderModal()">
                            <i class="fas fa-plus"></i> New Order
                        </button>
                        <script>
                            function openCreateOrderModal() {
                                document.getElementById('createOrderModal').style.display = 'block';
                            }
                            function closeCreateOrderModal() {
                                document.getElementById('createOrderModal').style.display = 'none';
                                document.getElementById('createOrderForm').reset();
                            }
                            // Optional: Close modal when clicking outside
                            window.onclick = function(event) {
                                var modal = document.getElementById('createOrderModal');
                                if (event.target === modal) {
                                    closeCreateOrderModal();
                                }
                            }
                        </script>
                        <?php
                        // Handle new order creation
                        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_order') {
                            $customer_name = trim($_POST['customer_name']);
                            $customer_phone = trim($_POST['customer_phone']);
                            $delivery_address = trim($_POST['delivery_address']);
                            $total_amount = floatval($_POST['total_amount']);
                            $payment_method = $_POST['payment_method'];
                            $order_status = $_POST['order_status'];

                            // Basic validation
                            if ($customer_name && $customer_phone && $delivery_address && $total_amount > 0 && $payment_method && $order_status) {
                                $stmt = $conn->prepare("INSERT INTO orders (customer_name, customer_phone, delivery_address, total_amount, payment_method, order_status, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                                $stmt->execute([
                                    $customer_name,
                                    $customer_phone,
                                    $delivery_address,
                                    $total_amount,
                                    $payment_method,
                                    $order_status
                                ]);
                                echo "<script>window.location.reload();</script>";
                                exit;
                            } else {
                                echo "<div class='alert alert-danger'>Please fill all required fields correctly.</div>";
                            }
                        }
                        ?>