<?php
/**
 * Helakapuwa.com - Get Member Details API
 * Retrieves detailed member profile information with privacy controls
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

// Get member ID from request
$member_id = 0;

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $member_id = (int)($_GET['member_id'] ?? 0);
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $member_id = (int)($input['member_id'] ?? 0);
}

// Validate member ID
if ($member_id <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'වැරදි member ID.'
    ]);
    exit();
}

// Prevent viewing own profile through this API
if ($member_id == $current_user_id) {
    echo json_encode([
        'success' => false,
        'message' => 'ඔබේම profile බලන්න dashboard භාවිතා කරන්න.',
        'redirect' => 'member/dashboard.php'
    ]);
    exit();
}

try {
    // Get current user package info
    $current_user = getCurrentUserInfo($current_user_id, $conn);
    if (!$current_user) {
        throw new Exception('Current user details සොයා ගත නොහැක.');
    }
    
    // Get member basic info first
    $member = getMemberBasicInfo($member_id, $conn);
    if (!$member) {
        throw new Exception('Member සොයා ගත නොහැක හෝ account එක active නැත.');
    }
    
    // Check profile access permissions
    $access_check = checkProfileAccess($current_user, $member, $conn);
    if (!$access_check['allowed']) {
        echo json_encode([
            'success' => false,
            'message' => $access_check['message'],
            'access_level' => $access_check['level']
        ]);
        exit();
    }
    
    // Get detailed member information based on access level
    $member_details = getMemberDetailedInfo($member_id, $access_check['level'], $conn);
    
    // Get connection status
    $connection_status = getConnectionStatus($current_user_id, $member_id, $conn);
    
    // Get partner preferences if allowed
    $partner_preferences = null;
    if ($access_check['level'] >= 2) {
        $partner_preferences = getPartnerPreferences($member_id, $conn);
    }
    
    // Get profile photos if allowed
    $profile_photos = [];
    if ($access_check['level'] >= 1) {
        $profile_photos = getProfilePhotos($member_id, $conn);
    }
    
    // Get mutual connections if connected
    $mutual_connections = [];
    if ($connection_status['status'] === 'connected') {
        $mutual_connections = getMutualConnections($current_user_id, $member_id, $conn);
    }
    
    // Calculate compatibility score
    $compatibility_score = calculateCompatibilityScore($current_user, $member_details, $conn);
    
    // Record profile view activity
    recordProfileView($current_user_id, $member_id, $conn);
    
    // Prepare response
    $response = [
        'success' => true,
        'member' => $member_details,
        'access_level' => $access_check['level'],
        'connection_status' => $connection_status,
        'compatibility_score' => $compatibility_score,
        'can_send_request' => $connection_status['can_send_request'],
        'can_chat' => $connection_status['can_chat']
    ];
    
    // Add optional data based on access level
    if ($partner_preferences) {
        $response['partner_preferences'] = $partner_preferences;
    }
    
    if (!empty($profile_photos)) {
        $response['profile_photos'] = $profile_photos;
    }
    
    if (!empty($mutual_connections)) {
        $response['mutual_connections'] = $mutual_connections;
    }
    
    echo json_encode($response);

} catch (Exception $e) {
    // Log error
    error_log("Get member details error for user {$current_user_id}: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} finally {
    $conn->close();
}

/**
 * Get current user information
 */
function getCurrentUserInfo($user_id, $conn) {
    $stmt = $conn->prepare("
        SELECT user_id, first_name, last_name, package_id, gender, religion, caste,
               dob, profession, city, package_expires_at
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
 * Get member basic information
 */
function getMemberBasicInfo($member_id, $conn) {
    $stmt = $conn->prepare("
        SELECT user_id, first_name, last_name, status, gender, last_login
        FROM users 
        WHERE user_id = ?
    ");
    $stmt->bind_param("i", $member_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        return null;
    }
    
    $member = $result->fetch_assoc();
    $stmt->close();
    
    // Check if account is active
    if ($member['status'] !== 'Active') {
        return null;
    }
    
    return $member;
}

/**
 * Check profile access permissions
 */
function checkProfileAccess($current_user, $member, $conn) {
    // Get member's privacy settings
    $stmt = $conn->prepare("
        SELECT profile_visibility, show_phone, show_email
        FROM user_privacy_settings 
        WHERE user_id = ?
    ");
    $stmt->bind_param("i", $member['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $privacy_settings = null;
    if ($result->num_rows > 0) {
        $privacy_settings = $result->fetch_assoc();
    }
    $stmt->close();
    
    // Default privacy settings if none exist
    if (!$privacy_settings) {
        $privacy_settings = [
            'profile_visibility' => 'public',
            'show_phone' => false,
            'show_email' => false
        ];
    }
    
    // Check if current user's package has expired
    if ($current_user['package_id'] > 1) {
        if (!empty($current_user['package_expires_at'])) {
            $expires_at = new DateTime($current_user['package_expires_at']);
            $now = new DateTime();
            if ($expires_at <= $now) {
                return [
                    'allowed' => false,
                    'message' => 'ඔබේ package එක expire වී ඇත. නව package එකක් subscribe කරන්න.',
                    'level' => 0
                ];
            }
        }
    }
    
    // Determine access level based on privacy settings and user package
    $access_level = 0;
    
    switch ($privacy_settings['profile_visibility']) {
        case 'public':
            $access_level = 1; // Basic info
            break;
            
        case 'members_only':
            if ($current_user['package_id'] >= 1) {
                $access_level = 1;
            }
            break;
            
        case 'premium_only':
            if ($current_user['package_id'] >= 4) { // Premium package
                $access_level = 2; // Detailed info
            } else {
                return [
                    'allowed' => false,
                    'message' => 'මෙම profile බලන්න Premium package එකක් අවශ්‍යයි.',
                    'level' => 0
                ];
            }
            break;
    }
    
    // Check if users are connected (gives higher access)
    if (areUsersConnected($current_user['user_id'], $member['user_id'], $conn)) {
        $access_level = 3; // Full access
    }
    
    // Minimum access required
    if ($access_level === 0) {
        return [
            'allowed' => false,
            'message' => 'මෙම profile access කරන්න permission නැත.',
            'level' => 0
        ];
    }
    
    return [
        'allowed' => true,
        'message' => 'Profile access granted.',
        'level' => $access_level
    ];
}

/**
 * Get detailed member information based on access level
 */
function getMemberDetailedInfo($member_id, $access_level, $conn) {
    // Base query with basic info (access level 1)
    $select_fields = "
        u.user_id, u.first_name, u.last_name, u.profile_pic, u.city, u.profession,
        u.religion, u.education, u.about_me, u.last_login,
        YEAR(CURDATE()) - YEAR(u.dob) - (DATE_FORMAT(CURDATE(), '%m%d') < DATE_FORMAT(u.dob, '%m%d')) as age,
        CASE 
            WHEN u.last_login >= DATE_SUB(NOW(), INTERVAL 5 MINUTE) THEN 'online'
            WHEN u.last_login >= DATE_SUB(NOW(), INTERVAL 1 HOUR) THEN 'recently_active'
            ELSE 'offline'
        END as online_status
    ";
    
    // Add more fields based on access level
    if ($access_level >= 2) {
        $select_fields .= ", u.caste, u.marital_status, u.income_range, u.province";
    }
    
    if ($access_level >= 3) {
        // Connected users get contact info based on privacy settings
        $select_fields .= ", u.phone, u.email, u.address";
    }
    
    $stmt = $conn->prepare("
        SELECT {$select_fields}
        FROM users u
        WHERE u.user_id = ? AND u.status = 'Active'
    ");
    $stmt->bind_param("i", $member_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        return null;
    }
    
    $member = $result->fetch_assoc();
    $stmt->close();
    
    // Format last seen
    if ($member['online_status'] === 'online') {
        $member['last_seen'] = 'දැන් සම්බන්ධයි';
        $member['last_seen_color'] = 'green';
    } elseif ($member['online_status'] === 'recently_active') {
        $member['last_seen'] = 'මෙරටට සම්බන්ධ වුණා';
        $member['last_seen_color'] = 'yellow';
    } else {
        $last_login = new DateTime($member['last_login']);
        $now = new DateTime();
        $diff = $now->diff($last_login);
        
        if ($diff->days > 0) {
            $member['last_seen'] = $diff->days . ' දින කට පෙර';
        } elseif ($diff->h > 0) {
            $member['last_seen'] = $diff->h . ' පැය කට පෙර';
        } else {
            $member['last_seen'] = $diff->i . ' මිනිත්තු කට පෙර';
        }
        $member['last_seen_color'] = 'gray';
    }
    
    // Remove sensitive fields based on access level and privacy settings
    if ($access_level < 3) {
        unset($member['phone'], $member['email'], $member['address']);
    } else {
        // Even connected users need privacy settings check
        $privacy = getPrivacySettings($member_id, $conn);
        if (!($privacy['show_phone'] ?? false)) {
            unset($member['phone']);
        }
        if (!($privacy['show_email'] ?? false)) {
            unset($member['email']);
        }
    }
    
    return $member;
}

/**
 * Get connection status between users
 */
function getConnectionStatus($user1_id, $user2_id, $conn) {
    $stmt = $conn->prepare("
        SELECT r1.status as sent_status, r1.request_id as sent_request_id,
               r2.status as received_status, r2.request_id as received_request_id
        FROM (SELECT * FROM requests WHERE sender_id = ? AND receiver_id = ?) r1
        LEFT JOIN (SELECT * FROM requests WHERE sender_id = ? AND receiver_id = ?) r2 ON 1=1
    ");
    $stmt->bind_param("iiii", $user1_id, $user2_id, $user2_id, $user1_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $status = [
        'status' => 'none',
        'can_send_request' => true,
        'can_chat' => false,
        'request_id' => null,
        'message' => ''
    ];
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        
        // Check if connected (either direction accepted)
        if ($row['sent_status'] === 'Accepted' || $row['received_status'] === 'Accepted') {
            $status['status'] = 'connected';
            $status['can_send_request'] = false;
            $status['can_chat'] = true;
            $status['message'] = 'ඔබ connected වී ඇත. Chat කරන්න පුළුවන්.';
        }
        // Check if request pending (sent by current user)
        elseif ($row['sent_status'] === 'Pending') {
            $status['status'] = 'request_sent';
            $status['can_send_request'] = false;
            $status['can_chat'] = false;
            $status['request_id'] = $row['sent_request_id'];
            $status['message'] = 'ඔබේ request එක pending වෙලා ඇත.';
        }
        // Check if request received (sent by other user)
        elseif ($row['received_status'] === 'Pending') {
            $status['status'] = 'request_received';
            $status['can_send_request'] = false;
            $status['can_chat'] = false;
            $status['request_id'] = $row['received_request_id'];
            $status['message'] = 'ඔබට request එකක් ලැබී ඇත.';
        }
        // Check if request was declined
        elseif ($row['sent_status'] === 'Declined') {
            $status['status'] = 'request_declined';
            $status['can_send_request'] = false; // For now, prevent immediate re-send
            $status['can_chat'] = false;
            $status['message'] = 'ඔබේ request එක decline කර ඇත.';
        }
    }
    
    $stmt->close();
    return $status;
}

/**
 * Get partner preferences
 */
function getPartnerPreferences($member_id, $conn) {
    $stmt = $conn->prepare("
        SELECT min_age, max_age, pref_religion, pref_caste, pref_marital_status,
               pref_profession, pref_location, pref_education
        FROM partner_preferences 
        WHERE user_id = ?
    ");
    $stmt->bind_param("i", $member_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        return null;
    }
    
    $preferences = $result->fetch_assoc();
    $stmt->close();
    
    // Format preferences for display
    $formatted_preferences = [];
    
    if ($preferences['min_age'] && $preferences['max_age']) {
        $formatted_preferences['age_range'] = $preferences['min_age'] . ' - ' . $preferences['max_age'] . ' වසර';
    }
    
    if ($preferences['pref_religion']) {
        $formatted_preferences['religion'] = $preferences['pref_religion'];
    }
    
    if ($preferences['pref_caste']) {
        $formatted_preferences['caste'] = $preferences['pref_caste'];
    }
    
    if ($preferences['pref_education']) {
        $formatted_preferences['education'] = $preferences['pref_education'];
    }
    
    if ($preferences['pref_location']) {
        $formatted_preferences['location'] = $preferences['pref_location'];
    }
    
    return $formatted_preferences;
}

/**
 * Get profile photos
 */
function getProfilePhotos($member_id, $conn) {
    // For now, just return the main profile picture
    // In future, you could have a separate profile_photos table
    $stmt = $conn->prepare("
        SELECT profile_pic
        FROM users 
        WHERE user_id = ? AND profile_pic IS NOT NULL AND profile_pic != ''
    ");
    $stmt->bind_param("i", $member_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $photos = [];
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        if (!empty($row['profile_pic'])) {
            $photos[] = [
                'url' => $row['profile_pic'],
                'is_primary' => true,
                'alt' => 'Profile Picture'
            ];
        }
    }
    
    $stmt->close();
    return $photos;
}

/**
 * Get mutual connections
 */
function getMutualConnections($user1_id, $user2_id, $conn) {
    $stmt = $conn->prepare("
        SELECT DISTINCT u.user_id, u.first_name, u.last_name, u.profile_pic
        FROM users u
        INNER JOIN requests r1 ON (
            (r1.sender_id = u.user_id AND r1.receiver_id = ?) OR
            (r1.receiver_id = u.user_id AND r1.sender_id = ?)
        )
        INNER JOIN requests r2 ON (
            (r2.sender_id = u.user_id AND r2.receiver_id = ?) OR
            (r2.receiver_id = u.user_id AND r2.sender_id = ?)
        )
        WHERE r1.status = 'Accepted' AND r2.status = 'Accepted'
        AND u.user_id NOT IN (?, ?)
        AND u.status = 'Active'
        LIMIT 5
    ");
    $stmt->bind_param("iiiiii", $user1_id, $user1_id, $user2_id, $user2_id, $user1_id, $user2_id);
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
 * Calculate compatibility score
 */
function calculateCompatibilityScore($current_user, $member, $conn) {
    $score = 0;
    $total_factors = 0;
    
    // Get current user's partner preferences
    $stmt = $conn->prepare("
        SELECT min_age, max_age, pref_religion, pref_caste, pref_education, pref_location
        FROM partner_preferences 
        WHERE user_id = ?
    ");
    $stmt->bind_param("i", $current_user['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        return ['score' => 0, 'factors' => []];
    }
    
    $preferences = $result->fetch_assoc();
    $stmt->close();
    
    $matching_factors = [];
    
    // Age compatibility
    if ($preferences['min_age'] && $preferences['max_age'] && isset($member['age'])) {
        $total_factors++;
        if ($member['age'] >= $preferences['min_age'] && $member['age'] <= $preferences['max_age']) {
            $score += 20;
            $matching_factors[] = 'Age Range';
        }
    }
    
    // Religion compatibility
    if ($preferences['pref_religion'] && isset($member['religion'])) {
        $total_factors++;
        if ($preferences['pref_religion'] === $member['religion']) {
            $score += 25;
            $matching_factors[] = 'Religion';
        }
    }
    
    // Education compatibility
    if ($preferences['pref_education'] && isset($member['education'])) {
        $total_factors++;
        if ($preferences['pref_education'] === $member['education']) {
            $score += 20;
            $matching_factors[] = 'Education';
        }
    }
    
    // Location compatibility
    if ($preferences['pref_location'] && isset($member['city'])) {
        $total_factors++;
        if (stripos($member['city'], $preferences['pref_location']) !== false) {
            $score += 15;
            $matching_factors[] = 'Location';
        }
    }
    
    // Caste compatibility (if specified)
    if ($preferences['pref_caste'] && isset($member['caste'])) {
        $total_factors++;
        if ($preferences['pref_caste'] === $member['caste']) {
            $score += 20;
            $matching_factors[] = 'Caste';
        }
    }
    
    // Calculate percentage
    $percentage = $total_factors > 0 ? round(($score / ($total_factors * 20)) * 100) : 0;
    
    return [
        'score' => $percentage,
        'factors' => $matching_factors,
        'total_factors' => $total_factors
    ];
}

/**
 * Check if users are connected
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
 * Get privacy settings
 */
function getPrivacySettings($user_id, $conn) {
    $stmt = $conn->prepare("
        SELECT profile_visibility, show_phone, show_email
        FROM user_privacy_settings 
        WHERE user_id = ?
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        return [
            'profile_visibility' => 'public',
            'show_phone' => false,
            'show_email' => false
        ];
    }
    
    $privacy = $result->fetch_assoc();
    $stmt->close();
    return $privacy;
}

/**
 * Record profile view activity
 */
function recordProfileView($viewer_id, $viewed_id, $conn) {
    // Don't record if viewing own profile
    if ($viewer_id === $viewed_id) {
        return;
    }
    
    // Insert or update profile view record
    $stmt = $conn->prepare("
        INSERT INTO profile_views (viewer_id, viewed_id, view_count, last_viewed) 
        VALUES (?, ?, 1, NOW())
        ON DUPLICATE KEY UPDATE 
        view_count = view_count + 1, 
        last_viewed = NOW()
    ");
    $stmt->bind_param("ii", $viewer_id, $viewed_id);
    $stmt->execute();
    $stmt->close();
    
    // Log activity
    $stmt = $conn->prepare("
        INSERT INTO user_activity_log (user_id, activity_type, description, created_at) 
        VALUES (?, 'profile_view', ?, NOW())
    ");
    $description = "Viewed profile of user {$viewed_id}";
    $stmt->bind_param("is", $viewer_id, $description);
    $stmt->execute();
    $stmt->close();
}
?>
