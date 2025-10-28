<?php
session_start();
include('../connection/db.php');
include('../security/crypt.php');
include('includes/logger.php');
include('includes/exceptions.php'); // Include custom exceptions
date_default_timezone_set('Asia/Manila');

$logger = new Logger();

// Check session
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

$validCourses = include('includes/courses.php');
$errors = [
    'firstName' => '', 'middleName' => '', 'lastName' => '',
    'email' => '', 'mobile' => '', 'course' => '', 'specialization' => ''
];
$studentData = [
    'student_id' => '', 'first_name' => '', 'middle_name' => '', 'last_name' => '',
    'email' => '', 'mobileno' => '', 'course' => '', 'specialization' => ''
];

// Validate and fetch student data
try {
    if (!isset($_GET['id']) || empty($_GET['id'])) {
        throw new InvalidStudentIdException("Invalid request: Student ID missing.");
    }

    $decryptedID = decrypt($_GET['id']);
    if (!$decryptedID) {
        throw new InvalidStudentIdException("Invalid student ID: Decryption failed.");
    }

    $stmt = $conn->prepare("SELECT * FROM students WHERE student_id = ?");
    if (!$stmt) {
        throw new DatabaseException("Failed to prepare statement for fetching student data.");
    }

    $stmt->bind_param("s", $decryptedID);
    if (!$stmt->execute()) {
        throw new DatabaseException("Failed to execute query for student data.");
    }

    $result = $stmt->get_result();
    if ($result->num_rows !== 1) {
        throw new InvalidStudentIdException("Student not found.");
    }

    $studentData = array_merge($studentData, $result->fetch_assoc());
    $studentData['mobileno'] = $studentData['mobile_no'];
    unset($studentData['mobile_no']);
} catch (InvalidStudentIdException $e) {
    $_SESSION['error'] = $e->getMessage();
    $logger->write("Student ID error: " . $e->getMessage());
    header('Location: reg-students.php');
    exit;
} catch (DatabaseException $e) {
    $_SESSION['error'] = "Database error occurred.";
    $logger->write("Database error: " . $e->getMessage());
    header('Location: reg-students.php');
    exit;
} catch (Exception $e) {
    $_SESSION['error'] = "An unexpected error occurred.";
    $logger->write("Unexpected error: " . $e->getMessage());
    header('Location: reg-students.php');
    exit;
} finally {
    if (isset($stmt)) {
        $stmt->close();
    }
}

function checkEmailExists($conn, $email, $student_id) {
    try {
        $stmt = $conn->prepare("SELECT * FROM students WHERE email = ? AND student_id != ?");
        if (!$stmt) {
            throw new DatabaseException("Failed to prepare statement for email check.");
        }

        $stmt->bind_param("ss", $email, $student_id);
        if (!$stmt->execute()) {
            throw new DatabaseException("Failed to execute email check query.");
        }

        return $stmt->get_result()->num_rows > 0;
    } catch (DatabaseException $e) {
        throw $e; 
    } finally {
        if (isset($stmt)) {
            $stmt->close();
        }
    }
}

function validateInput($data, $validCourses) {
    $errors = [];
    try {
        if (!is_array($validCourses)) {
            throw new ValidationException("Course configuration is invalid.");
        }
        if (!preg_match("/^[a-zA-Z\s'-]+$/u", $data['first_name'])) {
            $errors['firstName'] = "First name should only contain letters, spaces, apostrophes, or hyphens.";
        }
        if (!empty($data['middle_name']) && !preg_match("/^[a-zA-Z\s'-]+$/u", $data['middle_name'])) {
            $errors['middleName'] = "Middle name should only contain letters, spaces, apostrophes, or hyphens.";
        }
        if (!preg_match("/^[a-zA-Z\s'-]+$/u", $data['last_name'])) {
            $errors['lastName'] = "Last name should only contain letters, spaces, apostrophes, or hyphens.";
        }
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = "Invalid email format.";
        }
        if (!preg_match('/^09\d{9}$/', $data['mobileno'])) {
            $errors['mobile'] = "Mobile number must start with 09 and have 11 digits.";
        }
        if (!array_key_exists($data['course'], $validCourses)) {
            $errors['course'] = "Please select a valid course.";
        }
        if (!empty($validCourses[$data['course']]) && (empty($data['specialization']) || !in_array($data['specialization'], $validCourses[$data['course']]))) {
            $errors['specialization'] = "Please select a valid specialization for the chosen course.";
        }
        if (!empty($errors)) {
            throw new ValidationException("Input validation failed.");
        }
        return $errors;
    } catch (ValidationException $e) {
        return $errors; 
    }
}

if (isset($_POST['update'])) {
    try {
        $studentData = array_merge($studentData, [
            'first_name' => trim($_POST['first_name']),
            'middle_name' => trim($_POST['middle_name']),
            'last_name' => trim($_POST['last_name']),
            'email' => trim($_POST['email']),
            'mobileno' => trim($_POST['mobileno']),
            'course' => trim($_POST['course']),
            'specialization' => isset($_POST['specialization']) ? trim($_POST['specialization']) : null
        ]);

        $errors = validateInput($studentData, $validCourses);
        if (empty(array_filter($errors))) {
            if (checkEmailExists($conn, $studentData['email'], $studentData['student_id'])) {
                throw new EmailExistsException("Email already exists. Please use a different one.");
            }

            $stmt = $conn->prepare("UPDATE students SET first_name = ?, middle_name = ?, last_name = ?, email = ?, mobile_no = ?, course = ?, specialization = ? WHERE student_id = ?");
            if (!$stmt) {
                throw new DatabaseException("Failed to prepare statement for updating student data.");
            }

            $stmt->bind_param("ssssssss", $studentData['first_name'], $studentData['middle_name'], $studentData['last_name'], $studentData['email'], $studentData['mobileno'], $studentData['course'], $studentData['specialization'], $studentData['student_id']);
            
            if (!$stmt->execute()) {
                throw new DatabaseException("Failed to update student information.");
            }

            $_SESSION['updatemsg'] = "Student information updated successfully.";
            $logger->write("Student information updated: {$studentData['first_name']} {$studentData['last_name']}, Course: {$studentData['course']}, Specialization: " . ($studentData['specialization'] ?: 'None'));
            header('Location: reg-students.php');
            exit;
        } else {
            // Validation errors are already in $errors, will be displayed in the form
        }
    } catch (EmailExistsException $e) {
        $errors['email'] = $e->getMessage();
        $logger->write("Email error: " . $e->getMessage());
    } catch (DatabaseException $e) {
        $errors['email'] = "Database error occurred.";
        $logger->write("Database error: " . $e->getMessage());
    } catch (ValidationException $e) {
        // Validation errors are already handled in validateInput
        $logger->write("Validation error: " . $e->getMessage());
    } catch (Exception $e) {
        $errors['email'] = "An unexpected error occurred.";
        $logger->write("Unexpected error: " . $e->getMessage());
    } finally {
        if (isset($stmt)) {
            $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <title>Edit Student</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/styles.css">
</head>
<body style="font-family: 'Verdana', sans-serif;">
<?php include('includes/header.php'); ?>

<div class="signup-container">
    <h3>Student Information</h3>

    <form id="studentForm" method="POST">
        <div class="section-title">Personal Information</div>
        <div class="form-row">
            <div class="form-group">
                <label for="studentid">Student ID <span class="required">*</span></label>
                <input type="text" id="studentid" name="studentid" value="<?php echo htmlspecialchars($studentData['student_id']); ?>" readonly>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label for="first_name">First Name <span class="required">*</span></label>
                <input type="text" id="first_name" name="first_name" required
                    value="<?php echo htmlspecialchars($studentData['first_name']); ?>">
                <?php if (!empty($errors['firstName'])): ?>
                    <small class="error"><?php echo $errors['firstName']; ?></small>
                <?php endif; ?>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label for="middle_name">Middle Name</label>
                <input type="text" id="middle_name" name="middle_name"
                    value="<?php echo htmlspecialchars($studentData['middle_name']); ?>">
                <?php if (!empty($errors['middleName'])): ?>
                    <small class="error"><?php echo $errors['middleName']; ?></small>
                <?php endif; ?>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label for="last_name">Last Name <span class="required">*</span></label>
                <input type="text" id="last_name" name="last_name" required
                    value="<?php echo htmlspecialchars($studentData['last_name']); ?>">
                <?php if (!empty($errors['lastName'])): ?>
                    <small class="error"><?php echo $errors['lastName']; ?></small>
                <?php endif; ?>
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
                            <?php echo $studentData['course'] === $courseName ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($courseName); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (!empty($errors['course'])): ?>
                    <small class="error"><?php echo $errors['course']; ?></small>
                <?php endif; ?>
            </div>
            <div class="form-group">
                <label for="specialization">Specialization</label>
                <select id="specialization" name="specialization" disabled>
                    <option value="">Select a specialization</option>
                    <?php if (!empty($validCourses[$studentData['course']])): ?>
                        <?php foreach ($validCourses[$studentData['course']] as $spec): ?>
                            <option value="<?php echo htmlspecialchars($spec); ?>" 
                                <?php echo $studentData['specialization'] === $spec ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($spec); ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
                <?php if (!empty($errors['specialization'])): ?>
                    <small class="error"><?php echo $errors['specialization']; ?></small>
                <?php endif; ?>
            </div>
        </div>

        <div class="section-title">Contact Information</div>
        <div class="form-row">
            <div class="form-group">
                <label for="email">Email Address <span class="required">*</span></label>
                <input type="email" id="email" name="email" required
                    value="<?php echo htmlspecialchars($studentData['email']); ?>">
                <?php if (!empty($errors['email'])): ?>
                    <small class="error"><?php echo $errors['email']; ?></small>
                <?php endif; ?>
            </div>
            <div class="form-group">
                <label for="mobileno">Mobile Number <span class="required">*</span></label>
                <input type="tel" id="mobileno" name="mobileno" maxlength="11" required
                    value="<?php echo htmlspecialchars($studentData['mobileno']); ?>">
                <?php if (!empty($errors['mobile'])): ?>
                    <small class="error"><?php echo $errors['mobile']; ?></small>
                <?php endif; ?>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" name="update" class="btn">Update</button>
        </div>
    </form>
</div>

<script>
class FormModel {
    constructor(data) {
        this.data = { ...data };
        this.listeners = {};
    }

    get(property) {
        return this.data[property];
    }

    set(property, value) {
        this.data[property] = value;
        this.notify(property, value);
    }

    on(property, callback) {
        this.listeners[property] = this.listeners[property] || [];
        this.listeners[property].push(callback);
    }

    notify(property, value) {
        if (this.listeners[property]) {
            this.listeners[property].forEach(callback => callback(value));
        }
    }
}

const model = new FormModel(<?php echo json_encode($studentData); ?>);
const specializations = <?php echo json_encode($validCourses); ?>;

function bindInput(id, property) {
    const element = document.getElementById(id);
    element.value = model.get(property);
    model.on(property, value => element.value = value);
    element.addEventListener('input', () => {
        model.set(property, element.value);
        validateField(id);
    });
}

function validateField(id) {
    const element = document.getElementById(id);
    const errorEl = element.parentNode.querySelector('.error') || document.createElement('small');
    errorEl.className = 'error';

    const validations = {
        first_name: () => element.value && !/^[a-zA-Z\s'-]+$/.test(element.value) 
            ? 'Name should only contain letters, spaces, apostrophes, or hyphens.' : '',
        middle_name: () => element.value && !/^[a-zA-Z\s'-]+$/.test(element.value) 
            ? 'Name should only contain letters, spaces, apostrophes, or hyphens.' : '',
        last_name: () => element.value && !/^[a-zA-Z\s'-]+$/.test(element.value) 
            ? 'Name should only contain letters, spaces, apostrophes, or hyphens.' : '',
        email: () => element.value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(element.value) 
            ? 'Invalid email format.' : '',
        mobileno: () => element.value && !/^09\d{0,9}$/.test(element.value) 
            ? 'Mobile must start with 09 and be 11 digits.' : ''
    };

    const errorMessage = validations[id]?.() || '';
    if (errorMessage) {
        errorEl.textContent = errorMessage;
        if (!errorEl.parentNode) element.parentNode.appendChild(errorEl);
    } else if (errorEl.parentNode) {
        errorEl.parentNode.removeChild(errorEl);
    }
}

function setupCourseAndSpecialization() {
    const courseSelect = document.getElementById('course');
    const specializationSelect = document.getElementById('specialization');

    function updateSpecialization() {
        const course = model.get('course');
        specializationSelect.innerHTML = '<option value="">Select a specialization</option>';
        specializationSelect.disabled = !specializations[course]?.length;
        specializationSelect.required = !!specializations[course]?.length;

        if (specializations[course]?.length) {
            specializations[course].forEach(spec => {
                const option = document.createElement('option');
                option.value = spec;
                option.textContent = spec;
                if (model.get('specialization') === spec) option.selected = true;
                specializationSelect.appendChild(option);
            });
        } else {
            model.set('specialization', '');
        }
        validateSpecialization();
    }

    courseSelect.value = model.get('course');
    model.on('course', () => {
        courseSelect.value = model.get('course');
        updateSpecialization();
    });

    courseSelect.addEventListener('change', () => {
        model.set('course', courseSelect.value);
        validateCourse();
    });

    specializationSelect.addEventListener('change', () => {
        model.set('specialization', specializationSelect.value);
        validateSpecialization();
    });

    function validateCourse() {
        const errorEl = courseSelect.parentNode.querySelector('.error') || document.createElement('small');
        errorEl.className = 'error';
        if (!courseSelect.value) {
            errorEl.textContent = 'Please select a valid course.';
            if (!errorEl.parentNode) courseSelect.parentNode.appendChild(errorEl);
        } else if (errorEl.parentNode) {
            errorEl.parentNode.removeChild(errorEl);
        }
    }

    function validateSpecialization() {
        const errorEl = specializationSelect.parentNode.querySelector('.error') || document.createElement('small');
        errorEl.className = 'error';
        if (specializationSelect.required && !specializationSelect.value) {
            errorEl.textContent = 'Please select a valid specialization.';
            if (!errorEl.parentNode) specializationSelect.parentNode.appendChild(errorEl);
        } else if (errorEl.parentNode) {
            errorEl.parentNode.removeChild(errorEl);
        }
    }

    updateSpecialization();
}

document.addEventListener('DOMContentLoaded', () => {
    ['first_name', 'middle_name', 'last_name', 'email', 'mobileno'].forEach(field => bindInput(field, field));
    setupCourseAndSpecialization();

    document.getElementById('studentForm').addEventListener('submit', (e) => {
        ['first_name', 'middle_name', 'last_name', 'email', 'mobileno', 'course', 'specialization'].forEach(prop => {
            const input = document.querySelector(`[name="${prop}"]`);
            if (input) input.value = model.get(prop);
        });

        if (!model.get('course')) {
            e.preventDefault();
            alert('Please select a course.');
        }
        if (document.getElementById('specialization').required && !model.get('specialization')) {
            e.preventDefault();
            alert('Please select a specialization.');
        }
    });

    document.getElementById('email').addEventListener('blur', async () => {
        try {
            const email = document.getElementById('email').value;
            const response = await fetch('validate_email.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `email=${encodeURIComponent(email)}&studentid=${encodeURIComponent('<?php echo $studentData['student_id']; ?>')}`
            });
            const result = await response.json();
            const errorEl = document.getElementById('email').parentNode.querySelector('.error') || document.createElement('small');
            errorEl.className = 'error';
            if (result.exists) {
                errorEl.textContent = 'Email already exists.';
                if (!errorEl.parentNode) document.getElementById('email').parentNode.appendChild(errorEl);
            } else if (errorEl.parentNode) {
                errorEl.parentNode.removeChild(errorEl);
            }
        } catch (error) {
            console.error('Email validation error:', error);
            const errorEl = document.getElementById('email').parentNode.querySelector('.error') || document.createElement('small');
            errorEl.className = 'error';
            errorEl.textContent = 'Error validating email.';
            if (!errorEl.parentNode) document.getElementById('email').parentNode.appendChild(errorEl);
        }
    });
});
</script>

<style>
    .required { color: #dc3545; }
    select, input[type="text"], input[type="email"], input[type="tel"] { 
        width: 100%; 
        padding: 8px; 
        margin-top: 5px; 
        box-sizing: border-box;
    }
    .error { color: #dc3545; display: block; font-size: 0.8em; margin-top: 5px; }
</style>

</body>
</html>