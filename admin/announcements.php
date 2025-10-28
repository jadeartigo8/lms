<?php
session_start();
include('../connection/db.php');
include('includes/logger.php');
include('includes/exceptions.php');
date_default_timezone_set('Asia/Manila');

$logger = new Logger();

// Session validation - Admin only
try {
    if (empty($_SESSION['alogin'])) {
        throw new SessionException("User not logged in.");
    }
    // Check if user exists in admin table (implies admin role)
    $user_email = $_SESSION['alogin'];
    $stmt = $conn->prepare("SELECT admin_id, first_name, last_name FROM admin WHERE email = ?");
    if (!$stmt) {
        throw new DatabaseException("Statement preparation failed: " . $conn->error);
    }
    $stmt->bind_param("s", $user_email);
    if (!$stmt->execute()) {
        throw new DatabaseException("Failed to execute admin query.");
    }
    $result = $stmt->get_result();
    $admin = $result->fetch_assoc();
    if (!$admin) {
        throw new SessionException("Access denied. Admin privileges required.");
    }
    $admin_name = trim($admin['first_name'] . ' ' . $admin['last_name']);
    $stmt->close();
} catch (SessionException $e) {
    $_SESSION['error'] = $e->getMessage();
    $logger->write("Session error: " . $e->getMessage());
    header('Location: ../index.php');
    exit;
} catch (DatabaseException $e) {
    $_SESSION['error'] = "System error. Please try again.";
    $logger->write("Database error: " . $e->getMessage());
    header('Location: dashboard.php');
    exit;
}

// Handle POST: Post announcement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['post_announcement'])) {
    try {
        $title = trim($_POST['title'] ?? '');
        $message = trim($_POST['message'] ?? '');

        if (empty($title) || empty($message)) {
            throw new ValidationException("Title and message are required.");
        }

        // Insert into announcements table
        $posted_by = $user_email;
        $post_date = date("Y-m-d H:i:s");
        $is_active = 1;

        $stmt = $conn->prepare("INSERT INTO announcements (title, message, posted_by, post_date, is_active) VALUES (?, ?, ?, ?, ?)");
        if (!$stmt) {
            throw new DatabaseException("Statement preparation failed: " . $conn->error);
        }
        $stmt->bind_param("ssssi", $title, $message, $posted_by, $post_date, $is_active);
        if (!$stmt->execute()) {
            throw new DatabaseException("Failed to post announcement.");
        }
        $stmt->close();

        $_SESSION['successmsg'] = "Announcement posted successfully!";
        $logger->write("Admin $user_email ($admin_name) posted announcement: $title");
        header('Location: announcements.php');
        exit;
    } catch (ValidationException $e) {
        $_SESSION['error'] = $e->getMessage();
        $logger->write("Validation error: " . $e->getMessage());
    } catch (DatabaseException $e) {
        $_SESSION['error'] = "Failed to post announcement.";
        $logger->write("Announcement post error: " . $e->getMessage());
    }
}

// Fetch recent announcements for preview
$announcements = [];
try {
    $stmt = $conn->prepare("SELECT title, message, post_date, posted_by FROM announcements WHERE is_active = 1 ORDER BY post_date DESC LIMIT 5");
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $announcements[] = $row;
        }
        $stmt->close();
    }
} catch (Exception $e) {
    $logger->write("Error fetching announcements: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
    <meta name="description" content="Admin Panel - Post Announcements for Notepad Users" />
    <meta name="author" content="" />
    <title>Post Notepad Announcement - Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/styles.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700&display=swap" rel="stylesheet">
    <style>
        .announce-container { max-width: 800px; margin: 20px auto; padding: 0 15px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group textarea, .form-group input { width: 100%; padding: 8px; box-sizing: border-box; }
        .form-group textarea { height: 150px; resize: vertical; }
        .btn { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; }
        .btn:hover { background: #0056b3; }
        .preview { background: #f8f9fa; padding: 15px; border-left: 4px solid #007bff; margin-top: 20px; }
        .recent-ann { margin-top: 30px; }
        .recent-ann h4 { color: #333; }
        .ann-item { background: #e9ecef; padding: 10px; margin-bottom: 10px; border-radius: 4px; }
        .success, .error { padding: 10px; margin-bottom: 15px; border-radius: 4px; }
        .success { background: #d4edda; color: #155724; }
        .error { background: #f8d7da; color: #721c24; }
        .required { color: #dc3545; }
        @media (max-width: 600px) { .announce-container { padding: 0 10px; } }
    </style>
</head>
<body>
<?php include('includes/header.php'); ?>

<div class="announce-container">
    <h3><i class="fas fa-bullhorn"></i> Post Notepad Announcement</h3>

    <?php if (isset($_SESSION['successmsg'])): ?>
        <div class="success"><?php echo htmlspecialchars($_SESSION['successmsg']); unset($_SESSION['successmsg']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="error"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <form name="announceForm" method="POST" id="announceForm">
        <div class="form-group">
            <label for="title">Announcement Title <span class="required">*</span></label>
            <input type="text" id="title" name="title" required maxlength="200" placeholder="e.g., New Notepad File Monitoring Feature">
        </div>

        <div class="form-group">
            <label for="message">Message <span class="required">*</span></label>
            <textarea id="message" name="message" required placeholder="Enter your announcement message here... (e.g., Dear Students, the ISCP Library Notepad now supports real-time file monitoring for .txt files in your personal folder...)"></textarea>
        </div>

        <button type="submit" name="post_announcement" class="btn"><i class="fas fa-paper-plane"></i> Post Announcement</button>
    </form>

    <div id="preview" class="preview" style="display: none;">
        <h5>Preview:</h5>
        <h4 id="previewTitle"></h4>
        <p id="previewMessage"></p>
    </div>

    <?php if (!empty($announcements)): ?>
        <div class="recent-ann">
            <h4>Recent Announcements</h4>
            <?php foreach ($announcements as $ann): ?>
                <div class="ann-item">
                    <strong><?php echo htmlspecialchars($ann['title']); ?></strong> - Posted by <?php echo htmlspecialchars($ann['posted_by']); ?> on <?php echo date('M j, Y g:i A', strtotime($ann['post_date'])); ?>
                    <p><?php echo nl2br(htmlspecialchars($ann['message'])); ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('announceForm');
    const title = document.getElementById('title');
    const message = document.getElementById('message');
    const preview = document.getElementById('preview');
    const previewTitle = document.getElementById('previewTitle');
    const previewMessage = document.getElementById('previewMessage');

    // Real-time preview
    [title, message].forEach(input => {
        input.addEventListener('input', function() {
            if (title.value.trim() && message.value.trim()) {
                previewTitle.textContent = title.value;
                previewMessage.textContent = message.value;
                preview.style.display = 'block';
            } else {
                preview.style.display = 'none';
            }
        });
    });

    // Client-side form validation
    form.addEventListener('submit', function(e) {
        if (!title.value.trim() || !message.value.trim()) {
            e.preventDefault();
            alert('Please fill in both the title and message fields.');
        }
    });
});
</script>

</body>
</html>