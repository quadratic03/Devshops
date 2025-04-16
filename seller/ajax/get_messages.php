<?php
// Only start the session if one doesn't already exist
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../includes/functions.php';
require_once '../../config/database.php';

// Check if user is logged in and is a seller
if (!is_logged_in() || !is_seller()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$user_id = $_SESSION['user_id'];

// Get all conversations for the seller
$conversations_query = "
    SELECT 
        CASE 
            WHEN m.sender_id = ? THEN m.receiver_id
            ELSE m.sender_id
        END as conversation_with,
        u.username as conversation_name,
        u.role as conversation_role,
        (SELECT COUNT(*) FROM messages WHERE sender_id = CASE WHEN m.sender_id = ? THEN m.receiver_id ELSE m.sender_id END AND receiver_id = ? AND is_read = FALSE) as unread_count,
        (SELECT message FROM messages WHERE (sender_id = ? AND receiver_id = CASE WHEN m.sender_id = ? THEN m.receiver_id ELSE m.sender_id END) OR (sender_id = CASE WHEN m.sender_id = ? THEN m.receiver_id ELSE m.sender_id END AND receiver_id = ?) ORDER BY created_at DESC LIMIT 1) as last_message,
        (SELECT created_at FROM messages WHERE (sender_id = ? AND receiver_id = CASE WHEN m.sender_id = ? THEN m.receiver_id ELSE m.sender_id END) OR (sender_id = CASE WHEN m.sender_id = ? THEN m.receiver_id ELSE m.sender_id END AND receiver_id = ?) ORDER BY created_at DESC LIMIT 1) as last_message_time,
        (SELECT p.id FROM products p
         JOIN source_access_requests sar ON p.id = sar.product_id
         WHERE p.seller_id = ? AND sar.buyer_id = CASE WHEN m.sender_id = ? THEN m.receiver_id ELSE m.sender_id END
         LIMIT 1) as related_product_id,
        (SELECT p.name FROM products p
         JOIN source_access_requests sar ON p.id = sar.product_id
         WHERE p.seller_id = ? AND sar.buyer_id = CASE WHEN m.sender_id = ? THEN m.receiver_id ELSE m.sender_id END
         LIMIT 1) as related_product_name
    FROM messages m
    JOIN users u ON (CASE WHEN m.sender_id = ? THEN m.receiver_id ELSE m.sender_id END) = u.id
    WHERE (m.sender_id = ? OR m.receiver_id = ?) 
    AND u.role = 'buyer'
    GROUP BY conversation_with
    ORDER BY last_message_time DESC
";

$stmt = $conn->prepare($conversations_query);
$stmt->bind_param("iiiiiiiiiiiiiiiii", $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$conversations = [];

while ($row = $result->fetch_assoc()) {
    $conversation = $row;
    
    // Get related product info if available
    if (!empty($row['related_product_id'])) {
        $conversation['product'] = [
            'id' => $row['related_product_id'],
            'name' => $row['related_product_name']
        ];
    }
    
    $conversations[] = $conversation;
}

// Count total unread messages
$total_unread = array_sum(array_column($conversations, 'unread_count'));

// Return the data as JSON
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'conversations' => $conversations,
    'total_unread' => $total_unread
]); 