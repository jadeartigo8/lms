<?php
session_start();
error_reporting(E_ALL);
include('connection/db.php');
include('admin/includes/logger.php');
date_default_timezone_set('Asia/Manila');

$logger = new Logger();

if (strlen($_SESSION['login']) == 0) {
    header('location:index.php');
    exit();
}

$studentID = $_SESSION['stdid'];
$error = "";
$success = "";

// Handle password change
if (isset($_POST['change_password'])) {
    $currentPassword = $_POST['current_password'];
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];

    try {
        // Validate inputs
        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            throw new Exception("All fields are required.");
        }

        if (strlen($newPassword) < 6) {
            throw new Exception("New password must be at least 6 characters long.");
        }

        if ($newPassword !== $confirmPassword) {
            throw new Exception("New passwords do not match.");
        }

        // Verify current password
        $stmt = $conn->prepare("SELECT password FROM students WHERE student_id = ?");
        $stmt->bind_param("s", $studentID);
        $stmt->execute();
        $result = $stmt->get_result();
        $student = $result->fetch_assoc();

        if (!password_verify($currentPassword, $student['password'])) {
            throw new Exception("Current password is incorrect.");
        }

        // Update password
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $updateStmt = $conn->prepare("UPDATE students SET password = ?, update_date = NOW() WHERE student_id = ?");
        $updateStmt->bind_param("ss", $hashedPassword, $studentID);
        
        if ($updateStmt->execute()) {
            $success = "Password changed successfully!";
            $logger->write("Password changed successfully for student ID: $studentID");
            
            // Clear form
            $_POST = array();
        } else {
            throw new Exception("Failed to update password. Please try again.");
        }

    } catch (Exception $e) {
        $error = $e->getMessage();
        $logger->write("Password change error for student ID $studentID: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/styles.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --navy: #000435;
            --gold: #ffde59;
            --light-bg: #f8f9fa;
        }

        body {
            background: var(--light-bg);
            font-family: 'Montserrat', sans-serif;
        }

        .container {
            max-width: 600px;
            margin: 2rem auto;
            padding: 0 1rem;
        }

        .page-header {
            background: linear-gradient(135deg, var(--navy), #001a52);
            color: white;
            padding: 2rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            box-shadow: 0 8px 25px rgba(0,0,0,.2);
        }

        .page-header h1 {
            margin: 0;
            font-size: 2rem;
            color: white;
        }

        .page-header p {
            margin: 0.5rem 0 0 0;
            color: var(--gold);
        }

        .form-card {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 4px 15px rgba(0,0,0,.1);
        }

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

        .alert-error {
            background: #f8d7da;
            color: #842029;
            border-left: 4px solid #dc3545;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #333;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-group label i {
            color: var(--navy);
        }

        .password-input-wrapper {
            position: relative;
        }

        .form-group input[type="password"],
        .form-group input[type="text"] {
            width: 100%;
            padding: 0.75rem 2.5rem 0.75rem 1rem;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
            font-family: 'Montserrat', sans-serif;
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--navy);
            box-shadow: 0 0 0 3px rgba(0, 4, 53, 0.1);
        }

        .toggle-password {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #666;
            transition: color 0.3s ease;
        }

        .toggle-password:hover {
            color: var(--navy);
        }

        .password-requirements {
            background: #e7f3ff;
            border-left: 4px solid #0066cc;
            padding: 1rem;
            margin: 1.5rem 0;
            border-radius: 4px;
        }

        .password-requirements h4 {
            margin: 0 0 0.5rem 0;
            color: #003d7a;
            font-size: 0.9rem;
        }

        .password-requirements ul {
            margin: 0;
            padding-left: 1.5rem;
            color: #003d7a;
            font-size: 0.85rem;
        }

        .password-requirements li {
            margin: 0.25rem 0;
        }

        .form-actions {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }

        .btn {
            flex: 1;
            padding: 0.875rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: var(--navy);
            color: white;
        }

        .btn-primary:hover {
            background: #001a52;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,.2);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .strength-meter {
            margin-top: 0.5rem;
            height: 4px;
            background: #e0e0e0;
            border-radius: 2px;
            overflow: hidden;
            display: none;
        }

        .strength-meter-fill {
            height: 100%;
            transition: all 0.3s ease;
            width: 0%;
        }

        .strength-weak { background: #dc3545; width: 33%; }
        .strength-medium { background: #ffc107; width: 66%; }
        .strength-strong { background: #198754; width: 100%; }

        .strength-text {
            font-size: 0.75rem;
            margin-top: 0.25rem;
            display: none;
        }

        @media (max-width: 768px) {
            .form-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <?php include('includes/header.php'); ?>

    <div class="container">
        <div class="page-header">
            <h1><i class="fas fa-key"></i> Change Password</h1>
            <p>Update your account password</p>
        </div>

        <div class="form-card">
            <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <span><?= htmlspecialchars($success) ?></span>
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" id="changePasswordForm">
                <div class="form-group">
                    <label for="current_password">
                        <i class="fas fa-lock"></i>
                        Current Password
                    </label>
                    <div class="password-input-wrapper">
                        <input type="password" id="current_password" name="current_password" required>
                        <i class="fas fa-eye toggle-password" onclick="togglePassword('current_password')"></i>
                    </div>
                </div>

                <div class="password-requirements">
                    <h4><i class="fas fa-info-circle"></i> Password Requirements:</h4>
                    <ul>
                        <li>Minimum 6 characters long</li>
                        <li>Should contain a mix of letters and numbers</li>
                        <li>Avoid using easily guessable information</li>
                    </ul>
                </div>

                <div class="form-group">
                    <label for="new_password">
                        <i class="fas fa-lock"></i>
                        New Password
                    </label>
                    <div class="password-input-wrapper">
                        <input type="password" id="new_password" name="new_password" required>
                        <i class="fas fa-eye toggle-password" onclick="togglePassword('new_password')"></i>
                    </div>
                    <div class="strength-meter" id="strengthMeter">
                        <div class="strength-meter-fill" id="strengthFill"></div>
                    </div>
                    <div class="strength-text" id="strengthText"></div>
                </div>

                <div class="form-group">
                    <label for="confirm_password">
                        <i class="fas fa-lock"></i>
                        Confirm New Password
                    </label>
                    <div class="password-input-wrapper">
                        <input type="password" id="confirm_password" name="confirm_password" required>
                        <i class="fas fa-eye toggle-password" onclick="togglePassword('confirm_password')"></i>
                    </div>
                    <small id="matchMessage" style="display:none; margin-top: 0.5rem;"></small>
                </div>

                <div class="form-actions">
                    <button type="submit" name="change_password" class="btn btn-primary">
                        <i class="fas fa-check"></i> Change Password
                    </button>
                    <a href="profile.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>

    <script>
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const icon = field.nextElementSibling;
            
            if (field.type === 'password') {
                field.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                field.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        // Password strength checker
        const newPasswordField = document.getElementById('new_password');
        const strengthMeter = document.getElementById('strengthMeter');
        const strengthFill = document.getElementById('strengthFill');
        const strengthText = document.getElementById('strengthText');

        newPasswordField.addEventListener('input', function() {
            const password = this.value;
            
            if (password.length === 0) {
                strengthMeter.style.display = 'none';
                strengthText.style.display = 'none';
                return;
            }

            strengthMeter.style.display = 'block';
            strengthText.style.display = 'block';

            let strength = 0;
            
            // Length check
            if (password.length >= 6) strength++;
            if (password.length >= 10) strength++;
            
            // Has numbers
            if (/\d/.test(password)) strength++;
            
            // Has letters
            if (/[a-zA-Z]/.test(password)) strength++;
            
            // Has special characters
            if (/[^a-zA-Z0-9]/.test(password)) strength++;

            strengthFill.className = 'strength-meter-fill';
            
            if (strength <= 2) {
                strengthFill.classList.add('strength-weak');
                strengthText.textContent = 'Weak password';
                strengthText.style.color = '#dc3545';
            } else if (strength <= 3) {
                strengthFill.classList.add('strength-medium');
                strengthText.textContent = 'Medium strength';
                strengthText.style.color = '#ffc107';
            } else {
                strengthFill.classList.add('strength-strong');
                strengthText.textContent = 'Strong password';
                strengthText.style.color = '#198754';
            }
        });

        // Password match checker
        const confirmPasswordField = document.getElementById('confirm_password');
        const matchMessage = document.getElementById('matchMessage');

        confirmPasswordField.addEventListener('input', function() {
            const newPassword = newPasswordField.value;
            const confirmPassword = this.value;

            if (confirmPassword.length === 0) {
                matchMessage.style.display = 'none';
                return;
            }

            matchMessage.style.display = 'block';

            if (newPassword === confirmPassword) {
                matchMessage.textContent = '✓ Passwords match';
                matchMessage.style.color = '#198754';
            } else {
                matchMessage.textContent = '✗ Passwords do not match';
                matchMessage.style.color = '#dc3545';
            }
        });

        // Form validation
        document.getElementById('changePasswordForm').addEventListener('submit', function(e) {
            const newPassword = newPasswordField.value;
            const confirmPassword = confirmPasswordField.value;

            if (newPassword !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match. Please try again.');
                return false;
            }

            if (newPassword.length < 6) {
                e.preventDefault();
                alert('Password must be at least 6 characters long.');
                return false;
            }
        });

        // Auto-dismiss success message
        window.addEventListener('DOMContentLoaded', function() {
            const successAlert = document.querySelector('.alert-success');
            if (successAlert) {
                setTimeout(function() {
                    successAlert.style.transition = 'opacity 0.5s ease';
                    successAlert.style.opacity = '0';
                    setTimeout(function() {
                        successAlert.remove();
                    }, 500);
                }, 5000);
            }
        });
    </script>
</body>
</html>