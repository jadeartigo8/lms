<?php
session_start();

if (strlen($_SESSION['alogin']) == 0) {
  header('location:../index.php');
  exit;
}

include 'includes/header.php';
include 'includes/logger.php';

// Handle delete all logs
if (isset($_POST['delete_all_logs'])) {
  $logger = new Logger();
  $logger->clearLogs();
  header('location:logs.php');
  exit;
}

$logger = new Logger();
$logs = $logger->getLogs();

// Pagination settings
$logsPerPage = 50;
$currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$totalLogs = count($logs);
$totalPages = $totalLogs > 0 ? ceil($totalLogs / $logsPerPage) : 1;
$currentPage = min($currentPage, $totalPages);

// Get logs for current page
$offset = ($currentPage - 1) * $logsPerPage;
$logsToDisplay = array_slice($logs, $offset, $logsPerPage);
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>System Logs</title>

  <link rel="stylesheet" href="../css/styles.css">
  <link rel="stylesheet" href="../css/tables.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700&display=swap" rel="stylesheet">

  <style>
    .log-controls {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 20px;
      flex-wrap: wrap;
      gap: 10px;
    }

    .log-info {
      color: #666;
      font-size: 14px;
    }

    .pagination {
      display: flex;
      gap: 5px;
      align-items: center;
      flex-wrap: wrap;
    }

    .pagination a,
    .pagination span {
      padding: 8px 12px;
      border: 1px solid #ddd;
      text-decoration: none;
      color: #333;
      border-radius: 4px;
      transition: all 0.3s;
    }

    .pagination a:hover {
      background-color: #000435;
      color: white;
      border-color: #000435;
    }

    .pagination .current {
      background-color: #000435;
      color: white;
      border-color: #000435;
    }

    .pagination .disabled {
      color: #ccc;
      cursor: not-allowed;
      pointer-events: none;
    }

    .btn-delete-all {
      background-color: #dc3545;
      color: white;
      border: none;
      padding: 10px 20px;
      border-radius: 4px;
      cursor: pointer;
      font-size: 14px;
      transition: background-color 0.3s;
    }

    .btn-delete-all:hover {
      background-color: #c82333;
    }

    .log-entry {
      padding: 10px;
      margin-bottom: 5px;
      background-color: #f8f9fa;
      border-left: 3px solid #000435;
      font-family: monospace;
      font-size: 13px;
      word-break: break-all;
    }

    .no-logs {
      text-align: center;
      padding: 40px;
      color: #999;
      font-size: 16px;
    }
  </style>
</head>

<body>
  <div class="container-log">
    <div>
      <h2>System Logs</h2>
      
      <div class="log-controls">
        <div class="log-info">
          <?php if ($totalLogs > 0): ?>
            Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $logsPerPage, $totalLogs); ?> of <?php echo $totalLogs; ?> entries
          <?php else: ?>
            No logs available
          <?php endif; ?>
        </div>
        
        <?php if ($totalLogs > 0): ?>
          <form method="POST" onsubmit="return confirm('Are you sure you want to delete all logs? This action cannot be undone.');">
            <button type="submit" name="delete_all_logs" class="btn-delete-all">
              <i class="fas fa-trash"></i> Delete All Logs
            </button>
          </form>
        <?php endif; ?>
      </div>

      <div>
        <?php if (count($logsToDisplay) > 0): ?>
          <?php foreach ($logsToDisplay as $line): ?>
            <div class="log-entry"><?php echo htmlspecialchars($line); ?></div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="no-logs">
            <i class="fas fa-info-circle" style="font-size: 48px; margin-bottom: 10px;"></i>
            <p>No logs to display</p>
          </div>
        <?php endif; ?>
      </div>

      <?php if ($totalPages > 1): ?>
        <div class="log-controls" style="margin-top: 20px;">
          <div></div>
          <div class="pagination">
            <?php if ($currentPage > 1): ?>
              <a href="?page=1" title="First"><i class="fas fa-angle-double-left"></i></a>
              <a href="?page=<?php echo $currentPage - 1; ?>" title="Previous"><i class="fas fa-angle-left"></i> Prev</a>
            <?php else: ?>
              <span class="disabled"><i class="fas fa-angle-double-left"></i></span>
              <span class="disabled"><i class="fas fa-angle-left"></i> Prev</span>
            <?php endif; ?>

            <?php
            // Show page numbers with ellipsis
            $range = 2; // Number of pages to show on each side of current page
            
            for ($i = 1; $i <= $totalPages; $i++) {
              if ($i == 1 || $i == $totalPages || ($i >= $currentPage - $range && $i <= $currentPage + $range)) {
                if ($i == $currentPage) {
                  echo '<span class="current">' . $i . '</span>';
                } else {
                  echo '<a href="?page=' . $i . '">' . $i . '</a>';
                }
              } elseif ($i == $currentPage - $range - 1 || $i == $currentPage + $range + 1) {
                echo '<span class="disabled">...</span>';
              }
            }
            ?>

            <?php if ($currentPage < $totalPages): ?>
              <a href="?page=<?php echo $currentPage + 1; ?>" title="Next">Next <i class="fas fa-angle-right"></i></a>
              <a href="?page=<?php echo $totalPages; ?>" title="Last"><i class="fas fa-angle-double-right"></i></a>
            <?php else: ?>
              <span class="disabled">Next <i class="fas fa-angle-right"></i></span>
              <span class="disabled"><i class="fas fa-angle-double-right"></i></span>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>

</body>

</html>