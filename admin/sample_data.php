<?php
session_start();
require_once '../db_connect.php';

if (!isset($_SESSION['admin_loggedin']) || $_SESSION['admin_loggedin'] !== true) {
    header("location: login.php");
    exit;
}

// Sample categories
$sample_categories = [
    ['name' => 'Milk Products', 'description' => 'Fresh dairy milk and milk-based products'],
    ['name' => 'Butter & Ghee', 'description' => 'Pure butter and clarified ghee products'],
    ['name' => 'Paneer & Cheese', 'description' => 'Fresh paneer and cheese varieties'],
    ['name' => 'Curd & Yogurt', 'description' => 'Fresh curd and yogurt products'],
    ['name' => 'Organic Products', 'description' => 'Certified organic dairy products']
];

// Sample products
$sample_products = [
    [
        'name' => 'Cow Milk - Full Cream',
        'category_id' => 1,
        'sku' => 'MILK1001',
        'description' => 'Rich, fresh full-cream cow milk sourced from healthy cows. High in protein and calcium.',
        'price' => 60.00,
        'discount_percent' => 0,
        'quantity_unit' => '1L',
        'stock_quantity' => 250,
        'status' => 'active'
    ],
    [
        'name' => 'Buffalo Milk - Premium',
        'category_id' => 1,
        'sku' => 'MILK1002',
        'description' => 'Premium buffalo milk with higher fat content. Perfect for making rich desserts.',
        'price' => 80.00,
        'discount_percent' => 10,
        'quantity_unit' => '1L',
        'stock_quantity' => 150,
        'status' => 'active'
    ],
    [
        'name' => 'Fresh Butter - Unsalted',
        'category_id' => 2,
        'sku' => 'BUTT1001',
        'description' => 'Pure unsalted butter made from fresh cream. Perfect for cooking and baking.',
        'price' => 120.00,
        'discount_percent' => 5,
        'quantity_unit' => '500g',
        'stock_quantity' => 100,
        'status' => 'active'
    ],
    [
        'name' => 'Pure Ghee - Traditional',
        'category_id' => 2,
        'sku' => 'GHEE1001',
        'description' => 'Traditional clarified butter (ghee) made using age-old methods. Rich in flavor.',
        'price' => 200.00,
        'discount_percent' => 0,
        'quantity_unit' => '500g',
        'stock_quantity' => 75,
        'status' => 'active'
    ],
    [
        'name' => 'Fresh Paneer - Homemade',
        'category_id' => 3,
        'sku' => 'PANE1001',
        'description' => 'Fresh homemade paneer made from pure milk. Soft and crumbly texture.',
        'price' => 180.00,
        'discount_percent' => 15,
        'quantity_unit' => '500g',
        'stock_quantity' => 50,
        'status' => 'active'
    ],
    [
        'name' => 'Greek Yogurt - Natural',
        'category_id' => 4,
        'sku' => 'YOGU1001',
        'description' => 'Thick and creamy Greek yogurt with natural probiotics. No added sugar.',
        'price' => 90.00,
        'discount_percent' => 0,
        'quantity_unit' => '500g',
        'stock_quantity' => 80,
        'status' => 'active'
    ],
    [
        'name' => 'Organic Cow Milk',
        'category_id' => 5,
        'sku' => 'ORG1001',
        'description' => 'Certified organic cow milk from grass-fed cows. No antibiotics or hormones.',
        'price' => 100.00,
        'discount_percent' => 0,
        'quantity_unit' => '1L',
        'stock_quantity' => 60,
        'status' => 'active'
    ],
    [
        'name' => 'Low Fat Milk',
        'category_id' => 1,
        'sku' => 'MILK1003',
        'description' => 'Low fat milk perfect for health-conscious consumers. Rich in protein.',
        'price' => 50.00,
        'discount_percent' => 0,
        'quantity_unit' => '1L',
        'stock_quantity' => 200,
        'status' => 'active'
    ]
];

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // Insert categories
        $category_ids = [];
        foreach ($sample_categories as $category) {
            $sql = "INSERT IGNORE INTO categories (name, description, status) VALUES (:name, :description, 'active')";
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                ':name' => $category['name'],
                ':description' => $category['description']
            ]);
            
            // Get the category ID
            $category_id = $conn->lastInsertId();
            if (!$category_id) {
                // If category already exists, get its ID
                $sql = "SELECT id FROM categories WHERE name = :name";
                $stmt = $conn->prepare($sql);
                $stmt->execute([':name' => $category['name']]);
                $category_id = $stmt->fetchColumn();
            }
            $category_ids[] = $category_id;
        }
        
        // Insert products
        foreach ($sample_products as $index => $product) {
            $product['category_id'] = $category_ids[$product['category_id'] - 1] ?? null;
            
            $sql = "INSERT IGNORE INTO products (name, category_id, sku, description, price, discount_percent, quantity_unit, stock_quantity, status) 
                    VALUES (:name, :category_id, :sku, :description, :price, :discount_percent, :quantity_unit, :stock_quantity, :status)";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                ':name' => $product['name'],
                ':category_id' => $product['category_id'],
                ':sku' => $product['sku'],
                ':description' => $product['description'],
                ':price' => $product['price'],
                ':discount_percent' => $product['discount_percent'],
                ':quantity_unit' => $product['quantity_unit'],
                ':stock_quantity' => $product['stock_quantity'],
                ':status' => $product['status']
            ]);
        }
        
        $message = "Sample data added successfully!";
        $message_type = "success";
        
    } catch (PDOException $e) {
        $message = "Error: " . $e->getMessage();
        $message_type = "error";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Sample Data - Admin Panel</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sample-data-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
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
        
        .btn-primary {
            background: #3498db;
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            margin: 10px 5px;
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
        
        .sample-data-preview {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin: 20px 0;
        }
        
        .preview-section {
            margin-bottom: 30px;
        }
        
        .preview-section h3 {
            color: #2c3e50;
            border-bottom: 2px solid #3498db;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        
        .preview-item {
            background: #f8f9fa;
            padding: 10px;
            margin: 5px 0;
            border-radius: 5px;
            border-left: 4px solid #3498db;
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
            <h2><i class="fas fa-database"></i> Add Sample Data</h2>
            <div class="user-wrapper">
                <div>
                    <h4><?php echo $_SESSION['email']; ?></h4>
                    <small>Admin</small>
                </div>
            </div>
        </header>

        <main>
            <div class="sample-data-container">
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type; ?>">
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>

                <div class="sample-data-preview">
                    <h3><i class="fas fa-info-circle"></i> Sample Data Preview</h3>
                    
                    <div class="preview-section">
                        <h3><i class="fas fa-tags"></i> Categories (<?php echo count($sample_categories); ?>)</h3>
                        <?php foreach ($sample_categories as $category): ?>
                            <div class="preview-item">
                                <strong><?php echo htmlspecialchars($category['name']); ?></strong>
                                <br>
                                <small><?php echo htmlspecialchars($category['description']); ?></small>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="preview-section">
                        <h3><i class="fas fa-box"></i> Products (<?php echo count($sample_products); ?>)</h3>
                        <?php foreach ($sample_products as $product): ?>
                            <div class="preview-item">
                                <strong><?php echo htmlspecialchars($product['name']); ?></strong>
                                <br>
                                <small>
                                    SKU: <?php echo htmlspecialchars($product['sku']); ?> | 
                                    Price: ₹<?php echo number_format($product['price'], 2); ?> | 
                                    Unit: <?php echo htmlspecialchars($product['quantity_unit']); ?>
                                    <?php if ($product['discount_percent'] > 0): ?>
                                        | Discount: <?php echo $product['discount_percent']; ?>%
                                    <?php endif; ?>
                                </small>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <form method="POST">
                    <button type="submit" class="btn-primary btn-success">
                        <i class="fas fa-plus"></i> Add Sample Data to Database
                    </button>
                    <a href="products.php" class="btn-primary">
                        <i class="fas fa-arrow-left"></i> Back to Products
                    </a>
                </form>
            </div>
        </main>
    </div>
</body>
</html> 