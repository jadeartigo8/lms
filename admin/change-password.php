<?php
session_start();
error_reporting(E_ALL);
include('../connection/db.php');
include 'includes/logger.php';
date_default_timezone_set('Asia/Manila');

$logger = new Logger();



if (strlen($_SESSION['alogin']) == 0) {
  header('location:../index.php');
  exit;
}


if (isset($_POST['change_credentials'])) {
    $currentEmail = $_SESSION['alogin'];
    $newEmail = $_POST['new_email'];
    $currentPassword = $_POST['current_password'];
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];

    try {
        if ($newPassword !== $confirmPassword) {
            throw new Exception("New password and confirm password do not match.");
        }

        $stmt = $conn->prepare("SELECT password FROM admin WHERE email = ?");
        if (!$stmt) throw new Exception("Statement preparation failed: " . $conn->error);

        $stmt->bind_param("s", $currentEmail);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows === 1) {
            $admin = $result->fetch_assoc();

            if (password_verify($currentPassword, $admin['password'])) {
                $hashedNewPassword = password_hash($newPassword, PASSWORD_DEFAULT);

                $update = $conn->prepare("UPDATE admin SET email = ?, password = ?, update_date = CURRENT_TIMESTAMP WHERE email = ?");
                $update->bind_param("sss", $newEmail, $hashedNewPassword, $currentEmail);
                $update->execute();

                $_SESSION['alogin'] = $newEmail;
                $_SESSION['success'] = "Credentials updated successfully.";
                $logger->write("Credentials updated.");
            } else {
                throw new Exception("Current password is incorrect.");
            }
        } else {
            throw new Exception("Admin not found.");
        }
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Change Credentials</title>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../css/styles.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

<?php include('includes/header.php'); ?>

<div class="form-container">
  <div class="login-card">
    <div class="card-header">
      <h4><i class="fas fa-user-cog"></i>&nbsp; Change Email & Password</h4>
    </div>
    <div class="card-body">
      <form method="post">

        <?php if (!empty($_SESSION['error'])): ?>
          <div class="custom-error">
            <?php echo htmlentities($_SESSION['error']); unset($_SESSION['error']); ?>
          </div>
        <?php elseif (!empty($_SESSION['success'])): ?>
          <div class="custom-error" style="background-color: #d4edda; color: #155724; border-color: #c3e6cb;">
            <?php echo htmlentities($_SESSION['success']); unset($_SESSION['success']); ?>
          </div>
        <?php endif; ?>

        <div class="form-group">
          <label for="new_email">New Email</label>
          <input type="email" id="new_email" name="new_email" value="<?php echo htmlentities($_SESSION['alogin']); ?>" required>
        </div>

        <div class="form-group">
          <label for="current_password">Current Password</label>
          <input type="password" id="current_password" name="current_password" required>
        </div>

        <div class="form-group">
          <label for="new_password">New Password</label>
          <input type="password" id="new_password" name="new_password" required>
        </div>

        <div class="form-group">
          <label for="confirm_password">Confirm New Password</label>
          <input type="password" id="confirm_password" name="confirm_password" required>
        </div>

        <div class="form-actions">
          <button type="submit" name="change_credentials">Update Credentials</button>
        </div>
      </form>
    </div>
  </div>
</div>

</body>
</html>
