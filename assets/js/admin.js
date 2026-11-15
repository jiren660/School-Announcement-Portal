/**
 * School Announcement System - Admin Portal JS
 * Handles responsive sidebar, modals, live table filter, and confirmations.
 */

document.addEventListener('DOMContentLoaded', function () {
    // 1. Mobile Sidebar Toggle
    const sidebarToggle = document.getElementById('sidebarToggle');
    const adminSidebar = document.getElementById('adminSidebar');
    const sidebarCloseBtn = document.getElementById('sidebarCloseBtn');
    const sidebarBackdrop = document.getElementById('sidebarBackdrop');

    function openSidebar() {
        if (adminSidebar) adminSidebar.classList.add('sidebar-open');
        if (sidebarBackdrop) sidebarBackdrop.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeSidebar() {
        if (adminSidebar) adminSidebar.classList.remove('sidebar-open');
        if (sidebarBackdrop) sidebarBackdrop.classList.remove('active');
        document.body.style.overflow = '';
    }

    if (sidebarToggle) sidebarToggle.addEventListener('click', openSidebar);
    if (sidebarCloseBtn) sidebarCloseBtn.addEventListener('click', closeSidebar);
    if (sidebarBackdrop) sidebarBackdrop.addEventListener('click', closeSidebar);

    // 2. Flash Alert Auto-Dismiss
    const flashAlert = document.getElementById('flashAlert');
    if (flashAlert) {
        setTimeout(function () {
            flashAlert.style.transition = 'opacity 0.4s ease';
            flashAlert.style.opacity = '0';
            setTimeout(() => flashAlert.remove(), 400);
        }, 5000);
    }

    // 3. Generic Modal Handlers
    window.openModal = function (modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add('open');
            document.body.style.overflow = 'hidden';
            // Auto focus first input if exists
            const input = modal.querySelector('input:not([type=hidden]), textarea');
            if (input) setTimeout(() => input.focus(), 100);
        }
    };

    window.closeModal = function (modalId) {
        const modal = typeof modalId === 'string' ? document.getElementById(modalId) : modalId.closest('.modal-overlay');
        if (modal) {
            modal.classList.remove('open');
            document.body.style.overflow = '';
        }
    };

    // Close modal on click backdrop
    document.querySelectorAll('.modal-overlay').forEach(modal => {
        modal.addEventListener('click', function (e) {
            if (e.target === this) {
                closeModal(this);
            }
        });
    });

    // Close modal on ESC key
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            const openModalEl = document.querySelector('.modal-overlay.open');
            if (openModalEl) closeModal(openModalEl);
            closeSidebar();
        }
    });

    // 4. Live Table Filter (for tables with data-searchable="true")
    const searchInputs = document.querySelectorAll('[data-table-search]');
    searchInputs.forEach(input => {
        const targetTableId = input.getAttribute('data-table-search');
        const targetTable = document.getElementById(targetTableId);
        if (!targetTable) return;

        input.addEventListener('input', function () {
            const query = this.value.toLowerCase().trim();
            const rows = targetTable.querySelectorAll('tbody tr:not(.no-search-results)');
            let visibleCount = 0;

            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                if (text.includes(query)) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });

            // Handle empty state row if exists
            let emptyRow = targetTable.querySelector('.no-search-results');
            if (visibleCount === 0 && rows.length > 0) {
                if (!emptyRow) {
                    emptyRow = document.createElement('tr');
                    emptyRow.className = 'no-search-results';
                    const colCount = targetTable.querySelectorAll('thead th').length || 6;
                    emptyRow.innerHTML = `<td colspan="${colCount}" class="text-center" style="padding: 2rem; text-align: center; color: #64748b;">No matching records found.</td>`;
                    targetTable.querySelector('tbody').appendChild(emptyRow);
                }
                emptyRow.style.display = '';
            } else if (emptyRow) {
                emptyRow.style.display = 'none';
            }
        });
    });
});

/**
 * Global Instagram-Style Carousel Renderer
 * @param {Array<string>} images - Array of relative or absolute image URLs
 * @param {string} containerId - Element ID to render carousel into
 * @param {string} prefix - Path prefix (e.g. '../' for admin/announcer pages, '' for root)
 */
window.renderInstagramCarousel = function (images, containerId, prefix = '../') {
    const container = document.getElementById(containerId);
    if (!container) return;

    if (!images || images.length === 0) {
        container.innerHTML = '';
        container.style.display = 'none';
        return;
    }

    container.style.display = 'block';
    const total = images.length;
    let currentIndex = 0;

    const formattedImages = images.map(img => {
        if (!img) return '';
        if (img.startsWith('http') || img.startsWith('/') || img.startsWith('data:')) {
            return img;
        }
        return prefix + img;
    }).filter(img => img !== '');

    if (formattedImages.length === 0) {
        container.innerHTML = '';
        container.style.display = 'none';
        return;
    }

    let html = `
        <div class="ig-carousel">
            ${total > 1 ? `<span class="ig-carousel-badge"><i class="fa-solid fa-images"></i> <span class="current-slide">1</span>/${total}</span>` : ''}
            <div class="ig-carousel-slides">
                ${formattedImages.map((src, idx) => `
                    <div class="ig-carousel-slide">
                        <img src="${src}" alt="Photo ${idx + 1}" loading="lazy">
                    </div>
                `).join('')}
            </div>
            ${total > 1 ? `
                <button type="button" class="ig-carousel-btn ig-carousel-prev" aria-label="Previous"><i class="fa-solid fa-chevron-left"></i></button>
                <button type="button" class="ig-carousel-btn ig-carousel-next" aria-label="Next"><i class="fa-solid fa-chevron-right"></i></button>
                <div class="ig-carousel-dots">
                    ${formattedImages.map((_, idx) => `<span class="ig-carousel-dot ${idx === 0 ? 'active' : ''}" data-index="${idx}"></span>`).join('')}
                </div>
            ` : ''}
        </div>
    `;

    container.innerHTML = html;

    if (total <= 1) return;

    const carouselEl = container.querySelector('.ig-carousel');
    const slidesEl = carouselEl.querySelector('.ig-carousel-slides');
    const badgeEl = carouselEl.querySelector('.current-slide');
    const prevBtn = carouselEl.querySelector('.ig-carousel-prev');
    const nextBtn = carouselEl.querySelector('.ig-carousel-next');
    const dots = carouselEl.querySelectorAll('.ig-carousel-dot');

    function updateSlide(index) {
        if (index < 0) index = total - 1;
        if (index >= total) index = 0;
        currentIndex = index;
        slidesEl.style.transform = `translateX(-${currentIndex * 100}%)`;
        if (badgeEl) badgeEl.textContent = currentIndex + 1;
        dots.forEach((d, i) => d.classList.toggle('active', i === currentIndex));
    }

    prevBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        e.preventDefault();
        updateSlide(currentIndex - 1);
    });

    nextBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        e.preventDefault();
        updateSlide(currentIndex + 1);
    });

    dots.forEach(dot => {
        dot.addEventListener('click', (e) => {
            e.stopPropagation();
            e.preventDefault();
            updateSlide(parseInt(dot.getAttribute('data-index'), 10));
        });
    });
};

