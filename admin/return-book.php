<?php
session_start();
error_reporting(E_ALL);

include '../connection/db.php';
include '../security/crypt.php';
include 'includes/logger.php';
date_default_timezone_set('Asia/Manila');

$logger = new Logger();

if (strlen($_SESSION['alogin']) == 0) {
    header('location:../index.php');
    exit;
}

$issuedBook = null;
$fine = 0;
$remarks = 'On time';
$daysLateText = "";
$isOverdue = false;
$earlyReturn = false;

if (isset($_GET['id'])) {
    $decryptedID = decrypt($_GET['id']);
    if (!$decryptedID) {
        $_SESSION['error'] = "Invalid issued book ID.";
        header("location: issued-books.php");
        exit;
    }

    $stmt = $conn->prepare("
        SELECT ib.*, b.title, b.image, b.isbn, s.first_name, s.middle_name, s.last_name 
        FROM issued_books ib
        JOIN books b ON ib.book_id = b.book_id
        JOIN students s ON ib.student_id = s.student_id
        WHERE ib.issued_books_id = ?
    ");
    $stmt->bind_param("i", $decryptedID);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $issuedBook = $result->fetch_assoc();

        $dueDate = strtotime($issuedBook['due_date'] . ' 23:59:59'); // End of due day
          $now = time();

          if ($issuedBook['return_status'] == 0) {
              if ($now < $dueDate) {
                  $fine = 0;
                  $remarks = "Returned early";
                  $earlyReturn = true;
              } elseif ($now > $dueDate) {
                  $daysLate = floor(($now - $dueDate) / (60 * 60 * 24));
                  $fine = $daysLate * 10;
                  $remarks = "Overdue by $daysLate day" . ($daysLate > 1 ? 's' : '');
                  $daysLateText = "$daysLate day" . ($daysLate > 1 ? 's' : '') . " overdue";
                  $isOverdue = true;
              } else {
                  $fine = 0;
                  $remarks = "Returned on due date";
              }
          } else {
              $fine = $issuedBook['fine'] ?? 0;
              $remarks = $issuedBook['remarks'] ?? 'On time';
          }
    } else {
        $_SESSION['error'] = "Issued book not found.";
        header("location: issued-books.php");
        exit;
    }
    $stmt->close();
}

// Handle return
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $decryptedID = decrypt($_POST['issued_books_id']);
    $fine = (float)($_POST['fine'] ?? 0);
    $remarksText = trim($_POST['remarks'] ?? 'On time');

    $stmt = $conn->prepare("
        UPDATE issued_books 
        SET return_status = 1, actual_return = NOW(), fine = ?, remarks = ?
        WHERE issued_books_id = ?
    ");
    $stmt->bind_param("dsi", $fine, $remarksText, $decryptedID);

    if ($stmt->execute()) {
        $bookStmt = $conn->prepare("UPDATE books SET quantity = quantity + 1 WHERE book_id = ?");
        $bookStmt->bind_param("i", $issuedBook['book_id']);
        $bookStmt->execute();
        $bookStmt->close();

        $_SESSION['msg'] = "Book returned successfully.";
        $logger->write("Book returned. Issued ID: $decryptedID | Fine: ₱$fine");
    } else {
        $_SESSION['error'] = "Failed to update return record.";
    }
    $stmt->close();
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
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="../css/return-book.css">
</head>
<body>

<?php include('includes/header.php'); ?>

<div class="return-form-container">
    <h2><i class="fas fa-book-open"></i> Return Book</h2>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert error"><i class="fas fa-exclamation-triangle"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <?php if ($issuedBook): ?>
        <form method="POST" id="returnForm">
            <input type="hidden" name="issued_books_id" value="<?php echo htmlentities($_GET['id']); ?>">
            <input type="hidden" id="dueDate" value="<?php echo $issuedBook['due_date']; ?>">
            <input type="hidden" id="returnStatus" value="<?php echo $issuedBook['return_status']; ?>">

            <!-- Book Cover -->
            <div class="text-center">
                <?php
                $imagePath = $issuedBook['image'];
                $isUrl = filter_var($imagePath, FILTER_VALIDATE_URL);
                ?>
                <?php if ($imagePath && $isUrl): ?>
                    <img src="<?php echo htmlspecialchars($imagePath); ?>" class="book-image" alt="Book Cover">
                <?php elseif ($imagePath): ?>
                    <img src="uploads/<?php echo htmlspecialchars($imagePath); ?>" class="book-image" alt="Book Cover">
                <?php else: ?>
                    <div class="no-image"><i class="fas fa-book fa-2x"></i><br>No Image</div>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label><i class="fas fa-book"></i> Book Title</label>
                <input type="text" value="<?php echo htmlspecialchars($issuedBook['title']); ?>" readonly>
            </div>

            <div class="form-group">
                <label><i class="fas fa-user"></i> Student</label>
                <input type="text" value="<?php echo htmlspecialchars(trim($issuedBook['first_name'] . ' ' . $issuedBook['middle_name'] . ' ' . $issuedBook['last_name'])); ?>" readonly>
            </div>

            <div class="form-group">
                <label><i class="fas fa-barcode"></i> ISBN</label>
                <input type="text" value="<?php echo htmlspecialchars($issuedBook['isbn']); ?>" readonly>
            </div>

            <div class="form-group">
                <label><i class="fas fa-calendar-check"></i> Issued Date</label>
                <input type="text" value="<?php echo date('F j, Y \a\t g:i A', strtotime($issuedBook['issued_date'])); ?>" readonly>
            </div>

            <div class="form-group">
                <label><i class="fas fa-calendar-times"></i> Due Date</label>
                <input type="text" value="<?php echo date('F j, Y', strtotime($issuedBook['due_date'])); ?>" readonly>
            </div>

            <!-- Dynamic Notice -->
            <div id="fineNotice"></div>

            <div class="form-group">
                <label><i class="fas fa-coins"></i> Fine (₱)</label>
                <input type="number" name="fine" id="fineInput" value="<?php echo $fine; ?>" min="0" step="1" required <?php echo $issuedBook['return_status'] == 1 ? 'readonly' : ''; ?>>
            </div>

            <div class="form-group">
                <label><i class="fas fa-comment"></i> Remarks</label>
                <input type="text" id="remarksDisplay" value="<?php echo htmlspecialchars($remarks); ?>" readonly style="font-weight: bold;">
                <input type="hidden" name="remarks" id="remarksInput" value="<?php echo htmlspecialchars($remarks); ?>">
            </div>

            <div class="form-actions">
                <?php if ($issuedBook['return_status'] == 0): ?>
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-check-circle"></i> Confirm Return
                    </button>
                <?php else: ?>
                    <div class="already-returned">
                        <i class="fas fa-check-double"></i> Already Returned on
                        <?php echo date('F j, Y \a\t g:i A', strtotime($issuedBook['actual_return'])); ?>
                    </div>
                <?php endif; ?>
            </div>
        </form>
    <?php else: ?>
        <p>No book selected for return.</p>
    <?php endif; ?>
</div>

<script src="../js/return-book.js"></script>
</body>
</html>