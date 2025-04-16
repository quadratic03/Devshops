<?php
// Installation script for DevMarket Philippines

// Database configuration
$host = "localhost";
$username = "root";
$password = "";

// Create connection
$conn = mysqli_connect($host, $username, $password);

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Read SQL file
$sql = file_get_contents('database/devmarket_ph.sql');

// Execute SQL script
if ($conn->multi_query($sql)) {
    echo "<h2>Database setup successful!</h2>";
    echo "<p>DevMarket Philippines database has been successfully installed.</p>";
    echo "<p>Default admin credentials:</p>";
    echo "<ul>";
    echo "<li>Username: admin</li>";
    echo "<li>Password: admin123</li>";
    echo "</ul>";
    echo "<p><a href='index.php'>Go to homepage</a></p>";
} else {
    echo "<h2>Error setting up database</h2>";
    echo "<p>Error: " . $conn->error . "</p>";
}

mysqli_close($conn);
?> 