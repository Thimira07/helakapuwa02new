<?php
/**
 * Helakapuwa.com - Get Members API
 * Handles member browsing with search, filters, and pagination
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
$filters = [];
$page = 1;
$limit = 12; // Default members per page
$sort_by = 'last_login'; // Default sort
$sort_order = 'DESC';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $filters = $_GET;
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(50, max(6, (int)($_GET['limit'] ?? 12))); // Between 6-50
    $sort_by = $_GET['sort_by'] ?? 'last_login';
    $sort_order = strtoupper($_GET['sort_order'] ?? 'DESC');
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $filters = $input['filters'] ?? [];
    $page = max(1, (int)($input['page'] ?? 1));
    $limit = min(50, max(6, (int)($input['limit'] ?? 12)));
    $sort_by = $input['sort_by'] ?? 'last_login';
    $sort_order = strtoupper($input['sort_order'] ?? 'DESC');
}

// Validate sort parameters
$valid_sort_fields = ['last_login', 'created_at', 'age', 'first_name'];
$valid_sort_orders = ['ASC', 'DESC'];

if (!in_array($sort_by, $valid_sort_fields)) {
    $sort_by = 'last_login';
}

if (!in_array($sort_order, $valid_sort_orders)) {
    $sort_order = 'DESC';
}

try {
    // Get current user info and package details
    $current_user = getCurrentUserInfo($current_user_id, $conn);
    if (!$current_user) {
        throw new Exception('Current user details සොයා ගත නොහැක.');
    }
    
    // Check package access and limitations
    $access_limits = checkPackageAccess($current_user, $conn);
    if (!$access_limits['can_browse']) {
        throw new Exception($access_limits['message']);
    }
    
    // Build search query with filters
    $search_query = buildSearchQuery($filters, $current_user, $sort_by, $sort_order);
    
    // Get total count for pagination
    $total_count = getTotalMembersCount($search_query['count_query'], $search_query['params'], $conn);
    
    // Calculate pagination
    $offset = ($page - 1) * $limit;
    $total_pages = ceil($total_count / $limit);
    
    // Get members with pagination
    $members = getMembers($search_query['main_query'], $search_query['params'], $limit, $offset, $current_user_id, $conn);
    
    // Get connection statuses for all members
    $members_with_status = addConnectionStatuses($members, $current_user_id, $conn);
    
    // Get filter options for frontend
    $filter_options = getFilterOptions($conn);
    
    // Record search activity
    recordSearchActivity($current_user_id, $filters, count($members), $conn);
    
    // Prepare response
    $response = [
        'success' => true,
        'members' => $members_with_status,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $total_pages,
            'total_count' => $total_count,
            'per_page' => $limit,
            'has_next' => $page < $total_pages,
            'has_prev' => $page > 1
        ],
        'filters_applied' => array_filter($filters), // Remove empty filters
        'filter_options' => $filter_options,
        'access_info' => [
            'package_name' => getPackageName($current_user['package_id']),
            'daily_views_used' => $access_limits['daily_views_used'],
            'daily_views_limit' => $access_limits['daily_views_limit']
        ]
    ];
    
    echo json_encode($response);

} catch (Exception $e) {
    // Log error
    error_log("Get members error for user {$current_user_id}: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} finally {
    $conn->close();
}

/**
 * Get current user information with package details
 */
function getCurrentUserInfo($user_id, $conn) {
    $stmt = $conn->prepare("
        SELECT u.user_id, u.first_name, u.last_name, u.package_id, u.gender, 
               u.religion, u.caste, u.dob, u.city, u.package_expires_at,
               YEAR(CURDATE()) - YEAR(u.dob) - (DATE_FORMAT(CURDATE(), '%m%d') < DATE_FORMAT(u.dob, '%m%d')) as age
        FROM users u
        WHERE u.user_id = ? AND u.status = 'Active'
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
 * Check package access and browsing limitations
 */
function checkPackageAccess($user, $conn) {
    // Check if package is active
    if ($user['package_id'] > 1) {
        if (!empty($user['package_expires_at'])) {
            $expires_at = new DateTime($user['package_expires_at']);
            $now = new DateTime();
            if ($expires_at <= $now) {
                return [
                    'can_browse' => false,
                    'message' => 'ඔබේ package එක expire වී ඇත. නව package එකක් subscribe කරන්න.'
                ];
            }
        }
    }
    
    // Check daily browsing limits
    $daily_limits = [
        1 => 20,   // Free: 20 profile views per day
        2 => 50,   // Silver: 50 views per day
        3 => 100,  // Gold: 100 views per day
        4 => 500   // Premium: 500 views per day
    ];
    
    $daily_limit = $daily_limits[$user['package_id']] ?? 20;
    
    // Get today's profile views
    $stmt = $conn->prepare("
        SELECT COUNT(*) as daily_views
        FROM profile_views 
        WHERE viewer_id = ? AND DATE(last_viewed) = CURDATE()
    ");
    $stmt->bind_param("i", $user['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $daily_views = $result->fetch_assoc()['daily_views'] ?? 0;
    $stmt->close();
    
    if ($daily_views >= $daily_limit) {
        return [
            'can_browse' => false,
            'message' => 'දෛනික profile view limit එක ඉක්මවා ගොස් ඇත. හෙට නැවත උත්සාහ කරන්න හෝ package එකක් upgrade කරන්න.',
            'daily_views_used' => $daily_views,
            'daily_views_limit' => $daily_limit
        ];
    }
    
    return [
        'can_browse' => true,
        'message' => 'Browse access granted.',
        'daily_views_used' => $daily_views,
        'daily_views_limit' => $daily_limit
    ];
}

/**
 * Build search query with filters
 */
function buildSearchQuery($filters, $current_user, $sort_by, $sort_order) {
    $base_query = "
        FROM users u
        LEFT JOIN user_privacy_settings ups ON u.user_id = ups.user_id
        WHERE u.status = 'Active' 
        AND u.user_id != ?
        AND u.gender != ?
    ";
    
    $params = [$current_user['user_id'], $current_user['gender']];
    $param_types = "is";
    
    $where_conditions = [];
    
    // Age filter
    if (!empty($filters['min_age']) && is_numeric($filters['min_age'])) {
        $where_conditions[] = "YEAR(CURDATE()) - YEAR(u.dob) - (DATE_FORMAT(CURDATE(), '%m%d') < DATE_FORMAT(u.dob, '%m%d')) >= ?";
        $params[] = (int)$filters['min_age'];
        $param_types .= "i";
    }
    
    if (!empty($filters['max_age']) && is_numeric($filters['max_age'])) {
        $where_conditions[] = "YEAR(CURDATE()) - YEAR(u.dob) - (DATE_FORMAT(CURDATE(), '%m%d') < DATE_FORMAT(u.dob, '%m%d')) <= ?";
        $params[] = (int)$filters['max_age'];
        $param_types .= "i";
    }
    
    // Religion filter
    if (!empty($filters['religion'])) {
        $where_conditions[] = "u.religion = ?";
        $params[] = $filters['religion'];
        $param_types .= "s";
    }
    
    // Caste filter
    if (!empty($filters['caste'])) {
        $where_conditions[] = "u.caste = ?";
        $params[] = $filters['caste'];
        $param_types .= "s";
    }
    
    // Profession filter
    if (!empty($filters['profession'])) {
        $where_conditions[] = "u.profession LIKE ?";
        $params[] = '%' . $filters['profession'] . '%';
        $param_types .= "s";
    }
    
    // Location filter (city or province)
    if (!empty($filters['location'])) {
        $where_conditions[] = "(u.city LIKE ? OR u.province LIKE ?)";
        $location_param = '%' . $filters['location'] . '%';
        $params[] = $location_param;
        $params[] = $location_param;
        $param_types .= "ss";
    }
    
    // Education filter
    if (!empty($filters['education'])) {
        $where_conditions[] = "u.education = ?";
        $params[] = $filters['education'];
        $param_types .= "s";
    }
    
    // Marital status filter
    if (!empty($filters['marital_status'])) {
        $where_conditions[] = "u.marital_status = ?";
        $params[] = $filters['marital_status'];
        $param_types .= "s";
    }
    
    // Income range filter
    if (!empty($filters['income_range'])) {
        $where_conditions[] = "u.income_range = ?";
        $params[] = $filters['income_range'];
        $param_types .= "s";
    }
    
    // Online status filter
    if (!empty($filters['online_status'])) {
        switch ($filters['online_status']) {
            case 'online':
                $where_conditions[] = "u.last_login >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)";
                break;
            case 'recently_active':
                $where_conditions[] = "u.last_login >= DATE_SUB(NOW(), INTERVAL 1 DAY)";
                break;
            case 'this_week':
                $where_conditions[] = "u.last_login >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
                break;
        }
    }
    
    // Profile picture filter
    if (!empty($filters['has_photo']) && $filters['has_photo'] === 'true') {
        $where_conditions[] = "u.profile_pic IS NOT NULL AND u.profile_pic != ''";
    }
    
    // Search keyword (name, profession, city)
    if (!empty($filters['keyword'])) {
        $where_conditions[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.profession LIKE ? OR u.city LIKE ?)";
        $keyword_param = '%' . $filters['keyword'] . '%';
        $params[] = $keyword_param;
        $params[] = $keyword_param;
        $params[] = $keyword_param;
        $params[] = $keyword_param;
        $param_types .= "ssss";
    }
    
    // Privacy filter - exclude users who don't want to be found
    $where_conditions[] = "(ups.profile_visibility IS NULL OR ups.profile_visibility != 'hidden')";
    
    // Add all conditions to query
    if (!empty($where_conditions)) {
        $base_query .= " AND " . implode(" AND ", $where_conditions);
    }
    
    // Build count query
    $count_query = "SELECT COUNT(DISTINCT u.user_id) as total " . $base_query;
    
    // Build main query with sorting
    $sort_field = "u.last_login"; // Default
    switch ($sort_by) {
        case 'age':
            $sort_field = "YEAR(CURDATE()) - YEAR(u.dob) - (DATE_FORMAT(CURDATE(), '%m%d') < DATE_FORMAT(u.dob, '%m%d'))";
            break;
        case 'first_name':
            $sort_field = "u.first_name";
            break;
        case 'created_at':
            $sort_field = "u.created_at";
            break;
        default:
            $sort_field = "u.last_login";
    }
    
    $main_query = "
        SELECT u.user_id, u.first_name, u.last_name, u.profile_pic, u.city, u.profession,
               u.religion, u.education, u.last_login,
               YEAR(CURDATE()) - YEAR(u.dob) - (DATE_FORMAT(CURDATE(), '%m%d') < DATE_FORMAT(u.dob, '%m%d')) as age,
               CASE 
                   WHEN u.last_login >= DATE_SUB(NOW(), INTERVAL 5 MINUTE) THEN 'online'
                   WHEN u.last_login >= DATE_SUB(NOW(), INTERVAL 1 HOUR) THEN 'recently_active'
                   ELSE 'offline'
               END as online_status
        " . $base_query . "
        ORDER BY {$sort_field} {$sort_order}
        LIMIT ? OFFSET ?
    ";
    
    return [
        'main_query' => $main_query,
        'count_query' => $count_query,
        'params' => $params,
        'param_types' => $param_types
    ];
}

/**
 * Get total members count
 */
function getTotalMembersCount($count_query, $params, $conn) {
    $stmt = $conn->prepare($count_query);
    
    if (!empty($params)) {
        $param_types = $params['param_types'] ?? str_repeat('s', count($params));
        $stmt->bind_param($param_types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $count = $result->fetch_assoc()['total'] ?? 0;
    $stmt->close();
    
    return (int)$count;
}

/**
 * Get members with pagination
 */
function getMembers($main_query, $params, $limit, $offset, $current_user_id, $conn) {
    // Add limit and offset to params
    $params[] = $limit;
    $params[] = $offset;
    $param_types = $params['param_types'] ?? str_repeat('s', count($params) - 2) . 'ii';
    
    $stmt = $conn->prepare($main_query);
    $stmt->bind_param($param_types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $members = [];
    while ($row = $result->fetch_assoc()) {
        // Format last seen
        $last_seen = formatLastSeen($row['last_login'], $row['online_status']);
        
        $members[] = [
            'user_id' => (int)$row['user_id'],
            'first_name' => htmlspecialchars($row['first_name'], ENT_QUOTES, 'UTF-8'),
            'last_name' => htmlspecialchars($row['last_name'], ENT_QUOTES, 'UTF-8'),
            'profile_pic' => $row['profile_pic'] ?: 'img/default-avatar.jpg',
            'age' => (int)$row['age'],
            'city' => htmlspecialchars($row['city'], ENT_QUOTES, 'UTF-8'),
            'profession' => htmlspecialchars($row['profession'], ENT_QUOTES, 'UTF-8'),
            'religion' => htmlspecialchars($row['religion'], ENT_QUOTES, 'UTF-8'),
            'education' => htmlspecialchars($row['education'], ENT_QUOTES, 'UTF-8'),
            'online_status' => $row['online_status'],
            'last_seen' => $last_seen['text'],
            'last_seen_color' => $last_seen['color']
        ];
    }
    
    $stmt->close();
    return $members;
}

/**
 * Add connection statuses to members
 */
function addConnectionStatuses($members, $current_user_id, $conn) {
    if (empty($members)) {
        return $members;
    }
    
    // Get member IDs
    $member_ids = array_column($members, 'user_id');
    $placeholders = str_repeat('?,', count($member_ids) - 1) . '?';
    
    // Get all connection statuses in one query
    $stmt = $conn->prepare("
        SELECT 
            CASE 
                WHEN r.sender_id = ? THEN r.receiver_id 
                ELSE r.sender_id 
            END as other_user_id,
            r.status,
            r.sender_id
        FROM requests r
        WHERE ((r.sender_id = ? AND r.receiver_id IN ($placeholders)) OR 
               (r.receiver_id = ? AND r.sender_id IN ($placeholders)))
    ");
    
    $params = array_merge(
        [$current_user_id, $current_user_id], 
        $member_ids, 
        [$current_user_id], 
        $member_ids
    );
    $param_types = 'ii' . str_repeat('i', count($member_ids)) . 'i' . str_repeat('i', count($member_ids));
    
    $stmt->bind_param($param_types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $connection_statuses = [];
    while ($row = $result->fetch_assoc()) {
        $other_user_id = $row['other_user_id'];
        $status = $row['status'];
        $is_sender = $row['sender_id'] == $current_user_id;
        
        $connection_statuses[$other_user_id] = [
            'status' => $status,
            'is_sender' => $is_sender
        ];
    }
    $stmt->close();
    
    // Add connection status to each member
    foreach ($members as &$member) {
        $connection = $connection_statuses[$member['user_id']] ?? null;
        
        if (!$connection) {
            $member['connection_status'] = 'none';
            $member['can_send_request'] = true;
            $member['can_chat'] = false;
            $member['status_text'] = '';
        } elseif ($connection['status'] === 'Accepted') {
            $member['connection_status'] = 'connected';
            $member['can_send_request'] = false;
            $member['can_chat'] = true;
            $member['status_text'] = 'Connected';
        } elseif ($connection['status'] === 'Pending') {
            if ($connection['is_sender']) {
                $member['connection_status'] = 'request_sent';
                $member['status_text'] = 'Request Sent';
            } else {
                $member['connection_status'] = 'request_received';
                $member['status_text'] = 'Request Received';
            }
            $member['can_send_request'] = false;
            $member['can_chat'] = false;
        } else {
            $member['connection_status'] = 'declined';
            $member['can_send_request'] = false;
            $member['can_chat'] = false;
            $member['status_text'] = 'Declined';
        }
    }
    
    return $members;
}

/**
 * Get filter options for frontend
 */
function getFilterOptions($conn) {
    // Get available religions
    $stmt = $conn->prepare("
        SELECT DISTINCT religion FROM users 
        WHERE religion IS NOT NULL AND religion != '' AND status = 'Active'
        ORDER BY religion
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $religions = [];
    while ($row = $result->fetch_assoc()) {
        $religions[] = $row['religion'];
    }
    $stmt->close();
    
    // Get available cities
    $stmt = $conn->prepare("
        SELECT DISTINCT city FROM users 
        WHERE city IS NOT NULL AND city != '' AND status = 'Active'
        ORDER BY city
        LIMIT 50
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $cities = [];
    while ($row = $result->fetch_assoc()) {
        $cities[] = $row['city'];
    }
    $stmt->close();
    
    // Get available professions
    $stmt = $conn->prepare("
        SELECT DISTINCT profession FROM users 
        WHERE profession IS NOT NULL AND profession != '' AND status = 'Active'
        ORDER BY profession
        LIMIT 50
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $professions = [];
    while ($row = $result->fetch_assoc()) {
        $professions[] = $row['profession'];
    }
    $stmt->close();
    
    return [
        'religions' => $religions,
        'cities' => $cities,
        'professions' => $professions,
        'education_levels' => [
            'O/L' => 'සාමාන්‍ය පෙළ',
            'A/L' => 'උසස් පෙළ',
            'Diploma' => 'ඩිප්ලෝමා',
            'Degree' => 'උපාධිය',
            'Masters' => 'ශාස්ත්‍රපති',
            'PhD' => 'ආචාර්ය'
        ],
        'marital_statuses' => [
            'Single' => 'අවිවාහක',
            'Divorced' => 'දික්කසාද',
            'Widowed' => 'වැන්දඹු'
        ],
        'income_ranges' => [
            'Below 30k' => '30,000 ට අඩු',
            '30k-50k' => '30,000 - 50,000',
            '50k-100k' => '50,000 - 100,000',
            '100k-200k' => '100,000 - 200,000',
            'Above 200k' => '200,000 ට වැඩි'
        ]
    ];
}

/**
 * Format last seen text
 */
function formatLastSeen($last_login, $online_status) {
    if ($online_status === 'online') {
        return ['text' => 'දැන් සම්බන්ධයි', 'color' => 'green'];
    } elseif ($online_status === 'recently_active') {
        return ['text' => 'මෙරටට active', 'color' => 'yellow'];
    }
    
    $last_login_time = new DateTime($last_login);
    $now = new DateTime();
    $diff = $now->diff($last_login_time);
    
    if ($diff->days > 0) {
        $text = $diff->days . ' දින කට පෙර';
    } elseif ($diff->h > 0) {
        $text = $diff->h . ' පැය කට පෙර';
    } else {
        $text = $diff->i . ' මිනිත්තු කට පෙර';
    }
    
    return ['text' => $text, 'color' => 'gray'];
}

/**
 * Record search activity
 */
function recordSearchActivity($user_id, $filters, $results_count, $conn) {
    $search_terms = json_encode(array_filter($filters));
    
    $stmt = $conn->prepare("
        INSERT INTO search_activity (user_id, search_terms, results_count, search_date) 
        VALUES (?, ?, ?, NOW())
    ");
    $stmt->bind_param("isi", $user_id, $search_terms, $results_count);
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
 * Get popular search terms (bonus function)
 */
function getPopularSearchTerms($conn) {
    $stmt = $conn->prepare("
        SELECT search_terms, COUNT(*) as search_count
        FROM search_activity 
        WHERE search_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        AND search_terms != '{}'
        GROUP BY search_terms
        ORDER BY search_count DESC
        LIMIT 10
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    
    $popular_searches = [];
    while ($row = $result->fetch_assoc()) {
        $terms = json_decode($row['search_terms'], true);
        if ($terms) {
            $popular_searches[] = [
                'terms' => $terms,
                'count' => $row['search_count']
            ];
        }
    }
    
    $stmt->close();
    return $popular_searches;
}

/**
 * Get recommended members based on preferences (bonus function)
 */
function getRecommendedMembers($user_id, $limit, $conn) {
    $stmt = $conn->prepare("
        SELECT pp.min_age, pp.max_age, pp.pref_religion, pp.pref_caste, 
               pp.pref_education, pp.pref_location
        FROM partner_preferences pp
        WHERE pp.user_id = ?
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        return [];
    }
    
    $preferences = $result->fetch_assoc();
    $stmt->close();
    
    // Build recommendation query based on preferences
    $score_conditions = [];
    $params = [$user_id];
    $param_types = "i";
    
    if ($preferences['min_age'] && $preferences['max_age']) {
        $score_conditions[] = "CASE WHEN (YEAR(CURDATE()) - YEAR(u.dob) - (DATE_FORMAT(CURDATE(), '%m%d') < DATE_FORMAT(u.dob, '%m%d'))) BETWEEN ? AND ? THEN 25 ELSE 0 END";
        $params[] = $preferences['min_age'];
        $params[] = $preferences['max_age'];
        $param_types .= "ii";
    }
    
    if ($preferences['pref_religion']) {
        $score_conditions[] = "CASE WHEN u.religion = ? THEN 25 ELSE 0 END";
        $params[] = $preferences['pref_religion'];
        $param_types .= "s";
    }
    
    if ($preferences['pref_education']) {
        $score_conditions[] = "CASE WHEN u.education = ? THEN 20 ELSE 0 END";
        $params[] = $preferences['pref_education'];
        $param_types .= "s";
    }
    
    if ($preferences['pref_location']) {
        $score_conditions[] = "CASE WHEN u.city LIKE ? THEN 15 ELSE 0 END";
        $params[] = '%' . $preferences['pref_location'] . '%';
        $param_types .= "s";
    }
    
    if (empty($score_conditions)) {
        return [];
    }
    
    $score_query = "(" . implode(" + ", $score_conditions) . ") as compatibility_score";
    
    $stmt = $conn->prepare("
        SELECT u.user_id, u.first_name, u.last_name, u.profile_pic, u.city, u.profession,
               YEAR(CURDATE()) - YEAR(u.dob) - (DATE_FORMAT(CURDATE(), '%m%d') < DATE_FORMAT(u.dob, '%m%d')) as age,
               {$score_query}
        FROM users u
        WHERE u.user_id != ? AND u.status = 'Active'
        HAVING compatibility_score > 20
        ORDER BY compatibility_score DESC, u.last_login DESC
        LIMIT ?
    ");
    
    $params[] = $limit;
    $param_types .= "i";
    
    $stmt->bind_param($param_types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $recommended = [];
    while ($row = $result->fetch_assoc()) {
        $recommended[] = [
            'user_id' => $row['user_id'],
            'name' => $row['first_name'] . ' ' . $row['last_name'],
            'profile_pic' => $row['profile_pic'] ?: 'img/default-avatar.jpg',
            'age' => $row['age'],
            'city' => $row['city'],
            'profession' => $row['profession'],
            'compatibility_score' => $row['compatibility_score']
        ];
    }
    
    $stmt->close();
    return $recommended;
}
?>
