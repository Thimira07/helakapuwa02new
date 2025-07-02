<?php
/**
 * Helakapuwa.com - User Registration Handler
 * Handles user registration with validation and database insertion
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
    // Start transaction
    $conn->autocommit(false);
    
    // Validate required fields
    $validation_result = validateRegistrationData($input);
    if (!$validation_result['valid']) {
        throw new Exception($validation_result['message']);
    }
    
    // Check if email already exists
    if (emailExists($input['email'], $conn)) {
        throw new Exception('මෙම email address එක දැනටමත් ලියාපදිංචි කර ඇත.');
    }
    
    // Prepare user data
    $user_data = prepareUserData($input);
    
    // Create user account
    $user_id = createUserAccount($user_data, $conn);
    
    // Create partner preferences if provided
    if (hasPartnerPreferences($input)) {
        $preferences_data = preparePartnerPreferences($input, $user_id);
        createPartnerPreferences($preferences_data, $conn);
    }
    
    // Log registration activity
    logUserActivity($user_id, 'registration', 'User registered successfully', $conn);
    
    // Send welcome email
    sendWelcomeEmail($user_data);
    
    // Commit transaction
    $conn->commit();
    
    // Prepare success response
    $response = [
        'success' => true,
        'message' => 'ලියාපදිංචිය සාර්ථකයි! දැන් ප්‍රවේශ වන්න.',
        'user_id' => $user_id,
        'redirect' => 'login.html'
    ];
    
    echo json_encode($response);

} catch (Exception $e) {
    // Rollback transaction
    $conn->rollback();
    
    // Log error
    error_log("Registration error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} finally {
    $conn->autocommit(true);
    $conn->close();
}

/**
 * Validate registration data
 */
function validateRegistrationData($data) {
    // Required fields for step 1 (basic info)
    $required_fields = ['firstName', 'lastName', 'email', 'password', 'confirmPassword', 'gender', 'dob'];
    
    foreach ($required_fields as $field) {
        if (!isset($data[$field]) || empty(trim($data[$field]))) {
            return [
                'valid' => false,
                'message' => "අවශ්‍ය ක්ෂේත්‍රය හිස්: {$field}"
            ];
        }
    }
    
    // Validate email format
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        return [
            'valid' => false,
            'message' => 'වැරදි email format.'
        ];
    }
    
    // Validate password strength
    $password_validation = validatePassword($data['password']);
    if (!$password_validation['valid']) {
        return $password_validation;
    }
    
    // Check password confirmation
    if ($data['password'] !== $data['confirmPassword']) {
        return [
            'valid' => false,
            'message' => 'Password confirmation එක match නොවේ.'
        ];
    }
    
    // Validate gender
    if (!in_array($data['gender'], ['Male', 'Female'])) {
        return [
            'valid' => false,
            'message' => 'වැරදි gender value.'
        ];
    }
    
    // Validate date of birth
    $dob_validation = validateDateOfBirth($data['dob']);
    if (!$dob_validation['valid']) {
        return $dob_validation;
    }
    
    // Validate phone number if provided
    if (!empty($data['phone'])) {
        if (!validatePhoneNumber($data['phone'])) {
            return [
                'valid' => false,
                'message' => 'වැරදි phone number format. Sri Lankan format භාවිතා කරන්න.'
            ];
        }
    }
    
    // Validate optional fields if provided
    if (!empty($data['religion']) && !in_array($data['religion'], ['Buddhist', 'Hindu', 'Christian', 'Islam', 'Other'])) {
        return [
            'valid' => false,
            'message' => 'වැරදි religion value.'
        ];
    }
    
    if (!empty($data['maritalStatus']) && !in_array($data['maritalStatus'], ['Single', 'Divorced', 'Widowed'])) {
        return [
            'valid' => false,
            'message' => 'වැරදි marital status value.'
        ];
    }
    
    if (!empty($data['education']) && !in_array($data['education'], ['O/L', 'A/L', 'Diploma', 'Degree', 'Masters', 'PhD'])) {
        return [
            'valid' => false,
            'message' => 'වැරදි education value.'
        ];
    }
    
    if (!empty($data['income']) && !in_array($data['income'], ['Below 30k', '30k-50k', '50k-100k', '100k-200k', 'Above 200k'])) {
        return [
            'valid' => false,
            'message' => 'වැරදි income range value.'
        ];
    }
    
    // Validate text field lengths
    if (strlen($data['firstName']) > 100 || strlen($data['lastName']) > 100) {
        return [
            'valid' => false,
            'message' => 'නම ඉතා දිගයි.'
        ];
    }
    
    if (!empty($data['aboutMe']) && strlen($data['aboutMe']) > 1000) {
        return [
            'valid' => false,
            'message' => 'About me section ඉතා දිගයි. අක්ෂර 1000 ට සීමා කරන්න.'
        ];
    }
    
    // Validate partner preferences if provided
    if (!empty($data['prefMinAge']) || !empty($data['prefMaxAge'])) {
        $pref_validation = validatePartnerPreferences($data);
        if (!$pref_validation['valid']) {
            return $pref_validation;
        }
    }
    
    // Terms acceptance validation
    if (!isset($data['termsAccepted']) || !$data['termsAccepted']) {
        return [
            'valid' => false,
            'message' => 'නියම සහ කොන්දේසි පිළිගත යුතුයි.'
        ];
    }
    
    return [
        'valid' => true,
        'message' => 'Validation passed.'
    ];
}

/**
 * Validate password strength
 */
function validatePassword($password) {
    if (strlen($password) < 8) {
        return [
            'valid' => false,
            'message' => 'Password අවම වශයෙන් අක්ෂර 8ක් විය යුතුයි.'
        ];
    }
    
    if (strlen($password) > 100) {
        return [
            'valid' => false,
            'message' => 'Password ඉතා දිගයි.'
        ];
    }
    
    // Check for at least one number
    if (!preg_match('/[0-9]/', $password)) {
        return [
            'valid' => false,
            'message' => 'Password එකේ අවම වශයෙන් එක් digit එකක් තිබිය යුතුයි.'
        ];
    }
    
    // Check for at least one letter
    if (!preg_match('/[a-zA-Z]/', $password)) {
        return [
            'valid' => false,
            'message' => 'Password එකේ අවම වශයෙන් එක් letter එකක් තිබිය යුතුයි.'
        ];
    }
    
    return [
        'valid' => true,
        'message' => 'Password valid.'
    ];
}

/**
 * Validate date of birth
 */
function validateDateOfBirth($dob) {
    $birth_date = DateTime::createFromFormat('Y-m-d', $dob);
    
    if (!$birth_date) {
        return [
            'valid' => false,
            'message' => 'වැරදි date format.'
        ];
    }
    
    $today = new DateTime();
    $age = $today->diff($birth_date)->y;
    
    if ($age < 18) {
        return [
            'valid' => false,
            'message' => 'අවම වශයෙන් වයස 18 විය යුතුයි.'
        ];
    }
    
    if ($age > 70) {
        return [
            'valid' => false,
            'message' => 'උපරිම වයස 70 ට සීමා කර ඇත.'
        ];
    }
    
    return [
        'valid' => true,
        'message' => 'Date of birth valid.'
    ];
}

/**
 * Validate Sri Lankan phone number
 */
function validatePhoneNumber($phone) {
    // Remove spaces and special characters
    $phone = preg_replace('/[^0-9+]/', '', $phone);
    
    // Sri Lankan mobile number patterns
    $patterns = [
        '/^(\+94|94|0)?[1-9][0-9]{8}$/',  // Mobile: 0771234567, +94771234567, 94771234567
        '/^(\+94|94|0)?[1-9][0-9]{8,9}$/' // Landline: 0112345678, +94112345678
    ];
    
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $phone)) {
            return true;
        }
    }
    
    return false;
}

/**
 * Validate partner preferences
 */
function validatePartnerPreferences($data) {
    if (!empty($data['prefMinAge']) && !empty($data['prefMaxAge'])) {
        $min_age = (int)$data['prefMinAge'];
        $max_age = (int)$data['prefMaxAge'];
        
        if ($min_age < 18 || $min_age > 70) {
            return [
                'valid' => false,
                'message' => 'කැමති අවම වයස 18-70 අතර විය යුතුයි.'
            ];
        }
        
        if ($max_age < 18 || $max_age > 70) {
            return [
                'valid' => false,
                'message' => 'කැමති උපරිම වයස 18-70 අතර විය යුතුයි.'
            ];
        }
        
        if ($min_age > $max_age) {
            return [
                'valid' => false,
                'message' => 'කැමති අවම වයස උපරිම වයසට වඩා වැඩි විය නොහැක.'
            ];
        }
    }
    
    return [
        'valid' => true,
        'message' => 'Partner preferences valid.'
    ];
}

/**
 * Check if email already exists
 */
function emailExists($email, $conn) {
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result->num_rows > 0;
    $stmt->close();
    
    return $exists;
}

/**
 * Prepare user data for database insertion
 */
function prepareUserData($input) {
    return [
        'email' => trim(strtolower($input['email'])),
        'password' => password_hash($input['password'], PASSWORD_DEFAULT),
        'first_name' => trim($input['firstName']),
        'last_name' => trim($input['lastName']),
        'gender' => $input['gender'],
        'dob' => $input['dob'],
        'phone' => !empty($input['phone']) ? preg_replace('/[^0-9+]/', '', $input['phone']) : null,
        'religion' => !empty($input['religion']) ? $input['religion'] : null,
        'caste' => !empty($input['caste']) ? trim($input['caste']) : null,
        'marital_status' => !empty($input['maritalStatus']) ? $input['maritalStatus'] : null,
        'education' => !empty($input['education']) ? $input['education'] : null,
        'profession' => !empty($input['profession']) ? trim($input['profession']) : null,
        'income_range' => !empty($input['income']) ? $input['income'] : null,
        'city' => !empty($input['city']) ? trim($input['city']) : null,
        'province' => !empty($input['province']) ? trim($input['province']) : null,
        'country' => 'Sri Lanka',
        'about_me' => !empty($input['aboutMe']) ? trim($input['aboutMe']) : null,
        'status' => 'Active',
        'package_id' => 1, // Free package by default
        'requests_remaining' => 0 // Free package has no requests
    ];
}

/**
 * Create user account
 */
function createUserAccount($user_data, $conn) {
    $stmt = $conn->prepare("
        INSERT INTO users (
            email, password, first_name, last_name, gender, dob, phone, religion, 
            caste, marital_status, education, profession, income_range, city, 
            province, country, about_me, status, package_id, requests_remaining, 
            created_at, last_login
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW()
        )
    ");
    
    $stmt->bind_param("ssssssssssssssssssii", 
        $user_data['email'],
        $user_data['password'],
        $user_data['first_name'],
        $user_data['last_name'],
        $user_data['gender'],
        $user_data['dob'],
        $user_data['phone'],
        $user_data['religion'],
        $user_data['caste'],
        $user_data['marital_status'],
        $user_data['education'],
        $user_data['profession'],
        $user_data['income_range'],
        $user_data['city'],
        $user_data['province'],
        $user_data['country'],
        $user_data['about_me'],
        $user_data['status'],
        $user_data['package_id'],
        $user_data['requests_remaining']
    );
    
    if (!$stmt->execute()) {
        $stmt->close();
        throw new Exception('Database එකට user data save කරන්න බැරි වුණා: ' . $stmt->error);
    }
    
    $user_id = $conn->insert_id;
    $stmt->close();
    
    return $user_id;
}

/**
 * Check if partner preferences data exists
 */
function hasPartnerPreferences($input) {
    return !empty($input['prefMinAge']) || !empty($input['prefMaxAge']) || 
           !empty($input['prefReligion']) || !empty($input['prefEducation']) ||
           !empty($input['prefLocation']);
}

/**
 * Prepare partner preferences data
 */
function preparePartnerPreferences($input, $user_id) {
    return [
        'user_id' => $user_id,
        'min_age' => !empty($input['prefMinAge']) ? (int)$input['prefMinAge'] : null,
        'max_age' => !empty($input['prefMaxAge']) ? (int)$input['prefMaxAge'] : null,
        'pref_religion' => !empty($input['prefReligion']) ? $input['prefReligion'] : null,
        'pref_caste' => !empty($input['prefCaste']) ? trim($input['prefCaste']) : null,
        'pref_marital_status' => !empty($input['prefMaritalStatus']) ? $input['prefMaritalStatus'] : null,
        'pref_profession' => !empty($input['prefProfession']) ? trim($input['prefProfession']) : null,
        'pref_location' => !empty($input['prefLocation']) ? trim($input['prefLocation']) : null,
        'pref_education' => !empty($input['prefEducation']) ? $input['prefEducation'] : null
    ];
}

/**
 * Create partner preferences
 */
function createPartnerPreferences($preferences_data, $conn) {
    $stmt = $conn->prepare("
        INSERT INTO partner_preferences (
            user_id, min_age, max_age, pref_religion, pref_caste, 
            pref_marital_status, pref_profession, pref_location, pref_education
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->bind_param("iiissssss", 
        $preferences_data['user_id'],
        $preferences_data['min_age'],
        $preferences_data['max_age'],
        $preferences_data['pref_religion'],
        $preferences_data['pref_caste'],
        $preferences_data['pref_marital_status'],
        $preferences_data['pref_profession'],
        $preferences_data['pref_location'],
        $preferences_data['pref_education']
    );
    
    if (!$stmt->execute()) {
        $stmt->close();
        throw new Exception('Partner preferences save කරන්න බැරි වුණා: ' . $stmt->error);
    }
    
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
 * Send welcome email
 */
function sendWelcomeEmail($user_data) {
    $to = $user_data['email'];
    $subject = "Welcome to Helakapuwa.com - ඔබ Helakapuwa.com වෙත සාදරයෙන් පිළිගනිමු!";
    
    $message = "
    Dear {$user_data['first_name']},
    
    ඔබ සාර්ථකව Helakapuwa.com හි ලියාපදිංචි වී ඇත!
    
    දැන් ඔබට:
    • සෝදර profiles browse කරන්න පුළුවන්
    • ඔබේ profile සම්පූර්ණ කරන්න පුළුවන්
    • Compatible matches සොයා ගන්න පුළුවන්
    
    Premium package එකක් ගැනීමෙන්:
    • Connection requests යවන්න පුළුවන්
    • Chat කරන්න පුළුවන්
    • Advanced search features භාවිතා කරන්න පුළුවන්
    
    ඔබේ ජීවිත සහකරු සොයා ගැනීමේ ගමන ආරම්භ කරන්න:
    https://helakapuwa.com/login.html
    
    Important Tips:
    • ඔබේ profile photo එකක් upload කරන්න
    • Profile details සම්පූර්ණ කරන්න
    • Partner preferences set කරන්න
    
    ප්‍රශ්න තිබේ නම්:
    Email: support@helakapuwa.com
    Phone: +94 11 234 5678
    
    Happy matching!
    
    Helakapuwa.com Team
    ";
    
    $headers = "From: noreply@helakapuwa.com\r\n";
    $headers .= "Reply-To: support@helakapuwa.com\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    
    // Send email (use proper email service in production)
    mail($to, $subject, $message, $headers);
}

/**
 * Generate email verification token (bonus feature)
 */
function generateVerificationToken($user_id, $email, $conn) {
    $token = bin2hex(random_bytes(32));
    $expires_at = date('Y-m-d H:i:s', strtotime('+24 hours'));
    
    $stmt = $conn->prepare("
        INSERT INTO email_verifications (user_id, email, token, expires_at, created_at) 
        VALUES (?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE 
        token = VALUES(token), 
        expires_at = VALUES(expires_at), 
        created_at = NOW()
    ");
    $stmt->bind_param("isss", $user_id, $email, $token, $expires_at);
    $stmt->execute();
    $stmt->close();
    
    return $token;
}

/**
 * Send email verification email (bonus feature)
 */
function sendVerificationEmail($user_data, $token) {
    $verification_url = "https://helakapuwa.com/verify-email.php?token=" . $token;
    
    $to = $user_data['email'];
    $subject = "Email Verification - Helakapuwa.com";
    
    $message = "
    Dear {$user_data['first_name']},
    
    Please verify your email address by clicking the link below:
    
    {$verification_url}
    
    This link will expire in 24 hours.
    
    If you didn't create an account, please ignore this email.
    
    Thank you,
    Helakapuwa.com Team
    ";
    
    $headers = "From: noreply@helakapuwa.com\r\n";
    $headers .= "Reply-To: support@helakapuwa.com\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();
    
    mail($to, $subject, $message, $headers);
}

/**
 * Clean input data to prevent XSS
 */
function cleanInput($data) {
    if (is_array($data)) {
        return array_map('cleanInput', $data);
    }
    
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Generate secure user ID (bonus feature)
 */
function generateSecureUserId() {
    return 'HK_' . time() . '_' . bin2hex(random_bytes(4));
}

/**
 * Check for suspicious registration patterns (bonus feature)
 */
function checkSuspiciousActivity($data, $conn) {
    // Check for multiple registrations from same IP in short time
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM user_registrations 
        WHERE ip_address = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
    ");
    $stmt->bind_param("s", $ip_address);
    $stmt->execute();
    $result = $stmt->get_result();
    $ip_count = $result->fetch_assoc()['count'] ?? 0;
    $stmt->close();
    
    if ($ip_count >= 3) {
        throw new Exception('ඉතා වේගයෙන් registrations. කරුණාකර පැයකට පසු නැවත උත්සාහ කරන්න.');
    }
    
    // Log registration attempt
    $stmt = $conn->prepare("
        INSERT INTO user_registrations (ip_address, email, success, created_at) 
        VALUES (?, ?, 1, NOW())
    ");
    $stmt->bind_param("ss", $ip_address, $data['email']);
    $stmt->execute();
    $stmt->close();
}

/**
 * Create default privacy settings (bonus feature)
 */
function createDefaultPrivacySettings($user_id, $conn) {
    $stmt = $conn->prepare("
        INSERT INTO user_privacy_settings (user_id, profile_visibility, show_phone, show_email) 
        VALUES (?, 'public', 0, 0)
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
}
?>
