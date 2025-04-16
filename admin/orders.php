<?php
// Only start the session if one doesn't already exist
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../includes/functions.php';

// Check if user is admin
if (!is_admin()) {
    // Redirect to login page
    header('Location: ../login.php');
    exit;
}

$error = '';
$success = '';

// Process order actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Update order status
    if (isset($_POST['update_status'])) {
        $transaction_id = (int)$_POST['transaction_id'];
        $new_status = clean_input($_POST['status']);
        
        // Validate status
        $valid_statuses = ['pending', 'completed', 'cancelled', 'refunded'];
        if (!in_array($new_status, $valid_statuses)) {
            $error = "Invalid status value.";
        } else {
            // Update transaction status
            $sql = "UPDATE transactions SET status = ?, updated_at = NOW() WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("si", $new_status, $transaction_id);
            
            if ($stmt->execute()) {
                $success = "Order status updated successfully.";
            } else {
                $error = "Error updating order status: " . $conn->error;
            }
        }
    }
}

// Get orders with related data
$sql = "SELECT t.*, 
        p.name as product_name, 
        p.image as product_image,
        u_buyer.username as buyer_username,
        u_buyer.email as buyer_email,
        u_seller.username as seller_username,
        u_seller.email as seller_email
        FROM transactions t
        LEFT JOIN products p ON t.product_id = p.id
        LEFT JOIN users u_buyer ON t.buyer_id = u_buyer.id
        LEFT JOIN users u_seller ON t.seller_id = u_seller.id
        ORDER BY t.created_at DESC";
$result = $conn->query($sql);
$orders = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }
}

// Get order statistics
$stats = [
    'total' => 0,
    'completed' => 0,
    'pending' => 0,
    'cancelled' => 0,
    'refunded' => 0,
    'total_value' => 0,
    'completed_value' => 0
];

$stats_sql = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
    SUM(CASE WHEN status = 'refunded' THEN 1 ELSE 0 END) as refunded,
    SUM(amount) as total_value,
    SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END) as completed_value
    FROM transactions";
$stats_result = $conn->query($stats_sql);

if ($stats_result && $stats_result->num_rows > 0) {
    $stats = $stats_result->fetch_assoc();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Orders - DevMarket Philippines</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include 'includes/sidebar.php'; ?>
            
            <!-- Main Content -->
            <div class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Manage Orders</h1>
                </div>
                
                <?php if ($error): ?>
                <div class="alert alert-danger">
                    <?php echo $error; ?>
                </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                <div class="alert alert-success">
                    <?php echo $success; ?>
                </div>
                <?php endif; ?>
                
                <!-- Order Statistics -->
                <div class="row mb-4">
                    <div class="col-md-3 mb-3">
                        <div class="card bg-primary text-white h-100">
                            <div class="card-body">
                                <h5 class="card-title">Total Orders</h5>
                                <h2><?php echo number_format($stats['total']); ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card bg-success text-white h-100">
                            <div class="card-body">
                                <h5 class="card-title">Completed</h5>
                                <h2><?php echo number_format($stats['completed']); ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card bg-warning h-100">
                            <div class="card-body">
                                <h5 class="card-title">Pending</h5>
                                <h2><?php echo number_format($stats['pending']); ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card bg-danger text-white h-100">
                            <div class="card-body">
                                <h5 class="card-title">Cancelled/Refunded</h5>
                                <h2><?php echo number_format($stats['cancelled'] + $stats['refunded']); ?></h2>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row mb-4">
                    <div class="col-md-6 mb-3">
                        <div class="card bg-info text-white h-100">
                            <div class="card-body">
                                <h5 class="card-title">Total Order Value</h5>
                                <h2>₱<?php echo number_format($stats['total_value'], 2); ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="card bg-success text-white h-100">
                            <div class="card-body">
                                <h5 class="card-title">Completed Order Value</h5>
                                <h2>₱<?php echo number_format($stats['completed_value'], 2); ?></h2>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Filter Form -->
                <div class="card mb-4">
                    <div class="card-header bg-secondary text-white">
                        <h5 class="mb-0">Filter Orders</h5>
                    </div>
                    <div class="card-body">
                        <form action="" method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label for="status" class="form-label">Status</label>
                                <select name="status" id="status" class="form-select">
                                    <option value="">All Statuses</option>
                                    <option value="pending" <?php echo isset($_GET['status']) && $_GET['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="completed" <?php echo isset($_GET['status']) && $_GET['status'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                    <option value="cancelled" <?php echo isset($_GET['status']) && $_GET['status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                    <option value="refunded" <?php echo isset($_GET['status']) && $_GET['status'] == 'refunded' ? 'selected' : ''; ?>>Refunded</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="date_from" class="form-label">Date From</label>
                                <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo isset($_GET['date_from']) ? $_GET['date_from'] : ''; ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="date_to" class="form-label">Date To</label>
                                <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo isset($_GET['date_to']) ? $_GET['date_to'] : ''; ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="search" class="form-label">Search</label>
                                <input type="text" class="form-control" id="search" name="search" placeholder="Order ID, Product, Buyer..." value="<?php echo isset($_GET['search']) ? $_GET['search'] : ''; ?>">
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">Apply Filters</button>
                                <a href="orders.php" class="btn btn-secondary">Reset</a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Orders Table -->
                <div class="card mb-4">
                    <div class="card-header bg-dark text-white">
                        <h5 class="mb-0">All Orders</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Order ID</th>
                                        <th>Product</th>
                                        <th>Buyer</th>
                                        <th>Seller</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Payment Method</th>
                                        <th>Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($orders) > 0): ?>
                                        <?php foreach ($orders as $order): ?>
                                            <tr>
                                                <td>#<?php echo $order['id']; ?></td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <?php if (!empty($order['product_image'])): ?>
                                                            <img src="../uploads/products/<?php echo $order['product_image']; ?>" class="me-2" style="width: 40px; height: 40px; object-fit: cover;" alt="<?php echo $order['product_name']; ?>">
                                                        <?php else: ?>
                                                            <div class="bg-secondary text-white rounded me-2 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                                                <i class="fas fa-box"></i>
                                                            </div>
                                                        <?php endif; ?>
                                                        <span><?php echo $order['product_name']; ?></span>
                                                    </div>
                                                </td>
                                                <td><?php echo $order['buyer_username']; ?><br><small class="text-muted"><?php echo $order['buyer_email']; ?></small></td>
                                                <td><?php echo $order['seller_username']; ?><br><small class="text-muted"><?php echo $order['seller_email']; ?></small></td>
                                                <td>₱<?php echo number_format($order['amount'], 2); ?></td>
                                                <td>
                                                    <?php if ($order['status'] == 'completed'): ?>
                                                        <span class="badge bg-success">Completed</span>
                                                    <?php elseif ($order['status'] == 'pending'): ?>
                                                        <span class="badge bg-warning text-dark">Pending</span>
                                                    <?php elseif ($order['status'] == 'cancelled'): ?>
                                                        <span class="badge bg-danger">Cancelled</span>
                                                    <?php elseif ($order['status'] == 'refunded'): ?>
                                                        <span class="badge bg-danger">Refunded</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary"><?php echo ucfirst($order['status']); ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo ucfirst($order['payment_method'] ?? 'Not specified'); ?></td>
                                                <td><?php echo date('M d, Y h:i A', strtotime($order['created_at'])); ?></td>
                                                <td>
                                                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#viewOrderModal<?php echo $order['id']; ?>">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#updateStatusModal<?php echo $order['id']; ?>">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                            
                                            <!-- View Order Modal -->
                                            <div class="modal fade" id="viewOrderModal<?php echo $order['id']; ?>" tabindex="-1" aria-labelledby="viewOrderModalLabel<?php echo $order['id']; ?>" aria-hidden="true">
                                                <div class="modal-dialog modal-lg">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title" id="viewOrderModalLabel<?php echo $order['id']; ?>">Order Details #<?php echo $order['id']; ?></h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <div class="row mb-3">
                                                                <div class="col-md-6">
                                                                    <h6>Order Information</h6>
                                                                    <p>
                                                                        <strong>Order ID:</strong> #<?php echo $order['id']; ?><br>
                                                                        <strong>Date:</strong> <?php echo date('M d, Y h:i A', strtotime($order['created_at'])); ?><br>
                                                                        <strong>Status:</strong> 
                                                                        <?php if ($order['status'] == 'completed'): ?>
                                                                            <span class="badge bg-success">Completed</span>
                                                                        <?php elseif ($order['status'] == 'pending'): ?>
                                                                            <span class="badge bg-warning text-dark">Pending</span>
                                                                        <?php elseif ($order['status'] == 'cancelled'): ?>
                                                                            <span class="badge bg-danger">Cancelled</span>
                                                                        <?php elseif ($order['status'] == 'refunded'): ?>
                                                                            <span class="badge bg-danger">Refunded</span>
                                                                        <?php else: ?>
                                                                            <span class="badge bg-secondary"><?php echo ucfirst($order['status']); ?></span>
                                                                        <?php endif; ?><br>
                                                                        <strong>Payment Method:</strong> <?php echo ucfirst($order['payment_method'] ?? 'Not specified'); ?><br>
                                                                        <strong>Payment Reference:</strong> <?php echo $order['payment_reference'] ?? 'N/A'; ?><br>
                                                                        <strong>Amount:</strong> ₱<?php echo number_format($order['amount'], 2); ?>
                                                                    </p>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <h6>Product Information</h6>
                                                                    <p>
                                                                        <strong>Product:</strong> <?php echo $order['product_name']; ?><br>
                                                                        <strong>Product ID:</strong> <?php echo $order['product_id']; ?><br>
                                                                        <strong>Seller:</strong> <?php echo $order['seller_username']; ?> (<?php echo $order['seller_email']; ?>)<br>
                                                                        <strong>Buyer:</strong> <?php echo $order['buyer_username']; ?> (<?php echo $order['buyer_email']; ?>)
                                                                    </p>
                                                                </div>
                                                            </div>
                                                            
                                                            <?php if (!empty($order['notes'])): ?>
                                                            <div class="row mb-3">
                                                                <div class="col-12">
                                                                    <h6>Order Notes</h6>
                                                                    <p><?php echo nl2br(htmlspecialchars($order['notes'])); ?></p>
                                                                </div>
                                                            </div>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#updateStatusModal<?php echo $order['id']; ?>" data-bs-dismiss="modal">
                                                                Update Status
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <!-- Update Status Modal -->
                                            <div class="modal fade" id="updateStatusModal<?php echo $order['id']; ?>" tabindex="-1" aria-labelledby="updateStatusModalLabel<?php echo $order['id']; ?>" aria-hidden="true">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title" id="updateStatusModalLabel<?php echo $order['id']; ?>">Update Order Status</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <form method="POST">
                                                            <div class="modal-body">
                                                                <input type="hidden" name="transaction_id" value="<?php echo $order['id']; ?>">
                                                                
                                                                <div class="mb-3">
                                                                    <label for="orderStatus<?php echo $order['id']; ?>" class="form-label">Status</label>
                                                                    <select class="form-select" id="orderStatus<?php echo $order['id']; ?>" name="status" required>
                                                                        <option value="pending" <?php echo $order['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                                        <option value="completed" <?php echo $order['status'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                                                        <option value="cancelled" <?php echo $order['status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                                                        <option value="refunded" <?php echo $order['status'] == 'refunded' ? 'selected' : ''; ?>>Refunded</option>
                                                                    </select>
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                <button type="submit" name="update_status" class="btn btn-primary">Update Status</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="9" class="text-center">No orders found.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JavaScript -->
    <script src="../assets/js/script.js"></script>
    <script src="../assets/js/admin.js"></script>
</body>
</html> 