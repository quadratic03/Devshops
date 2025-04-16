<?php
// Only start the session if one doesn't already exist
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../includes/functions.php';

// Check if user is logged in and is an admin
if (!is_logged_in() || !is_admin()) {
    // Redirect to login page
    header('Location: ../login.php');
    exit;
}

// Error and success messages
$errors = [];
$success = '';

// Handle approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && isset($_POST['seller_id'])) {
        $action = $_POST['action'];
        $seller_id = (int)$_POST['seller_id'];
        
        if ($action === 'approve') {
            // Approve seller
            $sql = "UPDATE users SET status = 'active', is_approved = 'yes' WHERE id = ? AND role = 'seller'";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $seller_id);
            
            if ($stmt->execute()) {
                $success = "Seller successfully approved.";
            } else {
                $errors[] = "Error approving seller: " . $conn->error;
            }
        } else if ($action === 'reject') {
            // Reject seller
            $sql = "UPDATE users SET is_approved = 'no' WHERE id = ? AND role = 'seller'";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $seller_id);
            
            if ($stmt->execute()) {
                $success = "Seller application rejected.";
            } else {
                $errors[] = "Error rejecting seller: " . $conn->error;
            }
        }
    }
}

// Get all sellers
$sql = "SELECT * FROM users WHERE role = 'seller' ORDER BY created_at DESC";
$result = $conn->query($sql);
$sellers = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $sellers[] = $row;
    }
}

// Count sellers by approval status
$pending_count = 0;
$approved_count = 0;
$rejected_count = 0;

foreach ($sellers as $seller) {
    if ($seller['is_approved'] === 'pending') {
        $pending_count++;
    } else if ($seller['is_approved'] === 'yes') {
        $approved_count++;
    } else {
        $rejected_count++;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Sellers - Admin Dashboard</title>
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
            
            <!-- Main content -->
            <div class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Manage Sellers</h1>
                </div>
                
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger" role="alert">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo $error; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success" role="alert">
                        <?php echo $success; ?>
                    </div>
                <?php endif; ?>
                
                <!-- Stats cards -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card bg-warning text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-title">Pending Approvals</h6>
                                        <h2 class="mb-0"><?php echo $pending_count; ?></h2>
                                    </div>
                                    <i class="fas fa-clock fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-title">Approved Sellers</h6>
                                        <h2 class="mb-0"><?php echo $approved_count; ?></h2>
                                    </div>
                                    <i class="fas fa-check-circle fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-danger text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-title">Rejected Applications</h6>
                                        <h2 class="mb-0"><?php echo $rejected_count; ?></h2>
                                    </div>
                                    <i class="fas fa-times-circle fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Seller tabs -->
                <ul class="nav nav-tabs mb-4" id="sellerTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="all-tab" data-bs-toggle="tab" data-bs-target="#all" type="button" role="tab" aria-controls="all" aria-selected="true">
                            All Sellers <span class="badge bg-secondary"><?php echo count($sellers); ?></span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="pending-tab" data-bs-toggle="tab" data-bs-target="#pending" type="button" role="tab" aria-controls="pending" aria-selected="false">
                            Pending <span class="badge bg-warning"><?php echo $pending_count; ?></span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="approved-tab" data-bs-toggle="tab" data-bs-target="#approved" type="button" role="tab" aria-controls="approved" aria-selected="false">
                            Approved <span class="badge bg-success"><?php echo $approved_count; ?></span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="rejected-tab" data-bs-toggle="tab" data-bs-target="#rejected" type="button" role="tab" aria-controls="rejected" aria-selected="false">
                            Rejected <span class="badge bg-danger"><?php echo $rejected_count; ?></span>
                        </button>
                    </li>
                </ul>
                
                <div class="tab-content" id="sellerTabsContent">
                    <!-- All Sellers Tab -->
                    <div class="tab-pane fade show active" id="all" role="tabpanel" aria-labelledby="all-tab">
                        <?php if (empty($sellers)): ?>
                            <div class="alert alert-info">No sellers found.</div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Username</th>
                                            <th>Email</th>
                                            <th>Phone</th>
                                            <th>Status</th>
                                            <th>Approval</th>
                                            <th>Registered On</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($sellers as $seller): ?>
                                            <tr>
                                                <td><?php echo $seller['id']; ?></td>
                                                <td><?php echo htmlspecialchars($seller['username']); ?></td>
                                                <td><?php echo htmlspecialchars($seller['email']); ?></td>
                                                <td><?php echo htmlspecialchars($seller['phone_number']); ?></td>
                                                <td>
                                                    <?php if ($seller['status'] === 'active'): ?>
                                                        <span class="badge bg-success">Active</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Inactive</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($seller['is_approved'] === 'yes'): ?>
                                                        <span class="badge bg-success">Approved</span>
                                                    <?php elseif ($seller['is_approved'] === 'pending'): ?>
                                                        <span class="badge bg-warning">Pending</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger">Rejected</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo date('M d, Y', strtotime($seller['created_at'])); ?></td>
                                                <td>
                                                    <?php if ($seller['is_approved'] === 'pending'): ?>
                                                        <div class="btn-group" role="group">
                                                            <form method="post" class="me-1">
                                                                <input type="hidden" name="seller_id" value="<?php echo $seller['id']; ?>">
                                                                <input type="hidden" name="action" value="approve">
                                                                <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('Are you sure you want to approve this seller?')">
                                                                    <i class="fas fa-check"></i> Approve
                                                                </button>
                                                            </form>
                                                            <form method="post">
                                                                <input type="hidden" name="seller_id" value="<?php echo $seller['id']; ?>">
                                                                <input type="hidden" name="action" value="reject">
                                                                <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to reject this seller?')">
                                                                    <i class="fas fa-times"></i> Reject
                                                                </button>
                                                            </form>
                                                        </div>
                                                    <?php else: ?>
                                                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#viewSellerModal<?php echo $seller['id']; ?>">
                                                            <i class="fas fa-eye"></i> View
                                                        </button>
                                                    <?php endif; ?>
                                                    
                                                    <!-- View Seller Modal -->
                                                    <div class="modal fade" id="viewSellerModal<?php echo $seller['id']; ?>" tabindex="-1" aria-labelledby="viewSellerModalLabel<?php echo $seller['id']; ?>" aria-hidden="true">
                                                        <div class="modal-dialog">
                                                            <div class="modal-content">
                                                                <div class="modal-header">
                                                                    <h5 class="modal-title" id="viewSellerModalLabel<?php echo $seller['id']; ?>">Seller Details</h5>
                                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                </div>
                                                                <div class="modal-body">
                                                                    <div class="mb-3">
                                                                        <h6>Username</h6>
                                                                        <p><?php echo htmlspecialchars($seller['username']); ?></p>
                                                                    </div>
                                                                    <div class="mb-3">
                                                                        <h6>Email</h6>
                                                                        <p><?php echo htmlspecialchars($seller['email']); ?></p>
                                                                    </div>
                                                                    <div class="mb-3">
                                                                        <h6>Phone</h6>
                                                                        <p><?php echo htmlspecialchars($seller['phone_number']); ?></p>
                                                                    </div>
                                                                    <div class="mb-3">
                                                                        <h6>Status</h6>
                                                                        <p>
                                                                            <?php if ($seller['status'] === 'active'): ?>
                                                                                <span class="badge bg-success">Active</span>
                                                                            <?php else: ?>
                                                                                <span class="badge bg-secondary">Inactive</span>
                                                                            <?php endif; ?>
                                                                        </p>
                                                                    </div>
                                                                    <div class="mb-3">
                                                                        <h6>Approval Status</h6>
                                                                        <p>
                                                                            <?php if ($seller['is_approved'] === 'yes'): ?>
                                                                                <span class="badge bg-success">Approved</span>
                                                                            <?php elseif ($seller['is_approved'] === 'pending'): ?>
                                                                                <span class="badge bg-warning">Pending</span>
                                                                            <?php else: ?>
                                                                                <span class="badge bg-danger">Rejected</span>
                                                                            <?php endif; ?>
                                                                        </p>
                                                                    </div>
                                                                    <div>
                                                                        <h6>Registered On</h6>
                                                                        <p><?php echo date('F d, Y h:i A', strtotime($seller['created_at'])); ?></p>
                                                                    </div>
                                                                </div>
                                                                <div class="modal-footer">
                                                                    <?php if ($seller['is_approved'] !== 'pending'): ?>
                                                                        <?php if ($seller['is_approved'] === 'yes'): ?>
                                                                            <form method="post">
                                                                                <input type="hidden" name="seller_id" value="<?php echo $seller['id']; ?>">
                                                                                <input type="hidden" name="action" value="reject">
                                                                                <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure you want to reject this seller?')">
                                                                                    <i class="fas fa-ban"></i> Revoke Approval
                                                                                </button>
                                                                            </form>
                                                                        <?php else: ?>
                                                                            <form method="post">
                                                                                <input type="hidden" name="seller_id" value="<?php echo $seller['id']; ?>">
                                                                                <input type="hidden" name="action" value="approve">
                                                                                <button type="submit" class="btn btn-success" onclick="return confirm('Are you sure you want to approve this seller?')">
                                                                                    <i class="fas fa-check"></i> Approve Now
                                                                                </button>
                                                                            </form>
                                                                        <?php endif; ?>
                                                                    <?php endif; ?>
                                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Pending Tab -->
                    <div class="tab-pane fade" id="pending" role="tabpanel" aria-labelledby="pending-tab">
                        <?php 
                        $pending_sellers = array_filter($sellers, function($seller) {
                            return $seller['is_approved'] === 'pending';
                        });
                        
                        if (empty($pending_sellers)): 
                        ?>
                            <div class="alert alert-info">No pending seller applications.</div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Username</th>
                                            <th>Email</th>
                                            <th>Phone</th>
                                            <th>Registered On</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($pending_sellers as $seller): ?>
                                            <tr>
                                                <td><?php echo $seller['id']; ?></td>
                                                <td><?php echo htmlspecialchars($seller['username']); ?></td>
                                                <td><?php echo htmlspecialchars($seller['email']); ?></td>
                                                <td><?php echo htmlspecialchars($seller['phone_number']); ?></td>
                                                <td><?php echo date('M d, Y', strtotime($seller['created_at'])); ?></td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <form method="post" class="me-1">
                                                            <input type="hidden" name="seller_id" value="<?php echo $seller['id']; ?>">
                                                            <input type="hidden" name="action" value="approve">
                                                            <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('Are you sure you want to approve this seller?')">
                                                                <i class="fas fa-check"></i> Approve
                                                            </button>
                                                        </form>
                                                        <form method="post">
                                                            <input type="hidden" name="seller_id" value="<?php echo $seller['id']; ?>">
                                                            <input type="hidden" name="action" value="reject">
                                                            <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to reject this seller?')">
                                                                <i class="fas fa-times"></i> Reject
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Approved Tab -->
                    <div class="tab-pane fade" id="approved" role="tabpanel" aria-labelledby="approved-tab">
                        <?php 
                        $approved_sellers = array_filter($sellers, function($seller) {
                            return $seller['is_approved'] === 'yes';
                        });
                        
                        if (empty($approved_sellers)): 
                        ?>
                            <div class="alert alert-info">No approved sellers found.</div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Username</th>
                                            <th>Email</th>
                                            <th>Phone</th>
                                            <th>Status</th>
                                            <th>Registered On</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($approved_sellers as $seller): ?>
                                            <tr>
                                                <td><?php echo $seller['id']; ?></td>
                                                <td><?php echo htmlspecialchars($seller['username']); ?></td>
                                                <td><?php echo htmlspecialchars($seller['email']); ?></td>
                                                <td><?php echo htmlspecialchars($seller['phone_number']); ?></td>
                                                <td>
                                                    <?php if ($seller['status'] === 'active'): ?>
                                                        <span class="badge bg-success">Active</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Inactive</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo date('M d, Y', strtotime($seller['created_at'])); ?></td>
                                                <td>
                                                    <form method="post">
                                                        <input type="hidden" name="seller_id" value="<?php echo $seller['id']; ?>">
                                                        <input type="hidden" name="action" value="reject">
                                                        <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to revoke approval for this seller?')">
                                                            <i class="fas fa-ban"></i> Revoke Approval
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Rejected Tab -->
                    <div class="tab-pane fade" id="rejected" role="tabpanel" aria-labelledby="rejected-tab">
                        <?php 
                        $rejected_sellers = array_filter($sellers, function($seller) {
                            return $seller['is_approved'] === 'no';
                        });
                        
                        if (empty($rejected_sellers)): 
                        ?>
                            <div class="alert alert-info">No rejected sellers found.</div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Username</th>
                                            <th>Email</th>
                                            <th>Phone</th>
                                            <th>Registered On</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($rejected_sellers as $seller): ?>
                                            <tr>
                                                <td><?php echo $seller['id']; ?></td>
                                                <td><?php echo htmlspecialchars($seller['username']); ?></td>
                                                <td><?php echo htmlspecialchars($seller['email']); ?></td>
                                                <td><?php echo htmlspecialchars($seller['phone_number']); ?></td>
                                                <td><?php echo date('M d, Y', strtotime($seller['created_at'])); ?></td>
                                                <td>
                                                    <form method="post">
                                                        <input type="hidden" name="seller_id" value="<?php echo $seller['id']; ?>">
                                                        <input type="hidden" name="action" value="approve">
                                                        <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('Are you sure you want to approve this seller?')">
                                                            <i class="fas fa-check"></i> Approve Now
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom Script -->
    <script src="../assets/js/script.js"></script>
</body>
</html> 