<?php
session_start();
error_reporting(E_ALL);

include '../connection/db.php';
include '../security/crypt.php';
include 'includes/logger.php';
date_default_timezone_set('Asia/Manila');

$logger = new Logger();

if (strlen($_SESSION['alogin']) == 0) {
    header('location:index.php');
    exit;
}

function deleteBook($conn, $bookID)
{
    $stmt = $conn->prepare("DELETE FROM books WHERE book_id = ?");
    $stmt->bind_param("i", $bookID);
    return $stmt->execute();
}

if (isset($_GET['del'])) {
    $encryptedID = $_GET['del'];
    $decryptedID = decrypt($encryptedID);

    if (!$decryptedID) {
        $_SESSION['delmsg'] = "Invalid book ID.";
        header("location: books.php");
        exit;
    }

    if (deleteBook($conn, $decryptedID)) {
        $_SESSION['delmsg'] = "Book deleted successfully.";
        $logger->write("Book deleted.");
    } else {
        $_SESSION['delmsg'] = "Failed to delete book.";
    }

    header("location: books.php");
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

if (isset($_GET['download']) && $_GET['download'] == 1) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="books.csv"');

    $output = fopen("php://output", "w");
    fputcsv($output, ['#', 'Title', 'Category', 'Author', 'ISBN', 'Quantity']);

    $allBooks = getBookDetails($conn, 1, 999999);
    $cnt = 1;
    foreach ($allBooks['books'] as $book) {
        fputcsv($output, [$cnt++, $book['title'], $book['category'], $book['author'], $book['isbn'], $book['quantity']]);
    }

    fclose($output);
    exit;
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
    <meta charset="UTF-8">
    <title>Manage Books</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="../css/books.css">
</head>

<body>
    <?php include('includes/header.php'); ?>

    <div class="library-container">
        <div class="page-header">
            <div class="page-title">
                <h1>Library Collection</h1>
            </div>
            <div class="header-actions">
                <a href="books.php?download=1" class="btn btn-secondary">
                    <i class="fas fa-file-csv"></i> Export CSV
                </a>
                <button type="button" onclick="window.print()" class="btn btn-secondary">
                    <i class="fas fa-print"></i> Print
                </button>
                <a href="add-book.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Add Book
                </a>
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
                    <?php $encryptedID = encrypt($book['book_id']); ?>

                    <!-- Replace the book card section (around line 220-270) with this updated version -->

                    <div class="book-item">
                        <div class="book-card">
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
                                    } elseif (file_exists('uploads/' . $book['image'])) {
                                        $imageSrc = 'uploads/' . $book['image'];
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

                            <div class="book-hover-details">
                                <div class="book-hover-details-content">
                                    <h4 class="book-hover-title"><?= htmlentities($book['title']) ?></h4>
                                    <p class="book-hover-author"><?= htmlentities($book['author']) ?></p>
                                    <div class="book-hover-meta">
                                        <p class="book-hover-isbn">ISBN: <?= htmlentities($book['isbn']) ?></p>
                                        <p class="book-hover-category">Category:
                                            <?= htmlentities($book['category'] ?: 'Uncategorized') ?></p>

                                        <?php
                                        // Determine stock status and styling
                                        $quantity = $book['quantity'];
                                        $stockClass = 'in-stock';
                                        $stockText = "In Stock: $quantity";

                                        if ($quantity == 0) {
                                            $stockClass = 'out-of-stock';
                                            $stockText = "Out of Stock";
                                        } elseif ($quantity < 5) {
                                            $stockClass = 'low-stock';
                                            $stockText = "Low Stock: $quantity";
                                        }
                                        ?>

                                        <p class="book-hover-stock <?= $stockClass ?>">
                                            <?= $stockText ?>
                                        </p>
                                    </div>
                                    <div class="book-hover-actions">
                                        <a href="edit-book.php?id=<?= urlencode($encryptedID) ?>" class="action-btn edit">
                                            Edit
                                        </a>
                                        <a href="books.php?del=<?= urlencode($encryptedID) ?>" class="action-btn delete"
                                            onclick="return confirmDelete('<?= addslashes($book['title']) ?>')">
                                            Delete
                                        </a>
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
                        $range = 2; // Pages to show on each side of current page
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
                <p>Try adjusting your search or filters, or start building your library by adding your first book!</p>
            </div>
        <?php endif; ?>
    </div>

    <script src="../js/books.js"></script>
    <script>
        // Function to change items per page
        function changePerPage(value) {
            const url = new URL(window.location.href);
            url.searchParams.set('per_page', value);
            url.searchParams.set('page', '1'); // Reset to first page
            window.location.href = url.toString();
        }
    </script>
</body>

</html>