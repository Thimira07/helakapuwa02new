<?php
/**
 * Helakapuwa.com - Send Friend Request Handler
 * Handles sending connection requests between users with package-based limits
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
if (!isset($input['receiver_id'])) {
    echo json_encode([
        'success' => false, 
        'message' => 'Receiver ID සපයා නොමැත.'
    ]);
    exit();
}

$receiver_id = (int)$input['receiver_id'];
$message = trim($input['message'] ?? ''); // Optional message with request

// Validate inputs
if ($receiver_id <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'වැරදි receiver ID.'
    ]);
    exit();
}

// Prevent sending request to oneself
if ($receiver_id == $current_user_id) {
    echo json_encode([
        'success' => false,
        'message' => 'ඔබටම request යවන්න බැහැ.'
    ]);
    exit();
}

// Validate optional message length
if (!empty($message) && strlen($message) > 200) {
    echo json_encode([
        'success' => false,
        'message' => 'Request message අක්ෂර 200 ට සීමා කරන්න.'
    ]);
    exit();
}

try {
    // Start transaction
    $conn->autocommit(false);
    
    // Get current user details and package info
    $sender = getUserWithPackageInfo($current_user_id, $conn);
    if (!$sender) {
        throw new Exception('Sender user සොයා ගත නොහැක.');
    }
    
    // Check if sender's account is active
    if ($sender['status'] !== 'Active') {
        throw new Exception('ඔබේ account එක active නැත. Support team එක සම්බන්ධ කරන්න.');
    }
    
    // Get receiver details
    $receiver = getUserBasicInfo($receiver_id, $conn);
    if (!$receiver) {
        throw new Exception('Request ගන්නා user සොයා ගත නොහැක හෝ account එක active නැත.');
    }
    
    // Check package expiry
    if (!isPackageActive($sender, $conn)) {
        throw new Exception('ඔබේ package එක expire වී ඇත. නව package එකක් subscribe කරන්න.');
    }
    
    // Check request limits based on package
    $limit_check = checkRequestLimits($sender, $conn);
    if (!$limit_check['allowed']) {
        throw new Exception($limit_check['message']);
    }
    
    // Check if request already exists
    $existing_request = checkExistingRequest($current_user_id, $receiver_id, $conn);
    if ($existing_request['exists']) {
        throw new Exception($existing_request['message']);
    }
    
    // Check if users are already connected
    if (areUsersConnected($current_user_id, $receiver_id, $conn)) {
        throw new Exception('ඔබ දැනටමත් මෙම user සමඟ connected වී ඇත.');
    }
    
    // Validate receiver's profile visibility and preferences
    $visibility_check = checkProfileVisibility($sender, $receiver, $conn);
    if (!$visibility_check['can_send']) {
        throw new Exception($visibility_check['message']);
    }
    
    // Clean message if provided
    $clean_message = !empty($message) ? cleanRequestMessage($message) : '';
    
    // Send the request
    $request_id = createFriendRequest($current_user_id, $receiver_id, $clean_message, $conn);
    
    // Update sender's request count
    updateRequestCount($current_user_id, $conn);
    
    // Create notification for receiver
    createRequestNotification($current_user_id, $receiver_id, $conn);
    
    // Log activity
    logUserActivity($current_user_id, 'request_sent', "Sent connection request to user {$receiver_id}", $conn);
    
    // Send email notification to receiver (if enabled in their settings)
    sendRequestEmailNotification($sender, $receiver, $clean_message);
    
    // Commit transaction
    $conn->commit();
    
    // Get updated request count for response
    $remaining_requests = $sender['requests_remaining'] - 1;
    
    echo json_encode([
        'success' => true,
        'message' => "{$receiver['first_name']} ට connection request යවන ලදි!",
        'request_id' => $request_id,
        'receiver_info' => [
            'name' => $receiver['first_name'] . ' ' . $receiver['last_name'],
            'profile_pic' => $receiver['profile_pic'] ?: 'img/default-avatar.jpg',
            'city' => $receiver['city']
        ],
        'remaining_requests' => $remaining_requests,
        'package_info' => [
            'name' => getPackageName($sender['package_id']),
            'monthly_limit' => getPackageRequestLimit($sender['package_id'])
        ]
    ]);

} catch (Exception $e) {
    // Rollback transaction
    $conn->rollback();
    
    // Log error
    error_log("Send request error for user {$current_user_id}: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} finally {
    $conn->autocommit(true);
    $conn->close();
}

/**
 * Get user with package information
 */
function getUserWithPackageInfo($user_id, $conn) {
    $stmt = $conn->prepare("
        SELECT u.user_id, u.first_name, u.last_name, u.email, u.profile_pic, 
               u.package_id, u.package_expires_at, u.requests_remaining, u.status,
               u.city, u.profession, u.religion, u.gender,
               p.package_name, p.max_requests, p.duration_days
        FROM users u
        LEFT JOIN packages p ON u.package_id = p.package_id
        WHERE u.user_id = ?
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
 * Get basic user information
 */
function getUserBasicInfo($user_id, $conn) {
    $stmt = $conn->prepare("
        SELECT user_id, first_name, last_name, profile_pic, city, profession, 
               religion, gender, status, email_notifications
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
 * Check if package is active
 */
function isPackageActive($user, $conn) {
    // Free package (ID 1) is always active
    if ($user['package_id'] == 1) {
        return true;
    }
    
    // Check if package has expired
    if (!empty($user['package_expires_at'])) {
        $expires_at = new DateTime($user['package_expires_at']);
        $now = new DateTime();
        return $expires_at > $now;
    }
    
    return false;
}

/**
 * Check request limits based on package
 */
function checkRequestLimits($user, $conn) {
    // Check if user has remaining requests
    if ($user['requests_remaining'] <= 0) {
        $package_name = getPackageName($user['package_id']);
        return [
            'allowed' => false,
            'message' => "ඔබේ {$package_name} package එකේ request limit එක ඉක්මවා ගොස් ඇත. Higher package එකක් upgrade කරන්න."
        ];
    }
    
    // Check daily limit (additional safety measure)
    $stmt = $conn->prepare("
        SELECT COUNT(*) as daily_requests
        FROM requests 
        WHERE sender_id = ? AND DATE(requested_at) = CURDATE()
    ");
    $stmt->bind_param("i", $user['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $daily_count = $result->fetch_assoc()['daily_requests'];
    $stmt->close();
    
    // Daily limit based on package (prevent abuse)
    $daily_limits = [
        1 => 5,   // Free: 5 per day
        2 => 10,  // Silver: 10 per day  
        3 => 15,  // Gold: 15 per day
        4 => 25   // Premium: 25 per day
    ];
    
    $daily_limit = $daily_limits[$user['package_id']] ?? 5;
    
    if ($daily_count >= $daily_limit) {
        return [
            'allowed' => false,
            'message' => "දෛනික request limit එක ඉක්මවා ගොස් ඇත. හෙට නැවත උත්සාහ කරන්න."
        ];
    }
    
    return [
        'allowed' => true,
        'message' => 'Request limit OK.'
    ];
}

/**
 * Check for existing requests
 */
function checkExistingRequest($sender_id, $receiver_id, $conn) {
    $stmt = $conn->prepare("
        SELECT status, requested_at
        FROM requests 
        WHERE sender_id = ? AND receiver_id = ?
        ORDER BY requested_at DESC
        LIMIT 1
    ");
    $stmt->bind_param("ii", $sender_id, $receiver_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        return ['exists' => false];
    }
    
    $request = $result->fetch_assoc();
    $stmt->close();
    
    switch ($request['status']) {
        case 'Pending':
            return [
                'exists' => true,
                'message' => 'ඔබ දැනටමත් මෙම user ට request එකක් යවා ඇත. Response එක එනතුරු රැඳී සිටින්න.'
            ];
        case 'Accepted':
            return [
                'exists' => true,
                'message' => 'ඔබ දැනටමත් මෙම user සමඟ connected වී ඇත.'
            ];
        case 'Declined':
            // Allow sending new request after 7 days
            $declined_date = new DateTime($request['requested_at']);
            $now = new DateTime();
            $diff = $now->diff($declined_date);
            
            if ($diff->days < 7) {
                return [
                    'exists' => true,
                    'message' => 'මෙම user ඔබේ previous request එක decline කර ඇත. දින 7කට පසු නැවත උත්සාහ කරන්න.'
                ];
            }
            break;
    }
    
    return ['exists' => false];
}

/**
 * Check if users are already connected
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
 * Check profile visibility and matching criteria
 */
function checkProfileVisibility($sender, $receiver, $conn) {
    // Check if receiver has privacy settings that prevent requests
    $stmt = $conn->prepare("
        SELECT profile_visibility, receive_requests_from
        FROM user_privacy_settings 
        WHERE user_id = ?
    ");
    $stmt->bind_param("i", $receiver['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $privacy = $result->fetch_assoc();
        
        // Check profile visibility
        if ($privacy['profile_visibility'] === 'premium_only' && $sender['package_id'] < 4) {
            $stmt->close();
            return [
                'can_send' => false,
                'message' => 'මෙම user premium members ගෙන් පමණක් requests receive කරයි. Premium package එකක් ගන්න.'
            ];
        }
        
        // Check request receiving preferences
        if ($privacy['receive_requests_from'] === 'none') {
            $stmt->close();
            return [
                'can_send' => false,
                'message' => 'මෙම user දැනට requests receive කරන්නේ නැත.'
            ];
        }
    }
    $stmt->close();
    
    // Check basic compatibility (gender preferences)
    if ($sender['gender'] === $receiver['gender']) {
        return [
            'can_send' => false,
            'message' => 'Same gender users ට requests යවන්න බැහැ.'
        ];
    }
    
    return [
        'can_send' => true,
        'message' => 'Profile visibility OK.'
    ];
}

/**
 * Clean request message
 */
function cleanRequestMessage($message) {
    // Remove excessive whitespace
    $message = preg_replace('/\s+/', ' ', $message);
    
    // Trim and sanitize
    $message = trim($message);
    $message = strip_tags($message);
    $message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    
    // Remove URLs and contact info for safety
    $message = preg_replace('/https?:\/\/[^\s]+/', '[URL removed]', $message);
    $message = preg_replace('/\b[0-9]{9,10}\b/', '[Phone removed]', $message);
    $message = preg_replace('/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/', '[Email removed]', $message);
    
    return $message;
}

/**
 * Create friend request record
 */
function createFriendRequest($sender_id, $receiver_id, $message, $conn) {
    $stmt = $conn->prepare("
        INSERT INTO requests (sender_id, receiver_id, status, requested_at, message) 
        VALUES (?, ?, 'Pending', NOW(), ?)
    ");
    $stmt->bind_param("iis", $sender_id, $receiver_id, $message);
    
    if (!$stmt->execute()) {
        $stmt->close();
        throw new Exception('Request database එකට save කරන්න බැරි වුණා.');
    }
    
    $request_id = $conn->insert_id;
    $stmt->close();
    
    return $request_id;
}

/**
 * Update user's request count
 */
function updateRequestCount($user_id, $conn) {
    $stmt = $conn->prepare("
        UPDATE users 
        SET requests_remaining = requests_remaining - 1 
        WHERE user_id = ? AND requests_remaining > 0
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
}

/**
 * Create notification for receiver
 */
function createRequestNotification($sender_id, $receiver_id, $conn) {
    // Get sender name
    $stmt = $conn->prepare("SELECT first_name FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $sender_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $sender_name = $result->fetch_assoc()['first_name'] ?? 'Someone';
    $stmt->close();
    
    // Create notification
    $notification_message = "{$sender_name} ඔබට connection request එකක් යවා ඇත";
    
    $stmt = $conn->prepare("
        INSERT INTO notifications (user_id, type, message, related_user_id, is_read, created_at) 
        VALUES (?, 'friend_request', ?, ?, 0, NOW())
    ");
    $stmt->bind_param("isi", $receiver_id, $notification_message, $sender_id);
    $stmt->execute();
    $stmt->close();
}

/**
 * Send email notification to receiver
 */
function sendRequestEmailNotification($sender, $receiver, $message) {
    // Check if receiver wants email notifications
    if (!($receiver['email_notifications'] ?? true)) {
        return;
    }
    
    $to = $receiver['email'];
    $subject = "New Connection Request - Helakapuwa.com";
    
    $email_body = "
    Dear {$receiver['first_name']},
    
    You have received a new connection request on Helakapuwa.com!
    
    From: {$sender['first_name']} {$sender['last_name']}
    Profession: {$sender['profession']}
    Location: {$sender['city']}
    
    " . (!empty($message) ? "Message: \"{$message}\"\n\n" : "") . "
    
    To view their profile and respond to this request, please log in to your account:
    https://helakapuwa.com/member/dashboard.php
    
    Remember:
    • Review their profile carefully
    • Only accept if you're genuinely interested
    • Report any inappropriate behavior
    
    Happy matching!
    
    Helakapuwa.com Team
    ";
    
    $headers = "From: noreply@helakapuwa.com\r\n";
    $headers .= "Reply-To: support@helakapuwa.com\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();
    
    // Send email (use proper email service in production)
    mail($to, $subject, $email_body, $headers);
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
 * Get package name by ID
 */
function getPackageName($package_id) {
    $packages = [
        1 => 'Free',
        2 => 'Silver',
        3 => 'Gold', 
        4 => 'Premium'
    ];
    
    return $packages[$package_id] ?? 'Unknown';
}

/**
 * Get package request limit
 */
function getPackageRequestLimit($package_id) {
    $limits = [
        1 => 0,    // Free: No requests
        2 => 50,   // Silver: 50 requests/month
        3 => 100,  // Gold: 100 requests/month
        4 => 200   // Premium: 200 requests/month
    ];
    
    return $limits[$package_id] ?? 0;
}

/**
 * Get user's sent requests today (bonus function)
 */
function getTodayRequestCount($user_id, $conn) {
    $stmt = $conn->prepare("
        SELECT COUNT(*) as today_count
        FROM requests 
        WHERE sender_id = ? AND DATE(requested_at) = CURDATE()
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    return (int)$row['today_count'];
}

/**
 * Get request statistics for user (bonus function)
 */
function getRequestStatistics($user_id, $conn) {
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_sent,
            COUNT(CASE WHEN status = 'Accepted' THEN 1 END) as accepted,
            COUNT(CASE WHEN status = 'Declined' THEN 1 END) as declined,
            COUNT(CASE WHEN status = 'Pending' THEN 1 END) as pending
        FROM requests 
        WHERE sender_id = ?
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats = $result->fetch_assoc();
    $stmt->close();
    
    $acceptance_rate = $stats['total_sent'] > 0 ? 
        round(($stats['accepted'] / $stats['total_sent']) * 100, 1) : 0;
    
    return [
        'total_sent' => (int)$stats['total_sent'],
        'accepted' => (int)$stats['accepted'],
        'declined' => (int)$stats['declined'],
        'pending' => (int)$stats['pending'],
        'acceptance_rate' => $acceptance_rate
    ];
}
?>
