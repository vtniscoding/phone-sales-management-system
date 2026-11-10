/**
 * Global storefront JavaScript for NovaPhone.
 * Handles mobile menu drawer, dismissible flash alerts, and hero banner slider.
 */

document.addEventListener('DOMContentLoaded', () => {
    initMobileNav();
    initDismissibleAlerts();
    initHeroSlider();
});

function initMobileNav() {
    const toggleBtn = document.getElementById('mobile-menu-toggle');
    const nav = document.getElementById('primary-nav');
    if (!toggleBtn || !nav) return;

    const closeNav = () => {
        nav.classList.remove('is-open');
        toggleBtn.setAttribute('aria-expanded', 'false');
    };

    toggleBtn.addEventListener('click', () => {
        const isOpen = nav.classList.toggle('is-open');
        toggleBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && nav.classList.contains('is-open')) {
            closeNav();
            toggleBtn.focus();
        }
    });
}

function initDismissibleAlerts() {
    document.querySelectorAll('.alert-close').forEach((btn) => {
        btn.addEventListener('click', () => {
            const alertBox = btn.closest('.alert');
            if (alertBox) {
                alertBox.remove();
            }
        });
    });
}

function initHeroSlider() {
    const slider = document.getElementById('hero-slider');
    if (!slider) return;

    const track = slider.querySelector('.hero-slides');
    const slides = slider.querySelectorAll('.hero-slide');
    const dots = slider.querySelectorAll('.hero-dot');
    const prevBtn = slider.querySelector('.hero-prev');
    const nextBtn = slider.querySelector('.hero-next');

    if (!track || slides.length <= 1) return;

    let currentIndex = 0;
    let timerId = null;
    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    const goToSlide = (index) => {
        currentIndex = (index + slides.length) % slides.length;
        track.style.transform = `translateX(-${currentIndex * 100}%)`;
        dots.forEach((dot, i) => {
            dot.classList.toggle('is-active', i === currentIndex);
            dot.setAttribute('aria-selected', i === currentIndex ? 'true' : 'false');
        });
    };

    const stopAutoPlay = () => {
        if (timerId !== null) {
            window.clearInterval(timerId);
            timerId = null;
        }
    };

    const startAutoPlay = () => {
        if (prefersReducedMotion) return;
        stopAutoPlay();
        timerId = window.setInterval(() => goToSlide(currentIndex + 1), 5500);
    };

    if (prevBtn) {
        prevBtn.addEventListener('click', () => {
            goToSlide(currentIndex - 1);
            startAutoPlay();
        });
    }

    if (nextBtn) {
        nextBtn.addEventListener('click', () => {
            goToSlide(currentIndex + 1);
            startAutoPlay();
        });
    }

    dots.forEach((dot, index) => {
        dot.addEventListener('click', () => {
            goToSlide(index);
            startAutoPlay();
        });
    });

    slider.addEventListener('mouseenter', stopAutoPlay);
    slider.addEventListener('mouseleave', startAutoPlay);
    slider.addEventListener('focusin', stopAutoPlay);
    slider.addEventListener('focusout', startAutoPlay);

    startAutoPlay();
}

