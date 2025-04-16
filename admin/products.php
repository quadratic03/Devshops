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

// Process product actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Delete product
    if (isset($_POST['delete_product'])) {
        $product_id = (int)$_POST['product_id'];
        
        // Get product info to delete image if needed
        $get_product = "SELECT image FROM products WHERE id = ?";
        $stmt = $conn->prepare($get_product);
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $product = $result->fetch_assoc();
            // Delete image file if it exists
            if (!empty($product['image']) && file_exists("../uploads/products/" . $product['image'])) {
                unlink("../uploads/products/" . $product['image']);
            }
            
            // Delete product
            $sql = "DELETE FROM products WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $product_id);
            
            if ($stmt->execute()) {
                $success = "Product deleted successfully.";
            } else {
                $error = "Error deleting product: " . $conn->error;
            }
        } else {
            $error = "Product not found.";
        }
    }
    
    // Change product status (approve/reject)
    if (isset($_POST['change_status'])) {
        $product_id = (int)$_POST['product_id'];
        $new_status = clean_input($_POST['status']);
        
        $sql = "UPDATE products SET status = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $new_status, $product_id);
        
        if ($stmt->execute()) {
            $success = "Product status changed successfully.";
        } else {
            $error = "Error changing product status: " . $conn->error;
        }
    }
}

// Get all products with user and category info
$sql = "SELECT p.*, u.username as seller_name, c.name as category_name 
        FROM products p 
        LEFT JOIN users u ON p.seller_id = u.id 
        LEFT JOIN categories c ON p.category_id = c.id 
        ORDER BY p.created_at DESC";
$result = $conn->query($sql);
$products = [];

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Products - DevMarket Philippines</title>
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
                    <h1 class="h2">Manage Products</h1>
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
                
                <!-- Products Table -->
                <div class="card mb-4">
                    <div class="card-header bg-dark text-white">
                        <h5 class="mb-0">All Products</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Image</th>
                                        <th>Title</th>
                                        <th>Category</th>
                                        <th>Price</th>
                                        <th>Seller</th>
                                        <th>Status</th>
                                        <th>Posted Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($products) > 0): ?>
                                        <?php foreach ($products as $product): ?>
                                            <tr>
                                                <td><?php echo $product['id']; ?></td>
                                                <td>
                                                    <img src="../<?php echo !empty($product['image']) ? 'uploads/products/' . $product['image'] : 'assets/images/placeholder.png'; ?>" 
                                                         class="img-thumbnail" style="width: 50px; height: 50px; object-fit: cover;" 
                                                         alt="<?php echo htmlspecialchars($product['name']); ?>">
                                                </td>
                                                <td><?php echo htmlspecialchars($product['name']); ?></td>
                                                <td><?php echo htmlspecialchars($product['category_name']); ?></td>
                                                <td>₱<?php echo number_format($product['price'], 2); ?></td>
                                                <td><?php echo htmlspecialchars($product['seller_name']); ?></td>
                                                <td>
                                                    <span class="badge <?php 
                                                        echo $product['status'] === 'active' ? 'bg-success' : 
                                                            ($product['status'] === 'pending' ? 'bg-warning' : 'bg-danger'); 
                                                    ?>">
                                                        <?php echo ucfirst($product['status']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo date('M d, Y', strtotime($product['created_at'])); ?></td>
                                                <td>
                                                    <button class="btn btn-sm btn-primary view-btn"
                                                            data-id="<?php echo $product['id']; ?>"
                                                            data-title="<?php echo htmlspecialchars($product['name']); ?>"
                                                            data-description="<?php echo htmlspecialchars($product['description']); ?>"
                                                            data-price="<?php echo number_format($product['price'], 2); ?>"
                                                            data-category="<?php echo htmlspecialchars($product['category_name']); ?>"
                                                            data-seller="<?php echo htmlspecialchars($product['seller_name']); ?>"
                                                            data-status="<?php echo $product['status']; ?>"
                                                            data-image="../<?php echo !empty($product['image']) ? 'uploads/products/' . $product['image'] : 'assets/images/placeholder.png'; ?>"
                                                            data-created="<?php echo date('M d, Y H:i', strtotime($product['created_at'])); ?>">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    
                                                    <?php if ($product['status'] === 'pending'): ?>
                                                    <button class="btn btn-sm btn-success status-btn"
                                                            data-id="<?php echo $product['id']; ?>"
                                                            data-title="<?php echo htmlspecialchars($product['name']); ?>"
                                                            data-status="active">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                    
                                                    <button class="btn btn-sm btn-danger status-btn"
                                                            data-id="<?php echo $product['id']; ?>"
                                                            data-title="<?php echo htmlspecialchars($product['name']); ?>"
                                                            data-status="rejected">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                    <?php endif; ?>
                                                    
                                                    <button class="btn btn-sm btn-danger delete-btn"
                                                            data-id="<?php echo $product['id']; ?>"
                                                            data-title="<?php echo htmlspecialchars($product['name']); ?>">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="9" class="text-center">No products found.</td>
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

    <!-- View Product Modal -->
    <div class="modal fade" id="viewProductModal" tabindex="-1" aria-labelledby="viewProductModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewProductModalLabel">Product Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-5">
                            <img id="view_image" src="" class="img-fluid rounded" alt="Product Image">
                        </div>
                        <div class="col-md-7">
                            <h4 id="view_title"></h4>
                            <p class="mb-1"><strong>Price:</strong> ₱<span id="view_price"></span></p>
                            <p class="mb-1"><strong>Category:</strong> <span id="view_category"></span></p>
                            <p class="mb-1"><strong>Seller:</strong> <span id="view_seller"></span></p>
                            <p class="mb-1"><strong>Status:</strong> <span id="view_status"></span></p>
                            <p class="mb-1"><strong>Date Posted:</strong> <span id="view_created"></span></p>
                            <hr>
                            <h5>Description</h5>
                            <p id="view_description"></p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Status Change Modal -->
    <div class="modal fade" id="changeStatusModal" tabindex="-1" aria-labelledby="changeStatusModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="changeStatusModalLabel">Change Product Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to <span id="status_action_text"></span> product "<span id="status_title"></span>"?</p>
                    <form action="products.php" method="POST">
                        <input type="hidden" id="status_product_id" name="product_id">
                        <input type="hidden" id="status_new" name="status">
                        <div class="text-end">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" name="change_status" class="btn btn-primary">Change Status</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Product Modal -->
    <div class="modal fade" id="deleteProductModal" tabindex="-1" aria-labelledby="deleteProductModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteProductModalLabel">Delete Product</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete product "<span id="delete_title"></span>"?</p>
                    <p class="text-danger">This action cannot be undone.</p>
                    <form action="products.php" method="POST">
                        <input type="hidden" id="delete_product_id" name="product_id">
                        <div class="text-end">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" name="delete_product" class="btn btn-danger">Delete Product</button>
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
            // Handle view button click
            const viewButtons = document.querySelectorAll('.view-btn');
            const viewModal = new bootstrap.Modal(document.getElementById('viewProductModal'));
            
            viewButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    const title = this.getAttribute('data-title');
                    const description = this.getAttribute('data-description');
                    const price = this.getAttribute('data-price');
                    const category = this.getAttribute('data-category');
                    const seller = this.getAttribute('data-seller');
                    const status = this.getAttribute('data-status');
                    const image = this.getAttribute('data-image');
                    const created = this.getAttribute('data-created');
                    
                    document.getElementById('view_title').textContent = title;
                    document.getElementById('view_description').textContent = description;
                    document.getElementById('view_price').textContent = price;
                    document.getElementById('view_category').textContent = category;
                    document.getElementById('view_seller').textContent = seller;
                    document.getElementById('view_status').textContent = status.charAt(0).toUpperCase() + status.slice(1);
                    document.getElementById('view_image').src = image;
                    document.getElementById('view_created').textContent = created;
                    
                    viewModal.show();
                });
            });
            
            // Handle status button click
            const statusButtons = document.querySelectorAll('.status-btn');
            const statusModal = new bootstrap.Modal(document.getElementById('changeStatusModal'));
            
            statusButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    const title = this.getAttribute('data-title');
                    const newStatus = this.getAttribute('data-status');
                    const actionText = newStatus === 'active' ? 'approve' : 'reject';
                    
                    document.getElementById('status_action_text').textContent = actionText;
                    document.getElementById('status_title').textContent = title;
                    document.getElementById('status_product_id').value = id;
                    document.getElementById('status_new').value = newStatus;
                    
                    statusModal.show();
                });
            });
            
            // Handle delete button click
            const deleteButtons = document.querySelectorAll('.delete-btn');
            const deleteModal = new bootstrap.Modal(document.getElementById('deleteProductModal'));
            
            deleteButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    const title = this.getAttribute('data-title');
                    
                    document.getElementById('delete_title').textContent = title;
                    document.getElementById('delete_product_id').value = id;
                    
                    deleteModal.show();
                });
            });
        });
    </script>
</body>
</html> 