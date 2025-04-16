<?php
require_once '../includes/header.php';

// Check if user is logged in and is a seller
if (!is_logged_in() || $_SESSION['user_type'] !== 'seller') {
    header('Location: ../login.php');
    exit;
}

$seller_id = $_SESSION['user_id'];
$message = '';

// Handle approve/reject actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['approve']) && isset($_POST['request_id'])) {
        $request_id = (int)$_POST['request_id'];
        
        $update_query = "UPDATE source_access_requests SET status = 'approved', updated_at = NOW() WHERE id = ? AND seller_id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("ii", $request_id, $seller_id);
        
        if ($stmt->execute()) {
            $message = '<div class="alert alert-success">Access request approved successfully.</div>';
        } else {
            $message = '<div class="alert alert-danger">Error approving request.</div>';
        }
    } elseif (isset($_POST['reject']) && isset($_POST['request_id'])) {
        $request_id = (int)$_POST['request_id'];
        
        $update_query = "UPDATE source_access_requests SET status = 'rejected', updated_at = NOW() WHERE id = ? AND seller_id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("ii", $request_id, $seller_id);
        
        if ($stmt->execute()) {
            $message = '<div class="alert alert-success">Access request rejected successfully.</div>';
        } else {
            $message = '<div class="alert alert-danger">Error rejecting request.</div>';
        }
    }
}

// Get counts for each status
$count_query = "SELECT status, COUNT(*) as count FROM source_access_requests 
                WHERE seller_id = ? 
                GROUP BY status";
$stmt = $conn->prepare($count_query);
$stmt->bind_param("i", $seller_id);
$stmt->execute();
$count_result = $stmt->get_result();

$counts = [
    'pending' => 0,
    'approved' => 0,
    'rejected' => 0,
    'total' => 0
];

while ($row = $count_result->fetch_assoc()) {
    $counts[$row['status']] = $row['count'];
    $counts['total'] += $row['count'];
}

// Set default filter
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';

// Build query based on filter
$query_status = "";
if ($status_filter !== 'all') {
    $query_status = " AND sar.status = '$status_filter'";
}

// Get access requests
$requests_query = "SELECT sar.*, 
                   p.name as product_name, p.image as product_image, 
                   u.username as buyer_username, u.email as buyer_email 
                   FROM source_access_requests sar
                   JOIN products p ON sar.product_id = p.id
                   JOIN users u ON sar.buyer_id = u.id
                   WHERE sar.seller_id = ?$query_status
                   ORDER BY 
                   CASE 
                     WHEN sar.status = 'pending' THEN 1
                     WHEN sar.status = 'approved' THEN 2
                     WHEN sar.status = 'rejected' THEN 3
                   END, 
                   sar.created_at DESC";
$stmt = $conn->prepare($requests_query);
$stmt->bind_param("i", $seller_id);
$stmt->execute();
$requests_result = $stmt->get_result();
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <?php include 'includes/sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Source Code Access Requests</h1>
            </div>
            
            <?php echo $message; ?>
            
            <div class="card mb-4">
                <div class="card-header">
                    <ul class="nav nav-tabs card-header-tabs">
                        <li class="nav-item">
                            <a class="nav-link <?php echo $status_filter === 'all' ? 'active' : ''; ?>" href="?status=all">
                                All Requests <span class="badge bg-secondary"><?php echo $counts['total']; ?></span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $status_filter === 'pending' ? 'active' : ''; ?>" href="?status=pending">
                                Pending <span class="badge bg-warning"><?php echo $counts['pending']; ?></span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $status_filter === 'approved' ? 'active' : ''; ?>" href="?status=approved">
                                Approved <span class="badge bg-success"><?php echo $counts['approved']; ?></span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $status_filter === 'rejected' ? 'active' : ''; ?>" href="?status=rejected">
                                Rejected <span class="badge bg-danger"><?php echo $counts['rejected']; ?></span>
                            </a>
                        </li>
                    </ul>
                </div>
                <div class="card-body">
                    <?php if ($requests_result->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Buyer</th>
                                        <th>Status</th>
                                        <th>Requested</th>
                                        <th>Last Updated</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($request = $requests_result->fetch_assoc()): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="product-img-small me-2">
                                                        <?php if ($request['product_image']): ?>
                                                            <img src="../uploads/products/<?php echo $request['product_image']; ?>" alt="<?php echo htmlspecialchars($request['product_name']); ?>">
                                                        <?php else: ?>
                                                            <img src="../assets/images/placeholder.png" alt="No image">
                                                        <?php endif; ?>
                                                    </div>
                                                    <a href="../product.php?id=<?php echo $request['product_id']; ?>" target="_blank">
                                                        <?php echo htmlspecialchars($request['product_name']); ?>
                                                    </a>
                                                </div>
                                            </td>
                                            <td>
                                                <span><?php echo htmlspecialchars($request['buyer_username']); ?></span><br>
                                                <small class="text-muted"><?php echo htmlspecialchars($request['buyer_email']); ?></small>
                                            </td>
                                            <td>
                                                <?php if ($request['status'] === 'pending'): ?>
                                                    <span class="badge bg-warning">Pending</span>
                                                <?php elseif ($request['status'] === 'approved'): ?>
                                                    <span class="badge bg-success">Approved</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Rejected</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($request['created_at'])); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($request['updated_at'])); ?></td>
                                            <td>
                                                <?php if ($request['status'] === 'pending'): ?>
                                                    <form method="post" class="d-inline me-1">
                                                        <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                                        <button type="submit" name="approve" class="btn btn-sm btn-success">
                                                            <i class="fas fa-check"></i> Approve
                                                        </button>
                                                    </form>
                                                    <form method="post" class="d-inline">
                                                        <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                                        <button type="submit" name="reject" class="btn btn-sm btn-danger">
                                                            <i class="fas fa-times"></i> Reject
                                                        </button>
                                                    </form>
                                                <?php elseif ($request['status'] === 'approved'): ?>
                                                    <form method="post" class="d-inline">
                                                        <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                                        <button type="submit" name="reject" class="btn btn-sm btn-danger">
                                                            <i class="fas fa-ban"></i> Revoke Access
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <form method="post" class="d-inline">
                                                        <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                                        <button type="submit" name="approve" class="btn btn-sm btn-success">
                                                            <i class="fas fa-check"></i> Grant Access
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i> No source code access requests found.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-question-circle me-2"></i> About Source Code Access</h5>
                </div>
                <div class="card-body">
                    <p>As a seller, you control who can access your source code files. When buyers request access:</p>
                    <ul>
                        <li><strong>Pending Requests:</strong> New requests from buyers that need your approval.</li>
                        <li><strong>Approved Requests:</strong> Buyers who have been granted permission to download your source code.</li>
                        <li><strong>Rejected Requests:</strong> Buyers whose access requests have been denied.</li>
                    </ul>
                    <p>You can change your decision at any time by revoking access or granting permissions.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.product-img-small {
    width: 40px;
    height: 40px;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 4px;
}

.product-img-small img {
    max-width: 100%;
    max-height: 100%;
    object-fit: cover;
}
</style>

<?php require_once '../includes/footer.php'; ?> 