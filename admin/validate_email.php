<?php
include('../connection/db.php');
header('Content-Type: application/json');
$email = $_POST['email'] ?? '';
$studentid = $_POST['studentid'] ?? '';
$stmt = $conn->prepare("SELECT * FROM students WHERE email = ? AND student_id != ?");
$stmt->bind_param("ss", $email, $studentid);
$stmt->execute();
echo json_encode(['exists' => $stmt->get_result()->num_rows > 0]);
?>