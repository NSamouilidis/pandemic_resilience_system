<?php
session_start();

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== "government") {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit;
}

require_once "../../config/db_connect.php";

header('Content-Type: application/json');

if (!isset($_GET['prs_id']) || empty($_GET['prs_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'PRS ID is required']);
    exit;
}

$prs_id = sanitize_input($_GET['prs_id']);

$sql = "SELECT * FROM users WHERE prs_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $prs_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['status' => 'error', 'message' => 'User not found']);
    exit;
}

$user = $result->fetch_assoc();
$stmt->close();

unset($user['password']);

log_activity($_SESSION["prs_id"], "view", "user_details", $prs_id, "success");

echo json_encode([
    'status' => 'success',
    'message' => 'User details retrieved successfully',
    'user' => $user
]);
?>