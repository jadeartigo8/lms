<?php 

session_start();
error_reporting(E_ALL);
include('connection/db.php');
include 'includes/functions.php';


//checking if user ba ang login if dili, redirects sa index
if (strlen($_SESSION['login']) == 0) {
  header('location:index.php');
  exit();
}


// student id from session after login. used for querrying that needs student id as parameter
$studentID= $_SESSION['stdid'];


// ---- GET COUNTS -----

// 1. get total books
$totalBooks = $conn->query("SELECT SUM(quantity) AS total FROM books")->fetch_assoc()['total'];
// 2. get available books
$availableBooks = $conn->query("SELECT COUNT(*) AS available FROM books WHERE quantity !=0")->fetch_assoc()['available'];

// 3. count all issued books for student id
$issuedBooksQuerry = "select count(*) as issued_count from issued_books where student_id = ?";
$stmt = $conn->prepare($issuedBooksQuerry);
$stmt->bind_param('s',$studentID);
$stmt->execute();
$issuedBooksResult = $stmt->get_result();
$issuedBooks= $issuedBooksResult->fetch_assoc()['issued_count'];


?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>User Dashboard</title>

  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="css/styles.css">
  <link rel="stylesheet" href="css/dashboard.css">
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700&display=swap" rel="stylesheet">
</head>
<body>
<?php include('includes/header.php'); ?>

  <div class="dashboard-container">
    <div class="dashboard-header">
      <h1><strong>User Dashboard</strong></h1>
      <p>Welcome, <?php echo htmlentities($_SESSION['login']); ?>!</p>
    </div>

    <div class="cards-grid">
      <div class="dashboard-card">
        <h3><?php echo $totalBooks; ?> <i class="fas fa-book dashboard-icon"></i></h3>
        <p>Total Books</p>
      </div>

      <div class="dashboard-card">
        <h3><?php echo $availableBooks; ?> <i class="fas fa-check-circle dashboard-icon"></i></h3>
        <p>Books Available</p>
      </div>

      <div class="dashboard-card">
        <h3><?php echo $issuedBooks; ?> <i class="fas fa-book-reader dashboard-icon"></i></h3>
        <p>Books Issued</p>
      </div>

    </div>
  </div>
</body>
</html>