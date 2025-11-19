<?php
session_start();
error_reporting(E_ALL);
include('../connection/db.php');

if (strlen($_SESSION['alogin']) == 0) {
    header('location:../index.php');
    exit();
}

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    // Validation
    if (empty($firstName)) {
        $error = 'First name is required.';
    } elseif (empty($lastName)) {
        $error = 'Last name is required.';
    } elseif (empty($email)) {
        $error = 'Email is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (empty($password)) {
        $error = 'Password is required.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } else {
        // Check if email already exists
        $checkEmailQuery = "SELECT admin_id FROM admin WHERE email = ?";
        $checkStmt = $conn->prepare($checkEmailQuery);
        $checkStmt->bind_param("s", $email);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        
        if ($checkResult->num_rows > 0) {
            $error = 'This email is already registered.';
        } else {
            // Hash password and insert new admin
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            
            $insertQuery = "INSERT INTO admin (first_name, last_name, email, password) VALUES (?, ?, ?, ?)";
            $insertStmt = $conn->prepare($insertQuery);
            $insertStmt->bind_param("ssss", $firstName, $lastName, $email, $hashedPassword);
            
            if ($insertStmt->execute()) {
                $_SESSION['msg'] = 'New administrator added successfully!';
                header('location:manage-admins.php');
                exit();
            } else {
                $error = 'Failed to add administrator. Please try again.';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Administrator</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/styles.css">
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

        .page-container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 0 2rem;
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
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .breadcrumb {
            margin-top: 0.5rem;
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .breadcrumb a {
            color: var(--gold);
            text-decoration: none;
        }

        .breadcrumb a:hover {
            text-decoration: underline;
        }

        .card {
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

        .alert-danger {
            background: #f8d7da;
            color: #842029;
            border-left: 4px solid #dc3545;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--navy);
            font-size: 0.95rem;
        }

        .form-group label i {
            margin-right: 0.5rem;
            color: var(--gold);
        }

        .form-group label .required {
            color: #dc3545;
            margin-left: 0.25rem;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
            font-family: 'Montserrat', sans-serif;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--navy);
            box-shadow: 0 0 0 3px rgba(0, 4, 53, 0.1);
        }

        .password-input-wrapper {
            position: relative;
        }

        .password-toggle {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #666;
            cursor: pointer;
            font-size: 1.1rem;
            padding: 0.25rem;
        }

        .password-toggle:hover {
            color: var(--navy);
        }

        .help-text {
            font-size: 0.85rem;
            color: #666;
            margin-top: 0.5rem;
        }

        .password-requirements {
            font-size: 0.85rem;
            color: #666;
            margin-top: 0.5rem;
        }

        .password-requirements ul {
            margin: 0.5rem 0;
            padding-left: 1.5rem;
        }

        .password-requirements li {
            margin: 0.25rem 0;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 1rem;
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

        .form-actions {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 2px solid #e0e0e0;
        }

        .info-box {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin-top: 2rem;
        }

        .info-box h3 {
            color: var(--navy);
            margin-top: 0;
            margin-bottom: 0.75rem;
            font-size: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .info-box p {
            margin: 0;
            font-size: 0.9rem;
            color: #333;
        }

        @media (max-width: 768px) {
            .page-container {
                padding: 0 1rem;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .form-actions {
                flex-direction: column;
            }

            .btn {
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
            <div>
                <h1><i class="fas fa-user-plus"></i> Add New Administrator</h1>
                <div class="breadcrumb">
                    <a href="manage-admins.php"><i class="fas fa-users-cog"></i> Manage Admins</a> / Add New Admin
                </div>
            </div>
        </div>

        <div class="card">
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="" id="addAdminForm">
                <div class="form-row">
                    <div class="form-group">
                        <label for="first_name">
                            <i class="fas fa-user"></i> First Name<span class="required">*</span>
                        </label>
                        <input 
                            type="text" 
                            class="form-control" 
                            id="first_name" 
                            name="first_name" 
                            value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="last_name">
                            <i class="fas fa-user"></i> Last Name<span class="required">*</span>
                        </label>
                        <input 
                            type="text" 
                            class="form-control" 
                            id="last_name" 
                            name="last_name" 
                            value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>"
                            required
                        >
                    </div>
                </div>

                <div class="form-group">
                    <label for="email">
                        <i class="fas fa-envelope"></i> Email Address<span class="required">*</span>
                    </label>
                    <input 
                        type="email" 
                        class="form-control" 
                        id="email" 
                        name="email" 
                        value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                        required
                    >
                    <div class="help-text">
                        <i class="fas fa-info-circle"></i> This email will be used for login
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">
                        <i class="fas fa-key"></i> Password<span class="required">*</span>
                    </label>
                    <div class="password-input-wrapper">
                        <input 
                            type="password" 
                            class="form-control" 
                            id="password" 
                            name="password" 
                            required
                            minlength="6"
                            autocomplete="new-password"
                        >
                        <button type="button" class="password-toggle" onclick="togglePassword('password')">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <div class="password-requirements">
                        <strong>Password requirements:</strong>
                        <ul>
                            <li>At least 6 characters long</li>
                            <li>Should contain a mix of letters and numbers</li>
                        </ul>
                    </div>
                </div>

                <div class="form-group">
                    <label for="confirm_password">
                        <i class="fas fa-check-circle"></i> Confirm Password<span class="required">*</span>
                    </label>
                    <div class="password-input-wrapper">
                        <input 
                            type="password" 
                            class="form-control" 
                            id="confirm_password" 
                            name="confirm_password" 
                            required
                            minlength="6"
                            autocomplete="new-password"
                        >
                        <button type="button" class="password-toggle" onclick="togglePassword('confirm_password')">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Create Administrator
                    </button>
                    <a href="manage-admins.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </form>

            <div class="info-box">
                <h3><i class="fas fa-shield-alt"></i> Security Notice</h3>
                <p>
                    The new administrator will have full access to the system. Make sure you create strong passwords 
                    and only grant admin access to trusted individuals. The new admin will be able to manage books, 
                    students, and issue/return operations.
                </p>
            </div>
        </div>
    </div>

    <script>
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const button = field.nextElementSibling;
            const icon = button.querySelector('i');
            
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

        // Form validation
        document.getElementById('addAdminForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match!');
                return false;
            }
            
            if (password.length < 6) {
                e.preventDefault();
                alert('Password must be at least 6 characters long!');
                return false;
            }
        });
    </script>
</body>
</html>