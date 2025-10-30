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

// ---------- FETCH BOOK ----------
$book = null;
if (isset($_GET['id'])) {
    $encryptedID = $_GET['id'];
    $decryptedID = decrypt($encryptedID);

    if (!$decryptedID) {
        $_SESSION['error'] = "Invalid book ID.";
        header("location: books.php");
        exit;
    }

    $stmt = $conn->prepare("SELECT * FROM books WHERE book_id = ?");
    $stmt->bind_param("i", $decryptedID);
    $stmt->execute();
    $result = $stmt->get_result();
    $book = $result->fetch_assoc();
    $stmt->close();

    if (!$book) {
        $_SESSION['error'] = "Book not found.";
        header("location: books.php");
        exit;
    }
} else {
    header("location: books.php");
    exit;
}

// ---------- UPDATE BOOK ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title     = trim($_POST['title']);
    $category  = trim($_POST['category']);
    $author    = trim($_POST['author']);
    $isbn      = trim($_POST['isbn']);
    $quantity  = (int)$_POST['quantity'];

    $imageName = $book['image']; // keep old

    // ---- Image upload (only if new file) ----
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = "uploads/";
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        $imageName = time() . '_' . basename($_FILES['image']['name']);
        $targetPath = $uploadDir . $imageName;

        if (move_uploaded_file($_FILES['image']['tmp_name'], $targetPath)) {
            // Optional: delete old local image
            if ($book['image'] && file_exists("uploads/" . $book['image'])) {
                @unlink("uploads/" . $book['image']);
            }
        } else {
            $_SESSION['error'] = "Failed to upload image.";
        }
    }

    // ---- ISBN uniqueness (except current book) ----
    $stmt = $conn->prepare("SELECT COUNT(*) FROM books WHERE isbn = ? AND book_id != ?");
    $stmt->bind_param("si", $isbn, $decryptedID);
    $stmt->execute();
    $stmt->bind_result($isbnExists);
    $stmt->fetch();
    $stmt->close();

    if ($isbnExists > 0) {
        $_SESSION['isbnError'] = "ISBN already exists for another book.";
        // Stay on edit page
        $book = array_merge($book, $_POST);
        $book['image'] = $imageName;
    } else {
        // ---- UPDATE DB ----
        $stmt = $conn->prepare("
            UPDATE books 
            SET title=?, category=?, author=?, isbn=?, quantity=?, image=?, update_date=NOW()
            WHERE book_id=?
        ");
        $stmt->bind_param("ssssssi", $title, $category, $author, $isbn, $quantity, $imageName, $decryptedID);

        if ($stmt->execute()) {
            $_SESSION['updatemsg'] = "Book updated successfully.";
            $logger->write("Book '$title' (ID: $decryptedID) updated by admin.");
        } else {
            $_SESSION['error'] = "Failed to update book.";
        }
        $stmt->close();
        header("Location: books.php");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Book</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="../css/edit-book.css">
</head>
<body>

<?php include('includes/header.php'); ?>

<div class="edit-book-container">
    <h3><i class="fas fa-edit"></i> Edit Book</h3>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert error"><i class="fas fa-exclamation-triangle"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <?php if (isset($_SESSION['isbnError'])): ?>
        <div class="alert warning"><i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['isbnError']; unset($_SESSION['isbnError']); ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" id="editBookForm">
        <div class="form-group">
            <label><i class="fas fa-book"></i> Title</label>
            <input type="text" name="title" value="<?php echo htmlspecialchars($book['title'] ?? ''); ?>" required>
        </div>

        <div class="form-group">
            <label><i class="fas fa-tags"></i> Category</label>
            <input type="text" name="category" value="<?php echo htmlspecialchars($book['category'] ?? ''); ?>" required>
        </div>

        <div class="form-group">
            <label><i class="fas fa-user-edit"></i> Author</label>
            <input type="text" name="author" value="<?php echo htmlspecialchars($book['author'] ?? ''); ?>" required>
        </div>

        <div class="form-group">
            <label><i class="fas fa-barcode"></i> ISBN</label>
            <input type="text" name="isbn" value="<?php echo htmlspecialchars($book['isbn'] ?? ''); ?>" required>
        </div>

        <div class="form-group">
            <label><i class="fas fa-cubes"></i> Quantity</label>
            <input type="number" name="quantity" min="0" value="<?php echo htmlspecialchars($book['quantity'] ?? ''); ?>" required>
        </div>

        <!-- IMAGE PREVIEW -->
        <div class="form-group">
            <label><i class="fas fa-image"></i> Current Image</label>
            <div id="imagePreview">
                <?php
                $img = $book['image'] ?? '';
                $isUrl = filter_var($img, FILTER_VALIDATE_URL);
                if ($img && $isUrl): ?>
                    <img src="<?php echo htmlspecialchars($img); ?>" alt="Book Cover">
                <?php elseif ($img): ?>
                    <img src="uploads/<?php echo htmlspecialchars($img); ?>" alt="Book Cover">
                <?php else: ?>
                    <div class="no-image"><i class="fas fa-book"></i><br>No Image</div>
                <?php endif; ?>
            </div>
            <input type="file" name="image" accept="image/*" id="imageInput">
            <small>Leave blank to keep current image.</small>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn-submit"><i class="fas fa-save"></i> Update Book</button>
            <a href="books.php" class="btn-cancel">Cancel</a>
        </div>
    </form>
</div>

<script src="../js/edit-book.js"></script>
</body>
</html>