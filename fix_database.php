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

echo "<h1>Database Fix Utility</h1>";
echo "<p>Attempting to create the missing source_access_requests table...</p>";

// SQL to create the missing table
$sql = "CREATE TABLE IF NOT EXISTS source_access_requests (
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
)";

// Execute query
if ($conn->query($sql) === TRUE) {
    echo "<p style='color:green;'>✓ Success: source_access_requests table has been created successfully.</p>";
    
    // Check if the table exists
    $result = $conn->query("SHOW TABLES LIKE 'source_access_requests'");
    if ($result->num_rows > 0) {
        echo "<p style='color:green;'>✓ Verification: Table exists in the database.</p>";
    } else {
        echo "<p style='color:red;'>❌ Verification failed: Table was not created properly.</p>";
    }
} else {
    echo "<p style='color:red;'>❌ Error creating table: " . $conn->error . "</p>";
}

echo "<p>Database fix attempt completed. <a href='index.php'>Return to homepage</a></p>";

// Close connection
$conn->close();
?>
