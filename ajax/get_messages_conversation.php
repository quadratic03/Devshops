<?php
// Only start the session if one doesn't already exist
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../includes/functions.php';
require_once '../config/database.php';

// Check if user is logged in
if (!is_logged_in()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$sender_id = $_SESSION['user_id'];
$receiver_id = isset($_GET['to']) ? (int)$_GET['to'] : 0;
$last_id = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;

// Validate input
if ($receiver_id <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid receiver ID']);
    exit();
}

// Get new messages
$new_messages_query = "SELECT m.*, 
                      u_sender.username as sender_name, 
                      u_receiver.username as receiver_name 
                      FROM messages m 
                      JOIN users u_sender ON m.sender_id = u_sender.id 
                      JOIN users u_receiver ON m.receiver_id = u_receiver.id 
                      WHERE ((m.sender_id = ? AND m.receiver_id = ?) 
                      OR (m.sender_id = ? AND m.receiver_id = ?))
                      AND m.id > ?
                      ORDER BY m.created_at ASC";
$stmt = $conn->prepare($new_messages_query);
$stmt->bind_param("iiiii", $sender_id, $receiver_id, $receiver_id, $sender_id, $last_id);
$stmt->execute();
$result = $stmt->get_result();
$messages = [];

while ($message = $result->fetch_assoc()) {
    $messages[] = $message;
}

// Mark any new messages as read
if (count($messages) > 0) {
    $mark_read_query = "UPDATE messages SET is_read = TRUE 
                      WHERE sender_id = ? AND receiver_id = ? AND is_read = FALSE";
    $stmt = $conn->prepare($mark_read_query);
    $stmt->bind_param("ii", $receiver_id, $sender_id);
    $stmt->execute();
}

// Return the data as JSON
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'messages' => $messages
]); 