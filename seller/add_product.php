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

// Get categories for dropdown
$categories_sql = "SELECT * FROM categories ORDER BY name ASC";
$categories_result = $conn->query($categories_sql);
$categories = [];

if ($categories_result && $categories_result->num_rows > 0) {
    while($row = $categories_result->fetch_assoc()) {
        $categories[] = $row;
    }
}

// Messages
$errors = [];
$success = '';

// Form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate form data
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = floatval($_POST['price'] ?? 0);
    $stock = intval($_POST['stock'] ?? 0);
    $category_id = intval($_POST['category_id'] ?? 0);
    $status = trim($_POST['status'] ?? 'active');
    
    // Validation
    if (empty($name)) {
        $errors[] = "Product name is required";
    }
    
    if (empty($description)) {
        $errors[] = "Product description is required";
    }
    
    if ($price <= 0) {
        $errors[] = "Price must be greater than 0";
    }
    
    if ($stock < 0) {
        $errors[] = "Stock cannot be negative";
    }
    
    if ($category_id <= 0) {
        $errors[] = "Please select a valid category";
    }
    
    // Image upload handling
    $image_name = '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $file_name = $_FILES['image']['name'];
        $file_tmp = $_FILES['image']['tmp_name'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        if (!in_array($file_ext, $allowed)) {
            $errors[] = "Image extension not allowed, please choose a JPG, PNG or GIF file.";
        } else {
            // Create unique filename
            $image_name = uniqid('product_') . '.' . $file_ext;
            $upload_dir = __DIR__ . '/../uploads/products/';
            
            // Create directory if it doesn't exist
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $upload_path = $upload_dir . $image_name;
            
            if (!move_uploaded_file($file_tmp, $upload_path)) {
                $errors[] = "Failed to upload image. Please try again.";
            }
        }
    } else {
        $errors[] = "Product image is required";
    }
    
    // Source code file upload handling
    $source_file_name = '';
    if (isset($_FILES['source_file']) && $_FILES['source_file']['error'] == 0) {
        $allowed_source = ['zip', 'rar', 'tar', 'gz', 'pdf', 'doc', 'docx', 'txt', 'cpp', 'php', 'html', 'css', 'js'];
        $source_file = $_FILES['source_file']['name'];
        $source_tmp = $_FILES['source_file']['tmp_name'];
        $source_ext = strtolower(pathinfo($source_file, PATHINFO_EXTENSION));
        
        if (!in_array($source_ext, $allowed_source)) {
            $errors[] = "Source file extension not allowed. Allowed formats: ZIP, RAR, TAR, GZ, PDF, DOC, DOCX, TXT and common source code files.";
        } else {
            // Create unique filename
            $source_file_name = uniqid('source_') . '.' . $source_ext;
            $source_upload_dir = __DIR__ . '/../uploads/sourcecode/';
            
            // Create directory if it doesn't exist
            if (!file_exists($source_upload_dir)) {
                mkdir($source_upload_dir, 0777, true);
            }
            
            $source_upload_path = $source_upload_dir . $source_file_name;
            
            if (!move_uploaded_file($source_tmp, $source_upload_path)) {
                $errors[] = "Failed to upload source file. Please try again.";
            }
        }
    } else {
        $errors[] = "Source code file is required";
    }
    
    // Insert product if no errors
    if (empty($errors)) {
        $sql = "INSERT INTO products (name, description, price, category_id, seller_id, image, file_path, status, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssdissss", $name, $description, $price, $category_id, $_SESSION['user_id'], $image_name, $source_file_name, $status);
        
        if ($stmt->execute()) {
            $success = "Product added successfully!";
            // Clear form data
            $name = $description = '';
            $price = 0;
            $category_id = 0;
            $status = 'active';
        } else {
            $errors[] = "Error adding product: " . $conn->error;
            
            // Remove uploaded files if product insertion failed
            if (!empty($image_name)) {
                @unlink($upload_dir . $image_name);
            }
            if (!empty($source_file_name)) {
                @unlink($source_upload_dir . $source_file_name);
            }
        }
    }
}
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
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-plus-circle me-2"></i> Add New Product</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?php echo $error; ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($success)): ?>
                            <div class="alert alert-success">
                                <?php echo $success; ?>
                            </div>
                        <?php endif; ?>
                        
                        <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post" enctype="multipart/form-data">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="name" class="form-label">Product Name*</label>
                                    <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($name ?? ''); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="category_id" class="form-label">Category*</label>
                                    <select class="form-select" id="category_id" name="category_id" required>
                                        <option value="">Select Category</option>
                                        <?php foreach ($categories as $category): ?>
                                            <option value="<?php echo $category['id']; ?>" <?php echo ($category_id ?? 0) == $category['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($category['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="price" class="form-label">Price (PHP)*</label>
                                    <div class="input-group">
                                        <span class="input-group-text">₱</span>
                                        <input type="number" class="form-control" id="price" name="price" min="0.01" step="0.01" value="<?php echo htmlspecialchars($price ?? '0.00'); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label for="status" class="form-label">Status*</label>
                                    <select class="form-select" id="status" name="status" required>
                                        <option value="available" <?php echo ($status ?? '') === 'available' ? 'selected' : ''; ?>>Available</option>
                                        <option value="sold" <?php echo ($status ?? '') === 'sold' ? 'selected' : ''; ?>>Sold</option>
                                        <option value="hidden" <?php echo ($status ?? '') === 'hidden' ? 'selected' : ''; ?>>Hidden</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="description" class="form-label">Product Description*</label>
                                <textarea class="form-control" id="description" name="description" rows="5" required><?php echo htmlspecialchars($description ?? ''); ?></textarea>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="image" class="form-label">Product Image*</label>
                                    <input type="file" class="form-control" id="image" name="image" accept="image/*" required>
                                    <div class="form-text">Upload a clear image of your product. JPG, PNG or GIF only.</div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="source_file" class="form-label">Source Code File*</label>
                                <input type="file" class="form-control" id="source_file" name="source_file" required>
                                <div class="form-text">Upload source code or documentation file. Accepted formats: ZIP, RAR, TAR, GZ, PDF, DOC, DOCX, TXT, and common source code files.</div>
                            </div>
                            
                            <div class="mb-3">
                                <div class="form-text mb-2">
                                    <strong>Note:</strong> By adding a product, you agree to our Terms of Service and Seller Guidelines.
                                </div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-plus-circle me-2"></i> Add Product
                                </button>
                                <a href="my_products.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left me-2"></i> Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <div class="card mt-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i> Tips for Adding Products</h5>
                    </div>
                    <div class="card-body">
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item">
                                <i class="fas fa-camera text-info me-2"></i>
                                <strong>Images:</strong> Upload clear, high-quality images that show your product from multiple angles.
                            </li>
                            <li class="list-group-item">
                                <i class="fas fa-align-left text-info me-2"></i>
                                <strong>Description:</strong> Write detailed descriptions including features, benefits, and technical specifications.
                            </li>
                            <li class="list-group-item">
                                <i class="fas fa-tags text-info me-2"></i>
                                <strong>Pricing:</strong> Research competitive pricing and set a fair price for your product.
                            </li>
                            <li class="list-group-item">
                                <i class="fas fa-list-ol text-info me-2"></i>
                                <strong>Inventory:</strong> Keep your stock levels accurate to avoid overselling.
                            </li>
                            <li class="list-group-item">
                                <i class="fas fa-th-large text-info me-2"></i>
                                <strong>Category:</strong> Choose the most relevant category to help buyers find your product.
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include_once __DIR__ . '/../includes/footer.php'; ?>
    
    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JavaScript -->
    <script src="../assets/js/script.js"></script>
    <script>
        // Preview image before upload
        document.getElementById('image').addEventListener('change', function(e) {
            const fileInput = e.target;
            if (fileInput.files && fileInput.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    // Create image preview if it doesn't exist
                    let preview = document.getElementById('image-preview');
                    if (!preview) {
                        preview = document.createElement('div');
                        preview.id = 'image-preview';
                        preview.className = 'mt-2';
                        fileInput.parentNode.appendChild(preview);
                    }
                    
                    preview.innerHTML = `
                        <div class="card" style="max-width: 200px;">
                            <img src="${e.target.result}" class="card-img-top" alt="Product Preview">
                            <div class="card-body p-2 text-center">
                                <p class="card-text small mb-0">Image Preview</p>
                            </div>
                        </div>
                    `;
                }
                reader.readAsDataURL(fileInput.files[0]);
            }
        });
    </script>
</body>
</html> 