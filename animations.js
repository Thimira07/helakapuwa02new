/**
 * Helakapuwa.com - Animations & Interactive Effects
 * Handles carousels, hover effects, and subtle background animations
 */

// Global animation configuration
const ANIMATION_CONFIG = {
    duration: {
        short: 300,
        medium: 500,
        long: 800
    },
    easing: 'cubic-bezier(0.4, 0, 0.2, 1)',
    primaryColor: '#0096C7'
};

// Initialize all animations when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    initTestimonialCarousel();
    initLiveMembersCarousel();
    initHoverEffects();
    initScrollAnimations();
    initLoadingAnimations();
    initBackgroundAnimations();
    initCountUpAnimations();
    initFormAnimations();
});

/**
 * Customer Reviews/Testimonials Carousel
 */
function initTestimonialCarousel() {
    const testimonialData = [
        {
            name: "අනුරා සහ සුනිල්",
            location: "කොළඹ",
            image: "img/testimonials/couple1.jpg",
            text: "Helakapuwa.com හරහා අපි මුණගැසුණා. ඉතාම සරල සහ ආරක්ෂිත platform එකක්. දැන් අපි සතුටින් ජීවත් වෙනවා.",
            rating: 5,
            date: "2024 ජනවාරි"
        },
        {
            name: "සමන්ති සහ රොෂාන්",
            location: "කණ්ඩි",
            image: "img/testimonials/couple2.jpg", 
            text: "මෙහි profile quality ඉතාම හොඳයි. අපේ family values match වෙන කෙනෙක් සොයා ගැනීමට හැකි වුණා.",
            rating: 5,
            date: "2024 පෙබරවාරි"
        },
        {
            name: "තිලකා සහ ප්‍රියන්ත",
            location: "ගාල්ල",
            image: "img/testimonials/couple3.jpg",
            text: "ඉතාම professional service එකක්. Customer support team එක සැමවිටම උපකාර කරනවා.",
            rating: 5,
            date: "2024 මාර්තු"
        },
        {
            name: "නිල්මිනී සහ චමින්ද",
            location: "මාතර",
            image: "img/testimonials/couple4.jpg",
            text: "6 මාසක් ඇතුළත අපි අපේ life partner සොයා ගත්තා. ස්තූතියි Helakapuwa.com!",
            rating: 5,
            date: "2024 අප්‍රේල්"
        }
    ];

    const carousel = document.getElementById('testimonialCarousel');
    if (!carousel) return;

    let currentIndex = 0;
    let isAnimating = false;

    // Create carousel HTML
    carousel.innerHTML = `
        <div class="testimonial-container">
            <div class="testimonial-wrapper">
                <div class="testimonial-track" id="testimonialTrack">
                    ${testimonialData.map((testimonial, index) => createTestimonialCard(testimonial, index)).join('')}
                </div>
            </div>
            <div class="carousel-controls">
                <button class="carousel-btn prev-btn" id="testimonialPrev">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <div class="carousel-dots" id="testimonialDots">
                    ${testimonialData.map((_, index) => `
                        <button class="dot ${index === 0 ? 'active' : ''}" data-index="${index}"></button>
                    `).join('')}
                </div>
                <button class="carousel-btn next-btn" id="testimonialNext">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
        </div>
    `;

    // Add CSS styles
    addTestimonialStyles();

    // Event listeners
    const track = document.getElementById('testimonialTrack');
    const prevBtn = document.getElementById('testimonialPrev');
    const nextBtn = document.getElementById('testimonialNext');
    const dots = document.querySelectorAll('#testimonialDots .dot');

    prevBtn.addEventListener('click', () => moveTestimonial(-1));
    nextBtn.addEventListener('click', () => moveTestimonial(1));
    
    dots.forEach((dot, index) => {
        dot.addEventListener('click', () => goToTestimonial(index));
    });

    // Auto-play
    setInterval(() => {
        if (!isAnimating) {
            moveTestimonial(1);
        }
    }, 5000);

    function moveTestimonial(direction) {
        if (isAnimating) return;
        
        isAnimating = true;
        currentIndex += direction;
        
        if (currentIndex >= testimonialData.length) currentIndex = 0;
        if (currentIndex < 0) currentIndex = testimonialData.length - 1;
        
        updateTestimonialPosition();
    }

    function goToTestimonial(index) {
        if (isAnimating || index === currentIndex) return;
        
        isAnimating = true;
        currentIndex = index;
        updateTestimonialPosition();
    }

    function updateTestimonialPosition() {
        const track = document.getElementById('testimonialTrack');
        const offset = -currentIndex * 100;
        
        track.style.transform = `translateX(${offset}%)`;
        
        // Update dots
        dots.forEach((dot, index) => {
            dot.classList.toggle('active', index === currentIndex);
        });
        
        setTimeout(() => {
            isAnimating = false;
        }, ANIMATION_CONFIG.duration.medium);
    }
}

function createTestimonialCard(testimonial, index) {
    const stars = '★'.repeat(testimonial.rating);
    
    return `
        <div class="testimonial-card" data-index="${index}">
            <div class="testimonial-content">
                <div class="testimonial-header">
                    <img src="${testimonial.image}" alt="${testimonial.name}" class="testimonial-image" 
                         onerror="this.src='img/placeholder-couple.jpg'">
                    <div class="testimonial-info">
                        <h4 class="testimonial-name">${testimonial.name}</h4>
                        <p class="testimonial-location">${testimonial.location}</p>
                        <div class="testimonial-rating">${stars}</div>
                    </div>
                </div>
                <blockquote class="testimonial-text">
                    "${testimonial.text}"
                </blockquote>
                <div class="testimonial-date">${testimonial.date}</div>
            </div>
        </div>
    `;
}

/**
 * Live Members Carousel
 */
function initLiveMembersCarousel() {
    const liveMembersData = [
        {
            id: 1,
            name: "නිමල්කා",
            age: 28,
            profession: "ගුරුවරිය",
            location: "කොළඹ",
            image: "img/members/member1.jpg",
            isOnline: true,
            lastSeen: "දැන්"
        },
        {
            id: 2,
            name: "චමිත",
            age: 32,
            profession: "ඉංජිනේරු",
            location: "කණ්ඩි",
            image: "img/members/member2.jpg",
            isOnline: false,
            lastSeen: "5 මිනිත්තු පෙර"
        },
        {
            id: 3,
            name: "සුනේත්‍රා",
            age: 26,
            profession: "වෛද්‍යවරිය",
            location: "ගම්පහ",
            image: "img/members/member3.jpg",
            isOnline: true,
            lastSeen: "දැන්"
        },
        {
            id: 4,
            name: "දිනේෂ්",
            age: 30,
            profession: "ගණකාධිකාරී",
            location: "නුගේගොඩ",
            image: "img/members/member4.jpg",
            isOnline: true,
            lastSeen: "දැන්"
        },
        {
            id: 5,
            name: "රාජිනී",
            age: 24,
            profession: "නම්‍ය කලාකරණිය",
            location: "මහරගම",
            image: "img/members/member5.jpg",
            isOnline: false,
            lastSeen: "10 මිනිත්තු පෙර"
        },
        {
            id: 6,
            name: "අසේල",
            age: 29,
            profession: "ව්‍යාපාරික",
            location: "මොරටුව",
            image: "img/members/member6.jpg",
            isOnline: true,
            lastSeen: "දැන්"
        }
    ];

    const carousel = document.getElementById('liveMembersCarousel');
    if (!carousel) return;

    let currentMemberIndex = 0;
    let isMemberAnimating = false;
    const itemsPerView = window.innerWidth >= 1024 ? 4 : window.innerWidth >= 768 ? 3 : 2;

    // Create carousel HTML
    carousel.innerHTML = `
        <div class="members-container">
            <div class="members-wrapper">
                <div class="members-track" id="membersTrack">
                    ${liveMembersData.map((member, index) => createMemberCard(member, index)).join('')}
                </div>
            </div>
            <div class="members-controls">
                <button class="carousel-btn prev-btn" id="membersPrev">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <button class="carousel-btn next-btn" id="membersNext">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
        </div>
    `;

    // Add CSS styles
    addMembersStyles();

    // Event listeners
    const prevBtn = document.getElementById('membersPrev');
    const nextBtn = document.getElementById('membersNext');

    prevBtn.addEventListener('click', () => moveMembersCarousel(-1));
    nextBtn.addEventListener('click', () => moveMembersCarousel(1));

    // Auto-play
    setInterval(() => {
        if (!isMemberAnimating) {
            moveMembersCarousel(1);
        }
    }, 3000);

    function moveMembersCarousel(direction) {
        if (isMemberAnimating) return;
        
        isMemberAnimating = true;
        currentMemberIndex += direction;
        
        const maxIndex = Math.max(0, liveMembersData.length - itemsPerView);
        
        if (currentMemberIndex > maxIndex) currentMemberIndex = 0;
        if (currentMemberIndex < 0) currentMemberIndex = maxIndex;
        
        updateMembersPosition();
    }

    function updateMembersPosition() {
        const track = document.getElementById('membersTrack');
        const cardWidth = 100 / itemsPerView;
        const offset = -currentMemberIndex * cardWidth;
        
        track.style.transform = `translateX(${offset}%)`;
        
        setTimeout(() => {
            isMemberAnimating = false;
        }, ANIMATION_CONFIG.duration.medium);
    }

    // Update on window resize
    window.addEventListener('resize', debounce(() => {
        location.reload(); // Simple approach for responsive carousel
    }, 250));
}

function createMemberCard(member, index) {
    const onlineStatus = member.isOnline ? 'online' : 'offline';
    const onlineText = member.isOnline ? 'සම්බන්ධයි' : member.lastSeen;
    
    return `
        <div class="member-card" data-index="${index}">
            <div class="member-image-container">
                <img src="${member.image}" alt="${member.name}" class="member-image" 
                     onerror="this.src='img/placeholder-avatar.jpg'">
                <div class="online-indicator ${onlineStatus}"></div>
                <div class="member-overlay">
                    <button class="view-profile-btn">
                        <i class="fas fa-eye"></i> Profile බලන්න
                    </button>
                </div>
            </div>
            <div class="member-info">
                <h4 class="member-name">${member.name}</h4>
                <p class="member-age">${member.age} වසර</p>
                <p class="member-profession">${member.profession}</p>
                <p class="member-location">
                    <i class="fas fa-map-marker-alt"></i> ${member.location}
                </p>
                <p class="member-status ${onlineStatus}">
                    <i class="fas fa-circle"></i> ${onlineText}
                </p>
            </div>
        </div>
    `;
}

/**
 * Hover Effects and Interactions
 */
function initHoverEffects() {
    // Card hover effects
    document.addEventListener('mouseover', function(e) {
        if (e.target.closest('.member-card')) {
            const card = e.target.closest('.member-card');
            card.style.transform = 'translateY(-8px)';
            card.style.boxShadow = '0 20px 40px rgba(0, 150, 199, 0.15)';
        }
        
        if (e.target.closest('.testimonial-card')) {
            const card = e.target.closest('.testimonial-card');
            card.style.transform = 'scale(1.02)';
        }
    });

    document.addEventListener('mouseout', function(e) {
        if (e.target.closest('.member-card')) {
            const card = e.target.closest('.member-card');
            card.style.transform = 'translateY(0)';
            card.style.boxShadow = '0 4px 20px rgba(0, 0, 0, 0.1)';
        }
        
        if (e.target.closest('.testimonial-card')) {
            const card = e.target.closest('.testimonial-card');
            card.style.transform = 'scale(1)';
        }
    });

    // Button hover effects
    const buttons = document.querySelectorAll('button, .btn');
    buttons.forEach(button => {
        button.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-2px)';
        });
        
        button.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
        });
    });
}

/**
 * Scroll Animations
 */
function initScrollAnimations() {
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('animate-in');
                observer.unobserve(entry.target);
            }
        });
    }, observerOptions);

    // Observe elements for scroll animation
    const animatedElements = document.querySelectorAll(
        '.section, .card, .feature-item, .stats-item, .step-item'
    );
    
    animatedElements.forEach(el => {
        el.classList.add('animate-on-scroll');
        observer.observe(el);
    });
}

/**
 * Loading Animations
 */
function initLoadingAnimations() {
    // Page load animation
    window.addEventListener('load', function() {
        const loader = document.querySelector('.page-loader');
        if (loader) {
            loader.style.opacity = '0';
            setTimeout(() => {
                loader.style.display = 'none';
            }, 300);
        }
    });

    // Button loading states
    document.addEventListener('click', function(e) {
        if (e.target.matches('.btn-loading')) {
            const button = e.target;
            const originalText = button.innerHTML;
            
            button.disabled = true;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
            
            // Simulate async operation
            setTimeout(() => {
                button.disabled = false;
                button.innerHTML = originalText;
            }, 2000);
        }
    });
}

/**
 * Subtle Background Animations
 */
function initBackgroundAnimations() {
    // Only add if Three.js is available and on homepage
    if (typeof THREE !== 'undefined' && document.getElementById('hero-background')) {
        createSubtleBackground();
    }
    
    // CSS-based floating hearts animation
    createFloatingHearts();
}

function createSubtleBackground() {
    const container = document.getElementById('hero-background');
    if (!container) return;

    // Create scene
    const scene = new THREE.Scene();
    const camera = new THREE.PerspectiveCamera(75, container.offsetWidth / container.offsetHeight, 0.1, 1000);
    const renderer = new THREE.WebGLRenderer({ alpha: true, antialias: true });
    
    renderer.setSize(container.offsetWidth, container.offsetHeight);
    renderer.setClearColor(0x000000, 0);
    container.appendChild(renderer.domElement);

    // Create subtle floating particles
    const geometry = new THREE.BufferGeometry();
    const particleCount = 50;
    const positions = new Float32Array(particleCount * 3);
    
    for (let i = 0; i < particleCount * 3; i += 3) {
        positions[i] = (Math.random() - 0.5) * 20;
        positions[i + 1] = (Math.random() - 0.5) * 20;
        positions[i + 2] = (Math.random() - 0.5) * 20;
    }
    
    geometry.setAttribute('position', new THREE.BufferAttribute(positions, 3));
    
    const material = new THREE.PointsMaterial({
        color: 0x0096C7,
        size: 0.05,
        transparent: true,
        opacity: 0.6
    });
    
    const particles = new THREE.Points(geometry, material);
    scene.add(particles);
    
    camera.position.z = 5;
    
    // Animation loop
    function animate() {
        requestAnimationFrame(animate);
        
        particles.rotation.x += 0.001;
        particles.rotation.y += 0.002;
        
        renderer.render(scene, camera);
    }
    
    animate();
    
    // Handle resize
    window.addEventListener('resize', () => {
        camera.aspect = container.offsetWidth / container.offsetHeight;
        camera.updateProjectionMatrix();
        renderer.setSize(container.offsetWidth, container.offsetHeight);
    });
}

function createFloatingHearts() {
    const heartsContainer = document.getElementById('floating-hearts');
    if (!heartsContainer) return;

    setInterval(() => {
        const heart = document.createElement('div');
        heart.className = 'floating-heart';
        heart.innerHTML = '💕';
        heart.style.left = Math.random() * 100 + '%';
        heart.style.animationDuration = (Math.random() * 3 + 2) + 's';
        heart.style.opacity = Math.random() * 0.5 + 0.3;
        
        heartsContainer.appendChild(heart);
        
        setTimeout(() => {
            heart.remove();
        }, 5000);
    }, 3000);
}

/**
 * Count Up Animations for Statistics
 */
function initCountUpAnimations() {
    const counters = document.querySelectorAll('.counter');
    
    const counterObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const counter = entry.target;
                const target = parseInt(counter.getAttribute('data-target'));
                const duration = 2000;
                const increment = target / (duration / 16);
                let current = 0;
                
                const updateCounter = () => {
                    current += increment;
                    if (current < target) {
                        counter.textContent = Math.floor(current).toLocaleString();
                        requestAnimationFrame(updateCounter);
                    } else {
                        counter.textContent = target.toLocaleString();
                    }
                };
                
                updateCounter();
                counterObserver.unobserve(counter);
            }
        });
    });
    
    counters.forEach(counter => {
        counterObserver.observe(counter);
    });
}

/**
 * Form Animations
 */
function initFormAnimations() {
    // Floating labels
    const formInputs = document.querySelectorAll('.form-group input, .form-group textarea');
    
    formInputs.forEach(input => {
        input.addEventListener('focus', function() {
            this.parentElement.classList.add('focused');
        });
        
        input.addEventListener('blur', function() {
            if (!this.value) {
                this.parentElement.classList.remove('focused');
            }
        });
        
        // Check if input has value on load
        if (input.value) {
            input.parentElement.classList.add('focused');
        }
    });

    // Form validation animations
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const inputs = this.querySelectorAll('[required]');
            inputs.forEach(input => {
                if (!input.value) {
                    input.classList.add('error-shake');
                    setTimeout(() => {
                        input.classList.remove('error-shake');
                    }, 500);
                }
            });
        });
    });
}

/**
 * CSS Styles for Animations
 */
function addTestimonialStyles() {
    const styles = `
        <style>
        .testimonial-container {
            position: relative;
            overflow: hidden;
            border-radius: 12px;
            background: white;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        
        .testimonial-wrapper {
            overflow: hidden;
        }
        
        .testimonial-track {
            display: flex;
            transition: transform 0.5s ease-in-out;
        }
        
        .testimonial-card {
            min-width: 100%;
            padding: 2rem;
            transition: transform 0.3s ease;
        }
        
        .testimonial-header {
            display: flex;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .testimonial-image {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 1rem;
            border: 3px solid ${ANIMATION_CONFIG.primaryColor};
        }
        
        .testimonial-name {
            font-size: 1.1rem;
            font-weight: 600;
            color: #333;
            margin: 0;
        }
        
        .testimonial-location {
            color: #666;
            font-size: 0.9rem;
            margin: 0.25rem 0;
        }
        
        .testimonial-rating {
            color: #ffd700;
            font-size: 1rem;
        }
        
        .testimonial-text {
            font-style: italic;
            font-size: 1.1rem;
            line-height: 1.6;
            color: #555;
            margin: 0 0 1rem 0;
            position: relative;
        }
        
        .testimonial-date {
            color: #888;
            font-size: 0.85rem;
            text-align: right;
        }
        
        .carousel-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 2rem;
            background: #f8f9fa;
        }
        
        .carousel-btn {
            background: ${ANIMATION_CONFIG.primaryColor};
            color: white;
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .carousel-btn:hover {
            background: #007BB5;
            transform: scale(1.1);
        }
        
        .carousel-dots {
            display: flex;
            gap: 0.5rem;
        }
        
        .dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            border: none;
            background: #ddd;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .dot.active {
            background: ${ANIMATION_CONFIG.primaryColor};
            transform: scale(1.2);
        }
        </style>
    `;
    document.head.insertAdjacentHTML('beforeend', styles);
}

function addMembersStyles() {
    const styles = `
        <style>
        .members-container {
            position: relative;
            overflow: hidden;
        }
        
        .members-wrapper {
            overflow: hidden;
        }
        
        .members-track {
            display: flex;
            transition: transform 0.5s ease-in-out;
        }
        
        .member-card {
            flex: 0 0 auto;
            width: calc(100% / 4);
            padding: 0.5rem;
            transition: all 0.3s ease;
        }
        
        @media (max-width: 1024px) {
            .member-card { width: calc(100% / 3); }
        }
        
        @media (max-width: 768px) {
            .member-card { width: calc(100% / 2); }
        }
        
        .member-image-container {
            position: relative;
            overflow: hidden;
            border-radius: 12px;
            aspect-ratio: 1;
        }
        
        .member-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }
        
        .member-card:hover .member-image {
            transform: scale(1.1);
        }
        
        .online-indicator {
            position: absolute;
            top: 10px;
            right: 10px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            border: 2px solid white;
        }
        
        .online-indicator.online {
            background: #4ade80;
            animation: pulse-online 2s infinite;
        }
        
        .online-indicator.offline {
            background: #gray-400;
        }
        
        @keyframes pulse-online {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        .member-overlay {
            position: absolute;
            inset: 0;
            background: linear-gradient(to top, rgba(0, 0, 0, 0.7), transparent);
            display: flex;
            align-items: flex-end;
            justify-content: center;
            padding: 1rem;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .member-card:hover .member-overlay {
            opacity: 1;
        }
        
        .view-profile-btn {
            background: ${ANIMATION_CONFIG.primaryColor};
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .view-profile-btn:hover {
            background: #007BB5;
        }
        
        .member-info {
            padding: 1rem;
            background: white;
            border-radius: 0 0 12px 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }
        
        .member-name {
            font-size: 1.1rem;
            font-weight: 600;
            color: #333;
            margin: 0 0 0.25rem 0;
        }
        
        .member-age, .member-profession, .member-location {
            font-size: 0.85rem;
            color: #666;
            margin: 0.125rem 0;
        }
        
        .member-status {
            font-size: 0.75rem;
            margin-top: 0.5rem;
        }
        
        .member-status.online {
            color: #4ade80;
        }
        
        .member-status.offline {
            color: #gray-500;
        }
        
        .members-controls {
            position: absolute;
            top: 50%;
            width: 100%;
            display: flex;
            justify-content: space-between;
            padding: 0 1rem;
            pointer-events: none;
        }
        
        .members-controls .carousel-btn {
            pointer-events: auto;
        }
        </style>
    `;
    document.head.insertAdjacentHTML('beforeend', styles);
}

// Add general animation styles
function addGeneralAnimationStyles() {
    const styles = `
        <style>
        .animate-on-scroll {
            opacity: 0;
            transform: translateY(30px);
            transition: all 0.6s ease;
        }
        
        .animate-on-scroll.animate-in {
            opacity: 1;
            transform: translateY(0);
        }
        
        .floating-heart {
            position: absolute;
            font-size: 1.5rem;
            animation: float-up linear;
            pointer-events: none;
            z-index: 1;
        }
        
        @keyframes float-up {
            0% {
                transform: translateY(100vh) scale(0);
                opacity: 0;
            }
            10% {
                opacity: 1;
            }
            90% {
                opacity: 1;
            }
            100% {
                transform: translateY(-100px) scale(1);
                opacity: 0;
            }
        }
        
        .error-shake {
            animation: shake 0.5s ease-in-out;
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
        
        .page-loader {
            position: fixed;
            inset: 0;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            transition: opacity 0.3s ease;
        }
        
        .form-group {
            position: relative;
            margin-bottom: 1.5rem;
        }
        
        .form-group.focused label {
            transform: translateY(-1.5rem) scale(0.85);
            color: ${ANIMATION_CONFIG.primaryColor};
        }
        
        .form-group label {
            position: absolute;
            top: 1rem;
            left: 1rem;
            transition: all 0.3s ease;
            pointer-events: none;
            color: #666;
        }
        </style>
    `;
    document.head.insertAdjacentHTML('beforeend', styles);
}

// Utility functions
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

// Initialize general styles
addGeneralAnimationStyles();

// Export for use in other files
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        initTestimonialCarousel,
        initLiveMembersCarousel,
        initHoverEffects,
        initScrollAnimations,
        initLoadingAnimations,
        initBackgroundAnimations,
        initCountUpAnimations,
        initFormAnimations
    };
}
