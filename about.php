<?php
require_once 'includes/header.php';
?>

<div class="row">
    <div class="col-md-12">
        <h1 class="mb-4">About DevMarket Philippines</h1>
    </div>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card mb-4">
            <div class="card-body">
                <h2 class="card-title">Our Mission</h2>
                <p class="card-text">
                    DevMarket Philippines aims to be the premier digital marketplace for Filipino developers 
                    to showcase and monetize their technical skills. We provide a platform where talented 
                    developers can sell their digital products, source code, UI templates, and complete systems 
                    to clients who need ready-made solutions.
                </p>
                <p class="card-text">
                    Our goal is to bridge the gap between Filipino tech talent and potential buyers, 
                    creating opportunities for developers and providing valuable resources to businesses 
                    and individuals looking for digital solutions.
                </p>
            </div>
        </div>
        
        <div class="card mb-4">
            <div class="card-body">
                <h2 class="card-title">How It Works</h2>
                <div class="row">
                    <div class="col-md-4 text-center mb-4">
                        <div class="bg-light p-3 rounded">
                            <i class="fas fa-upload fa-3x text-primary mb-3"></i>
                            <h4>Create & Upload</h4>
                            <p>Developers create digital products and upload them to our platform</p>
                        </div>
                    </div>
                    <div class="col-md-4 text-center mb-4">
                        <div class="bg-light p-3 rounded">
                            <i class="fas fa-search fa-3x text-primary mb-3"></i>
                            <h4>Browse & Discover</h4>
                            <p>Buyers browse our marketplace to find solutions for their needs</p>
                        </div>
                    </div>
                    <div class="col-md-4 text-center mb-4">
                        <div class="bg-light p-3 rounded">
                            <i class="fas fa-handshake fa-3x text-primary mb-3"></i>
                            <h4>Connect & Purchase</h4>
                            <p>Direct connection between buyer and seller for a smooth transaction</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card mb-4">
            <div class="card-body">
                <h2 class="card-title">Why Choose DevMarket Philippines?</h2>
                <ul class="list-group list-group-flush mb-4">
                    <li class="list-group-item">
                        <i class="fas fa-check-circle text-success me-2"></i>
                        <strong>Filipino Talent:</strong> Support local developers and access culture-specific solutions
                    </li>
                    <li class="list-group-item">
                        <i class="fas fa-check-circle text-success me-2"></i>
                        <strong>Direct Communication:</strong> Connect directly with sellers for customization options
                    </li>
                    <li class="list-group-item">
                        <i class="fas fa-check-circle text-success me-2"></i>
                        <strong>Quality Products:</strong> All products undergo a basic review process
                    </li>
                    <li class="list-group-item">
                        <i class="fas fa-check-circle text-success me-2"></i>
                        <strong>Diverse Categories:</strong> From simple code snippets to complete enterprise systems
                    </li>
                    <li class="list-group-item">
                        <i class="fas fa-check-circle text-success me-2"></i>
                        <strong>Community:</strong> Join a growing community of Filipino developers and tech enthusiasts
                    </li>
                </ul>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card mb-4">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0">Join Our Community</h5>
            </div>
            <div class="card-body">
                <p>Become a part of the DevMarket Philippines community today!</p>
                
                <?php if (!is_logged_in()): ?>
                <div class="d-grid gap-2">
                    <a href="register.php" class="btn btn-primary">Sign Up Now</a>
                    <a href="login.php" class="btn btn-outline-secondary">Already Have an Account? Login</a>
                </div>
                <?php else: ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i> You're already a member!
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="card mb-4">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0">Our Stats</h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-6 mb-3">
                        <h2 class="text-primary"><?php
                            $products_count = $conn->query("SELECT COUNT(*) as count FROM products WHERE status = 'active'")->fetch_assoc()['count'];
                            echo $products_count;
                        ?></h2>
                        <p class="text-muted">Products</p>
                    </div>
                    <div class="col-6 mb-3">
                        <h2 class="text-primary"><?php
                            $sellers_count = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'seller'")->fetch_assoc()['count'];
                            echo $sellers_count;
                        ?></h2>
                        <p class="text-muted">Sellers</p>
                    </div>
                    <div class="col-6">
                        <h2 class="text-primary"><?php
                            $users_count = $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'];
                            echo $users_count;
                        ?></h2>
                        <p class="text-muted">Users</p>
                    </div>
                    <div class="col-6">
                        <h2 class="text-primary"><?php
                            $categories_count = $conn->query("SELECT COUNT(*) as count FROM categories")->fetch_assoc()['count'];
                            echo $categories_count;
                        ?></h2>
                        <p class="text-muted">Categories</p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0">Contact Us</h5>
            </div>
            <div class="card-body">
                <p>Have questions about DevMarket Philippines?</p>
                <a href="contact.php" class="btn btn-primary w-100">Get In Touch</a>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?> 