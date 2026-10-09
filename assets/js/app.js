/**
 * MEDGem — Core Clinical JavaScript
 * Interactive clinical components, modals, tabs, accordions, and track lookup
 */

document.addEventListener('DOMContentLoaded', () => {
    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    // ── Mobile Nav Toggle ──────────────────────────────
    const navToggle = document.getElementById('navToggle');
    const navLinks = document.getElementById('navLinks');
    
    if (navToggle && navLinks) {
        navToggle.addEventListener('click', () => {
            navLinks.classList.toggle('active');
            const isOpen = navLinks.classList.contains('active');
            navToggle.innerHTML = isOpen
                ? '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>'
                : '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>';
        });
        
        navLinks.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', () => {
                navLinks.classList.remove('active');
            });
        });
    }

    // ── Scroll Reveal ──────────────────────────────────
    const reveals = document.querySelectorAll('.reveal');
    if (reveals.length > 0) {
        reveals.forEach((el, index) => {
            el.style.transitionDelay = `${Math.min(index * 70, 280)}ms`;
        });

        const revealObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                    revealObserver.unobserve(entry.target);
                }
            });
        }, {
            threshold: 0.1,
            rootMargin: '0px 0px -40px 0px'
        });

        reveals.forEach(el => revealObserver.observe(el));
    }

    // ── Navbar Shadow on Scroll ────────────────────────
    const navbar = document.getElementById('navbar');
    if (navbar) {
        window.addEventListener('scroll', () => {
            if (window.scrollY > 40) {
                navbar.classList.add('navbar--scrolled');
            } else {
                navbar.classList.remove('navbar--scrolled');
            }
        }, { passive: true });
    }

    // ── Smooth Scroll for Anchor Links ─────────────────
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            const targetId = this.getAttribute('href');
            if (targetId === '#' || targetId === '') return;
            
            const target = document.querySelector(targetId);
            if (target) {
                e.preventDefault();
                const headerOffset = 80;
                const elementPosition = target.getBoundingClientRect().top;
                const offsetPosition = elementPosition + window.scrollY - headerOffset;
                
                window.scrollTo({
                    top: offsetPosition,
                    behavior: prefersReducedMotion ? 'auto' : 'smooth'
                });
            }
        });
    });

    // ── Track ID Input Auto-Format ─────────────────────
    const trackInputs = document.querySelectorAll('.hero-track-box__input, #trackInput');
    trackInputs.forEach(input => {
        input.addEventListener('input', (e) => {
            let val = e.target.value.toUpperCase().replace(/[^A-Z0-9-]/g, '');
            if (val.length >= 3 && !val.includes('-') && val.startsWith('SYN')) {
                val = 'SYN-' + val.substring(3);
            }
            e.target.value = val;
        });
    });

    // ── Demo Track Chips Fill ──────────────────────────
    document.querySelectorAll('[data-track-fill]').forEach(chip => {
        chip.addEventListener('click', (e) => {
            e.preventDefault();
            const trackVal = chip.getAttribute('data-track-fill');
            const targetInput = document.querySelector('.hero-track-box__input') || document.getElementById('trackInput');
            if (targetInput) {
                targetInput.value = trackVal;
                targetInput.focus();
            }
        });
    });

    // ── Multimodal Modality Tabs ───────────────────────
    const tabs = document.querySelectorAll('.multimodal-tab');
    const panels = document.querySelectorAll('.modality-panel');
    if (tabs.length > 0 && panels.length > 0) {
        tabs.forEach(tab => {
            tab.addEventListener('click', () => {
                tabs.forEach(t => t.classList.remove('active'));
                panels.forEach(p => p.classList.remove('active'));

                tab.classList.add('active');
                const targetId = tab.getAttribute('data-tab');
                const activePanel = document.getElementById(targetId);
                if (activePanel) {
                    activePanel.classList.add('active');
                }
            });
        });
    }

    // ── Clinical FAQ Accordion ─────────────────────────
    const faqItems = document.querySelectorAll('.faq-item');
    faqItems.forEach(item => {
        const questionBtn = item.querySelector('.faq-question');
        if (questionBtn) {
            questionBtn.addEventListener('click', () => {
                const isActive = item.classList.contains('active');
                faqItems.forEach(i => i.classList.remove('active'));
                if (!isActive) {
                    item.classList.add('active');
                }
            });
        }
    });
});

/**
 * Toast Notification Utility
 */
function showToast(message, duration = 3000) {
    const existing = document.querySelector('.toast');
    if (existing) existing.remove();
    
    const toast = document.createElement('div');
    toast.className = 'toast';
    toast.textContent = message;
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateY(16px)';
        toast.style.transition = 'all 0.3s ease';
        setTimeout(() => toast.remove(), 300);
    }, duration);
}

/**
 * Loading Spinner Helper
 */
function showLoading(button, text = 'Processing...') {
    button.disabled = true;
    button._originalHTML = button.innerHTML;
    button.innerHTML = `<span class="spinner" style="width:18px;height:18px;border-width:2px;display:inline-block;vertical-align:-3px;margin-right:8px"></span> ${text}`;
}

function hideLoading(button) {
    button.disabled = false;
    if (button._originalHTML) {
        button.innerHTML = button._originalHTML;
    }
}
