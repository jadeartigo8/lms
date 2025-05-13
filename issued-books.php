<?php 

session_start();
error_reporting(E_ALL);
include('connection/db.php');
include 'includes/functions.php';

if (strlen($_SESSION['login']) == 0) {
  header('location:index.php');
  exit();
}



?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Issued Books</title>

  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="css/styles.css">
  <link rel="stylesheet" href="css/tables.css">
</head>
<body>
  <?php include('includes/header.php'); ?>


  <div class="container">
    <h2>Issued Books</h2>

  
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Book Title</th>
          <th>Issued Date</th>
          <th>Due Date</th>
          <th>Fine</th>
          <th>Status</th>
          <th>Actual Return</th>

        </tr>
      </thead>
      <tbody>
        <?php
          $id = $_SESSION['stdid'];
          var_dump($id);
          $issuedBooks = getIssuedBooksByID($id,$conn);
          var_dump($issuedBooks);
          $cnt = 1;

          if(count($issuedBooks)>0){
            foreach($issuedBooks as $row){
              $status = $row['return_status'] == 1 ? 'Returned' : 'Not Returned';

              echo "<tr>
                      <td>{$cnt}</td>
                      <td>". htmlentities($row['title']?? '') ."</td>
                      <td>". htmlentities($row['issued_date']?? '') ."</td>
                      <td>". htmlentities($row['due_date']?? '') ."</td>
                      <td>". htmlentities($row['fine']?? '') ."</td>
                      <td>{$status}</td>
                      <td>". htmlentities($row['actual_return']?? '') ."</td>
                    </tr>";

                    $cnt++;
            }
          } else{
            echo '<tr><td colspan="7" style="text-align:center;">No issued books found.</td></tr>';
          }

        ?>
      </tbody>
    </table>
  </div>

</body>
</html>


