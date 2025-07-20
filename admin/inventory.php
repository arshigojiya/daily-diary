<?php
session_start();
require_once '../db_connect.php';

if (!isset($_SESSION['admin_loggedin']) || $_SESSION['admin_loggedin'] !== true) {
    header("location: login.php");
    exit;
}

// Create inventory_logs table if not exists
$sql = "CREATE TABLE IF NOT EXISTS inventory_logs (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT(6) UNSIGNED NOT NULL,
    action_type ENUM('stock_in', 'stock_out', 'adjustment', 'damaged', 'expired') NOT NULL,
    quantity INT NOT NULL,
    previous_stock INT NOT NULL,
    new_stock INT NOT NULL,
    reason TEXT,
    reference_number VARCHAR(50),
    performed_by VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
)";
$conn->exec($sql);

// Create inventory_settings table if not exists
$sql = "CREATE TABLE IF NOT EXISTS inventory_settings (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    setting_name VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";
$conn->exec($sql);

// Insert default inventory settings if not exists
$default_settings = [
    ['low_stock_threshold', '10'],
    ['critical_stock_threshold', '5'],
    ['auto_alert_enabled', '1'],
    ['stock_expiry_warning_days', '7']
];

foreach ($default_settings as $setting) {
    $sql = "INSERT IGNORE INTO inventory_settings (setting_name, setting_value) VALUES (:name, :value)";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':name' => $setting[0], ':value' => $setting[1]]);
}

// Handle CRUD operations
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'stock_adjustment':
                try {
                    $product_id = intval($_POST['product_id']);
                    $action_type = $_POST['action_type'];
                    $quantity = intval($_POST['quantity']);
                    $reason = trim($_POST['reason']);
                    $reference_number = trim($_POST['reference_number']);
                    
                    // Get current stock
                    $sql = "SELECT stock_quantity, name FROM products WHERE id = :id";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([':id' => $product_id]);
                    $product = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$product) {
                        throw new Exception("Product not found");
                    }
                    
                    $previous_stock = $product['stock_quantity'];
                    
                    // Calculate new stock based on action type
                    switch ($action_type) {
                        case 'stock_in':
                            $new_stock = $previous_stock + $quantity;
                            break;
                        case 'stock_out':
                            $new_stock = $previous_stock - $quantity;
                            if ($new_stock < 0) {
                                throw new Exception("Insufficient stock. Current stock: $previous_stock");
                            }
                            break;
                        case 'adjustment':
                            $new_stock = $quantity;
                            break;
                        case 'damaged':
                        case 'expired':
                            $new_stock = $previous_stock - $quantity;
                            if ($new_stock < 0) {
                                throw new Exception("Insufficient stock for this action");
                            }
                            break;
                        default:
                            throw new Exception("Invalid action type");
                    }
                    
                    // Update product stock
                    $sql = "UPDATE products SET stock_quantity = :new_stock WHERE id = :id";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([':new_stock' => $new_stock, ':id' => $product_id]);
                    
                    // Log the inventory action
                    $sql = "INSERT INTO inventory_logs (product_id, action_type, quantity, previous_stock, new_stock, reason, reference_number, performed_by) 
                            VALUES (:product_id, :action_type, :quantity, :previous_stock, :new_stock, :reason, :reference_number, :performed_by)";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([
                        ':product_id' => $product_id,
                        ':action_type' => $action_type,
                        ':quantity' => $quantity,
                        ':previous_stock' => $previous_stock,
                        ':new_stock' => $new_stock,
                        ':reason' => $reason,
                        ':reference_number' => $reference_number,
                        ':performed_by' => $_SESSION['email']
                    ]);
                    
                    $message = "Stock updated successfully! {$product['name']}: $previous_stock → $new_stock";
                    $message_type = "success";
                } catch (Exception $e) {
                    $message = "Error: " . $e->getMessage();
                    $message_type = "error";
                }
                break;

            case 'bulk_stock_in':
                try {
                    $products = $_POST['products'];
                    $reference_number = trim($_POST['reference_number']);
                    $reason = trim($_POST['reason']);
                    
                    foreach ($products as $product_data) {
                        $product_id = intval($product_data['id']);
                        $quantity = intval($product_data['quantity']);
                        
                        if ($quantity <= 0) continue;
                        
                        // Get current stock
                        $sql = "SELECT stock_quantity, name FROM products WHERE id = :id";
                        $stmt = $conn->prepare($sql);
                        $stmt->execute([':id' => $product_id]);
                        $product = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if (!$product) continue;
                        
                        $previous_stock = $product['stock_quantity'];
                        $new_stock = $previous_stock + $quantity;
                        
                        // Update product stock
                        $sql = "UPDATE products SET stock_quantity = :new_stock WHERE id = :id";
                        $stmt = $conn->prepare($sql);
                        $stmt->execute([':new_stock' => $new_stock, ':id' => $product_id]);
                        
                        // Log the inventory action
                        $sql = "INSERT INTO inventory_logs (product_id, action_type, quantity, previous_stock, new_stock, reason, reference_number, performed_by) 
                                VALUES (:product_id, 'stock_in', :quantity, :previous_stock, :new_stock, :reason, :reference_number, :performed_by)";
                        $stmt = $conn->prepare($sql);
                        $stmt->execute([
                            ':product_id' => $product_id,
                            ':quantity' => $quantity,
                            ':previous_stock' => $previous_stock,
                            ':new_stock' => $new_stock,
                            ':reason' => $reason,
                            ':reference_number' => $reference_number,
                            ':performed_by' => $_SESSION['email']
                        ]);
                    }
                    
                    $message = "Bulk stock-in completed successfully!";
                    $message_type = "success";
                } catch (Exception $e) {
                    $message = "Error: " . $e->getMessage();
                    $message_type = "error";
                }
                break;

            case 'update_settings':
                try {
                    $low_stock_threshold = intval($_POST['low_stock_threshold']);
                    $critical_stock_threshold = intval($_POST['critical_stock_threshold']);
                    $auto_alert_enabled = isset($_POST['auto_alert_enabled']) ? '1' : '0';
                    $stock_expiry_warning_days = intval($_POST['stock_expiry_warning_days']);
                    
                    $settings = [
                        'low_stock_threshold' => $low_stock_threshold,
                        'critical_stock_threshold' => $critical_stock_threshold,
                        'auto_alert_enabled' => $auto_alert_enabled,
                        'stock_expiry_warning_days' => $stock_expiry_warning_days
                    ];
                    
                    foreach ($settings as $name => $value) {
                        $sql = "UPDATE inventory_settings SET setting_value = :value WHERE setting_name = :name";
                        $stmt = $conn->prepare($sql);
                        $stmt->execute([':value' => $value, ':name' => $name]);
                    }
                    
                    $message = "Inventory settings updated successfully!";
                    $message_type = "success";
                } catch (Exception $e) {
                    $message = "Error: " . $e->getMessage();
                    $message_type = "error";
                }
                break;
        }
    }
}

// Fetch inventory settings
$settings = [];
try {
    $sql = "SELECT setting_name, setting_value FROM inventory_settings";
    $stmt = $conn->query($sql);
    $settings_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($settings_data as $setting) {
        $settings[$setting['setting_name']] = $setting['setting_value'];
    }
} catch (PDOException $e) {
    // Handle error
}

// Fetch products with inventory data
$products = [];
try {
    $sql = "SELECT p.*, c.name as category_name,
            (SELECT COUNT(*) FROM inventory_logs WHERE product_id = p.id) as log_count
            FROM products p 
            LEFT JOIN categories c ON p.category_id = c.id 
            ORDER BY p.stock_quantity ASC, p.name ASC";
    $stmt = $conn->query($sql);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Handle error
}

// Calculate inventory statistics
$total_products = count($products);
$low_stock_products = 0;
$out_of_stock_products = 0;
$total_stock_value = 0;

foreach ($products as $product) {
    if ($product['stock_quantity'] <= intval($settings['critical_stock_threshold'] ?? 5)) {
        $out_of_stock_products++;
    } elseif ($product['stock_quantity'] <= intval($settings['low_stock_threshold'] ?? 10)) {
        $low_stock_products++;
    }
    $total_stock_value += $product['stock_quantity'] * $product['final_price'];
}

// Fetch recent inventory logs
$recent_logs = [];
try {
    $sql = "SELECT il.*, p.name as product_name, p.sku 
            FROM inventory_logs il 
            JOIN products p ON il.product_id = p.id 
            ORDER BY il.created_at DESC 
            LIMIT 10";
    $stmt = $conn->query($sql);
    $recent_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Handle error
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Management - Admin Panel</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .inventory-container {
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

        .stat-card.danger {
            border-left-color: #e74c3c;
        }

        .stat-card.success {
            border-left-color: #27ae60;
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

        .inventory-header {
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

        .inventory-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
        }

        .products-section, .logs-section {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .section-header {
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .section-content {
            padding: 20px;
        }

        .product-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid #eee;
            transition: background-color 0.3s ease;
        }

        .product-item:hover {
            background-color: #f8f9fa;
        }

        .product-item:last-child {
            border-bottom: none;
        }

        .product-info {
            flex: 1;
        }

        .product-name {
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .product-details {
            font-size: 14px;
            color: #7f8c8d;
        }

        .stock-info {
            text-align: right;
            margin-right: 15px;
        }

        .stock-quantity {
            font-size: 1.2em;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .stock-status {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }

        .status-normal {
            background: #d4edda;
            color: #155724;
        }

        .status-warning {
            background: #fff3cd;
            color: #856404;
        }

        .status-danger {
            background: #f8d7da;
            color: #721c24;
        }

        .product-actions {
            display: flex;
            gap: 5px;
        }

        .btn-small {
            padding: 5px 10px;
            font-size: 12px;
            border-radius: 3px;
        }

        .log-item {
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }

        .log-item:last-child {
            border-bottom: none;
        }

        .log-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 5px;
        }

        .log-product {
            font-weight: bold;
            color: #2c3e50;
        }

        .log-time {
            font-size: 12px;
            color: #7f8c8d;
        }

        .log-details {
            font-size: 14px;
            color: #34495e;
        }

        .log-action {
            padding: 2px 6px;
            border-radius: 8px;
            font-size: 11px;
            font-weight: bold;
            margin-right: 5px;
        }

        .action-stock_in {
            background: #d4edda;
            color: #155724;
        }

        .action-stock_out {
            background: #f8d7da;
            color: #721c24;
        }

        .action-adjustment {
            background: #d1ecf1;
            color: #0c5460;
        }

        .action-damaged {
            background: #f8d7da;
            color: #721c24;
        }

        .action-expired {
            background: #fff3cd;
            color: #856404;
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
            margin: 5% auto;
            padding: 20px;
            border-radius: 10px;
            width: 90%;
            max-width: 600px;
            max-height: 80vh;
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

        .bulk-stock-form {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .bulk-item {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
            padding: 10px;
            background: white;
            border-radius: 5px;
        }

        .bulk-item input[type="number"] {
            width: 80px;
        }

        .settings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        @media (max-width: 768px) {
            .inventory-grid {
                grid-template-columns: 1fr;
            }
            
            .search-filter {
                flex-direction: column;
                align-items: stretch;
            }
            
            .stats-grid {
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
            <li><a href="inventory.php" class="active"><i class="fas fa-warehouse"></i> Inventory / Stock</a></li>
            <li><a href="orders.php"><i class="fas fa-shopping-cart"></i> Orders</a></li>
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
            <h2><i class="fas fa-warehouse"></i> Inventory Management</h2>
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

            <div class="inventory-container">
                <!-- Statistics Cards -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $total_products; ?></div>
                        <div class="stat-label">Total Products</div>
                    </div>
                    <div class="stat-card warning">
                        <div class="stat-number"><?php echo $low_stock_products; ?></div>
                        <div class="stat-label">Low Stock Items</div>
                    </div>
                    <div class="stat-card danger">
                        <div class="stat-number"><?php echo $out_of_stock_products; ?></div>
                        <div class="stat-label">Out of Stock</div>
                    </div>
                    <div class="stat-card success">
                        <div class="stat-number">₹<?php echo number_format($total_stock_value, 2); ?></div>
                        <div class="stat-label">Total Stock Value</div>
                    </div>
                </div>

                <!-- Inventory Header -->
                <div class="inventory-header">
                    <h3>Stock Management</h3>
                    <div>
                        <button class="btn-primary" onclick="openModal('bulk_stock_in')">
                            <i class="fas fa-plus"></i> Bulk Stock In
                        </button>
                        <button class="btn-primary" onclick="openModal('settings')">
                            <i class="fas fa-cog"></i> Settings
                        </button>
                    </div>
                </div>

                <!-- Search and Filter -->
                <div class="search-filter">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" id="searchInput" placeholder="Search products..." onkeyup="filterProducts()">
                    </div>
                    <select class="filter-dropdown" id="stockFilter" onchange="filterProducts()">
                        <option value="">All Stock Levels</option>
                        <option value="out_of_stock">Out of Stock</option>
                        <option value="low_stock">Low Stock</option>
                        <option value="normal">Normal Stock</option>
                    </select>
                    <select class="filter-dropdown" id="categoryFilter" onchange="filterProducts()">
                        <option value="">All Categories</option>
                        <?php
                        $categories = [];
                        try {
                            $stmt = $conn->query("SELECT DISTINCT c.name FROM categories c JOIN products p ON c.id = p.category_id WHERE c.status = 'active' ORDER BY c.name");
                            $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
                        } catch (PDOException $e) {}
                        foreach ($categories as $category): ?>
                            <option value="<?php echo htmlspecialchars($category); ?>"><?php echo htmlspecialchars($category); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Main Inventory Grid -->
                <div class="inventory-grid">
                    <!-- Products Section -->
                    <div class="products-section">
                        <div class="section-header">
                            <h3><i class="fas fa-boxes"></i> Product Inventory</h3>
                            <span><?php echo count($products); ?> products</span>
                        </div>
                        <div class="section-content">
                            <?php if (empty($products)): ?>
                                <div style="text-align: center; padding: 40px; color: #7f8c8d;">
                                    <i class="fas fa-box-open fa-3x" style="margin-bottom: 20px;"></i>
                                    <h3>No Products Found</h3>
                                    <p>Add products to start managing inventory.</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($products as $product): ?>
                                    <?php
                                    $stock_status = 'normal';
                                    $stock_class = 'status-normal';
                                    if ($product['stock_quantity'] <= intval($settings['critical_stock_threshold'] ?? 5)) {
                                        $stock_status = 'critical';
                                        $stock_class = 'status-danger';
                                    } elseif ($product['stock_quantity'] <= intval($settings['low_stock_threshold'] ?? 10)) {
                                        $stock_status = 'low';
                                        $stock_class = 'status-warning';
                                    }
                                    ?>
                                    <div class="product-item" 
                                         data-name="<?php echo strtolower(htmlspecialchars($product['name'])); ?>"
                                         data-category="<?php echo strtolower(htmlspecialchars($product['category_name'] ?? '')); ?>"
                                         data-stock="<?php echo $stock_status; ?>">
                                        <div class="product-info">
                                            <div class="product-name"><?php echo htmlspecialchars($product['name']); ?></div>
                                            <div class="product-details">
                                                <i class="fas fa-tag"></i> <?php echo htmlspecialchars($product['category_name'] ?? 'Uncategorized'); ?> |
                                                <i class="fas fa-barcode"></i> <?php echo htmlspecialchars($product['sku']); ?> |
                                                <i class="fas fa-rupee-sign"></i> <?php echo number_format($product['final_price'], 2); ?>
                                            </div>
                                        </div>
                                        <div class="stock-info">
                                            <div class="stock-quantity"><?php echo $product['stock_quantity']; ?> <?php echo htmlspecialchars($product['quantity_unit']); ?></div>
                                            <div class="stock-status <?php echo $stock_class; ?>">
                                                <?php echo ucfirst($stock_status); ?> Stock
                                            </div>
                                        </div>
                                        <div class="product-actions">
                                            <button class="btn-primary btn-small" onclick="openModal('stock_adjustment', <?php echo $product['id']; ?>, '<?php echo htmlspecialchars($product['name']); ?>', <?php echo $product['stock_quantity']; ?>)">
                                                <i class="fas fa-edit"></i> Adjust
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Recent Logs Section -->
                    <div class="logs-section">
                        <div class="section-header">
                            <h3><i class="fas fa-history"></i> Recent Activity</h3>
                            <span><?php echo count($recent_logs); ?> logs</span>
                        </div>
                        <div class="section-content">
                            <?php if (empty($recent_logs)): ?>
                                <div style="text-align: center; padding: 20px; color: #7f8c8d;">
                                    <i class="fas fa-history fa-2x" style="margin-bottom: 10px;"></i>
                                    <p>No recent activity</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($recent_logs as $log): ?>
                                    <div class="log-item">
                                        <div class="log-header">
                                            <div class="log-product"><?php echo htmlspecialchars($log['product_name']); ?></div>
                                            <div class="log-time"><?php echo date('M d, H:i', strtotime($log['created_at'])); ?></div>
                                        </div>
                                        <div class="log-details">
                                            <span class="log-action action-<?php echo $log['action_type']; ?>">
                                                <?php echo str_replace('_', ' ', ucfirst($log['action_type'])); ?>
                                            </span>
                                            <?php echo $log['quantity']; ?> units
                                            (<?php echo $log['previous_stock']; ?> → <?php echo $log['new_stock']; ?>)
                                            <?php if ($log['reason']): ?>
                                                <br><small><?php echo htmlspecialchars($log['reason']); ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Stock Adjustment Modal -->
    <div id="stockModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Stock Adjustment</h3>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <form id="stockForm" method="POST">
                <input type="hidden" name="action" value="stock_adjustment">
                <input type="hidden" name="product_id" id="productId">
                
                <div class="form-group">
                    <label for="productName">Product</label>
                    <input type="text" id="productName" readonly>
                </div>

                <div class="form-group">
                    <label for="currentStock">Current Stock</label>
                    <input type="text" id="currentStock" readonly>
                </div>

                <div class="form-group">
                    <label for="action_type">Action Type *</label>
                    <select id="action_type" name="action_type" required onchange="updateQuantityLabel()">
                        <option value="">Select Action</option>
                        <option value="stock_in">Stock In</option>
                        <option value="stock_out">Stock Out</option>
                        <option value="adjustment">Stock Adjustment</option>
                        <option value="damaged">Damaged/Returned</option>
                        <option value="expired">Expired</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="quantity" id="quantityLabel">Quantity *</label>
                    <input type="number" id="quantity" name="quantity" min="1" required>
                </div>

                <div class="form-group">
                    <label for="reason">Reason/Notes</label>
                    <textarea id="reason" name="reason" placeholder="Enter reason for this stock adjustment..."></textarea>
                </div>

                <div class="form-group">
                    <label for="reference_number">Reference Number</label>
                    <input type="text" id="reference_number" name="reference_number" placeholder="Invoice, PO, or reference number">
                </div>

                <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                    <button type="button" class="btn btn-warning" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn btn-success">Update Stock</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Bulk Stock In Modal -->
    <div id="bulkStockModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Bulk Stock In</h3>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <form id="bulkStockForm" method="POST">
                <input type="hidden" name="action" value="bulk_stock_in">
                
                <div class="form-group">
                    <label for="bulk_reference_number">Reference Number</label>
                    <input type="text" id="bulk_reference_number" name="reference_number" placeholder="Invoice or PO number" required>
                </div>

                <div class="form-group">
                    <label for="bulk_reason">Reason/Notes</label>
                    <textarea id="bulk_reason" name="reason" placeholder="Enter reason for bulk stock in..."></textarea>
                </div>

                <div class="bulk-stock-form">
                    <h4>Select Products and Quantities</h4>
                    <?php foreach ($products as $product): ?>
                        <div class="bulk-item">
                            <input type="checkbox" name="products[<?php echo $product['id']; ?>][id]" value="<?php echo $product['id']; ?>" style="width: auto;">
                            <span style="flex: 1;"><?php echo htmlspecialchars($product['name']); ?></span>
                            <span style="color: #7f8c8d;">Current: <?php echo $product['stock_quantity']; ?></span>
                            <input type="number" name="products[<?php echo $product['id']; ?>][quantity]" placeholder="Qty" min="0" style="width: 80px;">
                        </div>
                    <?php endforeach; ?>
                </div>

                <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                    <button type="button" class="btn btn-warning" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn btn-success">Update Stock</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Settings Modal -->
    <div id="settingsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Inventory Settings</h3>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <form id="settingsForm" method="POST">
                <input type="hidden" name="action" value="update_settings">
                
                <div class="settings-grid">
                    <div class="form-group">
                        <label for="low_stock_threshold">Low Stock Threshold</label>
                        <input type="number" id="low_stock_threshold" name="low_stock_threshold" 
                               value="<?php echo $settings['low_stock_threshold'] ?? 10; ?>" min="1" required>
                        <small>Products with stock below this will show as "Low Stock"</small>
                    </div>

                    <div class="form-group">
                        <label for="critical_stock_threshold">Critical Stock Threshold</label>
                        <input type="number" id="critical_stock_threshold" name="critical_stock_threshold" 
                               value="<?php echo $settings['critical_stock_threshold'] ?? 5; ?>" min="0" required>
                        <small>Products with stock below this will show as "Out of Stock"</small>
                    </div>

                    <div class="form-group">
                        <label for="stock_expiry_warning_days">Expiry Warning Days</label>
                        <input type="number" id="stock_expiry_warning_days" name="stock_expiry_warning_days" 
                               value="<?php echo $settings['stock_expiry_warning_days'] ?? 7; ?>" min="1" required>
                        <small>Days before expiry to show warning</small>
                    </div>

                    <div class="form-group">
                        <label>
                            <input type="checkbox" id="auto_alert_enabled" name="auto_alert_enabled" 
                                   <?php echo ($settings['auto_alert_enabled'] ?? '1') == '1' ? 'checked' : ''; ?>>
                            Enable Auto Alerts
                        </label>
                        <small>Automatically alert for low stock items</small>
                    </div>
                </div>

                <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                    <button type="button" class="btn btn-warning" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn btn-success">Save Settings</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal(type, productId = null, productName = '', currentStock = 0) {
            if (type === 'stock_adjustment') {
                document.getElementById('stockModal').style.display = 'block';
                document.getElementById('productId').value = productId;
                document.getElementById('productName').value = productName;
                document.getElementById('currentStock').value = currentStock;
            } else if (type === 'bulk_stock_in') {
                document.getElementById('bulkStockModal').style.display = 'block';
            } else if (type === 'settings') {
                document.getElementById('settingsModal').style.display = 'block';
            }
        }

        function closeModal() {
            document.getElementById('stockModal').style.display = 'none';
            document.getElementById('bulkStockModal').style.display = 'none';
            document.getElementById('settingsModal').style.display = 'none';
        }

        function updateQuantityLabel() {
            const actionType = document.getElementById('action_type').value;
            const quantityLabel = document.getElementById('quantityLabel');
            
            switch (actionType) {
                case 'stock_in':
                    quantityLabel.textContent = 'Quantity to Add *';
                    break;
                case 'stock_out':
                    quantityLabel.textContent = 'Quantity to Remove *';
                    break;
                case 'adjustment':
                    quantityLabel.textContent = 'New Stock Level *';
                    break;
                case 'damaged':
                case 'expired':
                    quantityLabel.textContent = 'Quantity to Remove *';
                    break;
                default:
                    quantityLabel.textContent = 'Quantity *';
            }
        }

        function filterProducts() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const stockFilter = document.getElementById('stockFilter').value;
            const categoryFilter = document.getElementById('categoryFilter').value.toLowerCase();
            
            const products = document.querySelectorAll('.product-item');
            
            products.forEach(product => {
                const name = product.dataset.name;
                const category = product.dataset.category;
                const stock = product.dataset.stock;
                
                const matchesSearch = name.includes(searchTerm);
                const matchesStock = !stockFilter || stock === stockFilter;
                const matchesCategory = !categoryFilter || category.includes(categoryFilter);
                
                if (matchesSearch && matchesStock && matchesCategory) {
                    product.style.display = 'flex';
                } else {
                    product.style.display = 'none';
                }
            });
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            const stockModal = document.getElementById('stockModal');
            const bulkStockModal = document.getElementById('bulkStockModal');
            const settingsModal = document.getElementById('settingsModal');
            
            if (event.target === stockModal) {
                closeModal();
            }
            if (event.target === bulkStockModal) {
                closeModal();
            }
            if (event.target === settingsModal) {
                closeModal();
            }
        }

        // Auto-generate reference number for stock in
        document.getElementById('action_type').addEventListener('change', function() {
            if (this.value === 'stock_in') {
                const refInput = document.getElementById('reference_number');
                if (!refInput.value) {
                    const date = new Date();
                    const refNumber = 'STK-' + date.getFullYear() + 
                                    String(date.getMonth() + 1).padStart(2, '0') + 
                                    String(date.getDate()).padStart(2, '0') + '-' +
                                    Math.floor(Math.random() * 1000).toString().padStart(3, '0');
                    refInput.value = refNumber;
                }
            }
        });
    </script>
</body>
</html>