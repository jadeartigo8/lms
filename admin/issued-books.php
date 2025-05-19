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
  <style>
   
  </style>
</head>

<body>
  <?php include('includes/header.php'); ?>

  <div class="container">
    <h2>Issued Books</h2>

    <div style="text-align: right; margin-bottom: 10px;">
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
          <th>Actions</th>
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
              <td style='text-align:left;'>";

              if($row['return_status']!=1){
                echo "<a href=\"return-book.php?id=" . urlencode($encryptedID) . "\" class=\"btn btn-sm btn-return\" >
                      <i class=\"fas fa-undo\"></i> Return
                  </a>";
              } else{
                echo "<a href=\"return-book.php?id=" . urlencode($encryptedID) . "\" class=\"btn btn-sm btn-view\" >
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