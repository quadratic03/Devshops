<?php
// Only start the session if one doesn't already exist
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'includes/functions.php';
require_once 'config/database.php';

// Check if user is logged in
if (!is_logged_in()) {
    header("Location: login.php");
    exit();
}

$error = '';
$success = '';
$product_id = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;

// If no product_id, redirect to home
if ($product_id <= 0) {
    header("Location: index.php");
    exit();
}

// Get product details
$product = get_product($product_id);
if (!$product) {
    header("Location: products.php");
    exit();
}

// Check if product is available
if ($product['status'] !== 'available') {
    $error = "This product is no longer available.";
}

// Check if buyer is trying to request access to their own product
if ($product['seller_id'] == $_SESSION['user_id']) {
    $error = "You cannot request access to your own product.";
}

include 'includes/header.php';
?>

<div class="container mt-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php">Home</a></li>
            <li class="breadcrumb-item"><a href="products.php">Products</a></li>
            <li class="breadcrumb-item"><a href="product.php?id=<?php echo $product_id; ?>"><?php echo htmlspecialchars($product['name']); ?></a></li>
            <li class="breadcrumb-item active" aria-current="page">Request Source Code Access</li>
        </ol>
    </nav>
    
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-envelope me-2"></i> Request Source Code Access</h5>
                </div>
                <div class="card-body">
                    <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <?php echo $error; ?>
                    </div>
                    <?php elseif ($success): ?>
                    <div class="alert alert-success">
                        <h5><i class="fas fa-check-circle me-2"></i> <?php echo $success; ?></h5>
                        <p>Your access request has been submitted. The seller will review your request.</p>
                        <div class="mt-3">
                            <a href="product.php?id=<?php echo $product_id; ?>" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-2"></i> Back to Product
                            </a>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="product-summary mb-4">
                        <div class="row">
                            <div class="col-md-3">
                                <?php if ($product['image']): ?>
                                <img src="uploads/products/<?php echo $product['image']; ?>" class="img-fluid rounded" alt="<?php echo htmlspecialchars($product['name']); ?>">
                                <?php else: ?>
                                <img src="assets/images/placeholder.png" class="img-fluid rounded" alt="No image available">
                                <?php endif; ?>
                            </div>
                            <div class="col-md-9">
                                <h4><?php echo htmlspecialchars($product['name']); ?></h4>
                                <p class="text-muted"><?php echo truncate_text($product['description'], 100); ?></p>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="h4 text-success mb-0"><?php echo format_currency($product['price']); ?></span>
                                    <span class="badge bg-info">Category: <?php echo htmlspecialchars($product['category_name']); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="text-center mb-4">
                        <p>To request access to the source code for <strong><?php echo htmlspecialchars($product['name']); ?></strong>, please contact the seller directly.</p>
                        
                        <div class="seller-info bg-light p-3 rounded mb-4">
                            <h6><i class="fas fa-user me-2"></i> Seller Information</h6>
                            <p><strong>Seller:</strong> <?php echo htmlspecialchars($product['seller_name']); ?></p>
                            <p><strong>Email:</strong> <?php echo htmlspecialchars($product['seller_email']); ?></p>
                            <?php if(!empty($product['phone_number'])): ?>
                            <p><strong>Phone:</strong> <?php echo htmlspecialchars($product['phone_number']); ?></p>
                            <?php endif; ?>
                        </div>
                        
                        <div class="d-grid gap-2 col-md-8 mx-auto">
                            <a href="message.php?to=<?php echo $product['seller_id']; ?>&product=<?php echo $product_id; ?>" class="btn btn-primary btn-lg">
                                <i class="fas fa-envelope me-2"></i> Send Message to Seller
                            </a>
                            <form method="post" action="product.php?id=<?php echo $product_id; ?>">
                                <button type="submit" name="request_access" class="btn btn-outline-success mt-2">
                                    <i class="fas fa-unlock-alt me-2"></i> Submit Source Code Access Request
                                </button>
                            </form>
                            <a href="product.php?id=<?php echo $product_id; ?>" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-2"></i> Back to Product
                            </a>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?> 