<?php
/**
 * Helakapuwa.com - Chat Messages Retrieval API
 * Retrieves chat messages between two connected users
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
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

// Get request parameters
$other_user_id = 0;
$last_message_id = 0;
$limit = 50; // Default message limit
$offset = 0;

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $other_user_id = (int)($_GET['user_id'] ?? 0);
    $last_message_id = (int)($_GET['last_message_id'] ?? 0);
    $limit = min((int)($_GET['limit'] ?? 50), 100); // Max 100 messages
    $offset = (int)($_GET['offset'] ?? 0);
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $other_user_id = (int)($input['user_id'] ?? 0);
    $last_message_id = (int)($input['last_message_id'] ?? 0);
    $limit = min((int)($input['limit'] ?? 50), 100);
    $offset = (int)($input['offset'] ?? 0);
}

// Validate other user ID
if ($other_user_id <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'වැරදි user ID.'
    ]);
    exit();
}

// Prevent chatting with oneself
if ($other_user_id == $current_user_id) {
    echo json_encode([
        'success' => false,
        'message' => 'ඔබටම message යවන්න බැහැ.'
    ]);
    exit();
}

try {
    // Check if users have accepted connection
    $connection_status = checkConnectionStatus($current_user_id, $other_user_id, $conn);
    
    if (!$connection_status['can_chat']) {
        echo json_encode([
            'success' => false,
            'message' => $connection_status['message'],
            'connection_required' => true
        ]);
        exit();
    }
    
    // Get other user basic info
    $other_user = getUserBasicInfo($other_user_id, $conn);
    if (!$other_user) {
        echo json_encode([
            'success' => false,
            'message' => 'User සොයා ගත නොහැක.'
        ]);
        exit();
    }
    
    // Get chat messages
    $messages = getChatMessages($current_user_id, $other_user_id, $last_message_id, $limit, $offset, $conn);
    
    // Mark messages as read
    markMessagesAsRead($current_user_id, $other_user_id, $conn);
    
    // Get conversation stats
    $conversation_stats = getConversationStats($current_user_id, $other_user_id, $conn);
    
    // Check if other user is online (simplified check based on last activity)
    $is_online = isUserOnline($other_user_id, $conn);
    
    echo json_encode([
        'success' => true,
        'messages' => $messages,
        'other_user' => $other_user,
        'conversation_stats' => $conversation_stats,
        'is_online' => $is_online,
        'pagination' => [
            'current_offset' => $offset,
            'limit' => $limit,
            'has_more' => count($messages) == $limit
        ]
    ]);

} catch (Exception $e) {
    // Log error
    error_log("Chat get error for user {$current_user_id}: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Chat messages load කිරීමේදී දෝෂයක් ඇතිවිය.'
    ]);
} finally {
    $conn->close();
}

/**
 * Check if users can chat (must have accepted connection)
 */
function checkConnectionStatus($user1_id, $user2_id, $conn) {
    $stmt = $conn->prepare("
        SELECT r1.status as request1_status, r2.status as request2_status,
               r1.sender_id as request1_sender, r2.sender_id as request2_sender
        FROM requests r1
        LEFT JOIN requests r2 ON (
            (r2.sender_id = ? AND r2.receiver_id = ?) OR 
            (r2.sender_id = ? AND r2.receiver_id = ?)
        )
        WHERE (r1.sender_id = ? AND r1.receiver_id = ?) OR 
              (r1.sender_id = ? AND r1.receiver_id = ?)
    ");
    
    $stmt->bind_param("iiiiiiii", 
        $user2_id, $user1_id, $user1_id, $user2_id,
        $user1_id, $user2_id, $user2_id, $user1_id
    );
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        return [
            'can_chat' => false,
            'message' => 'මුලින්ම connection request එකක් යවන්න.'
        ];
    }
    
    $row = $result->fetch_assoc();
    $stmt->close();
    
    // Check if any request is accepted
    if ($row['request1_status'] === 'Accepted' || $row['request2_status'] === 'Accepted') {
        return [
            'can_chat' => true,
            'message' => 'Chat permission ඇත.'
        ];
    }
    
    return [
        'can_chat' => false,
        'message' => 'Connection request accept කරන තුරු chat කරන්න බැහැ.'
    ];
}

/**
 * Get user basic information for chat
 */
function getUserBasicInfo($user_id, $conn) {
    $stmt = $conn->prepare("
        SELECT user_id, first_name, last_name, profile_pic, city, last_login,
               CASE 
                   WHEN last_login >= DATE_SUB(NOW(), INTERVAL 5 MINUTE) THEN 'online'
                   WHEN last_login >= DATE_SUB(NOW(), INTERVAL 1 HOUR) THEN 'recently_active'
                   ELSE 'offline'
               END as online_status
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
    
    // Format last seen
    if ($user['online_status'] === 'online') {
        $user['last_seen'] = 'දැන් සම්බන්ධයි';
    } elseif ($user['online_status'] === 'recently_active') {
        $user['last_seen'] = 'මෙරටට සම්බන්ධ වුණා';
    } else {
        $last_login = new DateTime($user['last_login']);
        $now = new DateTime();
        $diff = $now->diff($last_login);
        
        if ($diff->days > 0) {
            $user['last_seen'] = $diff->days . ' දින කට පෙර';
        } elseif ($diff->h > 0) {
            $user['last_seen'] = $diff->h . ' පැය කට පෙර';
        } else {
            $user['last_seen'] = $diff->i . ' මිනිත්තු කට පෙර';
        }
    }
    
    return $user;
}

/**
 * Get chat messages between two users
 */
function getChatMessages($user1_id, $user2_id, $last_message_id, $limit, $offset, $conn) {
    $query = "
        SELECT c.chat_id, c.sender_id, c.receiver_id, c.message, c.timestamp, c.is_read,
               u.first_name, u.profile_pic
        FROM chats c
        JOIN users u ON c.sender_id = u.user_id
        WHERE ((c.sender_id = ? AND c.receiver_id = ?) OR 
               (c.sender_id = ? AND c.receiver_id = ?))
    ";
    
    $params = [$user1_id, $user2_id, $user2_id, $user1_id];
    $param_types = "iiii";
    
    // Add condition for loading newer messages (if last_message_id is provided)
    if ($last_message_id > 0) {
        $query .= " AND c.chat_id > ?";
        $params[] = $last_message_id;
        $param_types .= "i";
        $query .= " ORDER BY c.timestamp ASC";
    } else {
        // Regular pagination - get older messages
        $query .= " ORDER BY c.timestamp DESC";
    }
    
    $query .= " LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    $param_types .= "ii";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param($param_types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $messages = [];
    while ($row = $result->fetch_assoc()) {
        // Format timestamp
        $message_time = new DateTime($row['timestamp']);
        $now = new DateTime();
        $diff = $now->diff($message_time);
        
        if ($diff->days > 0) {
            $formatted_time = $message_time->format('M j, Y g:i A');
        } elseif ($diff->h > 0) {
            $formatted_time = $diff->h . ' hours ago';
        } elseif ($diff->i > 0) {
            $formatted_time = $diff->i . ' minutes ago';
        } else {
            $formatted_time = 'Just now';
        }
        
        $messages[] = [
            'chat_id' => (int)$row['chat_id'],
            'sender_id' => (int)$row['sender_id'],
            'receiver_id' => (int)$row['receiver_id'],
            'message' => htmlspecialchars($row['message'], ENT_QUOTES, 'UTF-8'),
            'timestamp' => $row['timestamp'],
            'formatted_time' => $formatted_time,
            'is_read' => (bool)$row['is_read'],
            'sender_name' => htmlspecialchars($row['first_name'], ENT_QUOTES, 'UTF-8'),
            'sender_avatar' => $row['profile_pic'] ?: 'img/default-avatar.jpg',
            'is_own_message' => $row['sender_id'] == $user1_id
        ];
    }
    
    $stmt->close();
    
    // If we're not loading newer messages, reverse the array to show chronological order
    if ($last_message_id == 0) {
        $messages = array_reverse($messages);
    }
    
    return $messages;
}

/**
 * Mark messages as read
 */
function markMessagesAsRead($current_user_id, $other_user_id, $conn) {
    $stmt = $conn->prepare("
        UPDATE chats 
        SET is_read = 1 
        WHERE receiver_id = ? AND sender_id = ? AND is_read = 0
    ");
    $stmt->bind_param("ii", $current_user_id, $other_user_id);
    $stmt->execute();
    $stmt->close();
}

/**
 * Get conversation statistics
 */
function getConversationStats($user1_id, $user2_id, $conn) {
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_messages,
            COUNT(CASE WHEN sender_id = ? THEN 1 END) as sent_by_me,
            COUNT(CASE WHEN sender_id = ? THEN 1 END) as sent_by_them,
            COUNT(CASE WHEN receiver_id = ? AND is_read = 0 THEN 1 END) as unread_count,
            MIN(timestamp) as first_message_date,
            MAX(timestamp) as last_message_date
        FROM chats 
        WHERE (sender_id = ? AND receiver_id = ?) OR 
              (sender_id = ? AND receiver_id = ?)
    ");
    
    $stmt->bind_param("iiiiiii", 
        $user1_id, $user2_id, $user1_id,
        $user1_id, $user2_id, $user2_id, $user1_id
    );
    $stmt->execute();
    $result = $stmt->get_result();
    $stats = $result->fetch_assoc();
    $stmt->close();
    
    return [
        'total_messages' => (int)$stats['total_messages'],
        'sent_by_me' => (int)$stats['sent_by_me'],
        'sent_by_them' => (int)$stats['sent_by_them'],
        'unread_count' => (int)$stats['unread_count'],
        'first_message_date' => $stats['first_message_date'],
        'last_message_date' => $stats['last_message_date']
    ];
}

/**
 * Check if user is currently online
 */
function isUserOnline($user_id, $conn) {
    $stmt = $conn->prepare("
        SELECT last_login,
               CASE 
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
 * Get conversation list for a user (bonus function)
 */
function getConversationList($user_id, $conn) {
    $stmt = $conn->prepare("
        SELECT DISTINCT
            CASE 
                WHEN c.sender_id = ? THEN c.receiver_id 
                ELSE c.sender_id 
            END as other_user_id,
            u.first_name, u.last_name, u.profile_pic,
            latest.message as last_message,
            latest.timestamp as last_message_time,
            latest.sender_id as last_sender_id,
            unread.unread_count,
            CASE 
                WHEN u.last_login >= DATE_SUB(NOW(), INTERVAL 5 MINUTE) THEN 1
                ELSE 0
            END as is_online
        FROM chats c
        JOIN users u ON (
            CASE 
                WHEN c.sender_id = ? THEN c.receiver_id 
                ELSE c.sender_id 
            END = u.user_id
        )
        JOIN (
            SELECT 
                CASE 
                    WHEN sender_id = ? THEN receiver_id 
                    ELSE sender_id 
                END as other_user,
                message, timestamp, sender_id,
                ROW_NUMBER() OVER (
                    PARTITION BY 
                    CASE 
                        WHEN sender_id = ? THEN receiver_id 
                        ELSE sender_id 
                    END 
                    ORDER BY timestamp DESC
                ) as rn
            FROM chats
            WHERE sender_id = ? OR receiver_id = ?
        ) latest ON latest.other_user = CASE 
            WHEN c.sender_id = ? THEN c.receiver_id 
            ELSE c.sender_id 
        END AND latest.rn = 1
        LEFT JOIN (
            SELECT receiver_id, COUNT(*) as unread_count
            FROM chats 
            WHERE receiver_id = ? AND is_read = 0
            GROUP BY receiver_id, sender_id
        ) unread ON unread.receiver_id = ?
        WHERE c.sender_id = ? OR c.receiver_id = ?
        ORDER BY latest.timestamp DESC
    ");
    
    $stmt->bind_param("iiiiiiiiiiii", 
        $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, 
        $user_id, $user_id, $user_id, $user_id, $user_id
    );
    $stmt->execute();
    $result = $stmt->get_result();
    
    $conversations = [];
    while ($row = $result->fetch_assoc()) {
        $conversations[] = [
            'user_id' => (int)$row['other_user_id'],
            'name' => $row['first_name'] . ' ' . $row['last_name'],
            'avatar' => $row['profile_pic'] ?: 'img/default-avatar.jpg',
            'last_message' => htmlspecialchars($row['last_message'], ENT_QUOTES, 'UTF-8'),
            'last_message_time' => $row['last_message_time'],
            'is_last_message_mine' => $row['last_sender_id'] == $user_id,
            'unread_count' => (int)($row['unread_count'] ?? 0),
            'is_online' => (bool)$row['is_online']
        ];
    }
    
    $stmt->close();
    return $conversations;
}
?>
