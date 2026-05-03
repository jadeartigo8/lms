<?php 

session_start();
error_reporting(E_ALL);
include('connection/db.php');

if (strlen($_SESSION['login']) == 0) {
  header('location:index.php');
  exit();
}

function getIssuedBooksByID($studentID, $conn, $filter = 'all'){
  $sql = "SELECT ib.*, b.title, b.isbn
          FROM issued_books ib 
          JOIN books b ON ib.book_id = b.book_id
          WHERE ib.student_id = ?";

  if ($filter === 'returned') {
    $sql .= " AND ib.return_status = 1";
  } elseif ($filter === 'not_returned') {
    $sql .= " AND ib.return_status = 0";
  } elseif ($filter === 'overdue') {
    $sql .= " AND ib.return_status = 0 AND ib.due_date < CURDATE()";
  } elseif ($filter === 'upcoming') {
    $sql .= " AND ib.return_status = 0 AND ib.due_date >= CURDATE() AND ib.due_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
  }

  $sql .= " ORDER BY ib.issued_date DESC";

  $stmt = $conn->prepare($sql);
  $stmt->bind_param('s', $studentID);
  $stmt->execute();

  $result = $stmt->get_result();
  $issuedBooks = [];

  if($result){
    while($row = $result->fetch_assoc()){
      $issuedBooks[] = $row;
    }
  }

  return $issuedBooks;
}

$allowed = ['all', 'returned', 'not_returned', 'overdue', 'upcoming'];
$statusFilter = $_GET['status'] ?? 'all';
$statusFilter = in_array($statusFilter, $allowed) ? $statusFilter : 'all';

$id = $_SESSION['stdid'];
$issuedBooks = getIssuedBooksByID($id, $conn, $statusFilter);

?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Issued Books</title>

  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="css/styles.css">
  <link rel="stylesheet" href="css/tables.css">
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/jquery.dataTables.min.css">

  <style>
    .tabs-container {
      margin: 20px 0;
      border-bottom: 2px solid #e0e0e0;
    }

    .tabs {
      display: flex;
      gap: 5px;
      flex-wrap: wrap;
    }

    .tab {
      padding: 12px 24px;
      background: #f5f5f5;
      border: none;
      border-radius: 8px 8px 0 0;
      cursor: pointer;
      font-size: 14px;
      font-weight: 500;
      color: #666;
      text-decoration: none;
      transition: all 0.3s ease;
      position: relative;
      bottom: -2px;
    }

    .tab:hover {
      background: #e8e8e8;
      color: #333;
    }

    .tab.active {
      background: #0b182c;
      color: #fff;
      border-bottom: 2px solid #0b182c;
    }

    .tab i {
      margin-right: 8px;
    }

    .tab-badge {
      display: inline-block;
      background: rgba(0, 0, 0, 0.1);
      padding: 2px 8px;
      border-radius: 12px;
      font-size: 12px;
      font-weight: bold;
      margin-left: 8px;
    }

    .tab.active .tab-badge {
      background: rgba(255, 255, 255, 0.25);
    }

    .overdue-row {
      background: #fff3cd !important;
    }

    .upcoming-row {
      background: #d1ecf1 !important;
    }

    /* MOBILE: grid tabs */
    @media (max-width: 768px) {
      .tabs-container {
        border-bottom: none;
        margin: 16px 0;
      }

      .tabs {
        display: grid;
        gap: 8px;
      }

      .tab {
        border-radius: 8px;
        bottom: 0;
        text-align: center;
        padding: 12px 8px;
        font-size: 12px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 4px;
      }

      .tab i {
        margin-right: 0;
        font-size: 18px;
      }

      .tab-badge {
        margin-left: 0;
        font-size: 11px;
      }
    }

    @media (max-width: 360px) {
      .tabs {
        grid-template-columns: 1fr;
      }
    }

    @media print {
      @page {
        size: landscape;
        margin: 15mm;
      }

      header,
      .tabs-container,
      .stats-cards,
      h1 {
        display: none !important;
      }

      table {
        font-size: 11px !important;
      }

      body {
        background: #fff !important;
        color: #000 !important;
      }
    }
  </style>
</head>
<body>
  <?php include('includes/header.php'); ?>

  <div class="container">
    <h1>My Issued Books</h1>

    <?php
    // Calculate statistics
    $allBooks = getIssuedBooksByID($id, $conn, 'all');
    $activeBooks = getIssuedBooksByID($id, $conn, 'not_returned');
    $overdueBooks = getIssuedBooksByID($id, $conn, 'overdue');
    $upcomingBooks = getIssuedBooksByID($id, $conn, 'upcoming');
    $returnedBooks = getIssuedBooksByID($id, $conn, 'returned');

    // Format badge numbers
    function formatBadge($count) {
      return $count > 99 ? '99+' : $count;
    }
    ?>

    <!-- Tabs -->
    <div class="tabs-container">
      <div class="tabs">
        <a href="?status=all" class="tab <?= $statusFilter === 'all' ? 'active' : '' ?>">
          <i class="fas fa-book"></i>
          All Books
          <span class="tab-badge"><?= formatBadge(count($allBooks)) ?></span>
        </a>
        <a href="?status=not_returned" class="tab <?= $statusFilter === 'not_returned' ? 'active' : '' ?>">
          <i class="fas fa-bookmark"></i>
          Currently Borrowed
          <span class="tab-badge"><?= formatBadge(count($activeBooks)) ?></span>
        </a>
        <a href="?status=upcoming" class="tab <?= $statusFilter === 'upcoming' ? 'active' : '' ?>">
          <i class="fas fa-clock"></i>
          Due Soon
          <span class="tab-badge"><?= formatBadge(count($upcomingBooks)) ?></span>
        </a>
        <a href="?status=overdue" class="tab <?= $statusFilter === 'overdue' ? 'active' : '' ?>">
          <i class="fas fa-exclamation-triangle"></i>
          Overdue
          <span class="tab-badge"><?= formatBadge(count($overdueBooks)) ?></span>
        </a>
        <a href="?status=returned" class="tab <?= $statusFilter === 'returned' ? 'active' : '' ?>">
          <i class="fas fa-check-circle"></i>
          Returned
          <span class="tab-badge"><?= formatBadge(count($returnedBooks)) ?></span>
        </a>
      </div>
    </div>

    <!-- Table -->
    <table id="issuedTable" class="display" style="width:100%">
      <thead>
        <tr>
          <th>#</th>
          <th>Book Title</th>
          <th>ISBN</th>
          <th>Issued Date</th>
          <th>Due Date</th>
          <th>Status</th>
          <th>Fine</th>
          <th>Actual Return</th>
        </tr>
      </thead>
      <tbody>
        <?php
          $cnt = 1;

          if(count($issuedBooks) > 0){
            foreach($issuedBooks as $row){
              $isReturned = $row['return_status'] == 1;
              $statusText = $isReturned ? 'Returned' : 'Not Returned';
              $statusColor = $isReturned ? 'green' : 'red';

              $isOverdue = !$isReturned && $row['due_date'] < date('Y-m-d');
              $isUpcoming = !$isReturned && $row['due_date'] >= date('Y-m-d') && $row['due_date'] <= date('Y-m-d', strtotime('+7 days'));
              
              $rowClass = '';
              if ($isOverdue) {
                $rowClass = 'overdue-row';
              } elseif ($isUpcoming) {
                $rowClass = 'upcoming-row';
              }

              $issued   = $row['issued_date']    ? date('M j, Y g:i A', strtotime($row['issued_date']))    : '—';
              $due      = $row['due_date']        ? date('M j, Y',       strtotime($row['due_date']))        : '—';
              $returned = $row['actual_return']   ? date('M j, Y g:i A', strtotime($row['actual_return']))  : '—';
              
              $fine = is_numeric($row['fine']) ? (float) $row['fine'] : 0.0;

              echo "<tr class='{$rowClass}'>
                      <td>{$cnt}</td>
                      <td>" . htmlentities($row['title'] ?? '') . "</td>
                      <td>" . htmlentities($row['isbn']  ?? '') . "</td>
                      <td>{$issued}</td>
                      <td>{$due}</td>
                      <td style='color:{$statusColor};font-weight:bold;'>{$statusText}</td>
                      <td>₱" . number_format($fine, 2) . "</td>
                      <td>{$returned}</td>
                    </tr>";

              $cnt++;
            }
          }
        ?>
      </tbody>
    </table>
  </div>

  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>

  <script>
    $(document).ready(function () {
      $('#issuedTable').DataTable({
        paging: true,
        searching: true,
        ordering: true,
        info: true,
        lengthMenu: [10, 25, 50, 100],
        pageLength: 10,
        language: {
          search: "Search books:",
          paginate: { 
            first: "First", 
            last: "Last", 
            next: "Next", 
            previous: "Previous" 
          },
          emptyTable: "No books found for the selected filter."
        }
      });
    });
  </script>
</body>
</html>