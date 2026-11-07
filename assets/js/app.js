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

    let currentCategory = 'all';
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

            const matchesCategory = (currentCategory === 'all') || (category === currentCategory);
            const matchesSearch = title.includes(searchQuery) || summary.includes(searchQuery);

            if (matchesCategory && matchesSearch) {
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
