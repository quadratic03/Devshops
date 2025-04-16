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

// Process actions
$error = '';
$success = '';

// Handle resell product (make available again)
if (isset($_POST['action']) && $_POST['action'] == 'resell_product') {
    $product_id = (int)$_POST['product_id'];
    
    // Verify product belongs to this seller and is sold
    $sql = "SELECT * FROM products WHERE id = ? AND seller_id = ? AND status = 'sold'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $product_id, $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Update the product to make it available again
        $update_sql = "UPDATE products SET status = 'available' WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("i", $product_id);
        
        if ($update_stmt->execute()) {
            $success = "Product is now available for sale again.";
        } else {
            $error = "Error updating product status: " . $conn->error;
        }
    } else {
        $error = "You don't have permission to update this product or it's not in 'sold' status.";
    }
}

// Handle product status change
if (isset($_POST['action']) && $_POST['action'] == 'change_status') {
    $product_id = (int)$_POST['product_id'];
    $new_status = clean_input($_POST['status']);
    
    // Verify product belongs to this seller
    $sql = "SELECT * FROM products WHERE id = ? AND seller_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $product_id, $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $product = $result->fetch_assoc();
        
        // Check if changing to "sold" status
        if ($new_status === 'sold') {
            // Begin transaction
            $conn->begin_transaction();
            
            try {
                // Update product status
                $update_sql = "UPDATE products SET status = ? WHERE id = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("si", $new_status, $product_id);
                $update_stmt->execute();
                
                // Create transaction record for the sold item
                $seller_id = $_SESSION['user_id'];
                $amount = $product['price'];
                $platform_fee = $amount * 0.10; // 10% platform fee
                
                // For sold items marked by seller (self-reporting)
                // We'll use a placeholder buyer ID and "manual" payment method
                $buyer_id = 1; // Admin/System account ID
                $payment_method = "manual";
                $reference = "SELF-REPORTED-" . date('YmdHis');
                $notes = "Product marked as sold by seller on " . date('Y-m-d H:i:s');
                
                $trans_sql = "INSERT INTO transactions (product_id, buyer_id, seller_id, amount, commission_rate, platform_fee, 
                                status, payment_method, reference_number, notes) 
                              VALUES (?, ?, ?, ?, 10.00, ?, 'completed', ?, ?, ?)";
                $trans_stmt = $conn->prepare($trans_sql);
                $trans_stmt->bind_param("iiiddsss", $product_id, $buyer_id, $seller_id, $amount, $platform_fee, $payment_method, $reference, $notes);
                $trans_stmt->execute();
                
                // Commit transaction
                $conn->commit();
                $success = "Product marked as sold successfully. Revenue has been added to your account.";
            } catch (Exception $e) {
                // Rollback transaction on error
                $conn->rollback();
                $error = "Error updating product status: " . $e->getMessage();
            }
        } else {
            // Regular status update
            $update_sql = "UPDATE products SET status = ? WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("si", $new_status, $product_id);
            
            if ($update_stmt->execute()) {
                $success = "Product status updated successfully.";
            } else {
                $error = "Error updating product status: " . $conn->error;
            }
        }
    } else {
        $error = "You don't have permission to update this product.";
    }
}

// Handle product deletion
if (isset($_POST['action']) && $_POST['action'] == 'delete_product') {
    $product_id = (int)$_POST['product_id'];
    
    // Verify product belongs to this seller and isn't sold
    $sql = "SELECT * FROM products WHERE id = ? AND seller_id = ? AND status != 'sold'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $product_id, $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Check if product has transactions
        $trans_sql = "SELECT COUNT(*) as count FROM transactions WHERE product_id = ?";
        $trans_stmt = $conn->prepare($trans_sql);
        $trans_stmt->bind_param("i", $product_id);
        $trans_stmt->execute();
        $trans_result = $trans_stmt->get_result()->fetch_assoc();
        
        if ($trans_result['count'] > 0) {
            $error = "Cannot delete product with existing transactions. You can hide it instead.";
        } else {
            // Delete product
            $delete_sql = "DELETE FROM products WHERE id = ?";
            $delete_stmt = $conn->prepare($delete_sql);
            $delete_stmt->bind_param("i", $product_id);
            
            if ($delete_stmt->execute()) {
                $success = "Product deleted successfully.";
            } else {
                $error = "Error deleting product: " . $conn->error;
            }
        }
    } else {
        $error = "You don't have permission to delete this product or it has been sold.";
    }
}

// Get seller's products
$sql = "SELECT p.*, c.name as category_name 
        FROM products p 
        JOIN categories c ON p.category_id = c.id 
        WHERE p.seller_id = ? 
        ORDER BY p.created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$products = [];

if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Products - DevMarket Philippines</title>
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
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-box me-2"></i> My Products</h5>
                        <a href="add_product.php" class="btn btn-light btn-sm">
                            <i class="fas fa-plus-circle me-2"></i> Add New Product
                        </a>
                    </div>
                    <div class="card-body">
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
                        
                        <?php if (count($products) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Image</th>
                                            <th>Product</th>
                                            <th>Category</th>
                                            <th>Price</th>
                                            <th>Status</th>
                                            <th>Created</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($products as $product): ?>
                                            <tr>
                                                <td><?php echo $product['id']; ?></td>
                                                <td>
                                                    <?php if ($product['image']): ?>
                                                        <img src="<?php echo !empty($product['image']) ? '../uploads/products/' . $product['image'] : '../assets/img/no-image.png'; ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="img-thumbnail" style="max-width: 50px;">
                                                    <?php else: ?>
                                                        <div class="text-center text-muted small">No image</div>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($product['name']); ?></td>
                                                <td><?php echo htmlspecialchars($product['category_name']); ?></td>
                                                <td><?php echo format_currency($product['price']); ?></td>
                                                <td>
                                                    <?php if ($product['status'] == 'available'): ?>
                                                        <span class="badge bg-success">Available</span>
                                                    <?php elseif ($product['status'] == 'sold'): ?>
                                                        <span class="badge bg-warning">Sold</span>
                                                    <?php elseif ($product['status'] == 'hidden'): ?>
                                                        <span class="badge bg-secondary">Hidden</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger">Deleted</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo date('M d, Y', strtotime($product['created_at'])); ?></td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <a href="../product.php?id=<?php echo $product['id']; ?>" class="btn btn-info" title="View">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <a href="edit_product.php?id=<?php echo $product['id']; ?>" class="btn btn-primary" title="Edit">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <?php if ($product['status'] == 'sold'): ?>
                                                            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#resellModal" data-id="<?php echo $product['id']; ?>" data-name="<?php echo htmlspecialchars($product['name']); ?>" title="Make Available Again">
                                                                <i class="fas fa-sync-alt"></i>
                                                            </button>
                                                        <?php else: ?>
                                                            <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#statusModal" data-id="<?php echo $product['id']; ?>" data-name="<?php echo htmlspecialchars($product['name']); ?>" data-status="<?php echo $product['status']; ?>" title="Change Status">
                                                                <i class="fas fa-exchange-alt"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                        <?php if ($product['status'] != 'sold'): ?>
                                                            <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal" data-id="<?php echo $product['id']; ?>" data-name="<?php echo htmlspecialchars($product['name']); ?>" title="Delete">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <p class="mb-0">You haven't added any products yet. <a href="add_product.php">Add your first product</a>.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Status Change Modal -->
    <div class="modal fade" id="statusModal" tabindex="-1" aria-labelledby="statusModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title" id="statusModalLabel">Change Product Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="change_status">
                        <input type="hidden" name="product_id" id="status_product_id">
                        
                        <p>Change status for: <strong id="status_product_name"></strong></p>
                        
                        <div class="alert alert-info" id="status_sold_message" style="display:none;">
                            <i class="fas fa-info-circle me-2"></i> This product is already marked as sold. You cannot change its status.
                        </div>
                        
                        <div class="alert alert-warning" id="status_confirmation" style="display:none;">
                            <p><i class="fas fa-exclamation-triangle me-2"></i> <strong>Marking this product as sold will:</strong></p>
                            <ul>
                                <li>Record a completed sale transaction</li>
                                <li>Add revenue to your account (minus platform fee)</li>
                                <li>Make the product unavailable for purchase</li>
                            </ul>
                            <p class="mb-0"><strong>Note:</strong> This action cannot be undone.</p>
                        </div>
                        
                        <div class="mb-3">
                            <label for="status" class="form-label">New Status</label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="available">Available</option>
                                <option value="hidden">Hidden</option>
                                <option value="sold">Sold</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning">Change Status</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Delete Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="deleteModalLabel">Delete Product</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="delete_product">
                        <input type="hidden" name="product_id" id="delete_product_id">
                        
                        <div class="alert alert-danger">
                            <p>Are you sure you want to delete: <strong id="delete_product_name"></strong>?</p>
                            <p class="mb-0"><strong>Warning:</strong> This action cannot be undone. All product data will be permanently removed.</p>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete Product</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Resell Modal -->
    <div class="modal fade" id="resellModal" tabindex="-1" aria-labelledby="resellModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="resellModalLabel">Make Product Available Again</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="resell_product">
                        <input type="hidden" name="product_id" id="resell_product_id">
                        
                        <div class="alert alert-info">
                            <p><i class="fas fa-info-circle me-2"></i> You are about to make <strong id="resell_product_name"></strong> available for sale again.</p>
                            <p class="mb-0">This will change the product status from "Sold" to "Available" so new customers can purchase it.</p>
                        </div>
                        
                        <p>This is useful for:</p>
                        <ul>
                            <li>Digital products that can be sold multiple times</li>
                            <li>Products with multiple units in stock</li>
                            <li>Products that were mistakenly marked as sold</li>
                        </ul>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Make Available</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <?php include_once __DIR__ . '/../includes/footer.php'; ?>
    
    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JavaScript -->
    <script src="../assets/js/script.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Status Modal
            document.querySelectorAll('[data-bs-target="#statusModal"]').forEach(function(button) {
                button.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    const name = this.getAttribute('data-name');
                    const status = this.getAttribute('data-status');
                    const statusSelect = document.getElementById('status');
                    
                    document.getElementById('status_product_id').value = id;
                    document.getElementById('status_product_name').textContent = name;
                    
                    // Reset the select options
                    statusSelect.innerHTML = '';
                    
                    // Add appropriate options based on current status
                    if (status === 'sold') {
                        // If already sold, only show sold status
                        const option = document.createElement('option');
                        option.value = 'sold';
                        option.textContent = 'Sold';
                        statusSelect.appendChild(option);
                        
                        // Show info message
                        document.getElementById('status_sold_message').style.display = 'block';
                        document.getElementById('status_confirmation').style.display = 'none';
                    } else {
                        // Add standard options
                        const availableOption = document.createElement('option');
                        availableOption.value = 'available';
                        availableOption.textContent = 'Available';
                        statusSelect.appendChild(availableOption);
                        
                        const hiddenOption = document.createElement('option');
                        hiddenOption.value = 'hidden';
                        hiddenOption.textContent = 'Hidden';
                        statusSelect.appendChild(hiddenOption);
                        
                        const soldOption = document.createElement('option');
                        soldOption.value = 'sold';
                        soldOption.textContent = 'Sold';
                        statusSelect.appendChild(soldOption);
                        
                        // Set current status
                        statusSelect.value = status;
                        
                        // Hide sold message
                        document.getElementById('status_sold_message').style.display = 'none';
                        document.getElementById('status_confirmation').style.display = 'none';
                    }
                    
                    // Listen for changes to show confirmation message when selecting "sold"
                    statusSelect.addEventListener('change', function() {
                        if (this.value === 'sold') {
                            document.getElementById('status_confirmation').style.display = 'block';
                        } else {
                            document.getElementById('status_confirmation').style.display = 'none';
                        }
                    });
                });
            });
            
            // Resell Modal
            document.querySelectorAll('[data-bs-target="#resellModal"]').forEach(function(button) {
                button.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    const name = this.getAttribute('data-name');
                    
                    document.getElementById('resell_product_id').value = id;
                    document.getElementById('resell_product_name').textContent = name;
                });
            });
            
            // Delete Modal
            document.querySelectorAll('[data-bs-target="#deleteModal"]').forEach(function(button) {
                button.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    const name = this.getAttribute('data-name');
                    
                    document.getElementById('delete_product_id').value = id;
                    document.getElementById('delete_product_name').textContent = name;
                });
            });
        });
    </script>
</body>
</html> 