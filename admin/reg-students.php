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
      ORDER BY registration_date DESC
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
    $nameStmt = $conn->prepare("SELECT CONCAT_WS(' ', first_name, middle_name, last_name) AS full_name FROM students WHERE student_id = ?");
    $nameStmt->bind_param("s", $studentID);
    $nameStmt->execute();
    $nameResult = $nameStmt->get_result();
    $full_name = $nameResult->num_rows > 0 ? $nameResult->fetch_assoc()['full_name'] : 'Unknown';

    $stmt = $conn->prepare("UPDATE students SET status = ? WHERE student_id = ?");
    $stmt->bind_param("is", $newStatus, $studentID);
    $success = $stmt->execute();

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

    $_SESSION['msg'] = updateStudentStatus($conn, $decryptedID, $newStatus)
        ? "Student status updated successfully."
        : "Failed to update student status.";

    header('location: reg-students.php');
    exit;
}

if (isset($_GET['del'])) {
    $encryptedID = $_GET['del'];
    $decryptedID = decrypt($encryptedID);

    if (!$decryptedID) {
        $_SESSION['error'] = "Invalid student ID.";
        header("location: reg-students.php");
        exit;
    }

    // Check if student has issued books
    $checkStmt = $conn->prepare("SELECT COUNT(*) as count FROM issued_books WHERE student_id = ?");
    $checkStmt->bind_param("s", $decryptedID);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    $count = $result->fetch_assoc()['count'];

    if ($count > 0) {
        $_SESSION['error'] = "Cannot delete student with borrowing history. Block the account instead.";
    } else {
        // Delete profile image if exists
        $imgStmt = $conn->prepare("SELECT profile_image FROM students WHERE student_id = ?");
        $imgStmt->bind_param("s", $decryptedID);
        $imgStmt->execute();
        $imgResult = $imgStmt->get_result();
        $student = $imgResult->fetch_assoc();

        if (!empty($student['profile_image']) && file_exists("uploads/students/" . $student['profile_image'])) {
            unlink("uploads/students/" . $student['profile_image']);
        }

        $stmt = $conn->prepare("DELETE FROM students WHERE student_id = ?");
        $stmt->bind_param("s", $decryptedID);

        if ($stmt->execute()) {
            $_SESSION['msg'] = "Student deleted successfully.";
            $logger->write("Student deleted: $decryptedID");
        } else {
            $_SESSION['error'] = "Failed to delete student.";
        }
    }

    header("location: reg-students.php");
    exit;
}

// Get statistics
$totalStudents = $conn->query("SELECT COUNT(*) as count FROM students")->fetch_assoc()['count'];
$activeStudents = $conn->query("SELECT COUNT(*) as count FROM students WHERE status = 1")->fetch_assoc()['count'];
$blockedStudents = $conn->query("SELECT COUNT(*) as count FROM students WHERE status = 0")->fetch_assoc()['count'];
$newThisMonth = $conn->query("SELECT COUNT(*) as count FROM students WHERE MONTH(registration_date) = MONTH(CURDATE()) AND YEAR(registration_date) = YEAR(CURDATE())")->fetch_assoc()['count'];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registered Students</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/styles.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="../css/reg-students.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>

</head>

<body>
    <?php include('includes/header.php'); ?>

    <div class="page-container">
        <div class="page-header">
            <h1><i class="fas fa-user-graduate"></i> Registered Students</h1>
            <div class="header-actions">
                <a href="signup.php" class="btn btn-primary">
                    <i class="fas fa-user-plus"></i> Register New Student
                </a>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon total">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-details">
                    <div class="stat-value"><?= $totalStudents ?></div>
                    <div class="stat-label">Total Students</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon active">
                    <i class="fas fa-user-check"></i>
                </div>
                <div class="stat-details">
                    <div class="stat-value"><?= $activeStudents ?></div>
                    <div class="stat-label">Active Students</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon blocked">
                    <i class="fas fa-user-lock"></i>
                </div>
                <div class="stat-details">
                    <div class="stat-value"><?= $blockedStudents ?></div>
                    <div class="stat-label">Blocked Students</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon new">
                    <i class="fas fa-user-plus"></i>
                </div>
                <div class="stat-details">
                    <div class="stat-value"><?= $newThisMonth ?></div>
                    <div class="stat-label">New This Month</div>
                </div>
            </div>
        </div>

        <!-- Alerts -->
        <?php
        $alerts = ['error' => 'danger', 'msg' => 'success', 'updatemsg' => 'success', 'delmsg' => 'success'];
        foreach ($alerts as $key => $type) {
            if (!empty($_SESSION[$key])) {
                $icon = $type === 'danger' ? 'fa-exclamation-circle' : 'fa-check-circle';
                echo '<div class="alert alert-' . $type . '">';
                echo '<i class="fas ' . $icon . '"></i>';
                echo '<span>' . htmlentities($_SESSION[$key]) . '</span>';
                echo '</div>';
                $_SESSION[$key] = "";
            }
        }
        ?>

        <!-- Table -->
        <div class="table-container">
            <div class="table-responsive">
                <table id="studentsTable" class="display">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Student ID</th>
                            <th>Contact</th>
                            <th>Year Level</th>
                            <th>Registered</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $students = getStudentDetails($conn);

                        if (count($students) > 0) {
                            foreach ($students as $row) {
                                $encryptedID = encrypt($row['student_id']);
                                $statusLabel = $row['status'] == 1 ? 'Active' : 'Blocked';
                                $statusClass = $row['status'] == 1 ? 'status-active' : 'status-blocked';
                                $actionText = $row['status'] == 1 ? 'Block' : 'Unblock';
                                $actionClass = $row['status'] == 1 ? 'btn-block' : 'btn-unblock';
                                $actionIcon = $row['status'] == 1 ? 'fa-ban' : 'fa-check';

                                echo "<tr>";

                                // Student Info with Avatar
                                echo "<td>";
                                echo "<div class='student-info'>";
                                if (!empty($row['profile_image']) && file_exists("uploads/students/" . $row['profile_image'])) {
                                    echo "<img src='uploads/students/" . htmlspecialchars($row['profile_image']) . "' alt='Profile' class='student-avatar'>";
                                } else {
                                    echo "<div class='student-avatar-placeholder'><i class='fas fa-user'></i></div>";
                                }
                                echo "<div class='student-details'>";
                                echo "<div class='student-name'>" . htmlentities($row['full_name']) . "</div>";
                                echo "<div class='student-course'>" . htmlentities($row['course']) . "</div>";
                                if (!empty($row['specialization'])) {
                                    echo "<div class='student-course'>" . htmlentities($row['specialization']) . "</div>";
                                }
                                echo "</div></div></td>";

                                // Student ID
                                echo "<td><strong>" . htmlentities($row['student_id']) . "</strong></td>";

                                // Contact
                                echo "<td>";
                                echo "<div style='font-size:0.85rem;'>";
                                echo "<div style='margin-bottom:0.25rem;'><i class='fas fa-envelope' style='color:var(--navy);width:15px;'></i> " . htmlentities($row['email']) . "</div>";
                                echo "<div><i class='fas fa-phone' style='color:var(--navy);width:15px;'></i> " . htmlentities($row['mobile_no']) . "</div>";
                                echo "</div></td>";

                                // Year Level
                                echo "<td>" . htmlentities($row['year_level'] ?? 'N/A') . "</td>";

                                // Registration Date
                                echo "<td>" . date('M j, Y', strtotime($row['registration_date'])) . "</td>";

                                // Status
                                echo "<td><span class='status-badge $statusClass'>$statusLabel</span></td>";

                                // Actions
                                echo "<td><div class='action-buttons'>";
                                echo "<a href='edit-student.php?id=" . urlencode($encryptedID) . "' class='btn-action btn-edit' title='Edit'>";
                                echo "<i class='fas fa-edit'></i></a>";
                                echo "<button class='btn-action $actionClass' onclick=\"toggleStatus('" . urlencode($encryptedID) . "', '$actionText')\" title='$actionText'>";
                                echo "<i class='fas $actionIcon'></i></button>";
                                echo "<button class='btn-action btn-delete' onclick=\"confirmDelete('" . urlencode($encryptedID) . "', '" . htmlentities($row['full_name']) . "')\" title='Delete'>";
                                echo "<i class='fas fa-trash'></i></button>";
                                echo "</div></td>";

                                echo "</tr>";
                            }
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <i class="fas fa-exclamation-triangle"></i>
                <div>
                    <h3>Confirm Deletion</h3>
                </div>
            </div>
            <p>Are you sure you want to delete <strong id="studentName"></strong>?</p>
            <p style="color: #dc3545; font-size: 0.9rem;">
                <i class="fas fa-info-circle"></i> This action cannot be undone. Students with borrowing history cannot
                be deleted.
            </p>
            <div class="modal-actions">
                <button class="modal-btn modal-btn-danger" id="confirmDelete">
                    <i class="fas fa-trash"></i> Delete
                </button>
                <button class="modal-btn modal-btn-cancel" onclick="closeModal()">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>
        </div>
    </div>

    <script>
        let deleteStudentId = '';

        $(document).ready(function () {
            $('#studentsTable').DataTable({
                "paging": true,
                "searching": true,
                "ordering": true,
                "info": true,
                "lengthMenu": [10, 25, 50, 100],
                "pageLength": 10,
                "order": [[4, "desc"]], // Sort by registration date
                "language": {
                    "search": "Search students:",
                    "paginate": {
                        "first": "First",
                        "last": "Last",
                        "next": "Next",
                        "previous": "Previous"
                    },
                    "emptyTable": "No students registered yet."
                }
            });
        });

        function toggleStatus(encryptedId, action) {
            if (confirm(`Are you sure you want to ${action.toLowerCase()} this student?`)) {
                window.location.href = `reg-students.php?toggle=${encryptedId}`;
            }
        }

        function confirmDelete(studentId, studentName) {
            deleteStudentId = studentId;
            document.getElementById('studentName').textContent = studentName;
            document.getElementById('deleteModal').style.display = 'block';
        }

        function closeModal() {
            document.getElementById('deleteModal').style.display = 'none';
        }

        document.getElementById('confirmDelete').addEventListener('click', function () {
            window.location.href = 'reg-students.php?del=' + encodeURIComponent(deleteStudentId);
        });

        window.addEventListener('click', function (event) {
            const modal = document.getElementById('deleteModal');
            if (event.target === modal) {
                closeModal();
            }
        });

        // Auto-dismiss alerts
        window.addEventListener('DOMContentLoaded', function () {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(function () {
                    alert.style.transition = 'opacity 0.5s ease';
                    alert.style.opacity = '0';
                    setTimeout(function () {
                        alert.remove();
                    }, 500);
                }, 5000);
            });
        });
    </script>
</body>

</html>