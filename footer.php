<?php
/**
 * Helakapuwa.com - Common Footer Component
 * Reusable footer for all pages
 */

// Get current year for copyright
$currentYear = date('Y');

// Check if user is logged in for conditional content
$isLoggedIn = isset($_SESSION['user_id']);

// Footer statistics (these could come from database)
$stats = [
    'total_members' => '50,000+',
    'successful_matches' => '5,000+',
    'active_members' => '10,000+',
    'years_experience' => '10+'
];

// Social media links
$socialLinks = [
    'facebook' => 'https://facebook.com/helakapuwa',
    'instagram' => 'https://instagram.com/helakapuwa',
    'twitter' => 'https://twitter.com/helakapuwa',
    'youtube' => 'https://youtube.com/helakapuwa',
    'linkedin' => 'https://linkedin.com/company/helakapuwa'
];

// Quick links
$quickLinks = [
    'public' => [
        'About Us' => 'about.html',
        'How It Works' => 'index.html#how-it-works',
        'Success Stories' => 'index.html#testimonials',
        'Pricing' => 'pricing.html',
        'Contact Us' => 'contact.html'
    ],
    'member' => [
        'Dashboard' => 'member/dashboard.php',
        'Browse Members' => 'browse-members.html',
        'My Requests' => 'member/my_requests.php',
        'My Connections' => 'member/my_connections.php',
        'Account Settings' => 'member/settings.php'
    ]
];

// Support links
$supportLinks = [
    'Help Center' => 'help.html',
    'Safety Tips' => 'safety.html',
    'FAQ' => 'faq.html',
    'Report Issue' => 'report.html',
    'Contact Support' => 'contact.html'
];

// Legal links
$legalLinks = [
    'Privacy Policy' => 'privacy.html',
    'Terms & Conditions' => 'terms.html',
    'Cookie Policy' => 'cookies.html',
    'Refund Policy' => 'refunds.html',
    'Community Guidelines' => 'guidelines.html'
];
?>

<!-- Footer -->
<footer class="bg-gray-900 text-white relative overflow-hidden">
    <!-- Background Pattern -->
    <div class="absolute inset-0 opacity-5">
        <div class="absolute top-10 left-10 w-32 h-32 bg-primary rounded-full"></div>
        <div class="absolute bottom-20 right-20 w-24 h-24 bg-primary rounded-full"></div>
        <div class="absolute top-1/2 left-1/4 w-16 h-16 bg-primary rounded-full"></div>
    </div>

    <!-- Newsletter Section -->
    <?php if (!$isLoggedIn): ?>
    <div class="bg-primary py-12 relative">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center">
                <h3 class="text-2xl font-bold text-white mb-4">
                    අළුත් සාමාජිකයන් ගැන දැනගන්න
                </h3>
                <p class="text-blue-100 mb-8">
                    ඔබේ preferences වලට ගැලපෙන නව profiles ගැන email මගින් දැනගන්න
                </p>
                <div class="max-w-md mx-auto">
                    <form id="newsletterForm" class="flex flex-col sm:flex-row gap-4">
                        <input type="email" 
                               id="newsletterEmail"
                               placeholder="ඔබේ email address එක"
                               required
                               class="flex-1 px-4 py-3 rounded-lg border-0 text-gray-900 placeholder-gray-500 focus:ring-4 focus:ring-blue-300 outline-none">
                        <button type="submit" 
                                class="bg-white text-primary px-8 py-3 rounded-lg font-semibold hover:bg-gray-100 transition-colors focus:ring-4 focus:ring-blue-300 outline-none">
                            Subscribe
                        </button>
                    </form>
                    <p class="text-blue-100 text-sm mt-3">
                        <i class="fas fa-lock mr-1"></i>
                        ඔබේ email address එක ආරක්ෂිතයි. Spam නැත.
                    </p>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Main Footer Content -->
    <div class="relative py-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-12">
                
                <!-- Company Info -->
                <div class="lg:col-span-1">
                    <div class="mb-6">
                        <a href="<?php echo $isLoggedIn ? 'member/dashboard.php' : 'index.html'; ?>" 
                           class="text-2xl font-bold text-white hover:text-primary transition-colors">
                            <i class="fas fa-heart mr-2 text-red-500"></i>
                            Helakapuwa.com
                        </a>
                        <p class="text-gray-300 mt-4 leading-relaxed">
                            ශ්‍රී ලංකාවේ ප්‍රමුඛතම විවාහ තේරීම් වෙබ් අඩවිය. 
                            ඔබේ ජීවිත සහකරු සොයා ගැනීම සඳහා ආරක්ෂිත සහ විශ්වසනීය platform එකක්.
                        </p>
                    </div>

                    <!-- Statistics -->
                    <div class="grid grid-cols-2 gap-4 mb-6">
                        <div class="text-center p-3 bg-gray-800 rounded-lg">
                            <div class="text-2xl font-bold text-primary"><?php echo $stats['total_members']; ?></div>
                            <div class="text-xs text-gray-400">සාමාජිකයන්</div>
                        </div>
                        <div class="text-center p-3 bg-gray-800 rounded-lg">
                            <div class="text-2xl font-bold text-primary"><?php echo $stats['successful_matches']; ?></div>
                            <div class="text-xs text-gray-400">සාර්ථක විවාහ</div>
                        </div>
                    </div>

                    <!-- Social Media -->
                    <div>
                        <h4 class="text-lg font-semibold mb-4">අප සමඟ සම්බන්ධ වන්න</h4>
                        <div class="flex space-x-3">
                            <a href="<?php echo $socialLinks['facebook']; ?>" 
                               class="w-10 h-10 bg-gray-800 rounded-full flex items-center justify-center text-gray-300 hover:bg-blue-600 hover:text-white transition-all duration-300">
                                <i class="fab fa-facebook-f"></i>
                            </a>
                            <a href="<?php echo $socialLinks['instagram']; ?>" 
                               class="w-10 h-10 bg-gray-800 rounded-full flex items-center justify-center text-gray-300 hover:bg-pink-600 hover:text-white transition-all duration-300">
                                <i class="fab fa-instagram"></i>
                            </a>
                            <a href="<?php echo $socialLinks['twitter']; ?>" 
                               class="w-10 h-10 bg-gray-800 rounded-full flex items-center justify-center text-gray-300 hover:bg-blue-400 hover:text-white transition-all duration-300">
                                <i class="fab fa-twitter"></i>
                            </a>
                            <a href="<?php echo $socialLinks['youtube']; ?>" 
                               class="w-10 h-10 bg-gray-800 rounded-full flex items-center justify-center text-gray-300 hover:bg-red-600 hover:text-white transition-all duration-300">
                                <i class="fab fa-youtube"></i>
                            </a>
                            <a href="<?php echo $socialLinks['linkedin']; ?>" 
                               class="w-10 h-10 bg-gray-800 rounded-full flex items-center justify-center text-gray-300 hover:bg-blue-700 hover:text-white transition-all duration-300">
                                <i class="fab fa-linkedin-in"></i>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Quick Links -->
                <div>
                    <h4 class="text-lg font-semibold mb-6">ඉක්මන් සබැඳි</h4>
                    <ul class="space-y-3">
                        <?php 
                        $links = $isLoggedIn ? $quickLinks['member'] : $quickLinks['public'];
                        foreach ($links as $name => $url): 
                        ?>
                            <li>
                                <a href="<?php echo $url; ?>" 
                                   class="text-gray-300 hover:text-primary transition-colors duration-300 flex items-center">
                                    <i class="fas fa-chevron-right text-xs mr-2 text-primary"></i>
                                    <?php echo $name; ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <!-- Support -->
                <div>
                    <h4 class="text-lg font-semibold mb-6">සහාය</h4>
                    <ul class="space-y-3">
                        <?php foreach ($supportLinks as $name => $url): ?>
                            <li>
                                <a href="<?php echo $url; ?>" 
                                   class="text-gray-300 hover:text-primary transition-colors duration-300 flex items-center">
                                    <i class="fas fa-chevron-right text-xs mr-2 text-primary"></i>
                                    <?php echo $name; ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <!-- Contact Info -->
                    <div class="mt-8">
                        <h5 class="font-semibold mb-4">සම්බන්ධ විය හැකි විට</h5>
                        <div class="space-y-2 text-sm text-gray-300">
                            <div class="flex items-center">
                                <i class="fas fa-clock w-4 mr-3 text-primary"></i>
                                <span>සඳුදා - සිකුරාදා: 9:00 AM - 6:00 PM</span>
                            </div>
                            <div class="flex items-center">
                                <i class="fas fa-clock w-4 mr-3 text-primary"></i>
                                <span>සෙනසුරාදා: 9:00 AM - 1:00 PM</span>
                            </div>
                            <div class="flex items-center">
                                <i class="fas fa-phone w-4 mr-3 text-primary"></i>
                                <a href="tel:+94112345678" class="hover:text-primary transition-colors">
                                    +94 11 234 5678
                                </a>
                            </div>
                            <div class="flex items-center">
                                <i class="fas fa-envelope w-4 mr-3 text-primary"></i>
                                <a href="mailto:support@helakapuwa.com" class="hover:text-primary transition-colors">
                                    support@helakapuwa.com
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Legal & Trust -->
                <div>
                    <h4 class="text-lg font-semibold mb-6">නීතිමය</h4>
                    <ul class="space-y-3">
                        <?php foreach ($legalLinks as $name => $url): ?>
                            <li>
                                <a href="<?php echo $url; ?>" 
                                   class="text-gray-300 hover:text-primary transition-colors duration-300 flex items-center">
                                    <i class="fas fa-chevron-right text-xs mr-2 text-primary"></i>
                                    <?php echo $name; ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <!-- Trust Badges -->
                    <div class="mt-8">
                        <h5 class="font-semibold mb-4">ආරක්ෂණ සහතික</h5>
                        <div class="space-y-3">
                            <div class="flex items-center text-sm text-gray-300">
                                <i class="fas fa-shield-alt w-4 mr-3 text-green-500"></i>
                                <span>SSL Encrypted</span>
                            </div>
                            <div class="flex items-center text-sm text-gray-300">
                                <i class="fas fa-user-shield w-4 mr-3 text-blue-500"></i>
                                <span>Profile Verified</span>
                            </div>
                            <div class="flex items-center text-sm text-gray-300">
                                <i class="fas fa-lock w-4 mr-3 text-purple-500"></i>
                                <span>Privacy Protected</span>
                            </div>
                            <div class="flex items-center text-sm text-gray-300">
                                <i class="fas fa-medal w-4 mr-3 text-yellow-500"></i>
                                <span>Award Winning</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bottom Footer -->
    <div class="border-t border-gray-800 py-8 relative">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col md:flex-row justify-between items-center">
                
                <!-- Copyright -->
                <div class="text-gray-400 text-sm mb-4 md:mb-0">
                    <p>
                        © <?php echo $currentYear; ?> Helakapuwa.com. සියලුම අයිතිවාසිකම් ආරක්ෂිතයි.
                    </p>
                    <p class="mt-1">
                        Made with <i class="fas fa-heart text-red-500 mx-1"></i> in Sri Lanka
                    </p>
                </div>

                <!-- Additional Links -->
                <div class="flex flex-wrap items-center space-x-6 text-sm">
                    <a href="sitemap.xml" class="text-gray-400 hover:text-primary transition-colors">
                        Sitemap
                    </a>
                    <span class="text-gray-600">•</span>
                    <a href="api-docs.html" class="text-gray-400 hover:text-primary transition-colors">
                        API
                    </a>
                    <span class="text-gray-600">•</span>
                    <a href="careers.html" class="text-gray-400 hover:text-primary transition-colors">
                        Careers
                    </a>
                    <span class="text-gray-600">•</span>
                    <a href="press.html" class="text-gray-400 hover:text-primary transition-colors">
                        Press
                    </a>
                </div>
            </div>

            <!-- Language & Currency -->
            <div class="mt-6 pt-6 border-t border-gray-800 flex flex-col sm:flex-row justify-between items-center text-sm text-gray-400">
                <div class="flex items-center space-x-4 mb-3 sm:mb-0">
                    <div class="flex items-center">
                        <i class="fas fa-globe mr-2"></i>
                        <select class="bg-transparent border-0 text-gray-400 text-sm focus:outline-none">
                            <option value="si">සිංහල</option>
                            <option value="ta">தமிழ்</option>
                            <option value="en">English</option>
                        </select>
                    </div>
                    <div class="flex items-center">
                        <i class="fas fa-money-bill-wave mr-2"></i>
                        <span>LKR - ශ්‍රී ලංකා රුපියල්</span>
                    </div>
                </div>
                
                <div class="flex items-center space-x-4">
                    <span>Version 2.0</span>
                    <span>•</span>
                    <span>Build <?php echo date('Ymd'); ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Back to Top Button -->
    <button id="backToTop" 
            class="fixed bottom-6 right-6 w-12 h-12 bg-primary text-white rounded-full shadow-lg hover:bg-primary-dark transition-all duration-300 opacity-0 invisible"
            onclick="scrollToTop()">
        <i class="fas fa-chevron-up"></i>
    </button>
</footer>

<!-- Success Message Modal for Newsletter -->
<div id="newsletterModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="bg-white rounded-lg p-8 text-center max-w-md mx-4">
        <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <i class="fas fa-check text-green-600 text-2xl"></i>
        </div>
        <h3 class="text-xl font-semibold text-gray-900 mb-2">සාර්ථකයි!</h3>
        <p class="text-gray-600 mb-6">
            ඔබ අපගේ newsletter subscription එකට සාර්ථකව ලියාපදිංචි වුණා. 
            නව profiles ගැන email මගින් දැනුම්දීම් ලැබෙනවා.
        </p>
        <button onclick="closeNewsletterModal()" 
                class="bg-primary text-white px-6 py-2 rounded-lg hover:bg-primary-dark transition-colors">
            හරි
        </button>
    </div>
</div>

<!-- Footer Scripts -->
<script>
// Newsletter subscription
document.getElementById('newsletterForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const email = document.getElementById('newsletterEmail').value;
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    
    // Show loading state
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Subscribing...';
    
    try {
        const response = await fetch('api/newsletter_subscribe.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ email: email })
        });
        
        const result = await response.json();
        
        if (result.success) {
            document.getElementById('newsletterModal').classList.remove('hidden');
            this.reset();
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        alert('Newsletter subscription අසාර්ථකයි. කරුණාකර නැවත උත්සාහ කරන්න.');
        console.error('Newsletter error:', error);
    } finally {
        // Reset button state
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    }
});

// Close newsletter modal
function closeNewsletterModal() {
    document.getElementById('newsletterModal').classList.add('hidden');
}

// Back to top functionality
function scrollToTop() {
    window.scrollTo({
        top: 0,
        behavior: 'smooth'
    });
}

// Show/hide back to top button
window.addEventListener('scroll', function() {
    const backToTop = document.getElementById('backToTop');
    
    if (window.scrollY > 500) {
        backToTop.classList.remove('opacity-0', 'invisible');
        backToTop.classList.add('opacity-100', 'visible');
    } else {
        backToTop.classList.remove('opacity-100', 'visible');
        backToTop.classList.add('opacity-0', 'invisible');
    }
});

// Smooth scroll for internal links
document.addEventListener('click', function(e) {
    if (e.target.matches('a[href^="#"]')) {
        e.preventDefault();
        const target = document.querySelector(e.target.getAttribute('href'));
        if (target) {
            target.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }
    }
});

// Footer animations on scroll
const observerOptions = {
    threshold: 0.1,
    rootMargin: '0px 0px -50px 0px'
};

const footerObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            entry.target.style.opacity = '1';
            entry.target.style.transform = 'translateY(0)';
        }
    });
}, observerOptions);

// Observe footer elements
document.querySelectorAll('footer > div > div > div > div').forEach(el => {
    el.style.opacity = '0';
    el.style.transform = 'translateY(20px)';
    el.style.transition = 'all 0.6s ease';
    footerObserver.observe(el);
});

// Language switcher functionality
document.querySelector('footer select')?.addEventListener('change', function(e) {
    const language = e.target.value;
    // Implement language switching logic here
    console.log('Language changed to:', language);
    // You can redirect to different language versions or use i18n
});

// Statistics counter animation
function animateCounters() {
    const counters = document.querySelectorAll('.text-2xl.font-bold.text-primary');
    
    counters.forEach(counter => {
        const text = counter.textContent;
        const number = parseInt(text.replace(/\D/g, ''));
        const suffix = text.replace(/[\d,]/g, '');
        
        let current = 0;
        const increment = number / 100;
        const timer = setInterval(() => {
            current += increment;
            if (current >= number) {
                current = number;
                clearInterval(timer);
            }
            counter.textContent = Math.floor(current).toLocaleString() + suffix;
        }, 20);
    });
}

// Trigger counter animation when footer comes into view
const statsObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            animateCounters();
            statsObserver.unobserve(entry.target);
        }
    });
});

const statsSection = document.querySelector('.grid.grid-cols-2.gap-4.mb-6');
if (statsSection) {
    statsObserver.observe(statsSection);
}
</script>

<!-- JavaScript Libraries -->
<script src="https://unpkg.com/swiper/swiper-bundle.min.js"></script>
<script src="js/main.js"></script>
<script src="js/animations.js"></script>

</body>
</html>
