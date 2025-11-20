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
            CONCAT_WS(' ', s.first_name, s.middle_name, s.last_name) AS full_name,
            s.profile_image,
            s.course
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

// Get statistics
$totalIssued = $conn->query("SELECT COUNT(*) as count FROM issued_books")->fetch_assoc()['count'];
$currentlyBorrowed = $conn->query("SELECT COUNT(*) as count FROM issued_books WHERE return_status = 0")->fetch_assoc()['count'];
$returned = $conn->query("SELECT COUNT(*) as count FROM issued_books WHERE return_status = 1")->fetch_assoc()['count'];
$overdue = $conn->query("SELECT COUNT(*) as count FROM issued_books WHERE return_status = 0 AND due_date < CURDATE()")->fetch_assoc()['count'];
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Issued Books</title>

  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="../css/styles.css">
  <link rel="stylesheet" href="../css/issued-books.css">
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/jquery.dataTables.min.css">
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>


</head>

<body>
  <?php include('includes/header.php'); ?>

  <div class="page-container">
    <div class="page-header">
      <h1><i class="fas fa-book-reader"></i> Issued Books</h1>
      <div class="header-actions">
        <div class="filter-group">
          <label for="statusFilter"><i class="fas fa-filter"></i> Filter:</label>
          <select id="statusFilter">
            <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All Records</option>
            <option value="not_returned" <?= $statusFilter === 'not_returned' ? 'selected' : '' ?>>Currently Borrowed
            </option>
            <option value="returned" <?= $statusFilter === 'returned' ? 'selected' : '' ?>>Returned</option>
            <option value="overdue" <?= $statusFilter === 'overdue' ? 'selected' : '' ?>>Overdue</option>
          </select>
        </div>
        <a href="issue-book.php" class="btn btn-primary">
          <i class="fas fa-plus-circle"></i> Issue Book
        </a>
      </div>
    </div>

    <!-- Stats Cards -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon total">
          <i class="fas fa-book"></i>
        </div>
        <div class="stat-details">
          <div class="stat-value"><?= $totalIssued ?></div>
          <div class="stat-label">Total Issued</div>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon borrowed">
          <i class="fas fa-book-open"></i>
        </div>
        <div class="stat-details">
          <div class="stat-value"><?= $currentlyBorrowed ?></div>
          <div class="stat-label">Currently Borrowed</div>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon returned">
          <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-details">
          <div class="stat-value"><?= $returned ?></div>
          <div class="stat-label">Returned</div>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon overdue">
          <i class="fas fa-exclamation-triangle"></i>
        </div>
        <div class="stat-details">
          <div class="stat-value"><?= $overdue ?></div>
          <div class="stat-label">Overdue</div>
        </div>
      </div>
    </div>

    <!-- Alerts -->
    <?php
    $alerts = ['error' => 'danger', 'msg' => 'success', 'updatemsg' => 'success', 'delmsg' => 'success'];
    foreach ($alerts as $key => $type) {
      if (!empty($_SESSION[$key])) {
        $icon = $type === 'danger' ? 'fa-exclamation-circle' : 'fa-check-circle';
        echo '<div class="alert alert-' . $type . '">';
        echo '<i class="fas ' . $icon . '"></i>';
        echo '<span>' . htmlentities($_SESSION[$key]) . '</span>';
        echo '</div>';
        $_SESSION[$key] = "";
      }
    }
    ?>

    <!-- Table -->
    <div class="table-container">
      <div class="table-responsive">
        <table id="issuedTable" class="display">
          <thead>
            <tr>
              <th>Student</th>
              <th>Book</th>
              <th>Issued Date</th>
              <th>Due Date</th>
              <th>Returned Date</th>
              <th>Status</th>
              <th>Fine</th>
              <th>Remarks</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php
            if (!empty($issuedBooks)) {
              foreach ($issuedBooks as $row) {
                $encryptedID = encrypt($row['issued_books_id']);
                $fine = is_numeric($row['fine']) ? (float) $row['fine'] : 0.0;

                $isReturned = $row['return_status'] == 1;
                $isOverdue = !$isReturned && $row['due_date'] < date('Y-m-d');

                $rowClass = $isOverdue ? 'overdue-row' : '';

                if ($isReturned) {
                  $statusText = 'Returned';
                  $statusClass = 'status-returned';
                } elseif ($isOverdue) {
                  $statusText = 'Overdue';
                  $statusClass = 'status-overdue';
                } else {
                  $statusText = 'Borrowed';
                  $statusClass = 'status-borrowed';
                }

                $issued = $row['issued_date'] ? date('M j, Y', strtotime($row['issued_date'])) : '—';
                $issuedTime = $row['issued_date'] ? date('g:i A', strtotime($row['issued_date'])) : '';
                $due = $row['due_date'] ? date('M j, Y', strtotime($row['due_date'])) : '—';
                $returned = $row['actual_return'] ? date('M j, Y', strtotime($row['actual_return'])) : '—';
                $returnedTime = $row['actual_return'] ? date('g:i A', strtotime($row['actual_return'])) : '';

                echo "<tr class='$rowClass'>";

                // Student Info with Avatar
                echo "<td>";
                echo "<div class='student-info'>";
                if (!empty($row['profile_image']) && file_exists("uploads/students/" . $row['profile_image'])) {
                  echo "<img src='uploads/students/" . htmlspecialchars($row['profile_image']) . "' alt='Profile' class='student-avatar'>";
                } else {
                  echo "<div class='student-avatar-placeholder'><i class='fas fa-user'></i></div>";
                }
                echo "<div class='student-details'>";
                echo "<div class='student-name'>" . htmlentities($row['full_name']) . "</div>";
                if (!empty($row['course'])) {
                  echo "<div class='student-course'>" . htmlentities($row['course']) . "</div>";
                }
                echo "</div></div></td>";

                // Book Info
                echo "<td>";
                echo "<div class='book-info'>";
                echo "<div class='book-title'>" . htmlentities($row['title'] ?? '') . "</div>";
                echo "<div class='book-isbn'><i class='fas fa-barcode'></i> " . htmlentities($row['isbn'] ?? '') . "</div>";
                echo "</div></td>";

                // Issued Date
                echo "<td>";
                echo "<div class='date-info'>";
                echo "<div class='date-value'>$issued</div>";
                if ($issuedTime)
                  echo "<div class='date-label'>$issuedTime</div>";
                echo "</div></td>";

                // Due Date
                echo "<td>";
                echo "<div class='date-info'>";
                echo "<div class='date-value'>$due</div>";
                echo "</div></td>";

                // Returned Date
                echo "<td>";
                echo "<div class='date-info'>";
                echo "<div class='date-value'>$returned</div>";
                if ($returnedTime)
                  echo "<div class='date-label'>$returnedTime</div>";
                echo "</div></td>";

                // Status
                echo "<td><span class='status-badge $statusClass'>$statusText</span></td>";

                // Fine
                $fineClass = $fine > 0 ? 'fine-amount' : 'fine-amount fine-zero';
                echo "<td><span class='$fineClass'>₱" . number_format($fine, 2) . "</span></td>";

                // Remarks
                echo "<td>" . htmlentities($row['remarks'] ?? '—') . "</td>";

                // Actions
                echo "<td style='white-space:nowrap;'>";
                if ($isReturned) {
                  echo "<a href='return-book.php?id=" . urlencode($encryptedID) . "' class='btn-action btn-view'>";
                  echo "<i class='fas fa-eye'></i> View</a>";
                } else {
                  echo "<a href='return-book.php?id=" . urlencode($encryptedID) . "' class='btn-action btn-return'>";
                  echo "<i class='fas fa-undo'></i> Return</a>";
                }
                echo "</td>";

                echo "</tr>";
              }
            }
            ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <script>
    $(document).ready(function () {
      $('#issuedTable').DataTable({
        paging: true,
        searching: true,
        ordering: true,
        info: true,
        lengthMenu: [10, 25, 50, 100],
        pageLength: 10,
        order: [[2, "desc"]], // Sort by issued date
        language: {
          search: "Search records:",
          paginate: {
            first: "First",
            last: "Last",
            next: "Next",
            previous: "Previous"
          },
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

    // Auto-dismiss alerts
    window.addEventListener('DOMContentLoaded', function () {
      const alerts = document.querySelectorAll('.alert');
      alerts.forEach(alert => {
        setTimeout(function () {
          alert.style.transition = 'opacity 0.5s ease';
          alert.style.opacity = '0';
          setTimeout(function () {
            alert.remove();
          }, 500);
        }, 5000);
      });
    });
  </script>
</body>

</html>