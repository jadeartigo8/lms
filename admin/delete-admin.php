<?php
session_start();
error_reporting(E_ALL);
include('../connection/db.php');
include('../security/crypt.php');

if (strlen($_SESSION['alogin']) == 0) {
    header('location:../index.php');
    exit();
}

$encryptedId = $_GET['id'] ?? '';
$adminId = 0;

// Decrypt the admin ID
if (!empty($encryptedId)) {
    try {
        $adminId = decrypt($encryptedId);
    } catch (Exception $e) {
        $_SESSION['error'] = 'Invalid admin ID.';
        header('location:manage-admins.php');
        exit();
    }
}

$currentAdminEmail = $_SESSION['alogin'];

// Fetch admin information to check if it's the current user
$adminQuery = "SELECT * FROM admin WHERE admin_id = ?";
$stmt = $conn->prepare($adminQuery);
$stmt->bind_param("i", $adminId);
$stmt->execute();
$adminResult = $stmt->get_result();
$admin = $adminResult->fetch_assoc();

if (!$admin) {
    $_SESSION['error'] = 'Admin not found.';
    header('location:manage-admins.php');
    exit();
}

// Prevent deleting own account
if ($admin['email'] === $currentAdminEmail) {
    $_SESSION['error'] = 'You cannot delete your own account.';
    header('location:manage-admins.php');
    exit();
}

// Delete the admin
$deleteQuery = "DELETE FROM admin WHERE admin_id = ?";
$deleteStmt = $conn->prepare($deleteQuery);
$deleteStmt->bind_param("i", $adminId);

if ($deleteStmt->execute()) {
    $_SESSION['delmsg'] = 'Administrator deleted successfully.';
} else {
    $_SESSION['error'] = 'Failed to delete administrator.';
}

header('location:manage-admins.php');
exit();
?>