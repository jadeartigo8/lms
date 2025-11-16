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
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/jquery.dataTables.min.css">
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>

  <style>
    :root {
      --navy: #000435;
      --gold: #ffde59;
    }

    body {
      background: #f8f9fa;
      font-family: 'Montserrat', sans-serif;
    }

    .page-container {
      max-width: 1600px;
      margin: 2rem auto;
      padding: 0 3rem;
    }

    /* Stats Cards */
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 1.5rem;
      margin-bottom: 2rem;
    }

    .stat-card {
      background: white;
      padding: 1.5rem;
      border-radius: 12px;
      box-shadow: 0 4px 15px rgba(0, 0, 0, .1);
      display: flex;
      align-items: center;
      gap: 1.5rem;
      transition: transform 0.3s ease, box-shadow 0.3s ease;
    }

    .stat-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 8px 25px rgba(0, 0, 0, .15);
    }

    .stat-icon {
      width: 70px;
      height: 70px;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 2rem;
      color: white;
    }

    .stat-icon.total {
      background: linear-gradient(135deg, var(--navy), #001a52);
    }

    .stat-icon.borrowed {
      background: linear-gradient(135deg, #ffc107, #ff9800);
    }

    .stat-icon.returned {
      background: linear-gradient(135deg, #28a745, #20c997);
    }

    .stat-icon.overdue {
      background: linear-gradient(135deg, #dc3545, #e74c3c);
    }

    .stat-details {
      flex: 1;
    }

    .stat-value {
      font-size: 2rem;
      font-weight: 700;
      color: var(--navy);
      margin-bottom: 0.25rem;
    }

    .stat-label {
      color: #666;
      font-size: 0.9rem;
      font-weight: 500;
    }

    /* Page Header */
    .page-header {
      background: linear-gradient(135deg, var(--navy), #001a52);
      color: white;
      padding: 2rem;
      border-radius: 12px;
      margin-bottom: 2rem;
      box-shadow: 0 8px 25px rgba(0, 0, 0, .2);
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 1rem;
    }

    .page-header h1 {
      margin: 0;
      font-size: 2rem;
      color: white;
    }

    .header-actions {
      display: flex;
      gap: 1rem;
      flex-wrap: wrap;
      align-items: center;
    }

    .filter-group {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      background: rgba(255, 255, 255, 0.1);
      padding: 0.5rem 1rem;
      border-radius: 8px;
    }

    .filter-group label {
      color: white;
      font-weight: 600;
      font-size: 0.9rem;
    }

    .filter-group select {
      border: 2px solid rgba(255, 255, 255, 0.3);
      border-radius: 6px;
      padding: 0.5rem 1rem;
      background: white;
      color: var(--navy);
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s ease;
    }

    .filter-group select:hover {
      border-color: var(--gold);
    }

    .btn {
      padding: 0.75rem 1.5rem;
      border-radius: 8px;
      font-weight: 600;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      transition: all 0.3s ease;
      border: none;
      cursor: pointer;
    }

    .btn-primary {
      background: var(--gold);
      color: var(--navy);
    }

    .btn-primary:hover {
      background: #ffd940;
      transform: translateY(-2px);
      box-shadow: 0 4px 15px rgba(255, 222, 89, .4);
    }

    .btn-secondary {
      background: rgba(255, 255, 255, 0.2);
      color: white;
      border: 2px solid rgba(255, 255, 255, 0.3);
    }

    .btn-secondary:hover {
      background: rgba(255, 255, 255, 0.3);
      border-color: var(--gold);
    }

    /* Alerts */
    .alert {
      padding: 1rem 1.5rem;
      border-radius: 8px;
      margin-bottom: 1.5rem;
      display: flex;
      align-items: center;
      gap: 0.75rem;
      animation: slideDown 0.3s ease;
    }

    @keyframes slideDown {
      from {
        opacity: 0;
        transform: translateY(-10px);
      }

      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .alert-success {
      background: #d1e7dd;
      color: #0f5132;
      border-left: 4px solid #198754;
    }

    .alert-danger {
      background: #f8d7da;
      color: #842029;
      border-left: 4px solid #dc3545;
    }

    /* Table Container */
    .table-container {
      background: white;
      border-radius: 12px;
      box-shadow: 0 4px 15px rgba(0, 0, 0, .1);
      overflow: hidden;
    }

    table.dataTable {
      width: 100% !important;
      border-collapse: collapse;
    }

    table.dataTable thead th {
      background: var(--navy);
      color: white;
      padding: 1rem;
      font-weight: 600;
      text-align: left;
      border: none;
    }

    table.dataTable tbody td {
      padding: 1rem;
      border-bottom: 1px solid #e0e0e0;
    }

    table.dataTable tbody tr:hover {
      background: #f8f9fa;
    }

    table.dataTable tbody tr.overdue-row {
      background: #fff3cd !important;
    }

    table.dataTable tbody tr.overdue-row:hover {
      background: #ffe69c !important;
    }

    /* Student Info Cell */
    .student-info {
      display: flex;
      align-items: center;
      gap: 1rem;
    }

    .student-avatar {
      width: 50px;
      height: 50px;
      border-radius: 50%;
      object-fit: cover;
      border: 2px solid var(--gold);
    }

    .student-avatar-placeholder {
      width: 50px;
      height: 50px;
      border-radius: 50%;
      background: linear-gradient(135deg, var(--navy), #001a52);
      display: flex;
      align-items: center;
      justify-content: center;
      border: 2px solid var(--gold);
    }

    .student-avatar-placeholder i {
      color: var(--gold);
      font-size: 1.5rem;
    }

    .student-details {
      flex: 1;
    }

    .student-name {
      font-weight: 600;
      color: var(--navy);
      margin-bottom: 0.25rem;
    }

    .student-course {
      font-size: 0.85rem;
      color: #666;
    }

    /* Book Info */
    .book-info {
      display: flex;
      flex-direction: column;
      gap: 0.25rem;
    }

    .book-title {
      font-weight: 600;
      color: var(--navy);
    }

    .book-isbn {
      font-size: 0.85rem;
      color: #666;
    }

    /* Date Info */
    .date-info {
      display: flex;
      flex-direction: column;
      gap: 0.25rem;
      font-size: 0.9rem;
    }

    .date-label {
      color: #666;
      font-size: 0.8rem;
    }

    .date-value {
      font-weight: 600;
      color: var(--navy);
    }

    /* Status Badge */
    .status-badge {
      display: inline-block;
      padding: 0.35rem 0.75rem;
      border-radius: 20px;
      font-size: 0.85rem;
      font-weight: 600;
    }

    .status-returned {
      background: #d1e7dd;
      color: #0f5132;
    }

    .status-borrowed {
      background: #fff3cd;
      color: #856404;
    }

    .status-overdue {
      background: #f8d7da;
      color: #842029;
    }

    /* Fine Display */
    .fine-amount {
      font-weight: 700;
      color: var(--navy);
      font-size: 1.1rem;
    }

    .fine-zero {
      color: #28a745;
    }

    /* Action Buttons */
    .btn-action {
      padding: 0.5rem 1rem;
      border-radius: 6px;
      font-weight: 600;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      transition: all 0.3s ease;
      border: none;
      cursor: pointer;
      font-size: 0.85rem;
    }

    .btn-view {
      background: var(--navy);
      color: white;
    }

    .btn-view:hover {
      background: #001a52;
      transform: translateY(-2px);
    }

    .btn-return {
      background: #28a745;
      color: white;
    }

    .btn-return:hover {
      background: #218838;
      transform: translateY(-2px);
    }

    /* DataTables Custom Styling */
    .dataTables_wrapper .dataTables_length,
    .dataTables_wrapper .dataTables_filter,
    .dataTables_wrapper .dataTables_info,
    .dataTables_wrapper .dataTables_paginate {
      padding: 1rem 1.5rem;
    }

    .dataTables_wrapper .dataTables_filter input {
      border: 2px solid #e0e0e0;
      border-radius: 8px;
      padding: 0.5rem 1rem;
      margin-left: 0.5rem;
    }

    .dataTables_wrapper .dataTables_length select {
      border: 2px solid #e0e0e0;
      border-radius: 8px;
      padding: 0.5rem;
      margin: 0 0.5rem;
    }

    /* Print Styles */
    @media print {
      @page {
        size: landscape;
        margin: 15mm;
      }

      header,
      .page-header,
      .stats-grid,
      .btn,
      .alert,
      .dataTables_wrapper .dataTables_length,
      .dataTables_wrapper .dataTables_filter,
      .dataTables_wrapper .dataTables_info,
      .dataTables_wrapper .dataTables_paginate {
        display: none !important;
      }

      .page-container {
        margin: 0;
        padding: 0;
        max-width: 100%;
      }

      .table-container {
        box-shadow: none;
      }

      table {
        font-size: 10px !important;
      }

      table th:last-child,
      table td:last-child {
        display: none !important;
      }

      .student-avatar,
      .student-avatar-placeholder {
        width: 30px;
        height: 30px;
      }

      body {
        background: #fff !important;
        color: #000 !important;
      }
    }

    @media (max-width: 768px) {
      .stats-grid {
        grid-template-columns: 1fr;
      }

      .page-header {
        flex-direction: column;
        text-align: center;
      }

      .header-actions {
        flex-direction: column;
        width: 100%;
      }

      .filter-group {
        width: 100%;
        justify-content: center;
      }
    }
  </style>
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
            <option value="not_returned" <?= $statusFilter === 'not_returned' ? 'selected' : '' ?>>Currently Borrowed</option>
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
              if ($issuedTime) echo "<div class='date-label'>$issuedTime</div>";
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
              if ($returnedTime) echo "<div class='date-label'>$returnedTime</div>";
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

  <script>
    $(document).ready(function() {
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

      $('#statusFilter').on('change', function() {
        const val = $(this).val();
        const url = new URL(window.location);
        url.searchParams.set('status', val);
        window.location = url.toString();
      });
    });

    // Auto-dismiss alerts
    window.addEventListener('DOMContentLoaded', function() {
      const alerts = document.querySelectorAll('.alert');
      alerts.forEach(alert => {
        setTimeout(function() {
          alert.style.transition = 'opacity 0.5s ease';
          alert.style.opacity = '0';
          setTimeout(function() {
            alert.remove();
          }, 500);
        }, 5000);
      });
    });
  </script>
</body>

</html>