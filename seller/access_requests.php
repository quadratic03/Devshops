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

$seller_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Function to handle access request actions with error handling
function handleAccessRequest($action, $request_id, $seller_id, $conn, &$success, &$error) {
    try {
        // Verify that the request belongs to the seller
        $check_query = "SELECT * FROM source_access_requests WHERE id = ? AND seller_id = ?";
        $stmt = $conn->prepare($check_query);
        $stmt->bind_param("ii", $request_id, $seller_id);
        $stmt->execute();
        $check_result = $stmt->get_result();
        
        if ($check_result->num_rows === 0) {
            $error = "Invalid request.";
            return;
        }
        
        if ($action === 'approve') {
            $update_query = "UPDATE source_access_requests SET status = 'approved', updated_at = NOW() WHERE id = ?";
            $status_type = "approved";
        } elseif ($action === 'reject') {
            $update_query = "UPDATE source_access_requests SET status = 'rejected', updated_at = NOW() WHERE id = ?";
            $status_type = "rejected";
        } else {
            $error = "Invalid action.";
            return;
        }
        
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("i", $request_id);
        
        if ($stmt->execute()) {
            $success = "Request successfully {$status_type}.";
        } else {
            $error = "Error updating request status.";
        }
    } catch (mysqli_sql_exception $e) {
        $error = "Database error while processing your request.";
        error_log("Error in handleAccessRequest: " . $e->getMessage());
    }
}

// Handle approve/reject actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $request_id = isset($_POST['request_id']) ? (int)$_POST['request_id'] : 0;

    try {
        if (!empty($action) && !empty($request_id)) {
            handleAccessRequest($action, $request_id, $seller_id, $conn, $success, $error);
        }
    } catch (mysqli_sql_exception $e) {
        $error = "Database error: Unable to process your request.";
        error_log("Error in access_requests.php action processing: " . $e->getMessage());
    }
}

// Initialize variables to avoid undefined variable warnings
$pending_result = null;
$approved_result = null;
$rejected_result = null;

try {
    // Get pending requests
    $pending_query = "SELECT sar.*, p.name as product_name, p.image as product_image, u.username as buyer_name, u.email as buyer_email 
                     FROM source_access_requests sar
                     JOIN products p ON sar.product_id = p.id
                     JOIN users u ON sar.buyer_id = u.id
                     WHERE sar.seller_id = ? AND sar.status = 'pending'
                     ORDER BY sar.created_at DESC";
    $stmt = $conn->prepare($pending_query);
    $stmt->bind_param("i", $seller_id);
    $stmt->execute();
    $pending_result = $stmt->get_result();

    // Get approved requests
    $approved_query = "SELECT sar.*, p.name as product_name, u.username as buyer_name, u.email as buyer_email 
                      FROM source_access_requests sar
                      JOIN products p ON sar.product_id = p.id
                      JOIN users u ON sar.buyer_id = u.id
                      WHERE sar.seller_id = ? AND sar.status = 'approved'
                      ORDER BY sar.updated_at DESC";
    $stmt = $conn->prepare($approved_query);
    $stmt->bind_param("i", $seller_id);
    $stmt->execute();
    $approved_result = $stmt->get_result();

    // Get rejected requests
    $rejected_query = "SELECT sar.*, p.name as product_name, u.username as buyer_name, u.email as buyer_email 
                      FROM source_access_requests sar
                      JOIN products p ON sar.product_id = p.id
                      JOIN users u ON sar.buyer_id = u.id
                      WHERE sar.seller_id = ? AND sar.status = 'rejected'
                      ORDER BY sar.updated_at DESC";
    $stmt = $conn->prepare($rejected_query);
    $stmt->bind_param("i", $seller_id);
    $stmt->execute();
    $rejected_result = $stmt->get_result();
} catch (mysqli_sql_exception $e) {
    $error = "The source code access feature is currently unavailable. Please try again later.";
    error_log("Error in access_requests.php: Table missing or other DB error: " . $e->getMessage());
    
    // Create empty result sets to avoid errors in the template
    class EmptyResult {
        public $num_rows = 0;
        public function fetch_assoc() { return null; }
    }
    
    $pending_result = new EmptyResult();
    $approved_result = new EmptyResult();
    $rejected_result = new EmptyResult();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Source Code Access - Seller Dashboard</title>
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
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-key me-2"></i> Manage Source Code Access Requests</h5>
                    </div>
                    <div class="card-body">
                        
                        <?php if (!empty($success)): ?>
                            <div class="alert alert-success"><?php echo $success; ?></div>
                        <?php endif; ?>
                        
                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        
                        <ul class="nav nav-tabs mb-4" id="accessTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="pending-tab" data-bs-toggle="tab" data-bs-target="#pending" type="button" role="tab" aria-controls="pending" aria-selected="true">
                                    Pending Requests 
                                    <?php if ($pending_result->num_rows > 0): ?>
                                    <span class="badge bg-danger"><?php echo $pending_result->num_rows; ?></span>
                                    <?php endif; ?>
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="approved-tab" data-bs-toggle="tab" data-bs-target="#approved" type="button" role="tab" aria-controls="approved" aria-selected="false">
                                    Approved
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="rejected-tab" data-bs-toggle="tab" data-bs-target="#rejected" type="button" role="tab" aria-controls="rejected" aria-selected="false">
                                    Rejected
                                </button>
                            </li>
                        </ul>
                        
                        <div class="tab-content" id="accessTabsContent">
                            <!-- Pending Requests -->
                            <div class="tab-pane fade show active" id="pending" role="tabpanel" aria-labelledby="pending-tab">
                                <?php if ($pending_result->num_rows === 0): ?>
                                    <div class="alert alert-info">No pending access requests found.</div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-striped">
                                            <thead>
                                                <tr>
                                                    <th>Product</th>
                                                    <th>Buyer</th>
                                                    <th>Requested On</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php while ($request = $pending_result->fetch_assoc()): ?>
                                                    <tr>
                                                        <td>
                                                            <div class="d-flex align-items-center">
                                                                <?php if (!empty($request['product_image'])): ?>
                                                                    <img src="../uploads/products/<?php echo $request['product_image']; ?>" class="rounded me-2" alt="<?php echo htmlspecialchars($request['product_name']); ?>" style="width: 50px; height: 50px; object-fit: cover;">
                                                                <?php else: ?>
                                                                    <div class="rounded me-2 bg-secondary text-white d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                                                                        <i class="fas fa-image"></i>
                                                                    </div>
                                                                <?php endif; ?>
                                                                <div>
                                                                    <a href="../product.php?id=<?php echo $request['product_id']; ?>">
                                                                        <?php echo htmlspecialchars($request['product_name']); ?>
                                                                    </a>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <?php echo htmlspecialchars($request['buyer_name']); ?><br>
                                                            <small class="text-muted"><?php echo htmlspecialchars($request['buyer_email']); ?></small>
                                                        </td>
                                                        <td>
                                                            <?php echo date('M d, Y g:i A', strtotime($request['created_at'])); ?>
                                                        </td>
                                                        <td>
                                                            <div class="btn-group">
                                                                <form method="post" class="me-2">
                                                                    <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                                                    <input type="hidden" name="action" value="approve">
                                                                    <button type="submit" class="btn btn-success btn-sm" onclick="return confirm('Are you sure you want to grant source code access to this buyer?')">
                                                                        <i class="fas fa-check me-1"></i> Approve
                                                                    </button>
                                                                </form>
                                                                <form method="post">
                                                                    <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                                                    <input type="hidden" name="action" value="reject">
                                                                    <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to reject this access request?')">
                                                                        <i class="fas fa-times me-1"></i> Reject
                                                                    </button>
                                                                </form>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endwhile; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Approved Requests -->
                            <div class="tab-pane fade" id="approved" role="tabpanel" aria-labelledby="approved-tab">
                                <?php if ($approved_result->num_rows === 0): ?>
                                    <div class="alert alert-info">No approved access requests found.</div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-striped">
                                            <thead>
                                                <tr>
                                                    <th>Product</th>
                                                    <th>Buyer</th>
                                                    <th>Approved On</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php while ($request = $approved_result->fetch_assoc()): ?>
                                                    <tr>
                                                        <td>
                                                            <a href="../product.php?id=<?php echo $request['product_id']; ?>">
                                                                <?php echo htmlspecialchars($request['product_name']); ?>
                                                            </a>
                                                        </td>
                                                        <td>
                                                            <?php echo htmlspecialchars($request['buyer_name']); ?><br>
                                                            <small class="text-muted"><?php echo htmlspecialchars($request['buyer_email']); ?></small>
                                                        </td>
                                                        <td>
                                                            <?php echo date('M d, Y g:i A', strtotime($request['updated_at'])); ?>
                                                        </td>
                                                        <td>
                                                            <form method="post">
                                                                <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                                                <input type="hidden" name="action" value="reject">
                                                                <button type="submit" class="btn btn-warning btn-sm" onclick="return confirm('Are you sure you want to revoke access for this buyer?')">
                                                                    <i class="fas fa-ban me-1"></i> Revoke Access
                                                                </button>
                                                            </form>
                                                        </td>
                                                    </tr>
                                                <?php endwhile; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Rejected Requests -->
                            <div class="tab-pane fade" id="rejected" role="tabpanel" aria-labelledby="rejected-tab">
                                <?php if ($rejected_result->num_rows === 0): ?>
                                    <div class="alert alert-info">No rejected access requests found.</div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-striped">
                                            <thead>
                                                <tr>
                                                    <th>Product</th>
                                                    <th>Buyer</th>
                                                    <th>Rejected On</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php while ($request = $rejected_result->fetch_assoc()): ?>
                                                    <tr>
                                                        <td>
                                                            <a href="../product.php?id=<?php echo $request['product_id']; ?>">
                                                                <?php echo htmlspecialchars($request['product_name']); ?>
                                                            </a>
                                                        </td>
                                                        <td>
                                                            <?php echo htmlspecialchars($request['buyer_name']); ?><br>
                                                            <small class="text-muted"><?php echo htmlspecialchars($request['buyer_email']); ?></small>
                                                        </td>
                                                        <td>
                                                            <?php echo date('M d, Y g:i A', strtotime($request['updated_at'])); ?>
                                                        </td>
                                                        <td>
                                                            <form method="post">
                                                                <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                                                <input type="hidden" name="action" value="approve">
                                                                <button type="submit" class="btn btn-success btn-sm" onclick="return confirm('Are you sure you want to approve this previously rejected request?')">
                                                                    <i class="fas fa-check me-1"></i> Approve Now
                                                                </button>
                                                            </form>
                                                        </td>
                                                    </tr>
                                                <?php endwhile; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card mt-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i> About Source Code Access</h5>
                    </div>
                    <div class="card-body">
                        <p>When buyers view your products, they can request access to download the source code. You can review these requests and decide whether to approve or reject them.</p>
                        <ul>
                            <li><strong>Approving</strong> a request allows the buyer to download the source code.</li>
                            <li><strong>Rejecting</strong> a request prevents the buyer from downloading the source code.</li>
                            <li>You can always change your decision later by revoking access or approving previously rejected requests.</li>
                        </ul>
                        <p>This feature ensures that you maintain control over who can access your valuable source code assets while still being able to showcase your products to potential buyers.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include_once __DIR__ . '/../includes/footer.php'; ?>
    
    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JavaScript -->
    <script src="../assets/js/script.js"></script>
</body>
</html> 