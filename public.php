<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once "../config/db_connect.php";

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$request_method = $_SERVER["REQUEST_METHOD"];
$endpoint = isset($_GET["endpoint"]) ? $_GET["endpoint"] : "";

$response = [
    "status" => "error",
    "message" => "Invalid request",
    "data" => null
];

function validateAccess() {
    if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
        return true;
    }
    
    $headers = getallheaders();
    $api_key = isset($headers['X-API-Key']) ? $headers['X-API-Key'] : '';
    
    $valid_key = "prs_api_key_2025";
    
    return $api_key === $valid_key;
}

if ($endpoint === "merchants") {
    if ($request_method === "GET") {
        if (!validateAccess()) {
            $response["message"] = "Unauthorized access";
            http_response_code(401);
            echo json_encode($response);
            exit;
        }
        
        $category = isset($_GET["category"]) ? sanitize_input($_GET["category"]) : null;
        $city = isset($_GET["city"]) ? sanitize_input($_GET["city"]) : null;
        $item = isset($_GET["item"]) ? sanitize_input($_GET["item"]) : null;
        
        $sql = "SELECT m.merchant_id, m.business_name, u.city, u.postal_code, 
                COUNT(DISTINCT i.item_id) as item_count 
                FROM merchants m 
                JOIN users u ON m.prs_id = u.prs_id 
                JOIN inventory i ON m.merchant_id = i.merchant_id 
                JOIN items it ON i.item_id = it.item_id 
                WHERE m.status = 'active' AND i.quantity > 0";
        
        $params = [];
        $types = "";
        
        if ($category !== null) {
            $sql .= " AND it.category = ?";
            $params[] = $category;
            $types .= "s";
        }
        
        if ($city !== null) {
            $sql .= " AND u.city = ?";
            $params[] = $city;
            $types .= "s";
        }
        
        if ($item !== null) {
            $sql .= " AND it.name LIKE ?";
            $params[] = "%$item%";
            $types .= "s";
        }
        
        $sql .= " GROUP BY m.merchant_id, m.business_name, u.city, u.postal_code
                  ORDER BY u.city, m.business_name";
        
        $merchants = [];
        
        if (count($params) > 0) {
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $merchants[] = $row;
            }
            
            $stmt->close();
        } else {
            $result = $conn->query($sql);
            
            while ($row = $result->fetch_assoc()) {
                $merchants[] = $row;
            }
        }
        
        $user_id = isset($_SESSION["prs_id"]) ? $_SESSION["prs_id"] : "api_user";
        log_activity($user_id, "fetch", "merchants", "api", "success");
        
        $response["status"] = "success";
        $response["message"] = "Merchants retrieved successfully";
        $response["data"] = [
            "merchants" => $merchants,
            "count" => count($merchants)
        ];
    }
} 
elseif ($endpoint === "merchant_items") {
    if ($request_method === "GET") {
        if (!validateAccess()) {
            $response["message"] = "Unauthorized access";
            http_response_code(401);
            echo json_encode($response);
            exit;
        }
        
        if (!isset($_GET["merchant_id"])) {
            $response["message"] = "Missing merchant ID";
            echo json_encode($response);
            exit;
        }
        
        $merchant_id = (int)$_GET["merchant_id"];
        
        $category = isset($_GET["category"]) ? sanitize_input($_GET["category"]) : null;
        
        $sql = "SELECT i.inventory_id, it.item_id, it.name as item_name, it.category, 
                it.description, i.quantity, i.price, it.rationing_limit 
                FROM inventory i 
                JOIN items it ON i.item_id = it.item_id 
                WHERE i.merchant_id = ? AND i.quantity > 0";
        
        $params = [$merchant_id];
        $types = "i";
        
        if ($category !== null) {
            $sql .= " AND it.category = ?";
            $params[] = $category;
            $types .= "s";
        }
        
        $sql .= " ORDER BY it.category, it.name";
        
        $items = [];
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $items[] = $row;
        }
        
        $stmt->close();
        
        $user_id = isset($_SESSION["prs_id"]) ? $_SESSION["prs_id"] : "api_user";
        log_activity($user_id, "fetch", "merchant_items", "api", "success");
        
        $response["status"] = "success";
        $response["message"] = "Items retrieved successfully";
        $response["data"] = [
            "items" => $items,
            "count" => count($items)
        ];
    }
}
elseif ($endpoint === "items") {
    if ($request_method === "GET") {
        if (!validateAccess()) {
            $response["message"] = "Unauthorized access";
            http_response_code(401);
            echo json_encode($response);
            exit;
        }
        
        $category = isset($_GET["category"]) ? sanitize_input($_GET["category"]) : null;
        $search = isset($_GET["search"]) ? sanitize_input($_GET["search"]) : null;
        
        $sql = "SELECT item_id, name, category, description, rationing_limit 
                FROM items WHERE 1=1";
        
        $params = [];
        $types = "";
        
        if ($category !== null) {
            $sql .= " AND category = ?";
            $params[] = $category;
            $types .= "s";
        }
        
        if ($search !== null) {
            $sql .= " AND (name LIKE ? OR description LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $types .= "ss";
        }
        
        $sql .= " ORDER BY category, name";
        
        $items = [];
        
        if (count($params) > 0) {
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $items[] = $row;
            }
            
            $stmt->close();
        } else {
            $result = $conn->query($sql);
            
            while ($row = $result->fetch_assoc()) {
                $items[] = $row;
            }
        }
        
        $user_id = isset($_SESSION["prs_id"]) ? $_SESSION["prs_id"] : "api_user";
        log_activity($user_id, "fetch", "items", "api", "success");
        
        $response["status"] = "success";
        $response["message"] = "Items retrieved successfully";
        $response["data"] = [
            "items" => $items,
            "count" => count($items)
        ];
    }
}
elseif ($endpoint === "verify_vaccination") {
    if ($request_method === "POST") {
        if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
            $response["message"] = "Authentication required";
            http_response_code(401);
            echo json_encode($response);
            exit;
        }
        
        $json_data = file_get_contents("php://input");
        $data = json_decode($json_data, true);
        
        if (!isset($data["prs_id"])) {
            $response["message"] = "Missing PRS ID";
            echo json_encode($response);
            exit;
        }
        
        $prs_id = sanitize_input($data["prs_id"]);
        
        $sql = "SELECT COUNT(*) as count FROM vaccinations WHERE prs_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $prs_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        $is_vaccinated = ($row["count"] > 0);
        
        log_activity($_SESSION["prs_id"], "verify", "vaccination", $prs_id, "success");
        
        $response["status"] = "success";
        $response["message"] = "Vaccination status verified";
        $response["data"] = [
            "is_vaccinated" => $is_vaccinated,
            "vaccination_count" => $row["count"]
        ];
        
        $stmt->close();
    }
}

echo json_encode($response);
?>