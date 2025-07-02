<?php
/**
 * Helakapuwa.com - Package Subscription Handler
 * Handles membership package upgrades and payment processing
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

$user_id = $_SESSION['user_id'];

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
$required_fields = ['package_id', 'payment_method'];
foreach ($required_fields as $field) {
    if (!isset($input[$field]) || empty($input[$field])) {
        echo json_encode([
            'success' => false, 
            'message' => "අවශ්‍ය ක්ෂේත්‍රය සපයා නොමැත: {$field}"
        ]);
        exit();
    }
}

$package_id = (int)$input['package_id'];
$payment_method = $input['payment_method'];
$promo_code = $input['promo_code'] ?? null;

try {
    // Start transaction
    $conn->autocommit(false);
    
    // Get current user details
    $stmt = $conn->prepare("
        SELECT user_id, first_name, last_name, email, phone, package_id as current_package_id, 
               package_expires_at, requests_remaining 
        FROM users 
        WHERE user_id = ? AND status = 'Active'
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user_result = $stmt->get_result();
    
    if ($user_result->num_rows === 0) {
        throw new Exception('පරිශීලක සොයා ගත නොහැක.');
    }
    
    $user = $user_result->fetch_assoc();
    $stmt->close();
    
    // Get package details
    $stmt = $conn->prepare("
        SELECT package_id, package_name, price, duration_days, max_requests, description, is_active 
        FROM packages 
        WHERE package_id = ? AND is_active = 1
    ");
    $stmt->bind_param("i", $package_id);
    $stmt->execute();
    $package_result = $stmt->get_result();
    
    if ($package_result->num_rows === 0) {
        throw new Exception('වැරදි package ID හෝ අක්‍රිය package එකක්.');
    }
    
    $package = $package_result->fetch_assoc();
    $stmt->close();
    
    // Check if user is trying to downgrade to free package
    if ($package_id == 1) {
        throw new Exception('Free package එකට downgrade කරන්න බැහැ. Support team එක සම්බන්ධ කරන්න.');
    }
    
    // Check if user already has this package
    if ($user['current_package_id'] == $package_id) {
        // Extend current package
        $extend_package = true;
    } else {
        $extend_package = false;
    }
    
    // Calculate final price (with promo code if applicable)
    $original_price = $package['price'];
    $discount_amount = 0;
    $final_price = $original_price;
    
    if ($promo_code) {
        $promo_result = validatePromoCode($promo_code, $package_id, $user_id, $conn);
        if ($promo_result['valid']) {
            $discount_amount = $promo_result['discount_amount'];
            $final_price = $original_price - $discount_amount;
        }
    }
    
    // Calculate new expiry date
    $current_expiry = $user['package_expires_at'];
    $current_time = new DateTime();
    
    if ($extend_package && $current_expiry && strtotime($current_expiry) > time()) {
        // Extend from current expiry date
        $new_expiry = new DateTime($current_expiry);
        $new_expiry->add(new DateInterval('P' . $package['duration_days'] . 'D'));
    } else {
        // Start from current date
        $new_expiry = clone $current_time;
        $new_expiry->add(new DateInterval('P' . $package['duration_days'] . 'D'));
    }
    
    // Create transaction record
    $stmt = $conn->prepare("
        INSERT INTO user_transactions 
        (user_id, package_id, amount, payment_status, transaction_date, payment_gateway_ref) 
        VALUES (?, ?, ?, 'Pending', NOW(), ?)
    ");
    
    $temp_ref = 'TEMP_' . uniqid();
    $stmt->bind_param("iids", $user_id, $package_id, $final_price, $temp_ref);
    $stmt->execute();
    $transaction_id = $conn->insert_id;
    $stmt->close();
    
    // Process payment based on method
    switch ($payment_method) {
        case 'payhere':
            $payment_result = processPayHerePayment($user, $package, $final_price, $transaction_id, $input);
            break;
            
        case 'sampath':
            $payment_result = processSampathPayment($user, $package, $final_price, $transaction_id, $input);
            break;
            
        case 'bank_transfer':
            $payment_result = processBankTransfer($user, $package, $final_price, $transaction_id, $input);
            break;
            
        case 'test': // For testing purposes only
            if ($_SERVER['HTTP_HOST'] === 'localhost' || strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false) {
                $payment_result = processTestPayment($user, $package, $final_price, $transaction_id);
            } else {
                throw new Exception('Test payment method අවසර නැත.');
            }
            break;
            
        default:
            throw new Exception('සහාය නොදක්වන payment method.');
    }
    
    if (!$payment_result['success']) {
        throw new Exception($payment_result['message']);
    }
    
    // Update transaction with payment reference
    $stmt = $conn->prepare("
        UPDATE user_transactions 
        SET payment_gateway_ref = ?, payment_status = ? 
        WHERE transaction_id = ?
    ");
    $stmt->bind_param("ssi", $payment_result['reference'], $payment_result['status'], $transaction_id);
    $stmt->execute();
    $stmt->close();
    
    // If payment is completed, update user package
    if ($payment_result['status'] === 'Completed') {
        updateUserPackage($user_id, $package_id, $new_expiry->format('Y-m-d H:i:s'), $package['max_requests'], $conn);
        
        // Mark promo code as used if applicable
        if ($promo_code && isset($promo_result) && $promo_result['valid']) {
            markPromoCodeUsed($promo_code, $user_id, $transaction_id, $conn);
        }
        
        // Send confirmation email
        sendPackageConfirmationEmail($user, $package, $transaction_id, $final_price);
        
        // Log activity
        logUserActivity($user_id, 'package_upgrade', "Upgraded to {$package['package_name']} package", $conn);
    }
    
    // Commit transaction
    $conn->commit();
    
    // Prepare response
    $response = [
        'success' => true,
        'message' => $payment_result['status'] === 'Completed' ? 
                    'Package subscription සාර්ථකයි!' : 
                    'Payment processing වෙමින්...',
        'transaction_id' => $transaction_id,
        'package_name' => $package['package_name'],
        'amount_paid' => $final_price,
        'expires_at' => $new_expiry->format('Y-m-d'),
        'payment_status' => $payment_result['status']
    ];
    
    // Add payment-specific data
    if (isset($payment_result['payment_url'])) {
        $response['payment_url'] = $payment_result['payment_url'];
        $response['redirect_required'] = true;
    }
    
    echo json_encode($response);

} catch (Exception $e) {
    // Rollback transaction
    $conn->rollback();
    
    // Log error
    error_log("Package subscription error for user {$user_id}: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} finally {
    $conn->autocommit(true);
    $conn->close();
}

/**
 * Process PayHere Payment
 */
function processPayHerePayment($user, $package, $amount, $transaction_id, $input) {
    // PayHere configuration
    $merchant_id = 'YOUR_PAYHERE_MERCHANT_ID'; // Replace with actual merchant ID
    $merchant_secret = 'YOUR_PAYHERE_MERCHANT_SECRET'; // Replace with actual secret
    
    $order_id = 'HK_' . $transaction_id . '_' . time();
    $currency = 'LKR';
    
    // Calculate hash
    $hash_data = $merchant_id . $order_id . number_format($amount, 2, '.', '') . $currency . 
                 strtoupper(md5($merchant_secret));
    $hash = strtoupper(md5($hash_data));
    
    // Prepare PayHere data
    $payhere_data = [
        'merchant_id' => $merchant_id,
        'return_url' => 'https://helakapuwa.com/payment-success.html',
        'cancel_url' => 'https://helakapuwa.com/payment-cancel.html',
        'notify_url' => 'https://helakapuwa.com/api/payment_notify.php',
        'order_id' => $order_id,
        'items' => $package['package_name'] . ' Package',
        'currency' => $currency,
        'amount' => number_format($amount, 2, '.', ''),
        'first_name' => $user['first_name'],
        'last_name' => $user['last_name'],
        'email' => $user['email'],
        'phone' => $user['phone'],
        'address' => '',
        'city' => '',
        'country' => 'Sri Lanka',
        'hash' => $hash
    ];
    
    // Generate PayHere form URL
    $payment_url = 'https://sandbox.payhere.lk/pay/checkout';
    if (PRODUCTION_MODE) {
        $payment_url = 'https://www.payhere.lk/pay/checkout';
    }
    
    return [
        'success' => true,
        'status' => 'Pending',
        'reference' => $order_id,
        'payment_url' => $payment_url,
        'payment_data' => $payhere_data,
        'message' => 'PayHere payment gateway එකට redirect කරමින්...'
    ];
}

/**
 * Process Sampath Bank Payment
 */
function processSampathPayment($user, $package, $amount, $transaction_id, $input) {
    // Sampath Bank payment gateway integration
    $merchant_id = 'YOUR_SAMPATH_MERCHANT_ID';
    $merchant_secret = 'YOUR_SAMPATH_SECRET';
    
    $order_id = 'SP_' . $transaction_id . '_' . time();
    
    // Implement Sampath Bank specific payment logic here
    // This is a placeholder implementation
    
    return [
        'success' => true,
        'status' => 'Pending',
        'reference' => $order_id,
        'payment_url' => 'https://sampath-payment-gateway.com/pay',
        'message' => 'Sampath Bank payment gateway එකට redirect කරමින්...'
    ];
}

/**
 * Process Bank Transfer
 */
function processBankTransfer($user, $package, $amount, $transaction_id, $input) {
    $reference = 'BT_' . $transaction_id . '_' . time();
    
    // Send bank transfer instructions email
    sendBankTransferInstructions($user, $package, $amount, $reference);
    
    return [
        'success' => true,
        'status' => 'Pending',
        'reference' => $reference,
        'message' => 'Bank transfer instructions ඔබේ email එකට යවා ඇත.'
    ];
}

/**
 * Process Test Payment (Development only)
 */
function processTestPayment($user, $package, $amount, $transaction_id) {
    $reference = 'TEST_' . $transaction_id . '_' . time();
    
    return [
        'success' => true,
        'status' => 'Completed',
        'reference' => $reference,
        'message' => 'Test payment සාර්ථකයි!'
    ];
}

/**
 * Update User Package
 */
function updateUserPackage($user_id, $package_id, $expires_at, $max_requests, $conn) {
    $stmt = $conn->prepare("
        UPDATE users 
        SET package_id = ?, package_expires_at = ?, requests_remaining = ? 
        WHERE user_id = ?
    ");
    $stmt->bind_param("isii", $package_id, $expires_at, $max_requests, $user_id);
    $stmt->execute();
    $stmt->close();
}

/**
 * Validate Promo Code
 */
function validatePromoCode($promo_code, $package_id, $user_id, $conn) {
    $stmt = $conn->prepare("
        SELECT pc.*, COUNT(pcu.promo_code_id) as usage_count 
        FROM promo_codes pc 
        LEFT JOIN promo_code_usage pcu ON pc.promo_code_id = pcu.promo_code_id 
        WHERE pc.code = ? AND pc.is_active = 1 
        AND pc.valid_from <= NOW() AND pc.valid_until >= NOW()
        GROUP BY pc.promo_code_id
    ");
    $stmt->bind_param("s", $promo_code);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return ['valid' => false, 'message' => 'වැරදි promo code.'];
    }
    
    $promo = $result->fetch_assoc();
    $stmt->close();
    
    // Check usage limit
    if ($promo['usage_limit'] > 0 && $promo['usage_count'] >= $promo['usage_limit']) {
        return ['valid' => false, 'message' => 'Promo code usage limit එක ඉක්මවා ගොස් ඇත.'];
    }
    
    // Check if user already used this code
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count FROM promo_code_usage 
        WHERE promo_code_id = ? AND user_id = ?
    ");
    $stmt->bind_param("ii", $promo['promo_code_id'], $user_id);
    $stmt->execute();
    $user_usage = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($user_usage['count'] > 0) {
        return ['valid' => false, 'message' => 'ඔබ මෙම promo code එක දැනටමත් භාවිතා කර ඇත.'];
    }
    
    // Calculate discount
    $discount_amount = 0;
    if ($promo['discount_type'] === 'percentage') {
        $discount_amount = ($promo['discount_value'] / 100) * $amount;
        if ($promo['max_discount'] > 0) {
            $discount_amount = min($discount_amount, $promo['max_discount']);
        }
    } else {
        $discount_amount = $promo['discount_value'];
    }
    
    return [
        'valid' => true,
        'promo_code_id' => $promo['promo_code_id'],
        'discount_amount' => $discount_amount,
        'message' => 'Promo code successfully applied!'
    ];
}

/**
 * Mark Promo Code as Used
 */
function markPromoCodeUsed($promo_code, $user_id, $transaction_id, $conn) {
    $stmt = $conn->prepare("
        INSERT INTO promo_code_usage (promo_code_id, user_id, transaction_id, used_at) 
        SELECT pc.promo_code_id, ?, ?, NOW() 
        FROM promo_codes pc 
        WHERE pc.code = ?
    ");
    $stmt->bind_param("iis", $user_id, $transaction_id, $promo_code);
    $stmt->execute();
    $stmt->close();
}

/**
 * Send Package Confirmation Email
 */
function sendPackageConfirmationEmail($user, $package, $transaction_id, $amount) {
    $to = $user['email'];
    $subject = "Package Subscription Confirmation - Helakapuwa.com";
    
    $message = "
    Dear {$user['first_name']},
    
    Your {$package['package_name']} package subscription has been confirmed!
    
    Package Details:
    - Package: {$package['package_name']}
    - Amount Paid: LKR " . number_format($amount, 2) . "
    - Transaction ID: {$transaction_id}
    - Duration: {$package['duration_days']} days
    - Monthly Requests: {$package['max_requests']}
    
    You can now enjoy all the premium features of your new package.
    
    Thank you for choosing Helakapuwa.com!
    
    Best regards,
    Helakapuwa.com Team
    ";
    
    $headers = "From: noreply@helakapuwa.com\r\n";
    $headers .= "Reply-To: support@helakapuwa.com\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();
    
    mail($to, $subject, $message, $headers);
}

/**
 * Send Bank Transfer Instructions
 */
function sendBankTransferInstructions($user, $package, $amount, $reference) {
    $to = $user['email'];
    $subject = "Bank Transfer Instructions - Helakapuwa.com";
    
    $message = "
    Dear {$user['first_name']},
    
    Thank you for choosing the {$package['package_name']} package!
    
    Please complete your payment using the following bank transfer details:
    
    Bank: Commercial Bank of Ceylon PLC
    Account Name: Helakapuwa.com (Pvt) Ltd
    Account Number: 1234567890
    Branch: Colombo 03
    Amount: LKR " . number_format($amount, 2) . "
    Reference: {$reference}
    
    IMPORTANT: Please include the reference number '{$reference}' in your transfer remarks.
    
    After making the transfer, please email the deposit slip to payments@helakapuwa.com
    
    Your package will be activated within 24 hours of payment verification.
    
    Best regards,
    Helakapuwa.com Team
    ";
    
    $headers = "From: noreply@helakapuwa.com\r\n";
    $headers .= "Reply-To: payments@helakapuwa.com\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();
    
    mail($to, $subject, $message, $headers);
}

/**
 * Log User Activity
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

// Configuration constants (move to config file in production)
define('PRODUCTION_MODE', false);
?>
