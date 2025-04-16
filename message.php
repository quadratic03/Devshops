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

$error = '';
$success = '';
$receiver_id = isset($_GET['to']) ? (int)$_GET['to'] : 0;
$product_id = isset($_GET['product']) ? (int)$_GET['product'] : 0;

// If no receiver_id, redirect to home
if ($receiver_id <= 0) {
    header("Location: index.php");
    exit();
}

// Get receiver information (the seller or buyer)
$receiver = get_user($receiver_id);
if (!$receiver) {
    header("Location: index.php");
    exit();
}

// Get product details if product_id is set
$product = null;
if ($product_id > 0) {
    $product = get_product($product_id);
}

// Get conversation history
$conversation_query = "SELECT m.*, 
                       u_sender.username as sender_name, 
                       u_receiver.username as receiver_name 
                       FROM messages m 
                       JOIN users u_sender ON m.sender_id = u_sender.id 
                       JOIN users u_receiver ON m.receiver_id = u_receiver.id 
                       WHERE (m.sender_id = ? AND m.receiver_id = ?) 
                       OR (m.sender_id = ? AND m.receiver_id = ?) 
                       ORDER BY m.created_at ASC";
$stmt = $conn->prepare($conversation_query);
$sender_id = $_SESSION['user_id'];
$stmt->bind_param("iiii", $sender_id, $receiver_id, $receiver_id, $sender_id);
$stmt->execute();
$messages_result = $stmt->get_result();
$messages = [];

while ($message = $messages_result->fetch_assoc()) {
    $messages[] = $message;
}

// Mark unread messages as read
if (count($messages) > 0) {
    $mark_read_query = "UPDATE messages SET is_read = TRUE 
                        WHERE sender_id = ? AND receiver_id = ? AND is_read = FALSE";
    $stmt = $conn->prepare($mark_read_query);
    $stmt->bind_param("ii", $receiver_id, $sender_id);
    $stmt->execute();
}

// Process new message
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['send_message'])) {
    $message_text = clean_input($_POST['message']);
    $is_ajax = isset($_POST['ajax']) && $_POST['ajax'] === 'true';
    
    if (empty($message_text)) {
        $error = "Message cannot be empty.";
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $error]);
            exit();
        }
    } else {
        // Insert the message
        $insert_query = "INSERT INTO messages (sender_id, receiver_id, message, is_read) 
                         VALUES (?, ?, ?, FALSE)";
        $stmt = $conn->prepare($insert_query);
        $stmt->bind_param("iis", $sender_id, $receiver_id, $message_text);
        
        if ($stmt->execute()) {
            $new_message_id = $conn->insert_id;
            $success = "Message sent successfully.";
            
            if ($is_ajax) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true, 
                    'message_id' => $new_message_id,
                    'message' => $message_text,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                exit();
            } else {
                // Redirect to prevent form resubmission for non-AJAX requests
                header("Location: message.php?to=" . $receiver_id . ($product_id > 0 ? "&product=" . $product_id : ""));
                exit();
            }
        } else {
            $error = "Error sending message: " . $conn->error;
            if ($is_ajax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $error]);
                exit();
            }
        }
    }
}

include 'includes/header.php';
?>

<div class="container mt-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php">Home</a></li>
            <?php if ($product): ?>
            <li class="breadcrumb-item"><a href="products.php">Products</a></li>
            <li class="breadcrumb-item"><a href="product.php?id=<?php echo $product_id; ?>"><?php echo htmlspecialchars($product['name']); ?></a></li>
            <?php endif; ?>
            <li class="breadcrumb-item"><a href="my_messages.php">My Messages</a></li>
            <li class="breadcrumb-item active" aria-current="page">Conversation with <?php echo htmlspecialchars($receiver['username']); ?></li>
        </ol>
    </nav>
    
    <?php if ($error): ?>
    <div class="alert alert-danger">
        <?php echo $error; ?>
    </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
    <div class="alert alert-success">
        <?php echo $success; ?>
    </div>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-comments me-2"></i> 
                        Conversation with <?php echo htmlspecialchars($receiver['username']); ?>
                    </h5>
                    <?php if ($product): ?>
                    <span class="badge bg-light text-dark">
                        Product: <?php echo htmlspecialchars($product['name']); ?>
                    </span>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <!-- Display product info if related to a product -->
                    <?php if ($product): ?>
                    <div class="product-info mb-4 p-3 bg-light rounded">
                        <div class="d-flex">
                            <div class="flex-shrink-0 me-3">
                                <?php if ($product['image']): ?>
                                <img src="uploads/products/<?php echo $product['image']; ?>" class="img-thumbnail" style="max-width: 100px;" alt="<?php echo htmlspecialchars($product['name']); ?>">
                                <?php else: ?>
                                <img src="assets/images/placeholder.png" class="img-thumbnail" style="max-width: 100px;" alt="No image available">
                                <?php endif; ?>
                            </div>
                            <div>
                                <h6><?php echo htmlspecialchars($product['name']); ?></h6>
                                <p class="text-primary mb-1"><?php echo format_currency($product['price']); ?></p>
                                <a href="product.php?id=<?php echo $product_id; ?>" class="btn btn-sm btn-outline-primary">View Product</a>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Display conversation -->
                    <div class="conversation mb-4" style="max-height: 400px; overflow-y: auto;">
                        <?php if (count($messages) > 0): ?>
                            <?php foreach ($messages as $msg): ?>
                                <?php $isCurrentUser = $msg['sender_id'] == $_SESSION['user_id']; ?>
                                <div class="message mb-3 <?php echo $isCurrentUser ? 'text-end' : ''; ?>" data-id="<?php echo $msg['id']; ?>">
                                    <div class="message-content d-inline-block p-3 rounded <?php echo $isCurrentUser ? 'bg-primary text-white' : 'bg-light'; ?>" style="max-width: 80%;">
                                        <div class="message-text">
                                            <?php echo nl2br(htmlspecialchars($msg['message'])); ?>
                                        </div>
                                        <div class="message-time small text-<?php echo $isCurrentUser ? 'light' : 'muted'; ?> mt-1">
                                            <?php echo date('M j, Y g:i A', strtotime($msg['created_at'])); ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center text-muted p-4">
                                <i class="fas fa-comments fa-2x mb-3"></i>
                                <p>No messages yet. Start a conversation!</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- New message form -->
                    <form method="post" action="message.php?to=<?php echo $receiver_id; ?><?php echo $product_id > 0 ? '&product=' . $product_id : ''; ?>">
                        <div class="mb-3">
                            <label for="message" class="form-label">Your Message</label>
                            <textarea class="form-control" id="message" name="message" rows="3" required></textarea>
                        </div>
                        <div class="d-flex justify-content-between">
                            <?php if ($product): ?>
                            <a href="product.php?id=<?php echo $product_id; ?>" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-2"></i> Back to Product
                            </a>
                            <?php else: ?>
                            <a href="my_messages.php" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-2"></i> Back to Messages
                            </a>
                            <?php endif; ?>
                            <button type="submit" name="send_message" class="btn btn-primary">
                                <i class="fas fa-paper-plane me-2"></i> Send Message
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-scroll to the bottom of the conversation
    scrollToBottom();
    
    // Set up AJAX form submission
    const messageForm = document.querySelector('form');
    const messageInput = document.getElementById('message');
    
    if (messageForm) {
        messageForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            if (messageInput.value.trim() === '') {
                return;
            }
            
            // Send message via AJAX
            const formData = new FormData(messageForm);
            formData.append('ajax', true);
            
            fetch(messageForm.getAttribute('action'), {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Add the new message to the conversation
                    addMessage({
                        message: messageInput.value,
                        is_current_user: true,
                        time: new Date().toISOString(),
                        id: data.message_id
                    });
                    
                    // Update lastMessageId with the new message id
                    window.lastMessageId = data.message_id;
                    
                    // Clear the input
                    messageInput.value = '';
                    
                    // Scroll to bottom
                    scrollToBottom();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
        });
    }
    
    // Start the interval to fetch new messages
    window.lastMessageId = getLastMessageId();
    setInterval(function() {
        checkForNewMessages(window.lastMessageId);
    }, 3000);
});

// Function to add a new message to the conversation
function addMessage(messageData) {
    const conversationDiv = document.querySelector('.conversation');
    const noMessagesDiv = conversationDiv.querySelector('.text-center.text-muted');
    
    // If there are no messages yet, clear the "no messages" placeholder
    if (noMessagesDiv) {
        conversationDiv.innerHTML = '';
    }
    
    // Check if message with this ID already exists (avoid duplicates)
    if (messageData.id) {
        const existingMessage = document.querySelector(`.message[data-id="${messageData.id}"]`);
        if (existingMessage) {
            return existingMessage; // Message already exists, don't add it again
        }
    }
    
    // Create message HTML
    const messageDiv = document.createElement('div');
    messageDiv.className = `message mb-3 ${messageData.is_current_user ? 'text-end' : ''}`;
    
    // Set message ID if provided
    if (messageData.id) {
        messageDiv.dataset.id = messageData.id;
    }
    
    const messageTime = new Date(messageData.time);
    const formattedTime = messageTime.toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: 'numeric',
        hour12: true
    });
    
    messageDiv.innerHTML = `
        <div class="message-content d-inline-block p-3 rounded ${messageData.is_current_user ? 'bg-primary text-white' : 'bg-light'}" style="max-width: 80%;">
            <div class="message-text">
                ${messageData.message.replace(/\n/g, '<br>')}
            </div>
            <div class="message-time small text-${messageData.is_current_user ? 'light' : 'muted'} mt-1">
                ${formattedTime}
            </div>
        </div>
    `;
    
    conversationDiv.appendChild(messageDiv);
    return messageDiv;
}

// Function to scroll to the bottom of the conversation
function scrollToBottom() {
    const conversationDiv = document.querySelector('.conversation');
    if (conversationDiv) {
        conversationDiv.scrollTop = conversationDiv.scrollHeight;
    }
}

// Function to get the ID of the last message
function getLastMessageId() {
    const messages = document.querySelectorAll('.message');
    if (messages.length > 0) {
        const lastMessage = messages[messages.length - 1];
        return lastMessage.dataset.id || '0';
    }
    return '0';
}

// Function to check for new messages
function checkForNewMessages(lastId) {
    const receiver_id = <?php echo $receiver_id; ?>;
    const product_id = <?php echo $product_id ?: 0; ?>;
    
    fetch(`ajax/get_messages_conversation.php?to=${receiver_id}&last_id=${lastId}${product_id ? '&product=' + product_id : ''}`)
    .then(response => response.json())
    .then(data => {
        if (data.success && data.messages.length > 0) {
            data.messages.forEach(msg => {
                addMessage({
                    message: msg.message,
                    is_current_user: msg.sender_id == <?php echo $_SESSION['user_id']; ?>,
                    time: msg.created_at,
                    id: msg.id
                });
            });
            
            // Update the last message ID
            const lastMessage = data.messages[data.messages.length - 1];
            window.lastMessageId = lastMessage.id; // Update the global variable
            
            // Scroll to bottom if the user is already at the bottom
            const conversationDiv = document.querySelector('.conversation');
            if (conversationDiv.scrollHeight - conversationDiv.scrollTop <= conversationDiv.clientHeight + 100) {
                scrollToBottom();
            }
        }
    })
    .catch(error => {
        console.error('Error:', error);
    });
}
</script>

<?php include 'includes/footer.php'; ?> 