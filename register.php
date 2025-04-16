<?php
require_once 'includes/header.php';

// Check if already logged in
if (is_logged_in()) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = '';

// Process registration form
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get form data
    $username = clean_input($_POST['username']);
    $email = clean_input($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $phone = clean_input($_POST['phone']);
    $role = isset($_POST['user_type']) && $_POST['user_type'] == 'seller' ? 'seller' : 'buyer';
    
    // Validate input
    if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = 'All required fields must be filled out.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } else {
        // Check if username already exists
        $sql = "SELECT id FROM users WHERE username = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error = 'Username already exists. Please choose another.';
        } else {
            // Check if email already exists
            $sql = "SELECT id FROM users WHERE email = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $error = 'Email already exists. Please use another email.';
            } else {
                // Hash password
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                
                // Set status and approval
                $status = ($role == 'seller') ? 'inactive' : 'active';
                $is_approved = ($role == 'seller') ? 'pending' : 'yes';
                
                // Insert user
                $sql = "INSERT INTO users (username, email, password, phone_number, role, status, is_approved) VALUES (?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sssssss", $username, $email, $password_hash, $phone, $role, $status, $is_approved);
                
                if ($stmt->execute()) {
                    if ($role == 'seller') {
                        $success = 'Registration successful! Your seller account is pending approval from admin. You\'ll be notified via email when approved.';
                    } else {
                        $success = 'Registration successful! You can now login.';
                    }
                } else {
                    $error = 'Error registering user: ' . $stmt->error;
                }
            }
        }
    }
}
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="auth-form my-5">
            <h2 class="text-center mb-4">Create an Account</h2>
            
            <?php if ($error): ?>
            <div class="alert alert-danger">
                <?php echo $error; ?>
            </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
            <div class="alert alert-success">
                <?php echo $success; ?>
                <p class="mt-2 mb-0">
                    <a href="login.php" class="btn btn-primary btn-sm">Go to Login</a>
                </p>
            </div>
            <?php else: ?>
            
            <form method="POST" action="register.php" class="needs-validation" novalidate>
                <div class="mb-3">
                    <label for="username" class="form-label">Username <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="username" name="username" required>
                    <div class="invalid-feedback">
                        Please choose a username.
                    </div>
                </div>
                <div class="mb-3">
                    <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                    <input type="email" class="form-control" id="email" name="email" required>
                    <div class="invalid-feedback">
                        Please enter a valid email.
                    </div>
                </div>
                <div class="mb-3">
                    <label for="phone" class="form-label">Phone Number</label>
                    <input type="text" class="form-control" id="phone" name="phone">
                    <div class="form-text">Phone number is required for sellers to allow buyers to contact you.</div>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                    <input type="password" class="form-control" id="password" name="password" required minlength="6">
                    <div class="invalid-feedback">
                        Password must be at least 6 characters long.
                    </div>
                </div>
                <div class="mb-3">
                    <label for="confirm_password" class="form-label">Confirm Password <span class="text-danger">*</span></label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                    <div class="invalid-feedback">
                        Passwords do not match.
                    </div>
                </div>
                <div class="mb-4">
                    <label class="form-label d-block">Account Type <span class="text-danger">*</span></label>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="user_type" id="type_buyer" value="buyer" checked>
                        <label class="form-check-label" for="type_buyer">Buyer</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="user_type" id="type_seller" value="seller">
                        <label class="form-check-label" for="type_seller">Seller</label>
                    </div>
                </div>
                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input" id="terms" required>
                    <label class="form-check-label" for="terms">I agree to the <a href="#" data-bs-toggle="modal" data-bs-target="#termsModal">Terms and Conditions</a> <span class="text-danger">*</span></label>
                    <div class="invalid-feedback">
                        You must agree to the terms and conditions.
                    </div>
                </div>
                <button type="submit" class="btn btn-primary w-100">Register</button>
            </form>
            
            <?php endif; ?>
            
            <div class="text-center mt-4">
                <p>Already have an account? <a href="login.php">Login here</a></p>
            </div>
        </div>
    </div>
</div>

<!-- Terms and Conditions Modal -->
<div class="modal fade" id="termsModal" tabindex="-1" aria-labelledby="termsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="termsModalLabel">Terms and Conditions</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" style="max-height: 400px; overflow-y: auto; padding: 20px; border: 1px solid #eee; border-radius: 5px; background-color: #f9f9f9;">
                <h4>1. Introduction</h4>
                <p>Welcome to DevMarket Philippines. These terms and conditions govern your use of our website and services. By using our platform, you agree to these terms in full.</p>
                
                <h4>2. Definitions</h4>
                <p>"We", "us", and "our" refer to DevMarket Philippines. "You" and "your" refer to users of our services. "Platform" refers to our website and services. "Content" refers to any material uploaded to or made available through our platform.</p>
                
                <h4>3. Account Registration</h4>
                <p>To use certain features of our platform, you must register for an account. You agree to provide accurate and complete information when creating your account and to update this information to keep it accurate and complete.</p>
                
                <h4>4. User Conduct</h4>
                <p>You agree not to use our platform for any illegal or unauthorized purpose. You must not violate any laws in your jurisdiction, including copyright or trademark laws.</p>
                
                <h4>5. Products and Services</h4>
                <p>Sellers are responsible for the accuracy of their listings. Buyers are responsible for verifying product details before making a purchase. We do not guarantee the quality, safety, or legality of items listed.</p>
                
                <h4>6. Payments and Fees</h4>
                <p>We may charge fees for certain services. All fees are non-refundable unless otherwise stated. Payment terms are subject to change.</p>
                
                <h4>7. Intellectual Property</h4>
                <p>You retain all rights to your content. By posting content, you grant us a non-exclusive, worldwide, royalty-free license to use, reproduce, modify, and display the content in connection with our services.</p>
                
                <h4>8. Termination</h4>
                <p>We reserve the right to terminate or suspend your account at our discretion, without notice, for conduct that we believe violates these terms or is harmful to other users, us, or third parties, or for any other reason.</p>
                
                <h4>9. Limitation of Liability</h4>
                <p>To the maximum extent permitted by law, we shall not be liable for any indirect, incidental, special, consequential, or punitive damages resulting from your use or inability to use our services.</p>
                
                <h4>10. Changes to Terms</h4>
                <p>We reserve the right to modify these terms at any time. We will provide notice of significant changes. Your continued use of our platform after such modifications constitutes your acceptance of the updated terms.</p>
                
                <h4>11. Contact Information</h4>
                <p>If you have any questions about these terms, please contact us at support@devmarket.ph.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal" onclick="document.getElementById('terms').checked = true;">I Agree</button>
            </div>
        </div>
    </div>
</div>

<script>
// Validate password matching
document.addEventListener('DOMContentLoaded', function() {
    const password = document.getElementById('password');
    const confirmPassword = document.getElementById('confirm_password');
    
    function validatePassword() {
        if (password.value !== confirmPassword.value) {
            confirmPassword.setCustomValidity("Passwords don't match");
        } else {
            confirmPassword.setCustomValidity('');
        }
    }
    
    if (password && confirmPassword) {
        password.addEventListener('change', validatePassword);
        confirmPassword.addEventListener('keyup', validatePassword);
    }
    
    // Enable Bootstrap form validation
    const forms = document.querySelectorAll('.needs-validation');
    Array.from(forms).forEach(form => {
        form.addEventListener('submit', event => {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });
});
</script>

<?php require_once 'includes/footer.php'; ?> 