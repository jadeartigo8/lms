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

function getBookDetails($conn)
{
    $books = [];
    $search = $_GET['search'] ?? '';
    $sort = $_GET['sort'] ?? '';

    $sql = "SELECT * FROM books WHERE title LIKE ?";
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
        default:
            $order = " ORDER BY category ASC, title ASC";
            break;
    }

    $stmt = $conn->prepare($sql . $order);
    $like = "%$search%";
    $stmt->bind_param("s", $like);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $books[] = $row;
    }

    return $books;
}

if (isset($_GET['download']) && $_GET['download'] == 1) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="books.csv"');

    $output = fopen("php://output", "w");
    fputcsv($output, ['#', 'Title', 'Category', 'Author', 'ISBN', 'Quantity']);

    $books = getBookDetails($conn);
    $cnt = 1;
    foreach ($books as $book) {
        fputcsv($output, [$cnt++, $book['title'], $book['category'], $book['author'], $book['isbn'], $book['quantity']]);
    }

    fclose($output);
    exit;
}

// Group books by category
$books = getBookDetails($conn);
$booksByCategory = [];
foreach ($books as $book) {
    $category = $book['category'] ?: 'Uncategorized';
    $booksByCategory[$category][] = $book;
}
ksort($booksByCategory);

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Manage Books</title>

    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/styles.css">

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            background: #f8f9fa;
            font-family: 'Montserrat', sans-serif;
        }

        .container {
            max-width: 1600px;
            margin: 0 auto;
            padding: 30px;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 35px;
            padding-bottom: 20px;
            border-bottom: 3px solid rgba(32, 142, 58, 0.15);
        }

        .page-title {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .page-title i {
            font-size: 36px;
            color: rgb(32, 142, 58);
        }

        .page-title h1 {
            margin: 0;
            color: #000435;
            font-size: 32px;
            font-weight: 700;
        }

        .header-actions {
            display: flex;
            gap: 12px;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: linear-gradient(135deg, rgb(32, 142, 58), rgb(27, 120, 49));
            color: white;
            box-shadow: 0 4px 12px rgba(32, 142, 58, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(32, 142, 58, 0.4);
        }

        .btn-secondary {
            background: #000435;
            color: white;
            box-shadow: 0 4px 12px rgba(0, 4, 53, 0.2);
        }

        .btn-secondary:hover {
            background: #0a0d5a;
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(0, 4, 53, 0.3);
        }

        .toolbar {
            background: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 40px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
        }

        .toolbar-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .toolbar-left, .toolbar-right {
            display: flex;
            gap: 12px;
            align-items: center;
        }

        .search-box {
            position: relative;
            flex: 1;
            min-width: 250px;
            max-width: 400px;
        }

        .search-box input {
            width: 100%;
            padding: 12px 45px 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
        }

        .search-box input:focus {
            outline: none;
            border-color: rgb(32, 142, 58);
            box-shadow: 0 0 0 3px rgba(32, 142, 58, 0.1);
        }

        .search-box i {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
        }

        select.form-select {
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            background: white;
            cursor: pointer;
            transition: all 0.3s;
            min-width: 180px;
        }

        select.form-select:focus {
            outline: none;
            border-color: rgb(32, 142, 58);
        }

        .alert {
            padding: 16px 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .alert i {
            font-size: 20px;
        }

        .alert-success {
            background: linear-gradient(135deg, rgba(32, 142, 58, 0.1), rgba(32, 142, 58, 0.05));
            color: rgb(27, 120, 49);
            border-left: 4px solid rgb(32, 142, 58);
        }

        .alert-danger {
            background: linear-gradient(135deg, rgba(220, 53, 69, 0.1), rgba(220, 53, 69, 0.05));
            color: #c82333;
            border-left: 4px solid #dc3545;
        }

        .category-section {
            margin-bottom: 60px;
        }

        .category-header {
            background: linear-gradient(135deg, #000435, #0a0d5a);
            color: white;
            padding: 20px 30px;
            border-radius: 12px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 15px;
            box-shadow: 0 4px 20px rgba(0, 4, 53, 0.3);
        }

        .category-header i {
            font-size: 28px;
        }

        .category-header h2 {
            margin: 0;
            font-size: 26px;
            font-weight: 700;
            flex: 1;
        }

        .category-badge {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            padding: 8px 20px;
            border-radius: 25px;
            font-size: 14px;
            font-weight: 600;
        }

        .books-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 30px;
            padding: 5px;
        }

        .book-card {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            display: flex;
            flex-direction: column;
            position: relative;
        }

        .book-card:hover {
            transform: translateY(-12px) scale(1.02);
            box-shadow: 0 12px 35px rgba(32, 142, 58, 0.25);
        }

        .book-cover {
            width: 100%;
            aspect-ratio: 2/3;
            background: linear-gradient(135deg, #f0f0f0, #e5e5e5);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative;
            border-bottom: 1px solid #e0e0e0;
        }

        .book-cover img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            object-position: center;
        }

        .book-cover .no-image {
            text-align: center;
            color: #bbb;
        }

        .book-cover .no-image i {
            font-size: 56px;
            margin-bottom: 12px;
            color: #ddd;
        }

        .book-cover .no-image p {
            margin: 0;
            font-size: 13px;
            font-weight: 500;
        }

        .stock-badge {
            position: absolute;
            top: 12px;
            right: 12px;
            background: rgba(32, 142, 58, 0.95);
            color: white;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            backdrop-filter: blur(10px);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        }

        .stock-badge.low {
            background: rgba(220, 53, 69, 0.95);
        }

        .book-details {
            padding: 20px;
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .book-title {
            font-size: 15px;
            font-weight: 700;
            color: #000435;
            margin: 0 0 12px 0;
            line-height: 1.4;
            min-height: 42px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .book-meta {
            margin-bottom: 15px;
        }

        .book-author {
            font-size: 13px;
            color: #666;
            margin: 0 0 6px 0;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .book-author i {
            color: rgb(32, 142, 58);
            font-size: 12px;
        }

        .book-isbn {
            font-size: 12px;
            color: #999;
            font-family: 'Courier New', monospace;
            margin: 0;
        }

        .book-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            margin-top: auto;
            padding-top: 15px;
            border-top: 1px solid #f0f0f0;
        }

        .action-btn {
            padding: 10px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .action-btn.edit {
            background: linear-gradient(135deg, rgb(32, 142, 58), rgb(27, 120, 49));
            color: white;
        }

        .action-btn.edit:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(32, 142, 58, 0.3);
        }

        .action-btn.delete {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
        }

        .action-btn.delete:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(220, 53, 69, 0.3);
        }

        .empty-state {
            text-align: center;
            padding: 80px 20px;
            background: white;
            border-radius: 16px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
        }

        .empty-state i {
            font-size: 80px;
            color: #e0e0e0;
            margin-bottom: 25px;
        }

        .empty-state h3 {
            color: #666;
            font-size: 22px;
            margin: 0 0 10px 0;
        }

        .empty-state p {
            color: #999;
            font-size: 16px;
            margin: 0;
        }

        @media (max-width: 1200px) {
            .books-grid {
                grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
                gap: 25px;
            }
        }

        @media (max-width: 768px) {
            .container {
                padding: 20px;
            }

            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }

            .header-actions {
                width: 100%;
                flex-direction: column;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }

            .toolbar-content {
                flex-direction: column;
            }

            .toolbar-left, .toolbar-right {
                width: 100%;
                flex-direction: column;
            }

            .search-box {
                max-width: 100%;
            }

            .books-grid {
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
                gap: 20px;
            }
        }

        @media print {
            body, .container {
                background: #fff !important;
            }

            .toolbar,
            .header-actions,
            .btn,
            .action-btn,
            .book-actions,
            .alert {
                display: none !important;
            }

            .books-grid {
                grid-template-columns: repeat(5, 1fr);
                gap: 15px;
            }

            .book-card {
                break-inside: avoid;
                box-shadow: none;
                border: 1px solid #ddd;
            }

            .category-header {
                background: #000435 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>

<body>
    <?php include('includes/header.php'); ?>

    <div class="container">
        <div class="page-header">
            <div class="page-title">
                <i class="fas fa-book-open"></i>
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
                <div class="toolbar-left">
                    <div class="search-box">
                        <input type="text" name="search" placeholder="Search books by title..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" />
                        <i class="fas fa-search"></i>
                    </div>
                </div>

                <div class="toolbar-right">
                    <select name="sort" class="form-select" onchange="this.form.submit()">
                        <option value="">Sort by Category</option>
                        <option value="title_asc" <?= ($_GET['sort'] ?? '') == 'title_asc' ? 'selected' : '' ?>>Title A-Z</option>
                        <option value="title_desc" <?= ($_GET['sort'] ?? '') == 'title_desc' ? 'selected' : '' ?>>Title Z-A</option>
                        <option value="qty_asc" <?= ($_GET['sort'] ?? '') == 'qty_asc' ? 'selected' : '' ?>>Stock Low-High</option>
                        <option value="qty_desc" <?= ($_GET['sort'] ?? '') == 'qty_desc' ? 'selected' : '' ?>>Stock High-Low</option>
                    </select>
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

        <?php if (count($booksByCategory) > 0): ?>
            <?php foreach ($booksByCategory as $category => $categoryBooks): ?>
                <div class="category-section">
                    <div class="category-header">
                        <i class="fas fa-layer-group"></i>
                        <h2><?= htmlentities($category) ?></h2>
                        <span class="category-badge"><?= count($categoryBooks) ?> books</span>
                    </div>

                    <div class="books-grid">
                        <?php foreach ($categoryBooks as $book): ?>
                            <?php $encryptedID = encrypt($book['book_id']); ?>
                            <div class="book-card">
                                <div class="book-cover">
                                    <?php if (!empty($book['image'])): ?>
                                        <?php if (filter_var($book['image'], FILTER_VALIDATE_URL)): ?>
                                            <img src="<?= htmlentities($book['image']) ?>" alt="<?= htmlentities($book['title']) ?>">
                                        <?php else: ?>
                                            <img src="uploads/<?= htmlentities($book['image']) ?>" alt="<?= htmlentities($book['title']) ?>">
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <div class="no-image">
                                            <i class="fas fa-book"></i>
                                            <p>No Cover</p>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <span class="stock-badge <?= $book['quantity'] < 5 ? 'low' : '' ?>">
                                        <?= htmlentities($book['quantity']) ?> in stock
                                    </span>
                                </div>

                                <div class="book-details">
                                    <h3 class="book-title"><?= htmlentities($book['title']) ?></h3>
                                    <div class="book-meta">
                                        <div class="book-author">
                                            <i class="fas fa-user-circle"></i>
                                            <span><?= htmlentities($book['author']) ?></span>
                                        </div>
                                        <p class="book-isbn">ISBN: <?= htmlentities($book['isbn']) ?></p>
                                    </div>
                                    <div class="book-actions">
                                        <a href="edit-book.php?id=<?= urlencode($encryptedID) ?>" class="action-btn edit">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                        <a href="books.php?del=<?= urlencode($encryptedID) ?>" class="action-btn delete" onclick="return confirm('Are you sure you want to delete this book?')">
                                            <i class="fas fa-trash-alt"></i> Delete
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-book-open"></i>
                <h3>No books found</h3>
                <p>Start building your library by adding your first book!</p>
            </div>
        <?php endif; ?>
    </div>

</body>

</html>