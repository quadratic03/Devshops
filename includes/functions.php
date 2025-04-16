<?php
// Include database connection
require_once __DIR__ . '/../config/database.php';

// Function to clean input data
function clean_input($data) {
    global $conn;
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return mysqli_real_escape_string($conn, $data);
}

// Function to check if user is logged in
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

// Function to check if user is admin
function is_admin() {
    return (isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'admin');
}

// Function to check if user is seller
function is_seller() {
    return (isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'seller');
}

// Function to get user details
function get_user($user_id) {
    global $conn;
    $sql = "SELECT * FROM users WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

// Function to get all products
function get_products($limit = 0, $category = 0) {
    global $conn;
    
    $sql = "SELECT p.*, c.name as category_name, u.username as seller_name, u.phone_number 
            FROM products p 
            JOIN categories c ON p.category_id = c.id 
            JOIN users u ON p.seller_id = u.id 
            WHERE p.status = 'available'";
    
    if ($category > 0) {
        $sql .= " AND p.category_id = " . (int)$category;
    }
    
    $sql .= " ORDER BY p.created_at DESC";
    
    if ($limit > 0) {
        $sql .= " LIMIT " . (int)$limit;
    }
    
    $result = $conn->query($sql);
    $products = [];
    
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $products[] = $row;
        }
    }
    
    return $products;
}

// Function to get product details
function get_product($product_id) {
    global $conn;
    $sql = "SELECT p.*, c.name as category_name, u.username as seller_name, u.phone_number, u.email as seller_email
            FROM products p 
            JOIN categories c ON p.category_id = c.id 
            JOIN users u ON p.seller_id = u.id 
            WHERE p.id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

// Function to get all categories
function get_categories() {
    global $conn;
    $result = $conn->query("SELECT * FROM categories ORDER BY name");
    $categories = [];
    
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $categories[] = $row;
        }
    }
    
    return $categories;
}

// Generate random string for file names
function generate_random_string($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $randomString;
}

// Format currency
function format_currency($amount) {
    return '₱' . number_format($amount, 2);
}

// Truncate text
function truncate_text($text, $length = 100) {
    if (strlen($text) > $length) {
        return substr($text, 0, $length) . '...';
    }
    return $text;
}

// Function to get base URL
function get_base_url() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $domain = $_SERVER['HTTP_HOST'];
    $script_name = dirname($_SERVER['SCRIPT_NAME']);
    $base_dir = trim(str_replace('\\', '/', $script_name), '/');
    
    // If script is in the root directory
    if (empty($base_dir)) {
        return $protocol . $domain . "/";
    }
    
    // If script is in a subdirectory
    $dirs = explode('/', $base_dir);
    
    // Get admin and seller directories out of URL if present
    if (in_array(end($dirs), ['admin', 'seller'])) {
        array_pop($dirs);
    }
    
    $base_path = implode('/', $dirs);
    return $protocol . $domain . "/" . $base_path . "/";
}
?> 