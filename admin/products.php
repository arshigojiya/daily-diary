<?php
session_start();
require_once '../db_connect.php';

if (!isset($_SESSION['admin_loggedin']) || $_SESSION['admin_loggedin'] !== true) {
    header("location: login.php");
    exit;
}

// Create categories table if not exists
$sql = "CREATE TABLE IF NOT EXISTS categories (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
$conn->exec($sql);

// Create products table if not exists
$sql = "CREATE TABLE IF NOT EXISTS products (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    category_id INT(6) UNSIGNED,
    sku VARCHAR(50) UNIQUE NOT NULL,
    description TEXT,
    image VARCHAR(255),
    price DECIMAL(10,2) NOT NULL,
    discount_percent DECIMAL(5,2) DEFAULT 0,
    final_price DECIMAL(10,2) GENERATED ALWAYS AS (price - (price * discount_percent / 100)) STORED,
    quantity_unit VARCHAR(50) NOT NULL,
    stock_quantity INT NOT NULL DEFAULT 0,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
)";
$conn->exec($sql);

// Handle CRUD operations
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                try {
                    $name = trim($_POST['name']);
                    $category_id = $_POST['category_id'];
                    $sku = trim($_POST['sku']);
                    $description = trim($_POST['description']);
                    $price = floatval($_POST['price']);
                    $discount_percent = floatval($_POST['discount_percent']);
                    $quantity_unit = trim($_POST['quantity_unit']);
                    $stock_quantity = intval($_POST['stock_quantity']);
                    $status = $_POST['status'];

                    // Handle image upload
                    $image = '';
                    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
                        $upload_dir = '../images/products/';
                        if (!is_dir($upload_dir)) {
                            mkdir($upload_dir, 0777, true);
                        }
                        
                        $file_extension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
                        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                        
                        if (in_array($file_extension, $allowed_extensions)) {
                            $new_filename = uniqid() . '.' . $file_extension;
                            $upload_path = $upload_dir . $new_filename;
                            
                            if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
                                $image = 'images/products/' . $new_filename;
                            }
                        }
                    }

                    $sql = "INSERT INTO products (name, category_id, sku, description, image, price, discount_percent, quantity_unit, stock_quantity, status) 
                            VALUES (:name, :category_id, :sku, :description, :image, :price, :discount_percent, :quantity_unit, :stock_quantity, :status)";
                    
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([
                        ':name' => $name,
                        ':category_id' => $category_id,
                        ':sku' => $sku,
                        ':description' => $description,
                        ':image' => $image,
                        ':price' => $price,
                        ':discount_percent' => $discount_percent,
                        ':quantity_unit' => $quantity_unit,
                        ':stock_quantity' => $stock_quantity,
                        ':status' => $status
                    ]);
                    
                    $message = "Product added successfully!";
                    $message_type = "success";
                } catch (PDOException $e) {
                    $message = "Error: " . $e->getMessage();
                    $message_type = "error";
                }
                break;

            case 'update':
                try {
                    $id = $_POST['id'];
                    $name = trim($_POST['name']);
                    $category_id = $_POST['category_id'];
                    $sku = trim($_POST['sku']);
                    $description = trim($_POST['description']);
                    $price = floatval($_POST['price']);
                    $discount_percent = floatval($_POST['discount_percent']);
                    $quantity_unit = trim($_POST['quantity_unit']);
                    $stock_quantity = intval($_POST['stock_quantity']);
                    $status = $_POST['status'];

                    // Handle image upload
                    $image_sql = '';
                    $params = [
                        ':id' => $id,
                        ':name' => $name,
                        ':category_id' => $category_id,
                        ':sku' => $sku,
                        ':description' => $description,
                        ':price' => $price,
                        ':discount_percent' => $discount_percent,
                        ':quantity_unit' => $quantity_unit,
                        ':stock_quantity' => $stock_quantity,
                        ':status' => $status
                    ];

                    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
                        $upload_dir = '../images/products/';
                        if (!is_dir($upload_dir)) {
                            mkdir($upload_dir, 0777, true);
                        }
                        
                        $file_extension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
                        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                        
                        if (in_array($file_extension, $allowed_extensions)) {
                            $new_filename = uniqid() . '.' . $file_extension;
                            $upload_path = $upload_dir . $new_filename;
                            
                            if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
                                $image_sql = ', image = :image';
                                $params[':image'] = 'images/products/' . $new_filename;
                            }
                        }
                    }

                    $sql = "UPDATE products SET name = :name, category_id = :category_id, sku = :sku, 
                            description = :description, price = :price, discount_percent = :discount_percent, 
                            quantity_unit = :quantity_unit, stock_quantity = :stock_quantity, status = :status" . $image_sql . " 
                            WHERE id = :id";
                    
                    $stmt = $conn->prepare($sql);
                    $stmt->execute($params);
                    
                    $message = "Product updated successfully!";
                    $message_type = "success";
                } catch (PDOException $e) {
                    $message = "Error: " . $e->getMessage();
                    $message_type = "error";
                }
                break;

            case 'delete':
                try {
                    $id = $_POST['id'];
                    $sql = "DELETE FROM products WHERE id = :id";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([':id' => $id]);
                    
                    $message = "Product deleted successfully!";
                    $message_type = "success";
                } catch (PDOException $e) {
                    $message = "Error: " . $e->getMessage();
                    $message_type = "error";
                }
                break;
        }
    }
}

// Fetch categories for dropdown
$categories = [];
try {
    $stmt = $conn->query("SELECT id, name FROM categories WHERE status = 'active' ORDER BY name");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Handle error
}

// Fetch products with category names
$products = [];
try {
    $sql = "SELECT p.*, c.name as category_name 
            FROM products p 
            LEFT JOIN categories c ON p.category_id = c.id 
            ORDER BY p.created_at DESC";
    $stmt = $conn->query($sql);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Handle error
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Products - Admin Panel</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .products-container {
            display: grid;
            gap: 20px;
        }

        .products-header {
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

        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }

        .product-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }

        .product-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6c757d;
        }

        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .product-details {
            padding: 20px;
        }

        .product-name {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 8px;
            color: #2c3e50;
        }

        .product-category {
            color: #7f8c8d;
            font-size: 14px;
            margin-bottom: 8px;
        }

        .product-sku {
            color: #95a5a6;
            font-size: 12px;
            margin-bottom: 10px;
        }

        .product-description {
            color: #34495e;
            font-size: 14px;
            margin-bottom: 15px;
            line-height: 1.4;
        }

        .product-price {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
        }

        .price-original {
            font-size: 16px;
            color: #7f8c8d;
            text-decoration: line-through;
        }

        .price-final {
            font-size: 20px;
            font-weight: bold;
            color: #27ae60;
        }

        .product-stock {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding: 8px 12px;
            background: #f8f9fa;
            border-radius: 5px;
        }

        .stock-quantity {
            font-weight: bold;
            color: #2c3e50;
        }

        .stock-status {
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: bold;
        }

        .status-active {
            background: #d4edda;
            color: #155724;
        }

        .status-inactive {
            background: #f8d7da;
            color: #721c24;
        }

        .product-actions {
            display: flex;
            gap: 8px;
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

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
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
            height: 100px;
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

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #7f8c8d;
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 20px;
            color: #bdc3c7;
        }

        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .products-grid {
                grid-template-columns: 1fr;
            }
            
            .search-filter {
                flex-direction: column;
                align-items: stretch;
            }
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <h2>Admin Panel</h2>
        <ul>
            <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="products.php" class="active"><i class="fas fa-box"></i> Manage Products</a></li>
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
            <h2><i class="fas fa-box"></i> Manage Products</h2>
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

            <div class="products-container">
                <div class="products-header">
                    <h3>Product Inventory</h3>
                    <button class="btn-primary" onclick="openModal('add')">
                        <i class="fas fa-plus"></i> Add New Product
                    </button>
                </div>

                <div class="search-filter">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" id="searchInput" placeholder="Search products..." onkeyup="filterProducts()">
                    </div>
                    <select class="filter-dropdown" id="categoryFilter" onchange="filterProducts()">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo htmlspecialchars($category['name']); ?>">
                                <?php echo htmlspecialchars($category['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <select class="filter-dropdown" id="statusFilter" onchange="filterProducts()">
                        <option value="">All Status</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>

                <div class="products-grid" id="productsGrid">
                    <?php if (empty($products)): ?>
                        <div class="empty-state">
                            <i class="fas fa-box-open"></i>
                            <h3>No Products Found</h3>
                            <p>Start by adding your first product to the inventory.</p>
                            <button class="btn-primary" onclick="openModal('add')">
                                <i class="fas fa-plus"></i> Add Product
                            </button>
                        </div>
                    <?php else: ?>
                        <?php foreach ($products as $product): ?>
                            <div class="product-card" data-name="<?php echo strtolower(htmlspecialchars($product['name'])); ?>" 
                                 data-category="<?php echo strtolower(htmlspecialchars($product['category_name'] ?? '')); ?>"
                                 data-status="<?php echo $product['status']; ?>">
                                <div class="product-image">
                                    <?php if ($product['image']): ?>
                                        <img src="../<?php echo htmlspecialchars($product['image']); ?>" 
                                             alt="<?php echo htmlspecialchars($product['name']); ?>">
                                    <?php else: ?>
                                        <i class="fas fa-image fa-3x"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="product-details">
                                    <div class="product-name"><?php echo htmlspecialchars($product['name']); ?></div>
                                    <div class="product-category">
                                        <i class="fas fa-tag"></i> 
                                        <?php echo htmlspecialchars($product['category_name'] ?? 'Uncategorized'); ?>
                                    </div>
                                    <div class="product-sku">
                                        <i class="fas fa-barcode"></i> <?php echo htmlspecialchars($product['sku']); ?>
                                    </div>
                                    <div class="product-description">
                                        <?php echo htmlspecialchars(substr($product['description'], 0, 100)); ?>
                                        <?php if (strlen($product['description']) > 100): ?>...<?php endif; ?>
                                    </div>
                                    <div class="product-price">
                                        <?php if ($product['discount_percent'] > 0): ?>
                                            <span class="price-original">₹<?php echo number_format($product['price'], 2); ?></span>
                                        <?php endif; ?>
                                        <span class="price-final">₹<?php echo number_format($product['final_price'], 2); ?></span>
                                        <?php if ($product['discount_percent'] > 0): ?>
                                            <span style="color: #e74c3c; font-size: 12px;">(-<?php echo $product['discount_percent']; ?>%)</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="product-stock">
                                        <span class="stock-quantity">
                                            <i class="fas fa-cubes"></i> <?php echo $product['stock_quantity']; ?> <?php echo htmlspecialchars($product['quantity_unit']); ?>
                                        </span>
                                        <span class="stock-status status-<?php echo $product['status']; ?>">
                                            <?php echo ucfirst($product['status']); ?>
                                        </span>
                                    </div>
                                    <div class="product-actions">
                                        <button class="btn-primary btn-small" onclick="openModal('edit', <?php echo $product['id']; ?>)">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <button class="btn-danger btn-small" onclick="deleteProduct(<?php echo $product['id']; ?>, '<?php echo htmlspecialchars($product['name']); ?>')">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Add/Edit Product Modal -->
    <div id="productModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Add New Product</h3>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <form id="productForm" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="id" id="productId">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="name">Product Name *</label>
                        <input type="text" id="name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="sku">SKU / Product Code *</label>
                        <input type="text" id="sku" name="sku" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="category_id">Category</label>
                        <select id="category_id" name="category_id">
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>">
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="quantity_unit">Quantity Unit *</label>
                        <select id="quantity_unit" name="quantity_unit" required>
                            <option value="">Select Unit</option>
                            <option value="500ml">500ml</option>
                            <option value="1L">1L</option>
                            <option value="2L">2L</option>
                            <option value="250g">250g</option>
                            <option value="500g">500g</option>
                            <option value="1kg">1kg</option>
                            <option value="piece">Piece</option>
                            <option value="dozen">Dozen</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="price">Price (₹) *</label>
                        <input type="number" id="price" name="price" step="0.01" min="0" required>
                    </div>
                    <div class="form-group">
                        <label for="discount_percent">Discount (%)</label>
                        <input type="number" id="discount_percent" name="discount_percent" step="0.01" min="0" max="100" value="0">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="stock_quantity">Stock Quantity *</label>
                        <input type="number" id="stock_quantity" name="stock_quantity" min="0" required>
                    </div>
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select id="status" name="status">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" placeholder="Enter product description..."></textarea>
                </div>

                <div class="form-group">
                    <label for="image">Product Image</label>
                    <input type="file" id="image" name="image" accept="image/*">
                    <small>Supported formats: JPG, PNG, GIF, WebP. Max size: 5MB</small>
                </div>

                <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                    <button type="button" class="btn btn-warning" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn btn-success">Save Product</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content" style="max-width: 400px;">
            <div class="modal-header">
                <h3>Confirm Delete</h3>
                <span class="close" onclick="closeDeleteModal()">&times;</span>
            </div>
            <div style="padding: 20px 0;">
                <p>Are you sure you want to delete the product "<span id="deleteProductName"></span>"?</p>
                <p style="color: #e74c3c; font-weight: bold;">This action cannot be undone.</p>
            </div>
            <form method="POST" id="deleteForm">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="deleteProductId">
                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" class="btn btn-warning" onclick="closeDeleteModal()">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal(action, productId = null) {
            const modal = document.getElementById('productModal');
            const modalTitle = document.getElementById('modalTitle');
            const formAction = document.getElementById('formAction');
            const form = document.getElementById('productForm');
            
            if (action === 'add') {
                modalTitle.textContent = 'Add New Product';
                formAction.value = 'add';
                form.reset();
                document.getElementById('productId').value = '';
            } else if (action === 'edit' && productId) {
                modalTitle.textContent = 'Edit Product';
                formAction.value = 'update';
                document.getElementById('productId').value = productId;
                
                // Fetch product data and populate form
                fetchProductData(productId);
            }
            
            modal.style.display = 'block';
        }

        function closeModal() {
            document.getElementById('productModal').style.display = 'none';
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').style.display = 'none';
        }

        function deleteProduct(productId, productName) {
            document.getElementById('deleteProductId').value = productId;
            document.getElementById('deleteProductName').textContent = productName;
            document.getElementById('deleteModal').style.display = 'block';
        }

        function fetchProductData(productId) {
            fetch(`get_product.php?id=${productId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        alert('Error: ' + data.error);
                        return;
                    }
                    
                    // Populate form fields
                    document.getElementById('name').value = data.name;
                    document.getElementById('category_id').value = data.category_id || '';
                    document.getElementById('sku').value = data.sku;
                    document.getElementById('description').value = data.description || '';
                    document.getElementById('price').value = data.price;
                    document.getElementById('discount_percent').value = data.discount_percent;
                    document.getElementById('quantity_unit').value = data.quantity_unit;
                    document.getElementById('stock_quantity').value = data.stock_quantity;
                    document.getElementById('status').value = data.status;
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error fetching product data');
                });
        }

        function filterProducts() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const categoryFilter = document.getElementById('categoryFilter').value.toLowerCase();
            const statusFilter = document.getElementById('statusFilter').value.toLowerCase();
            
            const products = document.querySelectorAll('.product-card');
            
            products.forEach(product => {
                const name = product.dataset.name;
                const category = product.dataset.category;
                const status = product.dataset.status;
                
                const matchesSearch = name.includes(searchTerm);
                const matchesCategory = !categoryFilter || category.includes(categoryFilter);
                const matchesStatus = !statusFilter || status === statusFilter;
                
                if (matchesSearch && matchesCategory && matchesStatus) {
                    product.style.display = 'block';
                } else {
                    product.style.display = 'none';
                }
            });
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            const productModal = document.getElementById('productModal');
            const deleteModal = document.getElementById('deleteModal');
            
            if (event.target === productModal) {
                closeModal();
            }
            if (event.target === deleteModal) {
                closeDeleteModal();
            }
        }

        // Auto-generate SKU when product name is entered
        document.getElementById('name').addEventListener('input', function() {
            const name = this.value;
            const sku = name.replace(/[^a-zA-Z0-9]/g, '').toUpperCase().substring(0, 8) + 
                       Math.floor(Math.random() * 1000).toString().padStart(3, '0');
            document.getElementById('sku').value = sku;
        });

        // Calculate final price when price or discount changes
        function calculateFinalPrice() {
            const price = parseFloat(document.getElementById('price').value) || 0;
            const discount = parseFloat(document.getElementById('discount_percent').value) || 0;
            const finalPrice = price - (price * discount / 100);
            
            // You can display this somewhere if needed
            console.log('Final Price:', finalPrice.toFixed(2));
        }

        document.getElementById('price').addEventListener('input', calculateFinalPrice);
        document.getElementById('discount_percent').addEventListener('input', calculateFinalPrice);
    </script>
</body>
</html>