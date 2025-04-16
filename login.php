<?php
require_once 'includes/header.php';

// Check if already logged in
if (is_logged_in()) {
    header('Location: index.php');
    exit;
}

$error = '';

// Process login form
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = clean_input($_POST['username']);
    $password = $_POST['password'];
    
    // Validate input
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        // Check user credentials
        $sql = "SELECT id, username, password, role, status, is_approved FROM users WHERE username = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 1) {
            $user = $result->fetch_assoc();
            
            // Check if user is active
            if ($user['status'] != 'active') {
                $error = 'Your account is not active. Please contact support.';
            } 
            // Check if seller is approved
            else if ($user['role'] == 'seller' && $user['is_approved'] != 'yes') {
                if ($user['is_approved'] == 'pending') {
                    $error = 'Your seller account is pending approval from admin. Please wait for approval notification.';
                } else {
                    $error = 'Your seller account has not been approved. Please contact support.';
                }
            }
            // Verify password
            else if (password_verify($password, $user['password'])) {
                // Set session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['user_type'] = $user['role'];
                
                // Redirect based on user role
                if ($user['role'] == 'admin') {
                    header('Location: admin/dashboard.php');
                } else if ($user['role'] == 'seller') {
                    header('Location: seller/dashboard.php');
                } else {
                    header('Location: index.php');
                }
                exit;
            } else {
                // Hard-coded admin check for fallback (remove in production)
                if ($username === 'admin' && $password === 'admin123') {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['user_type'] = 'admin';
                    header('Location: admin/dashboard.php');
                    exit;
                }
                
                // Hard-coded seller check for fallback/testing (remove in production)
                if ($username === 'seller' && $password === 'seller123') {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['user_type'] = 'seller';
                    header('Location: seller/dashboard.php');
                    exit;
                }
                
                $error = 'Invalid password.';
            }
        } else {
            $error = 'User not found.';
        }
    }
}
?>

<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="auth-form my-5">
            <h2 class="text-center mb-4">Login to DevMarket</h2>
            
            <?php if ($error): ?>
            <div class="alert alert-danger">
                <?php echo $error; ?>
            </div>
            <?php endif; ?>
            
            <form method="POST" action="login.php" class="needs-validation" novalidate>
                <div class="mb-3">
                    <label for="username" class="form-label">Username</label>
                    <input type="text" class="form-control" id="username" name="username" required>
                    <div class="invalid-feedback">
                        Please enter your username.
                    </div>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                    <div class="invalid-feedback">
                        Please enter your password.
                    </div>
                </div>
                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input" id="remember">
                    <label class="form-check-label" for="remember">Remember me</label>
                </div>
                <button type="submit" class="btn btn-primary w-100">Login</button>
            </form>
            
            <div class="text-center mt-4">
                <p>Don't have an account? <a href="register.php">Register here</a></p>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?> 