<?php
session_start();
error_reporting(E_ALL);
include('../connection/db.php');
include('../security/crypt.php');

if (strlen($_SESSION['alogin']) == 0) {
    header('location:../index.php');
    exit();
}

// Fetch all admin accounts
$adminsQuery = "SELECT * FROM admin ORDER BY admin_id ASC";
$adminsResult = $conn->query($adminsQuery);

$totalAdmins = $conn->query("SELECT COUNT(*) as count FROM admin")->fetch_assoc()['count'];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Admins</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="../css/manage-admins.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/jquery.dataTables.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>

</head>

<body>
    <?php include('includes/header.php'); ?>

    <div class="page-container">
        <div class="page-header">
            <h1><i class="fas fa-users-cog"></i> Manage Administrators</h1>
            <a href="add-admin.php" class="btn btn-primary">
                <i class="fas fa-plus-circle"></i> Add New Admin
            </a>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-user-shield"></i>
                </div>
                <div class="stat-details">
                    <div class="stat-value"><?= $totalAdmins ?></div>
                    <div class="stat-label">Total Administrators</div>
                </div>
            </div>
        </div>

        <!-- Alerts -->
        <?php
        $alerts = ['error' => 'danger', 'msg' => 'success', 'delmsg' => 'success'];
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
                <table id="adminsTable" class="display">
                    <thead>
                        <tr>
                            <th>Administrator</th>
                            <th>Email</th>
                            <th>Last Updated</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $currentAdminEmail = $_SESSION['alogin'];
                        while ($admin = $adminsResult->fetch_assoc()) {
                            $fullName = trim(($admin['first_name'] ?? '') . ' ' . ($admin['last_name'] ?? ''));
                            if (empty($fullName)) {
                                $fullName = 'Administrator';
                            }

                            $isCurrent = ($admin['email'] === $currentAdminEmail);

                            // Encrypt the admin ID
                            $encryptedId = encrypt($admin['admin_id']);

                            echo "<tr>";

                            // Admin Info
                            echo "<td>";
                            echo "<div class='admin-info'>";
                            echo "<div class='admin-avatar'><i class='fas fa-user-shield'></i></div>";
                            echo "<div class='admin-details'>";
                            echo "<div class='admin-name'>" . htmlspecialchars($fullName);
                            if ($isCurrent) {
                                echo " <span class='badge badge-current'>You</span>";
                            }
                            echo "</div>";
                            echo "</div></div></td>";

                            // Email
                            echo "<td>" . htmlspecialchars($admin['email']) . "</td>";

                            // Last Updated
                            echo "<td>" . date('M j, Y g:i A', strtotime($admin['update_date'])) . "</td>";

                            // Actions
                            echo "<td style='white-space:nowrap;'>";
                            echo "<a href='edit-other-admin.php?id=" . urlencode($encryptedId) . "' class='btn-action btn-edit'>";
                            echo "<i class='fas fa-edit'></i> Edit</a>";

                            if ($isCurrent) {
                                echo "<button class='btn-action btn-delete' disabled title='Cannot delete your own account'>";
                                echo "<i class='fas fa-trash'></i> Delete</button>";
                            } else {
                                echo "<a href='delete-admin.php?id=" . urlencode($encryptedId) . "' class='btn-action btn-delete' onclick='return confirm(\"Are you sure you want to delete this admin account?\");'>";
                                echo "<i class='fas fa-trash'></i> Delete</a>";
                            }
                            echo "</td>";

                            echo "</tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        $(document).ready(function () {
            $('#adminsTable').DataTable({
                paging: true,
                searching: true,
                ordering: true,
                info: true,
                lengthMenu: [10, 25, 50, 100],
                pageLength: 10,
                order: [[2, "desc"]]
            });
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