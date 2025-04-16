<?php
// Only start the session if one doesn't already exist
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../includes/functions.php';

// Check if user is logged in and is a seller
if (!is_logged_in() || !is_seller()) {
    header('Location: ../login.php');
    exit;
}

$seller_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Get user information
$query = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $seller_id);
$stmt->execute();
$user_result = $stmt->get_result();
$user = $user_result->fetch_assoc();

// Update profile
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    // Update general information
    if ($action === 'update_profile') {
        $username = clean_input($_POST['username'] ?? '');
        $email = clean_input($_POST['email'] ?? '');
        $bio = clean_input($_POST['bio'] ?? '');
        
        // Validate input
        if (empty($username)) {
            $error = "Username is required";
        } elseif (empty($email)) {
            $error = "Email is required";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Invalid email format";
        } else {
            // Check if username or email already exists (excluding current user)
            $check_query = "SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?";
            $check_stmt = $conn->prepare($check_query);
            $check_stmt->bind_param("ssi", $username, $email, $seller_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $error = "Username or email already exists";
            } else {
                // Update user information
                $update_query = "UPDATE users SET username = ?, email = ?, bio = ? WHERE id = ?";
                $update_stmt = $conn->prepare($update_query);
                $update_stmt->bind_param("sssi", $username, $email, $bio, $seller_id);
                
                if ($update_stmt->execute()) {
                    $success = "Profile updated successfully";
                    
                    // Refresh user data
                    $stmt->execute();
                    $user_result = $stmt->get_result();
                    $user = $user_result->fetch_assoc();
                } else {
                    $error = "Error updating profile: " . $conn->error;
                }
            }
        }
    }
    
    // Change password
    else if ($action === 'change_password') {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        // Validate input
        if (empty($current_password)) {
            $error = "Current password is required";
        } elseif (empty($new_password)) {
            $error = "New password is required";
        } elseif (strlen($new_password) < 6) {
            $error = "New password must be at least 6 characters";
        } elseif ($new_password !== $confirm_password) {
            $error = "New passwords do not match";
        } else {
            // Verify current password
            if (password_verify($current_password, $user['password'])) {
                // Update password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $update_query = "UPDATE users SET password = ? WHERE id = ?";
                $update_stmt = $conn->prepare($update_query);
                $update_stmt->bind_param("si", $hashed_password, $seller_id);
                
                if ($update_stmt->execute()) {
                    $success = "Password changed successfully";
                } else {
                    $error = "Error changing password: " . $conn->error;
                }
            } else {
                $error = "Current password is incorrect";
            }
        }
    }
    
    // Add payment method
    else if ($action === 'add_payment_method') {
        $method_type = clean_input($_POST['method_type'] ?? '');
        $account_name = clean_input($_POST['account_name'] ?? '');
        $account_number = clean_input($_POST['account_number'] ?? '');
        $additional_info = clean_input($_POST['additional_info'] ?? '');
        $is_default = isset($_POST['is_default']) ? 1 : 0;
        
        // Validate input
        if (empty($method_type)) {
            $error = "Payment method type is required";
        } elseif (empty($account_name)) {
            $error = "Account name is required";
        } elseif (empty($account_number)) {
            $error = "Account number is required";
        } else {
            try {
                // Begin transaction
                $conn->begin_transaction();
                
                // If setting as default, clear default flag from all other methods
                if ($is_default) {
                    $update_defaults = "UPDATE seller_payment_methods SET is_default = 0 WHERE seller_id = ?";
                    $update_stmt = $conn->prepare($update_defaults);
                    $update_stmt->bind_param("i", $seller_id);
                    $update_stmt->execute();
                }
                
                // Insert new payment method
                $insert_query = "INSERT INTO seller_payment_methods 
                                 (seller_id, method_type, account_name, account_number, is_default, additional_info) 
                                 VALUES (?, ?, ?, ?, ?, ?)";
                $insert_stmt = $conn->prepare($insert_query);
                $insert_stmt->bind_param("isssss", $seller_id, $method_type, $account_name, $account_number, $is_default, $additional_info);
                
                if ($insert_stmt->execute()) {
                    $conn->commit();
                    $success = "Payment method added successfully";
                } else {
                    throw new Exception("Error adding payment method: " . $conn->error);
                }
            } catch (Exception $e) {
                $conn->rollback();
                $error = $e->getMessage();
            }
        }
    }
    
    // Delete payment method
    else if ($action === 'delete_payment_method' && isset($_POST['method_id'])) {
        $method_id = (int)$_POST['method_id'];
        
        // Verify the payment method belongs to the seller
        $check_query = "SELECT * FROM seller_payment_methods WHERE id = ? AND seller_id = ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param("ii", $method_id, $seller_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows === 0) {
            $error = "Invalid payment method";
        } else {
            $method = $check_result->fetch_assoc();
            $is_default = $method['is_default'];
            
            // Delete the payment method
            $delete_query = "DELETE FROM seller_payment_methods WHERE id = ?";
            $delete_stmt = $conn->prepare($delete_query);
            $delete_stmt->bind_param("i", $method_id);
            
            if ($delete_stmt->execute()) {
                // If it was the default method, set another one as default if available
                if ($is_default) {
                    $update_query = "UPDATE seller_payment_methods SET is_default = 1 WHERE seller_id = ? LIMIT 1";
                    $update_stmt = $conn->prepare($update_query);
                    $update_stmt->bind_param("i", $seller_id);
                    $update_stmt->execute();
                }
                
                $success = "Payment method deleted successfully";
            } else {
                $error = "Error deleting payment method: " . $conn->error;
            }
        }
    }
    
    // Set default payment method
    else if ($action === 'set_default_payment_method' && isset($_POST['method_id'])) {
        $method_id = (int)$_POST['method_id'];
        
        // Verify the payment method belongs to the seller
        $check_query = "SELECT * FROM seller_payment_methods WHERE id = ? AND seller_id = ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param("ii", $method_id, $seller_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows === 0) {
            $error = "Invalid payment method";
        } else {
            try {
                // Begin transaction
                $conn->begin_transaction();
                
                // Clear default flag from all methods
                $clear_query = "UPDATE seller_payment_methods SET is_default = 0 WHERE seller_id = ?";
                $clear_stmt = $conn->prepare($clear_query);
                $clear_stmt->bind_param("i", $seller_id);
                $clear_stmt->execute();
                
                // Set new default
                $update_query = "UPDATE seller_payment_methods SET is_default = 1 WHERE id = ?";
                $update_stmt = $conn->prepare($update_query);
                $update_stmt->bind_param("i", $method_id);
                
                if ($update_stmt->execute()) {
                    $conn->commit();
                    $success = "Default payment method updated";
                } else {
                    throw new Exception("Error updating default payment method: " . $conn->error);
                }
            } catch (Exception $e) {
                $conn->rollback();
                $error = $e->getMessage();
            }
        }
    }
}

// Get seller payment methods
$payment_methods_query = "SELECT * FROM seller_payment_methods WHERE seller_id = ? ORDER BY is_default DESC, created_at DESC";
$payment_stmt = $conn->prepare($payment_methods_query);
$payment_stmt->bind_param("i", $seller_id);
$payment_stmt->execute();
$payment_methods_result = $payment_stmt->get_result();
$payment_methods = [];

while ($method = $payment_methods_result->fetch_assoc()) {
    $payment_methods[] = $method;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Seller Dashboard</title>
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
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-user me-2"></i> My Profile</h5>
                    </div>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger m-3"><?php echo $error; ?></div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success m-3"><?php echo $success; ?></div>
                    <?php endif; ?>
                    
                    <div class="card-body">
                        <ul class="nav nav-tabs" id="profileTabs" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link active" id="general-tab" data-bs-toggle="tab" href="#general" role="tab" aria-controls="general" aria-selected="true">General Information</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" id="password-tab" data-bs-toggle="tab" href="#password" role="tab" aria-controls="password" aria-selected="false">Change Password</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" id="payment-tab" data-bs-toggle="tab" href="#payment" role="tab" aria-controls="payment" aria-selected="false">Payment Methods</a>
                            </li>
                        </ul>
                        
                        <div class="tab-content p-3" id="profileTabsContent">
                            <!-- General Information Tab -->
                            <div class="tab-pane fade show active" id="general" role="tabpanel" aria-labelledby="general-tab">
                                <form method="post" action="profile.php">
                                    <input type="hidden" name="action" value="update_profile">
                                    
                                    <div class="mb-3">
                                        <label for="username" class="form-label">Username</label>
                                        <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email</label>
                                        <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="bio" class="form-label">Bio/Description</label>
                                        <textarea class="form-control" id="bio" name="bio" rows="4"><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                                        <div class="form-text">Tell buyers about yourself and your products/services.</div>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary">Update Profile</button>
                                </form>
                            </div>
                            
                            <!-- Change Password Tab -->
                            <div class="tab-pane fade" id="password" role="tabpanel" aria-labelledby="password-tab">
                                <form method="post" action="profile.php">
                                    <input type="hidden" name="action" value="change_password">
                                    
                                    <div class="mb-3">
                                        <label for="current_password" class="form-label">Current Password</label>
                                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="new_password" class="form-label">New Password</label>
                                        <input type="password" class="form-control" id="new_password" name="new_password" required>
                                        <div class="form-text">Password must be at least 6 characters long.</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="confirm_password" class="form-label">Confirm New Password</label>
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary">Change Password</button>
                                </form>
                            </div>
                            
                            <!-- Payment Methods Tab -->
                            <div class="tab-pane fade" id="payment" role="tabpanel" aria-labelledby="payment-tab">
                                <div class="mb-4">
                                    <h5>Your Payment Methods</h5>
                                    <?php if (empty($payment_methods)): ?>
                                        <div class="alert alert-info">You haven't added any payment methods yet.</div>
                                    <?php else: ?>
                                        <div class="table-responsive">
                                            <table class="table table-striped">
                                                <thead>
                                                    <tr>
                                                        <th>Type</th>
                                                        <th>Account Name</th>
                                                        <th>Account Number</th>
                                                        <th>Additional Info</th>
                                                        <th>Status</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($payment_methods as $method): ?>
                                                        <tr>
                                                            <td>
                                                                <?php if ($method['method_type'] === 'gcash'): ?>
                                                                    <span class="badge bg-primary">GCash</span>
                                                                <?php elseif ($method['method_type'] === 'paymaya'): ?>
                                                                    <span class="badge bg-success">PayMaya</span>
                                                                <?php elseif ($method['method_type'] === 'bank_transfer'): ?>
                                                                    <span class="badge bg-info">Bank Transfer</span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td><?php echo htmlspecialchars($method['account_name']); ?></td>
                                                            <td><?php echo htmlspecialchars($method['account_number']); ?></td>
                                                            <td><?php echo htmlspecialchars($method['additional_info'] ?? 'N/A'); ?></td>
                                                            <td>
                                                                <?php if ($method['is_default']): ?>
                                                                    <span class="badge bg-success">Default</span>
                                                                <?php else: ?>
                                                                    <form method="post" action="profile.php" class="d-inline">
                                                                        <input type="hidden" name="action" value="set_default_payment_method">
                                                                        <input type="hidden" name="method_id" value="<?php echo $method['id']; ?>">
                                                                        <button type="submit" class="btn btn-sm btn-outline-secondary">Set as Default</button>
                                                                    </form>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td>
                                                                <form method="post" action="profile.php" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this payment method?');">
                                                                    <input type="hidden" name="action" value="delete_payment_method">
                                                                    <input type="hidden" name="method_id" value="<?php echo $method['id']; ?>">
                                                                    <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                                                                </form>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="card">
                                    <div class="card-header bg-light">
                                        <h5 class="mb-0">Add New Payment Method</h5>
                                    </div>
                                    <div class="card-body">
                                        <form method="post" action="profile.php">
                                            <input type="hidden" name="action" value="add_payment_method">
                                            
                                            <div class="mb-3">
                                                <label for="method_type" class="form-label">Payment Method Type</label>
                                                <select class="form-select" id="method_type" name="method_type" required>
                                                    <option value="">Select Payment Method</option>
                                                    <option value="gcash">GCash</option>
                                                    <option value="paymaya">PayMaya</option>
                                                    <option value="bank_transfer">Bank Transfer</option>
                                                </select>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="account_name" class="form-label">Account Name</label>
                                                <input type="text" class="form-control" id="account_name" name="account_name" required>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="account_number" class="form-label">Account Number</label>
                                                <input type="text" class="form-control" id="account_number" name="account_number" required>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="additional_info" class="form-label">Additional Information</label>
                                                <textarea class="form-control" id="additional_info" name="additional_info" rows="2"></textarea>
                                                <div class="form-text">For bank transfers, include bank name, branch, etc.</div>
                                            </div>
                                            
                                            <div class="mb-3 form-check">
                                                <input type="checkbox" class="form-check-input" id="is_default" name="is_default">
                                                <label class="form-check-label" for="is_default">Set as default payment method</label>
                                            </div>
                                            
                                            <button type="submit" class="btn btn-primary">Add Payment Method</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include_once __DIR__ . '/../includes/footer.php'; ?>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 