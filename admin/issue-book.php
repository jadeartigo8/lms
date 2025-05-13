<?php
session_start();
date_default_timezone_set('Asia/Manila');

include('../connection/db.php'); 
include('includes/header.php'); 
include 'includes/logger.php';


$logger = new Logger();

if (strlen($_SESSION['alogin']) == 0) {
    header('location:index.php');
    exit;
}

$error = "";
$success = "";


$bookList = [];
$bookQuery = $conn->query("SELECT book_id, title FROM books WHERE quantity > 0");
while ($row = $bookQuery->fetch_assoc()) {
    $bookList[] = $row;
}


$studentList = [];
$studentQuery = $conn->query("SELECT student_id, CONCAT(first_name, ' ', middle_name, ' ', last_name) AS full_name FROM students");
while ($row = $studentQuery->fetch_assoc()) {
    $studentList[] = $row;
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $book_title = $_POST['book_title'] ?? '';
    $student_name = $_POST['student_name'] ?? '';
    $issued_date_raw = $_POST['issued_date'] ?? date('Y-m-d H:i:s');
    $return_date_raw = $_POST['return_date'] ?? date('Y-m-d H:i:s', strtotime('+7 days'));
    $remarks = $_POST['remarks'] ?? '';

   
    $issued_date = date('Y-m-d H:i:s', strtotime($issued_date_raw));
    $return_date = date('Y-m-d H:i:s', strtotime($return_date_raw));

    
    if (strtotime($return_date) <= strtotime($issued_date)) {
        $error = "Return date must be later than issued date.";
    } else {
        
        $bookStmt = $conn->prepare("SELECT book_id, quantity FROM books WHERE title = ?");
        $bookStmt->bind_param("s", $book_title);
        $bookStmt->execute();
        $bookStmt->bind_result($book_id, $quantity);
        $bookStmt->fetch();
        $bookStmt->close();

        $studentStmt = $conn->prepare("SELECT student_id FROM students WHERE CONCAT(first_name, ' ', middle_name, ' ', last_name) = ?");
        $studentStmt->bind_param("s", $student_name);
        $studentStmt->execute();
        $studentStmt->bind_result($student_id);
        $studentStmt->fetch();
        $studentStmt->close();

        if (!$book_id || !$student_id) {
            $error = "Invalid book title or student name.";
        } elseif ($quantity <= 0) {
            $error = "Book is out of stock.";
        } else {
            $stmt = $conn->prepare("INSERT INTO issued_books (book_id, student_id, issued_date, due_date, return_status, fine, remarks)
                                    VALUES (?, ?, ?, ?, 0, '', ?)");
            $stmt->bind_param("issss", $book_id, $student_id, $issued_date, $return_date, $remarks);

            if ($stmt->execute()) {
                $conn->query("UPDATE books SET quantity = quantity - 1 WHERE book_id = $book_id");
                $success = "Book issued successfully!";
                $logger->write("Issued book: $book_title");
                $_POST = []; 
            } else {
                $error = "Error issuing book.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Issue Book</title>
    <link rel="stylesheet" href="../css/styles.css">
</head>
<body>

<div class="signup-container">
    <h3>Issue Book</h3>

    <?php if ($error): ?>
        <div class="custom-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="custom-error" style="background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb;">
            <?php echo htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label>Book Title</label>
            <input list="book_titles" name="book_title" value="<?php echo $_POST['book_title'] ?? ''; ?>" required>
            <datalist id="book_titles">
                <?php foreach ($bookList as $book): ?>
                    <option value="<?php echo htmlspecialchars($book['title']); ?>">
                <?php endforeach; ?>
            </datalist>
        </div>

        <div class="form-group">
            <label>Student Name</label>
            <input list="student_names" name="student_name" value="<?php echo $_POST['student_name'] ?? ''; ?>" required>
            <datalist id="student_names">
                <?php foreach ($studentList as $student): ?>
                    <option value="<?php echo htmlspecialchars($student['full_name']); ?>">
                <?php endforeach; ?>
            </datalist>
        </div>

        <div class="form-group">
            <label>Issued Date</label>
            <input type="datetime-local" name="issued_date" value="<?php echo $_POST['issued_date'] ?? date('Y-m-d\TH:i'); ?>" required>
        </div>

        <div class="form-group">
            <label>Return Date</label>
            <input type="datetime-local" name="return_date" value="<?php echo $_POST['return_date'] ?? date('Y-m-d\TH:i', strtotime('+7 days')); ?>" required>
        </div>

        <div class="form-group">
            <label>Remarks</label>
            <input type="text" name="remarks" value="<?php echo $_POST['remarks'] ?? ''; ?>">
        </div>

        <div class="form-actions">
            <button type="submit" class="btn">Issue Book</button>
        </div>
    </form>
</div>

</body>
</html>
