<?php
session_start();
error_reporting(E_ALL);

include '../connection/db.php';
include '../security/crypt.php';
include 'includes/logger.php';
date_default_timezone_set('Asia/Manila');

if (strlen($_SESSION['alogin']) == 0) {
    header('location:../index.php');
    exit;
}

$logger = new Logger();

function getStudentDetails($conn)
{
    $students = [];
    $sql = "
      SELECT 
          *, 
          CONCAT_WS(' ', first_name, middle_name, last_name) AS full_name 
      FROM 
          students
    ";
    $result = $conn->query($sql);

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $students[] = $row;
        }
    }

    return $students;
}

function getStudentStatus($conn, $studentID)
{
    $status = '';
    $stmt = $conn->prepare("SELECT status FROM students WHERE student_id = ?");
    $stmt->bind_param("s", $studentID);
    $stmt->execute();
    $stmt->bind_result($status);

    if ($stmt->fetch()) {
        return $status;
    }

    return null;
}

function updateStudentStatus($conn, $studentID, $newStatus)
{
    global $logger;
    // Get student's full name for logging
    $nameStmt = $conn->prepare("SELECT CONCAT_WS(' ', first_name, middle_name, last_name) AS full_name FROM students WHERE student_id = ?");
    $nameStmt->bind_param("s", $studentID);
    $nameStmt->execute();
    $nameResult = $nameStmt->get_result();
    $full_name = $nameResult->num_rows > 0 ? $nameResult->fetch_assoc()['full_name'] : 'Unknown';

    // Update status
    $stmt = $conn->prepare("UPDATE students SET status = ? WHERE student_id = ?");
    $stmt->bind_param("is", $newStatus, $studentID);
    $success = $stmt->execute();

    // Log the action
    if ($success) {
        $action = $newStatus == 1 ? 'Unblocked' : 'Blocked';
        $logger->write("$action student: $full_name ($studentID)");
    }

    return $success;
}

if (isset($_GET['toggle'])) {
    $encryptedID = $_GET['toggle'];
    $decryptedID = decrypt($encryptedID);

    if (!$decryptedID) {
        $_SESSION['delmsg'] = "Invalid student ID.";
        header("location: reg-students.php");
        exit;
    }

    $currentStatus = getStudentStatus($conn, $decryptedID);

    if ($currentStatus === null) {
        $_SESSION['delmsg'] = "Student not found.";
        header("location: reg-students.php");
        exit;
    }

    $newStatus = ($currentStatus == 1) ? 0 : 1;

    $_SESSION['delmsg'] = updateStudentStatus($conn, $decryptedID, $newStatus)
        ? "Student status updated successfully."
        : "Failed to update student status.";

    header('location: reg-students.php');
    exit;
}

if (isset($_GET['del'])) {
    $encryptedID = $_GET['del'];
    $decryptedID = decrypt($encryptedID);

    if (!$decryptedID) {
        $_SESSION['delmsg'] = "Invalid student ID.";
        header("location: reg-students.php");
        exit;
    }

    $stmt = $conn->prepare("DELETE FROM students WHERE student_id = ?");
    $stmt->bind_param("s", $decryptedID);

    if ($stmt->execute()) {
        $_SESSION['delmsg'] = "Student deleted successfully.";
        $logger->write("Student deleted: $decryptedID");
    } else {
        $_SESSION['delmsg'] = "Failed to delete student.";
    }

    header("location: reg-students.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Registered Students</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="../css/tables.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700&display=swap" rel="stylesheet">
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/jquery.dataTables.min.css">
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
</head>

<body>
    <?php include('includes/header.php'); ?>

    <div class="container">
        <div class="header-container">
            <h2>Registered Students</h2>
            <div style="text-align: right; margin-bottom: 10px;">
                <a href="signup.php" class="btn btn-primary"
                    style="padding: 8px 14px; background-color:rgb(32, 142, 58); border: none; border-radius: 5px; color: #fff; text-decoration: none; font-weight: bold;">
                    <i class="fas fa-plus"></i> Register Student
                </a>
            </div>

            <br>

            <?php
            $alerts = ['error', 'msg', 'updatemsg', 'delmsg'];
            foreach ($alerts as $alert) {
                if (!empty($_SESSION[$alert])) {
                    $type = ($alert == 'error') ? 'danger' : 'success';
                    echo '<div class="alert alert-' . $type . '">';
                    echo '<strong>' . ucfirst($type) . ':</strong> ' . htmlentities($_SESSION[$alert]);
                    echo '</div>';
                    $_SESSION[$alert] = "";
                }
            }
            ?>
        </div>

        <table id="studentsTable" class="display">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Full Name</th>
                    <th>Student ID</th>
                    <th>Email</th>
                    <th>Reg Date</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $students = getStudentDetails($conn);
                $cnt = 1;

                if (count($students) > 0) {
                    foreach ($students as $row) {
                        $encryptedID = encrypt($row['student_id']);
                        $statusLabel = $row['status'] == 1 ? 'Active' : 'Blocked';
                        $statusColor = $row['status'] == 1 ? 'green' : 'red';
                        $actionText = $row['status'] == 1 ? 'Block' : 'Unblock';

                        echo "<tr>
                            <td>{$cnt}</td>
                            <td>" . htmlentities($row['full_name']) . "</td>
                            <td>" . htmlentities($row['student_id']) . "</td>
                            <td>" . htmlentities($row['email']) . "</td>
                            <td>" . htmlentities($row['registration_date']) . "</td>
                            <td style='color: {$statusColor}; font-weight: bold;'>{$statusLabel}</td>
                            <td>
                                <a class=\"btn-block toggle-btn\" href=\"reg-students.php?toggle=" . urlencode($encryptedID) . "\" 
                                   onclick=\"return confirm('Are you sure you want to " . htmlentities(strtolower($actionText)) . " this student?')\">
                                   {$actionText}
                                </a>
                                <a href=\"edit-student.php?id=" . urlencode($encryptedID) . "\" class=\"btn btn-sm btn-apply me-2\">
                                     Edit
                                </a>
                                <a href=\"reg-students.php?del=" . urlencode($encryptedID) . "\" class=\"btn btn-sm btn-danger\" onclick=\"return confirm('Are you sure you want to delete this student?')\">
                                     Delete
                                </a>
                            </td>
                        </tr>";
                        $cnt++;
                    }
                } else {
                    echo '<tr><td colspan="7" style="text-align:center;">No students registered yet.</td></tr>';
                }
                ?>
            </tbody>
        </table>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize DataTable
            $('#studentsTable').DataTable({
                "paging": true,
                "searching": true,
                "ordering": true,
                "info": true,
                "lengthMenu": [10, 25, 50, 100],
                "pageLength": 10,
                "language": {
                    "search": "Search records:",
                    "paginate": {
                        "first": "First",
                        "last": "Last",
                        "next": "Next",
                        "previous": "Previous"
                    }
                }
            });
        });
    </script>

</body>

</html>