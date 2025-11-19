<?php
session_start();
include('connection/db.php');
include('admin/includes/logger.php');
include('admin/includes/exceptions.php');
date_default_timezone_set('Asia/Manila');

$logger = new Logger();

// Clear session on page load
try {
    if (isset($_SESSION['alogin']) && $_SESSION['alogin'] != '') {
        $_SESSION['alogin'] = '';
        $_SESSION['admin_id'] = '';
        session_regenerate_id(true);
        $logger->write("Admin session cleared on login page load.");
    }
} catch (Exception $e) {
    $logger->write("Session error on logout: " . $e->getMessage());
    $_SESSION['error'] = "Session error occurred.";
    header('Location: index.php');
    exit;
}

function validateInput($email, $password) {
    try {
        // Sanitize email
        $email = filter_var(trim($email), FILTER_SANITIZE_EMAIL);
        
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new ValidationException("Invalid or missing email address.");
        }
        if (empty($password)) {
            throw new ValidationException("Password is required.");
        }
        if (strlen($password) < 6) {
            throw new ValidationException("Password must be at least 6 characters.");
        }
        // Check for suspicious patterns
        if (strlen($email) > 100) {
            throw new ValidationException("Email address is too long.");
        }
        if (strlen($password) > 255) {
            throw new ValidationException("Password is too long.");
        }
        
        return ['email' => $email, 'password' => $password];
    } catch (ValidationException $e) {
        throw $e;
    }
}

// Handle login request
if (isset($_POST['login'])) {
    $email = $_POST['emailid'] ?? '';
    $inputPassword = $_POST['password'] ?? '';

    try {
        // Validate and sanitize inputs
        $validated = validateInput($email, $inputPassword);
        $email = $validated['email'];
        $inputPassword = $validated['password'];

        // Query admin credentials
        $stmt = $conn->prepare("SELECT admin_id, email, password FROM admin WHERE email = ?");
        if (!$stmt) {
            throw new DatabaseException("Statement preparation failed: " . $conn->error);
        }

        $stmt->bind_param("s", $email);
        if (!$stmt->execute()) {
            throw new DatabaseException("Execution failed: " . $stmt->error);
        }

        $result = $stmt->get_result();
        if (!$result) {
            throw new DatabaseException("Failed to retrieve query results.");
        }

        $admin = $result->num_rows > 0 ? $result->fetch_assoc() : null;

        if ($admin && password_verify($inputPassword, $admin['password'])) {
            session_regenerate_id(true);
            $_SESSION['alogin'] = $admin['email'];
            $_SESSION['admin_id'] = $admin['admin_id'];
            $logger->write("Successful login attempt for email: $email");
            echo json_encode(['success' => true, 'redirect' => 'admin/dashboard.php']);
            exit;
        } else {
            throw new AuthenticationException("Invalid email or password.");
        }
    } catch (ValidationException $e) {
        $logger->write("Validation error for email: $email: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    } catch (AuthenticationException $e) {
        $logger->write("Failed login attempt for email: $email: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    } catch (DatabaseException $e) {
        $logger->write("Database error during login: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Database error occurred.']);
        exit;
    } catch (Exception $e) {
        $logger->write("Unexpected error during login: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'An unexpected error occurred.']);
        exit;
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/styles.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700&display=swap" rel="stylesheet">
    <style>
        .notification-area {
            transition: opacity 0.3s ease;
        }
        .notification-area.fade-out {
            opacity: 0;
        }
    </style>
</head>
<body>
<?php include('includes/header.php'); ?>
<div class="form-container">
    <div class="login-card">
        <div class="card-header">
            <h4><i class="fas fa-user-shield"></i>&nbsp; Admin Login</h4>
        </div>
        <div class="card-body">
            <div id="notification-area" class="notification-area"></div>

            <form id="loginForm" method="post">
                <div class="form-group">
                    <label for="emailid">Email</label>
                    <input type="email" 
                           id="emailid" 
                           name="emailid" 
                           required 
                           autocomplete="off"
                           maxlength="100"
                           pattern="[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$"
                           title="Please enter a valid email address">
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" 
                           id="password" 
                           name="password" 
                           required 
                           autocomplete="off"
                           minlength="6"
                           maxlength="255"
                           title="Password must be at least 6 characters">
                </div>
                <div class="form-actions">
                    <button type="submit" name="login">Login</button>
                    <div class="signup-link">
                        <a href="index.php"><small><i class="fas fa-user"></i> User?</small></a>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <div class="login-image" aria-hidden="true"></div>
</div>

<script>
class NotificationCenter {
    constructor() { this.events = {}; }
    subscribe(event, handler) {
        if (!this.events[event]) this.events[event] = [];
        this.events[event].push(handler);
    }
    publish(event, data) {
        if (this.events[event]) this.events[event].forEach(h => h(data));
    }
}

const notifier = new NotificationCenter();
const notificationArea = document.getElementById("notification-area");
let currentNotificationTimeout = null;

function showMessage(msg, type = "info") {
    // Clear any existing timeout
    if (currentNotificationTimeout) {
        clearTimeout(currentNotificationTimeout);
    }
    
    notificationArea.classList.remove('fade-out');
    notificationArea.innerHTML = `<div class="msg ${type}">${msg}</div>`;
    notificationArea.style.display = 'block';
}

function hideMessage() {
    notificationArea.classList.add('fade-out');
    currentNotificationTimeout = setTimeout(() => {
        notificationArea.innerHTML = '';
        notificationArea.style.display = 'none';
        notificationArea.classList.remove('fade-out');
    }, 300);
}

notifier.subscribe("login:attempt", () => {
    showMessage(`<span class="spinner"></span> Validating credentials...`, "info");
});
notifier.subscribe("login:success", () => {
    showMessage(`Login successful! Redirecting...`, "success");
});
notifier.subscribe("login:failed", (msg) => {
    showMessage(`${msg || 'Invalid credentials'}`, "error");
});

function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

// Clear notifications when user starts typing
const emailInput = document.getElementById("emailid");
const passwordInput = document.getElementById("password");

emailInput.addEventListener("input", () => {
    if (notificationArea.querySelector('.msg.error')) {
        hideMessage();
    }
});

passwordInput.addEventListener("input", () => {
    if (notificationArea.querySelector('.msg.error')) {
        hideMessage();
    }
});

// Field validation on focus
emailInput.addEventListener("focus", () => {
    if (!emailInput.value.trim()) {
        showMessage("Email must be valid.", "hint");
    }
});

emailInput.addEventListener("blur", () => {
    const email = emailInput.value.trim();
    if (email === "") {
        showMessage("Email is required.", "error");
    } else if (!isValidEmail(email)) {
        showMessage("Please enter a valid email address.", "error");
    } else if (email.length > 100) {
        showMessage("Email address is too long.", "error");
    } else {
        hideMessage();
    }
});

passwordInput.addEventListener("focus", () => {
    if (!passwordInput.value) {
        showMessage("Password must be at least 6 characters.", "hint");
    }
});

passwordInput.addEventListener("blur", () => {
    const password = passwordInput.value;
    if (password === "") {
        showMessage("Password is required.", "error");
    } else if (password.length < 6) {
        showMessage("Password must be at least 6 characters.", "error");
    } else if (password.length > 255) {
        showMessage("Password is too long.", "error");
    } else {
        hideMessage();
    }
});

async function handleLogin(e) {
    e.preventDefault();

    const email = emailInput.value.trim();
    const password = passwordInput.value;

    try {
        // Client-side validation
        if (email === "" || password === "") {
            notifier.publish("login:failed", "Please fill in all fields");
            return;
        }

        if (!isValidEmail(email)) {
            notifier.publish("login:failed", "Please enter a valid email address");
            return;
        }

        if (email.length > 100) {
            notifier.publish("login:failed", "Email address is too long");
            return;
        }

        if (password.length < 6) {
            notifier.publish("login:failed", "Password must be at least 6 characters");
            return;
        }

        if (password.length > 255) {
            notifier.publish("login:failed", "Password is too long");
            return;
        }

        // Check for spaces in password
        if (password.includes(' ')) {
            notifier.publish("login:failed", "Password should not contain spaces");
            return;
        }

        notifier.publish("login:attempt");

        const response = await fetch('adminlogin.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ emailid: email, password: password, login: true })
        });

        if (!response.ok) {
            throw new Error('Network response was not ok');
        }

        const result = await response.json();

        if (result.success) {
            notifier.publish("login:success");
            setTimeout(() => {
                window.location.href = result.redirect;
            }, 1500);
        } else {
            notifier.publish("login:failed", result.error);
        }
    } catch (error) {
        console.error('Login error:', error);
        notifier.publish("login:failed", "Network error occurred. Please try again.");
    }
}

document.getElementById("loginForm").addEventListener("submit", handleLogin);

// Handle URL error parameter and clear it
if (window.location.search.includes("error=1")) {
    notifier.publish("login:failed", "Invalid credentials");
    // Clear URL parameter without reloading
    if (window.history.replaceState) {
        window.history.replaceState({}, document.title, window.location.pathname);
    }
}
</script>
</body>
</html>