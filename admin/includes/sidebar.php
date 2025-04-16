<div class="col-md-3 col-lg-2 d-md-block bg-dark sidebar collapse">
    <div class="position-sticky pt-3">
        <a href="../index.php" class="d-flex align-items-center mb-3 mb-md-0 me-md-auto text-white text-decoration-none">
            <span class="fs-4"><span class="text-primary">web</span>DevMarket</span>
        </a>
        <hr class="text-white">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link text-white <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php">
                    <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-white <?php echo basename($_SERVER['PHP_SELF']) == 'products.php' ? 'active' : ''; ?>" href="products.php">
                    <i class="fas fa-box me-2"></i> Products
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-white <?php echo basename($_SERVER['PHP_SELF']) == 'categories.php' ? 'active' : ''; ?>" href="categories.php">
                    <i class="fas fa-tags me-2"></i> Categories
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-white <?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : ''; ?>" href="users.php">
                    <i class="fas fa-users me-2"></i> Users
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-white <?php echo basename($_SERVER['PHP_SELF']) == 'sellers.php' ? 'active' : ''; ?>" href="sellers.php">
                    <i class="fas fa-store me-2"></i> Sellers
                    <?php
                    // Make sure we have database connection
                    if (!isset($conn) || !$conn) {
                        require_once __DIR__ . '/../../config/database.php';
                    }
                    
                    // Count pending seller approvals - Check if is_approved column exists
                    $pending_count = 0;
                    
                    if (isset($conn) && $conn) {
                        // First check if the column exists
                        $check_column_sql = "SHOW COLUMNS FROM users LIKE 'is_approved'";
                        $column_result = $conn->query($check_column_sql);
                        
                        if ($column_result && $column_result->num_rows > 0) {
                            // Column exists, safe to query it
                            $pending_query = "SELECT COUNT(*) as count FROM users WHERE role='seller' AND is_approved='pending'";
                            $pending_result = $conn->query($pending_query);
                            if ($pending_result && $pending_result->num_rows > 0) {
                                $pending_count = $pending_result->fetch_assoc()['count'];
                            }
                        } else {
                            // Fall back to just counting sellers if the column doesn't exist
                            $pending_query = "SELECT COUNT(*) as count FROM users WHERE role='seller' AND status='inactive'";
                            $pending_result = $conn->query($pending_query);
                            if ($pending_result && $pending_result->num_rows > 0) {
                                $pending_count = $pending_result->fetch_assoc()['count'];
                            }
                        }
                    }
                    
                    if ($pending_count > 0): 
                    ?>
                    <span class="badge bg-danger ms-2"><?php echo $pending_count; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-white <?php echo basename($_SERVER['PHP_SELF']) == 'orders.php' ? 'active' : ''; ?>" href="orders.php">
                    <i class="fas fa-shopping-cart me-2"></i> Orders
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-white <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>" href="reports.php">
                    <i class="fas fa-chart-bar me-2"></i> Reports
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-white <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : ''; ?>" href="settings.php">
                    <i class="fas fa-cog me-2"></i> Settings
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