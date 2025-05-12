<?php
session_start();
include('../connection/db.php'); 
include('includes/header.php'); 

$error = "";
$success = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'] ?? '';
    $category = $_POST['category'] ?? '';
    $author = $_POST['author'] ?? '';
    $isbn = $_POST['isbn'] ?? '';
    $price = $_POST['price'] ?? '';
    $quantity = $_POST['quantity'] ?? 0;

    // File upload handling
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $imageName = basename($_FILES['image']['name']);
        $targetDir = "uploads/";
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }
        $targetFilePath = $targetDir . $imageName;
        move_uploaded_file($_FILES['image']['tmp_name'], $targetFilePath);
    } else {
        $imageName = null;
    }

    // checking if nag-exist na ba ang isbn except ani nga book 
    $stmt = $conn->prepare("SELECT COUNT(*) FROM books WHERE isbn = ?");
    $stmt->bind_param("s", $isbn);
    $stmt->execute();
    $stmt->bind_result($isbnExists);
    $stmt->fetch();
    $stmt->close();

    if ($isbnExists > 0) {
        $isbnError = "ISBN already exists for another book. Please use a unique ISBN.";
    }
  
  if (empty($isbnError)) {
      $stmt = $conn->prepare("INSERT INTO books (title, category, author, isbn,  image, isIssued, quantity, registration_date, update_date) VALUES (?, ?, ?, ?, ?, 0, ?, NOW(), NOW())");
  
      if ($stmt) {
          $stmt->bind_param("sssssi", $title, $category, $author, $isbn, $imageName, $quantity);
          if ($stmt->execute()) {
              $success = "Book added successfully!";
              
              $_POST = [];
          } else {
              $error = "Failed to add book.";
          }
      } else {
          $error = "SQL Error: " . $conn->error;
      }
  }
  
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Book</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/styles.css">
</head>
<body>

<div class="signup-container">
    <h3>Add New Book</h3>

    <?php if ($error): ?>
        <div class="custom-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="custom-error" style="background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb;">
            <?php echo htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
    <div class="form-group">
        <label>Title</label>
        <input type="text" name="title" value="<?php echo isset($_POST['title']) ? htmlentities($_POST['title']) : ''; ?>" required>
    </div>

    <div class="form-group">
        <label>Category</label>
        <input type="text" name="category" value="<?php echo isset($_POST['category']) ? htmlentities($_POST['category']) : ''; ?>" required>
    </div>

    <div class="form-group">
        <label>Author</label>
        <input type="text" name="author" value="<?php echo isset($_POST['author']) ? htmlentities($_POST['author']) : ''; ?>" required>
    </div>

    <div class="form-group">
        <label>ISBN</label>
        <input type="text" name="isbn" value="<?php echo isset($_POST['isbn']) ? htmlentities($_POST['isbn']) : ''; ?>" required>
        <?php if (!empty($isbnError)): ?>
            <span style="color: red; font-size: 12px;"><?php echo htmlentities($isbnError); ?></span>
        <?php endif; ?>
    </div>

    <div class="form-group">
        <label>Quantity</label>
        <input type="text" name="quantity" value="<?php echo isset($_POST['quantity']) ? htmlentities($_POST['quantity']) : ''; ?>" required>
    </div>

    <div class="form-group">
        <label>Book Image</label>
        <input type="file" name="image" accept="image/*">
    </div>

    <div class="form-actions">
        <button type="submit" class="btn">Add Book</button>
    </div>
</form>

</div>

</body>
</html>
