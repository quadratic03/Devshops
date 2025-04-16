<?php
// Only start the session if one doesn't already exist
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/includes/functions.php';

// Check if user is logged in
if (!is_logged_in()) {
    header('Location: login.php');
    exit;
}

// Check if type and id are provided
if (!isset($_GET['type']) || !isset($_GET['id']) || empty($_GET['type']) || empty($_GET['id'])) {
    header('Location: index.php');
    exit;
}

$type = $_GET['type'];
$id = (int)$_GET['id'];

// Handle source code download
if ($type === 'source') {
    // Get product details
    $product = get_product($id);
    
    if (!$product) {
        die('Product not found.');
    }
    
    // Check if user has permission to download
    $user_id = $_SESSION['user_id'];
    $is_admin = $_SESSION['user_type'] === 'admin';
    $is_seller = $_SESSION['user_type'] === 'seller';
    $is_product_owner = $is_seller && $product['seller_id'] == $user_id;
    
    // If user is not admin or product owner, check access request status
    if (!$is_admin && !$is_product_owner) {
        $access_query = "SELECT * FROM source_access_requests WHERE product_id = ? AND buyer_id = ? AND status = 'approved'";
        $stmt = $conn->prepare($access_query);
        $stmt->bind_param("ii", $id, $user_id);
        $stmt->execute();
        $access_result = $stmt->get_result();
        
        if ($access_result->num_rows === 0) {
            die('You do not have permission to download this source code.');
        }
    }
    
    // Get source file path
    if (!isset($product['file_path']) || empty($product['file_path'])) {
        die('Source file not available. This product may not have a downloadable file attached.');
    }
    
    $source_file = $product['file_path'];
    $file_path = __DIR__ . '/uploads/sourcecode/' . $source_file;
    
    // Check if file exists
    if (!file_exists($file_path)) {
        die('Source file not found on server.');
    }
    
    // Set headers for download
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($source_file) . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($file_path));
    
    // Clear output buffer
    ob_clean();
    flush();
    
    // Read file and output to browser
    readfile($file_path);
    exit;
} else {
    // Invalid download type
    header('Location: index.php');
    exit;
}
?> 