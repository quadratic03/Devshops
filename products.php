<?php
require_once 'includes/header.php';

// Get category ID from query parameter
$category_id = isset($_GET['category']) ? (int)$_GET['category'] : 0;

// Get products based on category
$products = get_products(0, $category_id);
$categories = get_categories();

// Get current category name
$current_category = 'All Products';
if ($category_id > 0) {
    foreach ($categories as $category) {
        if ($category['id'] == $category_id) {
            $current_category = $category['name'];
            break;
        }
    }
}
?>

<div class="row">
    <!-- Category Sidebar -->
    <div class="col-md-3 mb-4">
        <div class="card category-sidebar">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0">Categories</h5>
            </div>
            <div class="list-group list-group-flush">
                <a href="products.php" class="list-group-item list-group-item-action <?php echo $category_id == 0 ? 'active' : ''; ?>">
                    All Products
                </a>
                <?php foreach ($categories as $category): ?>
                <a href="products.php?category=<?php echo $category['id']; ?>" 
                   class="list-group-item list-group-item-action <?php echo $category_id == $category['id'] ? 'active' : ''; ?>">
                    <?php echo $category['name']; ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Filter Options -->
        <div class="card mt-4">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0">Filter</h5>
            </div>
            <div class="card-body">
                <form action="products.php" method="GET">
                    <?php if ($category_id > 0): ?>
                    <input type="hidden" name="category" value="<?php echo $category_id; ?>">
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <label for="price-range" class="form-label">Max Price: ₱<span id="price-value">5000</span></label>
                        <input type="range" class="form-range" id="price-range" name="max_price" min="0" max="10000" step="500" value="5000">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Sort By</label>
                        <select class="form-select" name="sort">
                            <option value="newest">Newest First</option>
                            <option value="price_low">Price: Low to High</option>
                            <option value="price_high">Price: High to Low</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100">Apply Filter</button>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Products Grid -->
    <div class="col-md-9">
        <h2 class="mb-4"><?php echo $current_category; ?></h2>
        
        <?php if (empty($products)): ?>
        <div class="alert alert-info">
            No products available in this category. Check back later!
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
                        <!-- Source code badge -->
                        <div class="source-code-badge">
                            <i class="fas fa-code"></i> Source Available
                        </div>
                    </div>
                    <div class="card-body">
                        <h5 class="card-title"><?php echo $product['name']; ?></h5>
                        <p class="card-text"><?php echo truncate_text($product['description'], 80); ?></p>
                        <div class="d-flex justify-content-between align-items-center mt-auto">
                            <span class="price-tag"><?php echo format_currency($product['price']); ?></span>
                            <div class="btn-group">
                                <a href="product.php?id=<?php echo $product['id']; ?>" class="btn btn-primary btn-sm">
                                    <i class="fas fa-eye me-1"></i> View Details
                                </a>
                                <button type="button" class="btn btn-secondary btn-sm" data-bs-toggle="tooltip" data-bs-placement="top" title="Source code requires approval">
                                    <i class="fas fa-lock me-1"></i> Source Code
                                </button>
                            </div>
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

<style>
.product-card {
    position: relative;
    transition: transform 0.3s ease;
    height: 100%;
}

.product-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 20px rgba(0,0,0,0.1);
}

.product-img-container {
    height: 200px;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
}

.product-img {
    max-height: 100%;
    max-width: 100%;
    object-fit: contain;
}

.source-code-badge {
    position: absolute;
    top: 10px;
    right: 10px;
    background-color: rgba(52, 58, 64, 0.85);
    color: white;
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 0.8em;
    display: flex;
    align-items: center;
    gap: 5px;
    z-index: 1;
}

.price-tag {
    font-weight: bold;
    color: #2c7be5;
    font-size: 1.2em;
}
</style>

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

<script>
// Initialize tooltips
document.addEventListener('DOMContentLoaded', function() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
    });
});
</script>

<?php require_once 'includes/footer.php'; ?> 