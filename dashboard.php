<?php
session_start();
require_once('../includes/db_connect.php');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../public/login.html');
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch user data
$stmt = $conn->prepare("SELECT u.*, p.package_name, p.max_requests 
                        FROM users u 
                        LEFT JOIN packages p ON u.package_id = p.package_id 
                        WHERE u.user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Fetch user statistics
$stats_query = "
    SELECT 
        (SELECT COUNT(*) FROM requests WHERE sender_id = ? AND status = 'Pending') as sent_requests,
        (SELECT COUNT(*) FROM requests WHERE receiver_id = ? AND status = 'Pending') as received_requests,
        (SELECT COUNT(*) FROM requests WHERE (sender_id = ? OR receiver_id = ?) AND status = 'Accepted') as connections,
        (SELECT COUNT(*) FROM users WHERE user_id != ? AND status = 'Active') as total_members
";
$stmt = $conn->prepare($stats_query);
$stmt->bind_param("iiiii", $user_id, $user_id, $user_id, $user_id, $user_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();

// Fetch recent profile visitors (simulated data for demo)
$recent_visitors = [
    ['name' => 'Priya S.', 'age' => 25, 'city' => 'Colombo', 'visited' => '2 hours ago'],
    ['name' => 'Kasun P.', 'age' => 28, 'city' => 'Kandy', 'visited' => '5 hours ago'],
    ['name' => 'Sanduni F.', 'age' => 24, 'city' => 'Galle', 'visited' => '1 day ago']
];

// Fetch recent matches (simulated data for demo)
$recent_matches = [
    ['name' => 'Roshan W.', 'age' => 30, 'profession' => 'Engineer', 'match_score' => 95],
    ['name' => 'Thilini R.', 'age' => 26, 'profession' => 'Doctor', 'match_score' => 92],
    ['name' => 'Chamara S.', 'age' => 29, 'profession' => 'Teacher', 'match_score' => 88]
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Helakapuwa.com</title>
    
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

        .sidebar.mobile-hidden {
            transform: translateX(-100%);
        }

        .main-content {
            margin-left: 280px;
            transition: margin-left 0.3s ease;
        }

        .main-content.full-width {
            margin-left: 0;
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

        .stat-card {
            background: white;
            border-radius: 1rem;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            border: 1px solid #E5E7EB;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 25px rgba(0, 150, 199, 0.1);
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--primary-blue);
            line-height: 1;
        }

        .stat-label {
            color: var(--text-gray);
            font-size: 0.875rem;
            margin-top: 0.5rem;
        }

        .stat-icon {
            width: 3rem;
            height: 3rem;
            border-radius: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.25rem;
        }

        .dashboard-card {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            border: 1px solid #E5E7EB;
            overflow: hidden;
        }

        .card-header {
            padding: 1.5rem;
            border-bottom: 1px solid #F3F4F6;
            display: flex;
            justify-content: between;
            align-items: center;
        }

        .card-content {
            padding: 1.5rem;
        }

        .profile-photo {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--light-blue), var(--primary-blue));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 3rem;
            font-weight: 700;
            border: 4px solid white;
            box-shadow: 0 4px 12px rgba(0, 150, 199, 0.3);
        }

        .progress-bar {
            height: 8px;
            background: #E5E7EB;
            border-radius: 4px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--primary-blue), var(--light-blue));
            border-radius: 4px;
            transition: width 0.3s ease;
        }

        .visitor-item,
        .match-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem 0;
            border-bottom: 1px solid #F3F4F6;
        }

        .visitor-item:last-child,
        .match-item:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .visitor-avatar,
        .match-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--light-blue), var(--primary-blue));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 1.125rem;
        }

        .match-score {
            background: linear-gradient(135deg, #10B981, #059669);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 1rem;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .package-badge {
            background: linear-gradient(135deg, var(--accent-gold), #FF6B6B);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 1rem;
            font-size: 0.875rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .notification-badge {
            background: #EF4444;
            color: white;
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
            border-radius: 1rem;
            min-width: 20px;
            text-align: center;
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

        .quick-action-btn {
            background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
            color: white;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 0.75rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .quick-action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 150, 199, 0.4);
        }

        .activity-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem 0;
            border-bottom: 1px solid #F3F4F6;
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 0.875rem;
        }

        .activity-icon.visit { background: #3B82F6; }
        .activity-icon.match { background: #10B981; }
        .activity-icon.message { background: #8B5CF6; }
        .activity-icon.like { background: #EF4444; }
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

        <!-- User Profile Summary -->
        <div class="p-6 border-b border-gray-200">
            <div class="text-center">
                <div class="profile-photo mx-auto mb-3">
                    <?php echo strtoupper(substr($user['first_name'], 0, 1)); ?>
                </div>
                <h3 class="font-semibold text-gray-800"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h3>
                <p class="text-sm text-gray-600"><?php echo htmlspecialchars($user['profession'] ?? 'Professional'); ?></p>
                <div class="package-badge mt-2">
                    <i class="fas fa-crown"></i>
                    <?php echo htmlspecialchars($user['package_name']); ?>
                </div>
            </div>
        </div>

        <!-- Navigation -->
        <nav class="py-4">
            <a href="#" class="nav-item active" onclick="showSection('dashboard')">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            <a href="#" class="nav-item" onclick="showSection('profile')">
                <i class="fas fa-user"></i>
                <span>My Profile</span>
            </a>
            <a href="#" class="nav-item" onclick="showSection('matches')">
                <i class="fas fa-heart"></i>
                <span>Matches</span>
                <?php if ($stats['received_requests'] > 0): ?>
                <span class="notification-badge"><?php echo $stats['received_requests']; ?></span>
                <?php endif; ?>
            </a>
            <a href="#" class="nav-item" onclick="showSection('messages')">
                <i class="fas fa-comments"></i>
                <span>Messages</span>
            </a>
            <a href="#" class="nav-item" onclick="showSection('shortlist')">
                <i class="fas fa-bookmark"></i>
                <span>Shortlist</span>
            </a>
            <a href="#" class="nav-item" onclick="showSection('visitors')">
                <i class="fas fa-eye"></i>
                <span>Profile Visitors</span>
            </a>
            <a href="#" class="nav-item" onclick="showSection('settings')">
                <i class="fas fa-cog"></i>
                <span>Settings</span>
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
                    <h1 class="text-2xl font-bold text-gray-800">Welcome back, <?php echo htmlspecialchars($user['first_name']); ?>!</h1>
                    <p class="text-gray-600">Here's what's happening with your matrimonial journey</p>
                </div>
                <div class="flex items-center gap-4">
                    <div class="text-right">
                        <p class="text-sm text-gray-600">Requests Remaining</p>
                        <p class="text-lg font-semibold text-blue-600"><?php echo $user['requests_remaining']; ?>/<?php echo $user['max_requests']; ?></p>
                    </div>
                    <button class="quick-action-btn" onclick="window.location.href='../public/browse-members.html'">
                        <i class="fas fa-search"></i>
                        Browse Profiles
                    </button>
                </div>
            </div>
        </div>

        <!-- Dashboard Content -->
        <div class="p-6" id="content-area">
            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="stat-card">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="stat-number"><?php echo $stats['sent_requests']; ?></div>
                            <div class="stat-label">Requests Sent</div>
                        </div>
                        <div class="stat-icon" style="background: linear-gradient(135deg, #3B82F6, #1D4ED8);">
                            <i class="fas fa-paper-plane"></i>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="stat-number"><?php echo $stats['received_requests']; ?></div>
                            <div class="stat-label">Requests Received</div>
                        </div>
                        <div class="stat-icon" style="background: linear-gradient(135deg, #10B981, #059669);">
                            <i class="fas fa-inbox"></i>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="stat-number"><?php echo $stats['connections']; ?></div>
                            <div class="stat-label">Connections</div>
                        </div>
                        <div class="stat-icon" style="background: linear-gradient(135deg, #8B5CF6, #7C3AED);">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="stat-number"><?php echo number_format($stats['total_members']); ?></div>
                            <div class="stat-label">Total Members</div>
                        </div>
                        <div class="stat-icon" style="background: linear-gradient(135deg, #F59E0B, #D97706);">
                            <i class="fas fa-globe"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Dashboard Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Profile Completion -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3 class="text-lg font-semibold text-gray-800">Profile Completion</h3>
                        <span class="text-blue-600 font-semibold">75%</span>
                    </div>
                    <div class="card-content">
                        <div class="progress-bar mb-4">
                            <div class="progress-fill" style="width: 75%;"></div>
                        </div>
                        <div class="space-y-3">
                            <div class="flex items-center justify-between text-sm">
                                <span class="flex items-center gap-2">
                                    <i class="fas fa-check text-green-500"></i>
                                    Basic Information
                                </span>
                                <span class="text-green-500">Complete</span>
                            </div>
                            <div class="flex items-center justify-between text-sm">
                                <span class="flex items-center gap-2">
                                    <i class="fas fa-check text-green-500"></i>
                                    Contact Details
                                </span>
                                <span class="text-green-500">Complete</span>
                            </div>
                            <div class="flex items-center justify-between text-sm">
                                <span class="flex items-center gap-2">
                                    <i class="fas fa-times text-red-500"></i>
                                    Profile Photo
                                </span>
                                <span class="text-red-500">Missing</span>
                            </div>
                            <div class="flex items-center justify-between text-sm">
                                <span class="flex items-center gap-2">
                                    <i class="fas fa-check text-green-500"></i>
                                    About Me
                                </span>
                                <span class="text-green-500">Complete</span>
                            </div>
                        </div>
                        <button class="w-full mt-4 bg-blue-100 text-blue-700 py-2 rounded-lg hover:bg-blue-200 transition-colors">
                            Complete Profile
                        </button>
                    </div>
                </div>

                <!-- Recent Profile Visitors -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3 class="text-lg font-semibold text-gray-800">Recent Visitors</h3>
                        <a href="#" class="text-blue-600 text-sm hover:text-blue-800">View All</a>
                    </div>
                    <div class="card-content">
                        <?php foreach ($recent_visitors as $visitor): ?>
                        <div class="visitor-item">
                            <div class="visitor-avatar">
                                <?php echo strtoupper(substr($visitor['name'], 0, 1)); ?>
                            </div>
                            <div class="flex-1">
                                <h4 class="font-medium text-gray-800"><?php echo htmlspecialchars($visitor['name']); ?></h4>
                                <p class="text-sm text-gray-600"><?php echo $visitor['age']; ?> years, <?php echo htmlspecialchars($visitor['city']); ?></p>
                                <p class="text-xs text-gray-500"><?php echo $visitor['visited']; ?></p>
                            </div>
                            <button class="text-blue-600 hover:text-blue-800">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Recent Matches -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3 class="text-lg font-semibold text-gray-800">Recent Matches</h3>
                        <a href="#" class="text-blue-600 text-sm hover:text-blue-800">View All</a>
                    </div>
                    <div class="card-content">
                        <?php foreach ($recent_matches as $match): ?>
                        <div class="match-item">
                            <div class="match-avatar">
                                <?php echo strtoupper(substr($match['name'], 0, 1)); ?>
                            </div>
                            <div class="flex-1">
                                <h4 class="font-medium text-gray-800"><?php echo htmlspecialchars($match['name']); ?></h4>
                                <p class="text-sm text-gray-600"><?php echo $match['age']; ?> years, <?php echo htmlspecialchars($match['profession']); ?></p>
                            </div>
                            <div class="text-right">
                                <div class="match-score"><?php echo $match['match_score']; ?>% Match</div>
                                <button class="text-red-500 hover:text-red-700 mt-1">
                                    <i class="fas fa-heart"></i>
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="dashboard-card mt-8">
                <div class="card-header">
                    <h3 class="text-lg font-semibold text-gray-800">Recent Activity</h3>
                    <a href="#" class="text-blue-600 text-sm hover:text-blue-800">View All</a>
                </div>
                <div class="card-content">
                    <div class="space-y-4">
                        <div class="activity-item">
                            <div class="activity-icon visit">
                                <i class="fas fa-eye"></i>
                            </div>
                            <div class="flex-1">
                                <p class="text-gray-800"><strong>Priya S.</strong> viewed your profile</p>
                                <p class="text-sm text-gray-500">2 hours ago</p>
                            </div>
                        </div>
                        
                        <div class="activity-item">
                            <div class="activity-icon match">
                                <i class="fas fa-heart"></i>
                            </div>
                            <div class="flex-1">
                                <p class="text-gray-800">You got a new match with <strong>Kasun P.</strong></p>
                                <p class="text-sm text-gray-500">5 hours ago</p>
                            </div>
                        </div>
                        
                        <div class="activity-item">
                            <div class="activity-icon like">
                                <i class="fas fa-thumbs-up"></i>
                            </div>
                            <div class="flex-1">
                                <p class="text-gray-800"><strong>Sanduni F.</strong> liked your profile</p>
                                <p class="text-sm text-gray-500">1 day ago</p>
                            </div>
                        </div>
                        
                        <div class="activity-item">
                            <div class="activity-icon message">
                                <i class="fas fa-comment"></i>
                            </div>
                            <div class="flex-1">
                                <p class="text-gray-800">You received a message from <strong>Roshan W.</strong></p>
                                <p class="text-sm text-gray-500">2 days ago</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.querySelector('.main-content');
            
            sidebar.classList.toggle('mobile-open');
            
            if (window.innerWidth <= 768) {
                document.body.style.overflow = sidebar.classList.contains('mobile-open') ? 'hidden' : 'auto';
            }
        }

        function showSection(section) {
            // Remove active class from all nav items
            document.querySelectorAll('.nav-item').forEach(item => {
                item.classList.remove('active');
            });
            
            // Add active class to clicked item
            event.target.closest('.nav-item').classList.add('active');
            
            // Close mobile sidebar
            if (window.innerWidth <= 768) {
                toggleSidebar();
            }
            
            // Here you would load different content based on the section
            console.log('Loading section:', section);
            
            // For demo purposes, just show an alert
            if (section !== 'dashboard') {
                showNotification(`${section.charAt(0).toUpperCase() + section.slice(1)} section coming soon!`, 'info');
            }
        }

        function showNotification(message, type = 'info') {
            const existingNotifications = document.querySelectorAll('.notification');
            existingNotifications.forEach(notif => notif.remove());

            const notification = document.createElement('div');
            notification.className = 'notification';
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: ${type === 'success' ? '#10B981' : type === 'error' ? '#EF4444' : '#3B82F6'};
                color: white;
                padding: 1rem 1.5rem;
                border-radius: 0.5rem;
                box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
                z-index: 10000;
                transform: translateX(100%);
                transition: transform 0.3s ease;
                max-width: 300px;
                font-weight: 500;
            `;

            notification.innerHTML = `
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
                    <span>${message}</span>
                    <button onclick="this.parentElement.parentElement.remove()" style="background: none; border: none; color: white; margin-left: auto; cursor: pointer;">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;

            document.body.appendChild(notification);

            setTimeout(() => {
                notification.style.transform = 'translateX(0)';
            }, 100);

            setTimeout(() => {
                notification.style.transform = 'translateX(100%)';
                setTimeout(() => {
                    if (notification.parentElement) {
                        notification.remove();
                    }
                }, 300);
            }, 4000);
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

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Dashboard loaded successfully!');
        });
    </script>
</body>
</html>
