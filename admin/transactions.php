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

// Process transaction actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Update transaction status
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
                $success = "Transaction status updated successfully.";
            } else {
                $error = "Error updating transaction status: " . $conn->error;
            }
        }
    }
}

// Get transactions with related data
$sql = "SELECT t.*, 
        (SELECT name FROM products WHERE id = t.product_id) as product_name, 
        u_buyer.username as buyer_username,
        u_seller.username as seller_username
        FROM transactions t
        LEFT JOIN users u_buyer ON t.buyer_id = u_buyer.id
        LEFT JOIN users u_seller ON t.seller_id = u_seller.id
        ORDER BY t.created_at DESC";
$result = $conn->query($sql);
$transactions = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $transactions[] = $row;
    }
}

// Get transaction statistics
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
    <title>Manage Transactions - DevMarket Philippines</title>
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
                    <h1 class="h2">Manage Transactions</h1>
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
                
                <!-- Transaction Statistics -->
                <div class="row mb-4">
                    <div class="col-md-3 mb-3">
                        <div class="card bg-primary text-white h-100">
                            <div class="card-body">
                                <h5 class="card-title">Total Transactions</h5>
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
                                <h5 class="card-title">Total Transaction Value</h5>
                                <h2>₱<?php echo number_format($stats['total_value'], 2); ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="card bg-success text-white h-100">
                            <div class="card-body">
                                <h5 class="card-title">Completed Transaction Value</h5>
                                <h2>₱<?php echo number_format($stats['completed_value'], 2); ?></h2>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Transactions Table -->
                <div class="card mb-4">
                    <div class="card-header bg-dark text-white">
                        <h5 class="mb-0">All Transactions</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
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
                                    <?php if (count($transactions) > 0): ?>
                                        <?php foreach ($transactions as $transaction): ?>
                                            <tr>
                                                <td><?php echo $transaction['id']; ?></td>
                                                <td><?php echo htmlspecialchars($transaction['product_name']); ?></td>
                                                <td><?php echo htmlspecialchars($transaction['buyer_username']); ?></td>
                                                <td><?php echo htmlspecialchars($transaction['seller_username']); ?></td>
                                                <td>₱<?php echo number_format($transaction['amount'], 2); ?></td>
                                                <td>
                                                    <?php
                                                    $status_class = 'bg-secondary';
                                                    switch ($transaction['status']) {
                                                        case 'completed':
                                                            $status_class = 'bg-success';
                                                            break;
                                                        case 'pending':
                                                            $status_class = 'bg-warning text-dark';
                                                            break;
                                                        case 'cancelled':
                                                            $status_class = 'bg-danger';
                                                            break;
                                                        case 'refunded':
                                                            $status_class = 'bg-info';
                                                            break;
                                                    }
                                                    ?>
                                                    <span class="badge <?php echo $status_class; ?>">
                                                        <?php echo ucfirst($transaction['status']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo ucfirst($transaction['payment_method']); ?></td>
                                                <td><?php echo date('M d, Y H:i', strtotime($transaction['created_at'])); ?></td>
                                                <td>
                                                    <!-- View Transaction Button -->
                                                    <button class="btn btn-sm btn-info view-btn" 
                                                            data-id="<?php echo $transaction['id']; ?>"
                                                            data-product="<?php echo htmlspecialchars($transaction['product_name']); ?>"
                                                            data-buyer="<?php echo htmlspecialchars($transaction['buyer_username']); ?>"
                                                            data-seller="<?php echo htmlspecialchars($transaction['seller_username']); ?>"
                                                            data-amount="<?php echo number_format($transaction['amount'], 2); ?>"
                                                            data-status="<?php echo $transaction['status']; ?>"
                                                            data-payment="<?php echo ucfirst($transaction['payment_method']); ?>"
                                                            data-created="<?php echo date('M d, Y H:i', strtotime($transaction['created_at'])); ?>"
                                                            data-reference="<?php echo htmlspecialchars($transaction['reference_number']); ?>"
                                                            data-notes="<?php echo htmlspecialchars($transaction['notes']); ?>">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    
                                                    <!-- Update Status Button -->
                                                    <button class="btn btn-sm btn-warning status-btn"
                                                            data-id="<?php echo $transaction['id']; ?>"
                                                            data-product="<?php echo htmlspecialchars($transaction['product_name']); ?>"
                                                            data-status="<?php echo $transaction['status']; ?>">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="9" class="text-center">No transactions found.</td>
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

    <!-- View Transaction Modal -->
    <div class="modal fade" id="viewTransactionModal" tabindex="-1" aria-labelledby="viewTransactionModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewTransactionModalLabel">Transaction Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-4 fw-bold">ID:</div>
                        <div class="col-8" id="view_id"></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-4 fw-bold">Product:</div>
                        <div class="col-8" id="view_product"></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-4 fw-bold">Buyer:</div>
                        <div class="col-8" id="view_buyer"></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-4 fw-bold">Seller:</div>
                        <div class="col-8" id="view_seller"></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-4 fw-bold">Amount:</div>
                        <div class="col-8" id="view_amount"></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-4 fw-bold">Status:</div>
                        <div class="col-8" id="view_status"></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-4 fw-bold">Payment Method:</div>
                        <div class="col-8" id="view_payment"></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-4 fw-bold">Reference #:</div>
                        <div class="col-8" id="view_reference"></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-4 fw-bold">Date:</div>
                        <div class="col-8" id="view_created"></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-4 fw-bold">Notes:</div>
                        <div class="col-8" id="view_notes"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Update Status Modal -->
    <div class="modal fade" id="updateStatusModal" tabindex="-1" aria-labelledby="updateStatusModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="updateStatusModalLabel">Update Transaction Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Update status for transaction: <span id="status_product" class="fw-bold"></span></p>
                    <form action="transactions.php" method="POST">
                        <input type="hidden" id="status_transaction_id" name="transaction_id">
                        <div class="mb-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="pending">Pending</option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
                                <option value="refunded">Refunded</option>
                            </select>
                        </div>
                        <div class="text-end">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" name="update_status" class="btn btn-primary">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JavaScript -->
    <script src="../assets/js/script.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // View Transaction Modal
            const viewButtons = document.querySelectorAll('.view-btn');
            const viewModal = new bootstrap.Modal(document.getElementById('viewTransactionModal'));
            
            viewButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    const product = this.getAttribute('data-product');
                    const buyer = this.getAttribute('data-buyer');
                    const seller = this.getAttribute('data-seller');
                    const amount = this.getAttribute('data-amount');
                    const status = this.getAttribute('data-status');
                    const payment = this.getAttribute('data-payment');
                    const created = this.getAttribute('data-created');
                    const reference = this.getAttribute('data-reference');
                    const notes = this.getAttribute('data-notes');
                    
                    document.getElementById('view_id').textContent = id;
                    document.getElementById('view_product').textContent = product;
                    document.getElementById('view_buyer').textContent = buyer;
                    document.getElementById('view_seller').textContent = seller;
                    document.getElementById('view_amount').textContent = '₱' + amount;
                    
                    let statusDisplay = '';
                    switch(status) {
                        case 'completed':
                            statusDisplay = '<span class="badge bg-success">Completed</span>';
                            break;
                        case 'pending':
                            statusDisplay = '<span class="badge bg-warning text-dark">Pending</span>';
                            break;
                        case 'cancelled':
                            statusDisplay = '<span class="badge bg-danger">Cancelled</span>';
                            break;
                        case 'refunded':
                            statusDisplay = '<span class="badge bg-info">Refunded</span>';
                            break;
                        default:
                            statusDisplay = '<span class="badge bg-secondary">' + status.charAt(0).toUpperCase() + status.slice(1) + '</span>';
                    }
                    document.getElementById('view_status').innerHTML = statusDisplay;
                    
                    document.getElementById('view_payment').textContent = payment;
                    document.getElementById('view_reference').textContent = reference || 'N/A';
                    document.getElementById('view_created').textContent = created;
                    document.getElementById('view_notes').textContent = notes || 'No notes';
                    
                    viewModal.show();
                });
            });
            
            // Update Status Modal
            const statusButtons = document.querySelectorAll('.status-btn');
            const statusModal = new bootstrap.Modal(document.getElementById('updateStatusModal'));
            
            statusButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    const product = this.getAttribute('data-product');
                    const status = this.getAttribute('data-status');
                    
                    document.getElementById('status_transaction_id').value = id;
                    document.getElementById('status_product').textContent = product;
                    document.getElementById('status').value = status;
                    
                    statusModal.show();
                });
            });
        });
    </script>
</body>
</html> 