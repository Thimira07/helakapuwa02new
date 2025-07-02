/**
 * Helakapuwa.com - Complete JavaScript Functions
 * Main JavaScript file for all interactions and animations
 */

// Global Variables
let isLoading = false;
let testimonialsSwiper = null;
let membersSwiper = null;

// DOM Content Loaded Event
document.addEventListener('DOMContentLoaded', function() {
    console.log('🎉 Helakapuwa.com is loading...');
    initializeApp();
});

/**
 * Initialize the application
 */
function initializeApp() {
    // Initialize all components
    initializeNavbar();
    initializeHeroAnimations();
    initializeCounters();
    initializeCarousels();
    initializeScrollEffects();
    initializeFormHandlers();
    initializeMobileMenu();
    initializeBackToTop();
    initializePageAnimations();
    initializeSmoothScrolling();
    
    console.log('✅ Helakapuwa.com loaded successfully!');
}

/**
 * Navbar functionality
 */
function initializeNavbar() {
    const navbar = document.querySelector('.navbar');
    
    if (!navbar) return;
    
    // Scroll effect for navbar
    let lastScrollTop = 0;
    
    window.addEventListener('scroll', function() {
        const currentScroll = window.pageYOffset || document.documentElement.scrollTop;
        
        // Add scrolled class
        if (currentScroll > 50) {
            navbar.classList.add('scrolled');
        } else {
            navbar.classList.remove('scrolled');
        }
        
        // Hide/show navbar on scroll (optional)
        if (currentScroll > lastScrollTop && currentScroll > 100) {
            // Scrolling down
            navbar.style.transform = 'translateY(-100%)';
        } else {
            // Scrolling up
            navbar.style.transform = 'translateY(0)';
        }
        
        lastScrollTop = currentScroll;
    });
    
    // Active link highlighting
    const navLinks = document.querySelectorAll('.navbar-nav a[href^="#"]');
    const sections = document.querySelectorAll('section[id]');
    
    window.addEventListener('scroll', function() {
        const currentPos = window.scrollY + 100;
        
        sections.forEach(section => {
            const sectionTop = section.offsetTop;
            const sectionHeight = section.offsetHeight;
            const sectionId = section.getAttribute('id');
            
            if (currentPos >= sectionTop && currentPos < sectionTop + sectionHeight) {
                navLinks.forEach(link => {
                    link.classList.remove('active');
                    if (link.getAttribute('href') === `#${sectionId}`) {
                        link.classList.add('active');
                    }
                });
            }
        });
    });
}

/**
 * Mobile menu functionality
 */
function initializeMobileMenu() {
    const mobileMenuBtn = document.getElementById('mobile-menu-btn');
    const mobileMenu = document.getElementById('mobile-menu');
    
    if (!mobileMenuBtn || !mobileMenu) return;
    
    mobileMenuBtn.addEventListener('click', function() {
        const isOpen = !mobileMenu.classList.contains('hidden');
        
        if (isOpen) {
            // Close menu
            mobileMenu.classList.add('hidden');
            mobileMenuBtn.innerHTML = '<i class="fas fa-bars text-xl"></i>';
        } else {
            // Open menu
            mobileMenu.classList.remove('hidden');
            mobileMenuBtn.innerHTML = '<i class="fas fa-times text-xl"></i>';
        }
    });
    
    // Close menu when clicking on links
    const mobileLinks = mobileMenu.querySelectorAll('a');
    mobileLinks.forEach(link => {
        link.addEventListener('click', function() {
            mobileMenu.classList.add('hidden');
            mobileMenuBtn.innerHTML = '<i class="fas fa-bars text-xl"></i>';
        });
    });
    
    // Close menu when clicking outside
    document.addEventListener('click', function(e) {
        if (!mobileMenu.contains(e.target) && !mobileMenuBtn.contains(e.target)) {
            mobileMenu.classList.add('hidden');
            mobileMenuBtn.innerHTML = '<i class="fas fa-bars text-xl"></i>';
        }
    });
}

/**
 * Hero section animations
 */
function initializeHeroAnimations() {
    const heroElements = document.querySelectorAll('.hero-bg h1, .hero-bg p, .hero-bg .btn');
    
    // Animate hero elements on load
    heroElements.forEach((element, index) => {
        element.style.opacity = '0';
        element.style.transform = 'translateY(30px)';
        
        setTimeout(() => {
            element.style.transition = 'all 0.8s ease';
            element.style.opacity = '1';
            element.style.transform = 'translateY(0)';
        }, 200 * (index + 1));
    });
    
    // Parallax effect for hero background
    window.addEventListener('scroll', function() {
        const scrolled = window.pageYOffset;
        const hero = document.querySelector('.hero-bg');
        
        if (hero) {
            const rate = scrolled * -0.5;
            hero.style.transform = `translateY(${rate}px)`;
        }
    });
}

/**
 * Statistics counter animation
 */
function initializeCounters() {
    const counters = [
        { element: document.getElementById('stat-members'), target: 15420, suffix: '' },
        { element: document.getElementById('stat-matches'), target: 2340, suffix: '' },
        { element: document.getElementById('stat-marriages'), target: 1876, suffix: '' },
        { element: document.getElementById('stat-cities'), target: 25, suffix: '' }
    ];
    
    function animateCounter(element, target, duration = 2000, suffix = '') {
        if (!element) return;
        
        let start = 0;
        const increment = target / (duration / 16);
        
        const timer = setInterval(() => {
            start += increment;
            if (start >= target) {
                element.textContent = target.toLocaleString() + suffix;
                clearInterval(timer);
            } else {
                element.textContent = Math.floor(start).toLocaleString() + suffix;
            }
        }, 16);
    }
    
    // Intersection Observer for counter animation
    const observerOptions = {
        threshold: 0.5,
        rootMargin: '0px 0px -100px 0px'
    };
    
    const counterObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                counters.forEach(counter => {
                    if (counter.element) {
                        animateCounter(counter.element, counter.target, 2000, counter.suffix);
                    }
                });
                counterObserver.unobserve(entry.target);
            }
        });
    }, observerOptions);
    
    const heroSection = document.querySelector('#home');
    if (heroSection) {
        counterObserver.observe(heroSection);
    }
}

/**
 * Initialize Swiper carousels
 */
function initializeCarousels() {
    // Check if Swiper is loaded
    if (typeof Swiper === 'undefined') {
        console.warn('Swiper library not loaded');
        return;
    }
    
    // Testimonials Swiper
    const testimonialsContainer = document.querySelector('.testimonials-swiper');
    if (testimonialsContainer) {
        testimonialsSwiper = new Swiper('.testimonials-swiper', {
            slidesPerView: 1,
            spaceBetween: 30,
            loop: true,
            autoplay: {
                delay: 5000,
                disableOnInteraction: false,
            },
            pagination: {
                el: '.testimonials-swiper .swiper-pagination',
                clickable: true,
                dynamicBullets: true,
            },
            navigation: {
                nextEl: '.testimonials-swiper .swiper-button-next',
                prevEl: '.testimonials-swiper .swiper-button-prev',
            },
            breakpoints: {
                640: {
                    slidesPerView: 1,
                    spaceBetween: 20,
                },
                768: {
                    slidesPerView: 2,
                    spaceBetween: 30,
                },
                1024: {
                    slidesPerView: 3,
                    spaceBetween: 30,
                }
            },
            effect: 'slide',
            speed: 600,
            on: {
                init: function() {
                    console.log('Testimonials carousel initialized');
                }
            }
        });
    }
    
    // Members Swiper
    const membersContainer = document.querySelector('.members-swiper');
    if (membersContainer) {
        membersSwiper = new Swiper('.members-swiper', {
            slidesPerView: 1,
            spaceBetween: 20,
            loop: true,
            autoplay: {
                delay: 3000,
                disableOnInteraction: false,
            },
            pagination: {
                el: '.members-swiper .swiper-pagination',
                clickable: true,
                dynamicBullets: true,
            },
            navigation: {
                nextEl: '.members-swiper .swiper-button-next',
                prevEl: '.members-swiper .swiper-button-prev',
            },
            breakpoints: {
                480: {
                    slidesPerView: 2,
                    spaceBetween: 15,
                },
                768: {
                    slidesPerView: 3,
                    spaceBetween: 20,
                },
                1024: {
                    slidesPerView: 4,
                    spaceBetween: 20,
                },
                1280: {
                    slidesPerView: 5,
                    spaceBetween: 20,
                }
            },
            effect: 'slide',
            speed: 500,
            on: {
                init: function() {
                    console.log('Members carousel initialized');
                }
            }
        });
    }
    
    // Add hover pause functionality
    [testimonialsSwiper, membersSwiper].forEach(swiper => {
        if (swiper && swiper.el) {
            swiper.el.addEventListener('mouseenter', () => {
                swiper.autoplay.stop();
            });
            
            swiper.el.addEventListener('mouseleave', () => {
                swiper.autoplay.start();
            });
        }
    });
}

/**
 * Scroll effects and animations
 */
function initializeScrollEffects() {
    // Intersection Observer for scroll animations
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };
    
    const scrollObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('animate-in');
                
                // Add stagger animation for child elements
                const children = entry.target.querySelectorAll('.card-hover, .step-item, .testimonial-card, .member-card');
                children.forEach((child, index) => {
                    setTimeout(() => {
                        child.style.opacity = '1';
                        child.style.transform = 'translateY(0)';
                    }, index * 100);
                });
            }
        });
    }, observerOptions);
    
    // Observe sections for animations
    const sections = document.querySelectorAll('section');
    sections.forEach(section => {
        section.style.opacity = '0';
        section.style.transform = 'translateY(30px)';
        section.style.transition = 'all 0.8s ease';
        scrollObserver.observe(section);
    });
    
    // Prepare cards for stagger animation
    const cards = document.querySelectorAll('.card-hover, .step-item, .testimonial-card, .member-card');
    cards.forEach(card => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(30px)';
        card.style.transition = 'all 0.6s ease';
    });
    
    // Parallax effect for certain elements
    window.addEventListener('scroll', throttle(function() {
        const scrolled = window.pageYOffset;
        
        // Parallax for step numbers
        const stepNumbers = document.querySelectorAll('.step-number');
        stepNumbers.forEach((step, index) => {
            const rate = scrolled * 0.1 * (index % 2 === 0 ? 1 : -1);
            step.style.transform = `translateY(${rate}px)`;
        });
        
        // Parallax for testimonial cards
        const testimonials = document.querySelectorAll('.testimonial-card');
        testimonials.forEach((card, index) => {
            const rate = scrolled * 0.05 * (index % 2 === 0 ? 1 : -1);
            card.style.transform = `translateY(${rate}px)`;
        });
    }, 16));
}

/**
 * Form handlers and interactions
 */
function initializeFormHandlers() {
    // Newsletter signup (if exists)
    const newsletterForms = document.querySelectorAll('.newsletter-form');
    newsletterForms.forEach(form => {
        form.addEventListener('submit', handleNewsletterSubmit);
    });
    
    // Contact form (if exists)
    const contactForms = document.querySelectorAll('.contact-form');
    contactForms.forEach(form => {
        form.addEventListener('submit', handleContactSubmit);
    });
    
    // Button loading states
    const buttons = document.querySelectorAll('.btn-primary, .member-btn');
    buttons.forEach(button => {
        button.addEventListener('click', function(e) {
            if (this.classList.contains('loading')) return;
            
            // If it's a navigation link, add loading state
            const href = this.getAttribute('href');
            if (href && (href.includes('.html') || href.includes('.php'))) {
                e.preventDefault();
                addLoadingState(this);
                
                setTimeout(() => {
                    window.location.href = href;
                }, 800);
            }
        });
    });
}

/**
 * Newsletter signup handler
 */
function handleNewsletterSubmit(e) {
    e.preventDefault();
    const form = e.target;
    const email = form.querySelector('input[type="email"]').value;
    const button = form.querySelector('button');
    
    addLoadingState(button);
    
    // Simulate API call
    setTimeout(() => {
        removeLoadingState(button, 'Subscribed!');
        form.reset();
        showNotification('Successfully subscribed to newsletter!', 'success');
        
        setTimeout(() => {
            button.textContent = 'Subscribe';
        }, 2000);
    }, 1500);
}

/**
 * Contact form handler
 */
function handleContactSubmit(e) {
    e.preventDefault();
    const form = e.target;
    const button = form.querySelector('button[type="submit"]');
    
    addLoadingState(button);
    
    // Simulate API call
    setTimeout(() => {
        removeLoadingState(button, 'Message Sent!');
        form.reset();
        showNotification('Message sent successfully! We\'ll get back to you soon.', 'success');
        
        setTimeout(() => {
            button.textContent = 'Send Message';
        }, 2000);
    }, 2000);
}

/**
 * Back to top functionality
 */
function initializeBackToTop() {
    const backToTopBtn = document.getElementById('backToTop');
    
    if (!backToTopBtn) return;
    
    window.addEventListener('scroll', function() {
        if (window.scrollY > 300) {
            backToTopBtn.style.opacity = '1';
            backToTopBtn.style.pointerEvents = 'auto';
            backToTopBtn.classList.add('visible');
        } else {
            backToTopBtn.style.opacity = '0';
            backToTopBtn.style.pointerEvents = 'none';
            backToTopBtn.classList.remove('visible');
        }
    });
    
    backToTopBtn.addEventListener('click', function() {
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    });
}

/**
 * Page load animations
 */
function initializePageAnimations() {
    // Add fade-in animation to main elements
    const animateElements = [
        '.navbar',
        '.hero-bg .container > div',
        '.stats-grid',
        '.section-divider'
    ];
    
    animateElements.forEach((selector, index) => {
        const elements = document.querySelectorAll(selector);
        elements.forEach(element => {
            element.style.opacity = '0';
            element.style.transform = 'translateY(20px)';
            element.style.transition = 'all 0.8s ease';
            
            setTimeout(() => {
                element.style.opacity = '1';
                element.style.transform = 'translateY(0)';
            }, index * 200);
        });
    });
}

/**
 * Smooth scrolling for anchor links
 */
function initializeSmoothScrolling() {
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            
            if (target) {
                const headerHeight = document.querySelector('.navbar').offsetHeight;
                const targetPosition = target.offsetTop - headerHeight;
                
                window.scrollTo({
                    top: targetPosition,
                    behavior: 'smooth'
                });
            }
        });
    });
}

/**
 * Utility Functions
 */

// Throttle function for performance
function throttle(func, limit) {
    let inThrottle;
    return function() {
        const args = arguments;
        const context = this;
        if (!inThrottle) {
            func.apply(context, args);
            inThrottle = true;
            setTimeout(() => inThrottle = false, limit);
        }
    }
}

// Debounce function for performance
function debounce(func, wait, immediate) {
    let timeout;
    return function executedFunction() {
        const context = this;
        const args = arguments;
        const later = function() {
            timeout = null;
            if (!immediate) func.apply(context, args);
        };
        const callNow = immediate && !timeout;
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
        if (callNow) func.apply(context, args);
    };
}

// Add loading state to button
function addLoadingState(button) {
    if (!button || button.classList.contains('loading')) return;
    
    button.classList.add('loading');
    button.disabled = true;
    
    const originalText = button.textContent;
    button.dataset.originalText = originalText;
    button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Loading...';
}

// Remove loading state from button
function removeLoadingState(button, newText = null) {
    if (!button) return;
    
    button.classList.remove('loading');
    button.disabled = false;
    
    const text = newText || button.dataset.originalText || 'Submit';
    button.innerHTML = text;
}

// Show notification
function showNotification(message, type = 'info', duration = 5000) {
    // Remove existing notifications
    const existingNotifications = document.querySelectorAll('.notification');
    existingNotifications.forEach(notif => notif.remove());
    
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
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
    
    // Animate in
    setTimeout(() => {
        notification.style.transform = 'translateX(0)';
    }, 100);
    
    // Auto remove
    setTimeout(() => {
        notification.style.transform = 'translateX(100%)';
        setTimeout(() => {
            if (notification.parentElement) {
                notification.remove();
            }
        }, 300);
    }, duration);
}

// Format number with commas
function formatNumber(num) {
    return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
}

// Get random element from array
function getRandomElement(arr) {
    return arr[Math.floor(Math.random() * arr.length)];
}

// Check if element is in viewport
function isInViewport(element) {
    const rect = element.getBoundingClientRect();
    return (
        rect.top >= 0 &&
        rect.left >= 0 &&
        rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) &&
        rect.right <= (window.innerWidth || document.documentElement.clientWidth)
    );
}

// Lazy load images
function lazyLoadImages() {
    const images = document.querySelectorAll('img[data-src]');
    const imageObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const img = entry.target;
                img.src = img.dataset.src;
                img.classList.remove('lazy');
                imageObserver.unobserve(img);
            }
        });
    });
    
    images.forEach(img => imageObserver.observe(img));
}

// Initialize lazy loading on page load
document.addEventListener('DOMContentLoaded', lazyLoadImages);

/**
 * API Functions (for future integration)
 */

// Fetch recent members
async function fetchRecentMembers() {
    try {
        const response = await fetch('/api/get_members.php?recent=true&limit=10');
        const data = await response.json();
        
        if (data.success) {
            updateMembersCarousel(data.members);
        } else {
            console.error('Failed to fetch members:', data.message);
        }
    } catch (error) {
        console.error('Error fetching members:', error);
    }
}

// Update members carousel with real data
function updateMembersCarousel(members) {
    const container = document.getElementById('members-container');
    if (!container || !members.length) return;
    
    container.innerHTML = '';
    
    members.forEach(member => {
        const slide = document.createElement('div');
        slide.className = 'swiper-slide';
        slide.innerHTML = `
            <div class="member-card p-6 text-center">
                <div class="profile-img mx-auto mb-4">${member.first_name.charAt(0)}</div>
                <h3 class="font-semibold text-gray-800 mb-2">${member.first_name}</h3>
                <p class="text-gray-600 text-sm mb-2">${member.age} Years, ${member.city}</p>
                <p class="text-gray-500 text-xs mb-4">${member.profession}</p>
                <button class="bg-blue-600 text-white px-4 py-2 rounded-full text-sm hover:bg-blue-700 transition-colors" 
                        onclick="viewProfile(${member.user_id})">
                    View Profile
                </button>
            </div>
        `;
        container.appendChild(slide);
    });
    
    // Reinitialize Swiper if it exists
    if (membersSwiper) {
        membersSwiper.update();
    }
}

// View profile function
function viewProfile(userId) {
    addLoadingState(event.target);
    
    setTimeout(() => {
        window.location.href = `member-profile.html?id=${userId}`;
    }, 500);
}

/**
 * Error Handling
 */
window.addEventListener('error', function(e) {
    console.error('JavaScript Error:', e.error);
    // Log error to analytics service if available
});

window.addEventListener('unhandledrejection', function(e) {
    console.error('Unhandled Promise Rejection:', e.reason);
    // Log error to analytics service if available
});

/**
 * Performance Monitoring
 */
window.addEventListener('load', function() {
    // Log page load performance
    if ('performance' in window) {
        const perfData = performance.getEntriesByType('navigation')[0];
        console.log('Page Load Time:', perfData.loadEventEnd - perfData.loadEventStart, 'ms');
    }
});

/**
 * Export functions for global access
 */
window.HelakapuwaApp = {
    showNotification,
    addLoadingState,
    removeLoadingState,
    viewProfile,
    fetchRecentMembers,
    throttle,
    debounce
};

// Initialize tooltips and other UI components
document.addEventListener('DOMContentLoaded', function() {
    // Add ripple effect to buttons
    const buttons = document.querySelectorAll('.btn, .member-btn');
    buttons.forEach(button => {
        button.addEventListener('click', createRipple);
    });
});

// Ripple effect function
function createRipple(event) {
    const button = event.currentTarget;
    const circle = document.createElement('span');
    const diameter = Math.max(button.clientWidth, button.clientHeight);
    const radius = diameter / 2;
    
    circle.style.width = circle.style.height = `${diameter}px`;
    circle.style.left = `${event.clientX - button.offsetLeft - radius}px`;
    circle.style.top = `${event.clientY - button.offsetTop - radius}px`;
    circle.classList.add('ripple');
    
    const ripple = button.getElementsByClassName('ripple')[0];
    if (ripple) {
        ripple.remove();
    }
    
    button.appendChild(circle);
}

// Add ripple CSS
const rippleCSS = `
    .ripple {
        position: absolute;
        border-radius: 50%;
        background-color: rgba(255, 255, 255, 0.6);
        transform: scale(0);
        animation: ripple-animation 0.6s linear;
        pointer-events: none;
    }
    
    @keyframes ripple-animation {
        to {
            transform: scale(4);
            opacity: 0;
        }
    }
`;

// Inject ripple CSS
const style = document.createElement('style');
style.textContent = rippleCSS;
document.head.appendChild(style);

console.log('🚀 Helakapuwa.com JavaScript loaded and ready!');
