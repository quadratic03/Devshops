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

// Check if this is an AJAX request to add a user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action']) && $_POST['ajax_action'] === 'add_user') {
    // Get form data
    $username = clean_input($_POST['username'] ?? '');
    $email = clean_input($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $role = clean_input($_POST['role'] ?? '');
    $status = clean_input($_POST['status'] ?? '');
    $is_approved = 'yes'; // Admin created users are pre-approved
    
    $errors = [];
    
    // Validate input
    if (empty($username)) {
        $errors[] = "Username is required";
    }
    
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    
    if (empty($password)) {
        $errors[] = "Password is required";
    } elseif (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters";
    }
    
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    }
    
    if (empty($role)) {
        $errors[] = "Role is required";
    }
    
    if (empty($status)) {
        $errors[] = "Status is required";
    }
    
    // Check if username or email already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $stmt->bind_param("ss", $username, $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $errors[] = "Username or email already exists";
    }
    
    // Prepare response array
    $response = [];
    
    // If no errors, insert user
    if (empty($errors)) {
        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Set approval status based on role
        if ($role === 'seller') {
            // For sellers, respect the status set by admin
            // is_approved is already 'yes'
        } else {
            // For buyers, status is always active
            $status = 'active';
        }
        
        // Check if is_approved column exists
        $check_column_sql = "SHOW COLUMNS FROM users LIKE 'is_approved'";
        $column_result = $conn->query($check_column_sql);
        $is_approved_exists = ($column_result && $column_result->num_rows > 0);
        
        try {
            // Insert user - use different query depending on whether is_approved column exists
            if ($is_approved_exists) {
                $stmt = $conn->prepare("INSERT INTO users (username, email, password, role, status, is_approved) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssss", $username, $email, $hashed_password, $role, $status, $is_approved);
            } else {
                $stmt = $conn->prepare("INSERT INTO users (username, email, password, role, status) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("sssss", $username, $email, $hashed_password, $role, $status);
            }
            
            if ($stmt->execute()) {
                $user_id = $conn->insert_id;
                
                $response = [
                    'success' => true,
                    'message' => "User '{$username}' added successfully!",
                    'user' => [
                        'id' => $user_id,
                        'username' => $username,
                        'email' => $email,
                        'role' => $role,
                        'status' => $status
                    ]
                ];
            } else {
                $response = [
                    'success' => false,
                    'message' => "Error creating user: " . $conn->error
                ];
            }
        } catch (Exception $e) {
            $response = [
                'success' => false,
                'message' => "Database error: " . $e->getMessage()
            ];
        }
    } else {
        $response = [
            'success' => false,
            'message' => implode('<br>', $errors)
        ];
    }
    
    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Process user actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Change user status (activate/deactivate)
    if (isset($_POST['change_status'])) {
        $user_id = (int)$_POST['user_id'];
        $new_status = clean_input($_POST['status']);
        
        // Check if the user is an admin
        $check_admin_sql = "SELECT role FROM users WHERE id = ?";
        $check_admin_stmt = $conn->prepare($check_admin_sql);
        $check_admin_stmt->bind_param("i", $user_id);
        $check_admin_stmt->execute();
        $check_admin_result = $check_admin_stmt->get_result();
        $user_role = $check_admin_result->fetch_assoc()['role'];
        
        // Validate status
        if ($new_status != 'active' && $new_status != 'inactive') {
            $error = "Invalid status value.";
        } elseif ($user_role === 'admin') {
            $error = "Admin users cannot have their status changed for security reasons.";
        } else {
            // Update user status
            $sql = "UPDATE users SET status = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("si", $new_status, $user_id);
            
            if ($stmt->execute()) {
                $success = "User status updated successfully.";
            } else {
                $error = "Error updating user status: " . $conn->error;
            }
        }
    }
    
    // Change user role
    if (isset($_POST['change_role'])) {
        $user_id = (int)$_POST['user_id'];
        $new_role = clean_input($_POST['role']);
        
        // Check if the user is an admin
        $check_admin_sql = "SELECT role FROM users WHERE id = ?";
        $check_admin_stmt = $conn->prepare($check_admin_sql);
        $check_admin_stmt->bind_param("i", $user_id);
        $check_admin_stmt->execute();
        $check_admin_result = $check_admin_stmt->get_result();
        $user_role = $check_admin_result->fetch_assoc()['role'];
        
        // Validate role
        if ($new_role != 'admin' && $new_role != 'seller' && $new_role != 'buyer') {
            $error = "Invalid role value.";
        } elseif ($user_role === 'admin') {
            $error = "Admin users cannot have their role changed for security reasons.";
        } else {
            // Update user role
            $sql = "UPDATE users SET role = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("si", $new_role, $user_id);
            
            if ($stmt->execute()) {
                $success = "User role updated successfully.";
            } else {
                $error = "Error updating user role: " . $conn->error;
            }
        }
    }
    
    // Delete user
    if (isset($_POST['delete_user'])) {
        $user_id = (int)$_POST['user_id'];
        
        // Check if the user is an admin
        $check_admin_sql = "SELECT role FROM users WHERE id = ?";
        $check_admin_stmt = $conn->prepare($check_admin_sql);
        $check_admin_stmt->bind_param("i", $user_id);
        $check_admin_stmt->execute();
        $check_admin_result = $check_admin_stmt->get_result();
        $user_role = $check_admin_result->fetch_assoc()['role'];
        
        if ($user_role === 'admin') {
            $error = "Admin users cannot be deleted for security reasons.";
        } else {
            // Check if user has products
            $check_sql = "SELECT COUNT(*) as count FROM products WHERE seller_id = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("i", $user_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            $product_count = $check_result->fetch_assoc()['count'];
            
            // Check if user has orders
            $check_orders_sql = "SELECT COUNT(*) as count FROM transactions WHERE buyer_id = ?";
            $check_orders_stmt = $conn->prepare($check_orders_sql);
            $check_orders_stmt->bind_param("i", $user_id);
            $check_orders_stmt->execute();
            $check_orders_result = $check_orders_stmt->get_result();
            $order_count = $check_orders_result->fetch_assoc()['count'];
            
            if ($product_count > 0 || $order_count > 0) {
                $error = "Cannot delete user. User has " . 
                        ($product_count > 0 ? "{$product_count} products" : "") . 
                        ($product_count > 0 && $order_count > 0 ? " and " : "") .
                        ($order_count > 0 ? "{$order_count} orders" : "") . ".";
            } else {
                // Delete user
                $sql = "DELETE FROM users WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $user_id);
                
                if ($stmt->execute()) {
                    $success = "User deleted successfully.";
                } else {
                    $error = "Error deleting user: " . $conn->error;
                }
            }
        }
    }

    // Edit user details
    if (isset($_POST['edit_user'])) {
        $user_id = (int)$_POST['user_id'];
        $new_username = clean_input($_POST['username']);
        $new_email = clean_input($_POST['email']);
        $new_password = $_POST['password'] ?? '';
        
        // Validate input
        if (empty($new_username)) {
            $error = "Username is required.";
        } elseif (empty($new_email)) {
            $error = "Email is required.";
        } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
            $error = "Invalid email format.";
        } else {
            // Check if username or email already exists for different users
            $check_sql = "SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("ssi", $new_username, $new_email, $user_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $error = "Username or email already exists for another user.";
            } else {
                // Update user details
                if (!empty($new_password)) {
                    // If password is provided, update it along with username and email
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $sql = "UPDATE users SET username = ?, email = ?, password = ? WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("sssi", $new_username, $new_email, $hashed_password, $user_id);
                } else {
                    // Otherwise, just update username and email
                    $sql = "UPDATE users SET username = ?, email = ? WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("ssi", $new_username, $new_email, $user_id);
                }
                
                if ($stmt->execute()) {
                    $success = "User details updated successfully.";
                } else {
                    $error = "Error updating user details: " . $conn->error;
                }
            }
        }
    }
}

// Get users with product and order counts
$sql = "SELECT u.*, 
        (SELECT COUNT(*) FROM products WHERE seller_id = u.id) as product_count,
        (SELECT COUNT(*) FROM transactions WHERE buyer_id = u.id) as order_count 
        FROM users u 
        ORDER BY u.created_at DESC";
$result = $conn->query($sql);
$users = [];

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - DevMarket Philippines</title>
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
                    <h1 class="h2">Manage Users</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                            <i class="fas fa-plus me-1"></i> Add User
                        </button>
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
                
                <!-- Add User Modal -->
                <div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header bg-primary text-white">
                                <h5 class="modal-title" id="addUserModalLabel">Add New User</h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div id="addUserAlert" class="alert d-none"></div>
                                
                                <form id="addUserForm">
                                    <div class="mb-3">
                                        <label for="username" class="form-label">Username</label>
                                        <input type="text" class="form-control" id="username" name="username" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email</label>
                                        <input type="email" class="form-control" id="email" name="email" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="password" class="form-label">Password</label>
                                        <input type="password" class="form-control" id="password" name="password" required>
                                        <div class="form-text">Password must be at least 6 characters</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="confirm_password" class="form-label">Confirm Password</label>
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="role" class="form-label">Role</label>
                                        <select class="form-select" id="role" name="role" required>
                                            <option value="">Select Role</option>
                                            <option value="buyer">Buyer</option>
                                            <option value="seller">Seller</option>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="status" class="form-label">Status</label>
                                        <select class="form-select" id="status" name="status" required>
                                            <option value="">Select Status</option>
                                            <option value="active">Active</option>
                                            <option value="inactive">Inactive</option>
                                        </select>
                                    </div>
                                </form>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="button" class="btn btn-primary" id="saveUserBtn">Add User</button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Result Modal -->
                <div class="modal fade" id="resultModal" tabindex="-1" aria-labelledby="resultModalLabel" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header bg-success text-white">
                                <h5 class="modal-title" id="resultModalLabel">Success</h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body" id="resultModalBody">
                                <!-- Result message will be displayed here -->
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Users Table -->
                <div class="card mb-4">
                    <div class="card-header bg-dark text-white">
                        <h5 class="mb-0">All Users</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Username</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Status</th>
                                        <th>Products</th>
                                        <th>Orders</th>
                                        <th>Joined</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($users) > 0): ?>
                                        <?php foreach ($users as $user): ?>
                                            <tr>
                                                <td><?php echo $user['id']; ?></td>
                                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                                <td>
                                                    <?php if (isset($user['role']) && $user['role'] == 'admin'): ?>
                                                        <span class="badge bg-danger">Admin</span>
                                                    <?php elseif (isset($user['role']) && $user['role'] == 'seller'): ?>
                                                        <span class="badge bg-primary">Seller</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Buyer</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if (isset($user['status']) && $user['status'] == 'active'): ?>
                                                        <span class="badge bg-success">Active</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning text-dark">Inactive</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info"><?php echo isset($user['product_count']) ? $user['product_count'] : '0'; ?></span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info"><?php echo isset($user['order_count']) ? $user['order_count'] : '0'; ?></span>
                                                </td>
                                                <td><?php echo isset($user['created_at']) ? date('M d, Y', strtotime($user['created_at'])) : 'N/A'; ?></td>
                                                <td>
                                                    <!-- View User Button -->
                                                    <button class="btn btn-sm btn-info view-btn" 
                                                            data-id="<?php echo $user['id']; ?>"
                                                            data-username="<?php echo htmlspecialchars($user['username']); ?>"
                                                            data-email="<?php echo htmlspecialchars($user['email']); ?>"
                                                            data-role="<?php echo isset($user['role']) ? $user['role'] : 'buyer'; ?>"
                                                            data-status="<?php echo isset($user['status']) ? $user['status'] : 'inactive'; ?>"
                                                            data-joined="<?php echo isset($user['created_at']) ? date('M d, Y', strtotime($user['created_at'])) : 'N/A'; ?>"
                                                            data-products="<?php echo isset($user['product_count']) ? $user['product_count'] : '0'; ?>"
                                                            data-orders="<?php echo isset($user['order_count']) ? $user['order_count'] : '0'; ?>">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    
                                                    <!-- Edit User Button -->
                                                    <button class="btn btn-sm btn-success edit-btn"
                                                            data-id="<?php echo $user['id']; ?>"
                                                            data-username="<?php echo htmlspecialchars($user['username']); ?>"
                                                            data-email="<?php echo htmlspecialchars($user['email']); ?>">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    
                                                    <!-- Change Status Button -->
                                                    <?php if (isset($user['role']) && $user['role'] == 'admin'): ?>
                                                        <button class="btn btn-sm btn-warning status-btn" disabled title="Admin status cannot be changed">
                                                            <i class="fas fa-toggle-on"></i>
                                                        </button>
                                                    <?php else: ?>
                                                        <button class="btn btn-sm btn-warning status-btn"
                                                                data-id="<?php echo $user['id']; ?>"
                                                                data-username="<?php echo htmlspecialchars($user['username']); ?>"
                                                                data-status="<?php echo isset($user['status']) ? $user['status'] : 'inactive'; ?>">
                                                            <i class="fas fa-toggle-on"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    
                                                    <!-- Change Role Button -->
                                                    <?php if (isset($user['role']) && $user['role'] == 'admin'): ?>
                                                        <button class="btn btn-sm btn-primary role-btn" disabled title="Admin roles cannot be changed">
                                                            <i class="fas fa-user-tag"></i>
                                                        </button>
                                                    <?php else: ?>
                                                        <button class="btn btn-sm btn-primary role-btn"
                                                                data-id="<?php echo $user['id']; ?>"
                                                                data-username="<?php echo htmlspecialchars($user['username']); ?>"
                                                                data-role="<?php echo isset($user['role']) ? $user['role'] : 'buyer'; ?>">
                                                            <i class="fas fa-user-tag"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    
                                                    <!-- Delete User Button -->
                                                    <button class="btn btn-sm btn-danger delete-btn"
                                                            data-id="<?php echo $user['id']; ?>"
                                                            data-username="<?php echo htmlspecialchars($user['username']); ?>"
                                                            data-product-count="<?php echo isset($user['product_count']) ? $user['product_count'] : '0'; ?>"
                                                            data-order-count="<?php echo isset($user['order_count']) ? $user['order_count'] : '0'; ?>">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="9" class="text-center">No users found.</td>
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

    <!-- View User Modal -->
    <div class="modal fade" id="viewUserModal" tabindex="-1" aria-labelledby="viewUserModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewUserModalLabel">User Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-4 fw-bold">ID:</div>
                        <div class="col-8" id="view_id"></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-4 fw-bold">Username:</div>
                        <div class="col-8" id="view_username"></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-4 fw-bold">Email:</div>
                        <div class="col-8" id="view_email"></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-4 fw-bold">Role:</div>
                        <div class="col-8" id="view_role"></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-4 fw-bold">Status:</div>
                        <div class="col-8" id="view_status"></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-4 fw-bold">Products:</div>
                        <div class="col-8" id="view_products"></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-4 fw-bold">Orders:</div>
                        <div class="col-8" id="view_orders"></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-4 fw-bold">Joined:</div>
                        <div class="col-8" id="view_joined"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Change Status Modal -->
    <div class="modal fade" id="changeStatusModal" tabindex="-1" aria-labelledby="changeStatusModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="changeStatusModalLabel">Change User Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Change status for user: <span id="status_username" class="fw-bold"></span></p>
                    <form action="users.php" method="POST">
                        <input type="hidden" id="status_user_id" name="user_id">
                        <div class="mb-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        <div class="text-end">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" name="change_status" class="btn btn-primary">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Change Role Modal -->
    <div class="modal fade" id="changeRoleModal" tabindex="-1" aria-labelledby="changeRoleModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="changeRoleModalLabel">Change User Role</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Change role for user: <span id="role_username" class="fw-bold"></span></p>
                    <form action="users.php" method="POST">
                        <input type="hidden" id="role_user_id" name="user_id">
                        <div class="mb-3">
                            <label for="role" class="form-label">Role</label>
                            <select class="form-select" id="role" name="role" required>
                                <option value="buyer">Buyer</option>
                                <option value="seller">Seller</option>
                            </select>
                        </div>
                        <div class="text-end">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" name="change_role" class="btn btn-primary">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete User Modal -->
    <div class="modal fade" id="deleteUserModal" tabindex="-1" aria-labelledby="deleteUserModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteUserModalLabel">Delete User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete user: <span id="delete_username" class="fw-bold"></span>?</p>
                    <div id="delete_warning" class="alert alert-warning d-none">
                        This user has associated data and cannot be deleted:
                        <div id="warning_details"></div>
                    </div>
                    <form action="users.php" method="POST">
                        <input type="hidden" id="delete_user_id" name="user_id">
                        <div class="text-end">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" id="confirmDeleteBtn" name="delete_user" class="btn btn-danger">Delete User</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editUserModalLabel">Edit User Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="editUserAlert" class="alert d-none"></div>
                    <form action="users.php" method="POST" id="editUserForm">
                        <input type="hidden" id="edit_user_id" name="user_id">
                        
                        <div class="mb-3">
                            <label for="edit_username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="edit_username" name="username" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="edit_email" name="email" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_password" class="form-label">New Password (leave blank to keep current)</label>
                            <input type="password" class="form-control" id="edit_password" name="password" minlength="6">
                            <div class="form-text">Leave blank to keep the current password.</div>
                        </div>
                        
                        <div class="text-end">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" name="edit_user" class="btn btn-success">Save Changes</button>
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
            // View User Modal
            const viewButtons = document.querySelectorAll('.view-btn');
            const viewModal = new bootstrap.Modal(document.getElementById('viewUserModal'));
            
            viewButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const id = this.getAttribute('data-id') || '';
                    const username = this.getAttribute('data-username') || '';
                    const email = this.getAttribute('data-email') || '';
                    const role = this.getAttribute('data-role') || 'buyer';
                    const status = this.getAttribute('data-status') || 'inactive';
                    const joined = this.getAttribute('data-joined') || '';
                    const products = this.getAttribute('data-products') || '0';
                    const orders = this.getAttribute('data-orders') || '0';
                    
                    document.getElementById('view_id').textContent = id;
                    document.getElementById('view_username').textContent = username;
                    document.getElementById('view_email').textContent = email;
                    
                    let roleDisplay = '';
                    if (role === 'admin') {
                        roleDisplay = '<span class="badge bg-danger">Admin</span>';
                    } else if (role === 'seller') {
                        roleDisplay = '<span class="badge bg-primary">Seller</span>';
                    } else {
                        roleDisplay = '<span class="badge bg-secondary">Buyer</span>';
                    }
                    document.getElementById('view_role').innerHTML = roleDisplay;
                    
                    let statusDisplay = '';
                    if (status === 'active') {
                        statusDisplay = '<span class="badge bg-success">Active</span>';
                    } else {
                        statusDisplay = '<span class="badge bg-warning text-dark">Inactive</span>';
                    }
                    document.getElementById('view_status').innerHTML = statusDisplay;
                    
                    document.getElementById('view_products').textContent = products;
                    document.getElementById('view_orders').textContent = orders;
                    document.getElementById('view_joined').textContent = joined;
                    
                    viewModal.show();
                });
            });
            
            // Edit User Modal
            const editButtons = document.querySelectorAll('.edit-btn');
            const editModal = new bootstrap.Modal(document.getElementById('editUserModal'));
            
            editButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const id = this.getAttribute('data-id') || '';
                    const username = this.getAttribute('data-username') || '';
                    const email = this.getAttribute('data-email') || '';
                    
                    document.getElementById('edit_user_id').value = id;
                    document.getElementById('edit_username').value = username;
                    document.getElementById('edit_email').value = email;
                    document.getElementById('edit_password').value = '';
                    
                    // Reset alert if shown previously
                    const editUserAlert = document.getElementById('editUserAlert');
                    editUserAlert.classList.add('d-none');
                    
                    editModal.show();
                });
            });
            
            // Change Status Modal
            const statusButtons = document.querySelectorAll('.status-btn');
            const statusModal = new bootstrap.Modal(document.getElementById('changeStatusModal'));
            
            statusButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const id = this.getAttribute('data-id') || '';
                    const username = this.getAttribute('data-username') || '';
                    const status = this.getAttribute('data-status') || 'inactive';
                    
                    document.getElementById('status_user_id').value = id;
                    document.getElementById('status_username').textContent = username;
                    document.getElementById('status').value = status;
                    
                    statusModal.show();
                });
            });
            
            // Change Role Modal
            const roleButtons = document.querySelectorAll('.role-btn');
            const roleModal = new bootstrap.Modal(document.getElementById('changeRoleModal'));
            
            roleButtons.forEach(button => {
                button.addEventListener('click', function() {
                    // Skip if the button is disabled (for admin users)
                    if (this.hasAttribute('disabled')) {
                        return;
                    }
                    
                    const id = this.getAttribute('data-id') || '';
                    const username = this.getAttribute('data-username') || '';
                    const role = this.getAttribute('data-role') || 'buyer';
                    
                    document.getElementById('role_user_id').value = id;
                    document.getElementById('role_username').textContent = username;
                    document.getElementById('role').value = role;
                    
                    roleModal.show();
                });
            });
            
            // Delete User Modal
            const deleteButtons = document.querySelectorAll('.delete-btn');
            const deleteModal = new bootstrap.Modal(document.getElementById('deleteUserModal'));
            
            deleteButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const id = this.getAttribute('data-id') || '';
                    const username = this.getAttribute('data-username') || '';
                    const productCount = parseInt(this.getAttribute('data-product-count') || '0');
                    const orderCount = parseInt(this.getAttribute('data-order-count') || '0');
                    const role = this.closest('tr').querySelector('.badge').textContent.trim().toLowerCase();
                    
                    document.getElementById('delete_user_id').value = id;
                    document.getElementById('delete_username').textContent = username;
                    
                    if (role === 'admin') {
                        document.getElementById('warning_details').innerHTML = '<div>- Admin users cannot be deleted for security reasons</div>';
                        document.getElementById('delete_warning').classList.remove('d-none');
                        document.getElementById('confirmDeleteBtn').disabled = true;
                    } else if (productCount > 0 || orderCount > 0) {
                        let warningText = '';
                        if (productCount > 0) {
                            warningText += `<div>- ${productCount} products</div>`;
                        }
                        if (orderCount > 0) {
                            warningText += `<div>- ${orderCount} orders</div>`;
                        }
                        
                        document.getElementById('warning_details').innerHTML = warningText;
                        document.getElementById('delete_warning').classList.remove('d-none');
                        document.getElementById('confirmDeleteBtn').disabled = true;
                    } else {
                        document.getElementById('delete_warning').classList.add('d-none');
                        document.getElementById('confirmDeleteBtn').disabled = false;
                    }
                    
                    deleteModal.show();
                });
            });
            
            // Add User functionality
            const saveUserBtn = document.getElementById('saveUserBtn');
            const addUserForm = document.getElementById('addUserForm');
            const addUserAlert = document.getElementById('addUserAlert');
            const addUserModal = new bootstrap.Modal(document.getElementById('addUserModal'));
            const resultModal = new bootstrap.Modal(document.getElementById('resultModal'));
            
            if (saveUserBtn && addUserForm) {
                saveUserBtn.addEventListener('click', function() {
                    // Validate form
                    const formData = new FormData(addUserForm);
                    formData.append('ajax_action', 'add_user');
                    
                    // Reset alert
                    addUserAlert.classList.add('d-none');
                    
                    // Check if passwords match
                    const password = document.getElementById('password').value;
                    const confirmPassword = document.getElementById('confirm_password').value;
                    
                    if (password !== confirmPassword) {
                        addUserAlert.classList.remove('d-none');
                        addUserAlert.classList.add('alert-danger');
                        addUserAlert.textContent = 'Passwords do not match';
                        return;
                    }
                    
                    // Submit form via AJAX
                    fetch('users.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Show success message
                            document.getElementById('resultModalLabel').textContent = 'Success';
                            document.getElementById('resultModalLabel').parentElement.classList.remove('bg-danger');
                            document.getElementById('resultModalLabel').parentElement.classList.add('bg-success');
                            document.getElementById('resultModalBody').textContent = data.message;
                            
                            // Close add user modal and show result modal
                            addUserModal.hide();
                            resultModal.show();
                            
                            // Reset form
                            addUserForm.reset();
                            
                            // Reload page after a short delay to show the new user
                            setTimeout(() => {
                                window.location.reload();
                            }, 1500);
                        } else {
                            // Show error message within the form
                            addUserAlert.classList.remove('d-none');
                            addUserAlert.classList.add('alert-danger');
                            addUserAlert.innerHTML = data.message;
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        addUserAlert.classList.remove('d-none');
                        addUserAlert.classList.add('alert-danger');
                        addUserAlert.textContent = 'An error occurred. Please try again.';
                    });
                });
            }
        });
    </script>
</body>
</html> 