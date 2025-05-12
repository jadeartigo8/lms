<?php
session_start();
error_reporting(E_ALL);
include('connection/db.php');

if (isset($_SESSION['login']) && $_SESSION['login'] != '') {
  $_SESSION['login'] = '';
}


if (isset($_POST['login'])) {
    $email = $_POST['emailid'];
    $inputPassword = $_POST['password'];

    try {
        $stmt = $conn->prepare("SELECT * FROM students WHERE email = ?");
        if (!$stmt) {
            throw new Exception("Statement preparation failed: " . $conn->error);
        }

        
        $stmt->bind_param("s", $email);
        if (!$stmt->execute()) {
            throw new Exception("Execution failed: " . $stmt->error);
        }

        $result = $stmt->get_result();
        if ($result && $result->num_rows > 0) {
            $student = $result->fetch_assoc();

            if (password_verify($inputPassword, $student['password'])) {
                
                if ($student['status'] == 1) {
                    $_SESSION['stdid'] = $student['student_id'];
                    $_SESSION['login'] = $student['first_name'];

                    header('location: dashboard.php');
                    exit;
                } else {
                  $_SESSION['error'] = 'Invalid credentials';
                }
            } else {
              $_SESSION['error'] = 'Invalid credentials';
            }
        } else {
          $_SESSION['error'] = 'Invalid credentials';
        }
    } catch (Exception $e) {
      $_SESSION['error'] = "An error occurred: " . $e->getMessage();
    }
}
?>


<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>User Login</title>

  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="css/styles.css">

  <style>

  </style>
</head>

<body>

  <!-- Header Section -->
  <?php include('includes/header.php'); ?>

  <!-- Main Content -->

  <div class="form-container">
    <div class="login-card">
      <div class="card-header">
        <h4><i class="fas fa-user-circle"></i>&nbsp; User Login Form</h4>
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
              <a href="signup.php"><small>Not registered yet?</small></a>
            </div>
          </div>

        </form>
      </div>
    </div>
  </div>


</body>

</html>