<?php
// Get current page
$current_page = basename($_SERVER['PHP_SELF']);

// Count pending access requests
$pending_requests = 0;
try {
    $access_query = "SELECT COUNT(*) as count FROM source_access_requests WHERE seller_id = ? AND status = 'pending'";
    $stmt = $conn->prepare($access_query);
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $access_result = $stmt->get_result();
    if ($access_result && $access_result->num_rows > 0) {
        $pending_requests = $access_result->fetch_assoc()['count'];
    }
} catch (mysqli_sql_exception $e) {
    // Table doesn't exist or other database error
    // Just continue with pending_requests = 0
    error_log("Database error in seller sidebar: " . $e->getMessage());
}

// Count unread messages
$unread_messages = 0;
try {
    $messages_query = "SELECT COUNT(*) as count FROM messages 
                       WHERE receiver_id = ? AND is_read = FALSE 
                       AND sender_id IN (SELECT id FROM users WHERE role = 'buyer')";
    $stmt = $conn->prepare($messages_query);
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $messages_result = $stmt->get_result();
    if ($messages_result && $messages_result->num_rows > 0) {
        $unread_messages = $messages_result->fetch_assoc()['count'];
    }
} catch (mysqli_sql_exception $e) {
    // Table doesn't exist or other database error
    error_log("Database error counting messages in seller sidebar: " . $e->getMessage());
}
?>

<div class="card mb-4">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0">Seller Menu</h5>
    </div>
    <div class="list-group list-group-flush">
        <a href="http://localhost/devshops/" class="list-group-item list-group-item-action">
            <i class="fas fa-home me-2"></i> Back to Site
        </a>
        <a href="dashboard.php" class="list-group-item list-group-item-action <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
            <i class="fas fa-tachometer-alt me-2"></i> Dashboard
        </a>
        <a href="add_product.php" class="list-group-item list-group-item-action <?php echo $current_page == 'add_product.php' ? 'active' : ''; ?>">
            <i class="fas fa-plus-circle me-2"></i> Add New Product
        </a>
        <a href="my_products.php" class="list-group-item list-group-item-action <?php echo $current_page == 'my_products.php' ? 'active' : ''; ?>">
            <i class="fas fa-box me-2"></i> My Products
        </a>
        <a href="access_requests.php" class="list-group-item list-group-item-action <?php echo $current_page == 'access_requests.php' ? 'active' : ''; ?>">
            <i class="fas fa-key me-2"></i> Source Access Requests
            <?php if ($pending_requests > 0): ?>
            <span class="badge bg-danger rounded-pill ms-1"><?php echo $pending_requests; ?></span>
            <?php endif; ?>
        </a>
        <a href="my_messages.php" class="list-group-item list-group-item-action <?php echo $current_page == 'my_messages.php' ? 'active' : ''; ?>">
            <i class="fas fa-envelope me-2"></i> Buyer Messages
            <?php if ($unread_messages > 0): ?>
            <span class="badge bg-danger rounded-pill ms-1"><?php echo $unread_messages; ?></span>
            <?php endif; ?>
        </a>
        <a href="my_transactions.php" class="list-group-item list-group-item-action <?php echo $current_page == 'my_transactions.php' ? 'active' : ''; ?>">
            <i class="fas fa-money-bill-wave me-2"></i> My Transactions
        </a>
        <a href="profile.php" class="list-group-item list-group-item-action <?php echo $current_page == 'profile.php' ? 'active' : ''; ?>">
            <i class="fas fa-user me-2"></i> My Profile
        </a>
    </div>
</div> 