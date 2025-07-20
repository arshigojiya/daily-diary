<?php
session_start();
require_once '../db_connect.php';

if (!isset($_SESSION['admin_loggedin']) || $_SESSION['admin_loggedin'] !== true) {
    header("location: login.php");
    exit;
}

// Create payments table if it doesn't exist
$createPaymentsTable = "CREATE TABLE IF NOT EXISTS payments (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    order_id INT(11) NOT NULL,
    customer_name VARCHAR(100) NOT NULL,
    customer_phone VARCHAR(20) NOT NULL,
    delivery_address TEXT NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    payment_method ENUM('Cash on Delivery', 'Online Payment') DEFAULT 'Cash on Delivery',
    payment_status ENUM('Pending', 'Paid', 'Failed', 'Refunded') DEFAULT 'Pending',
    delivery_status ENUM('Pending', 'Out for Delivery', 'Delivered', 'Cancelled') DEFAULT 'Pending',
    payment_date TIMESTAMP NULL,
    delivery_date TIMESTAMP NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

try {
    $conn->exec($createPaymentsTable);
} catch(PDOException $e) {
    // Table might already exist
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_payment':
                $order_id = $_POST['order_id'];
                $customer_name = $_POST['customer_name'];
                $customer_phone = $_POST['customer_phone'];
                $delivery_address = $_POST['delivery_address'];
                $total_amount = $_POST['total_amount'];
                $payment_method = $_POST['payment_method'];
                $notes = $_POST['notes'];
                
                $sql = "INSERT INTO payments (order_id, customer_name, customer_phone, delivery_address, total_amount, payment_method, notes) 
                        VALUES (:order_id, :customer_name, :customer_phone, :delivery_address, :total_amount, :payment_method, :notes)";
                $stmt = $conn->prepare($sql);
                $stmt->execute([
                    ':order_id' => $order_id,
                    ':customer_name' => $customer_name,
                    ':customer_phone' => $customer_phone,
                    ':delivery_address' => $delivery_address,
                    ':total_amount' => $total_amount,
                    ':payment_method' => $payment_method,
                    ':notes' => $notes
                ]);
                $success_message = "Payment record added successfully!";
                break;
                
            case 'update_status':
                $payment_id = $_POST['payment_id'];
                $payment_status = $_POST['payment_status'];
                $delivery_status = $_POST['delivery_status'];
                $notes = $_POST['update_notes'];
                
                $sql = "UPDATE payments SET payment_status = :payment_status, delivery_status = :delivery_status, notes = :notes";
                if ($payment_status == 'Paid') {
                    $sql .= ", payment_date = NOW()";
                }
                if ($delivery_status == 'Delivered') {
                    $sql .= ", delivery_date = NOW()";
                }
                $sql .= " WHERE id = :payment_id";
                
                $stmt = $conn->prepare($sql);
                $stmt->execute([
                    ':payment_status' => $payment_status,
                    ':delivery_status' => $delivery_status,
                    ':notes' => $notes,
                    ':payment_id' => $payment_id
                ]);
                $success_message = "Payment status updated successfully!";
                break;
                
            case 'delete_payment':
                $payment_id = $_POST['payment_id'];
                $sql = "DELETE FROM payments WHERE id = :payment_id";
                $stmt = $conn->prepare($sql);
                $stmt->execute([':payment_id' => $payment_id]);
                $success_message = "Payment record deleted successfully!";
                break;
        }
    }
}

// Fetch payments with search and filter
$search = isset($_GET['search']) ? $_GET['search'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$method_filter = isset($_GET['method']) ? $_GET['method'] : '';

$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(customer_name LIKE :search OR customer_phone LIKE :search OR order_id LIKE :search)";
    $params[':search'] = "%$search%";
}

if (!empty($status_filter)) {
    $where_conditions[] = "payment_status = :status";
    $params[':status'] = $status_filter;
}

if (!empty($method_filter)) {
    $where_conditions[] = "payment_method = :method";
    $params[':method'] = $method_filter;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

$sql = "SELECT * FROM payments $where_clause ORDER BY created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate statistics
$total_payments = count($payments);
$total_amount = array_sum(array_column($payments, 'total_amount'));
$paid_payments = count(array_filter($payments, function($p) { return $p['payment_status'] == 'Paid'; }));
$pending_payments = count(array_filter($payments, function($p) { return $p['payment_status'] == 'Pending'; }));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Payments</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }
        
        .stat-card h3 {
            margin: 0;
            font-size: 2em;
        }
        
        .stat-card p {
            margin: 5px 0 0 0;
            opacity: 0.9;
        }
        
        .filters {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .filters input, .filters select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        
        .filters button {
            padding: 8px 20px;
            background: #3498db;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        
        .filters button:hover {
            background: #2980b9;
        }
        
        .payments-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .payments-table th, .payments-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .payments-table th {
            background: #f8f9fa;
            font-weight: 600;
        }
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .status-pending { background: #fff3cd; color: #856404; }
        .status-paid { background: #d4edda; color: #155724; }
        .status-failed { background: #f8d7da; color: #721c24; }
        .status-refunded { background: #e2e3e5; color: #383d41; }
        
        .delivery-pending { background: #fff3cd; color: #856404; }
        .delivery-out { background: #cce5ff; color: #004085; }
        .delivery-delivered { background: #d4edda; color: #155724; }
        .delivery-cancelled { background: #f8d7da; color: #721c24; }
        
        .action-buttons {
            display: flex;
            gap: 5px;
        }
        
        .btn-small {
            padding: 4px 8px;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            font-size: 12px;
        }
        
        .btn-edit { background: #ffc107; color: #000; }
        .btn-delete { background: #dc3545; color: white; }
        .btn-view { background: #17a2b8; color: white; }
        
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
            width: 80%;
            max-width: 600px;
        }
        
        .close {
            color: #aaa;
            float: right;
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
            margin-bottom: 15px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        
        .form-group textarea {
            height: 80px;
            resize: vertical;
        }
        
        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .add-payment-btn {
            background: #28a745;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            margin-bottom: 20px;
        }
        
        .add-payment-btn:hover {
            background: #218838;
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
            <li><a href="customers.php">Customers</a></li>
            <li><a href="payments.php" class="active">Payments / Transactions</a></li>
            <li><a href="reports.php">Analytics & Reports</a></li>
            <li><a href="settings.php">Settings</a></li>
            <li><a href="logout.php">Logout</a></li>
        </ul>
    </div>

    <div class="main-content">
        <header>
            <h2>Manage Payments</h2>
            <div class="user-wrapper">
                <div>
                    <h4><?php echo $_SESSION['email']; ?></h4>
                    <small>Admin</small>
                </div>
            </div>
        </header>

        <main>
            <?php if (isset($success_message)): ?>
                <div class="success-message"><?php echo $success_message; ?></div>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h3><?php echo $total_payments; ?></h3>
                    <p>Total Payments</p>
                </div>
                <div class="stat-card">
                    <h3>₹<?php echo number_format($total_amount, 2); ?></h3>
                    <p>Total Amount</p>
                </div>
                <div class="stat-card">
                    <h3><?php echo $paid_payments; ?></h3>
                    <p>Paid Payments</p>
                </div>
                <div class="stat-card">
                    <h3><?php echo $pending_payments; ?></h3>
                    <p>Pending Payments</p>
                </div>
            </div>

            <!-- Add Payment Button -->
            <button class="add-payment-btn" onclick="openAddModal()">+ Add New Payment</button>

            <!-- Filters -->
            <div class="filters">
                <form method="GET" style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
                    <input type="text" name="search" placeholder="Search by customer, phone, or order ID" 
                           value="<?php echo htmlspecialchars($search); ?>">
                    <select name="status">
                        <option value="">All Payment Status</option>
                        <option value="Pending" <?php echo $status_filter == 'Pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="Paid" <?php echo $status_filter == 'Paid' ? 'selected' : ''; ?>>Paid</option>
                        <option value="Failed" <?php echo $status_filter == 'Failed' ? 'selected' : ''; ?>>Failed</option>
                        <option value="Refunded" <?php echo $status_filter == 'Refunded' ? 'selected' : ''; ?>>Refunded</option>
                    </select>
                    <select name="method">
                        <option value="">All Payment Methods</option>
                        <option value="Cash on Delivery" <?php echo $method_filter == 'Cash on Delivery' ? 'selected' : ''; ?>>Cash on Delivery</option>
                        <option value="Online Payment" <?php echo $method_filter == 'Online Payment' ? 'selected' : ''; ?>>Online Payment</option>
                    </select>
                    <button type="submit">Filter</button>
                    <a href="payments.php" style="text-decoration: none; color: #666;">Clear</a>
                </form>
            </div>

            <!-- Payments Table -->
            <table class="payments-table">
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Customer</th>
                        <th>Phone</th>
                        <th>Amount</th>
                        <th>Payment Method</th>
                        <th>Payment Status</th>
                        <th>Delivery Status</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($payments)): ?>
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 40px; color: #666;">
                                No payments found. Add your first payment record.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($payments as $payment): ?>
                            <tr>
                                <td>#<?php echo $payment['order_id']; ?></td>
                                <td><?php echo htmlspecialchars($payment['customer_name']); ?></td>
                                <td><?php echo htmlspecialchars($payment['customer_phone']); ?></td>
                                <td>₹<?php echo number_format($payment['total_amount'], 2); ?></td>
                                <td><?php echo $payment['payment_method']; ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo strtolower($payment['payment_status']); ?>">
                                        <?php echo $payment['payment_status']; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge delivery-<?php echo strtolower(str_replace(' ', '-', $payment['delivery_status'])); ?>">
                                        <?php echo $payment['delivery_status']; ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($payment['created_at'])); ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn-small btn-view" onclick="viewPayment(<?php echo $payment['id']; ?>)">View</button>
                                        <button class="btn-small btn-edit" onclick="editPayment(<?php echo $payment['id']; ?>)">Edit</button>
                                        <button class="btn-small btn-delete" onclick="deletePayment(<?php echo $payment['id']; ?>)">Delete</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </main>
    </div>

    <!-- Add Payment Modal -->
    <div id="addPaymentModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('addPaymentModal')">&times;</span>
            <h2>Add New Payment</h2>
            <form method="POST">
                <input type="hidden" name="action" value="add_payment">
                <div class="form-row">
                    <div class="form-group">
                        <label>Order ID</label>
                        <input type="number" name="order_id" required>
                    </div>
                    <div class="form-group">
                        <label>Customer Name</label>
                        <input type="text" name="customer_name" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Customer Phone</label>
                        <input type="text" name="customer_phone" required>
                    </div>
                    <div class="form-group">
                        <label>Total Amount</label>
                        <input type="number" name="total_amount" step="0.01" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>Delivery Address</label>
                    <textarea name="delivery_address" required></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Payment Method</label>
                        <select name="payment_method" required>
                            <option value="Cash on Delivery">Cash on Delivery</option>
                            <option value="Online Payment">Online Payment</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Notes</label>
                        <textarea name="notes"></textarea>
                    </div>
                </div>
                <button type="submit" class="btn">Add Payment</button>
            </form>
        </div>
    </div>

    <!-- Edit Payment Modal -->
    <div id="editPaymentModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('editPaymentModal')">&times;</span>
            <h2>Update Payment Status</h2>
            <form method="POST">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="payment_id" id="edit_payment_id">
                <div class="form-row">
                    <div class="form-group">
                        <label>Payment Status</label>
                        <select name="payment_status" required>
                            <option value="Pending">Pending</option>
                            <option value="Paid">Paid</option>
                            <option value="Failed">Failed</option>
                            <option value="Refunded">Refunded</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Delivery Status</label>
                        <select name="delivery_status" required>
                            <option value="Pending">Pending</option>
                            <option value="Out for Delivery">Out for Delivery</option>
                            <option value="Delivered">Delivered</option>
                            <option value="Cancelled">Cancelled</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>Notes</label>
                    <textarea name="update_notes"></textarea>
                </div>
                <button type="submit" class="btn">Update Status</button>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deletePaymentModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('deletePaymentModal')">&times;</span>
            <h2>Confirm Delete</h2>
            <p>Are you sure you want to delete this payment record? This action cannot be undone.</p>
            <form method="POST">
                <input type="hidden" name="action" value="delete_payment">
                <input type="hidden" name="payment_id" id="delete_payment_id">
                <button type="submit" class="btn" style="background: #dc3545;">Delete Payment</button>
                <button type="button" class="btn" onclick="closeModal('deletePaymentModal')" style="background: #6c757d;">Cancel</button>
            </form>
        </div>
    </div>

    <script>
        function openAddModal() {
            document.getElementById('addPaymentModal').style.display = 'block';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        function editPayment(paymentId) {
            document.getElementById('edit_payment_id').value = paymentId;
            document.getElementById('editPaymentModal').style.display = 'block';
        }

        function deletePayment(paymentId) {
            document.getElementById('delete_payment_id').value = paymentId;
            document.getElementById('deletePaymentModal').style.display = 'block';
        }

        function viewPayment(paymentId) {
            // You can implement a detailed view modal here
            alert('View payment details for ID: ' + paymentId);
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
    </script>
</body>
</html>