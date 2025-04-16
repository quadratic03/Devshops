<div class="dropdown-menu" aria-labelledby="navbarDropdown">
    <?php if (is_admin()): ?>
        <a class="dropdown-item" href="<?php echo get_base_url(); ?>admin/dashboard.php">
            <i class="fas fa-tachometer-alt me-2"></i> Admin Dashboard
        </a>
        <div class="dropdown-divider"></div>
    <?php endif; ?>
    
    <?php if (is_seller()): ?>
        <a class="dropdown-item" href="<?php echo get_base_url(); ?>seller/dashboard.php">
            <i class="fas fa-store me-2"></i> Seller Dashboard
        </a>
        <div class="dropdown-divider"></div>
    <?php endif; ?>
    
    <a class="dropdown-item" href="<?php echo get_base_url(); ?>profile.php">
        <i class="fas fa-user me-2"></i> My Profile
    </a>
    <a class="dropdown-item" href="<?php echo get_base_url(); ?>purchases.php">
        <i class="fas fa-shopping-bag me-2"></i> My Purchases
    </a>
    <div class="dropdown-divider"></div>
    <a class="dropdown-item" href="<?php echo get_base_url(); ?>logout.php">
        <i class="fas fa-sign-out-alt me-2"></i> Logout
    </a>
</div> 