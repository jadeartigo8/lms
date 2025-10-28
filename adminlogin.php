<?php
session_start();
include('connection/db.php');
include('admin/includes/logger.php');
include('admin/includes/exceptions.php'); // Reuse custom exceptions
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
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new ValidationException("Invalid or missing email address.");
        }
        if (empty($password) || strlen($password) < 5) {
            throw new ValidationException("Password must be at least 6 characters.");
        }
    } catch (ValidationException $e) {
        throw $e; // Re-throw to be caught by the calling function
    }
}

// Handle login request
if (isset($_POST['login'])) {
    $email = trim($_POST['emailid']);
    $inputPassword = $_POST['password'];

    try {
        // Validate inputs
        validateInput($email, $inputPassword);

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
            throw new AuthenticationException("Invalid credentials.");
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
                    <input type="email" id="emailid" name="emailid" required autocomplete="off">
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required autocomplete="off">
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

function showMessage(msg, type = "info") {
    notificationArea.innerHTML = `<div class="msg ${type}">${msg}</div>`;
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

document.getElementById("emailid").addEventListener("focus", () => {
    showMessage("Email must be valid.", "hint");
});
document.getElementById("emailid").addEventListener("blur", () => {
    const email = document.getElementById("emailid").value.trim();
    if (email === "") {
        showMessage("Email is required.", "error");
    } else if (!isValidEmail(email)) {
        showMessage("Please enter a valid email address.", "error");
    } else {
        notificationArea.innerHTML = "";
    }
});

document.getElementById("password").addEventListener("focus", () => {
    showMessage("Password must be at least 6 characters.", "hint");
});
document.getElementById("password").addEventListener("blur", () => {
    const password = document.getElementById("password").value.trim();
    if (password === "") {
        showMessage("Password is required.", "error");
    } else if (password.length < 6) {
        showMessage("Password must be at least 6 characters.", "error");
    } else {
        notificationArea.innerHTML = "";
    }
});

async function handleLogin(e) {
    e.preventDefault();

    const email = document.getElementById("emailid").value.trim();
    const password = document.getElementById("password").value.trim();

    try {
        if (email === "" || password === "") {
            notifier.publish("login:failed", "Please fill in all fields");
            return;
        }

        if (!isValidEmail(email)) {
            notifier.publish("login:failed", "Please enter a valid email address");
            return;
        }

        if (password.length < 5) {
            notifier.publish("login:failed", "Password must be at least 6 characters");
            return;
        }

        notifier.publish("login:attempt");

        const response = await fetch('adminlogin.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ emailid: email, password: password, login: true })
        });
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
        notifier.publish("login:failed", "Network error occurred");
    }
}

document.getElementById("loginForm").addEventListener("submit", handleLogin);

// Handle URL error parameter
if (window.location.search.includes("error=1")) {
    notifier.publish("login:failed", "Invalid credentials");
}
</script>
</body>
</html>