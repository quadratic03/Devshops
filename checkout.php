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
$product_id = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;

// If no product_id, redirect to home
if ($product_id <= 0) {
    header("Location: index.php");
    exit();
}

// Get product details
$product = get_product($product_id);
if (!$product) {
    header("Location: products.php");
    exit();
}

// Check if product is available
if ($product['status'] !== 'available') {
    $error = "This product is no longer available for purchase.";
}

// Check if buyer is trying to buy their own product
if ($product['seller_id'] == $_SESSION['user_id']) {
    $error = "You cannot purchase your own product.";
}

// Define payment methods (we'll create this table later)
$payment_methods = [
    ['id' => 1, 'name' => 'GCash', 'description' => 'Mobile wallet payment via GCash'],
    ['id' => 2, 'name' => 'PayMaya', 'description' => 'Mobile wallet payment via PayMaya'],
    ['id' => 3, 'name' => 'Bank Transfer', 'description' => 'Direct bank transfer payment']
];

// Get seller payment methods
$seller_payment_methods_query = "SELECT * FROM seller_payment_methods WHERE seller_id = ? AND is_default = 1";
$seller_payment_stmt = $conn->prepare($seller_payment_methods_query);
$seller_payment_stmt->bind_param("i", $product['seller_id']);
$seller_payment_stmt->execute();
$seller_payment_result = $seller_payment_stmt->get_result();
$seller_payment_method = $seller_payment_result->fetch_assoc();

// Get buyer payment methods
$buyer_payment_methods_query = "SELECT * FROM buyer_payment_methods WHERE buyer_id = ? ORDER BY is_default DESC, created_at DESC";
$buyer_payment_stmt = $conn->prepare($buyer_payment_methods_query);
$buyer_payment_stmt->bind_param("i", $_SESSION['user_id']);
$buyer_payment_stmt->execute();
$buyer_payment_result = $buyer_payment_stmt->get_result();
$buyer_payment_methods = [];

while ($method = $buyer_payment_result->fetch_assoc()) {
    $buyer_payment_methods[] = $method;
}

// Process payment
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['process_payment'])) {
    $payment_method = clean_input($_POST['payment_method']);
    $reference_number = clean_input($_POST['reference_number']);
    $notes = clean_input($_POST['notes']);
    
    // Calculate platform fee (10% by default)
    $amount = $product['price'];
    $commission_rate = 10.00; // 10% commission
    $platform_fee = $amount * ($commission_rate / 100);
    $seller_amount = $amount - $platform_fee;
    
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // 1. Insert transaction record
        $transaction_sql = "INSERT INTO transactions (product_id, buyer_id, seller_id, amount, 
                            payment_method, reference_number, notes, status) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, 'completed')";
        $transaction_stmt = $conn->prepare($transaction_sql);
        $transaction_stmt->bind_param("iiidsss", $product_id, $_SESSION['user_id'], $product['seller_id'], 
                                    $amount, $payment_method, $reference_number, $notes);
        $transaction_stmt->execute();
        $transaction_id = $conn->insert_id;
        
        // 2. Update product status to 'sold'
        $product_update = "UPDATE products SET status = 'sold' WHERE id = ?";
        $product_update_stmt = $conn->prepare($product_update);
        $product_update_stmt->bind_param("i", $product_id);
        $product_update_stmt->execute();
        
        // Commit the transaction
        $conn->commit();
        $success = "Payment successful! Your purchase has been completed.";
        
    } catch (Exception $e) {
        // Rollback the transaction if any part fails
        $conn->rollback();
        $error = "Transaction failed: " . $e->getMessage();
    }
}

include 'includes/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-8 offset-md-2">
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <?php echo $success; ?>
                    <hr>
                    <div class="text-center">
                        <a href="product.php?id=<?php echo $product_id; ?>" class="btn btn-success">
                            <i class="fas fa-download me-2"></i> Download Source Code
                        </a>
                        <a href="products.php" class="btn btn-secondary">Continue Shopping</a>
                    </div>
                </div>
            <?php else: ?>
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">Complete Your Purchase</h4>
                    </div>
                    <div class="card-body">
                        <div class="row mb-4">
                            <div class="col-md-4">
                                <img src="<?php echo !empty($product['image']) ? 'uploads/products/' . $product['image'] : 'assets/images/placeholder.png'; ?>" 
                                     class="img-fluid rounded" alt="<?php echo htmlspecialchars($product['name']); ?>">
                            </div>
                            <div class="col-md-8">
                                <h4><?php echo htmlspecialchars($product['name']); ?></h4>
                                <p class="text-muted"><?php echo htmlspecialchars($product['category_name']); ?></p>
                                <p><strong>Seller:</strong> <?php echo htmlspecialchars($product['seller_name']); ?></p>
                                <h5 class="text-primary"><?php echo format_currency($product['price']); ?></h5>
                            </div>
                        </div>
                        
                        <form method="post" action="checkout.php?product_id=<?php echo $product_id; ?>">
                            <h5 class="mb-3">Payment Details</h5>
                            
                            <div class="mb-3">
                                <label for="payment_method" class="form-label">Payment Method</label>
                                <select class="form-select" id="payment_method" name="payment_method" required>
                                    <option value="">Select payment method</option>
                                    <?php foreach ($payment_methods as $method): ?>
                                    <option value="<?php echo htmlspecialchars($method['name']); ?>">
                                        <?php echo htmlspecialchars($method['name']); ?> - <?php echo htmlspecialchars($method['description']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <!-- Payment method details containers -->
                            <div id="payment_details_container" class="mb-4">
                                <?php if ($seller_payment_method): ?>
                                <!-- GCash Payment Details -->
                                <div id="gcash_details" class="payment-details d-none">
                                    <?php if ($seller_payment_method['method_type'] === 'gcash'): ?>
                                    <div class="alert alert-info">
                                        <h6>GCash Payment Details</h6>
                                        <p><strong>Account Name:</strong> <?php 
                                            $name = htmlspecialchars($seller_payment_method['account_name']);
                                            $name_parts = explode(' ', $name);
                                            $masked_name = '';
                                            
                                            if (count($name_parts) >= 2) {
                                                // For first name, show only first 2 letters
                                                $first_name = $name_parts[0];
                                                $masked_first = (strlen($first_name) >= 2) ? 
                                                    substr($first_name, 0, 2) . str_repeat('*', strlen($first_name) - 2) : 
                                                    $first_name;
                                                
                                                // For last name, show only first 2 letters
                                                $last_name = $name_parts[count($name_parts) - 1];
                                                $masked_last = (strlen($last_name) >= 2) ? 
                                                    substr($last_name, 0, 2) . str_repeat('*', strlen($last_name) - 2) : 
                                                    $last_name;
                                                
                                                // Add middle names if any (fully masked)
                                                $middle_parts = array_slice($name_parts, 1, count($name_parts) - 2);
                                                $masked_middle = '';
                                                foreach ($middle_parts as $part) {
                                                    $masked_middle .= str_repeat('*', strlen($part)) . ' ';
                                                }
                                                
                                                $masked_name = $masked_first . ' ' . $masked_middle . $masked_last;
                                            } else {
                                                // If only one word, show first 2 letters
                                                $masked_name = (strlen($name) >= 2) ? 
                                                    substr($name, 0, 2) . str_repeat('*', strlen($name) - 2) : 
                                                    $name;
                                            }
                                            
                                            echo trim($masked_name);
                                        ?></p>
                                        <p><strong>GCash Number:</strong> <?php 
                                            $number = htmlspecialchars($seller_payment_method['account_number']);
                                            if (strlen($number) > 4) {
                                                echo substr($number, 0, 2) . '******' . substr($number, -2);
                                            } else {
                                                echo $number;
                                            }
                                        ?></p>
                                        <?php if (!empty($seller_payment_method['additional_info'])): ?>
                                        <p><strong>Additional Info:</strong> <?php echo htmlspecialchars($seller_payment_method['additional_info']); ?></p>
                                        <?php endif; ?>
                                        <p class="mt-2">
                                            <a href="https://gcash.com" target="_blank" class="btn btn-primary btn-sm">
                                                <i class="fas fa-external-link-alt me-1"></i> Go to GCash App/Website
                                            </a>
                                        </p>
                                    </div>
                                    <?php else: ?>
                                    <div class="alert alert-warning">
                                        <p>The seller doesn't have GCash payment details set up. Please choose another payment method.</p>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- PayMaya Payment Details -->
                                <div id="paymaya_details" class="payment-details d-none">
                                    <?php if ($seller_payment_method['method_type'] === 'paymaya'): ?>
                                    <div class="alert alert-info">
                                        <h6>PayMaya Payment Details</h6>
                                        <p><strong>Account Name:</strong> <?php 
                                            $name = htmlspecialchars($seller_payment_method['account_name']);
                                            $name_parts = explode(' ', $name);
                                            $masked_name = '';
                                            
                                            if (count($name_parts) >= 2) {
                                                // For first name, show only first 2 letters
                                                $first_name = $name_parts[0];
                                                $masked_first = (strlen($first_name) >= 2) ? 
                                                    substr($first_name, 0, 2) . str_repeat('*', strlen($first_name) - 2) : 
                                                    $first_name;
                                                
                                                // For last name, show only first 2 letters
                                                $last_name = $name_parts[count($name_parts) - 1];
                                                $masked_last = (strlen($last_name) >= 2) ? 
                                                    substr($last_name, 0, 2) . str_repeat('*', strlen($last_name) - 2) : 
                                                    $last_name;
                                                
                                                // Add middle names if any (fully masked)
                                                $middle_parts = array_slice($name_parts, 1, count($name_parts) - 2);
                                                $masked_middle = '';
                                                foreach ($middle_parts as $part) {
                                                    $masked_middle .= str_repeat('*', strlen($part)) . ' ';
                                                }
                                                
                                                $masked_name = $masked_first . ' ' . $masked_middle . $masked_last;
                                            } else {
                                                // If only one word, show first 2 letters
                                                $masked_name = (strlen($name) >= 2) ? 
                                                    substr($name, 0, 2) . str_repeat('*', strlen($name) - 2) : 
                                                    $name;
                                            }
                                            
                                            echo trim($masked_name);
                                        ?></p>
                                        <p><strong>PayMaya Number:</strong> <?php 
                                            $number = htmlspecialchars($seller_payment_method['account_number']);
                                            if (strlen($number) > 4) {
                                                echo substr($number, 0, 2) . '******' . substr($number, -2);
                                            } else {
                                                echo $number;
                                            }
                                        ?></p>
                                        <?php if (!empty($seller_payment_method['additional_info'])): ?>
                                        <p><strong>Additional Info:</strong> <?php echo htmlspecialchars($seller_payment_method['additional_info']); ?></p>
                                        <?php endif; ?>
                                        <p class="mt-2">
                                            <a href="https://paymaya.com" target="_blank" class="btn btn-success btn-sm">
                                                <i class="fas fa-external-link-alt me-1"></i> Go to PayMaya App/Website
                                            </a>
                                        </p>
                                    </div>
                                    <?php else: ?>
                                    <div class="alert alert-warning">
                                        <p>The seller doesn't have PayMaya payment details set up. Please choose another payment method.</p>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Bank Transfer Payment Details -->
                                <div id="bank_details" class="payment-details d-none">
                                    <?php if ($seller_payment_method['method_type'] === 'bank_transfer'): ?>
                                    <div class="alert alert-info">
                                        <h6>Bank Transfer Details</h6>
                                        <p><strong>Account Name:</strong> <?php 
                                            $name = htmlspecialchars($seller_payment_method['account_name']);
                                            $name_parts = explode(' ', $name);
                                            $masked_name = '';
                                            
                                            if (count($name_parts) >= 2) {
                                                // For first name, show only first 2 letters
                                                $first_name = $name_parts[0];
                                                $masked_first = (strlen($first_name) >= 2) ? 
                                                    substr($first_name, 0, 2) . str_repeat('*', strlen($first_name) - 2) : 
                                                    $first_name;
                                                
                                                // For last name, show only first 2 letters
                                                $last_name = $name_parts[count($name_parts) - 1];
                                                $masked_last = (strlen($last_name) >= 2) ? 
                                                    substr($last_name, 0, 2) . str_repeat('*', strlen($last_name) - 2) : 
                                                    $last_name;
                                                
                                                // Add middle names if any (fully masked)
                                                $middle_parts = array_slice($name_parts, 1, count($name_parts) - 2);
                                                $masked_middle = '';
                                                foreach ($middle_parts as $part) {
                                                    $masked_middle .= str_repeat('*', strlen($part)) . ' ';
                                                }
                                                
                                                $masked_name = $masked_first . ' ' . $masked_middle . $masked_last;
                                            } else {
                                                // If only one word, show first 2 letters
                                                $masked_name = (strlen($name) >= 2) ? 
                                                    substr($name, 0, 2) . str_repeat('*', strlen($name) - 2) : 
                                                    $name;
                                            }
                                            
                                            echo trim($masked_name);
                                        ?></p>
                                        <p><strong>Account Number:</strong> <?php 
                                            $number = htmlspecialchars($seller_payment_method['account_number']);
                                            if (strlen($number) > 4) {
                                                echo substr($number, 0, 2) . '******' . substr($number, -2);
                                            } else {
                                                echo $number;
                                            }
                                        ?></p>
                                        <?php if (!empty($seller_payment_method['additional_info'])): ?>
                                        <p><strong>Bank/Branch Info:</strong> <?php echo htmlspecialchars($seller_payment_method['additional_info']); ?></p>
                                        <?php endif; ?>
                                        <p class="text-muted mt-2">
                                            Please transfer the exact amount to the bank account provided above.
                                        </p>
                                    </div>
                                    <?php else: ?>
                                    <div class="alert alert-warning">
                                        <p>The seller doesn't have bank details set up. Please choose another payment method.</p>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <?php else: ?>
                                <div class="alert alert-warning">
                                    <p>The seller has not set up any payment methods yet. Please contact the seller for payment instructions.</p>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="mb-3">
                                <label for="reference_number" class="form-label">Reference Number</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="reference_number" name="reference_number" 
                                       value="<?php echo 'PAY-' . date('YmdHis') . '-' . rand(1000, 9999); ?>" readonly>
                                    <button class="btn btn-outline-secondary" type="button" onclick="regenerateReference()">
                                        <i class="fas fa-sync-alt"></i> Regenerate
                                    </button>
                                </div>
                                <div class="form-text">This reference number will be automatically generated once payment is confirmed.</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="notes" class="form-label">Notes (Optional)</label>
                                <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                            </div>
                            
                            <div class="alert alert-info">
                                <h6>Order Summary</h6>
                                <div class="d-flex justify-content-between">
                                    <span>Product Price:</span>
                                    <span><?php echo format_currency($product['price']); ?></span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span>Platform Fee (10%):</span>
                                    <span><?php echo format_currency($product['price'] * 0.1); ?></span>
                                </div>
                                <hr>
                                <div class="d-flex justify-content-between fw-bold">
                                    <span>Total Amount:</span>
                                    <span><?php echo format_currency($product['price']); ?></span>
                                </div>
                            </div>
                            
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="terms" required>
                                <label class="form-check-label" for="terms">
                                    I agree to the <a href="#" data-bs-toggle="modal" data-bs-target="#termsModal">Terms and Conditions</a>
                                </label>
                            </div>
                            
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="payment_made" name="payment_made" required>
                                <label class="form-check-label" for="payment_made">
                                    <strong>I confirm that I have made the payment</strong>
                                </label>
                            </div>
                            
                            <div class="text-end">
                                <a href="product.php?id=<?php echo $product_id; ?>" class="btn btn-secondary">Cancel</a>
                                <button type="submit" name="process_payment" class="btn btn-primary">Complete Purchase</button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Terms and Conditions Modal -->
<div class="modal fade" id="termsModal" tabindex="-1" aria-labelledby="termsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="termsModalLabel">Terms and Conditions</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <h5>Payment Terms</h5>
                <p>By completing this purchase, you agree to the following terms:</p>
                <ul>
                    <li>All payments are processed securely through our platform</li>
                    <li>A 10% platform fee is charged on all transactions</li>
                    <li>Refunds must be requested within 7 days of purchase</li>
                    <li>Refunds are subject to seller approval</li>
                    <li>All transactions are final after successful download of source code</li>
                </ul>
                
                <h5>Product Usage</h5>
                <p>By purchasing this product, you acknowledge that:</p>
                <ul>
                    <li>You are purchasing a license to use the source code</li>
                    <li>You may not redistribute or resell the purchased source code</li>
                    <li>The seller retains intellectual property rights</li>
                    <li>Source code is provided as-is without warranty</li>
                </ul>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">I Understand</button>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<script>
// Handle payment method selection
document.addEventListener('DOMContentLoaded', function() {
    const paymentMethodSelect = document.getElementById('payment_method');
    const gcashDetails = document.getElementById('gcash_details');
    const paymayaDetails = document.getElementById('paymaya_details');
    const bankDetails = document.getElementById('bank_details');
    
    function hideAllPaymentDetails() {
        const paymentDetails = document.querySelectorAll('.payment-details');
        paymentDetails.forEach(detail => {
            detail.classList.add('d-none');
        });
    }
    
    if (paymentMethodSelect) {
        paymentMethodSelect.addEventListener('change', function() {
            hideAllPaymentDetails();
            
            const selectedMethod = this.value;
            
            if (selectedMethod === 'GCash' && gcashDetails) {
                gcashDetails.classList.remove('d-none');
            } else if (selectedMethod === 'PayMaya' && paymayaDetails) {
                paymayaDetails.classList.remove('d-none');
            } else if (selectedMethod === 'Bank Transfer' && bankDetails) {
                bankDetails.classList.remove('d-none');
            }
        });
    }
});

// Function to regenerate the reference number
function regenerateReference() {
    const now = new Date();
    const year = now.getFullYear();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');
    const hours = String(now.getHours()).padStart(2, '0');
    const minutes = String(now.getMinutes()).padStart(2, '0');
    const seconds = String(now.getSeconds()).padStart(2, '0');
    const random = Math.floor(Math.random() * 9000) + 1000;
    
    const newReference = `PAY-${year}${month}${day}${hours}${minutes}${seconds}-${random}`;
    document.getElementById('reference_number').value = newReference;
}
</script>
</body>
</html> 