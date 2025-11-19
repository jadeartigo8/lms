<?php
session_start();
include('../connection/db.php');
include('includes/logger.php');
include('includes/exceptions.php');
date_default_timezone_set('Asia/Manila');

$logger = new Logger();

// Session validation
try {
    if (empty($_SESSION['alogin'])) {
        throw new SessionException("User not logged in.");
    }
} catch (SessionException $e) {
    $_SESSION['error'] = $e->getMessage();
    $logger->write("Session error: " . $e->getMessage());
    header('Location: ../index.php');
    exit;
}

$emailError = "";
$passwordError = "";
$mobileError = "";
$firstNameError = "";
$middleNameError = "";
$lastNameError = "";
$studentiderror = "";
$courseError = "";
$specializationError = "";
$yearLevelError = "";
$photoError = "";

// Define valid courses and their specializations
$validCourses = include('includes/courses.php');

// Define valid year levels
$validYearLevels = ['1st Year', '2nd Year', '3rd Year', '4th Year'];

function checkEmailExists($conn, $email) {
    try {
        $stmt = $conn->prepare("SELECT * FROM students WHERE email = ?");
        if (!$stmt) {
            throw new DatabaseException("Statement preparation failed: " . $conn->error);
        }
        $stmt->bind_param("s", $email);
        if (!$stmt->execute()) {
            throw new DatabaseException("Failed to execute email check query.");
        }
        $result = $stmt->get_result();
        return $result->num_rows > 0;
    } catch (DatabaseException $e) {
        throw $e;
    } finally {
        if (isset($stmt)) {
            $stmt->close();
        }
    }
}

function checkStudentIdExists($conn, $studentid) {
    try {
        $stmt = $conn->prepare("SELECT * FROM students WHERE student_id = ?");
        if (!$stmt) {
            throw new DatabaseException("Statement preparation failed: " . $conn->error);
        }
        $stmt->bind_param("s", $studentid);
        if (!$stmt->execute()) {
            throw new DatabaseException("Failed to execute student ID check query.");
        }
        $result = $stmt->get_result();
        return $result->num_rows > 0;
    } catch (DatabaseException $e) {
        throw $e;
    } finally {
        if (isset($stmt)) {
            $stmt->close();
        }
    }
}

function registerStudent($conn, $studentid, $fname, $mname, $lname, $email, $mobile, $password, $course, $specialization, $yearLevel, $profileImage, $status, $regDate) {
    try {
        $stmt = $conn->prepare("INSERT INTO students (student_id, first_name, middle_name, last_name, email, mobile_no, password, course, specialization, year_level, profile_image, status, registration_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if (!$stmt) {
            throw new DatabaseException("Statement preparation failed: " . $conn->error);
        }
        $stmt->bind_param("sssssssssssss", $studentid, $fname, $mname, $lname, $email, $mobile, $password, $course, $specialization, $yearLevel, $profileImage, $status, $regDate);
        if (!$stmt->execute()) {
            throw new DatabaseException("Failed to register student.");
        }
        return true;
    } catch (DatabaseException $e) {
        throw $e;
    } finally {
        if (isset($stmt)) {
            $stmt->close();
        }
    }
}

function isValidMobileNumber($mobile) {
    return preg_match('/^09\d{9}$/', $mobile);
}

function isValidName($name) {
    return preg_match("/^[a-zA-Z\s'-]+$/u", $name);
}

function validateInput($data, $validCourses, $validYearLevels) {
    $errors = [];
    try {
        if (!preg_match('/^\d{7}-\d{1}$/', $data['studentid'])) {
            $errors['studentid'] = "Student ID must follow the format NNNNNNN-N (e.g., 2310074-1).";
        }
        if (!isValidName($data['first_name'])) {
            $errors['firstName'] = "First name should only contain letters, spaces, apostrophes, or hyphens.";
        }
        if (!empty($data['middle_name']) && !isValidName($data['middle_name'])) {
            $errors['middleName'] = "Middle name should only contain letters, spaces, apostrophes, or hyphens.";
        }
        if (!isValidName($data['last_name'])) {
            $errors['lastName'] = "Last name should only contain letters, spaces, apostrophes, or hyphens.";
        }
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = "Invalid email format.";
        }
        if (!isValidMobileNumber($data['mobileno'])) {
            $errors['mobile'] = "Mobile number must start with 09 and have 11 digits.";
        }
        if (!array_key_exists($data['course'], $validCourses)) {
            $errors['course'] = "Please select a valid course.";
        }
        if (!empty($validCourses[$data['course']]) && (empty($data['specialization']) || !in_array($data['specialization'], $validCourses[$data['course']]))) {
            $errors['specialization'] = "Please select a valid specialization for the chosen course.";
        }
        if (empty($data['year_level']) || !in_array($data['year_level'], $validYearLevels)) {
            $errors['yearLevel'] = "Please select a valid year level.";
        }
        if ($data['password'] !== $data['confirmPassword']) {
            $errors['password'] = "Passwords do not match.";
        }
        if (!empty($errors)) {
            throw new ValidationException("Input validation failed.");
        }
        return $errors;
    } catch (ValidationException $e) {
        return $errors;
    }
}

if (isset($_POST['signup'])) {
    try {
        // Sanitize inputs
        $inputData = [
            'studentid' => trim($_POST['studentid']),
            'first_name' => trim($_POST['first_name']),
            'middle_name' => trim($_POST['middle_name']),
            'last_name' => trim($_POST['last_name']),
            'mobileno' => trim($_POST['mobileno']),
            'email' => trim($_POST['email']),
            'password' => $_POST['password'],
            'confirmPassword' => $_POST['confirmpassword'],
            'course' => trim($_POST['course']),
            'specialization' => isset($_POST['specialization']) ? trim($_POST['specialization']) : null,
            'year_level' => trim($_POST['year_level'] ?? '')
        ];

        // Handle profile image upload
        $profileImageName = null;
        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
            $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            $fileType = $_FILES['profile_image']['type'];
            $fileSize = $_FILES['profile_image']['size'];
            
            if (!in_array($fileType, $allowedTypes)) {
                $photoError = "Only JPG, PNG, and GIF images are allowed.";
            } elseif ($fileSize > 5 * 1024 * 1024) { // 5MB limit
                $photoError = "Image size must not exceed 5MB.";
            } else {
                $ext = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
                $profileImageName = 'student_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                $targetDir = "uploads/students/";
                if (!is_dir($targetDir)) {
                    mkdir($targetDir, 0755, true);
                }
                $targetFilePath = $targetDir . $profileImageName;
                if (!move_uploaded_file($_FILES['profile_image']['tmp_name'], $targetFilePath)) {
                    $photoError = "Failed to upload profile image.";
                    $profileImageName = null;
                }
            }
        }

        // Validate inputs
        $errors = validateInput($inputData, $validCourses, $validYearLevels);
        $hasErrors = !empty(array_filter($errors)) || !empty($photoError);

        if (!$hasErrors) {
            // Check for duplicates
            if (checkEmailExists($conn, $inputData['email'])) {
                throw new EmailExistsException("Email already exists. Please use a different one.");
            }
            else if (checkStudentIdExists($conn, $inputData['studentid'])) {
                throw new InvalidStudentIdException("Student ID already exists. Please use a different one.");
            }

            // Register student
            $hashed_password = password_hash($inputData['password'], PASSWORD_DEFAULT);
            $status = 1;
            $registration_date = date("Y-m-d H:i:s");
            $success = registerStudent(
                $conn,
                $inputData['studentid'],
                $inputData['first_name'],
                $inputData['middle_name'],
                $inputData['last_name'],
                $inputData['email'],
                $inputData['mobileno'],
                $hashed_password,
                $inputData['course'],
                $inputData['specialization'],
                $inputData['year_level'],
                $profileImageName,
                $status,
                $registration_date
            );

            if ($success) {
                $_SESSION['successmsg'] = "Your registration was successful!";
                $logger->write("Student added: {$inputData['first_name']} {$inputData['last_name']}, Course: {$inputData['course']}, Year: {$inputData['year_level']}");
                $_POST = array();
                header('Location: reg-students.php');
                exit;
            } else {
                throw new DatabaseException("Failed to register student.");
            }
        }
    } catch (EmailExistsException $e) {
        $emailError = $e->getMessage();
        $logger->write("Email error: " . $e->getMessage());
    } catch (InvalidStudentIdException $e) {
        $studentiderror = $e->getMessage();
        $logger->write("Student ID error: " . $e->getMessage());
    } catch (DatabaseException $e) {
        $emailError = "Database error occurred.";
        $logger->write("Database error: " . $e->getMessage());
    } catch (ValidationException $e) {
        $logger->write("Validation error: " . $e->getMessage());
    } catch (Exception $e) {
        $emailError = "An unexpected error occurred.";
        $logger->write("Unexpected error: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
    <meta name="description" content="" />
    <meta name="author" content="" />
    <title>User Signup</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/styles.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700&display=swap" rel="stylesheet">
    <style>
        .image-preview { margin-top: 10px; max-width: 200px; border-radius: 8px; display: none; }
        .image-preview.show { display: block; }
        .image-preview img { width: 100%; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,.1); }
    </style>
</head>
<body>
<?php include('includes/header.php'); ?>

<div class="signup-container">
    <h3>Student Signup Form</h3>

    <form name="signup" method="POST" enctype="multipart/form-data" id="signupForm">
        <div class="section-title">Personal Information</div>
        <div class="form-group">
            <label for="studentid">Student ID <span class="required">*</span></label>
            <input type="text" id="studentid" name="studentid" maxlength="9" required
                   placeholder="e.g., 2310074-1"
                   value="<?php echo isset($_POST['studentid']) ? htmlspecialchars($_POST['studentid']) : ''; ?>">
            <?php if (!empty($studentiderror)): ?>
                <small class="error"><?php echo $studentiderror; ?></small>
            <?php endif; ?>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="first_name">First Name <span class="required">*</span></label>
                <input type="text" id="first_name" name="first_name" required
                       placeholder="Enter your first name"
                       value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>">
                <?php if (!empty($firstNameError)): ?>
                    <small class="error"><?php echo $firstNameError; ?></small>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="middle_name">Middle Name</label>
                <input type="text" id="middle_name" name="middle_name"
                       placeholder="Enter your middle name (optional)"
                       value="<?php echo isset($_POST['middle_name']) ? htmlspecialchars($_POST['middle_name']) : ''; ?>">
                <?php if (!empty($middleNameError)): ?>
                    <small class="error"><?php echo $middleNameError; ?></small>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="last_name">Last Name <span class="required">*</span></label>
                <input type="text" id="last_name" name="last_name" required
                       placeholder="Enter your last name"
                       value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>">
                <?php if (!empty($lastNameError)): ?>
                    <small class="error"><?php echo $lastNameError; ?></small>
                <?php endif; ?>
            </div>
        </div>

        <div class="form-group">
            <label for="profile_image">Profile Photo (Optional)</label>
            <input type="file" id="profile_image" name="profile_image" accept="image/*">
            <?php if (!empty($photoError)): ?>
                <small class="error"><?php echo $photoError; ?></small>
            <?php endif; ?>
            <div class="image-preview" id="imagePreview">
                <img src="" alt="Profile Preview" id="previewImg">
            </div>
        </div>

        <div class="section-title">Academic Information</div>
        <div class="form-row">
            <div class="form-group">
                <label for="course">Course <span class="required">*</span></label>
                <select id="course" name="course" required>
                    <option value="">Select a course</option>
                    <?php foreach ($validCourses as $courseName => $specializations): ?>
                        <option value="<?php echo htmlspecialchars($courseName); ?>"
                            <?php echo isset($_POST['course']) && $_POST['course'] == $courseName ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($courseName); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (!empty($courseError)): ?>
                    <small class="error"><?php echo $courseError; ?></small>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="specialization">Specialization</label>
                <select id="specialization" name="specialization" disabled>
                    <option value="">Select a specialization</option>
                </select>
                <?php if (!empty($specializationError)): ?>
                    <small class="error"><?php echo $specializationError; ?></small>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="year_level">Year Level <span class="required">*</span></label>
                <select id="year_level" name="year_level" required>
                    <option value="">Select year level</option>
                    <?php foreach ($validYearLevels as $year): ?>
                        <option value="<?php echo htmlspecialchars($year); ?>"
                            <?php echo isset($_POST['year_level']) && $_POST['year_level'] == $year ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($year); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (!empty($yearLevelError)): ?>
                    <small class="error"><?php echo $yearLevelError; ?></small>
                <?php endif; ?>
            </div>
        </div>

        <div class="section-title">Contact Information</div>
        <div class="form-row">
            <div class="form-group">
                <label for="mobileno">Mobile Number <span class="required">*</span></label>
                <input type="tel" id="mobileno" name="mobileno" maxlength="11" required
                       placeholder="e.g., 09123456789"
                       value="<?php echo isset($_POST['mobileno']) ? htmlspecialchars($_POST['mobileno']) : ''; ?>">
                <?php if (!empty($mobileError)): ?>
                    <small class="error"><?php echo $mobileError; ?></small>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="email">Email Address <span class="required">*</span></label>
                <input type="email" id="email" name="email" required
                       placeholder="your.email@example.com"
                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                <?php if (!empty($emailError)): ?>
                    <small class="error"><?php echo $emailError; ?></small>
                <?php endif; ?>
            </div>
        </div>

        <div class="section-title">Password</div>
        <div class="form-row">
            <div class="form-group">
                <label for="password">Password <span class="required">*</span></label>
                <input type="password" id="password" name="password" required
                       placeholder="Enter a strong password">
            </div>

            <div class="form-group">
                <label for="confirmpassword">Confirm Password <span class="required">*</span></label>
                <input type="password" id="confirmpassword" name="confirmpassword" required
                       placeholder="Confirm your password">
                <?php if (!empty($passwordError)): ?>
                    <small class="error"><?php echo $passwordError; ?></small>
                <?php endif; ?>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" name="signup" class="btn" id="submitBtn">Register Now</button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const password = document.getElementById('password');
    const confirmPassword = document.getElementById('confirmpassword');
    const submitBtn = document.getElementById('submitBtn');
    const form = document.getElementById('signupForm');
    const course = document.getElementById('course');
    const specialization = document.getElementById('specialization');
    const profileImage = document.getElementById('profile_image');
    const imagePreview = document.getElementById('imagePreview');
    const previewImg = document.getElementById('previewImg');

    // Image preview
    profileImage.addEventListener('change', function() {
        const file = this.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                previewImg.src = e.target.result;
                imagePreview.classList.add('show');
            }
            reader.readAsDataURL(file);
        } else {
            imagePreview.classList.remove('show');
        }
    });

    // Define specializations for each course
    const specializations = <?php echo json_encode($validCourses); ?>;

    // Update specialization dropdown based on course selection
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
                if (<?php echo json_encode(isset($_POST['specialization']) ? $_POST['specialization'] : ''); ?> === spec) {
                    option.selected = true;
                }
                specialization.appendChild(option);
            });
            specialization.required = true;
        } else {
            specialization.required = false;
        }
    });

    // Trigger course change on page load
    if (course.value) {
        course.dispatchEvent(new Event('change'));
    }

    // Password match validation
    function checkPasswordMatch() {
        const passError = document.querySelector('#confirmpassword + .error') || document.createElement('small');
        if (confirmPassword.value && password.value !== confirmPassword.value) {
            passError.textContent = 'Passwords do not match.';
            passError.className = 'error';
            if (!passError.parentNode) confirmPassword.parentNode.appendChild(passError);
        } else {
            if (passError.parentNode) passError.parentNode.removeChild(passError);
        }
    }

    password.addEventListener('input', checkPasswordMatch);
    confirmPassword.addEventListener('input', checkPasswordMatch);

    checkPasswordMatch();

    // Form submission validation
    form.addEventListener('submit', function(e) {
        try {
            if (password.value !== confirmPassword.value) {
                e.preventDefault();
                alert('Passwords do not match. Please try again.');
            }
            if (!course.value) {
                e.preventDefault();
                alert('Please select a course.');
            }
            if (specialization.required && !specialization.value) {
                e.preventDefault();
                alert('Please select a specialization.');
            }
        } catch (error) {
            console.error('Form submission error:', error);
            e.preventDefault();
            alert('An error occurred during form validation.');
        }
    });

    // Real-time mobile number validation
    const mobile = document.getElementById('mobileno');
    mobile.addEventListener('input', function() {
        const mobileError = this.parentNode.querySelector('.error');
        if (this.value && !/^09\d{0,9}$/.test(this.value)) {
            if (!mobileError) {
                const errorEl = document.createElement('small');
                errorEl.className = 'error';
                errorEl.textContent = 'Mobile must start with 09 and be 11 digits.';
                this.parentNode.appendChild(errorEl);
            }
        } else if (mobileError) {
            mobileError.remove();
        }
    });

    // Real-time name validation
    ['first_name', 'middle_name', 'last_name'].forEach(function(id) {
        const input = document.getElementById(id);
        input.addEventListener('input', function() {
            const nameError = this.parentNode.querySelector('.error');
            if (this.value && !/^[a-zA-Z\s'-]+$/.test(this.value)) {
                if (!nameError) {
                    const errorEl = document.createElement('small');
                    errorEl.className = 'error';
                    errorEl.textContent = 'Name should only contain letters, spaces, apostrophes, or hyphens.';
                    this.parentNode.appendChild(errorEl);
                }
            } else if (nameError) {
                nameError.remove();
            }
        });
    });

    // Student ID format validation
    const studentId = document.getElementById('studentid');
    studentId.addEventListener('input', function() {
        const idError = this.parentNode.querySelector('.error');
        if (this.value && !/^\d{0,7}-\d{0,1}$/.test(this.value)) {
            if (!idError) {
                const errorEl = document.createElement('small');
                errorEl.className = 'error';
                errorEl.textContent = 'Student ID must follow the format NNNNNNN-N.';
                this.parentNode.appendChild(errorEl);
            }
        } else if (idError) {
            idError.remove();
        }
    });
});
</script>

<style>
    .required { color: #dc3545; }
    a { color: #007bff; text-decoration: none; }
    a:hover { text-decoration: underline; }
    select { width: 100%; padding: 8px; margin-top: 5px; }
</style>

</body>
</html>