
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
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>

    <style>
        :root {
            --navy: #000435;
            --gold: #ffde59;
        }

        body {
            background: #f8f9fa;
            font-family: 'Montserrat', sans-serif;
        }

        .page-container {
            max-width: 1600px;
            margin: 2rem auto;
            padding: 0 3rem;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,.1);
            display: flex;
            align-items: center;
            gap: 1.5rem;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,.15);
        }

        .stat-icon {
            width: 70px;
            height: 70px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: white;
        }

        .stat-icon.total { background: linear-gradient(135deg, var(--navy), #001a52); }
        .stat-icon.active { background: linear-gradient(135deg, #28a745, #20c997); }
        .stat-icon.blocked { background: linear-gradient(135deg, #dc3545, #e74c3c); }
        .stat-icon.new { background: linear-gradient(135deg, #ffc107, #ff9800); }

        .stat-details {
            flex: 1;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--navy);
            margin-bottom: 0.25rem;
        }

        .stat-label {
            color: #666;
            font-size: 0.9rem;
            font-weight: 500;
        }

        /* Page Header */
        .page-header {
            background: linear-gradient(135deg, var(--navy), #001a52);
            color: white;
            padding: 2rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            box-shadow: 0 8px 25px rgba(0,0,0,.2);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .page-header h1 {
            margin: 0;
            font-size: 2rem;
            color: white;
        }

        .header-actions {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }

        .btn-primary {
            background: var(--gold);
            color: var(--navy);
        }

        .btn-primary:hover {
            background: #ffd940;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(255,222,89,.4);
        }

        /* Alerts */
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
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
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

        /* Table Container */
        .table-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,.1);
            overflow: hidden;
        }

        .table-header {
            padding: 1.5rem;
            border-bottom: 2px solid #e0e0e0;
        }

        table.dataTable {
            width: 100% !important;
            border-collapse: collapse;
        }

        table.dataTable thead th {
            background: var(--navy);
            color: white;
            padding: 1rem;
            font-weight: 600;
            text-align: left;
            border: none;
        }

        table.dataTable tbody td {
            padding: 1rem;
            border-bottom: 1px solid #e0e0e0;
        }

        table.dataTable tbody tr:hover {
            background: #f8f9fa;
        }

        /* Student Info Cell */
        .student-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .student-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--gold);
        }

        .student-avatar-placeholder {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--navy), #001a52);
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid var(--gold);
        }

        .student-avatar-placeholder i {
            color: var(--gold);
            font-size: 1.5rem;
        }

        .student-details {
            flex: 1;
        }

        .student-name {
            font-weight: 600;
            color: var(--navy);
            margin-bottom: 0.25rem;
        }

        .student-course {
            font-size: 0.85rem;
            color: #666;
        }

        /* Status Badge */
        .status-badge {
            display: inline-block;
            padding: 0.35rem 0.75rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .status-active {
            background: #d1e7dd;
            color: #0f5132;
        }

        .status-blocked {
            background: #f8d7da;
            color: #842029;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .btn-action {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            position: relative;
            font-size: 1rem;
        }

        .btn-action::after {
            content: attr(title);
            position: absolute;
            bottom: -40px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 0.5rem 0.75rem;
            border-radius: 6px;
            font-size: 0.8rem;
            white-space: nowrap;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            z-index: 100;
        }

        .btn-action:hover::after {
            opacity: 1;
            visibility: visible;
            bottom: -35px;
        }

        .btn-view {
            background: var(--navy);
            color: white;
        }

        .btn-view:hover {
            background: #001a52;
        }

        .btn-edit {
            background: #0d6efd;
            color: white;
        }

        .btn-edit:hover {
            background: #0b5ed7;
        }

        .btn-block {
            background: #dc3545;
            color: white;
        }

        .btn-block:hover {
            background: #bb2d3b;
        }

        .btn-unblock {
            background: #28a745;
            color: white;
        }

        .btn-unblock:hover {
            background: #218838;
        }

        .btn-delete {
            background: #6c757d;
            color: white;
        }

        .btn-delete:hover {
            background: #5a6268;
        }

        /* DataTables Custom Styling */
        .dataTables_wrapper .dataTables_length,
        .dataTables_wrapper .dataTables_filter,
        .dataTables_wrapper .dataTables_info,
        .dataTables_wrapper .dataTables_paginate {
            padding: 1rem 1.5rem;
        }

        .dataTables_wrapper .dataTables_filter input {
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 0.5rem 1rem;
            margin-left: 0.5rem;
        }

        .dataTables_wrapper .dataTables_length select {
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 0.5rem;
            margin: 0 0.5rem;
        }

        /* Confirmation Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .modal-content {
            background: white;
            margin: 10% auto;
            padding: 2rem;
            border-radius: 12px;
            max-width: 500px;
            animation: slideUp 0.3s ease;
        }

        @keyframes slideUp {
            from { transform: translateY(50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .modal-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .modal-header i {
            font-size: 2rem;
            color: #dc3545;
        }

        .modal-actions {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .modal-btn {
            flex: 1;
            padding: 0.75rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
        }

        .modal-btn-danger {
            background: #dc3545;
            color: white;
        }

        .modal-btn-cancel {
            background: #6c757d;
            color: white;
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .page-header {
                flex-direction: column;
                text-align: center;
            }

            .action-buttons {
                flex-direction: column;
            }

            .btn-action {
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
                <i class="fas fa-info-circle"></i> This action cannot be undone. Students with borrowing history cannot be deleted.
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

        $(document).ready(function() {
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

        document.getElementById('confirmDelete').addEventListener('click', function() {
            window.location.href = 'reg-students.php?del=' + encodeURIComponent(deleteStudentId);
        });

        window.addEventListener('click', function(event) {
            const modal = document.getElementById('deleteModal');
            if (event.target === modal) {
                closeModal();
            }
        });

        // Auto-dismiss alerts
        window.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(function() {
                    alert.style.transition = 'opacity 0.5s ease';
                    alert.style.opacity = '0';
                    setTimeout(function() {
                        alert.remove();
                    }, 500);
                }, 5000);
            });
        });
    </script>
</body>

</html>
