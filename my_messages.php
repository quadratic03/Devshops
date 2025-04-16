<?php
// Only start the session if one doesn't already exist
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'includes/functions.php';
require_once 'config/database.php';

// Check if user is logged in
if (!is_logged_in()) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get all conversations for the user
$conversations_query = "
    SELECT 
        CASE 
            WHEN m.sender_id = ? THEN m.receiver_id
            ELSE m.sender_id
        END as conversation_with,
        u.username as conversation_name,
        u.role as conversation_role,
        (SELECT COUNT(*) FROM messages WHERE sender_id = conversation_with AND receiver_id = ? AND is_read = FALSE) as unread_count,
        (SELECT message FROM messages WHERE (sender_id = ? AND receiver_id = conversation_with) OR (sender_id = conversation_with AND receiver_id = ?) ORDER BY created_at DESC LIMIT 1) as last_message,
        (SELECT created_at FROM messages WHERE (sender_id = ? AND receiver_id = conversation_with) OR (sender_id = conversation_with AND receiver_id = ?) ORDER BY created_at DESC LIMIT 1) as last_message_time,
        (SELECT product_id FROM source_access_requests WHERE (seller_id = ? AND buyer_id = conversation_with) OR (seller_id = conversation_with AND buyer_id = ?) LIMIT 1) as related_product_id
    FROM messages m
    JOIN users u ON (CASE WHEN m.sender_id = ? THEN m.receiver_id ELSE m.sender_id END) = u.id
    WHERE m.sender_id = ? OR m.receiver_id = ?
    GROUP BY conversation_with
    ORDER BY last_message_time DESC
";

$stmt = $conn->prepare($conversations_query);
$stmt->bind_param("iiiiiiiii", $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$conversations = [];

while ($row = $result->fetch_assoc()) {
    // If there's a related product, get its details
    if ($row['related_product_id']) {
        $product_query = "SELECT id, name FROM products WHERE id = ?";
        $product_stmt = $conn->prepare($product_query);
        $product_stmt->bind_param("i", $row['related_product_id']);
        $product_stmt->execute();
        $product_result = $product_stmt->get_result();
        if ($product_result->num_rows > 0) {
            $row['product'] = $product_result->fetch_assoc();
        }
    }
    
    $conversations[] = $row;
}

// Get total unread messages
$unread_query = "SELECT COUNT(*) as total_unread FROM messages WHERE receiver_id = ? AND is_read = FALSE";
$stmt = $conn->prepare($unread_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$unread_result = $stmt->get_result();
$unread_data = $unread_result->fetch_assoc();
$total_unread = $unread_data['total_unread'];

include 'includes/header.php';
?>

<div class="container mt-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php">Home</a></li>
            <li class="breadcrumb-item active" aria-current="page">My Messages</li>
        </ol>
    </nav>
    
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-envelope me-2"></i> My Messages</h5>
                    <?php if ($total_unread > 0): ?>
                    <span class="badge bg-danger"><?php echo $total_unread; ?> unread</span>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if (count($conversations) > 0): ?>
                        <div class="list-group">
                            <?php foreach ($conversations as $conversation): ?>
                                <a href="message.php?to=<?php echo $conversation['conversation_with']; ?><?php echo isset($conversation['product']) ? '&product=' . $conversation['product']['id'] : ''; ?>" 
                                   class="list-group-item list-group-item-action <?php echo $conversation['unread_count'] > 0 ? 'bg-light' : ''; ?>">
                                    <div class="d-flex w-100 justify-content-between align-items-center">
                                        <h6 class="mb-1">
                                            <?php echo htmlspecialchars($conversation['conversation_name']); ?>
                                            <span class="badge bg-secondary"><?php echo ucfirst($conversation['conversation_role']); ?></span>
                                            <?php if ($conversation['unread_count'] > 0): ?>
                                            <span class="badge bg-danger ms-2"><?php echo $conversation['unread_count']; ?> new</span>
                                            <?php endif; ?>
                                        </h6>
                                        <small class="text-muted"><?php echo date('M j, g:i A', strtotime($conversation['last_message_time'])); ?></small>
                                    </div>
                                    <p class="mb-1 text-truncate"><?php echo htmlspecialchars($conversation['last_message']); ?></p>
                                    <?php if (isset($conversation['product'])): ?>
                                    <small class="text-primary">
                                        <i class="fas fa-tag me-1"></i> Product: <?php echo htmlspecialchars($conversation['product']['name']); ?>
                                    </small>
                                    <?php endif; ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center p-4">
                            <i class="far fa-envelope-open fa-3x mb-3 text-muted"></i>
                            <p>You don't have any messages yet.</p>
                            <p class="text-muted">When you message a seller or receive messages, they will appear here.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Back button -->
            <div class="d-grid">
                <a href="index.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i> Back to Home
                </a>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?> 