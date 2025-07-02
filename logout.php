<?php
/**
 * Helakapuwa.com - User Logout Handler
 * Handles secure user logout and session cleanup
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
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

try {
    // Check if user is actually logged in
    if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
        // User not logged in, but still return success for consistency
        echo json_encode([
            'success' => true,
            'message' => 'දැනටමත් logout වී ඇත.',
            'redirect' => 'index.html'
        ]);
        exit();
    }
    
    $user_id = $_SESSION['user_id'];
    $session_id = session_id();
    
    // Determine if this is an AJAX request or direct request
    $is_ajax_request = (
        $_SERVER['REQUEST_METHOD'] === 'POST' || 
        (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
         strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
        (isset($_SERVER['CONTENT_TYPE']) && 
         strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false)
    );
    
    // Get logout reason if provided
    $logout_reason = 'user_initiated';
    $input = null;
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $is_ajax_request) {
        $input = json_decode(file_get_contents('php://input'), true);
        $logout_reason = $input['reason'] ?? 'user_initiated';
    } else if (isset($_GET['reason'])) {
        $logout_reason = $_GET['reason'];
    }
    
    // Validate logout reason
    $valid_reasons = [
        'user_initiated',    // User clicked logout
        'session_expired',   // Session timeout
        'security',          // Security-related logout
        'admin_action',      // Admin forced logout
        'package_expired',   // Package expiry logout
        'account_suspended'  // Account suspension
    ];
    
    if (!in_array($logout_reason, $valid_reasons)) {
        $logout_reason = 'user_initiated';
    }
    
    // Perform logout operations
    $logout_result = performLogout($user_id, $session_id, $logout_reason, $conn);
    
    // Determine redirect URL based on logout reason
    $redirect_url = determineLogoutRedirect($logout_reason);
    
    // Prepare response message
    $logout_messages = [
        'user_initiated' => 'සාර්ථකව logout වුණා. ආයෙත් එන්න!',
        'session_expired' => 'Session expire වී ඇත. නැවත login වන්න.',
        'security' => 'Security හේතුවක් නිසා logout වුණා.',
        'admin_action' => 'Admin විසින් logout කර ඇත.',
        'package_expired' => 'Package expire වීම නිසා logout වුණා.',
        'account_suspended' => 'Account suspend කර ඇත.'
    ];
    
    $message = $logout_messages[$logout_reason] ?? $logout_messages['user_initiated'];
    
    // Return response based on request type
    if ($is_ajax_request) {
        echo json_encode([
            'success' => true,
            'message' => $message,
            'redirect' => $redirect_url,
            'logout_reason' => $logout_reason
        ]);
    } else {
        // Direct request - redirect with message
        session_start(); // Start new session for flash message
        $_SESSION['logout_message'] = $message;
        $_SESSION['logout_reason'] = $logout_reason;
        header("Location: {$redirect_url}");
        exit();
    }

} catch (Exception $e) {
    // Log error
    error_log("Logout error: " . $e->getMessage());
    
    // Force logout even if error occurs
    forceLogout();
    
    if ($is_ajax_request ?? true) {
        echo json_encode([
            'success' => true, // Still return success to ensure user is logged out
            'message' => 'Logout වුණා.',
            'redirect' => 'index.html'
        ]);
    } else {
        header('Location: index.html');
        exit();
    }
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}

/**
 * Perform complete logout operations
 */
function performLogout($user_id, $session_id, $reason, $conn) {
    $results = [
        'session_destroyed' => false,
        'db_session_removed' => false,
        'activity_logged' => false,
        'login_time_calculated' => false
    ];
    
    try {
        // Calculate session duration before logout
        $session_duration = calculateSessionDuration($user_id, $conn);
        $results['login_time_calculated'] = true;
        
        // Log logout activity with duration
        logLogoutActivity($user_id, $reason, $session_duration, $conn);
        $results['activity_logged'] = true;
        
        // Update user's last activity
        updateLastActivity($user_id, $conn);
        
        // Remove session from database
        removeSessionFromDatabase($session_id, $user_id, $conn);
        $results['db_session_removed'] = true;
        
        // Clean up any expired sessions for this user
        cleanupUserExpiredSessions($user_id, $conn);
        
        // Destroy PHP session
        destroyPHPSession();
        $results['session_destroyed'] = true;
        
        // Update user statistics
        updateUserStats($user_id, $session_duration, $conn);
        
    } catch (Exception $e) {
        error_log("Logout operation error: " . $e->getMessage());
        // Continue with logout even if some operations fail
    }
    
    return $results;
}

/**
 * Calculate session duration
 */
function calculateSessionDuration($user_id, $conn) {
    $stmt = $conn->prepare("
        SELECT created_at FROM user_sessions 
        WHERE user_id = ? AND session_id = ?
    ");
    $session_id = session_id();
    $stmt->bind_param("is", $user_id, $session_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $login_time = new DateTime($row['created_at']);
        $logout_time = new DateTime();
        $duration = $logout_time->diff($login_time);
        
        // Convert to minutes
        $duration_minutes = ($duration->days * 24 * 60) + ($duration->h * 60) + $duration->i;
        $stmt->close();
        return $duration_minutes;
    }
    
    $stmt->close();
    return 0;
}

/**
 * Log logout activity
 */
function logLogoutActivity($user_id, $reason, $duration_minutes, $conn) {
    // Log in activity log
    $description = "User logged out. Reason: {$reason}, Duration: {$duration_minutes} minutes";
    $stmt = $conn->prepare("
        INSERT INTO user_activity_log (user_id, activity_type, description, created_at) 
        VALUES (?, 'logout', ?, NOW())
    ");
    $stmt->bind_param("is", $user_id, $description);
    $stmt->execute();
    $stmt->close();
    
    // Log in login history with logout info
    $stmt = $conn->prepare("
        INSERT INTO login_history (user_id, ip_address, user_agent, login_time, success, logout_time, session_duration, logout_reason) 
        VALUES (?, ?, ?, NOW(), 0, NOW(), ?, ?)
    ");
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $stmt->bind_param("issis", $user_id, $ip_address, $user_agent, $duration_minutes, $reason);
    $stmt->execute();
    $stmt->close();
}

/**
 * Update user's last activity
 */
function updateLastActivity($user_id, $conn) {
    $stmt = $conn->prepare("
        UPDATE users 
        SET last_login = NOW() 
        WHERE user_id = ?
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
}

/**
 * Remove session from database
 */
function removeSessionFromDatabase($session_id, $user_id, $conn) {
    $stmt = $conn->prepare("
        DELETE FROM user_sessions 
        WHERE session_id = ? AND user_id = ?
    ");
    $stmt->bind_param("si", $session_id, $user_id);
    $stmt->execute();
    $stmt->close();
}

/**
 * Clean up expired sessions for user
 */
function cleanupUserExpiredSessions($user_id, $conn) {
    $stmt = $conn->prepare("
        DELETE FROM user_sessions 
        WHERE user_id = ? AND expires_at < NOW()
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
}

/**
 * Destroy PHP session properly
 */
function destroyPHPSession() {
    // Clear session variables
    $_SESSION = array();
    
    // Delete session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Destroy session
    session_destroy();
}

/**
 * Update user statistics
 */
function updateUserStats($user_id, $session_duration, $conn) {
    // Update total session time (if you have user stats table)
    $stmt = $conn->prepare("
        INSERT INTO user_stats (user_id, total_session_time, last_session_duration, last_logout) 
        VALUES (?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE 
        total_session_time = total_session_time + VALUES(last_session_duration),
        last_session_duration = VALUES(last_session_duration),
        last_logout = NOW()
    ");
    $stmt->bind_param("iii", $user_id, $session_duration, $session_duration);
    $stmt->execute();
    $stmt->close();
}

/**
 * Determine logout redirect URL
 */
function determineLogoutRedirect($reason) {
    switch ($reason) {
        case 'session_expired':
            return 'login.html?expired=1';
        case 'security':
            return 'login.html?security=1';
        case 'admin_action':
            return 'login.html?admin=1';
        case 'package_expired':
            return 'login.html?package_expired=1';
        case 'account_suspended':
            return 'login.html?suspended=1';
        default:
            return 'index.html';
    }
}

/**
 * Force logout without database operations (emergency)
 */
function forceLogout() {
    try {
        destroyPHPSession();
    } catch (Exception $e) {
        error_log("Force logout error: " . $e->getMessage());
    }
}

/**
 * Logout all sessions for a user (admin function)
 */
function logoutAllUserSessions($user_id, $conn) {
    // Remove all database sessions
    $stmt = $conn->prepare("DELETE FROM user_sessions WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
    
    // Log admin action
    $stmt = $conn->prepare("
        INSERT INTO user_activity_log (user_id, activity_type, description, created_at) 
        VALUES (?, 'admin_logout_all', 'All sessions terminated by admin', NOW())
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
}

/**
 * Check for concurrent sessions (security feature)
 */
function checkConcurrentSessions($user_id, $conn) {
    $stmt = $conn->prepare("
        SELECT COUNT(*) as session_count 
        FROM user_sessions 
        WHERE user_id = ? AND expires_at > NOW()
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $session_count = $result->fetch_assoc()['session_count'] ?? 0;
    $stmt->close();
    
    return $session_count;
}

/**
 * Get user's active sessions (for settings page)
 */
function getUserActiveSessions($user_id, $conn) {
    $stmt = $conn->prepare("
        SELECT session_id, ip_address, user_agent, created_at, expires_at,
               CASE 
                   WHEN session_id = ? THEN 1 
                   ELSE 0 
               END as is_current
        FROM user_sessions 
        WHERE user_id = ? AND expires_at > NOW()
        ORDER BY created_at DESC
    ");
    $current_session = session_id();
    $stmt->bind_param("si", $current_session, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $sessions = [];
    while ($row = $result->fetch_assoc()) {
        // Parse user agent for display
        $device_info = parseUserAgent($row['user_agent']);
        
        $sessions[] = [
            'session_id' => $row['session_id'],
            'ip_address' => $row['ip_address'],
            'device_info' => $device_info,
            'created_at' => $row['created_at'],
            'expires_at' => $row['expires_at'],
            'is_current' => (bool)$row['is_current']
        ];
    }
    
    $stmt->close();
    return $sessions;
}

/**
 * Parse user agent for device information
 */
function parseUserAgent($user_agent) {
    $device_info = [
        'browser' => 'Unknown',
        'os' => 'Unknown',
        'device' => 'Desktop'
    ];
    
    // Simple browser detection
    if (strpos($user_agent, 'Chrome') !== false) {
        $device_info['browser'] = 'Chrome';
    } elseif (strpos($user_agent, 'Firefox') !== false) {
        $device_info['browser'] = 'Firefox';
    } elseif (strpos($user_agent, 'Safari') !== false) {
        $device_info['browser'] = 'Safari';
    } elseif (strpos($user_agent, 'Edge') !== false) {
        $device_info['browser'] = 'Edge';
    }
    
    // Simple OS detection
    if (strpos($user_agent, 'Windows') !== false) {
        $device_info['os'] = 'Windows';
    } elseif (strpos($user_agent, 'Mac') !== false) {
        $device_info['os'] = 'macOS';
    } elseif (strpos($user_agent, 'Linux') !== false) {
        $device_info['os'] = 'Linux';
    } elseif (strpos($user_agent, 'Android') !== false) {
        $device_info['os'] = 'Android';
        $device_info['device'] = 'Mobile';
    } elseif (strpos($user_agent, 'iOS') !== false) {
        $device_info['os'] = 'iOS';
        $device_info['device'] = 'Mobile';
    }
    
    return $device_info;
}

/**
 * Terminate specific session (for settings page)
 */
function terminateSession($session_id, $user_id, $conn) {
    $stmt = $conn->prepare("
        DELETE FROM user_sessions 
        WHERE session_id = ? AND user_id = ?
    ");
    $stmt->bind_param("si", $session_id, $user_id);
    $success = $stmt->execute();
    $stmt->close();
    
    if ($success) {
        // Log session termination
        $stmt = $conn->prepare("
            INSERT INTO user_activity_log (user_id, activity_type, description, created_at) 
            VALUES (?, 'session_terminated', ?, NOW())
        ");
        $description = "Session terminated: {$session_id}";
        $stmt->bind_param("is", $user_id, $description);
        $stmt->execute();
        $stmt->close();
    }
    
    return $success;
}

/**
 * Auto-logout based on inactivity (cron job function)
 */
function autoLogoutInactiveUsers($conn) {
    // Get users with expired sessions
    $stmt = $conn->prepare("
        SELECT DISTINCT u.user_id, u.first_name, u.email
        FROM users u
        INNER JOIN user_sessions us ON u.user_id = us.user_id
        WHERE us.expires_at < NOW()
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    
    $logged_out_users = [];
    while ($row = $result->fetch_assoc()) {
        $logged_out_users[] = $row;
        
        // Log auto-logout
        logLogoutActivity($row['user_id'], 'session_expired', 0, $conn);
    }
    $stmt->close();
    
    // Remove expired sessions
    $stmt = $conn->prepare("DELETE FROM user_sessions WHERE expires_at < NOW()");
    $stmt->execute();
    $affected_rows = $stmt->affected_rows;
    $stmt->close();
    
    return [
        'users_logged_out' => count($logged_out_users),
        'sessions_removed' => $affected_rows,
        'users' => $logged_out_users
    ];
}
?>