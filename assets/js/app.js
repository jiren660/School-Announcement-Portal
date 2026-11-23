/**
 * School Announcement Portal - Client-Side Interactivity
 */

document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('search-input');
    const categoryPills = document.querySelectorAll('.pill-btn');
    const cards = document.querySelectorAll('.announcement-card');
    const feedContainer = document.querySelector('.feed-container');
    const modal = document.getElementById('announcement-modal');
    const modalCloseBtn = document.getElementById('modal-close-btn');
    const modalTitle = document.getElementById('modal-title');
    const modalCategory = document.getElementById('modal-category');
    const modalDate = document.getElementById('modal-date');
    const modalAuthor = document.getElementById('modal-author');
    const modalBody = document.getElementById('modal-body');
    const noResultsMsg = document.getElementById('no-results-msg');
    const urgentMessage = document.getElementById('urgent-message');
    const urgentTitle = document.getElementById('urgent-title');
    const urgentSummary = document.getElementById('urgent-summary');
    const urgentItems = Array.from(document.querySelectorAll('.urgent-alert-item'));
    const scopeTabBtns = document.querySelectorAll('.scope-tab-btn');

    let currentCategory = 'all';
    let currentScope = 'all';
    let searchQuery = '';
    let activeUrgentIndex = 0;

    if (urgentItems.length > 1 && urgentTitle && urgentSummary && urgentMessage) {
        window.setInterval(() => {
            urgentMessage.classList.add('is-changing');
            window.setTimeout(() => {
                activeUrgentIndex = (activeUrgentIndex + 1) % urgentItems.length;
                const nextUrgent = urgentItems[activeUrgentIndex];
                urgentTitle.textContent = `${nextUrgent.dataset.title || ''}:`;
                urgentSummary.textContent = nextUrgent.dataset.summary || '';
                urgentMessage.classList.remove('is-changing');
            }, 250);
        }, 10000);
    }

    // Filter Logic
    function filterCards() {
        let visibleCount = 0;

        cards.forEach(card => {
            const title = (card.dataset.title || '').toLowerCase();
            const summary = (card.dataset.summary || '').toLowerCase();
            const category = card.dataset.category || '';
            const scope = card.dataset.scope || 'university';

            const matchesCategory = (currentCategory === 'all') || (category === currentCategory);
            const matchesScope = (currentScope === 'all') || (scope === currentScope);
            const matchesSearch = title.includes(searchQuery) || summary.includes(searchQuery);

            if (matchesCategory && matchesScope && matchesSearch) {
                card.style.display = 'flex';
                visibleCount++;
            } else {
                card.style.display = 'none';
            }
        });

        if (noResultsMsg) {
            noResultsMsg.style.display = (visibleCount === 0) ? 'block' : 'none';
        }
    }

    // Scope Tab Listener
    scopeTabBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            scopeTabBtns.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            currentScope = btn.dataset.scope || 'all';
            filterCards();
        });
    });

    // Search Input Listener
    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            searchQuery = e.target.value.trim().toLowerCase();
            filterCards();
        });
    }

    // Category Pill Listener
    categoryPills.forEach(pill => {
        pill.addEventListener('click', () => {
            categoryPills.forEach(p => p.classList.remove('active'));
            pill.classList.add('active');
            currentCategory = pill.dataset.category;
            filterCards();
            if (feedContainer) {
                feedContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });

    // Open Announcement Detail Modal
    document.querySelectorAll('.open-modal-trigger').forEach(trigger => {
        trigger.addEventListener('click', (e) => {
            e.preventDefault();
            const card = trigger.closest('.announcement-card');
            if (!card) return;

            if (modalTitle) modalTitle.textContent = card.dataset.title;
            if (modalCategory) {
                modalCategory.textContent = card.dataset.categoryName;
                modalCategory.className = `badge badge-${card.dataset.badgeColor || 'primary'}`;
            }
            if (modalDate) modalDate.textContent = card.dataset.date;
            if (modalAuthor) modalAuthor.textContent = card.dataset.author;
            if (modalBody) modalBody.textContent = card.dataset.content;

            const modalScopeBadge = document.getElementById('modal-scope-badge');
            if (modalScopeBadge) {
                modalScopeBadge.textContent = card.dataset.scopeLabel || 'Official';
                let bClass = 'authority-badge-university';
                if (card.dataset.scope === 'office') bClass = 'authority-badge-office';
                else if (card.dataset.scope === 'college') bClass = 'authority-badge-college';
                else if (card.dataset.scope === 'department') bClass = 'authority-badge-department';
                modalScopeBadge.className = 'authority-badge ' + bClass;
            }

            const modalAuthorLogo = document.getElementById('modal-author-logo');
            if (modalAuthorLogo && card.dataset.logo) {
                modalAuthorLogo.src = card.dataset.logo;
            }

            // Render Facebook-Style Image Carousel
            const carouselContainer = document.getElementById('modal-carousel-container');
            if (carouselContainer) {
                let images = [];
                try {
                    images = JSON.parse(card.dataset.images || '[]');
                } catch (e) {
                    images = [];
                }
                window.renderFacebookCarousel(images, 'modal-carousel-container', '');
            }

            if (modal) {
                modal.classList.add('active');
                document.body.style.overflow = 'hidden';
            }

            if (card.dataset.trackViews === 'true') {
                const formData = new URLSearchParams({ id: card.dataset.id });
                fetch('record_view.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: formData.toString()
                }).then(response => {
                    if (!response.ok) {
                        throw new Error(`View count request failed with status ${response.status}`);
                    }
                    return response.json();
                }).catch(error => {
                    console.error('Unable to record announcement view:', error);
                });
            }
        });
    });

    // Close Modal
    function closeModal() {
        if (modal) {
            modal.classList.remove('active');
            document.body.style.overflow = 'auto';
        }
    }

    if (modalCloseBtn) modalCloseBtn.addEventListener('click', closeModal);
    if (modal) {
        modal.addEventListener('click', (e) => {
            if (e.target === modal) closeModal();
        });
    }

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') closeModal();
    });
});

/**
 * Global Facebook-Format Image Carousel Renderer for Public Board
 */
window.renderFacebookCarousel = function (images, containerId, prefix = '') {
    const container = document.getElementById(containerId);
    if (!container) return;

    if (!images || images.length === 0) {
        container.innerHTML = '';
        container.style.display = 'none';
        return;
    }

    const formattedImages = images.map(img => {
        if (!img) return '';
        if (img.startsWith('http') || img.startsWith('/') || img.startsWith('data:') || img.startsWith('blob:')) {
            return img;
        }
        return prefix + img;
    }).filter(img => img !== '');

    const total = formattedImages.length;
    if (total === 0) {
        container.innerHTML = '';
        container.style.display = 'none';
        return;
    }

    container.style.display = 'block';
    let currentIndex = 0;

    // Single Image View
    if (total === 1) {
        container.innerHTML = `
            <div class="ig-carousel">
                <div class="ig-carousel-slides">
                    <div class="ig-carousel-slide">
                        <img src="${formattedImages[0]}" alt="Announcement Photo" loading="lazy">
                    </div>
                </div>
            </div>
        `;
        return;
    }

    // Multiple Images: Facebook Carousel Format
    // Left & Right arrows: First image has right arrow as default; left arrow is hidden initially.
    let html = `
        <div class="ig-carousel facebook-carousel">
            <span class="ig-carousel-badge">
                <i class="fa-solid fa-images"></i> <span class="current-slide">1</span>/${total}
            </span>
            <div class="ig-carousel-slides">
                ${formattedImages.map((src, idx) => `
                    <div class="ig-carousel-slide">
                        <img src="${src}" alt="Photo ${idx + 1}" loading="lazy">
                    </div>
                `).join('')}
            </div>
            <!-- Facebook-style left and right navigation buttons -->
            <button type="button" class="ig-carousel-btn ig-carousel-prev" aria-label="Previous photo" style="display: none;">
                <i class="fa-solid fa-chevron-left"></i>
            </button>
            <button type="button" class="ig-carousel-btn ig-carousel-next" aria-label="Next photo" style="display: flex;">
                <i class="fa-solid fa-chevron-right"></i>
            </button>
            <div class="ig-carousel-dots">
                ${formattedImages.map((_, idx) => `<span class="ig-carousel-dot ${idx === 0 ? 'active' : ''}" data-index="${idx}"></span>`).join('')}
            </div>
        </div>
    `;

    container.innerHTML = html;

    const carouselEl = container.querySelector('.ig-carousel');
    const slidesEl = carouselEl.querySelector('.ig-carousel-slides');
    const badgeEl = carouselEl.querySelector('.current-slide');
    const prevBtn = carouselEl.querySelector('.ig-carousel-prev');
    const nextBtn = carouselEl.querySelector('.ig-carousel-next');
    const dots = carouselEl.querySelectorAll('.ig-carousel-dot');

    function updateSlide(index) {
        if (index < 0) index = 0;
        if (index >= total) index = total - 1;
        currentIndex = index;

        slidesEl.style.transform = `translateX(-${currentIndex * 100}%)`;
        if (badgeEl) badgeEl.textContent = currentIndex + 1;
        dots.forEach((d, i) => d.classList.toggle('active', i === currentIndex));

        // Facebook format:
        // On 1st image: left arrow is hidden, right arrow is visible (right arrow as default)
        // On middle images: both arrows are visible
        // On last image: left arrow is visible, right arrow is hidden
        if (prevBtn) {
            prevBtn.style.display = (currentIndex === 0) ? 'none' : 'flex';
        }
        if (nextBtn) {
            nextBtn.style.display = (currentIndex === total - 1) ? 'none' : 'flex';
        }
    }

    // Set initial arrow states (slide 0)
    updateSlide(0);

    prevBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        e.preventDefault();
        if (currentIndex > 0) {
            updateSlide(currentIndex - 1);
        }
    });

    nextBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        e.preventDefault();
        if (currentIndex < total - 1) {
            updateSlide(currentIndex + 1);
        }
    });

    dots.forEach(dot => {
        dot.addEventListener('click', (e) => {
            e.stopPropagation();
            e.preventDefault();
            updateSlide(parseInt(dot.getAttribute('data-index'), 10));
        });
    });
};

window.renderInstagramCarousel = window.renderFacebookCarousel;
