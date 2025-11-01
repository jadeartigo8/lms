<?php
session_start();
error_reporting(E_ALL);

include '../connection/db.php';
include '../security/crypt.php';

if (strlen($_SESSION['alogin']) == 0) {
  header('location:../index.php');
  exit;
}

function getIssuedBooks($conn, $filter = 'all')
{
  $sql = "
        SELECT 
            ib.*, 
            b.title, 
            b.isbn, 
            s.first_name, 
            s.middle_name, 
            s.last_name,
            CONCAT_WS(' ', s.first_name, s.middle_name, s.last_name) AS full_name
        FROM issued_books ib
        JOIN books    b ON ib.book_id   = b.book_id
        JOIN students s ON ib.student_id = s.student_id
        WHERE 1=1
    ";

  if ($filter === 'returned') {
    $sql .= " AND ib.return_status = 1";
  } elseif ($filter === 'not_returned') {
    $sql .= " AND ib.return_status = 0";
  } elseif ($filter === 'overdue') {
    $sql .= " AND ib.return_status = 0 AND ib.due_date < CURDATE()";
  }

  $sql .= " ORDER BY ib.issued_date DESC";

  $stmt = $conn->prepare($sql);
  $stmt->execute();
  $result = $stmt->get_result();

  $books = [];
  while ($row = $result->fetch_assoc()) {
    $books[] = $row;
  }
  return $books;
}

$allowed = ['all', 'returned', 'not_returned', 'overdue'];
$statusFilter = $_GET['status'] ?? 'all';
$statusFilter = in_array($statusFilter, $allowed) ? $statusFilter : 'all';

$issuedBooks = getIssuedBooks($conn, $statusFilter);
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
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/jquery.dataTables.min.css">

  <style>
    @media print {
      @page {
        size: landscape;
        margin: 15mm;
      }

      header,
      .print-btn,
      h2,
      .btn,
      .alert,
      #filterRow {
        display: none !important;
      }

      table,
      .container {
        width: 100vw !important;
        max-width: 100vw !important;
        margin: 0;
        padding: 0;
      }

      table {
        table-layout: auto !important;
        font-size: 12px !important;
      }

      table th:last-child,
      table td:last-child {
        display: none !important;
      }

      body {
        background: #fff !important;
        color: #000 !important;
      }
    }

    .overdue-row {
      background: #fff3cd !important;
    }

    #filterRow {
      margin: 15px 0;
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 10px;
    }
  </style>
</head>

<body>
  <?php include('includes/header.php'); ?>

  <div class="container">
    <h2>Issued Books</h2>

    <div id="filterRow">
      <div>
        <label for="statusFilter"><strong>Filter:</strong></label>
        <select id="statusFilter" class="form-control" style="display:inline-block;width:auto;margin-left:8px;">
          <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All</option>
          <option value="returned" <?= $statusFilter === 'returned' ? 'selected' : '' ?>>Returned</option>
          <option value="not_returned" <?= $statusFilter === 'not_returned' ? 'selected' : '' ?>>Not Returned</option>
          <option value="overdue" <?= $statusFilter === 'overdue' ? 'selected' : '' ?>>Overdue</option>
        </select>
      </div>

      <div>
        <a onclick="window.print()" class="btn btn-secondary print-btn"
          style="padding:8px 14px;background:#0b182c;color:#fff;border-radius:5px;text-decoration:none;">
          Print as PDF
        </a>
        <a href="issue-book.php" class="btn btn-primary">
          Issue Book
        </a>
      </div>
    </div>

    <!-- ALERTS -->
    <?php
    $alerts = ['error', 'msg', 'updatemsg', 'delmsg'];
    foreach ($alerts as $a) {
      if (!empty($_SESSION[$a])) {
        $type = $a === 'error' ? 'danger' : 'success';
        echo "<div class='alert alert-$type'><strong>" . ucfirst($type) . ":</strong> " . htmlentities($_SESSION[$a]) . "</div>";
        $_SESSION[$a] = '';
      }
    }
    ?>

    <!-- TABLE -->
    <table id="issuedTable" class="display" style="width:100%">
      <thead>
        <tr>
          <th>#</th>
          <th>Student</th>
          <th>Book Title</th>
          <th>ISBN</th>
          <th>Issued</th>
          <th>Due</th>
          <th>Returned</th>
          <th>Status</th>
          <th>Fine</th>
          <th>Remarks</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($issuedBooks)): ?>
          <!-- 11 EMPTY CELLS → MATCHES THEAD COLUMN COUNT -->
          <tr>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
          </tr>
        <?php else:
          $cnt = 1;
          foreach ($issuedBooks as $row):
            $encryptedID = encrypt($row['issued_books_id']);
            $fine = is_numeric($row['fine']) ? (float) $row['fine'] : 0.0;

            $isReturned = $row['return_status'] == 1;
            $statusText = $isReturned ? 'Returned' : 'Not Returned';
            $statusColor = $isReturned ? 'green' : 'red';

            $isOverdue = !$isReturned && $row['due_date'] < date('Y-m-d');
            $rowClass = $isOverdue ? 'class="overdue-row"' : '';

            $issued = $row['issued_date'] ? date('M j, Y g:i A', strtotime($row['issued_date'])) : '—';
            $due = $row['due_date'] ? date('M j, Y', strtotime($row['due_date'])) : '—';
            $returned = $row['actual_return'] ? date('M j, Y g:i A', strtotime($row['actual_return'])) : '—';

            echo "<tr $rowClass>
                        <td>{$cnt}</td>
                        <td>" . htmlentities($row['full_name']) . "</td>
                        <td>" . htmlentities($row['title'] ?? '') . "</td>
                        <td>" . htmlentities($row['isbn'] ?? '') . "</td>
                        <td>{$issued}</td>
                        <td>{$due}</td>
                        <td>{$returned}</td>
                        <td style='color:{$statusColor};font-weight:bold;'>{$statusText}</td>
                        <td>₱" . number_format($fine, 2) . "</td>
                        <td>" . htmlentities($row['remarks'] ?? '') . "</td>
                        <td style='white-space:nowrap;'>
                            " . ($isReturned
              ? "<a href='return-book.php?id=" . urlencode($encryptedID) . "' class='btn btn-sm btn-view'>View</a>"
              : "<a href='return-book.php?id=" . urlencode($encryptedID) . "' class='btn btn-sm btn-return'>Return</a>"
            ) . "
                        </td>
                    </tr>";
            $cnt++;
          endforeach;
        endif; ?>
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
          search: "Search records:",
          paginate: { first: "First", last: "Last", next: "Next", previous: "Previous" },
          emptyTable: "No issued books found for the selected filter."
        }
      });

      $('#statusFilter').on('change', function () {
        const val = $(this).val();
        const url = new URL(window.location);
        url.searchParams.set('status', val);
        window.location = url.toString();
      });
    });
  </script>
</body>

</html>