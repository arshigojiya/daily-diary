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

// Handle CRUD operations
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                try {
                    $name = trim($_POST['name']);
                    $description = trim($_POST['description']);
                    $status = $_POST['status'];

                    $sql = "INSERT INTO categories (name, description, status) VALUES (:name, :description, :status)";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([
                        ':name' => $name,
                        ':description' => $description,
                        ':status' => $status
                    ]);
                    
                    $message = "Category added successfully!";
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
                    $description = trim($_POST['description']);
                    $status = $_POST['status'];

                    $sql = "UPDATE categories SET name = :name, description = :description, status = :status WHERE id = :id";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([
                        ':id' => $id,
                        ':name' => $name,
                        ':description' => $description,
                        ':status' => $status
                    ]);
                    
                    $message = "Category updated successfully!";
                    $message_type = "success";
                } catch (PDOException $e) {
                    $message = "Error: " . $e->getMessage();
                    $message_type = "error";
                }
                break;

            case 'delete':
                try {
                    $id = $_POST['id'];
                    
                    // Check if category is used in products
                    $check_sql = "SELECT COUNT(*) FROM products WHERE category_id = :id";
                    $check_stmt = $conn->prepare($check_sql);
                    $check_stmt->execute([':id' => $id]);
                    $product_count = $check_stmt->fetchColumn();
                    
                    if ($product_count > 0) {
                        $message = "Cannot delete category. It is used by $product_count product(s).";
                        $message_type = "error";
                    } else {
                        $sql = "DELETE FROM categories WHERE id = :id";
                        $stmt = $conn->prepare($sql);
                        $stmt->execute([':id' => $id]);
                        
                        $message = "Category deleted successfully!";
                        $message_type = "success";
                    }
                } catch (PDOException $e) {
                    $message = "Error: " . $e->getMessage();
                    $message_type = "error";
                }
                break;
        }
    }
}

// Fetch categories
$categories = [];
try {
    $sql = "SELECT c.*, COUNT(p.id) as product_count 
            FROM categories c 
            LEFT JOIN products p ON c.id = p.category_id 
            GROUP BY c.id 
            ORDER BY c.created_at DESC";
    $stmt = $conn->query($sql);
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Handle error
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Categories - Admin Panel</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .categories-container {
            display: grid;
            gap: 20px;
        }

        .categories-header {
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

        .categories-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }

        .category-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border-left: 4px solid #3498db;
        }

        .category-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }

        .category-header {
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .category-name {
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .category-stats {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 14px;
        }

        .product-count {
            background: rgba(255,255,255,0.2);
            padding: 4px 8px;
            border-radius: 15px;
            font-weight: bold;
        }

        .category-details {
            padding: 20px;
        }

        .category-description {
            color: #34495e;
            font-size: 14px;
            line-height: 1.4;
            margin-bottom: 15px;
        }

        .category-status {
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

        .status-active {
            background: #d4edda;
            color: #155724;
        }

        .status-inactive {
            background: #f8d7da;
            color: #721c24;
        }

        .category-actions {
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
            max-width: 500px;
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

        .search-box {
            position: relative;
            margin-bottom: 20px;
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
            .categories-grid {
                grid-template-columns: 1fr;
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
            <li><a href="categories.php" class="active"><i class="fas fa-tags"></i> Categories</a></li>
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
            <h2><i class="fas fa-tags"></i> Manage Categories</h2>
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

            <div class="categories-container">
                <div class="categories-header">
                    <h3>Product Categories</h3>
                    <button class="btn-primary" onclick="openModal('add')">
                        <i class="fas fa-plus"></i> Add New Category
                    </button>
                </div>

                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchInput" placeholder="Search categories..." onkeyup="filterCategories()">
                </div>

                <div class="categories-grid" id="categoriesGrid">
                    <?php if (empty($categories)): ?>
                        <div class="empty-state">
                            <i class="fas fa-tags"></i>
                            <h3>No Categories Found</h3>
                            <p>Start by adding your first category to organize products.</p>
                            <button class="btn-primary" onclick="openModal('add')">
                                <i class="fas fa-plus"></i> Add Category
                            </button>
                        </div>
                    <?php else: ?>
                        <?php foreach ($categories as $category): ?>
                            <div class="category-card" data-name="<?php echo strtolower(htmlspecialchars($category['name'])); ?>">
                                <div class="category-header">
                                    <div class="category-name"><?php echo htmlspecialchars($category['name']); ?></div>
                                    <div class="category-stats">
                                        <span>Created: <?php echo date('M d, Y', strtotime($category['created_at'])); ?></span>
                                        <span class="product-count">
                                            <i class="fas fa-box"></i> <?php echo $category['product_count']; ?> products
                                        </span>
                                    </div>
                                </div>
                                <div class="category-details">
                                    <div class="category-description">
                                        <?php echo htmlspecialchars($category['description'] ?: 'No description available'); ?>
                                    </div>
                                    <div class="category-status">
                                        <span class="status-badge status-<?php echo $category['status']; ?>">
                                            <?php echo ucfirst($category['status']); ?>
                                        </span>
                                        <small>ID: <?php echo $category['id']; ?></small>
                                    </div>
                                    <div class="category-actions">
                                        <button class="btn-primary btn-small" onclick="openModal('edit', <?php echo $category['id']; ?>, '<?php echo htmlspecialchars($category['name']); ?>', '<?php echo htmlspecialchars($category['description']); ?>', '<?php echo $category['status']; ?>')">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <?php if ($category['product_count'] == 0): ?>
                                            <button class="btn-danger btn-small" onclick="deleteCategory(<?php echo $category['id']; ?>, '<?php echo htmlspecialchars($category['name']); ?>')">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        <?php else: ?>
                                            <button class="btn-danger btn-small" disabled title="Cannot delete category with products">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Add/Edit Category Modal -->
    <div id="categoryModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Add New Category</h3>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <form id="categoryForm" method="POST">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="id" id="categoryId">
                
                <div class="form-group">
                    <label for="name">Category Name *</label>
                    <input type="text" id="name" name="name" required>
                </div>

                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" placeholder="Enter category description..."></textarea>
                </div>

                <div class="form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>

                <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                    <button type="button" class="btn btn-warning" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn btn-success">Save Category</button>
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
                <p>Are you sure you want to delete the category "<span id="deleteCategoryName"></span>"?</p>
                <p style="color: #e74c3c; font-weight: bold;">This action cannot be undone.</p>
            </div>
            <form method="POST" id="deleteForm">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="deleteCategoryId">
                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" class="btn btn-warning" onclick="closeDeleteModal()">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal(action, categoryId = null, name = '', description = '', status = 'active') {
            const modal = document.getElementById('categoryModal');
            const modalTitle = document.getElementById('modalTitle');
            const formAction = document.getElementById('formAction');
            const form = document.getElementById('categoryForm');
            
            if (action === 'add') {
                modalTitle.textContent = 'Add New Category';
                formAction.value = 'add';
                form.reset();
                document.getElementById('categoryId').value = '';
            } else if (action === 'edit') {
                modalTitle.textContent = 'Edit Category';
                formAction.value = 'update';
                document.getElementById('categoryId').value = categoryId;
                document.getElementById('name').value = name;
                document.getElementById('description').value = description;
                document.getElementById('status').value = status;
            }
            
            modal.style.display = 'block';
        }

        function closeModal() {
            document.getElementById('categoryModal').style.display = 'none';
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').style.display = 'none';
        }

        function deleteCategory(categoryId, categoryName) {
            document.getElementById('deleteCategoryId').value = categoryId;
            document.getElementById('deleteCategoryName').textContent = categoryName;
            document.getElementById('deleteModal').style.display = 'block';
        }

        function filterCategories() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const categories = document.querySelectorAll('.category-card');
            
            categories.forEach(category => {
                const name = category.dataset.name;
                
                if (name.includes(searchTerm)) {
                    category.style.display = 'block';
                } else {
                    category.style.display = 'none';
                }
            });
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            const categoryModal = document.getElementById('categoryModal');
            const deleteModal = document.getElementById('deleteModal');
            
            if (event.target === categoryModal) {
                closeModal();
            }
            if (event.target === deleteModal) {
                closeDeleteModal();
            }
        }
    </script>
</body>
</html>