<?php
require_once 'includes/header.php';

// Check if product ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: products.php');
    exit;
}

$product_id = (int)$_GET['id'];
$product = get_product($product_id);

// Check if product exists
if (!$product) {
    header('Location: products.php');
    exit;
}

// Handle source code access request
$request_message = '';
$source_access_status = '';

if (is_logged_in() && $_SESSION['user_type'] !== 'seller' && $_SESSION['user_type'] !== 'admin') {
    // Check if user has already requested access
    $access_query = "SELECT * FROM source_access_requests WHERE product_id = ? AND buyer_id = ?";
    $stmt = $conn->prepare($access_query);
    $buyer_id = $_SESSION['user_id'];
    $stmt->bind_param("ii", $product_id, $buyer_id);
    $stmt->execute();
    $access_result = $stmt->get_result();
    
    if ($access_result->num_rows > 0) {
        $access_request = $access_result->fetch_assoc();
        $source_access_status = $access_request['status'];
    }
    
    // Process new request submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_access']) && empty($source_access_status)) {
        $seller_id = $product['seller_id'];
        
        $insert_query = "INSERT INTO source_access_requests (product_id, buyer_id, seller_id, status) VALUES (?, ?, ?, 'pending')";
        $stmt = $conn->prepare($insert_query);
        $stmt->bind_param("iii", $product_id, $buyer_id, $seller_id);
        
        if ($stmt->execute()) {
            $source_access_status = 'pending';
            $request_message = '<div class="alert alert-success">Your request for source code access has been submitted. The seller will review your request.</div>';
        } else {
            $request_message = '<div class="alert alert-danger">Error submitting your request. Please try again.</div>';
        }
    }
}

// Get seller information
$seller = get_user($product['seller_id']);
?>

<div class="container mt-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php">Home</a></li>
            <li class="breadcrumb-item"><a href="products.php">Products</a></li>
            <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($product['name']); ?></li>
        </ol>
    </nav>
    
    <?php echo $request_message; ?>
    
    <div class="row">
        <!-- Product Image -->
        <div class="col-md-5 mb-4">
            <div class="card">
                <div class="product-detail-img-container">
                    <?php if ($product['image']): ?>
                        <img src="uploads/products/<?php echo $product['image']; ?>" class="img-fluid rounded" alt="<?php echo htmlspecialchars($product['name']); ?>">
                    <?php else: ?>
                        <img src="assets/images/placeholder.png" class="img-fluid rounded" alt="No image available">
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Product Details -->
        <div class="col-md-7">
            <h2 class="mb-3"><?php echo htmlspecialchars($product['name']); ?></h2>
            
            <div class="mb-3">
                <span class="badge bg-primary"><?php echo htmlspecialchars($product['category_name']); ?></span>
                <span class="badge bg-<?php echo $product['status'] === 'available' ? 'success' : 'secondary'; ?>">
                    <?php echo ucfirst($product['status']); ?>
                </span>
            </div>
            
            <h3 class="text-primary mb-4"><?php echo format_currency($product['price']); ?></h3>
            
            <!-- Buy button section -->
            <div class="mb-4">
                <?php if (is_logged_in() && $_SESSION['user_type'] === 'buyer'): ?>
                    <div class="d-grid gap-2">
                        <a href="message.php?to=<?php echo $product['seller_id']; ?>&product=<?php echo $product['id']; ?>" class="btn btn-primary btn-lg mb-2">
                            <i class="fas fa-envelope me-2"></i> Send Message to Seller
                        </a>
                        <a href="direct_payment.php?product_id=<?php echo $product['id']; ?>" class="btn btn-outline-primary mb-3">
                            <i class="fas fa-key me-2"></i> Request Source Code Access
                        </a>
                    </div>
                <?php elseif (!is_logged_in()): ?>
                    <a href="login.php" class="btn btn-primary btn-lg mb-3 w-100">
                        <i class="fas fa-sign-in-alt me-2"></i> Login to Request Access
                    </a>
                <?php endif; ?>
            </div>
            
            <div class="mb-4">
                <h5>Description</h5>
                <p><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>
            </div>
            
            <!-- Source Code Access Section - Highlighted -->
            <div class="mb-4">
                <div class="card border-primary">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-code me-2"></i> Source Code Access</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <?php if (!is_logged_in() || ($_SESSION['user_type'] !== 'seller' && $_SESSION['user_type'] !== 'admin' && $source_access_status !== 'approved')): ?>
                                    <i class="fas fa-lock me-2"></i> <strong>Source code is locked</strong>
                                    <p class="text-muted mb-0 mt-2">Requires seller approval to access the source code</p>
                                <?php else: ?>
                                    <i class="fas fa-unlock me-2"></i> <strong>Source code is available</strong>
                                    <p class="text-muted mb-0 mt-2">You have permission to download this source code</p>
                                <?php endif; ?>
                            </div>
                            
                            <div>
                                <?php if (is_logged_in()): ?>
                                    <?php if (is_admin() || $product['seller_id'] == $_SESSION['user_id']): ?>
                                        <!-- Admin or product owner can download directly -->
                                        <a href="download.php?type=source&id=<?php echo $product['id']; ?>" class="btn btn-success btn-lg w-100 mb-2">
                                            <i class="fas fa-download me-2"></i> Download Source Code
                                        </a>
                                    <?php elseif ($source_access_status === 'approved'): ?>
                                        <a href="download.php?type=source&id=<?php echo $product_id; ?>" class="btn btn-success btn-lg w-100 mb-2">
                                            <i class="fas fa-download me-2"></i> Download Source Code
                                        </a>
                                    <?php elseif ($source_access_status === 'pending'): ?>
                                        <button class="btn btn-warning btn-lg w-100 mb-2" disabled>
                                            <i class="fas fa-clock me-2"></i> Access Request Pending
                                        </button>
                                    <?php elseif ($source_access_status === 'rejected'): ?>
                                        <button class="btn btn-danger btn-lg w-100 mb-2" disabled>
                                            <i class="fas fa-times-circle me-2"></i> Access Request Rejected
                                        </button>
                                    <?php else: ?>
                                        <form method="post" action="product.php?id=<?php echo $product['id']; ?>">
                                            <button type="submit" name="request_access" class="btn btn-outline-primary btn-lg w-100 mb-2">
                                                <i class="fas fa-key me-2"></i> Request Source Code Access
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <a href="login.php" class="btn btn-primary btn-lg w-100 mb-2">
                                        <i class="fas fa-sign-in-alt me-2"></i> Login to Purchase
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="mb-4">
                <h5>Seller Information</h5>
                <p>
                    <i class="fas fa-user me-2"></i> <?php echo htmlspecialchars($seller['username']); ?><br>
                    <i class="fas fa-envelope me-2"></i> <?php echo htmlspecialchars($seller['email']); ?><br>
                    <?php if (!empty($seller['phone_number'])): ?>
                    <i class="fas fa-phone me-2"></i> <?php echo htmlspecialchars($seller['phone_number']); ?>
                    <?php endif; ?>
                </p>
            </div>
            
            <div class="d-grid gap-2 d-md-block">
                <a href="products.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i> Back to Products
                </a>
            </div>
        </div>
    </div>
    
    <!-- Related Products -->
    <div class="mt-5">
        <h3 class="mb-4">Related Products</h3>
        <?php 
        $related_query = "SELECT p.*, c.name as category_name, u.username as seller_name 
                         FROM products p 
                         JOIN categories c ON p.category_id = c.id 
                         JOIN users u ON p.seller_id = u.id 
                         WHERE p.category_id = ? AND p.id != ? AND p.status = 'available' 
                         LIMIT 4";
        $stmt = $conn->prepare($related_query);
        $stmt->bind_param("ii", $product['category_id'], $product_id);
        $stmt->execute();
        $related_result = $stmt->get_result();
        
        if ($related_result->num_rows > 0):
        ?>
        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-4 g-4">
            <?php while($related = $related_result->fetch_assoc()): ?>
            <div class="col">
                <div class="card h-100 product-card">
                    <div class="product-img-container">
                        <?php if ($related['image']): ?>
                        <img src="uploads/products/<?php echo $related['image']; ?>" class="product-img" alt="<?php echo htmlspecialchars($related['name']); ?>">
                        <?php else: ?>
                        <img src="assets/images/placeholder.png" class="product-img" alt="No image available">
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <h5 class="card-title"><?php echo htmlspecialchars($related['name']); ?></h5>
                        <p class="card-text"><?php echo truncate_text($related['description'], 80); ?></p>
                        <div class="d-flex justify-content-between align-items-center mt-auto">
                            <span class="price-tag"><?php echo format_currency($related['price']); ?></span>
                            <a href="product.php?id=<?php echo $related['id']; ?>" class="btn btn-primary btn-sm">View Details</a>
                        </div>
                    </div>
                    <div class="card-footer text-muted">
                        <small>Category: <?php echo $related['category_name']; ?></small>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
        <?php else: ?>
        <div class="alert alert-info">No related products found.</div>
        <?php endif; ?>
    </div>
</div>

<style>
.product-detail-img-container {
    height: 400px;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

.product-detail-img-container img {
    max-height: 100%;
    max-width: 100%;
    object-fit: contain;
}
</style>

<?php require_once 'includes/footer.php'; ?> 