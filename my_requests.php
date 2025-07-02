<?php
session_start();
require_once('../includes/db_connect.php');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../public/login.html');
    exit();
}

$user_id = $_SESSION['user_id'];

// Handle request actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $request_id = (int)$_POST['request_id'];
    $action = $_POST['action'];
    
    try {
        if ($action === 'accept') {
            $stmt = $conn->prepare("UPDATE requests SET status = 'Accepted', responded_at = NOW() 
                                   WHERE request_id = ? AND receiver_id = ? AND status = 'Pending'");
            $stmt->bind_param("ii", $request_id, $user_id);
            $stmt->execute();
            
            if ($stmt->affected_rows > 0) {
                $success_message = "Request accepted successfully!";
            } else {
                $error_message = "Unable to accept request.";
            }
        } elseif ($action === 'decline') {
            $stmt = $conn->prepare("UPDATE requests SET status = 'Declined', responded_at = NOW() 
                                   WHERE request_id = ? AND receiver_id = ? AND status = 'Pending'");
            $stmt->bind_param("ii", $request_id, $user_id);
            $stmt->execute();
            
            if ($stmt->affected_rows > 0) {
                $success_message = "Request declined.";
            } else {
                $error_message = "Unable to decline request.";
            }
        }
    } catch (Exception $e) {
        $error_message = "Error processing request: " . $e->getMessage();
    }
}

// Fetch sent requests
$sent_requests_query = "
    SELECT r.*, u.first_name, u.last_name, u.age, u.city, u.profession, u.profile_pic
    FROM requests r
    JOIN users u ON r.receiver_id = u.user_id
    WHERE r.sender_id = ?
    ORDER BY r.requested_at DESC
";
$stmt = $conn->prepare($sent_requests_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$sent_requests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch received requests
$received_requests_query = "
    SELECT r.*, u.first_name, u.last_name, u.age, u.city, u.profession, u.profile_pic
    FROM requests r
    JOIN users u ON r.sender_id = u.user_id
    WHERE r.receiver_id = ?
    ORDER BY r.requested_at DESC
";
$stmt = $conn->prepare($received_requests_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$received_requests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Calculate age from DOB
function calculateAge($dob) {
    if (!$dob) return '';
    $today = new DateTime();
    $birthDate = new DateTime($dob);
    return $today->diff($birthDate)->y;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Requests - Helakapuwa.com</title>
    
    <!-- Tailwind CSS -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-blue: #0096C7;
            --secondary-blue: #0077A3;
            --light-blue: #48CAE4;
            --accent-gold: #FFD60A;
            --text-dark: #1F2937;
            --text-gray: #6B7280;
            --bg-light: #F8FAFC;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: var(--bg-light);
        }

        .sidebar {
            background: white;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            border-right: 1px solid #E5E7EB;
            position: fixed;
            left: 0;
            top: 0;
            height: 100vh;
            width: 280px;
            overflow-y: auto;
            z-index: 1000;
            transition: transform 0.3s ease;
        }

        .main-content {
            margin-left: 280px;
            transition: margin-left 0.3s ease;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 1rem 1.5rem;
            color: var(--text-gray);
            text-decoration: none;
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
        }

        .nav-item:hover,
        .nav-item.active {
            background: #F3F4F6;
            color: var(--primary-blue);
            border-left-color: var(--primary-blue);
        }

        .requests-section {
            background: white;
            border-radius: 1rem;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            border: 1px solid #E5E7EB;
        }

        .section-title {
            color: var(--primary-blue);
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #E5E7EB;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .request-card {
            background: white;
            border-radius: 1rem;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            border: 1px solid #E5E7EB;
            transition: all 0.3s ease;
        }

        .request-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 150, 199, 0.15);
        }

        .profile-avatar {
            width: 4rem;
            height: 4rem;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--light-blue), var(--primary-blue));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            font-weight: 700;
            border: 3px solid white;
            box-shadow: 0 2px 8px rgba(0, 150, 199, 0.3);
        }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 1rem;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-pending {
            background: #FEF3C7;
            color: #92400E;
        }

        .status-accepted {
            background: #D1FAE5;
            color: #065F46;
        }

        .status-declined {
            background: #FEE2E2;
            color: #991B1B;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--secondary-blue) 100%);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            border: none;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.875rem;
        }

        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 150, 199, 0.4);
        }

        .btn-secondary {
            background: #F3F4F6;
            color: var(--text-dark);
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            border: 1px solid #E5E7EB;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.875rem;
        }

        .btn-secondary:hover {
            background: #E5E7EB;
        }

        .btn-danger {
            background: #EF4444;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            border: none;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.875rem;
        }

        .btn-danger:hover {
            background: #DC2626;
            transform: translateY(-1px);
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: var(--text-gray);
        }

        .empty-state i {
            font-size: 4rem;
            color: #D1D5DB;
            margin-bottom: 1rem;
        }

        .tabs {
            display: flex;
            border-bottom: 2px solid #E5E7EB;
            margin-bottom: 2rem;
        }

        .tab {
            padding: 1rem 2rem;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .tab:hover {
            color: var(--primary-blue);
        }

        .tab.active {
            color: var(--primary-blue);
            border-bottom-color: var(--primary-blue);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.mobile-open {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }

            .request-card {
                padding: 1rem;
            }

            .tabs {
                flex-wrap: wrap;
            }

            .tab {
                padding: 0.75rem 1rem;
                font-size: 0.875rem;
            }
        }

        .mobile-menu-toggle {
            display: none;
            background: var(--primary-blue);
            color: white;
            border: none;
            padding: 0.75rem;
            border-radius: 0.5rem;
            cursor: pointer;
            position: fixed;
            top: 1rem;
            left: 1rem;
            z-index: 1001;
        }

        @media (max-width: 768px) {
            .mobile-menu-toggle {
                display: block;
            }
        }
    </style>
</head>
<body>
    <!-- Mobile Menu Toggle -->
    <button class="mobile-menu-toggle" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <!-- Logo -->
        <div class="p-6 border-b border-gray-200">
            <div class="flex items-center space-x-2">
                <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-blue-600 rounded-lg flex items-center justify-center">
                    <i class="fas fa-heart text-white"></i>
                </div>
                <span class="text-xl font-bold text-gray-800">Helakapuwa</span>
            </div>
        </div>

        <!-- Navigation -->
        <nav class="py-4">
            <a href="dashboard.php" class="nav-item">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            <a href="edit_profile.php" class="nav-item">
                <i class="fas fa-user"></i>
                <span>My Profile</span>
            </a>
            <a href="#" class="nav-item active">
                <i class="fas fa-heart"></i>
                <span>Matches</span>
                <?php if (count(array_filter($received_requests, fn($r) => $r['status'] === 'Pending')) > 0): ?>
                <span class="bg-red-500 text-white text-xs px-2 py-1 rounded-full ml-auto">
                    <?php echo count(array_filter($received_requests, fn($r) => $r['status'] === 'Pending')); ?>
                </span>
                <?php endif; ?>
            </a>
            <a href="chat.php" class="nav-item">
                <i class="fas fa-comments"></i>
                <span>Messages</span>
            </a>
            <a href="my_connections.php" class="nav-item">
                <i class="fas fa-users"></i>
                <span>Connections</span>
            </a>
            <a href="../api/logout.php" class="nav-item text-red-600 hover:text-red-700">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="bg-white shadow-sm border-b border-gray-200 p-6">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">My Requests</h1>
                    <p class="text-gray-600">Manage your sent and received interest requests</p>
                </div>
                <a href="../public/browse-members.html" class="btn-primary">
                    <i class="fas fa-search mr-2"></i>
                    Browse Members
                </a>
            </div>
        </div>

        <!-- Requests Content -->
        <div class="p-6">
            <?php if (isset($success_message)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                <i class="fas fa-check-circle mr-2"></i>
                <?php echo $success_message; ?>
            </div>
            <?php endif; ?>

            <?php if (isset($error_message)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <i class="fas fa-exclamation-circle mr-2"></i>
                <?php echo $error_message; ?>
            </div>
            <?php endif; ?>

            <!-- Tabs -->
            <div class="tabs">
                <div class="tab active" onclick="showTab('received')">
                    <i class="fas fa-inbox mr-2"></i>
                    Received Requests
                    <?php if (count(array_filter($received_requests, fn($r) => $r['status'] === 'Pending')) > 0): ?>
                    <span class="bg-red-500 text-white text-xs px-2 py-1 rounded-full ml-2">
                        <?php echo count(array_filter($received_requests, fn($r) => $r['status'] === 'Pending')); ?>
                    </span>
                    <?php endif; ?>
                </div>
                <div class="tab" onclick="showTab('sent')">
                    <i class="fas fa-paper-plane mr-2"></i>
                    Sent Requests
                    <span class="bg-gray-500 text-white text-xs px-2 py-1 rounded-full ml-2">
                        <?php echo count($sent_requests); ?>
                    </span>
                </div>
            </div>

            <!-- Received Requests Tab -->
            <div id="received-tab" class="tab-content active">
                <div class="requests-section">
                    <h2 class="section-title">
                        <i class="fas fa-inbox"></i>
                        Received Requests (<?php echo count($received_requests); ?>)
                    </h2>

                    <?php if (empty($received_requests)): ?>
                    <div class="empty-state">
                        <i class="fas fa-heart-broken"></i>
                        <h3 class="text-lg font-semibold mb-2">No requests received yet</h3>
                        <p>Complete your profile to attract more interest!</p>
                        <a href="edit_profile.php" class="btn-primary mt-4 inline-block">
                            <i class="fas fa-user-edit mr-2"></i>
                            Complete Profile
                        </a>
                    </div>
                    <?php else: ?>
                    <?php foreach ($received_requests as $request): ?>
                    <div class="request-card">
                        <div class="flex items-center gap-4">
                            <div class="profile-avatar">
                                <?php echo strtoupper(substr($request['first_name'], 0, 1)); ?>
                            </div>
                            
                            <div class="flex-1">
                                <div class="flex items-center justify-between mb-2">
                                    <h3 class="text-lg font-semibold text-gray-800">
                                        <?php echo htmlspecialchars($request['first_name'] . ' ' . $request['last_name']); ?>
                                    </h3>
                                    <span class="status-badge status-<?php echo strtolower($request['status']); ?>">
                                        <?php echo $request['status']; ?>
                                    </span>
                                </div>
                                
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-2 text-sm text-gray-600 mb-3">
                                    <div><i class="fas fa-birthday-cake mr-1"></i> <?php echo $request['age']; ?> years</div>
                                    <div><i class="fas fa-map-marker-alt mr-1"></i> <?php echo htmlspecialchars($request['city']); ?></div>
                                    <div><i class="fas fa-briefcase mr-1"></i> <?php echo htmlspecialchars($request['profession']); ?></div>
                                </div>
                                
                                <div class="flex items-center justify-between">
                                    <p class="text-sm text-gray-500">
                                        <i class="fas fa-clock mr-1"></i>
                                        Sent on <?php echo date('M j, Y', strtotime($request['requested_at'])); ?>
                                    </p>
                                    
                                    <?php if ($request['status'] === 'Pending'): ?>
                                    <div class="flex gap-2">
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="request_id" value="<?php echo $request['request_id']; ?>">
                                            <input type="hidden" name="action" value="accept">
                                            <button type="submit" class="btn-primary" onclick="return confirm('Accept this request?')">
                                                <i class="fas fa-check mr-1"></i>
                                                Accept
                                            </button>
                                        </form>
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="request_id" value="<?php echo $request['request_id']; ?>">
                                            <input type="hidden" name="action" value="decline">
                                            <button type="submit" class="btn-danger" onclick="return confirm('Decline this request?')">
                                                <i class="fas fa-times mr-1"></i>
                                                Decline
                                            </button>
                                        </form>
                                    </div>
                                    <?php elseif ($request['status'] === 'Accepted'): ?>
                                    <a href="chat.php?user=<?php echo $request['sender_id']; ?>" class="btn-primary">
                                        <i class="fas fa-comments mr-1"></i>
                                        Start Chat
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Sent Requests Tab -->
            <div id="sent-tab" class="tab-content">
                <div class="requests-section">
                    <h2 class="section-title">
                        <i class="fas fa-paper-plane"></i>
                        Sent Requests (<?php echo count($sent_requests); ?>)
                    </h2>

                    <?php if (empty($sent_requests)): ?>
                    <div class="empty-state">
                        <i class="fas fa-search"></i>
                        <h3 class="text-lg font-semibold mb-2">No requests sent yet</h3>
                        <p>Start browsing profiles and send interest requests!</p>
                        <a href="../public/browse-members.html" class="btn-primary mt-4 inline-block">
                            <i class="fas fa-search mr-2"></i>
                            Browse Members
                        </a>
                    </div>
                    <?php else: ?>
                    <?php foreach ($sent_requests as $request): ?>
                    <div class="request-card">
                        <div class="flex items-center gap-4">
                            <div class="profile-avatar">
                                <?php echo strtoupper(substr($request['first_name'], 0, 1)); ?>
                            </div>
                            
                            <div class="flex-1">
                                <div class="flex items-center justify-between mb-2">
                                    <h3 class="text-lg font-semibold text-gray-800">
                                        <?php echo htmlspecialchars($request['first_name'] . ' ' . $request['last_name']); ?>
                                    </h3>
                                    <span class="status-badge status-<?php echo strtolower($request['status']); ?>">
                                        <?php echo $request['status']; ?>
                                    </span>
                                </div>
                                
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-2 text-sm text-gray-600 mb-3">
                                    <div><i class="fas fa-birthday-cake mr-1"></i> <?php echo $request['age']; ?> years</div>
                                    <div><i class="fas fa-map-marker-alt mr-1"></i> <?php echo htmlspecialchars($request['city']); ?></div>
                                    <div><i class="fas fa-briefcase mr-1"></i> <?php echo htmlspecialchars($request['profession']); ?></div>
                                </div>
                                
                                <div class="flex items-center justify-between">
                                    <p class="text-sm text-gray-500">
                                        <i class="fas fa-clock mr-1"></i>
                                        Sent on <?php echo date('M j, Y', strtotime($request['requested_at'])); ?>
                                        <?php if ($request['responded_at']): ?>
                                        | Responded <?php echo date('M j, Y', strtotime($request['responded_at'])); ?>
                                        <?php endif; ?>
                                    </p>
                                    
                                    <?php if ($request['status'] === 'Accepted'): ?>
                                    <a href="chat.php?user=<?php echo $request['receiver_id']; ?>" class="btn-primary">
                                        <i class="fas fa-comments mr-1"></i>
                                        Start Chat
                                    </a>
                                    <?php elseif ($request['status'] === 'Pending'): ?>
                                    <span class="text-sm text-gray-500 italic">Waiting for response...</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('mobile-open');
            
            if (window.innerWidth <= 768) {
                document.body.style.overflow = sidebar.classList.contains('mobile-open') ? 'hidden' : 'auto';
            }
        }

        function showTab(tabName) {
            // Remove active class from all tabs and contents
            document.querySelectorAll('.tab').forEach(tab => tab.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
            
            // Add active class to clicked tab and corresponding content
            event.target.classList.add('active');
            document.getElementById(tabName + '-tab').classList.add('active');
        }

        // Handle window resize
        window.addEventListener('resize', function() {
            if (window.innerWidth > 768) {
                document.getElementById('sidebar').classList.remove('mobile-open');
                document.body.style.overflow = 'auto';
            }
        });

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(e) {
            const sidebar = document.getElementById('sidebar');
            const toggleBtn = document.querySelector('.mobile-menu-toggle');
            
            if (window.innerWidth <= 768 && 
                sidebar.classList.contains('mobile-open') && 
                !sidebar.contains(e.target) && 
                !toggleBtn.contains(e.target)) {
                toggleSidebar();
            }
        });

        // Auto-refresh page every 30 seconds to check for new requests
        setInterval(function() {
            if (document.visibilityState === 'visible') {
                location.reload();
            }
        }, 30000);

        console.log('My Requests page loaded successfully!');
    </script>
</body>
</html>
