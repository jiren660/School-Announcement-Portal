/**
 * School Announcement Portal - Client-Side Interactivity
 */

document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('search-input');
    const categoryPills = document.querySelectorAll('.pill-btn');
    const cards = document.querySelectorAll('.announcement-card');
    const modal = document.getElementById('announcement-modal');
    const modalCloseBtn = document.getElementById('modal-close-btn');
    const modalTitle = document.getElementById('modal-title');
    const modalCategory = document.getElementById('modal-category');
    const modalDate = document.getElementById('modal-date');
    const modalAuthor = document.getElementById('modal-author');
    const modalBody = document.getElementById('modal-body');
    const noResultsMsg = document.getElementById('no-results-msg');

    let currentCategory = 'all';
    let searchQuery = '';

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
