
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    first_name VARCHAR(50),
    last_name VARCHAR(50),
    role ENUM('admin', 'seller', 'buyer') NOT NULL DEFAULT 'buyer',
    status ENUM('active', 'inactive', 'suspended') NOT NULL DEFAULT 'active',
    is_approved ENUM('yes', 'no', 'pending') NOT NULL DEFAULT 'pending',
    phone_number VARCHAR(20),
    profile_image VARCHAR(255),
    bio TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Categories table
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);




-- Insert default categories
INSERT INTO categories (name, description) VALUES 
('Source Code', 'Complete source code projects and applications'),
('UI Templates', 'Website templates, UI kits, and design assets'),
('Systems', 'Full systems with documentation and installation guides'),
('Web Development', 'Services and products related to web development'),
('Mobile App Development', 'Services and products related to mobile app development'),
('Graphic Design', 'Services and products related to graphic design'),
('Digital Marketing', 'Services and products related to digital marketing'),
('UI/UX Design', 'Services and products related to user interface and user experience design'),
('Other Digital Products', 'Other digital products that don\'t fit in the above categories')
ON DUPLICATE KEY UPDATE name = name;

-- Products table
CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    image VARCHAR(255),
    file_path VARCHAR(255),
    seller_id INT NOT NULL,
    category_id INT NULL,
    status ENUM('available', 'sold', 'hidden', 'deleted') DEFAULT 'available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
);

-- Reviews table
CREATE TABLE IF NOT EXISTS reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    user_id INT NOT NULL,
    rating INT NOT NULL CHECK (rating BETWEEN 1 AND 5),
    comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Transactions table
CREATE TABLE IF NOT EXISTS transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    buyer_id INT NOT NULL,
    seller_id INT NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    commission_rate DECIMAL(5, 2) NOT NULL DEFAULT 10.00,
    platform_fee DECIMAL(10, 2) NOT NULL DEFAULT 0.00, 
    status ENUM('pending', 'completed', 'cancelled', 'refunded') DEFAULT 'pending',
    payment_method VARCHAR(50) NOT NULL,
    reference_number VARCHAR(100),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (buyer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Platform Revenue table
CREATE TABLE IF NOT EXISTS platform_revenue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_id INT NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    payout_status ENUM('pending', 'paid') DEFAULT 'pending',
    payout_date TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE CASCADE
);

-- Seller Balance table
CREATE TABLE IF NOT EXISTS seller_balance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    seller_id INT NOT NULL,
    available_balance DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    pending_balance DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    total_earnings DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Seller Payment Methods table
CREATE TABLE IF NOT EXISTS seller_payment_methods (
    id INT AUTO_INCREMENT PRIMARY KEY,
    seller_id INT NOT NULL,
    method_type ENUM('gcash', 'paymaya', 'bank_transfer') NOT NULL,
    account_name VARCHAR(100) NOT NULL,
    account_number VARCHAR(50) NOT NULL,
    is_default BOOLEAN DEFAULT FALSE,
    additional_info TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Buyer Payment Methods table
CREATE TABLE IF NOT EXISTS buyer_payment_methods (
    id INT AUTO_INCREMENT PRIMARY KEY,
    buyer_id INT NOT NULL,
    method_type ENUM('gcash', 'paymaya', 'bank_transfer') NOT NULL,
    account_name VARCHAR(100) NOT NULL,
    account_number VARCHAR(50) NOT NULL,
    is_default BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (buyer_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Add indexes for buyer_payment_methods
CREATE INDEX IF NOT EXISTS idx_payment_method_type ON buyer_payment_methods (method_type);
CREATE INDEX IF NOT EXISTS idx_payment_is_default ON buyer_payment_methods (is_default);

-- Payment Methods table
CREATE TABLE IF NOT EXISTS payment_methods (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default payment methods
INSERT INTO payment_methods (name, description) VALUES 
('GCash', 'Mobile wallet payment via GCash'),
('PayMaya', 'Mobile wallet payment via PayMaya'),
('Bank Transfer', 'Direct bank transfer payment'),
('Cash on Delivery', 'Payment upon delivery')
ON DUPLICATE KEY UPDATE name = name;

-- Messages table
CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Source Code Access Requests table
CREATE TABLE IF NOT EXISTS source_access_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    seller_id INT NOT NULL,
    buyer_id INT NOT NULL,
    product_id INT NOT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (buyer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- Create admin user with plain password 'admin123'
-- The password hash below is generated using PHP's password_hash() with PASSWORD_DEFAULT
INSERT INTO users (username, email, password, role, status) VALUES 
('admin', 'admin@devmarket.ph', '$2y$10$9UhpO9tpMHPiVHeVTbkYCOpQJMVo7Se4ODrEJz/XDvPgEbUxoUN66', 'admin', 'active')
ON DUPLICATE KEY UPDATE id = id;

-- Create a function to run this SQL in PHP
-- You can execute this file via http://localhost/DevShops/fix_database.php
-- or run the SQL directly in phpMyAdmin 