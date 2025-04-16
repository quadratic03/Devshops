<?php
// Only start the session if one doesn't already exist
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/functions.php';
require_once '../config/database.php';

// Check if user is logged in and is a seller
if (!is_logged_in() || !is_seller()) {
    header("Location: ../login.php");
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
        (SELECT COUNT(*) FROM messages WHERE sender_id = conversation_with AND receiver_id = ? AND is_read = FALSE) as unread_count,
        (SELECT message FROM messages WHERE (sender_id = ? AND receiver_id = conversation_with) OR (sender_id = conversation_with AND receiver_id = ?) ORDER BY created_at DESC LIMIT 1) as last_message,
        (SELECT created_at FROM messages WHERE (sender_id = ? AND receiver_id = conversation_with) OR (sender_id = conversation_with AND receiver_id = ?) ORDER BY created_at DESC LIMIT 1) as last_message_time,
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
$stmt->bind_param("iiiiiiiiiiiii", $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id);
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

$page_title = "My Buyer Messages";
include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-lg-3">
            <?php include 'includes/sidebar.php'; ?>
        </div>
        <div class="col-lg-9">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-envelope me-2"></i> Buyer Messages</h5>
                    <?php $total_unread = array_sum(array_column($conversations, 'unread_count')); ?>
                    <?php if ($total_unread > 0): ?>
                    <span class="badge bg-light text-dark"><?php echo $total_unread; ?> unread messages</span>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if (count($conversations) > 0): ?>
                        <div class="list-group">
                            <?php foreach ($conversations as $conversation): ?>
                                <a href="../message.php?to=<?php echo $conversation['conversation_with']; ?><?php echo isset($conversation['product']) ? '&product=' . $conversation['product']['id'] : ''; ?>" 
                                   class="list-group-item list-group-item-action <?php echo $conversation['unread_count'] > 0 ? 'bg-light' : ''; ?>">
                                    <div class="d-flex w-100 justify-content-between align-items-center">
                                        <h6 class="mb-1">
                                            <?php echo htmlspecialchars($conversation['conversation_name']); ?>
                                            <span class="badge bg-info">Buyer</span>
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
                            <p>You don't have any messages from buyers yet.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<script>
// Function to fetch new message data
function updateMessages() {
    const xhr = new XMLHttpRequest();
    xhr.open('GET', 'ajax/get_messages.php', true);
    
    xhr.onload = function() {
        if (this.status === 200) {
            try {
                const response = JSON.parse(this.responseText);
                
                if (response.success) {
                    // Update the message list
                    const messagesContainer = document.querySelector('.card-body .list-group');
                    const noMessagesDiv = document.querySelector('.card-body .text-center');
                    
                    // If there are messages but container shows "no messages", replace it
                    if (response.conversations.length > 0 && noMessagesDiv) {
                        const newContainer = document.createElement('div');
                        newContainer.className = 'list-group';
                        document.querySelector('.card-body').innerHTML = '';
                        document.querySelector('.card-body').appendChild(newContainer);
                    }
                    
                    // Update unread count in header
                    const totalUnread = response.total_unread;
                    const unreadBadge = document.querySelector('.card-header .badge');
                    
                    if (totalUnread > 0) {
                        if (unreadBadge) {
                            unreadBadge.textContent = totalUnread + ' unread messages';
                        } else {
                            const newBadge = document.createElement('span');
                            newBadge.className = 'badge bg-light text-dark';
                            newBadge.textContent = totalUnread + ' unread messages';
                            document.querySelector('.card-header').appendChild(newBadge);
                        }
                    } else if (unreadBadge) {
                        unreadBadge.remove();
                    }
                    
                    // If we have messages and a container, update it
                    if (response.conversations.length > 0 && messagesContainer) {
                        // Store current first message ID to check if we need to scroll
                        messagesContainer.innerHTML = '';
                        
                        // Add each conversation
                        response.conversations.forEach(conv => {
                            const messageItem = document.createElement('a');
                            messageItem.className = `list-group-item list-group-item-action ${parseInt(conv.unread_count) > 0 ? 'bg-light' : ''}`;
                            messageItem.href = `../message.php?to=${conv.conversation_with}${conv.product ? '&product=' + conv.product.id : ''}`;
                            
                            const messageTime = new Date(conv.last_message_time);
                            const formattedTime = messageTime.toLocaleDateString('en-US', {
                                month: 'short', 
                                day: 'numeric',
                                hour: 'numeric',
                                minute: 'numeric',
                                hour12: true
                            });
                            
                            messageItem.innerHTML = `
                                <div class="d-flex w-100 justify-content-between align-items-center">
                                    <h6 class="mb-1">
                                        ${escapeHtml(conv.conversation_name)}
                                        <span class="badge bg-info">Buyer</span>
                                        ${parseInt(conv.unread_count) > 0 ? `<span class="badge bg-danger ms-2">${conv.unread_count} new</span>` : ''}
                                    </h6>
                                    <small class="text-muted">${formattedTime}</small>
                                </div>
                                <p class="mb-1 text-truncate">${escapeHtml(conv.last_message)}</p>
                                ${conv.product ? `
                                <small class="text-primary">
                                    <i class="fas fa-tag me-1"></i> Product: ${escapeHtml(conv.product.name)}
                                </small>` : ''}
                            `;
                            
                            messagesContainer.appendChild(messageItem);
                        });
                    }
                }
            } catch (e) {
                console.error('Error parsing JSON:', e);
            }
        }
    };
    
    xhr.onerror = function() {
        console.error('Request error');
    };
    
    xhr.send();
}

// Helper function to escape HTML
function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

// Update messages every 5 seconds
setInterval(updateMessages, 5000);

// Also update on page load
document.addEventListener('DOMContentLoaded', updateMessages);
</script> 