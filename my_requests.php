<?php
// Only start the session if one doesn't already exist
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'includes/functions.php';
require_once 'includes/dbconnect.php';

// Check if user is logged in and is a buyer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'buyer') {
    header("Location: login.php");
    exit();
}

$buyer_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Get all access requests for this buyer
$query = "SELECT sar.*, p.name as product_name, p.image as product_image, u.username as seller_username 
          FROM source_access_requests sar 
          JOIN products p ON sar.product_id = p.id 
          JOIN users u ON sar.seller_id = u.id 
          WHERE sar.buyer_id = ? 
          ORDER BY sar.created_at DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $buyer_id);
$stmt->execute();
$result = $stmt->get_result();
$requests = [];

while ($row = $result->fetch_assoc()) {
    $requests[] = $row;
}

// Count requests by status
$pending_count = 0;
$approved_count = 0;
$rejected_count = 0;

foreach ($requests as $request) {
    if ($request['status'] == 'pending') {
        $pending_count++;
    } elseif ($request['status'] == 'approved') {
        $approved_count++;
    } elseif ($request['status'] == 'rejected') {
        $rejected_count++;
    }
}

// Get total count
$total_count = count($requests);

// Handle tab filter
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'all';

include 'includes/header.php';
?>

<div class="container mt-4">
    <h2>My Source Code Access Requests</h2>
    
    <div class="row mt-4">
        <div class="col-md-12">
            <div class="card mb-4">
                <div class="card-header">
                    <ul class="nav nav-tabs card-header-tabs">
                        <li class="nav-item">
                            <a class="nav-link <?php echo $active_tab == 'all' ? 'active' : ''; ?>" href="my_requests.php?tab=all">
                                All Requests <span class="badge bg-secondary"><?php echo $total_count; ?></span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $active_tab == 'pending' ? 'active' : ''; ?>" href="my_requests.php?tab=pending">
                                Pending <span class="badge bg-warning"><?php echo $pending_count; ?></span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $active_tab == 'approved' ? 'active' : ''; ?>" href="my_requests.php?tab=approved">
                                Approved <span class="badge bg-success"><?php echo $approved_count; ?></span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $active_tab == 'rejected' ? 'active' : ''; ?>" href="my_requests.php?tab=rejected">
                                Rejected <span class="badge bg-danger"><?php echo $rejected_count; ?></span>
                            </a>
                        </li>
                    </ul>
                </div>
                <div class="card-body">
                    <?php if (empty($requests)): ?>
                        <div class="alert alert-info">
                            You haven't made any source code access requests yet. Browse products and request access to start viewing.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Seller</th>
                                        <th>Request Date</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($requests as $request): 
                                        // Skip if not matching the active tab filter
                                        if ($active_tab != 'all' && $request['status'] != $active_tab) continue;
                                    ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <img src="<?php echo $request['product_image']; ?>" alt="<?php echo $request['product_name']; ?>" class="img-thumbnail me-2" style="width: 50px; height: 50px; object-fit: cover;">
                                                    <a href="product.php?id=<?php echo $request['product_id']; ?>"><?php echo $request['product_name']; ?></a>
                                                </div>
                                            </td>
                                            <td><?php echo $request['seller_username']; ?></td>
                                            <td><?php echo date('M d, Y h:i A', strtotime($request['created_at'])); ?></td>
                                            <td>
                                                <?php if ($request['status'] == 'pending'): ?>
                                                    <span class="badge bg-warning">Pending</span>
                                                <?php elseif ($request['status'] == 'approved'): ?>
                                                    <span class="badge bg-success">Approved</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Rejected</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="product.php?id=<?php echo $request['product_id']; ?>" class="btn btn-sm btn-primary">View Product</a>
                                                
                                                <?php if ($request['status'] == 'approved'): ?>
                                                    <a href="download.php?type=source&id=<?php echo $request['product_id']; ?>" class="btn btn-sm btn-success">Download Code</a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header bg-light">
                    <h5>About Source Code Access</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="card mb-3">
                                <div class="card-body text-center">
                                    <i class="fas fa-clock fa-3x text-warning mb-3"></i>
                                    <h5>Pending</h5>
                                    <p>Your request is awaiting seller review. Please be patient as sellers evaluate access requests.</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card mb-3">
                                <div class="card-body text-center">
                                    <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                                    <h5>Approved</h5>
                                    <p>Congratulations! You can now download and use the source code according to the seller's terms.</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card mb-3">
                                <div class="card-body text-center">
                                    <i class="fas fa-times-circle fa-3x text-danger mb-3"></i>
                                    <h5>Rejected</h5>
                                    <p>Your request was declined. You may contact the seller for more information or submit a new request.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?> 