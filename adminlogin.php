<?php
session_start();
error_reporting(E_ALL);
include('connection/db.php');
include 'admin/includes/logger.php';
date_default_timezone_set('Asia/Manila');

$logger = new Logger();

if (isset($_SESSION['alogin']) && $_SESSION['alogin'] != '') {
    $_SESSION['alogin'] = '';
}


$error = '';

if (isset($_POST['login'])) {
    $email = $_POST['emailid'];
    $inputPassword = $_POST['password'];

    try {
        $stmt = $conn->prepare("SELECT admin_id, email, password
                                        FROM admin
                                        WHERE email = ?");
        if (!$stmt) {
            throw new Exception("Statement preparation failed: " . $conn->error);
           
        }

        $stmt->bind_param("s", $email);
        if (!$stmt->execute()) {
            throw new Exception("Execution failed: " . $stmt->error);
        }

        $result = $stmt->get_result();
        $student = ($result && $result->num_rows > 0) ? $result->fetch_assoc() : null;

        if ($student) {
            if (password_verify($inputPassword, $student['password'])) {
                session_regenerate_id(true);
                $_SESSION['alogin'] = $student['email'];
                $_SESSION['admin_id'] = $student['admin_id'];
                header("Location: admin/dashboard.php");
                $logger->write("Admin is logged in.");
                exit();
            } else {
              $_SESSION['error'] = 'Invalid credentials';
              $logger->write("There is an attempt to log in.");
            }
        } else {
          $_SESSION['error'] = 'Invalid credentials';
          $logger->write("There is an attempt to log in.");
        }

    } catch (Exception $e) {
        $error = "An error occurred: " . $e->getMessage();
        echo "<script>alert('$error');</script>";
    }
}
?>



<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Login</title>

  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="css/styles.css">
</head>

<body >

  <!-- Header Section -->
  <?php include('includes/header.php'); ?>

  <!-- Main Content -->

  <div class="form-container">
    <div class="login-card">
      
      <div class="card-header">
        <h4><i class="fas fa-user-shield"></i>&nbsp; Admin Login Form</h4>
      </div>
      <div class="card-body">
        <form method="post">

        <?php if (!empty($_SESSION['error'])): ?>
        <div class="custom-error">
          <?php
            echo htmlentities($_SESSION['error']);
            unset($_SESSION['error']);
          ?>
        </div>
        <?php endif; ?>

        

          <!-- Email Field -->
          <div class="form-group">
            <label for="emailid">Email</label>
            <input type="email" id="emailid" name="emailid" required autocomplete="off">
          </div>

          <!-- Password Field -->
          <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required autocomplete="off">
          </div>

          <!-- Buttons -->
          <div class="form-actions">
            <button type="submit" name="login">Login</button>

            <div class="signup-link">
              <a href="index.php"><small><i class="fas fa-user"></i> User?</small></a>
            </div>
          </div>

          

          
        </form>
      </div>
    </div>



    <div class="login-image"></div>
  </div>


</body>

</html>