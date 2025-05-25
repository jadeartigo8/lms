<?php
session_start();

if (strlen($_SESSION['alogin']) == 0) {
  header('location:../index.php');
  exit;
}

include 'includes/header.php';
include 'includes/logger.php';


$logger = new Logger();
$logs = $logger->getLogs();
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>System Logs</title>

  <link rel="stylesheet" href="../css/styles.css">
  <link rel="stylesheet" href="../css/tables.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700&display=swap" rel="stylesheet">

</head>

<body>
  <div class="container-log">
    <div>
      <h2>System Logs</h2>
      <div>
        <?php foreach ($logs as $line): ?>
          <div class="log-entry"><?php echo htmlspecialchars($line); ?></div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>


</body>

</html>