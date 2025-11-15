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

// Define valid courses and year levels
$validCourses = include('admin/includes/courses.php');
$validYearLevels = ['1st Year', '2nd Year', '3rd Year', '4th Year'];

// Fetch current student data
$stmt = $conn->prepare("SELECT * FROM students WHERE student_id = ?");
$stmt->bind_param("s", $studentID);
$stmt->execute();
$result = $stmt->get_result();
$student = $result->fetch_assoc();

// Handle form submission
if (isset($_POST['update_profile'])) {
    $firstName = trim($_POST['first_name']);
    $middleName = trim($_POST['middle_name']);
    $lastName = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $mobile = trim($_POST['mobile_no']);
    $course = trim($_POST['course']);
    $specialization = isset($_POST['specialization']) ? trim($_POST['specialization']) : null;
    $yearLevel = trim($_POST['year_level']);

    try {
        // Validation
        if (empty($firstName) || empty($lastName) || empty($email) || empty($mobile) || empty($course) || empty($yearLevel)) {
            throw new Exception("Please fill in all required fields.");
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid email format.");
        }

        if (!preg_match('/^09\d{9}$/', $mobile)) {
            throw new Exception("Mobile number must start with 09 and have 11 digits.");
        }

        if (!preg_match("/^[a-zA-Z\s'-]+$/u", $firstName) || !preg_match("/^[a-zA-Z\s'-]+$/u", $lastName)) {
            throw new Exception("Names should only contain letters, spaces, apostrophes, or hyphens.");
        }

        if (!empty($middleName) && !preg_match("/^[a-zA-Z\s'-]+$/u", $middleName)) {
            throw new Exception("Middle name should only contain letters, spaces, apostrophes, or hyphens.");
        }

        if (!array_key_exists($course, $validCourses)) {
            throw new Exception("Please select a valid course.");
        }

        if (!in_array($yearLevel, $validYearLevels)) {
            throw new Exception("Please select a valid year level.");
        }

        // Check if email is already used by another student
        if ($email !== $student['email']) {
            $checkEmail = $conn->prepare("SELECT student_id FROM students WHERE email = ? AND student_id != ?");
            $checkEmail->bind_param("ss", $email, $studentID);
            $checkEmail->execute();
            if ($checkEmail->get_result()->num_rows > 0) {
                throw new Exception("Email is already in use by another account.");
            }
        }

        // Handle profile image upload
        $profileImage = $student['profile_image'];
        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
            $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            $fileType = $_FILES['profile_image']['type'];
            $fileSize = $_FILES['profile_image']['size'];
            
            if (!in_array($fileType, $allowedTypes)) {
                throw new Exception("Only JPG, PNG, and GIF images are allowed.");
            }
            
            if ($fileSize > 5 * 1024 * 1024) {
                throw new Exception("Image size must not exceed 5MB.");
            }

            // Delete old image if exists
            if (!empty($student['profile_image']) && file_exists("admin/uploads/students/" . $student['profile_image'])) {
                unlink("admin/uploads/students/" . $student['profile_image']);
            }

            // Upload new image
            $ext = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
            $profileImage = 'student_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $targetDir = "admin/uploads/students/";
            
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0755, true);
            }
            
            $targetFilePath = $targetDir . $profileImage;
            if (!move_uploaded_file($_FILES['profile_image']['tmp_name'], $targetFilePath)) {
                throw new Exception("Failed to upload profile image.");
            }
        }

        // Update database
        $updateStmt = $conn->prepare("
            UPDATE students 
            SET first_name = ?, middle_name = ?, last_name = ?, email = ?, 
                mobile_no = ?, course = ?, specialization = ?, year_level = ?, 
                profile_image = ?, update_date = NOW()
            WHERE student_id = ?
        ");
        
        $updateStmt->bind_param(
            "ssssssssss",
            $firstName,
            $middleName,
            $lastName,
            $email,
            $mobile,
            $course,
            $specialization,
            $yearLevel,
            $profileImage,
            $studentID
        );

        if ($updateStmt->execute()) {
            $success = "Profile updated successfully!";
            $_SESSION['login'] = $firstName; // Update session
            $logger->write("Profile updated for student ID: $studentID");
            
            // Refresh student data
            $stmt = $conn->prepare("SELECT * FROM students WHERE student_id = ?");
            $stmt->bind_param("s", $studentID);
            $stmt->execute();
            $result = $stmt->get_result();
            $student = $result->fetch_assoc();
        } else {
            throw new Exception("Failed to update profile. Please try again.");
        }

    } catch (Exception $e) {
        $error = $e->getMessage();
        $logger->write("Profile update error for student ID $studentID: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile</title>
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
            max-width: 900px;
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

        .section-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--navy);
            margin: 2rem 0 1rem 0;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--gold);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .section-title:first-child {
            margin-top: 0;
        }

        .profile-photo-section {
            text-align: center;
            margin-bottom: 2rem;
            padding: 2rem;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .current-photo {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid var(--gold);
            margin-bottom: 1rem;
        }

        .photo-placeholder {
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

        .photo-placeholder i {
            font-size: 4rem;
            color: var(--gold);
        }

        .file-input-wrapper {
            position: relative;
            display: inline-block;
            cursor: pointer;
        }

        .file-input-wrapper input[type="file"] {
            position: absolute;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }

        .file-input-label {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            background: var(--navy);
            color: white;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .file-input-label:hover {
            background: #001a52;
            transform: translateY(-2px);
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
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

        .required {
            color: #dc3545;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
            font-family: 'Montserrat', sans-serif;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--navy);
            box-shadow: 0 0 0 3px rgba(0, 4, 53, 0.1);
        }

        .form-group small {
            display: block;
            margin-top: 0.25rem;
            color: #666;
            font-size: 0.85rem;
        }

        .form-group small.error {
            color: #dc3545;
        }

        .info-note {
            background: #e7f3ff;
            border-left: 4px solid #0066cc;
            padding: 1rem;
            margin: 1.5rem 0;
            border-radius: 4px;
            font-size: 0.9rem;
            color: #003d7a;
        }

        .form-actions {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 2px solid #e0e0e0;
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

        .readonly-field {
            background: #f8f9fa;
            color: #666;
            cursor: not-allowed;
        }

        #imagePreview {
            margin-top: 1rem;
            display: none;
        }

        #previewImg {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid var(--gold);
        }

        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }

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
            <h1><i class="fas fa-user-edit"></i> Edit Profile</h1>
            <p>Update your personal information</p>
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

            <form method="POST" enctype="multipart/form-data" id="editProfileForm">
                <!-- Profile Photo Section -->
                <div class="section-title">
                    <i class="fas fa-camera"></i> Profile Photo
                </div>

                <div class="profile-photo-section">
                    <?php if (!empty($student['profile_image']) && file_exists('admin/uploads/students/' . $student['profile_image'])): ?>
                        <img src="admin/uploads/students/<?= htmlspecialchars($student['profile_image']) ?>" 
                             alt="Current Photo" class="current-photo" id="currentPhoto">
                    <?php else: ?>
                        <div class="photo-placeholder" id="photoPlaceholder">
                            <i class="fas fa-user"></i>
                        </div>
                    <?php endif; ?>

                    <div id="imagePreview">
                        <img src="" alt="Preview" id="previewImg">
                    </div>
                    <br>

                    <div class="file-input-wrapper">
                        <label class="file-input-label">
                            <i class="fas fa-upload"></i> Choose New Photo
                            <input type="file" name="profile_image" id="profile_image" accept="image/*">
                        </label>
                    </div>
                    <small style="display: block; margin-top: 0.5rem; color: #666;">
                        Maximum file size: 5MB. Accepted formats: JPG, PNG, GIF
                    </small>
                </div>

                <!-- Personal Information -->
                <div class="section-title">
                    <i class="fas fa-user"></i> Personal Information
                </div>

                <div class="form-group">
                    <label>
                        <i class="fas fa-id-card"></i>
                        Student ID
                    </label>
                    <input type="text" value="<?= htmlspecialchars($student['student_id']) ?>" readonly class="readonly-field">
                    <small>Student ID cannot be changed</small>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="first_name">
                            First Name <span class="required">*</span>
                        </label>
                        <input type="text" id="first_name" name="first_name" 
                               value="<?= htmlspecialchars($student['first_name']) ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="middle_name">
                            Middle Name
                        </label>
                        <input type="text" id="middle_name" name="middle_name" 
                               value="<?= htmlspecialchars($student['middle_name']) ?>">
                    </div>

                    <div class="form-group">
                        <label for="last_name">
                            Last Name <span class="required">*</span>
                        </label>
                        <input type="text" id="last_name" name="last_name" 
                               value="<?= htmlspecialchars($student['last_name']) ?>" required>
                    </div>
                </div>

                <!-- Contact Information -->
                <div class="section-title">
                    <i class="fas fa-address-book"></i> Contact Information
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="email">
                            <i class="fas fa-envelope"></i>
                            Email Address <span class="required">*</span>
                        </label>
                        <input type="email" id="email" name="email" 
                               value="<?= htmlspecialchars($student['email']) ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="mobile_no">
                            <i class="fas fa-phone"></i>
                            Mobile Number <span class="required">*</span>
                        </label>
                        <input type="tel" id="mobile_no" name="mobile_no" maxlength="11"
                               value="<?= htmlspecialchars($student['mobile_no']) ?>" required>
                        <small>Format: 09XXXXXXXXX</small>
                    </div>
                </div>

                <!-- Academic Information -->
                <div class="section-title">
                    <i class="fas fa-graduation-cap"></i> Academic Information
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="course">
                            Course <span class="required">*</span>
                        </label>
                        <select id="course" name="course" required>
                            <option value="">Select a course</option>
                            <?php foreach ($validCourses as $courseName => $specializations): ?>
                                <option value="<?= htmlspecialchars($courseName) ?>"
                                    <?= $student['course'] == $courseName ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($courseName) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="specialization">
                            Specialization
                        </label>
                        <select id="specialization" name="specialization">
                            <option value="">Select a specialization</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="year_level">
                            Year Level <span class="required">*</span>
                        </label>
                        <select id="year_level" name="year_level" required>
                            <option value="">Select year level</option>
                            <?php foreach ($validYearLevels as $year): ?>
                                <option value="<?= htmlspecialchars($year) ?>"
                                    <?= $student['year_level'] == $year ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($year) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="info-note">
                    <i class="fas fa-info-circle"></i>
                    <strong>Note:</strong> Make sure all information is accurate. Changes will be reflected in your library account immediately.
                </div>

                <div class="form-actions">
                    <button type="submit" name="update_profile" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                    <a href="profile.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Course and specialization handler
        const specializations = <?php echo json_encode($validCourses); ?>;
        const course = document.getElementById('course');
        const specialization = document.getElementById('specialization');
        const currentSpecialization = <?php echo json_encode($student['specialization']); ?>;

        course.addEventListener('change', function() {
            const selectedCourse = this.value;
            specialization.innerHTML = '<option value="">Select a specialization</option>';
            specialization.disabled = true;

            if (specializations[selectedCourse] && specializations[selectedCourse].length > 0) {
                specialization.disabled = false;
                specializations[selectedCourse].forEach(function(spec) {
                    const option = document.createElement('option');
                    option.value = spec;
                    option.textContent = spec;
                    if (spec === currentSpecialization) {
                        option.selected = true;
                    }
                    specialization.appendChild(option);
                });
                specialization.required = true;
            } else {
                specialization.required = false;
            }
        });

        // Trigger on page load
        if (course.value) {
            course.dispatchEvent(new Event('change'));
        }

        // Image preview
        const profileImage = document.getElementById('profile_image');
        const imagePreview = document.getElementById('imagePreview');
        const previewImg = document.getElementById('previewImg');
        const currentPhoto = document.getElementById('currentPhoto');
        const photoPlaceholder = document.getElementById('photoPlaceholder');

        profileImage.addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewImg.src = e.target.result;
                    imagePreview.style.display = 'block';
                    if (currentPhoto) currentPhoto.style.display = 'none';
                    if (photoPlaceholder) photoPlaceholder.style.display = 'none';
                }
                reader.readAsDataURL(file);
            }
        });

        // Mobile number validation
        const mobile = document.getElementById('mobile_no');
        mobile.addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '');
        });

        // Name validation
        ['first_name', 'middle_name', 'last_name'].forEach(function(id) {
            const input = document.getElementById(id);
            input.addEventListener('input', function() {
                this.value = this.value.replace(/[^a-zA-Z\s'-]/g, '');
            });
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