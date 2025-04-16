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

// Get seller's transactions
$sql = "SELECT t.*, p.name as product_name, p.price, u.username as buyer_username, u.email as buyer_email
        FROM transactions t
        JOIN products p ON t.product_id = p.id
        JOIN users u ON t.buyer_id = u.id
        WHERE t.seller_id = ?
        ORDER BY t.created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$transactions = [];

if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $transactions[] = $row;
    }
}

// Get transaction statistics
$stats_sql = "SELECT 
    COUNT(*) as total_transactions,
    SUM(amount) as total_sales,
    COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_sales,
    COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_sales
    FROM transactions 
    WHERE seller_id = ?";
$stats_stmt = $conn->prepare($stats_sql);
$stats_stmt->bind_param("i", $_SESSION['user_id']);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Transactions - DevMarket Philippines</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
                <!-- Transaction Statistics -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-primary text-white mb-3">
                            <div class="card-body text-center">
                                <h6 class="card-title">Total Transactions</h6>
                                <p class="card-text display-6"><?php echo $stats['total_transactions'] ?? 0; ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white mb-3">
                            <div class="card-body text-center">
                                <h6 class="card-title">Total Sales</h6>
                                <p class="card-text display-6"><?php echo format_currency($stats['total_sales'] ?? 0); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white mb-3">
                            <div class="card-body text-center">
                                <h6 class="card-title">Completed</h6>
                                <p class="card-text display-6"><?php echo $stats['completed_sales'] ?? 0; ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-dark mb-3">
                            <div class="card-body text-center">
                                <h6 class="card-title">Pending</h6>
                                <p class="card-text display-6"><?php echo $stats['pending_sales'] ?? 0; ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Transaction List -->
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-money-bill-wave me-2"></i> My Transactions</h5>
                    </div>
                    <div class="card-body">
                        <?php if (count($transactions) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Product</th>
                                            <th>Buyer</th>
                                            <th>Amount</th>
                                            <th>Status</th>
                                            <th>Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($transactions as $transaction): ?>
                                            <tr>
                                                <td><?php echo $transaction['id']; ?></td>
                                                <td><?php echo htmlspecialchars($transaction['product_name']); ?></td>
                                                <td>
                                                    <?php echo htmlspecialchars($transaction['buyer_username']); ?>
                                                    <div class="small text-muted"><?php echo htmlspecialchars($transaction['buyer_email']); ?></div>
                                                </td>
                                                <td><?php echo format_currency($transaction['amount']); ?></td>
                                                <td>
                                                    <?php if ($transaction['status'] == 'completed'): ?>
                                                        <span class="badge bg-success">Completed</span>
                                                    <?php elseif ($transaction['status'] == 'pending'): ?>
                                                        <span class="badge bg-warning text-dark">Pending</span>
                                                    <?php elseif ($transaction['status'] == 'cancelled'): ?>
                                                        <span class="badge bg-danger">Cancelled</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-info">Refunded</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo date('M d, Y H:i', strtotime($transaction['created_at'])); ?></td>
                                                <td>
                                                    <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#transactionModal" 
                                                        data-id="<?php echo $transaction['id']; ?>"
                                                        data-product="<?php echo htmlspecialchars($transaction['product_name']); ?>"
                                                        data-buyer="<?php echo htmlspecialchars($transaction['buyer_username']); ?>"
                                                        data-email="<?php echo htmlspecialchars($transaction['buyer_email']); ?>"
                                                        data-amount="<?php echo format_currency($transaction['amount']); ?>"
                                                        data-status="<?php echo $transaction['status']; ?>"
                                                        data-date="<?php echo date('F d, Y H:i:s', strtotime($transaction['created_at'])); ?>"
                                                        data-notes="<?php echo htmlspecialchars($transaction['notes'] ?? ''); ?>">
                                                        <i class="fas fa-eye"></i> Details
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <p class="mb-0">You haven't made any sales yet. Once you sell a product, your transactions will appear here.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Transaction Details Modal -->
    <div class="modal fade" id="transactionModal" tabindex="-1" aria-labelledby="transactionModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="transactionModalLabel">Transaction Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-2">
                        <div class="col-md-4 fw-bold">Transaction ID:</div>
                        <div class="col-md-8" id="modal-transaction-id"></div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-md-4 fw-bold">Product:</div>
                        <div class="col-md-8" id="modal-product"></div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-md-4 fw-bold">Buyer:</div>
                        <div class="col-md-8" id="modal-buyer"></div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-md-4 fw-bold">Buyer Email:</div>
                        <div class="col-md-8" id="modal-email"></div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-md-4 fw-bold">Amount:</div>
                        <div class="col-md-8" id="modal-amount"></div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-md-4 fw-bold">Status:</div>
                        <div class="col-md-8" id="modal-status"></div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-md-4 fw-bold">Date:</div>
                        <div class="col-md-8" id="modal-date"></div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-md-4 fw-bold">Notes:</div>
                        <div class="col-md-8" id="modal-notes"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    
    <?php include_once __DIR__ . '/../includes/footer.php'; ?>
    
    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JavaScript -->
    <script src="../assets/js/script.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Transaction Modal
            const transactionModal = document.getElementById('transactionModal');
            transactionModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                
                document.getElementById('modal-transaction-id').textContent = button.getAttribute('data-id');
                document.getElementById('modal-product').textContent = button.getAttribute('data-product');
                document.getElementById('modal-buyer').textContent = button.getAttribute('data-buyer');
                document.getElementById('modal-email').textContent = button.getAttribute('data-email');
                document.getElementById('modal-amount').textContent = button.getAttribute('data-amount');
                document.getElementById('modal-date').textContent = button.getAttribute('data-date');
                document.getElementById('modal-notes').textContent = button.getAttribute('data-notes') || 'No notes available';
                
                const status = button.getAttribute('data-status');
                let statusHtml = '';
                
                if (status === 'completed') {
                    statusHtml = '<span class="badge bg-success">Completed</span>';
                } else if (status === 'pending') {
                    statusHtml = '<span class="badge bg-warning text-dark">Pending</span>';
                } else if (status === 'cancelled') {
                    statusHtml = '<span class="badge bg-danger">Cancelled</span>';
                } else {
                    statusHtml = '<span class="badge bg-info">Refunded</span>';
                }
                
                document.getElementById('modal-status').innerHTML = statusHtml;
            });
        });
    </script>
</body>
</html> 