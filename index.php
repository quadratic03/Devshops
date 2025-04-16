<?php
require_once 'includes/header.php';
$products = get_products(8); // Get 8 latest products for homepage
$categories = get_categories();
?>

<!-- Hero Section -->
<div class="bg-primary text-white py-5 mb-4 rounded">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="display-4 fw-bold">DevMarket Philippines</h1>
                <p class="lead">Buy and sell digital products, source code, UI templates, and systems created by Filipino developers.</p>
                <a href="products.php" class="btn btn-light btn-lg">Browse Products</a>
                <?php if (!is_logged_in()): ?>
                <a href="register.php" class="btn btn-outline-light btn-lg ms-2">Start Selling</a>
                <?php endif; ?>
            </div>
            <div class="col-md-4 d-none d-md-block">
                <img src="assets/images/hero-image.svg" alt="DevMarket Hero" class="img-fluid">
            </div>
        </div>
    </div>
</div>

<!-- Main Content -->
<div class="row">
    <!-- Category Sidebar -->
    <div class="col-md-3 mb-4">
        <div class="card category-sidebar">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0">Categories</h5>
            </div>
            <div class="list-group list-group-flush">
                <a href="products.php" class="list-group-item list-group-item-action">All Products</a>
                <?php foreach ($categories as $category): ?>
                <a href="products.php?category=<?php echo $category['id']; ?>" class="list-group-item list-group-item-action">
                    <?php echo $category['name']; ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    
    <!-- Products Grid -->
    <div class="col-md-9">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Featured Products</h2>
            <a href="products.php" class="btn btn-outline-primary">View All</a>
        </div>
        
        <?php if (empty($products)): ?>
        <div class="alert alert-info">
            No products available at the moment. Check back later!
        </div>
        <?php else: ?>
        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
            <?php foreach ($products as $product): ?>
            <div class="col">
                <div class="card h-100 product-card">
                    <div class="product-img-container">
                        <?php if ($product['image']): ?>
                        <img src="uploads/products/<?php echo $product['image']; ?>" class="product-img" alt="<?php echo $product['name']; ?>">
                        <?php else: ?>
                        <img src="assets/images/placeholder.png" class="product-img" alt="No image available">
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <h5 class="card-title"><?php echo $product['name']; ?></h5>
                        <p class="card-text"><?php echo truncate_text($product['description'], 80); ?></p>
                        <div class="d-flex justify-content-between align-items-center mt-auto">
                            <span class="price-tag"><?php echo format_currency($product['price']); ?></span>
                            <button type="button" class="btn btn-primary"
                                data-bs-toggle="modal" 
                                data-bs-target="#productModal"
                                data-product-id="<?php echo $product['id']; ?>"
                                data-product-title="<?php echo $product['name']; ?>"
                                data-product-price="<?php echo format_currency($product['price']); ?>"
                                data-product-description="<?php echo $product['description']; ?>"
                                data-seller-phone="<?php echo $product['phone_number']; ?>"
                                data-seller-username="<?php echo $product['seller_name']; ?>">
                                Buy Now
                            </button>
                        </div>
                    </div>
                    <div class="card-footer text-muted">
                        <small>Category: <?php echo $product['category_name']; ?></small>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Product Modal -->
<div class="modal fade product-modal" id="productModal" tabindex="-1" aria-labelledby="productModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="productModalLabel"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <h4 class="modal-price text-primary"></h4>
                        <p class="modal-description"></p>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-dark text-white">
                                <h5 class="mb-0">Contact Seller</h5>
                            </div>
                            <div class="card-body">
                                <p><i class="fas fa-phone-alt me-2"></i> <span class="modal-phone"></span></p>
                                <a href="#" class="btn btn-primary messenger-link w-100 mb-2">
                                    <i class="fab fa-facebook-messenger me-2"></i> Message on Messenger
                                </a>
                                <?php if (is_logged_in()): ?>
                                <button class="btn btn-success w-100">
                                    <i class="fas fa-paper-plane me-2"></i> Send Inquiry
                                </button>
                                <?php else: ?>
                                <p class="small text-muted mt-2">Please <a href="login.php">login</a> to contact the seller directly on our platform.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?> 