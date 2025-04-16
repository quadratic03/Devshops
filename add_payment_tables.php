<?php
// Database connection parameters
$host = 'localhost';
$username = 'root'; // Default XAMPP username
$password = ''; // Default XAMPP password
$database = 'devmarket_ph';

// Connect to database
$conn = new mysqli($host, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h1>Payment Tables Update Utility</h1>";

// SQL to create seller payment methods table
$seller_table_sql = "CREATE TABLE IF NOT EXISTS seller_payment_methods (
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
)";

// SQL to create buyer payment methods table
$buyer_table_sql = "CREATE TABLE IF NOT EXISTS buyer_payment_methods (
    id INT AUTO_INCREMENT PRIMARY KEY,
    buyer_id INT NOT NULL,
    method_type ENUM('gcash', 'paymaya', 'bank_transfer') NOT NULL,
    account_name VARCHAR(100) NOT NULL,
    account_number VARCHAR(50) NOT NULL,
    is_default BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (buyer_id) REFERENCES users(id) ON DELETE CASCADE
)";

// Execute queries
$tables = [
    'seller_payment_methods' => $seller_table_sql,
    'buyer_payment_methods' => $buyer_table_sql
];

foreach ($tables as $table_name => $sql) {
    if ($conn->query($sql) === TRUE) {
        echo "<p style='color:green;'>✓ Success: $table_name table has been created successfully.</p>";
        
        // Check if the table exists
        $result = $conn->query("SHOW TABLES LIKE '$table_name'");
        if ($result->num_rows > 0) {
            echo "<p style='color:green;'>✓ Verification: $table_name table exists in the database.</p>";
        } else {
            echo "<p style='color:red;'>❌ Verification failed: $table_name table was not created properly.</p>";
        }
    } else {
        echo "<p style='color:red;'>❌ Error creating $table_name table: " . $conn->error . "</p>";
    }
}

// Add bio column to users table if it doesn't exist
$check_bio_column = $conn->query("SHOW COLUMNS FROM users LIKE 'bio'");
if ($check_bio_column->num_rows == 0) {
    $add_bio_sql = "ALTER TABLE users ADD COLUMN bio TEXT AFTER email";
    if ($conn->query($add_bio_sql) === TRUE) {
        echo "<p style='color:green;'>✓ Success: Added 'bio' column to users table.</p>";
    } else {
        echo "<p style='color:red;'>❌ Error adding 'bio' column: " . $conn->error . "</p>";
    }
}

echo "<p>Database update completed. <a href='index.php'>Return to homepage</a></p>";

// Close connection
$conn->close();
?> 