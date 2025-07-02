<?php
/**
 * Helakapuwa.com - Common Header Component
 * Reusable header for all pages with authentication status
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']);
$currentUser = null;

if ($isLoggedIn) {
    // Get user basic info for header display
    require_once(__DIR__ . '/db_connect.php');
    
    $stmt = $conn->prepare("
        SELECT user_id, first_name, last_name, profile_pic, package_id, requests_remaining 
        FROM users 
        WHERE user_id = ? AND status = 'Active'
    ");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $currentUser = $result->fetch_assoc();
    } else {
        // User not found or inactive, logout
        session_destroy();
        $isLoggedIn = false;
    }
    $stmt->close();
}

// Get current page for active navigation
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$currentPath = $_SERVER['REQUEST_URI'];

// Define navigation menu
$publicMenu = [
    'index' => ['name' => 'මුල් පිටුව', 'url' => 'index.html', 'icon' => 'fas fa-home'],
    'browse' => ['name' => 'සාමාජිකයන්', 'url' => 'browse-members.html', 'icon' => 'fas fa-users'],
    'pricing' => ['name' => 'මිල ගණන්', 'url' => 'pricing.html', 'icon' => 'fas fa-tags'],
    'about' => ['name' => 'අප ගැන', 'url' => 'about.html', 'icon' => 'fas fa-info-circle'],
    'contact' => ['name' => 'සම්බන්ධ වන්න', 'url' => 'contact.html', 'icon' => 'fas fa-envelope']
];

$memberMenu = [
    'dashboard' => ['name' => 'ප්‍රධාන පිටුව', 'url' => 'member/dashboard.php', 'icon' => 'fas fa-tachometer-alt'],
    'browse' => ['name' => 'සාමාජිකයන්', 'url' => 'browse-members.html', 'icon' => 'fas fa-users'],
    'requests' => ['name' => 'ඉල්ලීම්', 'url' => 'member/my_requests.php', 'icon' => 'fas fa-heart'],
    'connections' => ['name' => 'සම්බන්ධතා', 'url' => 'member/my_connections.php', 'icon' => 'fas fa-link'],
    'chat' => ['name' => 'Chat', 'url' => 'member/chat.php', 'icon' => 'fas fa-comments']
];

// Get package info
$packages = [
    1 => ['name' => 'Free', 'color' => 'gray'],
    2 => ['name' => 'Silver', 'color' => 'gray'],
    3 => ['name' => 'Gold', 'color' => 'yellow'],
    4 => ['name' => 'Premium', 'color' => 'purple']
];
?>

<!DOCTYPE html>
<html lang="si" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="ශ්‍රී ලංකාවේ ප්‍රමුඛතම විවාහ තේරීම් වෙබ් අඩවිය. ඔබේ ජීවිත සහකරු සොයා ගන්න Helakapuwa.com හි.">
    <meta name="keywords" content="sri lanka matrimony, wedding, marriage, partner, විවාහ, ජීවිත සහකරු">
    <meta name="author" content="Helakapuwa.com">
    
    <!-- Open Graph Meta Tags -->
    <meta property="og:title" content="Helakapuwa.com - ශ්‍රී ලංකාවේ ප්‍රමුඛ විවාහ තේරීම් වෙබ් අඩවිය">
    <meta property="og:description" content="ඔබේ ජීවිත සහකරු සොයා ගන්න. ආරක්ෂිත සහ විශ්වසනීය platform එකක්.">
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://helakapuwa.com">
    <meta property="og:image" content="img/og-image.jpg">
    
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - Helakapuwa.com' : 'Helakapuwa.com - ඔබේ ජීවිත සහකරු සොයා ගන්න'; ?></title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="img/favicon.ico">
    <link rel="apple-touch-icon" href="img/apple-touch-icon.png">
    
    <!-- CSS Libraries -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/swiper/swiper-bundle.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="css/style.css">
    
    <!-- Custom Styles -->
    <style>
        :root {
            --primary-color: #0096C7;
            --primary-dark: #007BB5;
            --primary-light: #48CAE4;
        }
        
        .bg-primary { background-color: var(--primary-color); }
        .text-primary { color: var(--primary-color); }
        .border-primary { border-color: var(--primary-color); }
        .hover\:bg-primary-dark:hover { background-color: var(--primary-dark); }
        
        .navbar-blur {
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.95);
        }
        
        .dropdown-menu {
            transform: translateY(-10px);
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }
        
        .dropdown:hover .dropdown-menu {
            transform: translateY(0);
            opacity: 1;
            visibility: visible;
        }
        
        .mobile-menu {
            transform: translateX(100%);
            transition: transform 0.3s ease;
        }
        
        .mobile-menu.open {
            transform: translateX(0);
        }
        
        .notification-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #ef4444;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 0.7rem;
            display: flex;
            align-items: center;
            justify-content: center;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        .search-suggestions {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
        }
        
        .search-suggestions.open {
            max-height: 300px;
        }
    </style>
</head>
<body class="bg-gray-50">

<!-- Header/Navigation -->
<header class="sticky top-0 z-40 navbar-blur border-b border-gray-200" id="mainHeader">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center py-4">
            
            <!-- Logo -->
            <div class="flex items-center">
                <a href="<?php echo $isLoggedIn ? 'member/dashboard.php' : 'index.html'; ?>" 
                   class="text-2xl font-bold text-primary hover:text-primary-dark transition-colors">
                    <i class="fas fa-heart mr-2 text-red-500"></i>
                    <span class="bg-gradient-to-r from-primary to-primary-dark bg-clip-text text-transparent">
                        Helakapuwa.com
                    </span>
                </a>
            </div>

            <!-- Desktop Navigation -->
            <nav class="hidden lg:flex items-center space-x-8">
                <?php 
                $menuItems = $isLoggedIn ? $memberMenu : $publicMenu;
                foreach ($menuItems as $key => $item): 
                    $isActive = strpos($currentPath, $key) !== false || 
                               ($key === 'index' && ($currentPage === 'index' || $currentPage === 'dashboard'));
                ?>
                    <a href="<?php echo $item['url']; ?>" 
                       class="flex items-center space-x-2 px-3 py-2 rounded-lg text-gray-700 hover:text-primary hover:bg-blue-50 transition-colors <?php echo $isActive ? 'text-primary bg-blue-50' : ''; ?>">
                        <i class="<?php echo $item['icon']; ?> text-sm"></i>
                        <span><?php echo $item['name']; ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>

            <!-- Right Side Menu -->
            <div class="flex items-center space-x-4">
                
                <?php if ($isLoggedIn): ?>
                    <!-- Search (for logged in users) -->
                    <div class="hidden md:block relative">
                        <div class="relative">
                            <input type="text" 
                                   id="headerSearch"
                                   placeholder="සාමාජිකයන් සොයන්න..."
                                   class="w-64 px-4 py-2 pl-10 pr-4 border border-gray-300 rounded-full focus:ring-2 focus:ring-primary focus:border-primary outline-none transition-colors">
                            <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        </div>
                        <div id="searchSuggestions" class="search-suggestions absolute top-full left-0 right-0 bg-white border border-gray-200 rounded-lg shadow-lg mt-1">
                            <!-- Search suggestions will be populated via AJAX -->
                        </div>
                    </div>

                    <!-- Notifications -->
                    <div class="relative dropdown">
                        <button class="relative p-2 text-gray-600 hover:text-primary transition-colors">
                            <i class="fas fa-bell text-xl"></i>
                            <span class="notification-badge" id="notificationCount">0</span>
                        </button>
                        <div class="dropdown-menu absolute right-0 mt-2 w-80 bg-white rounded-lg shadow-lg border border-gray-200 py-2">
                            <div class="px-4 py-2 border-b border-gray-200">
                                <h3 class="font-semibold text-gray-900">දැනුම්දීම්</h3>
                            </div>
                            <div id="notificationList" class="max-h-64 overflow-y-auto">
                                <!-- Notifications will be loaded via AJAX -->
                                <div class="px-4 py-3 text-center text-gray-500">
                                    නව දැනුම්දීම් නැත
                                </div>
                            </div>
                            <div class="px-4 py-2 border-t border-gray-200">
                                <a href="member/notifications.php" class="text-primary hover:underline text-sm">
                                    සියලුම දැනුම්දීම් බලන්න
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- User Profile Dropdown -->
                    <div class="relative dropdown">
                        <button class="flex items-center space-x-2 p-2 rounded-lg hover:bg-gray-100 transition-colors">
                            <img src="<?php echo $currentUser['profile_pic'] ?: 'img/default-avatar.jpg'; ?>" 
                                 alt="Profile" 
                                 class="w-8 h-8 rounded-full object-cover border-2 border-primary"
                                 onerror="this.src='img/default-avatar.jpg'">
                            <div class="hidden sm:block text-left">
                                <div class="text-sm font-medium text-gray-900">
                                    <?php echo htmlspecialchars($currentUser['first_name']); ?>
                                </div>
                                <div class="text-xs text-gray-500">
                                    <?php 
                                    $packageInfo = $packages[$currentUser['package_id']] ?? $packages[1];
                                    echo $packageInfo['name']; 
                                    ?>
                                </div>
                            </div>
                            <i class="fas fa-chevron-down text-gray-400 text-sm"></i>
                        </button>
                        
                        <div class="dropdown-menu absolute right-0 mt-2 w-64 bg-white rounded-lg shadow-lg border border-gray-200 py-2">
                            <!-- User Info -->
                            <div class="px-4 py-3 border-b border-gray-200">
                                <div class="flex items-center space-x-3">
                                    <img src="<?php echo $currentUser['profile_pic'] ?: 'img/default-avatar.jpg'; ?>" 
                                         alt="Profile" 
                                         class="w-12 h-12 rounded-full object-cover"
                                         onerror="this.src='img/default-avatar.jpg'">
                                    <div>
                                        <div class="font-medium text-gray-900">
                                            <?php echo htmlspecialchars($currentUser['first_name'] . ' ' . $currentUser['last_name']); ?>
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            <?php echo $packageInfo['name']; ?> Package
                                        </div>
                                        <div class="text-xs text-primary">
                                            <?php echo $currentUser['requests_remaining']; ?> requests remaining
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Menu Items -->
                            <a href="member/dashboard.php" class="flex items-center px-4 py-2 text-gray-700 hover:bg-gray-100">
                                <i class="fas fa-tachometer-alt w-5"></i>
                                <span class="ml-3">ප්‍රධාන පිටුව</span>
                            </a>
                            
                            <a href="member/edit_profile.php" class="flex items-center px-4 py-2 text-gray-700 hover:bg-gray-100">
                                <i class="fas fa-user-edit w-5"></i>
                                <span class="ml-3">Profile සංස්කරණය</span>
                            </a>
                            
                            <a href="member/my_requests.php" class="flex items-center px-4 py-2 text-gray-700 hover:bg-gray-100">
                                <i class="fas fa-heart w-5"></i>
                                <span class="ml-3">මගේ ඉල්ලීම්</span>
                            </a>
                            
                            <a href="member/my_connections.php" class="flex items-center px-4 py-2 text-gray-700 hover:bg-gray-100">
                                <i class="fas fa-link w-5"></i>
                                <span class="ml-3">මගේ සම්බන්ධතා</span>
                            </a>
                            
                            <div class="border-t border-gray-200 mt-2 pt-2">
                                <a href="pricing.html" class="flex items-center px-4 py-2 text-gray-700 hover:bg-gray-100">
                                    <i class="fas fa-crown w-5 text-yellow-500"></i>
                                    <span class="ml-3">Package Upgrade</span>
                                </a>
                                
                                <a href="member/settings.php" class="flex items-center px-4 py-2 text-gray-700 hover:bg-gray-100">
                                    <i class="fas fa-cog w-5"></i>
                                    <span class="ml-3">සැකසුම්</span>
                                </a>
                                
                                <a href="api/logout.php" class="flex items-center px-4 py-2 text-red-600 hover:bg-red-50">
                                    <i class="fas fa-sign-out-alt w-5"></i>
                                    <span class="ml-3">ඉවත් වන්න</span>
                                </a>
                            </div>
                        </div>
                    </div>

                <?php else: ?>
                    <!-- Guest Menu -->
                    <div class="hidden sm:flex items-center space-x-4">
                        <a href="login.html" 
                           class="text-gray-600 hover:text-primary transition-colors">
                            ප්‍රවේශ වන්න
                        </a>
                        <a href="register.html" 
                           class="bg-primary text-white px-6 py-2 rounded-lg hover:bg-primary-dark transition-colors">
                            ලියාපදිංචි වන්න
                        </a>
                    </div>
                <?php endif; ?>

                <!-- Mobile Menu Button -->
                <button class="lg:hidden p-2 text-gray-600 hover:text-primary transition-colors" 
                        onclick="toggleMobileMenu()">
                    <i class="fas fa-bars text-xl" id="mobileMenuIcon"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- Mobile Menu -->
    <div id="mobileMenu" class="mobile-menu lg:hidden fixed top-0 right-0 h-full w-80 bg-white shadow-xl z-50">
        <div class="p-6">
            <!-- Mobile Menu Header -->
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-lg font-semibold text-gray-900">Menu</h3>
                <button onclick="toggleMobileMenu()" class="p-2 text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <?php if ($isLoggedIn): ?>
                <!-- Mobile User Info -->
                <div class="flex items-center space-x-3 p-4 bg-gray-50 rounded-lg mb-6">
                    <img src="<?php echo $currentUser['profile_pic'] ?: 'img/default-avatar.jpg'; ?>" 
                         alt="Profile" 
                         class="w-12 h-12 rounded-full object-cover"
                         onerror="this.src='img/default-avatar.jpg'">
                    <div>
                        <div class="font-medium text-gray-900">
                            <?php echo htmlspecialchars($currentUser['first_name']); ?>
                        </div>
                        <div class="text-sm text-gray-500">
                            <?php echo $packageInfo['name']; ?> Package
                        </div>
                    </div>
                </div>

                <!-- Mobile Search -->
                <div class="mb-6">
                    <input type="text" 
                           placeholder="සාමාජිකයන් සොයන්න..."
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary outline-none">
                </div>
            <?php endif; ?>

            <!-- Mobile Navigation -->
            <nav class="space-y-2">
                <?php 
                $menuItems = $isLoggedIn ? $memberMenu : $publicMenu;
                foreach ($menuItems as $key => $item): 
                    $isActive = strpos($currentPath, $key) !== false || 
                               ($key === 'index' && ($currentPage === 'index' || $currentPage === 'dashboard'));
                ?>
                    <a href="<?php echo $item['url']; ?>" 
                       class="flex items-center space-x-3 p-3 rounded-lg text-gray-700 hover:bg-blue-50 hover:text-primary transition-colors <?php echo $isActive ? 'bg-blue-50 text-primary' : ''; ?>">
                        <i class="<?php echo $item['icon']; ?> w-5"></i>
                        <span><?php echo $item['name']; ?></span>
                    </a>
                <?php endforeach; ?>

                <?php if ($isLoggedIn): ?>
                    <div class="border-t border-gray-200 pt-4 mt-4">
                        <a href="member/settings.php" 
                           class="flex items-center space-x-3 p-3 rounded-lg text-gray-700 hover:bg-gray-100">
                            <i class="fas fa-cog w-5"></i>
                            <span>සැකසුම්</span>
                        </a>
                        <a href="api/logout.php" 
                           class="flex items-center space-x-3 p-3 rounded-lg text-red-600 hover:bg-red-50">
                            <i class="fas fa-sign-out-alt w-5"></i>
                            <span>ඉවත් වන්න</span>
                        </a>
                    </div>
                <?php else: ?>
                    <div class="border-t border-gray-200 pt-4 mt-4 space-y-2">
                        <a href="login.html" 
                           class="block w-full text-center py-3 border border-primary text-primary rounded-lg hover:bg-blue-50 transition-colors">
                            ප්‍රවේශ වන්න
                        </a>
                        <a href="register.html" 
                           class="block w-full text-center py-3 bg-primary text-white rounded-lg hover:bg-primary-dark transition-colors">
                            ලියාපදිංචි වන්න
                        </a>
                    </div>
                <?php endif; ?>
            </nav>
        </div>
    </div>

    <!-- Mobile Menu Overlay -->
    <div id="mobileMenuOverlay" class="fixed inset-0 bg-black bg-opacity-50 z-40 hidden lg:hidden" 
         onclick="toggleMobileMenu()"></div>
</header>

<script>
// Mobile menu toggle
function toggleMobileMenu() {
    const mobileMenu = document.getElementById('mobileMenu');
    const overlay = document.getElementById('mobileMenuOverlay');
    const icon = document.getElementById('mobileMenuIcon');
    
    if (mobileMenu.classList.contains('open')) {
        mobileMenu.classList.remove('open');
        overlay.classList.add('hidden');
        icon.classList.remove('fa-times');
        icon.classList.add('fa-bars');
        document.body.style.overflow = 'auto';
    } else {
        mobileMenu.classList.add('open');
        overlay.classList.remove('hidden');
        icon.classList.remove('fa-bars');
        icon.classList.add('fa-times');
        document.body.style.overflow = 'hidden';
    }
}

<?php if ($isLoggedIn): ?>
// Search functionality
document.getElementById('headerSearch')?.addEventListener('input', debounce(function(e) {
    const query = e.target.value.trim();
    const suggestions = document.getElementById('searchSuggestions');
    
    if (query.length >= 2) {
        fetch('api/search_members.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ query: query, limit: 5 })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.members.length > 0) {
                suggestions.innerHTML = data.members.map(member => `
                    <a href="member-profile.html?id=${member.user_id}" 
                       class="flex items-center p-3 hover:bg-gray-100 border-b border-gray-100 last:border-b-0">
                        <img src="${member.profile_pic || 'img/default-avatar.jpg'}" 
                             class="w-8 h-8 rounded-full object-cover mr-3"
                             onerror="this.src='img/default-avatar.jpg'">
                        <div>
                            <div class="font-medium text-gray-900">${member.first_name} ${member.last_name}</div>
                            <div class="text-sm text-gray-500">${member.profession} - ${member.location}</div>
                        </div>
                    </a>
                `).join('');
                suggestions.classList.add('open');
            } else {
                suggestions.classList.remove('open');
            }
        })
        .catch(error => {
            console.error('Search error:', error);
            suggestions.classList.remove('open');
        });
    } else {
        suggestions.classList.remove('open');
    }
}, 300));

// Close search suggestions when clicking outside
document.addEventListener('click', function(e) {
    if (!e.target.closest('#headerSearch') && !e.target.closest('#searchSuggestions')) {
        document.getElementById('searchSuggestions').classList.remove('open');
    }
});

// Load notifications
function loadNotifications() {
    fetch('api/get_notifications.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('notificationCount').textContent = data.unread_count;
                const notificationList = document.getElementById('notificationList');
                
                if (data.notifications.length > 0) {
                    notificationList.innerHTML = data.notifications.map(notification => `
                        <div class="px-4 py-3 hover:bg-gray-50 border-b border-gray-100 last:border-b-0 ${!notification.is_read ? 'bg-blue-50' : ''}">
                            <div class="flex items-start space-x-3">
                                <i class="fas fa-${notification.icon} text-primary mt-1"></i>
                                <div class="flex-1">
                                    <p class="text-sm text-gray-900">${notification.message}</p>
                                    <p class="text-xs text-gray-500 mt-1">${notification.time_ago}</p>
                                </div>
                            </div>
                        </div>
                    `).join('');
                } else {
                    notificationList.innerHTML = '<div class="px-4 py-3 text-center text-gray-500">නව දැනුම්දීම් නැත</div>';
                }
            }
        })
        .catch(error => console.error('Notification load error:', error));
}

// Load notifications on page load and every 30 seconds
loadNotifications();
setInterval(loadNotifications, 30000);
<?php endif; ?>

// Utility function
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Header scroll effect
window.addEventListener('scroll', function() {
    const header = document.getElementById('mainHeader');
    if (window.scrollY > 10) {
        header.classList.add('shadow-lg');
    } else {
        header.classList.remove('shadow-lg');
    }
});
</script>
