<?php
// Only start the session if one doesn't already exist
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../includes/functions.php';

// Check if user is admin
if (!is_admin()) {
    // Redirect to login page
    header('Location: ../login.php');
    exit;
}

$categories = get_categories();
$error = '';
$success = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get form data
    $title = clean_input($_POST['title']);
    $description = clean_input($_POST['description']);
    $price = clean_input($_POST['price']);
    $category_id = (int)$_POST['category'];
    $seller_id = (int)$_POST['seller'];
    
    // Validate input
    if (empty($title) || empty($description) || empty($price) || $category_id <= 0 || $seller_id <= 0) {
        $error = 'All fields are required.';
    } else {
        // Process image upload
        $image_name = '';
        if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/jpg'];
            $file_type = $_FILES['image']['type'];
            
            if (in_array($file_type, $allowed_types)) {
                $upload_dir = '../uploads/products/';
                
                // Create directory if it doesn't exist
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $image_name = generate_random_string() . '_' . $_FILES['image']['name'];
                $upload_path = $upload_dir . $image_name;
                
                if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
                    // Image uploaded successfully
                } else {
                    $error = 'Failed to upload image.';
                }
            } else {
                $error = 'Invalid image type. Only JPEG, JPG and PNG are allowed.';
            }
        }
        
        if (empty($error)) {
            // Insert product into database
            $sql = "INSERT INTO products (title, description, price, image, seller_id, category_id) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssdsii", $title, $description, $price, $image_name, $seller_id, $category_id);
            
            if ($stmt->execute()) {
                $success = 'Product added successfully!';
            } else {
                $error = 'Error adding product: ' . $stmt->error;
            }
        }
    }
}

// Get all sellers
$sellers_query = "SELECT id, username, email FROM users WHERE user_type = 'seller'";
$sellers = $conn->query($sellers_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Product - DevMarket Philippines</title>
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
            <div class="col-md-3 col-lg-2 d-md-block bg-dark sidebar collapse">
                <div class="position-sticky pt-3">
                    <a href="../index.php" class="d-flex align-items-center mb-3 mb-md-0 me-md-auto text-white text-decoration-none">
                        <span class="fs-4"><span class="text-primary">web</span>DevMarket</span>
                    </a>
                    <hr class="text-white">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link text-white" href="dashboard.php">
                                <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active text-white" href="products.php">
                                <i class="fas fa-box me-2"></i> Products
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="users.php">
                                <i class="fas fa-users me-2"></i> Users
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="categories.php">
                                <i class="fas fa-tags me-2"></i> Categories
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="transactions.php">
                                <i class="fas fa-money-bill-wave me-2"></i> Transactions
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="reports.php">
                                <i class="fas fa-chart-bar me-2"></i> Reports
                            </a>
                        </li>
                        <li class="nav-item mt-5">
                            <a class="nav-link text-danger" href="../logout.php">
                                <i class="fas fa-sign-out-alt me-2"></i> Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Add New Product</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="products.php" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i> Back to Products
                        </a>
                    </div>
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
                
                <div class="card mb-4">
                    <div class="card-header bg-dark text-white">
                        <h5 class="mb-0">Product Information</h5>
                    </div>
                    <div class="card-body">
                        <form action="add_product.php" method="POST" enctype="multipart/form-data" class="needs-validation upload-form" novalidate>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="title" class="form-label">Product Title <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="title" name="title" required>
                                    <div class="invalid-feedback">
                                        Please enter a product title.
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label for="category" class="form-label">Category <span class="text-danger">*</span></label>
                                    <select class="form-select" id="category" name="category" required>
                                        <option value="">Select Category</option>
                                        <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category['id']; ?>"><?php echo $category['name']; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="invalid-feedback">
                                        Please select a category.
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="price" class="form-label">Price (PHP) <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text">₱</span>
                                        <input type="number" step="0.01" min="0" class="form-control" id="price" name="price" required>
                                        <div class="invalid-feedback">
                                            Please enter a valid price.
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label for="seller" class="form-label">Seller <span class="text-danger">*</span></label>
                                    <select class="form-select" id="seller" name="seller" required>
                                        <option value="">Select Seller</option>
                                        <?php if ($sellers->num_rows > 0): ?>
                                            <?php while ($seller = $sellers->fetch_assoc()): ?>
                                                <option value="<?php echo $seller['id']; ?>"><?php echo $seller['username']; ?> (<?php echo $seller['email']; ?>)</option>
                                            <?php endwhile; ?>
                                        <?php endif; ?>
                                    </select>
                                    <div class="invalid-feedback">
                                        Please select a seller.
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="description" class="form-label">Description <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="description" name="description" rows="4" required></textarea>
                                <div class="invalid-feedback">
                                    Please enter a product description.
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="image" class="form-label">Product Image</label>
                                <input type="file" class="form-control" id="product-image" name="image" accept="image/*">
                                <div class="form-text">Upload a product image (max 2MB, JPEG or PNG).</div>
                            </div>
                            
                            <div id="image-preview-container" class="preview-container" style="display: none;">
                                <img id="image-preview" class="preview-image" src="" alt="Image Preview">
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">Add Product</button>
                                <a href="products.php" class="btn btn-outline-secondary">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JavaScript -->
    <script src="../assets/js/script.js"></script>
    <script src="../assets/js/admin.js"></script>
</body>
</html> 