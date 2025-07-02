<?php
/**
 * Helakapuwa.com - Accept Friend Request Handler
 * Handles accepting connection requests between users
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
if (!isset($input['request_id']) || !isset($input['action'])) {
    echo json_encode([
        'success' => false, 
        'message' => 'අවශ්‍ය ක්ෂේත්‍ර සපයා නොමැත.'
    ]);
    exit();
}

$request_id = (int)$input['request_id'];
$action = $input['action']; // 'accept' or 'decline'

// Validate inputs
if ($request_id <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'වැරදි request ID.'
    ]);
    exit();
}

if (!in_array($action, ['accept', 'decline'])) {
    echo json_encode([
        'success' => false,
        'message' => 'වැරදි action. accept හෝ decline විය යුතුයි.'
    ]);
    exit();
}

try {
    // Start transaction
    $conn->autocommit(false);
    
    // Get request details and validate
    $request = getRequestDetails($request_id, $current_user_id, $conn);
    if (!$request) {
        throw new Exception('Request සොයා ගත නොහැක හෝ ඔබට access නැත.');
    }
    
    // Check if request is still pending
    if ($request['status'] !== 'Pending') {
        throw new Exception('මෙම request එක දැනටමත් ' . 
                          ($request['status'] === 'Accepted' ? 'accept' : 'decline') . 
                          ' කර ඇත.');
    }
    
    // Get sender details
    $sender = getUserDetails($request['sender_id'], $conn);
    if (!$sender) {
        throw new Exception('Request sender සොයා ගත නොහැක.');
    }
    
    // Get current user details
    $receiver = getUserDetails($current_user_id, $conn);
    
    // Process the action
    if ($action === 'accept') {
        $result = acceptRequest($request, $sender, $receiver, $conn);
    } else {
        $result = declineRequest($request, $sender, $receiver, $conn);
    }
    
    if (!$result['success']) {
        throw new Exception($result['message']);
    }
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => $result['message'],
        'action' => $action,
        'sender_info' => [
            'name' => $sender['first_name'] . ' ' . $sender['last_name'],
            'profile_pic' => $sender['profile_pic'] ?: 'img/default-avatar.jpg',
            'profession' => $sender['profession'],
            'city' => $sender['city']
        ],
        'can_chat' => $action === 'accept',
        'connection_count' => $action === 'accept' ? getConnectionCount($current_user_id, $conn) : null
    ]);

} catch (Exception $e) {
    // Rollback transaction
    $conn->rollback();
    
    // Log error
    error_log("Accept request error for user {$current_user_id}: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} finally {
    $conn->autocommit(true);
    $conn->close();
}

/**
 * Get request details and validate access
 */
function getRequestDetails($request_id, $receiver_id, $conn) {
    $stmt = $conn->prepare("
        SELECT request_id, sender_id, receiver_id, status, requested_at
        FROM requests 
        WHERE request_id = ? AND receiver_id = ?
    ");
    $stmt->bind_param("ii", $request_id, $receiver_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        return null;
    }
    
    $request = $result->fetch_assoc();
    $stmt->close();
    return $request;
}

/**
 * Get user details
 */
function getUserDetails($user_id, $conn) {
    $stmt = $conn->prepare("
        SELECT user_id, first_name, last_name, email, phone, profile_pic, 
               profession, city, religion, age, 
               YEAR(CURDATE()) - YEAR(dob) - (DATE_FORMAT(CURDATE(), '%m%d') < DATE_FORMAT(dob, '%m%d')) as age,
               package_id, status
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
 * Accept friend request
 */
function acceptRequest($request, $sender, $receiver, $conn) {
    // Update request status to accepted
    $stmt = $conn->prepare("
        UPDATE requests 
        SET status = 'Accepted', responded_at = NOW() 
        WHERE request_id = ?
    ");
    $stmt->bind_param("i", $request['request_id']);
    
    if (!$stmt->execute()) {
        $stmt->close();
        return ['success' => false, 'message' => 'Request update කරන්න බැරි වුණා.'];
    }
    $stmt->close();
    
    // Create mutual connection record (optional table for easier queries)
    createConnectionRecord($sender['user_id'], $receiver['user_id'], $conn);
    
    // Create notification for sender
    $notification_message = "{$receiver['first_name']} ඔබේ connection request එක accept කර ඇත! දැන් chat කරන්න පුළුවන්.";
    createNotification($sender['user_id'], 'request_accepted', $notification_message, $receiver['user_id'], $conn);
    
    // Log activity for both users
    logUserActivity($receiver['user_id'], 'request_accepted', "Accepted connection request from user {$sender['user_id']}", $conn);
    logUserActivity($sender['user_id'], 'request_status_change', "Connection request was accepted by user {$receiver['user_id']}", $conn);
    
    // Send email notification to sender (optional)
    sendAcceptanceEmail($sender, $receiver);
    
    return [
        'success' => true,
        'message' => "{$sender['first_name']} ගේ connection request එක accept කරන ලදි! දැන් chat කරන්න පුළුවන්."
    ];
}

/**
 * Decline friend request
 */
function declineRequest($request, $sender, $receiver, $conn) {
    // Update request status to declined
    $stmt = $conn->prepare("
        UPDATE requests 
        SET status = 'Declined', responded_at = NOW() 
        WHERE request_id = ?
    ");
    $stmt->bind_param("i", $request['request_id']);
    
    if (!$stmt->execute()) {
        $stmt->close();
        return ['success' => false, 'message' => 'Request update කරන්න බැරි වුණා.'];
    }
    $stmt->close();
    
    // Create notification for sender (optional - some sites don't notify declines)
    $notification_message = "ඔබේ connection request එකට response එකක් ලැබී ඇත.";
    createNotification($sender['user_id'], 'request_responded', $notification_message, $receiver['user_id'], $conn);
    
    // Log activity
    logUserActivity($receiver['user_id'], 'request_declined', "Declined connection request from user {$sender['user_id']}", $conn);
    logUserActivity($sender['user_id'], 'request_status_change', "Connection request was declined by user {$receiver['user_id']}", $conn);
    
    return [
        'success' => true,
        'message' => "{$sender['first_name']} ගේ connection request එක decline කරන ලදි."
    ];
}

/**
 * Create connection record for easier queries
 */
function createConnectionRecord($user1_id, $user2_id, $conn) {
    // Ensure user1_id is always smaller for consistency
    if ($user1_id > $user2_id) {
        $temp = $user1_id;
        $user1_id = $user2_id;
        $user2_id = $temp;
    }
    
    $stmt = $conn->prepare("
        INSERT IGNORE INTO user_connections (user1_id, user2_id, connected_at) 
        VALUES (?, ?, NOW())
    ");
    $stmt->bind_param("ii", $user1_id, $user2_id);
    $stmt->execute();
    $stmt->close();
}

/**
 * Create notification
 */
function createNotification($user_id, $type, $message, $related_user_id, $conn) {
    $stmt = $conn->prepare("
        INSERT INTO notifications (user_id, type, message, related_user_id, is_read, created_at) 
        VALUES (?, ?, ?, ?, 0, NOW())
    ");
    $stmt->bind_param("issi", $user_id, $type, $message, $related_user_id);
    $stmt->execute();
    $stmt->close();
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
 * Get connection count for user
 */
function getConnectionCount($user_id, $conn) {
    $stmt = $conn->prepare("
        SELECT COUNT(*) as connection_count
        FROM requests 
        WHERE (sender_id = ? OR receiver_id = ?) AND status = 'Accepted'
    ");
    $stmt->bind_param("ii", $user_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    return (int)$row['connection_count'];
}

/**
 * Send acceptance email notification
 */
function sendAcceptanceEmail($sender, $receiver) {
    $to = $sender['email'];
    $subject = "Connection Request Accepted - Helakapuwa.com";
    
    $message = "
    Dear {$sender['first_name']},
    
    Great news! {$receiver['first_name']} has accepted your connection request on Helakapuwa.com.
    
    You can now:
    • Start chatting with {$receiver['first_name']}
    • View their complete profile
    • Exchange contact information (if both agree)
    
    Remember to:
    • Be respectful and courteous
    • Follow our community guidelines
    • Report any inappropriate behavior
    
    Start your conversation now by visiting your connections page.
    
    Best wishes for your journey ahead!
    
    Helakapuwa.com Team
    ";
    
    $headers = "From: noreply@helakapuwa.com\r\n";
    $headers .= "Reply-To: support@helakapuwa.com\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();
    
    // Send email (in production, use a proper email service)
    mail($to, $subject, $message, $headers);
}

/**
 * Check if users are already connected (bonus function)
 */
function areUsersConnected($user1_id, $user2_id, $conn) {
    $stmt = $conn->prepare("
        SELECT COUNT(*) as connection_count
        FROM requests 
        WHERE ((sender_id = ? AND receiver_id = ?) OR 
               (sender_id = ? AND receiver_id = ?))
        AND status = 'Accepted'
    ");
    $stmt->bind_param("iiii", $user1_id, $user2_id, $user2_id, $user1_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    return $row['connection_count'] > 0;
}

/**
 * Get mutual connections (bonus function)
 */
function getMutualConnections($user1_id, $user2_id, $conn) {
    $stmt = $conn->prepare("
        SELECT u.user_id, u.first_name, u.last_name, u.profile_pic
        FROM users u
        WHERE u.user_id IN (
            SELECT CASE 
                WHEN r1.sender_id = ? THEN r1.receiver_id 
                ELSE r1.sender_id 
            END
            FROM requests r1
            WHERE (r1.sender_id = ? OR r1.receiver_id = ?) 
            AND r1.status = 'Accepted'
            AND CASE 
                WHEN r1.sender_id = ? THEN r1.receiver_id 
                ELSE r1.sender_id 
            END IN (
                SELECT CASE 
                    WHEN r2.sender_id = ? THEN r2.receiver_id 
                    ELSE r2.sender_id 
                END
                FROM requests r2
                WHERE (r2.sender_id = ? OR r2.receiver_id = ?) 
                AND r2.status = 'Accepted'
            )
        )
        AND u.status = 'Active'
        LIMIT 5
    ");
    $stmt->bind_param("iiiiiii", 
        $user1_id, $user1_id, $user1_id, $user1_id,
        $user2_id, $user2_id, $user2_id
    );
    $stmt->execute();
    $result = $stmt->get_result();
    
    $mutual_connections = [];
    while ($row = $result->fetch_assoc()) {
        $mutual_connections[] = [
            'user_id' => $row['user_id'],
            'name' => $row['first_name'] . ' ' . $row['last_name'],
            'profile_pic' => $row['profile_pic'] ?: 'img/default-avatar.jpg'
        ];
    }
    
    $stmt->close();
    return $mutual_connections;
}

/**
 * Bulk accept/decline requests (bonus function)
 */
function bulkProcessRequests($user_id, $request_ids, $action, $conn) {
    if (empty($request_ids) || !in_array($action, ['accept', 'decline'])) {
        return ['success' => false, 'message' => 'වැරදි parameters.'];
    }
    
    $status = $action === 'accept' ? 'Accepted' : 'Declined';
    $placeholders = str_repeat('?,', count($request_ids) - 1) . '?';
    
    $query = "
        UPDATE requests 
        SET status = ?, responded_at = NOW() 
        WHERE request_id IN ($placeholders) AND receiver_id = ? AND status = 'Pending'
    ";
    
    $stmt = $conn->prepare($query);
    $params = array_merge([$status], $request_ids, [$user_id]);
    $types = str_repeat('i', count($request_ids) + 1);
    $types = 's' . $types; // 's' for status, 'i' for IDs
    
    $stmt->bind_param($types, ...$params);
    $affected_rows = $stmt->execute() ? $stmt->affected_rows : 0;
    $stmt->close();
    
    return [
        'success' => $affected_rows > 0,
        'message' => "Requests {$action}ed: {$affected_rows}",
        'affected_count' => $affected_rows
    ];
}
?>
