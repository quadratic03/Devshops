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

// Get statistics
$total_products_query = "SELECT COUNT(*) as total FROM products";
$total_users_query = "SELECT COUNT(*) as total FROM users WHERE role != 'admin'";
$total_transactions_query = "SELECT COUNT(*) as total FROM transactions";
$total_revenue_query = "SELECT SUM(amount) as total FROM transactions WHERE status = 'completed'";

$total_products = $conn->query($total_products_query)->fetch_assoc()['total'];
$total_users = $conn->query($total_users_query)->fetch_assoc()['total'];
$total_transactions = $conn->query($total_transactions_query)->fetch_assoc()['total'];
$total_revenue_result = $conn->query($total_revenue_query)->fetch_assoc();
$total_revenue = $total_revenue_result['total'] ? $total_revenue_result['total'] : 0;

// Get recent products
$recent_products_query = "SELECT p.*, c.name as category_name, u.username as seller_name 
                         FROM products p 
                         JOIN categories c ON p.category_id = c.id 
                         JOIN users u ON p.seller_id = u.id 
                         ORDER BY p.created_at DESC LIMIT 5";
$recent_products = $conn->query($recent_products_query);

// Get recent users
$recent_users_query = "SELECT * FROM users WHERE role != 'admin' ORDER BY created_at DESC LIMIT 5";
$recent_users = $conn->query($recent_users_query);

// Get platform revenue statistics
$stats_sql = "SELECT 
    COUNT(*) as transactions, 
    SUM(amount) as revenue 
    FROM transactions 
    WHERE status = 'completed'";
$stats_result = $conn->query($stats_sql);
$stats = $stats_result->fetch_assoc();
if (!$stats['revenue']) {
    $stats['revenue'] = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - DevMarket Philippines</title>
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
                    <h1 class="h2">Admin Dashboard</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()">
                                <i class="fas fa-print me-2"></i> Print Report
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-download me-2"></i> Export
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Stats Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card dashboard-card bg-primary text-white">
                            <div class="card-body">
                                <h5 class="card-title">Total Products</h5>
                                <p class="card-text fs-1"><?php echo $total_products; ?></p>
                                <a href="products.php" class="text-white">View Details <i class="fas fa-arrow-right"></i></a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card dashboard-card bg-success text-white">
                            <div class="card-body">
                                <h5 class="card-title">Total Users</h5>
                                <p class="card-text fs-1"><?php echo $total_users; ?></p>
                                <a href="users.php" class="text-white">View Details <i class="fas fa-arrow-right"></i></a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card dashboard-card bg-warning text-dark">
                            <div class="card-body">
                                <h5 class="card-title">Transactions</h5>
                                <p class="card-text fs-1"><?php echo $total_transactions; ?></p>
                                <a href="transactions.php" class="text-dark">View Details <i class="fas fa-arrow-right"></i></a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card dashboard-card bg-info text-white">
                            <div class="card-body">
                                <h5 class="card-title">Total Revenue</h5>
                                <p class="card-text fs-1"><?php echo format_currency($total_revenue); ?></p>
                                <a href="reports.php" class="text-white">View Details <i class="fas fa-arrow-right"></i></a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Products -->
                <div class="card mb-4">
                    <div class="card-header bg-dark text-white">
                        <h5 class="mb-0">Recent Products</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Title</th>
                                        <th>Category</th>
                                        <th>Price</th>
                                        <th>Seller</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($recent_products->num_rows > 0): ?>
                                        <?php while ($product = $recent_products->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo $product['id']; ?></td>
                                                <td><?php echo $product['name']; ?></td>
                                                <td><?php echo $product['category_name']; ?></td>
                                                <td><?php echo format_currency($product['price']); ?></td>
                                                <td><?php echo $product['seller_name']; ?></td>
                                                <td>
                                                    <?php if ($product['status'] == 'available'): ?>
                                                        <span class="badge bg-success">Available</span>
                                                    <?php elseif ($product['status'] == 'hidden'): ?>
                                                        <span class="badge bg-danger">Hidden</span>
                                                    <?php elseif ($product['status'] == 'deleted'): ?>
                                                        <span class="badge bg-danger">Deleted</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning text-dark">Sold</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <a href="view_product.php?id=<?php echo $product['id']; ?>" class="btn btn-sm btn-info">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="edit_product.php?id=<?php echo $product['id']; ?>" class="btn btn-sm btn-primary">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="delete_product.php?id=<?php echo $product['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this product?')">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" class="text-center">No products available</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <a href="products.php" class="btn btn-primary">View All Products</a>
                    </div>
                </div>
                
                <!-- Recent Users -->
                <div class="card mb-4">
                    <div class="card-header bg-dark text-white">
                        <h5 class="mb-0">Recent Users</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Username</th>
                                        <th>Email</th>
                                        <th>Type</th>
                                        <th>Registered On</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($recent_users->num_rows > 0): ?>
                                        <?php while ($user = $recent_users->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo $user['id']; ?></td>
                                                <td><?php echo $user['username']; ?></td>
                                                <td><?php echo $user['email']; ?></td>
                                                <td>
                                                    <?php if ($user['role'] == 'seller'): ?>
                                                        <span class="badge bg-primary">Seller</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Buyer</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                                <td>
                                                    <a href="view_user.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-info">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="edit_user.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-primary">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="delete_user.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this user?')">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center">No users available</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <a href="users.php" class="btn btn-primary">View All Users</a>
                    </div>
                </div>
                
                <!-- Platform Revenue Overview -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                                <h6 class="m-0 font-weight-bold text-primary">Platform Revenue Overview</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-3 text-center mb-3 mb-md-0">
                                        <div class="border rounded p-3">
                                            <h6 class="text-muted mb-2">Total Revenue</h6>
                                            <h3 class="text-primary">₱<?php echo number_format($stats['revenue'], 2); ?></h3>
                                            <small class="text-muted">Gross transaction volume</small>
                                        </div>
                                    </div>
                                    <div class="col-md-3 text-center mb-3 mb-md-0">
                                        <div class="border rounded p-3">
                                            <h6 class="text-muted mb-2">Platform Fees</h6>
                                            <h3 class="text-success">₱<?php echo number_format($stats['revenue'] * 0.1, 2); ?></h3>
                                            <small class="text-muted">10% of all transactions</small>
                                        </div>
                                    </div>
                                    <div class="col-md-3 text-center mb-3 mb-md-0">
                                        <div class="border rounded p-3">
                                            <h6 class="text-muted mb-2">Monthly Revenue</h6>
                                            <h3 class="text-info">₱<?php echo number_format($stats['revenue'] * 0.65, 2); ?></h3>
                                            <small class="text-muted">Current month</small>
                                        </div>
                                    </div>
                                    <div class="col-md-3 text-center">
                                        <div class="border rounded p-3">
                                            <h6 class="text-muted mb-2">Avg Fee Per Sale</h6>
                                            <h3 class="text-warning">₱<?php echo $stats['transactions'] > 0 ? number_format(($stats['revenue'] * 0.1)/$stats['transactions'], 2) : '0.00'; ?></h3>
                                            <small class="text-muted">Fee per transaction</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
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