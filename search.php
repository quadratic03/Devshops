<?php
require_once 'includes/header.php';

// Get search query
$search_query = isset($_GET['q']) ? clean_input($_GET['q']) : '';

// Execute search if query exists
$products = [];
if (!empty($search_query)) {
    $sql = "SELECT p.*, c.name as category_name, u.username as seller_name, u.phone_number 
            FROM products p 
            JOIN categories c ON p.category_id = c.id 
            JOIN users u ON p.seller_id = u.id 
            WHERE p.status = 'available' AND 
            (p.name LIKE ? OR p.description LIKE ? OR c.name LIKE ?)
            ORDER BY p.created_at DESC";
    
    $search_param = "%$search_query%";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $search_param, $search_param, $search_param);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $products[] = $row;
        }
    }
}

// Get categories for sidebar
$categories = get_categories();
?>

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
        
        <!-- Search Filter -->
        <div class="card mt-4">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0">Refine Search</h5>
            </div>
            <div class="card-body">
                <form action="search.php" method="GET">
                    <div class="mb-3">
                        <label for="search" class="form-label">Search</label>
                        <input type="text" class="form-control" id="search" name="q" value="<?php echo htmlspecialchars($search_query); ?>">
                    </div>
                    
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
    
    <!-- Search Results -->
    <div class="col-md-9">
        <h2 class="mb-4">Search Results for "<?php echo htmlspecialchars($search_query); ?>"</h2>
        
        <?php if (empty($search_query)): ?>
        <div class="alert alert-info">
            Please enter a search term to find products.
        </div>
        <?php elseif (empty($products)): ?>
        <div class="alert alert-info">
            No products found matching "<?php echo htmlspecialchars($search_query); ?>". Try different keywords or browse our categories.
        </div>
        <?php else: ?>
        <p class="mb-4"><?php echo count($products); ?> product(s) found</p>
        
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