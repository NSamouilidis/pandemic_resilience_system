<?php
session_start();

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Authentication required']);
    exit;
}

require_once "../../config/db_connect.php";

header('Content-Type: application/json');

if (!isset($_GET['merchant_id']) || empty($_GET['merchant_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Merchant ID is required']);
    exit;
}

$merchant_id = (int)$_GET['merchant_id'];

$merchant_sql = "SELECT m.business_name, u.city, u.address, u.postal_code 
                FROM merchants m 
                JOIN users u ON m.prs_id = u.prs_id 
                WHERE m.merchant_id = ? AND m.status = 'active'";
$stmt = $conn->prepare($merchant_sql);
$stmt->bind_param("i", $merchant_id);
$stmt->execute();
$merchant_result = $stmt->get_result();

if ($merchant_result->num_rows == 0) {
    echo json_encode(['status' => 'error', 'message' => 'Merchant not found or not active']);
    exit;
}

$merchant = $merchant_result->fetch_assoc();
$stmt->close();

$sql = "SELECT i.inventory_id, it.name, it.category, it.description, 
        i.quantity, i.price, it.rationing_limit 
        FROM inventory i 
        JOIN items it ON i.item_id = it.item_id 
        WHERE i.merchant_id = ? AND i.quantity > 0 
        ORDER BY it.category, it.name";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $merchant_id);
$stmt->execute();
$result = $stmt->get_result();

$items = [];
while ($row = $result->fetch_assoc()) {
    $items[] = $row;
}
$stmt->close();

log_activity($_SESSION["prs_id"], "view", "merchant_inventory", $merchant_id, "success");

echo json_encode([
    'status' => 'success',
    'message' => 'Inventory retrieved successfully',
    'merchant' => $merchant,
    'items' => $items
]);
?>