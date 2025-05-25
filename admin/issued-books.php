<?php
session_start();
error_reporting(E_ALL);

include '../connection/db.php';
include '../security/crypt.php';

if (strlen($_SESSION['alogin']) == 0) {
  header('location:../index.php');
  exit;
}

function getIssuedBooks($conn)
{
  $issuedBooks = [];
  $sql = "SELECT ib.*, b.title, b.isbn, s.first_name, s.middle_name, s.last_name
            FROM issued_books ib
            JOIN books b ON ib.book_id = b.book_id
            JOIN students s ON ib.student_id = s.student_id
            ORDER BY ib.issued_date DESC";
  $result = $conn->query($sql);

  if ($result) {
    while ($row = $result->fetch_assoc()) {
      $issuedBooks[] = $row;
    }
  }
  return $issuedBooks;
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Issued Books</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="../css/styles.css">
  <link rel="stylesheet" href="../css/tables.css">
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700&display=swap" rel="stylesheet">
  <style>
@media print {
  @page { size: landscape; margin: 20mm; }
  header, .print-btn, h2, .btn, .alert { display: none !important; }
  table, .container {
    width: 100vw !important;
    max-width: 100vw !important;
    margin: 0 !important;
    padding: 0 !important;
  }
  table {
    table-layout: auto !important;
    font-size: 13px !important;
    overflow-x: visible !important;
    display: table !important;
  }
  table th:last-child, table td:last-child { display: none !important; }
  body { background: #fff !important; color: #000 !important; box-shadow: none !important; margin: 0; padding: 0; text-align: center; }
  .table-responsive, [style*="overflow-x: auto"] { overflow-x: visible !important; }
}


  </style>
</head>

<body>
  <?php include('includes/header.php'); ?>

  <div class="container">
    <h2>Issued Books</h2>



    <div style="text-align: right; margin-bottom: 10px; ">
      <a onclick="window.print()" class="btn btn-secondary" style="padding: 8px 14px; background-color:rgb(11, 24, 44); border: none; border-radius: 5px; color: #fff; text-decoration: none; font-weight: bold;">
            <i class="fas fa-print"></i> Print as PDF
          </a> 
          <a href="issue-book.php" class="btn btn-primary" style="padding: 8px 14px; background-color: #28a745; border: none; border-radius: 5px; color: #fff; text-decoration: none; font-weight: bold;">
              <i class="fas fa-plus"></i> Issue Book
          </a>
         
      </div>

    <?php
    $alerts = ['error', 'msg', 'updatemsg', 'delmsg'];
    foreach ($alerts as $alert) {
      if (!empty($_SESSION[$alert])) {
        $type = ($alert == 'error') ? 'danger' : 'success';
        echo '<div class="alert alert-' . $type . '">';
        echo '<strong>' . ucfirst($type) . ':</strong> ' . htmlentities($_SESSION[$alert]);
        echo '</div>';
        $_SESSION[$alert] = "";
      }
    }
    ?>




    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Student</th>
          <th>Book Title</th>
          <th>ISBN</th>
          <th>Issued Date</th>
          <th>Due Date</th>
          <th>Returned Date</th>
          <th>Status</th>
          <th style="min-width:110px;">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $issuedBooks = getIssuedBooks($conn);
        $cnt = 1;

        if (count($issuedBooks) > 0) {
          foreach ($issuedBooks as $row) {
            $fullName = htmlentities($row['first_name'] . ' ' . $row['middle_name'] . ' ' . $row['last_name']);
            $encryptedID = encrypt($row['issued_books_id']);
            $status = $row['return_status'] == 1 ? 'Returned' : 'Not Returned';

            echo "<tr>
              <td>{$cnt}</td>
              <td>{$fullName}</td>
              <td>" . htmlentities($row['title'] ?? '') . "</td>
              <td>" . htmlentities($row['isbn'] ?? '') . "</td>
              <td>" . htmlentities($row['issued_date'] ?? '') . "</td>
              <td>" . htmlentities($row['due_date'] ?? '') . "</td>
              <td>" . htmlentities($row['actual_return'] ?? '') . "</td>
              <td style=\"color: " . ($status === 'Returned'?'green':'red') . ";\">" . htmlentities($status)."</td>
              <td style='text-align:left; min-width:110px;'>";

              if($row['return_status']!=1){
                echo "<a href=\"return-book.php?id=" . urlencode($encryptedID) . "\" class=\"btn btn-sm btn-return\" style=\"white-space: nowrap;\">
                      <i class=\"fas fa-undo\"></i> Return
                  </a>";
              } else{
                echo "<a href=\"return-book.php?id=" . urlencode($encryptedID) . "\" class=\"btn btn-sm btn-view\" style=\"white-space: nowrap;\">
                      <i class=\"fas fa-undo\"></i> View
                  </a>";
              }
                  
              echo "</td>
          </tr>";

            $cnt++;
          }
        } else {
          echo '<tr><td colspan="9" style="text-align:center;">No issued books found.</td></tr>';
        }
        ?>
      </tbody>
    </table>
  </div>
</body>

</html>