<?php
session_start();

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Authentication required']);
    exit;
}

require_once "../../config/db_connect.php";

header('Content-Type: application/json');

$sql = "SELECT m.merchant_id, m.business_name, m.business_type, 
        u.city, u.address, u.postal_code, 
        COUNT(DISTINCT i.item_id) as item_count 
        FROM merchants m 
        JOIN users u ON m.prs_id = u.prs_id 
        JOIN inventory i ON m.merchant_id = i.merchant_id 
        WHERE m.status = 'active' AND i.quantity > 0 
        GROUP BY m.merchant_id";

$merchants = [];
$result = $conn->query($sql);

while ($row = $result->fetch_assoc()) {
    $base_lat = 40.6401;
    $base_lng = 22.9444;
    
    $lat = $base_lat + (mt_rand(-100, 100) / 1000);
    $lng = $base_lng + (mt_rand(-100, 100) / 1000);
    
    $merchants[] = [
        'id' => $row['merchant_id'],
        'name' => $row['business_name'],
        'type' => $row['business_type'],
        'address' => $row['address'] ?? '',
        'city' => $row['city'] ?? '',
        'postal_code' => $row['postal_code'] ?? '',
        'item_count' => $row['item_count'],
        'lat' => $lat,
        'lng' => $lng
    ];
}

log_activity($_SESSION["prs_id"], "fetch", "merchant_locations", "map", "success");

echo json_encode([
    'status' => 'success',
    'message' => 'Merchant locations retrieved successfully',
    'merchants' => $merchants
]);
?>