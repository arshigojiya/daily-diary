<?php
session_start();

if (!isset($_SESSION['admin_loggedin']) || $_SESSION['admin_loggedin'] !== true) {
    header("location: login.php");
    exit;
}

require_once '../db_connect.php';

// Create customers table if it doesn't exist
$createTableSQL = "CREATE TABLE IF NOT EXISTS customers (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    phone VARCHAR(20) NOT NULL,
    address TEXT,
    city VARCHAR(50),
    state VARCHAR(50),
    zip_code VARCHAR(10),
    registration_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('active', 'inactive') DEFAULT 'active',
    total_orders INT(11) DEFAULT 0,
    total_spent DECIMAL(10,2) DEFAULT 0.00
)";

try {
    $conn->exec($createTableSQL);
} catch(PDOException $e) {
    // Table might already exist
}

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $name = trim($_POST['name']);
                $email = trim($_POST['email']);
                $phone = trim($_POST['phone']);
                $address = trim($_POST['address']);
                $city = trim($_POST['city']);
                $state = trim($_POST['state']);
                $zip_code = trim($_POST['zip_code']);
                
                if (empty($name) || empty($email) || empty($phone)) {
                    $message = "Name, email, and phone are required fields.";
                    $messageType = "error";
                } else {
                    try {
                        $sql = "INSERT INTO customers (name, email, phone, address, city, state, zip_code) 
                                VALUES (:name, :email, :phone, :address, :city, :state, :zip_code)";
                        $stmt = $conn->prepare($sql);
                        $stmt->bindParam(':name', $name);
                        $stmt->bindParam(':email', $email);
                        $stmt->bindParam(':phone', $phone);
                        $stmt->bindParam(':address', $address);
                        $stmt->bindParam(':city', $city);
                        $stmt->bindParam(':state', $state);
                        $stmt->bindParam(':zip_code', $zip_code);
                        $stmt->execute();
                        
                        $message = "Customer added successfully!";
                        $messageType = "success";
                    } catch(PDOException $e) {
                        if ($e->getCode() == 23000) {
                            $message = "Email already exists!";
                        } else {
                            $message = "Error adding customer: " . $e->getMessage();
                        }
                        $messageType = "error";
                    }
                }
                break;
                
            case 'edit':
                $id = $_POST['id'];
                $name = trim($_POST['name']);
                $email = trim($_POST['email']);
                $phone = trim($_POST['phone']);
                $address = trim($_POST['address']);
                $city = trim($_POST['city']);
                $state = trim($_POST['state']);
                $zip_code = trim($_POST['zip_code']);
                $status = $_POST['status'];
                
                try {
                    $sql = "UPDATE customers SET name=:name, email=:email, phone=:phone, 
                            address=:address, city=:city, state=:state, zip_code=:zip_code, status=:status 
                            WHERE id=:id";
                    $stmt = $conn->prepare($sql);
                    $stmt->bindParam(':id', $id);
                    $stmt->bindParam(':name', $name);
                    $stmt->bindParam(':email', $email);
                    $stmt->bindParam(':phone', $phone);
                    $stmt->bindParam(':address', $address);
                    $stmt->bindParam(':city', $city);
                    $stmt->bindParam(':state', $state);
                    $stmt->bindParam(':zip_code', $zip_code);
                    $stmt->bindParam(':status', $status);
                    $stmt->execute();
                    
                    $message = "Customer updated successfully!";
                    $messageType = "success";
                } catch(PDOException $e) {
                    $message = "Error updating customer: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
                
            case 'delete':
                $id = $_POST['id'];
                try {
                    $sql = "DELETE FROM customers WHERE id = :id";
                    $stmt = $conn->prepare($sql);
                    $stmt->bindParam(':id', $id);
                    $stmt->execute();
                    
                    $message = "Customer deleted successfully!";
                    $messageType = "success";
                } catch(PDOException $e) {
                    $message = "Error deleting customer: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
        }
    }
}

// Get customers for display
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(name LIKE :search OR email LIKE :search OR phone LIKE :search)";
    $params[':search'] = "%$search%";
}

if (!empty($status_filter)) {
    $where_conditions[] = "status = :status";
    $params[':status'] = $status_filter;
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

$sql = "SELECT * FROM customers $where_clause ORDER BY registration_date DESC";
$stmt = $conn->prepare($sql);

foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}

$stmt->execute();
$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get customer for editing
$edit_customer = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $edit_id = $_GET['edit'];
    $sql = "SELECT * FROM customers WHERE id = :id";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':id', $edit_id);
    $stmt->execute();
    $edit_customer = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Customers</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .customers-container {
            display: flex;
            gap: 20px;
        }
        
        .customers-list {
            flex: 2;
        }
        
        .customer-form {
            flex: 1;
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            height: fit-content;
        }
        
        .search-filters {
            background: #fff;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .search-filters form {
            display: flex;
            gap: 15px;
            align-items: end;
            flex-wrap: wrap;
        }
        
        .form-row {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .form-group {
            flex: 1;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #333;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            box-sizing: border-box;
        }
        
        .form-group textarea {
            height: 80px;
            resize: vertical;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: #3498db;
            color: white;
        }
        
        .btn-primary:hover {
            background: #2980b9;
        }
        
        .btn-success {
            background: #27ae60;
            color: white;
        }
        
        .btn-success:hover {
            background: #229954;
        }
        
        .btn-warning {
            background: #f39c12;
            color: white;
        }
        
        .btn-warning:hover {
            background: #e67e22;
        }
        
        .btn-danger {
            background: #e74c3c;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c0392b;
        }
        
        .btn-secondary {
            background: #95a5a6;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #7f8c8d;
        }
        
        .customers-table {
            background: #fff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .customers-table table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .customers-table th,
        .customers-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .customers-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
        }
        
        .customers-table tr:hover {
            background: #f8f9fa;
        }
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-active {
            background: #d4edda;
            color: #155724;
        }
        
        .status-inactive {
            background: #f8d7da;
            color: #721c24;
        }
        
        .action-buttons {
            display: flex;
            gap: 5px;
        }
        
        .action-buttons .btn {
            padding: 6px 10px;
            font-size: 12px;
        }
        
        .message {
            padding: 10px 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-card h3 {
            margin: 0 0 10px 0;
            color: #333;
        }
        
        .stat-card .number {
            font-size: 2em;
            font-weight: bold;
            color: #3498db;
        }
        
        .stat-card .label {
            color: #666;
            font-size: 14px;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        
        .empty-state i {
            font-size: 3em;
            color: #ddd;
            margin-bottom: 20px;
        }
        
        @media (max-width: 768px) {
            .customers-container {
                flex-direction: column;
            }
            
            .search-filters form {
                flex-direction: column;
            }
            
            .form-row {
                flex-direction: column;
            }
            
            .customers-table {
                overflow-x: auto;
            }
        }
    </style>
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
            <li><a href="customers.php" class="active">Customers</a></li>
            <li><a href="payments.php">Payments / Transactions</a></li>
            <li><a href="reports.php">Analytics & Reports</a></li>
            <li><a href="settings.php">Settings</a></li>
            <li><a href="logout.php">Logout</a></li>
        </ul>
    </div>

    <div class="main-content">
        <header>
            <h2>Manage Customers</h2>
            <div class="user-wrapper">
                <div>
                    <h4><?php echo $_SESSION['email']; ?></h4>
                    <small>Admin</small>
                </div>
            </div>
        </header>

        <?php if (!empty($message)): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <?php
        $total_customers = $conn->query("SELECT COUNT(*) FROM customers")->fetchColumn();
        $active_customers = $conn->query("SELECT COUNT(*) FROM customers WHERE status = 'active'")->fetchColumn();
        $new_customers_month = $conn->query("SELECT COUNT(*) FROM customers WHERE MONTH(registration_date) = MONTH(CURRENT_DATE()) AND YEAR(registration_date) = YEAR(CURRENT_DATE())")->fetchColumn();
        $total_revenue = $conn->query("SELECT SUM(total_spent) FROM customers")->fetchColumn();
        ?>
        
        <div class="stats-cards">
            <div class="stat-card">
                <h3><i class="fas fa-users"></i></h3>
                <div class="number"><?php echo $total_customers; ?></div>
                <div class="label">Total Customers</div>
            </div>
            <div class="stat-card">
                <h3><i class="fas fa-user-check"></i></h3>
                <div class="number"><?php echo $active_customers; ?></div>
                <div class="label">Active Customers</div>
            </div>
            <div class="stat-card">
                <h3><i class="fas fa-user-plus"></i></h3>
                <div class="number"><?php echo $new_customers_month; ?></div>
                <div class="label">New This Month</div>
            </div>
            <div class="stat-card">
                <h3><i class="fas fa-dollar-sign"></i></h3>
                <div class="number">$<?php echo number_format($total_revenue, 2); ?></div>
                <div class="label">Total Revenue</div>
            </div>
        </div>

        <!-- Search and Filters -->
        <div class="search-filters">
            <form method="GET">
                <div class="form-group">
                    <label for="search">Search Customers</label>
                    <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                           placeholder="Search by name, email, or phone...">
                </div>
                <div class="form-group">
                    <label for="status">Status Filter</label>
                    <select id="status" name="status">
                        <option value="">All Status</option>
                        <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Search
                    </button>
                    <a href="customers.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Clear
                    </a>
                </div>
            </form>
        </div>

        <div class="customers-container">
            <!-- Customers List -->
            <div class="customers-list">
                <div class="customers-table">
                    <?php if (empty($customers)): ?>
                        <div class="empty-state">
                            <i class="fas fa-users"></i>
                            <h3>No customers found</h3>
                            <p>Start by adding your first customer using the form on the right.</p>
                        </div>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Location</th>
                                    <th>Status</th>
                                    <th>Orders</th>
                                    <th>Spent</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($customers as $customer): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($customer['name']); ?></strong>
                                            <br>
                                            <small>Joined: <?php echo date('M d, Y', strtotime($customer['registration_date'])); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($customer['email']); ?></td>
                                        <td><?php echo htmlspecialchars($customer['phone']); ?></td>
                                        <td>
                                            <?php 
                                            $location = [];
                                            if (!empty($customer['city'])) $location[] = $customer['city'];
                                            if (!empty($customer['state'])) $location[] = $customer['state'];
                                            echo implode(', ', $location);
                                            ?>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?php echo $customer['status']; ?>">
                                                <?php echo ucfirst($customer['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo $customer['total_orders']; ?></td>
                                        <td>$<?php echo number_format($customer['total_spent'], 2); ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <a href="?edit=<?php echo $customer['id']; ?>" class="btn btn-warning">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this customer?')">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?php echo $customer['id']; ?>">
                                                    <button type="submit" class="btn btn-danger">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Customer Form -->
            <div class="customer-form">
                <h3>
                    <?php if ($edit_customer): ?>
                        <i class="fas fa-edit"></i> Edit Customer
                    <?php else: ?>
                        <i class="fas fa-user-plus"></i> Add New Customer
                    <?php endif; ?>
                </h3>
                
                <form method="POST">
                    <input type="hidden" name="action" value="<?php echo $edit_customer ? 'edit' : 'add'; ?>">
                    <?php if ($edit_customer): ?>
                        <input type="hidden" name="id" value="<?php echo $edit_customer['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label for="name">Full Name *</label>
                        <input type="text" id="name" name="name" required 
                               value="<?php echo $edit_customer ? htmlspecialchars($edit_customer['name']) : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email Address *</label>
                        <input type="email" id="email" name="email" required 
                               value="<?php echo $edit_customer ? htmlspecialchars($edit_customer['email']) : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="phone">Phone Number *</label>
                        <input type="tel" id="phone" name="phone" required 
                               value="<?php echo $edit_customer ? htmlspecialchars($edit_customer['phone']) : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="address">Address</label>
                        <textarea id="address" name="address"><?php echo $edit_customer ? htmlspecialchars($edit_customer['address']) : ''; ?></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="city">City</label>
                            <input type="text" id="city" name="city" 
                                   value="<?php echo $edit_customer ? htmlspecialchars($edit_customer['city']) : ''; ?>">
                        </div>
                        <div class="form-group">
                            <label for="state">State</label>
                            <input type="text" id="state" name="state" 
                                   value="<?php echo $edit_customer ? htmlspecialchars($edit_customer['state']) : ''; ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="zip_code">ZIP Code</label>
                        <input type="text" id="zip_code" name="zip_code" 
                               value="<?php echo $edit_customer ? htmlspecialchars($edit_customer['zip_code']) : ''; ?>">
                    </div>
                    
                    <?php if ($edit_customer): ?>
                        <div class="form-group">
                            <label for="status">Status</label>
                            <select id="status" name="status">
                                <option value="active" <?php echo $edit_customer['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo $edit_customer['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <?php if ($edit_customer): ?>
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-save"></i> Update Customer
                            </button>
                            <a href="customers.php" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                        <?php else: ?>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Add Customer
                            </button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Auto-hide messages after 5 seconds
        setTimeout(function() {
            const messages = document.querySelectorAll('.message');
            messages.forEach(function(message) {
                message.style.display = 'none';
            });
        }, 5000);

        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const name = document.getElementById('name').value.trim();
            const email = document.getElementById('email').value.trim();
            const phone = document.getElementById('phone').value.trim();
            
            if (!name || !email || !phone) {
                e.preventDefault();
                alert('Please fill in all required fields (Name, Email, Phone)');
                return false;
            }
            
            // Basic email validation
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                e.preventDefault();
                alert('Please enter a valid email address');
                return false;
            }
        });
    </script>
</body>
</html>