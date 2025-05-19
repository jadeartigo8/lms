<?php
session_start();
include('../connection/db.php');
include '../security/crypt.php';
include 'includes/logger.php';
date_default_timezone_set('Asia/Manila');

if (strlen($_SESSION['alogin']) == 0) {
    header('location:../index.php');
    exit;
}

$logger = new Logger();



$studentid = "";
$first_name = "";
$middle_name = "";
$last_name = "";
$email = "";
$mobileno = "";

$errors = [];

if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "Invalid request.";
    header('Location: reg-students.php');
    exit;
}

$decryptedID = decrypt($_GET['id']);
if (!$decryptedID) {
    $_SESSION['error'] = "Invalid student ID.";
    header('Location: reg-students.php');
    exit;
}


$stmt = $conn->prepare("SELECT * FROM students WHERE student_id = ?");
$stmt->bind_param("s", $decryptedID);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    $_SESSION['error'] = "Student not found.";
    header('Location: reg-students.php');
    exit;
}

$row = $result->fetch_assoc();
$studentid = $row['student_id'];
$first_name = $row['first_name'];
$middle_name = $row['middle_name'];
$last_name = $row['last_name'];
$email = $row['email'];
$mobileno = $row['mobile_no'];

if (isset($_POST['update'])) {
    $first_name = trim($_POST['first_name']);
    $middle_name = trim($_POST['middle_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $mobileno = trim($_POST['mobileno']);

    
    if (!preg_match("/^[a-zA-Z\s'-]+$/u", $first_name)) {
        $errors[] = "First name is invalid.";
    }

    if (!preg_match("/^[a-zA-Z\s'-]+$/u", $middle_name)) {
        $errors[] = "Middle name is invalid.";
    }

    if (!preg_match("/^[a-zA-Z\s'-]+$/u", $last_name)) {
        $errors[] = "Last name is invalid.";
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }

    if (!preg_match('/^09\d{9}$/', $mobileno)) {
        $errors[] = "Mobile number must start with 09 and have 11 digits.";
    }

    if (empty($errors)) {
        $stmt = $conn->prepare("UPDATE students SET first_name = ?, middle_name = ?, last_name = ?, email = ?, mobile_no = ? WHERE student_id = ?");
        $stmt->bind_param("ssssss", $first_name, $middle_name, $last_name, $email, $mobileno, $studentid);
        
        if ($stmt->execute()) {
            $_SESSION['updatemsg'] = "Student information updated successfully.";
            $logger->write("Student information updated. $first_name, $last_name");

            header('Location: reg-students.php');
            exit;
        } else {
            $errors[] = "Failed to update student information.";
        }
    }
    
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Student</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/styles.css">
</head>
<body style="font-family: 'Verdana', sans-serif;">
<?php include('includes/header.php'); ?>

<div class="signup-container">
    <h3>Edit Student Information</h3>

    <?php if (!empty($errors)): ?>
        <div class="custom-error">
            <ul>
            <?php foreach ($errors as $error): ?>
                <li><?php echo htmlentities($error); ?></li>
            <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-row">
            <div class="form-group">
                <label>Student ID</label>
                <input type="text" value="<?php echo htmlentities($studentid); ?>" readonly>
            </div>
            <div class="form-group">
                <label>Mobile Number</label>
                <input type="text" name="mobileno" value="<?php echo htmlentities($mobileno); ?>" maxlength="11" required>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>First Name</label>
                <input type="text" name="first_name" value="<?php echo htmlentities($first_name); ?>" required>
            </div>
            <div class="form-group">
                <label>Middle Name</label>
                <input type="text" name="middle_name" value="<?php echo htmlentities($middle_name); ?>" required>
            </div>
            <div class="form-group">
                <label>Last Name</label>
                <input type="text" name="last_name" value="<?php echo htmlentities($last_name); ?>" required>
            </div>
        </div>

        <div class="form-group">
            <label>Email Address</label>
            <input type="email" name="email" value="<?php echo htmlentities($email); ?>" required>
        </div>

        <div class="form-actions">
            <button type="submit" name="update" class="btn">Update</button>
        </div>
    </form>
</div>

</body>
</html>

