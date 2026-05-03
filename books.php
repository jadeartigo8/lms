<?php
session_start();
error_reporting(E_ALL);

include 'connection/db.php';
include 'security/crypt.php';
date_default_timezone_set('Asia/Manila');

if (strlen($_SESSION['login']) == 0) {
    header('location:index.php');
    exit;
}

function getBookDetails($conn, $page = 1, $perPage = 12)
{
    $search = $_GET['search'] ?? '';
    $author = $_GET['author'] ?? '';
    $category = $_GET['category'] ?? '';
    $sort = $_GET['sort'] ?? 'title_asc';

    // Build WHERE clause
    $sql = "SELECT * FROM books WHERE title LIKE ?";
    $params = ["%$search%"];
    $types = "s";

    if (!empty($author)) {
        $sql .= " AND author = ?";
        $params[] = $author;
        $types .= "s";
    }

    if (!empty($category)) {
        $sql .= " AND category = ?";
        $params[] = $category;
        $types .= "s";
    }

    // Get total count
    $countSql = str_replace("SELECT *", "SELECT COUNT(*) as total", $sql);
    $countStmt = $conn->prepare($countSql);
    $countStmt->bind_param($types, ...$params);
    $countStmt->execute();
    $totalResult = $countStmt->get_result();
    $totalBooks = $totalResult->fetch_assoc()['total'];

    // Add ORDER BY
    $order = "";
    switch ($sort) {
        case 'title_asc':
            $order = " ORDER BY title ASC";
            break;
        case 'title_desc':
            $order = " ORDER BY title DESC";
            break;
        case 'qty_asc':
            $order = " ORDER BY quantity ASC";
            break;
        case 'qty_desc':
            $order = " ORDER BY quantity DESC";
            break;
        case 'category':
            $order = " ORDER BY category ASC, title ASC";
            break;
        default:
            $order = " ORDER BY title ASC";
            break;
    }

    // Add LIMIT and OFFSET
    $offset = ($page - 1) * $perPage;
    $sql .= $order . " LIMIT ? OFFSET ?";
    $params[] = $perPage;
    $params[] = $offset;
    $types .= "ii";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    $books = [];
    while ($row = $result->fetch_assoc()) {
        $books[] = $row;
    }

    return [
        'books' => $books,
        'total' => $totalBooks,
        'pages' => ceil($totalBooks / $perPage)
    ];
}

function getAuthors($conn)
{
    $authors = [];
    $sql = "SELECT DISTINCT author FROM books WHERE author IS NOT NULL AND author != '' ORDER BY author ASC";
    $result = $conn->query($sql);
    while ($row = $result->fetch_assoc()) {
        $authors[] = $row['author'];
    }
    return $authors;
}

function getCategories($conn)
{
    $categories = [];
    $sql = "SELECT DISTINCT category FROM books WHERE category IS NOT NULL AND category != '' ORDER BY category ASC";
    $result = $conn->query($sql);
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row['category'];
    }
    return $categories;
}

// Pagination setup
$currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = isset($_GET['per_page']) ? max(1, min(100, intval($_GET['per_page']))) : 12;

$result = getBookDetails($conn, $currentPage, $perPage);
$books = $result['books'];
$totalBooks = $result['total'];
$totalPages = $result['pages'];

$authors = getAuthors($conn);
$categories = getCategories($conn);

// Calculate display range
$startEntry = $totalBooks > 0 ? (($currentPage - 1) * $perPage) + 1 : 0;
$endEntry = min($currentPage * $perPage, $totalBooks);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Books</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="css/books.css">
</head>

<body>
    <?php include('includes/header.php'); ?>

    <div class="library-container">
        <div class="page-header">
            <div class="page-title">
                <h1>Browse Books</h1>
            </div>
        </div>

        <div class="toolbar">
            <form method="get" class="toolbar-content">
                <input type="hidden" name="page" value="1">
                <div class="toolbar-left">
                    <div class="search-box">
                        <input type="text" name="search" placeholder="Search books by title..."
                            value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" />
                        <i class="fas fa-search"></i>
                    </div>
                </div>

                <div class="toolbar-right">
                    <select name="author" class="form-select" onchange="this.form.submit()">
                        <option value="">All Authors</option>
                        <?php foreach ($authors as $author): ?>
                            <option value="<?= htmlspecialchars($author) ?>" <?= ($_GET['author'] ?? '') == $author ? 'selected' : '' ?>>
                                <?= htmlspecialchars($author) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <select name="category" class="form-select" onchange="this.form.submit()">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= htmlspecialchars($category) ?>" <?= ($_GET['category'] ?? '') == $category ? 'selected' : '' ?>>
                                <?= htmlspecialchars($category) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <select name="sort" class="form-select" onchange="this.form.submit()">
                        <option value="title_asc" <?= ($_GET['sort'] ?? 'title_asc') == 'title_asc' ? 'selected' : '' ?>>
                            Alphabetical (A-Z)</option>
                        <option value="title_desc" <?= ($_GET['sort'] ?? '') == 'title_desc' ? 'selected' : '' ?>>Title Z-A
                        </option>
                        <option value="category" <?= ($_GET['sort'] ?? '') == 'category' ? 'selected' : '' ?>>Sort by
                            Category</option>
                        <option value="qty_asc" <?= ($_GET['sort'] ?? '') == 'qty_asc' ? 'selected' : '' ?>>Stock Low-High
                        </option>
                        <option value="qty_desc" <?= ($_GET['sort'] ?? '') == 'qty_desc' ? 'selected' : '' ?>>Stock
                            High-Low</option>
                    </select>

                    <?php if (!empty($_GET['search']) || !empty($_GET['author']) || !empty($_GET['category']) || !empty($_GET['sort'])): ?>
                        <button type="button" onclick="window.location.href='books.php'" class="btn-clear">
                            <i class="fas fa-times"></i> Clear Filters
                        </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <?php
        $alerts = ['error', 'msg', 'updatemsg', 'delmsg'];
        foreach ($alerts as $alert) {
            if (!empty($_SESSION[$alert])) {
                $type = ($alert == 'error') ? 'danger' : 'success';
                $icon = ($alert == 'error') ? 'fa-exclamation-circle' : 'fa-check-circle';
                echo '<div class="alert alert-' . $type . '">';
                echo '<i class="fas ' . $icon . '"></i>';
                echo '<span>' . htmlentities($_SESSION[$alert]) . '</span>';
                echo '</div>';
                $_SESSION[$alert] = "";
            }
        }
        ?>

        <?php if (count($books) > 0): ?>
            <!-- Entries info and per page selector -->
            <div class="datatable-info">
                <div class="datatable-length">
                    <label>
                        Show
                        <select name="per_page" onchange="changePerPage(this.value)">
                            <option value="12" <?= $perPage == 12 ? 'selected' : '' ?>>12</option>
                            <option value="24" <?= $perPage == 24 ? 'selected' : '' ?>>24</option>
                            <option value="48" <?= $perPage == 48 ? 'selected' : '' ?>>48</option>
                            <option value="96" <?= $perPage == 96 ? 'selected' : '' ?>>96</option>
                        </select>
                        entries
                    </label>
                </div>
                <div class="datatable-showing">
                    Showing <?= $startEntry ?> to <?= $endEntry ?> of <?= $totalBooks ?> entries
                </div>
            </div>

            <div class="books-grid">
                <?php foreach ($books as $book): ?>
                    <!-- Replace the book card section in the books grid loop with this updated version -->

                    <div class="book-item">
                        <div class="book-card book-card-view-only">
                            <div class="book-cover">
                                <?php if ($book['quantity'] == 0): ?>
                                    <div class="out-of-stock-overlay">
                                        <span style="color: white;">OUT OF STOCK</span>
                                    </div>
                                <?php endif; ?>

                                <?php
                                $hasImage = false;
                                $imageSrc = '';
                                if (!empty($book['image'])) {
                                    if (filter_var($book['image'], FILTER_VALIDATE_URL)) {
                                        $imageSrc = $book['image'];
                                        $hasImage = true;
                                    } elseif (file_exists('admin/uploads/' . $book['image'])) {
                                        $imageSrc = 'admin/uploads/' . $book['image'];
                                        $hasImage = true;
                                    }
                                }
                                ?>

                                <?php if ($hasImage): ?>
                                    <img src="<?= htmlentities($imageSrc) ?>" alt="<?= htmlentities($book['title']) ?>"
                                        onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                <?php endif; ?>

                                <div class="no-image" style="display: <?= $hasImage ? 'none' : 'flex' ?>;">
                                    <i class="fas fa-book"></i>
                                    <p>No Cover</p>
                                </div>
                            </div>

                            <div class="book-info-overlay">
                                <div class="book-info-content">
                                    <h4 class="book-info-title"><?= htmlentities($book['title']) ?></h4>
                                    <p class="book-info-author">by <?= htmlentities($book['author']) ?></p>
                                    <div class="book-info-meta">
                                        <p><strong>ISBN:</strong> <?= htmlentities($book['isbn']) ?></p>
                                        <p><strong>Category:</strong> <?= htmlentities($book['category'] ?: 'Uncategorized') ?>
                                        </p>

                                        <?php
                                        // Determine stock status and styling
                                        $quantity = $book['quantity'];
                                        $stockClass = 'in-stock';
                                        $stockLabel = 'Available';

                                        if ($quantity == 0) {
                                            $stockClass = 'out-of-stock';
                                            $stockLabel = 'Out of Stock';
                                        } elseif ($quantity < 5) {
                                            $stockClass = 'low-stock';
                                            $stockLabel = 'Low Stock';
                                        }
                                        ?>

                                        <p class="stock-status-<?= $stockClass ?>">
                                            <strong><?= $stockLabel ?>:</strong> <?= $quantity ?>
                                            <?= $quantity == 1 ? 'copy' : 'copies' ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="book-details">
                            <h3 class="book-title"><?= htmlentities($book['title']) ?></h3>
                            <p class="book-author"><?= htmlentities($book['author']) ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="datatable-pagination">
                    <div class="datatable-info-bottom">
                        Showing <?= $startEntry ?> to <?= $endEntry ?> of <?= $totalBooks ?> entries
                    </div>

                    <div class="pagination">
                        <?php
                        // Build query string for pagination links
                        $queryParams = $_GET;
                        unset($queryParams['page']);
                        $queryString = http_build_query($queryParams);
                        $queryString = $queryString ? '&' . $queryString : '';
                        ?>

                        <!-- Previous button -->
                        <?php if ($currentPage > 1): ?>
                            <a href="?page=<?= $currentPage - 1 ?><?= $queryString ?>" class="page-link">Previous</a>
                        <?php else: ?>
                            <span class="page-link disabled">Previous</span>
                        <?php endif; ?>

                        <?php
                        // Calculate page range to display
                        $range = 2;
                        $startPage = max(1, $currentPage - $range);
                        $endPage = min($totalPages, $currentPage + $range);

                        // Show first page
                        if ($startPage > 1):
                            ?>
                            <a href="?page=1<?= $queryString ?>" class="page-link">1</a>
                            <?php if ($startPage > 2): ?>
                                <span class="page-link ellipsis">...</span>
                            <?php endif; ?>
                        <?php endif; ?>

                        <!-- Page numbers -->
                        <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                            <?php if ($i == $currentPage): ?>
                                <span class="page-link active"><?= $i ?></span>
                            <?php else: ?>
                                <a href="?page=<?= $i ?><?= $queryString ?>" class="page-link"><?= $i ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>

                        <!-- Show last page -->
                        <?php if ($endPage < $totalPages): ?>
                            <?php if ($endPage < $totalPages - 1): ?>
                                <span class="page-link ellipsis">...</span>
                            <?php endif; ?>
                            <a href="?page=<?= $totalPages ?><?= $queryString ?>" class="page-link"><?= $totalPages ?></a>
                        <?php endif; ?>

                        <!-- Next button -->
                        <?php if ($currentPage < $totalPages): ?>
                            <a href="?page=<?= $currentPage + 1 ?><?= $queryString ?>" class="page-link">Next</a>
                        <?php else: ?>
                            <span class="page-link disabled">Next</span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-book-open"></i>
                <h3>No books found</h3>
                <p>Try adjusting your search or filters to find the books you're looking for.</p>
            </div>
        <?php endif; ?>
    </div>


    <script>
        // Function to change items per page
        function changePerPage(value) {
            const url = new URL(window.location.href);
            url.searchParams.set('per_page', value);
            url.searchParams.set('page', '1');
            window.location.href = url.toString();

        }

        // Books page functionality for user view

        // Auto-dismiss alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function () {
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
                img.addEventListener('error', function () {
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

            const observer = new IntersectionObserver(function (entries) {
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
                searchInput.addEventListener('input', function () {
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
                select.addEventListener('change', function () {
                    window.scrollTo({
                        top: 0,
                        behavior: 'smooth'
                    });
                });
            });

            // Enhanced hover effects for touch devices
            const bookCardsAll = document.querySelectorAll('.book-card');
            bookCardsAll.forEach(card => {
                card.addEventListener('touchstart', function () {
                    this.classList.add('hover-active');
                });

                card.addEventListener('touchend', function () {
                    setTimeout(() => {
                        this.classList.remove('hover-active');
                    }, 3000);
                });
            });

            // Add click handler to show book details on mobile
            const viewOnlyCards = document.querySelectorAll('.book-card-view-only');
            viewOnlyCards.forEach(card => {
                card.addEventListener('click', function (e) {
                    // Toggle active class for mobile view
                    if (window.innerWidth <= 768) {
                        const wasActive = this.classList.contains('active');

                        // Remove active from all cards
                        viewOnlyCards.forEach(c => c.classList.remove('active'));

                        // Toggle current card
                        if (!wasActive) {
                            this.classList.add('active');
                        }
                    }
                });
            });

            // Close active card when clicking outside
            document.addEventListener('click', function (e) {
                if (!e.target.closest('.book-card-view-only')) {
                    viewOnlyCards.forEach(card => card.classList.remove('active'));
                }
            });
        });

        // Add loading state to form submissions
        document.addEventListener('DOMContentLoaded', function () {
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                form.addEventListener('submit', function () {
                    const submitButtons = form.querySelectorAll('button[type="submit"], input[type="submit"]');
                    submitButtons.forEach(button => {
                        button.disabled = true;
                        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
                    });
                });
            });
        });
    </script>
</body>

</html>