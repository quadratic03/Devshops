<?php
require_once 'includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8 text-center my-5">
        <div class="card">
            <div class="card-body p-5">
                <h1 class="display-1 text-primary mb-4">404</h1>
                <h2 class="mb-4">Page Not Found</h2>
                <p class="lead mb-5">The page you are looking for might have been removed, had its name changed, or is temporarily unavailable.</p>
                
                <div class="d-grid gap-3 d-sm-flex justify-content-sm-center">
                    <a href="index.php" class="btn btn-primary btn-lg">
                        <i class="fas fa-home me-2"></i> Go to Homepage
                    </a>
                    <a href="products.php" class="btn btn-outline-secondary btn-lg">
                        <i class="fas fa-box me-2"></i> Browse Products
                    </a>
                </div>
            </div>
        </div>
        
        <div class="mt-4">
            <p>Need help? <a href="contact.php">Contact our support team</a>.</p>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?> 