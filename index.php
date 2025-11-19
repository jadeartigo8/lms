<?php
session_start();
error_reporting(E_ALL);
include('connection/db.php');

if (isset($_SESSION['login']) && $_SESSION['login'] != '') {
  $_SESSION['login'] = '';
}


if (isset($_POST['login'])) {
    // Input validation
    $email = filter_var(trim($_POST['emailid']), FILTER_SANITIZE_EMAIL);
    $inputPassword = $_POST['password'];
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = 'Invalid email format';
    }
    // Validate password length
    else if (strlen($inputPassword) < 6) {
        $_SESSION['error'] = 'Password must be at least 6 characters';
    }
    else {
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
                        
                        // Clear any errors before redirect
                        unset($_SESSION['error']);
                        
                        header('location: dashboard.php');
                        exit;
                    } else {
                        $_SESSION['error'] = 'You are blocked. Contact the admin.';
                    }
                } else {
                    $_SESSION['error'] = 'Invalid credentials';
                }
            } else {
                $_SESSION['error'] = 'Invalid credentials';
            }
            
            $stmt->close();
        } catch (Exception $e) {
            $_SESSION['error'] = "An error occurred: " . $e->getMessage();
        }
    }
}

// Store error in a variable and clear it from session immediately
$errorMessage = '';
if (!empty($_SESSION['error'])) {
    $errorMessage = $_SESSION['error'];
    unset($_SESSION['error']);
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
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700&display=swap" rel="stylesheet">

  <style>
    .custom-error {
      transition: opacity 0.3s ease;
    }
    .custom-error.fade-out {
      opacity: 0;
    }
  </style>
</head>

<body>

  <!-- Header Section -->
  <?php include('includes/header.php'); ?>

  <!-- Main Content -->

  <div class="form-container">
    <div class="login-card">
      <div class="card-header">
        <h4><i class="fas fa-user"></i>&nbsp; User Login Form</h4>
      </div>
      <div class="card-body">

        <form method="post" id="loginForm">

        <?php if (!empty($errorMessage)): ?>
        <div class="custom-error" id="errorMessage">
          <?php echo htmlentities($errorMessage); ?>
        </div>
        <?php endif; ?>

          <!-- Email Field -->
          <div class="form-group">
            <label for="emailid">Email</label>
            <input type="email" id="emailid" name="emailid" required autocomplete="off" 
                   maxlength="100" pattern="[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$"
                   title="Please enter a valid email address">
          </div>

          <!-- Password Field -->
          <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required autocomplete="off"
                   minlength="6" maxlength="255"
                   title="Password must be at least 6 characters">
          </div>

          <!-- Buttons -->
          <div class="form-actions">
            <button type="submit" name="login">Login</button>
            <div class="signup-link">
              <a href="user-signup.php"><small>Not registered yet? Create your account.</small></a>
            </div>

            <div class="signup-link">
              <a href="adminlogin.php"><small><i class="fas fa-user-shield"></i> Admin?</small></a>
            </div>
          </div>

        </form>
      </div>
    </div>

    <div class="login-image">
      
    </div>
  </div>

  <script>
    // Hide error message when user starts typing
    const emailInput = document.getElementById('emailid');
    const passwordInput = document.getElementById('password');
    const errorMessage = document.getElementById('errorMessage');

    function hideError() {
      if (errorMessage) {
        errorMessage.classList.add('fade-out');
        setTimeout(() => {
          errorMessage.style.display = 'none';
        }, 300);
      }
    }

    if (emailInput && passwordInput) {
      emailInput.addEventListener('input', hideError);
      passwordInput.addEventListener('input', hideError);
    }

    // Client-side validation
    document.getElementById('loginForm').addEventListener('submit', function(e) {
      const email = emailInput.value.trim();
      const password = passwordInput.value;

      // Email validation
      const emailRegex = /^[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$/i;
      if (!emailRegex.test(email)) {
        alert('Please enter a valid email address');
        e.preventDefault();
        return false;
      }

      // Password length validation
      if (password.length < 6) {
        alert('Password must be at least 6 characters long');
        e.preventDefault();
        return false;
      }

      // Prevent empty spaces
      if (password.includes(' ')) {
        alert('Password should not contain spaces');
        e.preventDefault();
        return false;
      }
    });
  </script>

</body>

</html>