<?php
// Database connection parameters
$host = 'localhost';
$username = 'root'; // Default XAMPP username
$password = ''; // Default XAMPP password
$database = 'devmarket_ph'; // Your database name

// Create connection
$conn = new mysqli($host, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "Connected to database successfully.<br>";

// Read the SQL file
$sql = file_get_contents('update_schema.sql');

// Execute multi-query SQL
if ($conn->multi_query($sql)) {
    echo "Schema updated successfully.<br>";
    
    // Process all result sets
    do {
        if ($result = $conn->store_result()) {
            $result->free();
        }
    } while ($conn->more_results() && $conn->next_result());
    
} else {
    echo "Error updating schema: " . $conn->error . "<br>";
}

$conn->close();
echo "Database connection closed.<br>";
echo "You can now <a href='admin/users.php'>go back to the users page</a>.";
?> 