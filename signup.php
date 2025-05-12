<?php
session_start();
include('connection/db.php');
error_reporting(E_ALL);

$emailError = "";
$passwordError = "";
$mobileError = "";
$firstNameError = "";
$middleNameError = "";
$lastNameError = "";
$studentiderror = "";

function checkEmailExists($conn, $email) {
    $stmt = $conn->prepare("SELECT * FROM students WHERE email = ?");
    if (!$stmt) throw new Exception("Statement preparation failed: " . $conn->error);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->num_rows > 0;
}

function checkStudentIdExists($conn, $studentid) {
    $stmt = $conn->prepare("SELECT * FROM students WHERE student_id = ?");
    if (!$stmt) throw new Exception("Statement preparation failed: " . $conn->error);
    $stmt->bind_param("s", $studentid);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->num_rows > 0;
}

function registerStudent($conn, $studentid, $fname, $mname, $lname, $email, $mobile, $password, $status, $regDate) {
    $stmt = $conn->prepare("INSERT INTO students (student_id, first_name, middle_name, last_name, email, mobile_no, password, status, registration_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) throw new Exception("Statement preparation failed: " . $conn->error);
    $stmt->bind_param("sssssssis", $studentid, $fname, $mname, $lname, $email, $mobile, $password, $status, $regDate);
    return $stmt->execute();
}

function isValidMobileNumber($mobile) {
    return preg_match('/^09\d{9}$/', $mobile);
}

function isValidName($name) {
    return preg_match("/^[a-zA-Z\s'-]+$/u", $name);
}

if (isset($_POST['signup'])) {
    // Sanitize inputs
    $studentid = trim($_POST['studentid']);
    $first_name = trim($_POST['first_name']);
    $middle_name = trim($_POST['middle_name']);
    $last_name = trim($_POST['last_name']);
    $mobileno = trim($_POST['mobileno']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirmpassword'];
    $status = 1;
    $registration_date = date("Y-m-d H:i:s");

  
    if ($password !== $confirmPassword) {
        $passwordError = "Passwords do not match.";
    }

    if (!preg_match('/^\d{7}-\d{1}$/', $studentid)) {
        $studentiderror = "Student ID must follow the format NNNNNNN-N (e.g., 2310074-1).";
    }

    if (!isValidMobileNumber($mobileno)) {
        $mobileError = "Invalid mobile number format. Must be 11 digits starting with '09'.";
    }

    if (!isValidName($first_name)) {
        $firstNameError = "First name should only contain letters, spaces, apostrophes, or hyphens.";
    }

    if (!isValidName($middle_name)) {
        $middleNameError = "Middle name should only contain letters, spaces, apostrophes, or hyphens.";
    }

    if (!isValidName($last_name)) {
        $lastNameError = "Last name should only contain letters, spaces, apostrophes, or hyphens.";
    }

    
    $hasErrors = !empty($passwordError) || !empty($studentiderror) || !empty($mobileError) ||
                 !empty($firstNameError) || !empty($middleNameError) || !empty($lastNameError);

    if (!$hasErrors) {
        try {
            if (checkEmailExists($conn, $email)) {
                $emailError = "Email already exists. Please use a different one.";
            } elseif (checkStudentIdExists($conn, $studentid)) {
                $studentiderror = "Student ID already exists. Please use a different one.";
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $success = registerStudent($conn, $studentid, $first_name, $middle_name, $last_name, $email, $mobileno, $hashed_password, $status, $registration_date);
                if ($success) {
                    echo "<script>alert('Your registration was successful!');</script>";
                    $_POST = array(); 
                } else {
                    echo "<script>alert('Something went wrong. Please try again later.');</script>";
                }
            }
        } catch (Exception $e) {
            echo "<script>alert('Error: " . addslashes($e->getMessage()) . "');</script>";
        }
    }
}
?>





<!DOCTYPE html>
<html>

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
  <meta name="description" content="" />
  <meta name="author" content="" />
  <title>User Signup</title>
  
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="css/styles.css">
</head>

<body style="font-family: 'Verdana', sans-serif;">
  <?php include('includes/header.php'); ?>

  <div class="signup-container">
    <h3>Student Signup Form</h3>

    <form name="signup" method="POST">
      <div class="form-row">
        <div class="form-group">
          <label for="studentid">Student ID</label>
          <input type="text" name="studentid" maxlength="9" required
            value="<?php echo isset($_POST['studentid']) ? htmlspecialchars($_POST['studentid']) : ''; ?>">
          <?php if (!empty($studentiderror)): ?>
            <small class="error"><?php echo $studentiderror; ?></small>
          <?php endif; ?>
        </div>

        <div class="form-group">
          <label for="mobileno">Mobile Number</label>
          <input type="text" name="mobileno" maxlength="11" required
            value="<?php echo isset($_POST['mobileno']) ? htmlspecialchars($_POST['mobileno']) : ''; ?>">
            <?php if (!empty($mobileError)): ?>
            <small class="error"><?php echo $mobileError; ?></small>
          <?php endif; ?>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="first_name">First Name</label>
          <input type="text" name="first_name" required
            value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>">
            <?php if (!empty($firstNameError)): ?>
            <small class="error"><?php echo $firstNameError; ?></small>
          <?php endif; ?>
        </div>

        <div class="form-group">
          <label for="middle_name">Middle Name</label>
          <input type="text" name="middle_name" 
            value="<?php echo isset($_POST['middle_name']) ? htmlspecialchars($_POST['middle_name']) : ''; ?>">
            <?php if (!empty($middleNameError)): ?>
            <small class="error"><?php echo $middleNameError; ?></small>
          <?php endif; ?>
        </div>

        <div class="form-group">
          <label for="last_name">Last Name</label>
          <input type="text" name="last_name" required
            value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>">
            <?php if (!empty($lastNameError)): ?>
            <small class="error"><?php echo $lastNameError; ?></small>
          <?php endif; ?>
        </div>
      </div>

      <div class="form-group">
        <label for="email">Email Address</label>
        <input type="email" name="email" required
          value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
        <?php if (!empty($emailError)): ?>
          <small class="error"><?php echo $emailError; ?></small>
        <?php endif; ?>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="password">Password</label>
          <input type="password" name="password" required>
        </div>

        <div class="form-group">
          <label for="confirmpassword">Confirm Password</label>
          <input type="password" name="confirmpassword" required>
          <?php if (!empty($passwordError)): ?>
            <small class="error"><?php echo $passwordError; ?></small>
          <?php endif; ?>
        </div>
      </div>

      <div class="form-actions">
        <button type="submit" name="signup" class="btn">Register Now</button>
      </div>
    </form>
  </div>

</body>

</html>
