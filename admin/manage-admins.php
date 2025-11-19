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
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/jquery.dataTables.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
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

        .page-container {
            max-width: 1400px;
            margin: 2rem auto;
            padding: 0 2rem;
        }

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
        }

        .stat-icon {
            width: 70px;
            height: 70px;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--navy), #001a52);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: var(--gold);
        }

        .stat-details {
            flex: 1;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--navy);
        }

        .stat-label {
            color: #666;
            font-size: 0.9rem;
            font-weight: 500;
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
            box-shadow: 0 4px 15px rgba(255, 222, 89, .4);
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

        .alert-danger {
            background: #f8d7da;
            color: #842029;
            border-left: 4px solid #dc3545;
        }

        .table-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,.1);
            overflow: hidden;
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
        }

        table.dataTable tbody td {
            padding: 1rem;
            border-bottom: 1px solid #e0e0e0;
        }

        table.dataTable tbody tr:hover {
            background: #f8f9fa;
        }

        .admin-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .admin-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--navy), #001a52);
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid var(--gold);
        }

        .admin-avatar i {
            color: var(--gold);
            font-size: 1.5rem;
        }

        .admin-details {
            flex: 1;
        }

        .admin-name {
            font-weight: 600;
            color: var(--navy);
            margin-bottom: 0.25rem;
        }

        .admin-email {
            font-size: 0.85rem;
            color: #666;
        }

        .badge {
            display: inline-block;
            padding: 0.35rem 0.75rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .badge-current {
            background: var(--gold);
            color: var(--navy);
        }

        .badge-admin {
            background: #e7f3ff;
            color: #084298;
        }

        .btn-action {
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            font-size: 0.85rem;
            margin-right: 0.5rem;
        }

        .btn-edit {
            background: var(--navy);
            color: white;
        }

        .btn-edit:hover {
            background: #001a52;
            transform: translateY(-2px);
        }

        .btn-delete {
            background: #dc3545;
            color: white;
        }

        .btn-delete:hover {
            background: #c82333;
            transform: translateY(-2px);
        }

        .btn-delete:disabled {
            background: #ccc;
            cursor: not-allowed;
            opacity: 0.6;
        }

        .dataTables_wrapper .dataTables_length,
        .dataTables_wrapper .dataTables_filter,
        .dataTables_wrapper .dataTables_info,
        .dataTables_wrapper .dataTables_paginate {
            padding: 1rem 1.5rem;
        }

        @media (max-width: 768px) {
            .page-container {
                padding: 0 1rem;
            }

            .page-header {
                flex-direction: column;
                text-align: center;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
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

    <script>
        $(document).ready(function() {
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