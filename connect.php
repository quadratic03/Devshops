<?php
/**
 * Database Connection
 * 
 * This file establishes a connection to the MySQL database
 * for the DevMarket Philippines website.
 */

// Define database connection parameters
$host = "sql209.infinityfree.com";      // Database host
$username = "if0_38759459";       // Database username
$password = "ZL4uvRXizeL";           // Database password (blank for local development)
$database = "devmarket_ph"; // Database name

// Create a database connection
$conn = mysqli_connect($host, $username, $password, $database);

// Check if the connection was successful
if (!$conn) {
    // If connection fails, terminate script and display error message
    die("Database Connection Failed: " . mysqli_connect_error());
}

// Set the character set to UTF-8
mysqli_set_charset($conn, "utf8mb4");

// Uncomment for debugging:
// echo "Database Connection Successful";

// This connection is now available for use in other files that include connect.php
?> 