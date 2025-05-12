<?php
session_start();
include('../connection/db.php');


if (strlen($_SESSION['alogin']) == 0) {
  header('location:index.php');
  exit;
}

// Query Counts
$totalBooks = $conn->query("SELECT SUM(quantity) AS total FROM books")->fetch_assoc()['total'];
$availableBooks = $conn->query("SELECT COUNT(*) AS available FROM books WHERE quantity !=0")->fetch_assoc()['available'];
$issuedBooks = $conn->query("SELECT COUNT(*) AS issued FROM issued_books")->fetch_assoc()['issued'];
$totalStudents = $conn->query("SELECT COUNT(*) AS students FROM students")->fetch_assoc()['students'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Dashboard</title>

  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="../css/styles.css">
  <link rel="stylesheet" href="../css/dashboard.css">
  <style>
 
  </style>
</head>
<body>
  <?php include 'includes/header.php'; ?>

  <div class="dashboard-container">
    <div class="dashboard-header">
      <h2><strong>Admin Dashboard</strong></h2>
      <p>Welcome, <?php echo htmlentities($_SESSION['alogin']); ?>!</p>
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

      <div class="dashboard-card">
        <h3><?php echo $totalStudents; ?> <i class="fas fa-users dashboard-icon"></i></h3>
        <p>Registered Students</p>
      </div>
    </div>
  </div>
</body>
</html>
