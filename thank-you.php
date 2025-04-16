<?php
require_once 'includes/header.php';
?>

<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card border-success">
                <div class="card-header bg-success text-white">
                    <h3 class="mb-0"><i class="fas fa-check-circle me-2"></i> Message Sent Successfully</h3>
                </div>
                <div class="card-body">
                    <div class="text-center mb-4">
                        <div class="display-1 text-success mb-3">
                            <i class="fas fa-envelope-open-text"></i>
                        </div>
                        <h2>Thank You for Contacting Us!</h2>
                        <p class="lead">Your message has been received. We appreciate your interest and will get back to you as soon as possible.</p>
                    </div>
                    
                    <div class="alert alert-info">
                        <p><strong>What happens next?</strong></p>
                        <ul>
                            <li>Our team will review your message.</li>
                            <li>You'll receive a confirmation email shortly.</li>
                            <li>We typically respond within 24-48 business hours.</li>
                        </ul>
                    </div>
                    
                    <div class="mt-4 text-center">
                        <a href="index.php" class="btn btn-primary me-2">
                            <i class="fas fa-home me-2"></i> Return to Home
                        </a>
                        <a href="products.php" class="btn btn-outline-primary">
                            <i class="fas fa-shopping-cart me-2"></i> Browse Products
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?> 