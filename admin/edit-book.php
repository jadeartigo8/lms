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

// Fetch book details
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

    if (!$book) {
        $_SESSION['error'] = "Book not found.";
        header("location: books.php");
        exit;
    }
} else {
    header("location: books.php");
    exit;
}

// Update book
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = $_POST['title'];
    $category = $_POST['category'];
    $author = $_POST['author'];
    $isbn = $_POST['isbn'];
    $quantity = $_POST['quantity'];

    $imageName = $book['image']; 

    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $uploadDir = "uploads/";
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $imageName = basename($_FILES['image']['name']);
        $targetFilePath = $uploadDir . $imageName;
        move_uploaded_file($_FILES['image']['tmp_name'], $targetFilePath);
    }


    // checking if nag-exist na ba ang isbn except ani nga book 
    $stmt = $conn->prepare("SELECT COUNT(*) FROM books WHERE isbn = ? AND book_id != ?");
    $stmt->bind_param("si", $isbn, $decryptedID);
    $stmt->execute();
    $stmt->bind_result($isbnExists);
    $stmt->fetch();
    $stmt->close();

    if ($isbnExists > 0) {
        $_SESSION['isbnError'] = "ISBN already exists for another book. Please use a unique ISBN.";
        header("location: edit-book.php?id=" . urlencode($_GET['id']));
         exit;
    }

    // humag cheking, i-update

    $stmt = $conn->prepare("UPDATE books SET title=?, category=?, author=?, isbn=?, quantity=?, image=?, update_date=NOW() WHERE book_id=?");
    $stmt->bind_param("ssssssi", $title, $category, $author, $isbn, $quantity, $imageName, $decryptedID);

    if ($stmt->execute()) {
        $_SESSION['updatemsg'] = "Book updated successfully.";
        $logger->write("Book $title updated");
    } else {
        $_SESSION['error'] = "Failed to update book.";
    }

    header("location: books.php");
    exit;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Book</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/styles.css">
</head>
<body>

<?php include('includes/header.php'); ?>

<div class="signup-container">
    <h3>Edit Book</h3>

    <form method="POST" enctype="multipart/form-data">
        <div class="form-group">
            <label>Title</label>
            <input type="text" name="title" value="<?php echo htmlentities($book['title']); ?>" required>
        </div>

        <div class="form-group">
            <label>Category</label>
            <input type="text" name="category" value="<?php echo htmlentities($book['category']); ?>" required>
        </div>

        <div class="form-group">
            <label>Author</label>
            <input type="text" name="author" value="<?php echo htmlentities($book['author']); ?>" required>
        </div>

        <div class="form-group">
            <label>ISBN</label>
            <input type="text" name="isbn" value="<?php echo htmlentities($book['isbn']); ?>" required>
            <?php if (!empty($_SESSION['isbnError'])): ?>
                <span style="color: red; font-size: 12px;"><?php echo htmlentities($_SESSION['isbnError']); ?></span>
            <?php endif; ?>
        </div>

        <!-- <div class="form-group">
            <label>Price</label>
            <input type="text" name="price" value="<?php echo htmlentities($book['price']); ?>" required>
        </div> -->

        <div class="form-group">
            <label>Quantity</label>
            <input type="text" name="quantity" value="<?php echo htmlentities($book['quantity']); ?>" required>
        </div>

        <div class="form-group">
            <label>Book Image</label><br>
            <?php if (!empty($book['image'])): ?>
                <img src="uploads/<?php echo htmlentities($book['image']); ?>" width="100" style="margin-bottom:10px;"><br>
            <?php endif; ?>
            <input type="file" name="image" accept="image/*">
            <small>(Leave blank if you don't want to change the image)</small>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn">Update Book</button>
        </div>
    </form>

</div>

</body>
</html>
