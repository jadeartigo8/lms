// Books page functionality

// Auto-dismiss alerts after 5 seconds
document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity 0.5s ease';
            alert.style.opacity = '0';
            setTimeout(() => {
                alert.remove();
            }, 500);
        }, 5000);
    });

    // Enhanced image error handling
    const bookImages = document.querySelectorAll('.book-cover img');
    bookImages.forEach(img => {
        img.addEventListener('error', function() {
            this.style.display = 'none';
            const noImageDiv = this.parentElement.querySelector('.no-image');
            if (noImageDiv) {
                noImageDiv.style.display = 'flex';
            }
        });
    });

    // Add animation on scroll for book cards
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver(function(entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '0';
                entry.target.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    entry.target.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }, 100);
                observer.unobserve(entry.target);
            }
        });
    }, observerOptions);

    const bookCards = document.querySelectorAll('.book-card');
    bookCards.forEach(card => {
        observer.observe(card);
    });

    // Show active filters count
    const urlParams = new URLSearchParams(window.location.search);
    let activeFilters = 0;
    
    if (urlParams.get('search')) activeFilters++;
    if (urlParams.get('author')) activeFilters++;
    if (urlParams.get('category')) activeFilters++;
    if (urlParams.get('sort') && urlParams.get('sort') !== 'title_asc') activeFilters++;
    
    if (activeFilters > 0) {
        const pageTitle = document.querySelector('.page-title h1');
        if (pageTitle) {
            const badge = document.createElement('span');
            badge.style.cssText = 'background: rgb(32, 142, 58); color: white; padding: 5px 12px; border-radius: 20px; font-size: 14px; margin-left: 10px;';
            badge.textContent = `${activeFilters} filter${activeFilters > 1 ? 's' : ''} active`;
            pageTitle.appendChild(badge);
        }
    }

    // Search debounce for better performance
    let searchTimeout;
    const searchInput = document.querySelector('.search-box input');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            const form = this.closest('form');
            searchTimeout = setTimeout(() => {
                form.submit();
            }, 800);
        });
    }

    // Smooth scroll to top on filter change
    const filterSelects = document.querySelectorAll('select.form-select');
    filterSelects.forEach(select => {
        select.addEventListener('change', function() {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
    });

    // Enhanced hover effects for touch devices
    const bookCardsAll = document.querySelectorAll('.book-card');
    bookCardsAll.forEach(card => {
        card.addEventListener('touchstart', function() {
            this.classList.add('hover-active');
        });
        
        card.addEventListener('touchend', function() {
            setTimeout(() => {
                this.classList.remove('hover-active');
            }, 3000);
        });
    });
});

// Confirm delete with enhanced message
function confirmDelete(bookTitle) {
    return confirm(`Are you sure you want to delete "${bookTitle}"?\n\nThis action cannot be undone.`);
}

// Handle print functionality
window.addEventListener('beforeprint', function() {
    console.log('Preparing to print...');
});

window.addEventListener('afterprint', function() {
    console.log('Print completed or cancelled');
});

// Add loading state to form submissions
document.addEventListener('DOMContentLoaded', function() {
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function() {
            const submitButtons = form.querySelectorAll('button[type="submit"], input[type="submit"]');
            submitButtons.forEach(button => {
                button.disabled = true;
                button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
            });
        });
    });
});