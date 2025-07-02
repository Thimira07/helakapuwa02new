<?php
/**
 * Helakapuwa.com - Chat Message Send API
 * Handles sending chat messages between connected users
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Start session
session_start();

// Include database connection
require_once('../includes/db_connect.php');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false, 
        'message' => 'ප්‍රවේශ වී නැත. කරුණාකර ප්‍රවේශ වන්න.',
        'redirect' => 'login.html'
    ]);
    exit();
}

$current_user_id = $_SESSION['user_id'];

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false, 
        'message' => 'වැරදි request method.'
    ]);
    exit();
}

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);

// Validate required fields
$required_fields = ['receiver_id', 'message'];
foreach ($required_fields as $field) {
    if (!isset($input[$field])) {
        echo json_encode([
            'success' => false, 
            'message' => "අවශ්‍ය ක්ෂේත්‍රය සපයා නොමැත: {$field}"
        ]);
        exit();
    }
}

$receiver_id = (int)$input['receiver_id'];
$message = trim($input['message']);

// Validate inputs
if ($receiver_id <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'වැරදි receiver ID.'
    ]);
    exit();
}

if (empty($message)) {
    echo json_encode([
        'success' => false,
        'message' => 'හිස් message යවන්න බැහැ.'
    ]);
    exit();
}

// Prevent sending message to oneself
if ($receiver_id == $current_user_id) {
    echo json_encode([
        'success' => false,
        'message' => 'ඔබටම message යවන්න බැහැ.'
    ]);
    exit();
}

try {
    // Start transaction
    $conn->autocommit(false);
    
    // Check if receiver exists and is active
    $receiver = getUserInfo($receiver_id, $conn);
    if (!$receiver) {
        throw new Exception('Receiver user සොයා ගත නොහැක හෝ account එක active නැත.');
    }
    
    // Check connection status
    $connection_status = checkConnectionStatus($current_user_id, $receiver_id, $conn);
    if (!$connection_status['can_chat']) {
        throw new Exception($connection_status['message']);
    }
    
    // Validate message content
    $validation_result = validateMessage($message);
    if (!$validation_result['valid']) {
        throw new Exception($validation_result['message']);
    }
    
    // Check rate limiting
    $rate_limit_result = checkRateLimit($current_user_id, $conn);
    if (!$rate_limit_result['allowed']) {
        throw new Exception($rate_limit_result['message']);
    }
    
    // Clean and process message
    $clean_message = cleanMessage($message);
    
    // Insert message into database
    $chat_id = insertMessage($current_user_id, $receiver_id, $clean_message, $conn);
    
    // Create notification for receiver
    createMessageNotification($current_user_id, $receiver_id, $clean_message, $conn);
    
    // Update conversation activity
    updateConversationActivity($current_user_id, $receiver_id, $conn);
    
    // Log activity
    logUserActivity($current_user_id, 'message_sent', "Sent message to user {$receiver_id}", $conn);
    
    // Get sender info for response
    $sender = getUserInfo($current_user_id, $conn);
    
    // Commit transaction
    $conn->commit();
    
    // Prepare response
    $response = [
        'success' => true,
        'message' => 'Message සාර්ථකව යවන ලදි.',
        'chat_id' => $chat_id,
        'timestamp' => date('Y-m-d H:i:s'),
        'formatted_time' => 'Just now',
        'sender' => [
            'name' => $sender['first_name'],
            'avatar' => $sender['profile_pic'] ?: 'img/default-avatar.jpg'
        ],
        'receiver' => [
            'name' => $receiver['first_name'],
            'is_online' => isUserOnline($receiver_id, $conn)
        ]
    ];
    
    echo json_encode($response);

} catch (Exception $e) {
    // Rollback transaction
    $conn->rollback();
    
    // Log error
    error_log("Chat send error for user {$current_user_id}: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} finally {
    $conn->autocommit(true);
    $conn->close();
}

/**
 * Get user basic information
 */
function getUserInfo($user_id, $conn) {
    $stmt = $conn->prepare("
        SELECT user_id, first_name, last_name, profile_pic, last_login, status
        FROM users 
        WHERE user_id = ? AND status = 'Active'
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        return null;
    }
    
    $user = $result->fetch_assoc();
    $stmt->close();
    return $user;
}

/**
 * Check if users can chat (must have accepted connection)
 */
function checkConnectionStatus($sender_id, $receiver_id, $conn) {
    $stmt = $conn->prepare("
        SELECT COUNT(*) as connection_count
        FROM requests 
        WHERE ((sender_id = ? AND receiver_id = ?) OR 
               (sender_id = ? AND receiver_id = ?))
        AND status = 'Accepted'
    ");
    $stmt->bind_param("iiii", $sender_id, $receiver_id, $receiver_id, $sender_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    if ($row['connection_count'] === 0) {
        return [
            'can_chat' => false,
            'message' => 'Message යවන්න මුලින්ම connection request එක accept කරගන්න.'
        ];
    }
    
    return [
        'can_chat' => true,
        'message' => 'Chat permission ඇත.'
    ];
}

/**
 * Validate message content
 */
function validateMessage($message) {
    // Check message length
    if (strlen($message) > 1000) {
        return [
            'valid' => false,
            'message' => 'Message ඉතා දිගයි. අක්ෂර 1000 ට සීමා කරන්න.'
        ];
    }
    
    if (strlen($message) < 1) {
        return [
            'valid' => false,
            'message' => 'Message හිස් විය නොහැක.'
        ];
    }
    
    // Check for inappropriate content (basic implementation)
    $inappropriate_words = [
        'spam', 'scam', 'fraud', 'fake', 'cheat',
        // Add more words as needed
    ];
    
    $message_lower = strtolower($message);
    foreach ($inappropriate_words as $word) {
        if (strpos($message_lower, $word) !== false) {
            return [
                'valid' => false,
                'message' => 'Message එකේ නුසුදුසු content ඇත.'
            ];
        }
    }
    
    // Check for excessive repetition
    if (preg_match('/(.)\1{10,}/', $message)) {
        return [
            'valid' => false,
            'message' => 'Message එකේ අනවශ්‍ය repetition ඇත.'
        ];
    }
    
    // Check for URLs/links (matrimonial safety)
    if (preg_match('/https?:\/\/|www\.|\.com|\.lk|\.org/', $message_lower)) {
        return [
            'valid' => false,
            'message' => 'Message එකේ links share කරන්න එපා. Safety සඳහායි.'
        ];
    }
    
    // Check for phone numbers
    if (preg_match('/\b(\+94|94|0)?[1-9][0-9]{8,9}\b/', $message)) {
        return [
            'valid' => false,
            'message' => 'Message එකේ phone numbers share කරන්න එපා. Platform එක ඇතුළේම chat කරන්න.'
        ];
    }
    
    // Check for email addresses
    if (preg_match('/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/', $message)) {
        return [
            'valid' => false,
            'message' => 'Message එකේ email addresses share කරන්න එපා.'
        ];
    }
    
    return [
        'valid' => true,
        'message' => 'Message valid.'
    ];
}

/**
 * Check rate limiting to prevent spam
 */
function checkRateLimit($user_id, $conn) {
    // Check messages sent in last minute
    $stmt = $conn->prepare("
        SELECT COUNT(*) as recent_messages
        FROM chats 
        WHERE sender_id = ? AND timestamp >= DATE_SUB(NOW(), INTERVAL 1 MINUTE)
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $recent_count = $row['recent_messages'];
    $stmt->close();
    
    if ($recent_count >= 20) { // Max 20 messages per minute
        return [
            'allowed' => false,
            'message' => 'ඔබ ඉතා ඉක්මනින් messages යවනවා. මිනිත්තුවකට පසු නැවත උත්සාහ කරන්න.'
        ];
    }
    
    // Check messages sent in last hour
    $stmt = $conn->prepare("
        SELECT COUNT(*) as hourly_messages
        FROM chats 
        WHERE sender_id = ? AND timestamp >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $hourly_count = $row['hourly_messages'];
    $stmt->close();
    
    if ($hourly_count >= 100) { // Max 100 messages per hour
        return [
            'allowed' => false,
            'message' => 'Hourly message limit එක ඉක්මවා ගොස් ඇත. පැයකට පසු නැවත උත්සාහ කරන්න.'
        ];
    }
    
    return [
        'allowed' => true,
        'message' => 'Rate limit OK.'
    ];
}

/**
 * Clean and sanitize message
 */
function cleanMessage($message) {
    // Remove excessive whitespace
    $message = preg_replace('/\s+/', ' ', $message);
    
    // Trim whitespace
    $message = trim($message);
    
    // Remove potentially harmful characters
    $message = strip_tags($message);
    
    // Convert special characters to HTML entities
    $message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    
    return $message;
}

/**
 * Insert message into database
 */
function insertMessage($sender_id, $receiver_id, $message, $conn) {
    $stmt = $conn->prepare("
        INSERT INTO chats (sender_id, receiver_id, message, timestamp, is_read) 
        VALUES (?, ?, ?, NOW(), 0)
    ");
    $stmt->bind_param("iis", $sender_id, $receiver_id, $message);
    
    if (!$stmt->execute()) {
        $stmt->close();
        throw new Exception('Message database එකට save කරන්න බැරි වුණා.');
    }
    
    $chat_id = $conn->insert_id;
    $stmt->close();
    
    return $chat_id;
}

/**
 * Create notification for message receiver
 */
function createMessageNotification($sender_id, $receiver_id, $message, $conn) {
    // Get sender name
    $stmt = $conn->prepare("SELECT first_name FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $sender_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $sender_name = $result->fetch_assoc()['first_name'] ?? 'Someone';
    $stmt->close();
    
    // Create notification
    $notification_message = "{$sender_name} ඔබට message එකක් යවා ඇත";
    
    $stmt = $conn->prepare("
        INSERT INTO notifications (user_id, type, message, related_user_id, is_read, created_at) 
        VALUES (?, 'message', ?, ?, 0, NOW())
    ");
    $stmt->bind_param("isi", $receiver_id, $notification_message, $sender_id);
    $stmt->execute();
    $stmt->close();
}

/**
 * Update conversation activity timestamp
 */
function updateConversationActivity($user1_id, $user2_id, $conn) {
    // Update or insert conversation activity record
    $stmt = $conn->prepare("
        INSERT INTO conversation_activity (user1_id, user2_id, last_activity) 
        VALUES (?, ?, NOW())
        ON DUPLICATE KEY UPDATE last_activity = NOW()
    ");
    $stmt->bind_param("ii", 
        min($user1_id, $user2_id), 
        max($user1_id, $user2_id)
    );
    $stmt->execute();
    $stmt->close();
}

/**
 * Check if user is currently online
 */
function isUserOnline($user_id, $conn) {
    $stmt = $conn->prepare("
        SELECT CASE 
                   WHEN last_login >= DATE_SUB(NOW(), INTERVAL 5 MINUTE) THEN 1
                   ELSE 0
               END as is_online
        FROM users 
        WHERE user_id = ?
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        return false;
    }
    
    $row = $result->fetch_assoc();
    $stmt->close();
    
    return (bool)$row['is_online'];
}

/**
 * Log user activity
 */
function logUserActivity($user_id, $activity_type, $description, $conn) {
    $stmt = $conn->prepare("
        INSERT INTO user_activity_log (user_id, activity_type, description, created_at) 
        VALUES (?, ?, ?, NOW())
    ");
    $stmt->bind_param("iss", $user_id, $activity_type, $description);
    $stmt->execute();
    $stmt->close();
}

/**
 * Get unread message count for user (bonus function)
 */
function getUnreadMessageCount($user_id, $conn) {
    $stmt = $conn->prepare("
        SELECT COUNT(*) as unread_count
        FROM chats 
        WHERE receiver_id = ? AND is_read = 0
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    return (int)$row['unread_count'];
}

/**
 * Block/Report inappropriate message (bonus function)
 */
function reportMessage($chat_id, $reporter_id, $reason, $conn) {
    $stmt = $conn->prepare("
        INSERT INTO message_reports (chat_id, reporter_id, reason, reported_at) 
        VALUES (?, ?, ?, NOW())
    ");
    $stmt->bind_param("iis", $chat_id, $reporter_id, $reason);
    $stmt->execute();
    $stmt->close();
    
    // You could also automatically hide the message or flag for review
}
?>
