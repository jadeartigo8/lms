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

$issuedBook = null;
$fine = 0;
date_default_timezone_set('Asia/Manila');

if (isset($_GET['id'])) {
  $decryptedID = decrypt($_GET['id']);
  if (!$decryptedID) {
    $_SESSION['error'] = "Invalid issued book ID.";
    header("location: issued-books.php");
    exit;
  }

  // Fetch the issued book record
  $stmt = $conn->prepare("SELECT ib.*, b.title, b.image, b.isbn, s.first_name, s.middle_name, s.last_name 
                            FROM issued_books ib
                            JOIN books b ON ib.book_id = b.book_id
                            JOIN students s ON ib.student_id = s.student_id
                            WHERE ib.issued_books_id = ?");
  $stmt->bind_param("i", $decryptedID);
  $stmt->execute();
  $result = $stmt->get_result();

  if ($result && $result->num_rows > 0) {
    $issuedBook = $result->fetch_assoc();
    $returnDate = strtotime($issuedBook['return_date']);
    $now = time();

    if ($issuedBook['return_status'] == 0 && $now > $returnDate) {
      $daysLate = floor(($now - $returnDate) / (60 * 60 * 24));
      $fine = $daysLate * 10;
    }

  } else {
    $_SESSION['error'] = "Issued book not found.";
    header("location: issued-books.php");
    exit;
  }
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $decryptedID = decrypt($_POST['issued_books_id']);
  $fine = $_POST['fine'] ?? 0;

  $stmt = $conn->prepare("UPDATE issued_books 
                            SET return_status = 1, return_date = NOW(), fine = ? 
                            WHERE issued_books_id = ?");
  $stmt->bind_param("di", $fine, $decryptedID);

  if ($stmt->execute()) {
    $_SESSION['msg'] = "Book marked as returned successfully.";
    $logger->write("Book returned. ID: $decryptedID");
  } else {
    $_SESSION['error'] = "Failed to update record.";
  }

  header("Location: issued-books.php");
  exit;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Return Book</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="../css/styles.css">
  <link rel="stylesheet" href="../css/return-books.css">
</head>

<body>

  <?php include('includes/header.php'); ?>

  <div class="return-form-container">
    <h2>Return Book</h2>

    <?php if ($issuedBook): ?>
      <form method="POST">
        <input type="hidden" name="issued_books_id" value="<?php echo htmlentities($_GET['id']); ?>">

        <?php if (!empty($issuedBook['image'])): ?>
          <img src="uploads/<?php echo htmlentities($issuedBook['image']); ?>" class="book-image" alt="Book Image">
        <?php else: ?>
          <p>No Image Available</p>
        <?php endif; ?>

        <div class="form-group">
          <label>Book Title</label>
          <input type="text" value="<?php echo htmlentities($issuedBook['title']); ?>" readonly>
        </div>

        <div class="form-group">
          <label>Student</label>
          <input type="text"
            value="<?php echo htmlentities($issuedBook['first_name'] . ' ' . $issuedBook['middle_name'] . ' ' . $issuedBook['last_name']); ?>"
            readonly>
        </div>

        

        <div class="form-group">
          <label>ISBN</label>
          <input type="text" value="<?php echo htmlentities($issuedBook['isbn']); ?>" readonly>
        </div>

        <div class="form-group">
          <label>Issued Date</label>
          <input type="text" value="<?php echo htmlentities($issuedBook['issued_date']); ?>" readonly>
        </div>

        <div class="form-group">
          <label>Return Date</label>
          <input type="text" value="<?php echo htmlentities($issuedBook['return_date']); ?>" readonly>
        </div>


        <div class="form-group">
          <label>Fine (₱)</label>
          <input type="number" name="fine" value="<?php echo htmlentities($fine); ?>" min="0" step="1" required>
        </div>


        <div class="form-actions">
          <?php if ($issuedBook['return_status'] == 0): ?>
            <button type="submit" class="btn-submit">
              <i class="fas fa-check-circle"></i> Confirm Return
            </button>
          <?php else: ?>
            <div style="padding: 10px; background-color: #d4edda; color: #155724; border-radius: 5px; font-weight: bold;">
              <i class="fas fa-check-circle"></i> Returned on
              <?php echo date('F j, Y \a\t g:i A', strtotime($issuedBook['return_date'])); ?>
            </div>
          <?php endif; ?>
      </form>
    <?php endif; ?>
  </div>

</body>

</html>