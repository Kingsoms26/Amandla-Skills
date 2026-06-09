// dashboard.js

// Tab switching
function switchTab(tabId) {
    var triggerEl = document.querySelector(tabId);
    if (triggerEl) {
        var tab = new bootstrap.Tab(triggerEl);
        tab.show();
        var container = document.getElementById('adminTabs') || document.getElementById('providerTabs');
        if(container) container.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

// Pagination Engine
function setupPagination(listId, itemClass, itemsPerPage = 6) {
    const container = document.getElementById(listId);
    if (!container) return;
    
    let items = Array.from(container.querySelectorAll(itemClass));
    const nav = document.createElement('div');
    nav.className = 'd-flex justify-content-between align-items-center mt-3 px-3 pb-3 pagination-controls border-top pt-3';
    nav.innerHTML = `
        <button class="btn btn-sm btn-outline-secondary fw-bold rounded-pill prev-btn px-4">Previous</button>
        <span class="small text-muted fw-bold page-info"></span>
        <button class="btn btn-sm btn-outline-secondary fw-bold rounded-pill next-btn px-4">Next</button>
    `;
    
    if(container.tagName === 'TABLE') {
        container.parentElement.appendChild(nav);
    } else {
        container.appendChild(nav);
    }

    let currentPage = 1;
    function showPage(page) {
        let visibleItems = items.filter(item => !item.classList.contains('search-hidden'));
        let totalPages = Math.ceil(visibleItems.length / itemsPerPage) || 1;
        if (page < 1) page = 1;
        if (page > totalPages) page = totalPages;
        currentPage = page;

        items.forEach(item => { item.style.display = 'none'; });
        let startIndex = (currentPage - 1) * itemsPerPage;
        let endIndex = startIndex + itemsPerPage;
        visibleItems.slice(startIndex, endIndex).forEach(item => { item.style.display = ''; });

        nav.querySelector('.prev-btn').disabled = currentPage === 1;
        nav.querySelector('.next-btn').disabled = currentPage === totalPages;
        nav.querySelector('.page-info').textContent = `Page ${currentPage} of ${totalPages}`;
        nav.style.display = visibleItems.length > itemsPerPage ? 'flex' : 'none';
    }

    nav.querySelector('.prev-btn').addEventListener('click', (e) => { e.preventDefault(); showPage(currentPage - 1); });
    nav.querySelector('.next-btn').addEventListener('click', (e) => { e.preventDefault(); showPage(currentPage + 1); });
    showPage(1);
    container.updatePagination = () => showPage(1);
}

// Centralized Initialization
document.addEventListener('DOMContentLoaded', function () {
    // Initialize Pagination
    setupPagination('usersTable', '.user-row');
    setupPagination('bookingsTable', '.booking-row');
    setupPagination('disputesList', '.dispute-item');
    setupPagination('verificationsTable', '.verif-row');
    setupPagination('reviewsTable', '.review-row');
    setupPagination('servicesTable', '.service-row');
    setupPagination('heldTable', '.held-row');
    setupPagination('payDisputesList', '.pay-dispute-item');

    // Notifications and Tab triggers
    const params = new URLSearchParams(window.location.search);
    if (params.get('open') === 'notifications') {
        const modal = new bootstrap.Modal(document.getElementById('notificationsModal'));
        modal.show();
    }
    if (params.get('open') === 'disputes') {
        var disputeTab = document.querySelector('#disputes-tab');
        if (disputeTab) new bootstrap.Tab(disputeTab).show();
    }
});

// Password Toggle and Strength
function asmToggle() {
    const i = document.getElementById('asmPw');
    if(i) i.type = i.type === 'password' ? 'text' : 'password';
}
function asmStrength(v) {
    let s = 0;
    if (v.length >= 6) s++;
    if (v.length >= 10) s++;
    if (/[A-Z]/.test(v) && /[0-9]/.test(v)) s++;
    if (/[^A-Za-z0-9]/.test(v)) s++;
    [1,2,3,4].forEach(n => {
        let bar = document.getElementById('ab'+n);
        if(bar) bar.classList.toggle('active', n <= s);
    });
}