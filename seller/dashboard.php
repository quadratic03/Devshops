<?php
// Only start the session if one doesn't already exist
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../includes/functions.php';

// Check if user is logged in and is a seller
if (!is_logged_in() || !is_seller()) {
    // Redirect to login page
    header('Location: ../login.php');
    exit;
}

// Get seller statistics
$seller_id = $_SESSION['user_id'];

// Get total products count
$products_sql = "SELECT COUNT(*) as total_products FROM products WHERE seller_id = ?";
$stmt = $conn->prepare($products_sql);
$stmt->bind_param("i", $seller_id);
$stmt->execute();
$products_result = $stmt->get_result();
$products_data = $products_result->fetch_assoc();
$total_products = $products_data['total_products'];

// Get total transactions count
$transactions_sql = "SELECT 
                COUNT(*) as total_orders,
                SUM(amount) as total_sales,
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_orders,
                COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_orders
               FROM transactions
               WHERE seller_id = ?";
$stmt = $conn->prepare($transactions_sql);
$stmt->bind_param("i", $seller_id);
$stmt->execute();
$transactions_result = $stmt->get_result();
$transactions_data = $transactions_result->fetch_assoc();
$total_orders = $transactions_data['total_orders'] ?? 0;
$total_sales = $transactions_data['total_sales'] ?? 0;
$completed_orders = $transactions_data['completed_orders'] ?? 0;
$pending_orders = $transactions_data['pending_orders'] ?? 0;

// Get recent transactions (last 5)
$recent_transactions_sql = "SELECT 
                        t.id, t.id as order_number, t.amount as total_amount, t.status, t.created_at,
                        u.username as buyer_name, u.email as buyer_email,
                        p.name as product_name
                      FROM transactions t
                      JOIN users u ON t.buyer_id = u.id
                      JOIN products p ON t.product_id = p.id
                      WHERE t.seller_id = ?
                      ORDER BY t.created_at DESC
                      LIMIT 5";
$stmt = $conn->prepare($recent_transactions_sql);
$stmt->bind_param("i", $seller_id);
$stmt->execute();
$recent_transactions_result = $stmt->get_result();
$recent_orders = [];
while ($row = $recent_transactions_result->fetch_assoc()) {
    $recent_orders[] = $row;
}

// Get latest products (last 5)
$latest_products_sql = "SELECT id, name, price, image, status, created_at 
                       FROM products 
                       WHERE seller_id = ? 
                       ORDER BY created_at DESC 
                       LIMIT 5";
$stmt = $conn->prepare($latest_products_sql);
$stmt->bind_param("i", $seller_id);
$stmt->execute();
$latest_products_result = $stmt->get_result();
$latest_products = [];
while ($row = $latest_products_result->fetch_assoc()) {
    $latest_products[] = $row;
}

// Get recent messages from buyers (last 5)
$recent_messages_sql = "
    SELECT 
        m.id, m.message, m.created_at, m.is_read,
        u.id as buyer_id, u.username as buyer_name,
        p.id as product_id, p.name as product_name
    FROM messages m
    JOIN users u ON m.sender_id = u.id
    LEFT JOIN source_access_requests sar ON (sar.buyer_id = u.id AND sar.seller_id = ?)
    LEFT JOIN products p ON sar.product_id = p.id
    WHERE m.receiver_id = ? AND u.role = 'buyer'
    ORDER BY m.created_at DESC
    LIMIT 5
";
$stmt = $conn->prepare($recent_messages_sql);
$stmt->bind_param("ii", $seller_id, $seller_id);
$stmt->execute();
$recent_messages_result = $stmt->get_result();
$recent_messages = [];
while ($row = $recent_messages_result->fetch_assoc()) {
    $recent_messages[] = $row;
}

// Count unread messages
$unread_messages = 0;
try {
    $messages_query = "SELECT COUNT(*) as count FROM messages 
                       WHERE receiver_id = ? AND is_read = FALSE 
                       AND sender_id IN (SELECT id FROM users WHERE role = 'buyer')";
    $stmt = $conn->prepare($messages_query);
    $stmt->bind_param("i", $seller_id);
    $stmt->execute();
    $messages_result = $stmt->get_result();
    if ($messages_result && $messages_result->num_rows > 0) {
        $unread_messages = $messages_result->fetch_assoc()['count'];
    }
} catch (mysqli_sql_exception $e) {
    // Table doesn't exist or other database error
    error_log("Database error counting messages in seller dashboard: " . $e->getMessage());
}

// Count pending access requests
$pending_requests = 0;
try {
    $access_query = "SELECT COUNT(*) as count FROM source_access_requests WHERE seller_id = ? AND status = 'pending'";
    $stmt = $conn->prepare($access_query);
    $stmt->bind_param("i", $seller_id);
    $stmt->execute();
    $access_result = $stmt->get_result();
    if ($access_result && $access_result->num_rows > 0) {
        $pending_requests = $access_result->fetch_assoc()['count'];
    }
} catch (mysqli_sql_exception $e) {
    // Table doesn't exist or other database error
    // Just continue with pending_requests = 0
    error_log("Database error in seller dashboard: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seller Dashboard - DevMarket Philippines</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Chart.js -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include_once __DIR__ . '/../includes/navbar.php'; ?>
    
    <div class="container my-4">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3">
                <?php include_once __DIR__ . '/includes/sidebar.php'; ?>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-9">
                <h2 class="mb-4"><i class="fas fa-tachometer-alt me-2"></i> Seller Dashboard</h2>
                
                <!-- Stats Cards -->
                <div class="row mb-4">
                    <div class="col-md-3 mb-3">
                        <div class="card bg-primary text-white h-100">
                            <div class="card-body">
                                <h5 class="card-title"><i class="fas fa-box me-2"></i> Products</h5>
                                <h2 class="display-4"><?php echo $total_products; ?></h2>
                            </div>
                            <div class="card-footer d-flex">
                                <a href="my_products.php" class="text-white text-decoration-none">View Details
                                    <i class="fas fa-arrow-circle-right ms-2"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card bg-success text-white h-100">
                            <div class="card-body">
                                <h5 class="card-title"><i class="fas fa-shopping-cart me-2"></i> Orders</h5>
                                <h2 class="display-4"><?php echo $total_orders; ?></h2>
                            </div>
                            <div class="card-footer d-flex">
                                <a href="my_transactions.php" class="text-white text-decoration-none">View Details
                                    <i class="fas fa-arrow-circle-right ms-2"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card bg-info text-white h-100">
                            <div class="card-body">
                                <h5 class="card-title"><i class="fas fa-money-bill-wave me-2"></i> Sales</h5>
                                <h2 class="display-4">₱<?php echo number_format($total_sales, 2); ?></h2>
                            </div>
                            <div class="card-footer d-flex">
                                <a href="my_transactions.php" class="text-white text-decoration-none">View Details
                                    <i class="fas fa-arrow-circle-right ms-2"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card bg-warning text-white h-100">
                            <div class="card-body">
                                <h5 class="card-title"><i class="fas fa-clock me-2"></i> Pending</h5>
                                <h2 class="display-4"><?php echo $pending_orders; ?></h2>
                            </div>
                            <div class="card-footer d-flex">
                                <a href="my_transactions.php" class="text-white text-decoration-none">View Details
                                    <i class="fas fa-arrow-circle-right ms-2"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Revenue Overview -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i> Revenue Overview</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-3 text-center mb-3 mb-md-0">
                                        <div class="border rounded p-3">
                                            <h6 class="text-muted mb-2">Total Earnings</h6>
                                            <h3 class="text-primary">₱<?php echo number_format($total_sales * 0.9, 2); ?></h3>
                                            <small class="text-muted">After platform fee</small>
                                        </div>
                                    </div>
                                    <div class="col-md-3 text-center mb-3 mb-md-0">
                                        <div class="border rounded p-3">
                                            <h6 class="text-muted mb-2">Commission Paid</h6>
                                            <h3 class="text-danger">₱<?php echo number_format($total_sales * 0.1, 2); ?></h3>
                                            <small class="text-muted">10% platform fee</small>
                                        </div>
                                    </div>
                                    <div class="col-md-3 text-center mb-3 mb-md-0">
                                        <div class="border rounded p-3">
                                            <h6 class="text-muted mb-2">This Month</h6>
                                            <h3 class="text-success">₱<?php echo number_format($total_sales * 0.75, 2); ?></h3>
                                            <small class="text-muted">From <?php echo date('M 1'); ?></small>
                                        </div>
                                    </div>
                                    <div class="col-md-3 text-center">
                                        <div class="border rounded p-3">
                                            <h6 class="text-muted mb-2">Avg Per Sale</h6>
                                            <h3 class="text-info">₱<?php echo $total_orders > 0 ? number_format(($total_sales/$total_orders) * 0.9, 2) : '0.00'; ?></h3>
                                            <small class="text-muted">After commission</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row mb-4">
                    <!-- Sales Chart -->
                    <div class="col-md-8 mb-4">
                        <div class="card h-100">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i> Sales Performance</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="salesChart" width="100%" height="50"></canvas>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Order Stats -->
                    <div class="col-md-4 mb-4">
                        <div class="card h-100">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i> Order Status</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="orderChart" width="100%" height="50"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Orders and Products Row -->
                <div class="row">
                    <!-- Recent Orders -->
                    <div class="col-md-12 mb-4">
                        <div class="card">
                            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><i class="fas fa-shopping-cart me-2"></i> Recent Orders</h5>
                                <a href="my_transactions.php" class="btn btn-sm btn-light">View All</a>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead>
                                            <tr>
                                                <th>Order #</th>
                                                <th>Product</th>
                                                <th>Buyer</th>
                                                <th>Amount</th>
                                                <th>Status</th>
                                                <th>Date</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (count($recent_orders) > 0): ?>
                                                <?php foreach ($recent_orders as $order): ?>
                                                    <tr>
                                                    <td><a href="order_details.php?id=<?php echo $order['id']; ?>">#<?php echo $order['order_number']; ?></a></td>
                                                    <td><?php echo htmlspecialchars($order['product_name']); ?></td>
                                                        <td><?php echo htmlspecialchars($order['buyer_name']); ?></td>
                                                        <td>₱<?php echo number_format($order['total_amount'], 2); ?></td>
                                                        <td>
                                                        <span class="badge bg-<?php echo $order['status'] == 'completed' ? 'success' : ($order['status'] == 'pending' ? 'warning' : 'secondary'); ?>">
                                                            <?php echo ucfirst($order['status']); ?>
                                                        </span>
                                                        </td>
                                                    <td><?php echo date('M j, Y', strtotime($order['created_at'])); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="6" class="text-center">No orders yet</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Recent Buyer Messages -->
                    <div class="col-md-6 mb-4">
                        <div class="card h-100">
                            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">
                                    <i class="fas fa-envelope me-2"></i> Recent Buyer Messages
                                    <?php if ($unread_messages > 0): ?>
                                    <span class="badge bg-danger ms-2"><?php echo $unread_messages; ?> unread</span>
                                    <?php endif; ?>
                                </h5>
                                <a href="my_messages.php" class="btn btn-sm btn-light">View All</a>
                            </div>
                            <div class="card-body">
                                <?php if (count($recent_messages) > 0): ?>
                                    <div class="list-group">
                                        <?php foreach ($recent_messages as $message): ?>
                                            <a href="../message.php?to=<?php echo $message['buyer_id']; ?><?php echo !empty($message['product_id']) ? '&product=' . $message['product_id'] : ''; ?>" 
                                               class="list-group-item list-group-item-action <?php echo $message['is_read'] ? '' : 'bg-light'; ?>">
                                                <div class="d-flex w-100 justify-content-between align-items-center">
                                                    <h6 class="mb-1">
                                                        <?php echo htmlspecialchars($message['buyer_name']); ?>
                                                        <?php if (!$message['is_read']): ?>
                                                        <span class="badge bg-danger ms-2">New</span>
                                                        <?php endif; ?>
                                                    </h6>
                                                    <small class="text-muted"><?php echo date('M j, g:i A', strtotime($message['created_at'])); ?></small>
                                                </div>
                                                <p class="mb-1 text-truncate"><?php echo htmlspecialchars($message['message']); ?></p>
                                                <?php if (!empty($message['product_name'])): ?>
                                                <small class="text-primary">
                                                    <i class="fas fa-tag me-1"></i> Product: <?php echo htmlspecialchars($message['product_name']); ?>
                                                </small>
                                                <?php endif; ?>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center p-4">
                                        <i class="far fa-envelope-open fa-3x mb-3 text-muted"></i>
                                        <p>No messages from buyers yet</p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Latest Products -->
                    <div class="col-md-6 mb-4">
                        <div class="card h-100">
                            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><i class="fas fa-box me-2"></i> Latest Products</h5>
                                <a href="my_products.php" class="btn btn-sm btn-light">View All</a>
                            </div>
                            <div class="card-body">
                                <?php if (count($latest_products) > 0): ?>
                                    <div class="list-group">
                                        <?php foreach ($latest_products as $product): ?>
                                            <a href="../product.php?id=<?php echo $product['id']; ?>" class="list-group-item list-group-item-action">
                                                <div class="d-flex">
                                                    <div class="me-3">
                                                        <img src="<?php echo !empty($product['image']) ? '../' . $product['image'] : '../assets/images/placeholder.png'; ?>" 
                                                             class="img-thumbnail" alt="<?php echo htmlspecialchars($product['name']); ?>" 
                                                             style="width: 60px; height: 60px; object-fit: cover;">
                                                    </div>
                                                    <div class="flex-grow-1">
                                                        <div class="d-flex w-100 justify-content-between align-items-center">
                                                            <h6 class="mb-1">
                                                                <?php echo htmlspecialchars($product['name']); ?>
                                                                <span class="badge bg-<?php echo $product['status'] == 'active' ? 'success' : 'warning'; ?>">
                                                                    <?php echo ucfirst($product['status']); ?>
                                                                </span>
                                                            </h6>
                                                            <small class="text-muted"><?php echo date('M j, Y', strtotime($product['created_at'])); ?></small>
                                                        </div>
                                                        <p class="mb-1">₱<?php echo number_format($product['price'], 2); ?></p>
                                                    </div>
                                                </div>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center p-4">
                                        <i class="fas fa-box-open fa-3x mb-3 text-muted"></i>
                                        <p>No products added yet</p>
                                        <a href="add_product.php" class="btn btn-primary">Add Your First Product</a>
                                    </div>
                                    <?php endif; ?>
                                </div>
                        </div>
                    </div>
                </div>
                
                <!-- Access Requests -->
                <?php if ($pending_requests > 0): ?>
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header bg-warning">
                                <h5 class="mb-0"><i class="fas fa-key me-2"></i> Pending Source Access Requests</h5>
                            </div>
                            <div class="card-body">
                                <div class="alert alert-warning">
                                    <p><strong>You have <?php echo $pending_requests; ?> pending access requests to review.</strong></p>
                                    <a href="access_requests.php" class="btn btn-warning">
                                        <i class="fas fa-eye me-2"></i> View Access Requests
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php include_once __DIR__ . '/../includes/footer.php'; ?>
    
    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <!-- Custom JavaScript -->
    <script src="../assets/js/script.js"></script>
    <script>
        // Sample data for charts (in a real app, this would come from backend)
        document.addEventListener('DOMContentLoaded', function() {
            // Sales Chart
            const salesData = {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                datasets: [{
                    label: 'Sales (PHP)',
                    backgroundColor: 'rgba(54, 162, 235, 0.2)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 2,
                    data: [5000, 7500, 10000, 8000, 12000, <?php echo $total_sales; ?>],
                    fill: true,
                    tension: 0.3
                }]
            };
            
            const salesConfig = {
                type: 'line',
                data: salesData,
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'top',
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return '₱' + context.raw.toLocaleString();
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return '₱' + value.toLocaleString();
                                }
                            }
                        }
                    }
                }
            };
            
            new Chart(
                document.getElementById('salesChart'),
                salesConfig
            );
            
            // Order Status Chart
            const orderData = {
                labels: ['Completed', 'Pending', 'Cancelled'],
                datasets: [{
                    label: 'Orders',
                    backgroundColor: [
                        'rgba(40, 167, 69, 0.8)',
                        'rgba(255, 193, 7, 0.8)',
                        'rgba(220, 53, 69, 0.8)'
                    ],
                    data: [
                        <?php echo $completed_orders; ?>, 
                        <?php echo $pending_orders; ?>, 
                        <?php echo $total_orders - ($completed_orders + $pending_orders); ?>
                    ],
                    borderWidth: 1
                }]
            };
            
            const orderConfig = {
                type: 'doughnut',
                data: orderData,
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom',
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const value = context.raw;
                                    const total = context.dataset.data.reduce((acc, data) => acc + data, 0);
                                    const percentage = Math.round((value / total) * 100);
                                    return `${context.label}: ${value} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            };
            
            new Chart(
                document.getElementById('orderChart'),
                orderConfig
            );
        });
    </script>
</body>
</html> 