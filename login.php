<?php
/**
 * Helakapuwa.com - User Login Handler
 * Handles user authentication and session management
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

// Check if JSON decoding failed
if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode([
        'success' => false, 
        'message' => 'වැරදි JSON format.'
    ]);
    exit();
}

try {
    // Validate required fields
    $validation_result = validateLoginData($input);
    if (!$validation_result['valid']) {
        throw new Exception($validation_result['message']);
    }
    
    $email = trim(strtolower($input['email']));
    $password = $input['password'];
    $remember_me = isset($input['remember_me']) ? (bool)$input['remember_me'] : false;
    
    // Check rate limiting
    checkLoginRateLimit($email, $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', $conn);
    
    // Get user details
    $user = getUserByEmail($email, $conn);
    if (!$user) {
        // Log failed attempt
        logFailedLogin($email, $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', 'user_not_found', $conn);
        throw new Exception('වැරදි email address හෝ password.');
    }
    
    // Verify password
    if (!password_verify($password, $user['password'])) {
        // Log failed attempt
        logFailedLogin($email, $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', 'wrong_password', $conn);
        throw new Exception('වැරදි email address හෝ password.');
    }
    
    // Check account status
    if ($user['status'] !== 'Active') {
        logFailedLogin($email, $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', 'account_inactive', $conn);
        
        $status_messages = [
            'Inactive' => 'ඔබේ account එක inactive කර ඇත. Support team සම්බන්ධ කරන්න.',
            'Suspended' => 'ඔබේ account එක suspend කර ඇත. Support team සම්බන්ධ කරන්න.',
            'Pending' => 'ඔබේ account එක approval pending වේ.'
        ];
        
        throw new Exception($status_messages[$user['status']] ?? 'ඔබේ account එකේ ගැටලුවක් ඇත.');
    }
    
    // Check package expiry and update if needed
    $package_status = checkAndUpdatePackageStatus($user, $conn);
    
    // Create session
    createUserSession($user, $remember_me);
    
    // Update last login timestamp
    updateLastLogin($user['user_id'], $conn);
    
    // Log successful login
    logSuccessfulLogin($user['user_id'], $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', $conn);
    
    // Clear failed login attempts
    clearFailedLoginAttempts($email, $conn);
    
    // Determine redirect URL based on user type and status
    $redirect_url = determineRedirectUrl($user, $package_status);
    
    // Prepare response
    $response = [
        'success' => true,
        'message' => 'ප්‍රවේශය සාර්ථකයි!',
        'user' => [
            'id' => $user['user_id'],
            'first_name' => $user['first_name'],
            'last_name' => $user['last_name'],
            'email' => $user['email'],
            'profile_pic' => $user['profile_pic'] ?: 'img/default-avatar.jpg',
            'package_name' => getPackageName($user['package_id']),
            'package_expires_at' => $user['package_expires_at'],
            'requests_remaining' => $user['requests_remaining']
        ],
        'redirect' => $redirect_url,
        'package_status' => $package_status
    ];
    
    echo json_encode($response);

} catch (Exception $e) {
    // Log error
    error_log("Login error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} finally {
    $conn->close();
}

/**
 * Validate login data
 */
function validateLoginData($data) {
    // Check required fields
    if (!isset($data['email']) || empty(trim($data['email']))) {
        return [
            'valid' => false,
            'message' => 'Email address අවශ්‍යයි.'
        ];
    }
    
    if (!isset($data['password']) || empty($data['password'])) {
        return [
            'valid' => false,
            'message' => 'Password අවශ්‍යයි.'
        ];
    }
    
    // Validate email format
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        return [
            'valid' => false,
            'message' => 'වැරදි email format.'
        ];
    }
    
    return [
        'valid' => true,
        'message' => 'Validation passed.'
    ];
}

/**
 * Check login rate limiting
 */
function checkLoginRateLimit($email, $ip_address, $conn) {
    // Check failed attempts in last 15 minutes
    $stmt = $conn->prepare("
        SELECT COUNT(*) as failed_count
        FROM failed_logins 
        WHERE (email = ? OR ip_address = ?) 
        AND attempt_time >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)
    ");
    $stmt->bind_param("ss", $email, $ip_address);
    $stmt->execute();
    $result = $stmt->get_result();
    $failed_count = $result->fetch_assoc()['failed_count'] ?? 0;
    $stmt->close();
    
    if ($failed_count >= 5) {
        throw new Exception('ඉතා වේගයෙන් login attempts. 15 මිනිත්තුවකට පසු නැවත උත්සාහ කරන්න.');
    }
    
    // Check successful logins from IP in last hour (prevent brute force)
    $stmt = $conn->prepare("
        SELECT COUNT(*) as login_count
        FROM user_sessions 
        WHERE ip_address = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
    ");
    $stmt->bind_param("s", $ip_address);
    $stmt->execute();
    $result = $stmt->get_result();
    $login_count = $result->fetch_assoc()['login_count'] ?? 0;
    $stmt->close();
    
    if ($login_count >= 10) {
        throw new Exception('IP address එකෙන් ඉතා වේගයෙන් logins. පැයකට පසු නැවත උත්සාහ කරන්න.');
    }
}

/**
 * Get user by email
 */
function getUserByEmail($email, $conn) {
    $stmt = $conn->prepare("
        SELECT user_id, email, password, first_name, last_name, profile_pic, 
               status, package_id, package_expires_at, requests_remaining, last_login
        FROM users 
        WHERE email = ?
    ");
    $stmt->bind_param("s", $email);
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
 * Check and update package status
 */
function checkAndUpdatePackageStatus($user, $conn) {
    $package_status = [
        'is_expired' => false,
        'days_remaining' => null,
        'message' => '',
        'needs_upgrade' => false
    ];
    
    // Free package (ID 1) never expires
    if ($user['package_id'] == 1) {
        $package_status['message'] = 'Free package active.';
        $package_status['needs_upgrade'] = true;
        return $package_status;
    }
    
    // Check if paid package has expired
    if (!empty($user['package_expires_at'])) {
        $expires_at = new DateTime($user['package_expires_at']);
        $now = new DateTime();
        
        if ($expires_at <= $now) {
            // Package has expired - downgrade to free
            $stmt = $conn->prepare("
                UPDATE users 
                SET package_id = 1, requests_remaining = 0, package_expires_at = NULL 
                WHERE user_id = ?
            ");
            $stmt->bind_param("i", $user['user_id']);
            $stmt->execute();
            $stmt->close();
            
            $package_status['is_expired'] = true;
            $package_status['message'] = 'ඔබේ package එක expire වී ඇත. Free package එකට downgrade වුණා.';
            $package_status['needs_upgrade'] = true;
            
            // Log package expiry
            logUserActivity($user['user_id'], 'package_expired', 'Package expired and downgraded to free', $conn);
        } else {
            // Calculate days remaining
            $diff = $now->diff($expires_at);
            $days_remaining = $diff->days;
            
            $package_status['days_remaining'] = $days_remaining;
            
            if ($days_remaining <= 3) {
                $package_status['message'] = "ඔබේ package එක දින {$days_remaining} කින් expire වේ.";
                $package_status['needs_upgrade'] = true;
            } else if ($days_remaining <= 7) {
                $package_status['message'] = "ඔබේ package එක සතියකින් expire වේ.";
            } else {
                $package_status['message'] = 'Package active.';
            }
        }
    }
    
    return $package_status;
}

/**
 * Create user session
 */
function createUserSession($user, $remember_me) {
    // Regenerate session ID for security
    session_regenerate_id(true);
    
    // Set session variables
    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['first_name'] = $user['first_name'];
    $_SESSION['last_name'] = $user['last_name'];
    $_SESSION['package_id'] = $user['package_id'];
    $_SESSION['login_time'] = time();
    
    // Set session cookie parameters
    $session_duration = $remember_me ? (30 * 24 * 60 * 60) : (8 * 60 * 60); // 30 days or 8 hours
    
    session_set_cookie_params([
        'lifetime' => $session_duration,
        'path' => '/',
        'domain' => '',
        'secure' => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    
    // Create session record in database
    global $conn;
    $session_id = session_id();
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $expires_at = date('Y-m-d H:i:s', time() + $session_duration);
    
    $stmt = $conn->prepare("
        INSERT INTO user_sessions (session_id, user_id, ip_address, user_agent, expires_at, created_at) 
        VALUES (?, ?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE 
        ip_address = VALUES(ip_address), 
        user_agent = VALUES(user_agent), 
        expires_at = VALUES(expires_at)
    ");
    $stmt->bind_param("sisss", $session_id, $user['user_id'], $ip_address, $user_agent, $expires_at);
    $stmt->execute();
    $stmt->close();
}

/**
 * Update last login timestamp
 */
function updateLastLogin($user_id, $conn) {
    $stmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
}

/**
 * Log successful login
 */
function logSuccessfulLogin($user_id, $ip_address, $conn) {
    // Log in user activity
    logUserActivity($user_id, 'login', 'User logged in successfully', $conn);
    
    // Log in login history
    $stmt = $conn->prepare("
        INSERT INTO login_history (user_id, ip_address, user_agent, login_time, success) 
        VALUES (?, ?, ?, NOW(), 1)
    ");
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $stmt->bind_param("iss", $user_id, $ip_address, $user_agent);
    $stmt->execute();
    $stmt->close();
}

/**
 * Log failed login attempt
 */
function logFailedLogin($email, $ip_address, $reason, $conn) {
    $stmt = $conn->prepare("
        INSERT INTO failed_logins (email, ip_address, reason, attempt_time) 
        VALUES (?, ?, ?, NOW())
    ");
    $stmt->bind_param("sss", $email, $ip_address, $reason);
    $stmt->execute();
    $stmt->close();
}

/**
 * Clear failed login attempts
 */
function clearFailedLoginAttempts($email, $conn) {
    $stmt = $conn->prepare("DELETE FROM failed_logins WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->close();
}

/**
 * Determine redirect URL based on user status
 */
function determineRedirectUrl($user, $package_status) {
    // Check if profile is incomplete
    if (empty($user['profile_pic']) || empty($user['first_name']) || empty($user['last_name'])) {
        return 'member/edit_profile.php?welcome=1';
    }
    
    // Check if package expired
    if ($package_status['is_expired']) {
        return 'member/dashboard.php?package_expired=1';
    }
    
    // Check if this is first login (within 24 hours of registration)
    $stmt = global $conn;
    $stmt = $conn->prepare("
        SELECT created_at FROM users 
        WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ");
    $stmt->bind_param("i", $user['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $is_new_user = $result->num_rows > 0;
    $stmt->close();
    
    if ($is_new_user) {
        return 'member/dashboard.php?welcome=1';
    }
    
    // Default redirect
    return 'member/dashboard.php';
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
 * Get package name
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
 * Clean old sessions (can be called periodically)
 */
function cleanExpiredSessions($conn) {
    $stmt = $conn->prepare("DELETE FROM user_sessions WHERE expires_at < NOW()");
    $stmt->execute();
    $stmt->close();
    
    $stmt = $conn->prepare("DELETE FROM failed_logins WHERE attempt_time < DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    $stmt->execute();
    $stmt->close();
}

/**
 * Check if user is logged in (utility function)
 */
function isUserLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Get current user ID (utility function)
 */
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Logout user (utility function)
 */
function logoutUser($conn = null) {
    if ($conn && isset($_SESSION['user_id'])) {
        // Log logout activity
        logUserActivity($_SESSION['user_id'], 'logout', 'User logged out', $conn);
        
        // Remove session from database
        $session_id = session_id();
        $stmt = $conn->prepare("DELETE FROM user_sessions WHERE session_id = ?");
        $stmt->bind_param("s", $session_id);
        $stmt->execute();
        $stmt->close();
    }
    
    // Clear session
    session_unset();
    session_destroy();
    
    // Clear session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
}

/**
 * Check session validity (utility function)
 */
function checkSessionValidity($conn) {
    if (!isUserLoggedIn()) {
        return false;
    }
    
    // Check if session exists in database
    $session_id = session_id();
    $stmt = $conn->prepare("
        SELECT user_id FROM user_sessions 
        WHERE session_id = ? AND expires_at > NOW()
    ");
    $stmt->bind_param("s", $session_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $is_valid = $result->num_rows > 0;
    $stmt->close();
    
    if (!$is_valid) {
        logoutUser($conn);
        return false;
    }
    
    return true;
}

/**
 * Get user's login statistics (bonus function)
 */
function getUserLoginStats($user_id, $conn) {
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_logins,
            MAX(login_time) as last_login,
            COUNT(CASE WHEN login_time >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as logins_last_30_days,
            COUNT(DISTINCT DATE(login_time)) as active_days
        FROM login_history 
        WHERE user_id = ? AND success = 1
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats = $result->fetch_assoc();
    $stmt->close();
    
    return $stats;
}

/**
 * Check for suspicious login patterns (bonus function)
 */
function checkSuspiciousLogin($user_id, $ip_address, $conn) {
    // Check if login from new location
    $stmt = $conn->prepare("
        SELECT COUNT(*) as familiar_ip_count
        FROM login_history 
        WHERE user_id = ? AND ip_address = ? AND success = 1
    ");
    $stmt->bind_param("is", $user_id, $ip_address);
    $stmt->execute();
    $result = $stmt->get_result();
    $familiar_ip = $result->fetch_assoc()['familiar_ip_count'] > 0;
    $stmt->close();
    
    if (!$familiar_ip) {
        // Log suspicious activity
        logUserActivity($user_id, 'suspicious_login', "Login from new IP: {$ip_address}", $conn);
        
        // Could send email notification here
        return true;
    }
    
    return false;
}
?>