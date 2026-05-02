<?php
session_start();
error_reporting(E_ALL);
include('../connection/db.php');

if (strlen($_SESSION['alogin']) == 0) {
    header('location:../index.php');
    exit();
}

$adminEmail = $_SESSION['alogin'];

// Fetch admin information
$adminQuery = "SELECT * FROM admin WHERE email = ?";
$stmt = $conn->prepare($adminQuery);
$stmt->bind_param("s", $adminEmail);
$stmt->execute();
$adminResult = $stmt->get_result();
$admin = $adminResult->fetch_assoc();

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');

    // Validation
    if (empty($firstName)) {
        $error = 'First name is required.';
    } elseif (empty($lastName)) {
        $error = 'Last name is required.';
    } elseif (empty($email)) {
        $error = 'Email is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        // Check if email already exists (for other admins)
        $checkEmailQuery = "SELECT admin_id FROM admin WHERE email = ? AND admin_id != ?";
        $checkStmt = $conn->prepare($checkEmailQuery);
        $checkStmt->bind_param("si", $email, $admin['admin_id']);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        
        if ($checkResult->num_rows > 0) {
            $error = 'This email is already in use by another admin.';
        } else {
            // Update admin profile
            $updateQuery = "UPDATE admin SET first_name = ?, last_name = ?, email = ? WHERE admin_id = ?";
            $updateStmt = $conn->prepare($updateQuery);
            $updateStmt->bind_param("sssi", $firstName, $lastName, $email, $admin['admin_id']);
            
            if ($updateStmt->execute()) {
                // Update session email if changed
                if ($email !== $adminEmail) {
                    $_SESSION['alogin'] = $email;
                }
                
                $success = 'Profile updated successfully!';
                
                // Refresh admin data
                $stmt->execute();
                $admin = $stmt->get_result()->fetch_assoc();
            } else {
                $error = 'Failed to update profile. Please try again.';
            }
        }
    }
}

// Build full name
$fullName = trim(($admin['first_name'] ?? '') . ' ' . ($admin['last_name'] ?? ''));
if (empty($fullName)) {
    $fullName = 'Administrator';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Admin Profile</title>
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
            max-width: 900px;
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

        .alert-success {
            background: #d1e7dd;
            color: #0f5132;
            border-left: 4px solid #198754;
        }

        .alert-danger {
            background: #f8d7da;
            color: #842029;
            border-left: 4px solid #dc3545;
        }

        .profile-image-section {
            text-align: center;
            margin-bottom: 2rem;
            padding-bottom: 2rem;
            border-bottom: 2px solid #e0e0e0;
        }

        .current-image {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid var(--gold);
            margin-bottom: 1rem;
        }

        .image-placeholder {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--navy), #001a52);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            border: 4px solid var(--gold);
        }

        .image-placeholder i {
            font-size: 4rem;
            color: var(--gold);
        }

        .image-upload-label {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            background: var(--navy);
            color: white;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .image-upload-label:hover {
            background: #001a52;
            transform: translateY(-2px);
        }

        .image-upload-label i {
            margin-right: 0.5rem;
        }

        #profile_image_input {
            display: none;
        }

        .image-preview {
            display: none;
            margin-top: 1rem;
            font-size: 0.9rem;
            color: #666;
        }

        .image-info {
            font-size: 0.85rem;
            color: #666;
            margin-top: 0.5rem;
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
            box-sizing: border-box;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--navy);
            box-shadow: 0 0 0 3px rgba(0, 4, 53, 0.1);
        }

        .form-control:disabled {
            background: #f8f9fa;
            color: #666;
            cursor: not-allowed;
        }

        .help-text {
            font-size: 0.85rem;
            color: #666;
            margin-top: 0.5rem;
        }

        /* Scoped button styles to avoid global styles.css conflicts */
        .form-actions .btn {
            padding: 0.75rem 1.5rem !important;
            border: none !important;
            border-radius: 8px !important;
            font-weight: 600 !important;
            cursor: pointer !important;
            transition: all 0.3s ease !important;
            text-decoration: none !important;
            display: inline-flex !important;
            align-items: center !important;
            gap: 0.5rem !important;
            font-size: 1rem !important;
            width: auto !important;
            max-width: none !important;
            box-sizing: border-box !important;
        }

        .form-actions .btn-primary {
            background: var(--navy) !important;
            color: white !important;
        }

        .form-actions .btn-primary:hover {
            background: #001a52 !important;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,.2);
        }

        .form-actions .btn-secondary {
            background: #6c757d !important;
            color: white !important;
        }

        .form-actions .btn-secondary:hover {
            background: #5a6268 !important;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,.2);
        }

        .btn-danger {
            background: #dc3545;
            color: white;
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

        .btn-danger:hover {
            background: #c82333;
        }

        .form-actions {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 2px solid #e0e0e0;
            flex-wrap: wrap;
        }

        .info-box {
            background: #e7f3ff;
            border-left: 4px solid #0d6efd;
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

        /* MOBILE */
        @media (max-width: 768px) {
            .page-container {
                padding: 0 1.25rem;
                margin: 1rem auto;
            }

            .page-header {
                padding: 1.25rem;
                border-radius: 10px;
                margin-bottom: 1.25rem;
            }

            .page-header h1 {
                font-size: 1.4rem;
            }

            .card {
                padding: 1.25rem;
                border-radius: 10px;
            }

            .form-row {
                grid-template-columns: 1fr;
                gap: 0;
            }

            .form-actions {
                flex-direction: column;
                gap: 0.75rem;
            }

            .form-actions .btn {
                width: 100% !important;
                justify-content: center !important;
            }
        }

        /* EXTRA SMALL */
        @media (max-width: 480px) {
            .page-container {
                padding: 0 1rem;
            }

            .page-header h1 {
                font-size: 1.2rem;
            }
        }
    </style>
</head>
<body>
    <?php include('includes/header.php'); ?>

    <div class="page-container">
        <div class="page-header">
            <div>
                <h1><i class="fas fa-user-edit"></i> Edit Profile</h1>
                <div class="breadcrumb">
                    <a href="admin-profile.php"><i class="fas fa-user-shield"></i> Profile</a> / Edit Profile
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

            <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <span><?= htmlspecialchars($success) ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="" id="editProfileForm">
                <!-- Form Fields -->
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
                            value="<?= htmlspecialchars($admin['first_name'] ?? '') ?>"
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
                            value="<?= htmlspecialchars($admin['last_name'] ?? '') ?>"
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
                        value="<?= htmlspecialchars($admin['email'] ?? '') ?>"
                        required
                    >
                    <div class="help-text">
                        <i class="fas fa-info-circle"></i> This email will be used for login
                    </div>
                </div>


                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                    <a href="admin-profile.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </form>

            <div class="info-box">
                <h3><i class="fas fa-info-circle"></i> Important Information</h3>
                <p>
                    <strong>Email Change:</strong> If you change your email address, you'll need to use the new email to log in next time.
                    Make sure you remember your new email address.<br><br>
                    <strong>Password:</strong> To change your password, please use the 
                    <a href="change-password.php" style="color: var(--navy); font-weight: 600;">Change Password</a> page.
                </p>
            </div>
        </div>
    </div>

    <script>
        function previewImage(input) {
            const preview = document.getElementById('imagePreview');
            const placeholder = document.getElementById('imagePlaceholder');
            const previewText = document.getElementById('imagePreviewText');
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                const file = input.files[0];
                
                // Validate file size (5MB)
                if (file.size > 5000000) {
                    alert('File size too large! Maximum 5MB allowed.');
                    input.value = '';
                    return;
                }
                
                // Validate file type
                const allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif'];
                if (!allowedTypes.includes(file.type)) {
                    alert('Invalid file type! Only JPG, PNG, and GIF are allowed.');
                    input.value = '';
                    return;
                }
                
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                    if (placeholder) {
                        placeholder.style.display = 'none';
                    }
                    previewText.style.display = 'block';
                    previewText.innerHTML = '<i class="fas fa-check-circle" style="color: #198754;"></i> New image selected: ' + file.name;
                };
                
                reader.readAsDataURL(file);
            }
        }

        function confirmDeleteImage() {
            if (confirm('Are you sure you want to remove your profile photo?')) {
                // Create a form to delete the image
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'delete-profile-image.php';
                document.body.appendChild(form);
                form.submit();
            }
        }

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

        // Form validation
        document.getElementById('editProfileForm').addEventListener('submit', function(e) {
            const firstName = document.getElementById('first_name').value.trim();
            const lastName = document.getElementById('last_name').value.trim();
            const email = document.getElementById('email').value.trim();
            
            if (!firstName || !lastName || !email) {
                e.preventDefault();
                alert('Please fill in all required fields.');
                return false;
            }
            
            if (!email.match(/^[^\s@]+@[^\s@]+\.[^\s@]+$/)) {
                e.preventDefault();
                alert('Please enter a valid email address.');
                return false;
            }
        });
    </script>
</body>
</html>