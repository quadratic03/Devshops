<?php
// Only start the session if one doesn't already exist
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'includes/functions.php';
require_once 'config/database.php';

// Check if user is logged in
if (!is_logged_in()) {
    header("Location: login.php");
    exit();
}

$error = '';
$success = '';

// Get user payment methods
$query = "SELECT * FROM buyer_payment_methods WHERE buyer_id = ? ORDER BY is_default DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$payment_methods = [];

while ($row = $result->fetch_assoc()) {
    $payment_methods[] = $row;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_payment_method'])) {
        $method_type = clean_input($_POST['method_type']);
        $account_name = clean_input($_POST['account_name']);
        $account_number = clean_input($_POST['account_number']);
        $is_default = isset($_POST['is_default']) ? 1 : 0;
        
        // Validate input
        if (empty($method_type)) {
            $error = "Payment method type is required";
        } elseif (empty($account_name)) {
            $error = "Account name is required";
        } elseif (empty($account_number)) {
            $error = "Account number is required";
        } else {
            // If gcash, check if valid phone number format
            if ($method_type === 'gcash' && !preg_match('/^09\d{9}$/', $account_number)) {
                $error = "GCash number must be a valid Philippine mobile number (e.g., 09123456789)";
            } else {
                try {
                    // Begin transaction
                    $conn->begin_transaction();
                    
                    // If setting as default, clear default flag from all other methods
                    if ($is_default) {
                        $update_defaults = "UPDATE buyer_payment_methods SET is_default = 0 WHERE buyer_id = ?";
                        $update_stmt = $conn->prepare($update_defaults);
                        $update_stmt->bind_param("i", $_SESSION['user_id']);
                        $update_stmt->execute();
                    }
                    
                    // Insert new payment method
                    $insert_query = "INSERT INTO buyer_payment_methods 
                                    (buyer_id, method_type, account_name, account_number, is_default) 
                                    VALUES (?, ?, ?, ?, ?)";
                    $insert_stmt = $conn->prepare($insert_query);
                    $insert_stmt->bind_param("isssi", $_SESSION['user_id'], $method_type, $account_name, $account_number, $is_default);
                    
                    if ($insert_stmt->execute()) {
                        $conn->commit();
                        $success = "Payment method added successfully";
                        
                        // Refresh the page to show the updated payment methods
                        header("Location: payment_setup.php");
                        exit();
                    } else {
                        throw new Exception($conn->error);
                    }
                } catch (Exception $e) {
                    $conn->rollback();
                    $error = "Error adding payment method: " . $e->getMessage();
                }
            }
        }
    } elseif (isset($_POST['delete_method'])) {
        $method_id = (int)$_POST['method_id'];
        
        $delete_query = "DELETE FROM buyer_payment_methods WHERE id = ? AND buyer_id = ?";
        $delete_stmt = $conn->prepare($delete_query);
        $delete_stmt->bind_param("ii", $method_id, $_SESSION['user_id']);
        
        if ($delete_stmt->execute() && $delete_stmt->affected_rows > 0) {
            $success = "Payment method deleted successfully";
            
            // Refresh the page to show the updated payment methods
            header("Location: payment_setup.php");
            exit();
        } else {
            $error = "Error deleting payment method";
        }
    } elseif (isset($_POST['set_default'])) {
        $method_id = (int)$_POST['method_id'];
        
        // Begin transaction
        $conn->begin_transaction();
        
        try {
            // Clear default flag from all methods
            $clear_default = "UPDATE buyer_payment_methods SET is_default = 0 WHERE buyer_id = ?";
            $clear_stmt = $conn->prepare($clear_default);
            $clear_stmt->bind_param("i", $_SESSION['user_id']);
            $clear_stmt->execute();
            
            // Set new default
            $set_default = "UPDATE buyer_payment_methods SET is_default = 1 WHERE id = ? AND buyer_id = ?";
            $default_stmt = $conn->prepare($set_default);
            $default_stmt->bind_param("ii", $method_id, $_SESSION['user_id']);
            $default_stmt->execute();
            
            if ($default_stmt->affected_rows > 0) {
                $conn->commit();
                $success = "Default payment method updated successfully";
                
                // Refresh the page to show the updated payment methods
                header("Location: payment_setup.php");
                exit();
            } else {
                throw new Exception("Payment method not found");
            }
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Error updating default payment method: " . $e->getMessage();
        }
    }
}

include 'includes/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-10 offset-md-1">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">Payment Methods</h4>
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
                    
                    <div class="mb-4">
                        <p>Set up your payment accounts to complete purchases faster. We'll verify your payment account when you make a purchase.</p>
                    </div>
                    
                    <!-- Payment Methods List -->
                    <?php if (empty($payment_methods)): ?>
                    <div class="alert alert-info">
                        <p>You don't have any payment methods set up yet. Add one below.</p>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive mb-4">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Type</th>
                                    <th>Account Name</th>
                                    <th>Account Number</th>
                                    <th>Default</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($payment_methods as $method): ?>
                                <tr>
                                    <td>
                                        <?php if ($method['method_type'] === 'gcash'): ?>
                                            <span class="badge bg-primary"><i class="fas fa-wallet me-1"></i> GCash</span>
                                        <?php elseif ($method['method_type'] === 'paymaya'): ?>
                                            <span class="badge bg-success"><i class="fas fa-wallet me-1"></i> PayMaya</span>
                                        <?php elseif ($method['method_type'] === 'bank_transfer'): ?>
                                            <span class="badge bg-info"><i class="fas fa-university me-1"></i> Bank Transfer</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($method['account_name']); ?></td>
                                    <td>
                                        <?php
                                        $number = $method['account_number'];
                                        if (strlen($number) > 4) {
                                            echo substr($number, 0, 2) . '******' . substr($number, -2);
                                        } else {
                                            echo $number;
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php if ($method['is_default']): ?>
                                            <span class="badge bg-success"><i class="fas fa-check me-1"></i> Default</span>
                                        <?php else: ?>
                                            <form method="post">
                                                <input type="hidden" name="method_id" value="<?php echo $method['id']; ?>">
                                                <button type="submit" name="set_default" class="btn btn-sm btn-outline-primary">Set Default</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <form method="post" onsubmit="return confirm('Are you sure you want to delete this payment method?');">
                                            <input type="hidden" name="method_id" value="<?php echo $method['id']; ?>">
                                            <button type="submit" name="delete_method" class="btn btn-sm btn-danger">
                                                <i class="fas fa-trash-alt"></i> Delete
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Add New Payment Method Form -->
                    <div class="card">
                        <div class="card-header bg-light">
                            <h5 class="mb-0">Add Payment Method</h5>
                        </div>
                        <div class="card-body">
                            <form method="post">
                                <div class="mb-3">
                                    <label for="method_type" class="form-label">Payment Method Type</label>
                                    <select class="form-select" id="method_type" name="method_type" required>
                                        <option value="">Select a payment method</option>
                                        <option value="gcash">GCash</option>
                                        <option value="paymaya">PayMaya</option>
                                        <option value="bank_transfer">Bank Transfer</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="account_name" class="form-label">Account Name</label>
                                    <input type="text" class="form-control" id="account_name" name="account_name" required>
                                    <div class="form-text">Enter the name as it appears on your account</div>
                                </div>
                                
                                <div id="account_number_container" class="mb-3">
                                    <label for="account_number" class="form-label">Account Number</label>
                                    <input type="text" class="form-control" id="account_number" name="account_number" required>
                                    <div id="gcash_help" class="form-text d-none">Enter your 11-digit GCash mobile number (e.g., 09123456789)</div>
                                    <div id="paymaya_help" class="form-text d-none">Enter your PayMaya registered mobile number</div>
                                    <div id="bank_help" class="form-text d-none">Enter your bank account number</div>
                                </div>
                                
                                <div class="mb-3 form-check">
                                    <input type="checkbox" class="form-check-input" id="is_default" name="is_default" <?php echo empty($payment_methods) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="is_default">Set as default payment method</label>
                                </div>
                                
                                <button type="submit" name="add_payment_method" class="btn btn-primary">
                                    <i class="fas fa-plus-circle me-1"></i> Add Payment Method
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <a href="profile.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-1"></i> Back to Profile
                    </a>
                    
                    <?php if (!empty($payment_methods)): ?>
                    <a href="products.php" class="btn btn-primary float-end">
                        <i class="fas fa-shopping-cart me-1"></i> Browse Products
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const methodTypeSelect = document.getElementById('method_type');
    const gcashHelp = document.getElementById('gcash_help');
    const paymayaHelp = document.getElementById('paymaya_help');
    const bankHelp = document.getElementById('bank_help');
    const accountNumberInput = document.getElementById('account_number');
    
    methodTypeSelect.addEventListener('change', function() {
        // Hide all help texts
        gcashHelp.classList.add('d-none');
        paymayaHelp.classList.add('d-none');
        bankHelp.classList.add('d-none');
        
        // Reset pattern
        accountNumberInput.removeAttribute('pattern');
        
        // Show relevant help based on selected payment method
        switch(this.value) {
            case 'gcash':
                gcashHelp.classList.remove('d-none');
                accountNumberInput.setAttribute('pattern', '^09\\d{9}$');
                accountNumberInput.placeholder = "09123456789";
                break;
            case 'paymaya':
                paymayaHelp.classList.remove('d-none');
                accountNumberInput.setAttribute('pattern', '^09\\d{9}$');
                accountNumberInput.placeholder = "09123456789";
                break;
            case 'bank_transfer':
                bankHelp.classList.remove('d-none');
                accountNumberInput.placeholder = "Bank account number";
                break;
        }
    });
});
</script> 