<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once "../config/db_connect.php";

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, PUT, DELETE, OPTIONS");
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

function validateMerchantAccess() {
    global $conn;
    
    if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true && $_SESSION["role"] === "merchant") {
        // Also verify that merchant status is active
        $sql = "SELECT status FROM merchants WHERE prs_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $_SESSION["prs_id"]);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $row = $result->fetch_assoc();
            if ($row["status"] === "active") {
                return true;
            }
        }
        
        $stmt->close();
        return false;
    }
    
    $headers = getallheaders();
    $api_key = isset($headers['X-API-Key']) ? $headers['X-API-Key'] : '';
    
    $valid_key = "prs_merchant_api_key_2025";
    
    return $api_key === $valid_key;
}

function getMerchantId($prs_id) {
    global $conn;
    
    $sql = "SELECT merchant_id FROM merchants WHERE prs_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $prs_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $row = $result->fetch_assoc();
        return $row["merchant_id"];
    }
    
    return null;
}

if (!validateMerchantAccess()) {
    $response["message"] = "Unauthorized: Active merchant access required";
    http_response_code(403);
    echo json_encode($response);
    exit;
}

$merchant_id = getMerchantId($_SESSION["prs_id"]);

if ($merchant_id === null) {
    $response["message"] = "Merchant record not found";
    http_response_code(404);
    echo json_encode($response);
    exit;
}

if ($endpoint === "inventory") {
    if ($request_method === "GET") {
        $category = isset($_GET["category"]) ? sanitize_input($_GET["category"]) : null;
        $low_stock = isset($_GET["low_stock"]) ? (bool)$_GET["low_stock"] : false;
        
        $sql = "SELECT i.inventory_id, i.item_id, it.name as item_name, it.category, 
                it.description, i.quantity, i.price, it.rationing_limit 
                FROM inventory i 
                JOIN items it ON i.item_id = it.item_id 
                WHERE i.merchant_id = ?";
        
        $params = [$merchant_id];
        $types = "i";
        
        if ($category !== null) {
            $sql .= " AND it.category = ?";
            $params[] = $category;
            $types .= "s";
        }
        
        if ($low_stock) {
            $sql .= " AND i.quantity < 10";
        }
        
        $sql .= " ORDER BY it.category, it.name";
        
        $inventory = [];
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $inventory[] = $row;
        }
        
        $stmt->close();
        
        log_activity($_SESSION["prs_id"], "fetch", "inventory", "api", "success");
        
        $response["status"] = "success";
        $response["message"] = "Inventory retrieved successfully";
        $response["data"] = [
            "inventory" => $inventory,
            "count" => count($inventory)
        ];
    }
    elseif ($request_method === "PUT") {
        if (!isset($_GET["inventory_id"])) {
            $response["message"] = "Missing inventory ID";
            echo json_encode($response);
            exit;
        }
        
        $inventory_id = (int)$_GET["inventory_id"];
        
        $check_sql = "SELECT COUNT(*) as count FROM inventory 
                     WHERE inventory_id = ? AND merchant_id = ?";
        $stmt = $conn->prepare($check_sql);
        $stmt->bind_param("ii", $inventory_id, $merchant_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        if ($row["count"] == 0) {
            $response["message"] = "Inventory item not found or does not belong to this merchant";
            $stmt->close();
            echo json_encode($response);
            exit;
        }
        
        $stmt->close();
        
        $json_data = file_get_contents("php://input");
        $data = json_decode($json_data, true);
        
        if (!isset($data["quantity"]) && !isset($data["price"])) {
            $response["message"] = "No fields to update";
            echo json_encode($response);
            exit;
        }
        
        $sql = "UPDATE inventory SET ";
        $params = [];
        $types = "";
        $updates = [];
        
        if (isset($data["quantity"])) {
            $quantity = (int)$data["quantity"];
            if ($quantity < 0) {
                $response["message"] = "Quantity cannot be negative";
                echo json_encode($response);
                exit;
            }
            
            $updates[] = "quantity = ?";
            $params[] = $quantity;
            $types .= "i";
        }
        
        if (isset($data["price"])) {
            $price = (float)$data["price"];
            if ($price <= 0) {
                $response["message"] = "Price must be greater than zero";
                echo json_encode($response);
                exit;
            }
            
            $updates[] = "price = ?";
            $params[] = $price;
            $types .= "d";
        }
        
        $sql .= implode(", ", $updates);
        $sql .= " WHERE inventory_id = ?";
        $params[] = $inventory_id;
        $types .= "i";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        
        if ($stmt->execute()) {
            log_activity($_SESSION["prs_id"], "update", "inventory", $inventory_id, "success");
            
            $response["status"] = "success";
            $response["message"] = "Inventory updated successfully";
            $response["data"] = [
                "inventory_id" => $inventory_id,
                "updated_fields" => array_keys($data)
            ];
        } else {
            $response["message"] = "Error updating inventory: " . $stmt->error;
        }
        
        $stmt->close();
    }
    elseif ($request_method === "POST") {
        $json_data = file_get_contents("php://input");
        $data = json_decode($json_data, true);
        
        $required_fields = ["item_id", "quantity", "price"];
        foreach ($required_fields as $field) {
            if (!isset($data[$field])) {
                $response["message"] = "Missing required field: $field";
                echo json_encode($response);
                exit;
            }
        }
        
        $item_id = (int)$data["item_id"];
        $quantity = (int)$data["quantity"];
        $price = (float)$data["price"];
        
        if ($quantity < 0) {
            $response["message"] = "Quantity cannot be negative";
            echo json_encode($response);
            exit;
        }
        
        if ($price <= 0) {
            $response["message"] = "Price must be greater than zero";
            echo json_encode($response);
            exit;
        }
        
        $check_sql = "SELECT COUNT(*) as count FROM items WHERE item_id = ?";
        $stmt = $conn->prepare($check_sql);
        $stmt->bind_param("i", $item_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        if ($row["count"] == 0) {
            $response["message"] = "Item does not exist";
            $stmt->close();
            echo json_encode($response);
            exit;
        }
        
        $stmt->close();
        
        $check_sql = "SELECT inventory_id FROM inventory 
                     WHERE merchant_id = ? AND item_id = ?";
        $stmt = $conn->prepare($check_sql);
        $stmt->bind_param("ii", $merchant_id, $item_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $inventory_id = $row["inventory_id"];
            
            $update_sql = "UPDATE inventory SET quantity = ?, price = ? 
                          WHERE inventory_id = ?";
            $stmt = $conn->prepare($update_sql);
            $stmt->bind_param("idi", $quantity, $price, $inventory_id);
            
            if ($stmt->execute()) {
                log_activity($_SESSION["prs_id"], "update", "inventory", $inventory_id, "success");
                
                $response["status"] = "success";
                $response["message"] = "Inventory item updated successfully";
                $response["data"] = [
                    "inventory_id" => $inventory_id,
                    "item_id" => $item_id,
                    "quantity" => $quantity,
                    "price" => $price
                ];
            } else {
                $response["message"] = "Error updating inventory: " . $stmt->error;
            }
        } else {
            $insert_sql = "INSERT INTO inventory (merchant_id, item_id, quantity, price) 
                          VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($insert_sql);
            $stmt->bind_param("iiid", $merchant_id, $item_id, $quantity, $price);
            
            if ($stmt->execute()) {
                $inventory_id = $stmt->insert_id;
                
                log_activity($_SESSION["prs_id"], "create", "inventory", $inventory_id, "success");
                
                $response["status"] = "success";
                $response["message"] = "Inventory item added successfully";
                $response["data"] = [
                    "inventory_id" => $inventory_id,
                    "item_id" => $item_id,
                    "quantity" => $quantity,
                    "price" => $price
                ];
            } else {
                $response["message"] = "Error adding inventory item: " . $stmt->error;
            }
        }
        
        $stmt->close();
    }
    elseif ($request_method === "DELETE") {
        if (!isset($_GET["inventory_id"])) {
            $response["message"] = "Missing inventory ID";
            echo json_encode($response);
            exit;
        }
        
        $inventory_id = (int)$_GET["inventory_id"];
        
        $check_sql = "SELECT COUNT(*) as count FROM inventory 
                     WHERE inventory_id = ? AND merchant_id = ?";
        $stmt = $conn->prepare($check_sql);
        $stmt->bind_param("ii", $inventory_id, $merchant_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        if ($row["count"] == 0) {
            $response["message"] = "Inventory item not found or does not belong to this merchant";
            $stmt->close();
            echo json_encode($response);
            exit;
        }
        
        $stmt->close();
        
        $delete_sql = "DELETE FROM inventory WHERE inventory_id = ?";
        $stmt = $conn->prepare($delete_sql);
        $stmt->bind_param("i", $inventory_id);
        
        if ($stmt->execute()) {
            log_activity($_SESSION["prs_id"], "delete", "inventory", $inventory_id, "success");
            
            $response["status"] = "success";
            $response["message"] = "Inventory item deleted successfully";
            $response["data"] = [
                "inventory_id" => $inventory_id
            ];
        } else {
            $response["message"] = "Error deleting inventory item: " . $stmt->error;
        }
        
        $stmt->close();
    }
}
elseif ($endpoint === "transactions") {
    if ($request_method === "GET") {
        $limit = isset($_GET["limit"]) ? (int)$_GET["limit"] : 100;
        $offset = isset($_GET["offset"]) ? (int)$_GET["offset"] : 0;
        $from_date = isset($_GET["from_date"]) ? sanitize_input($_GET["from_date"]) : null;
        $to_date = isset($_GET["to_date"]) ? sanitize_input($_GET["to_date"]) : null;
        
        $sql = "SELECT t.transaction_id, t.prs_id, t.transaction_date, t.total_amount, 
                COUNT(ti.id) as item_count 
                FROM transactions t 
                JOIN transaction_items ti ON t.transaction_id = ti.transaction_id 
                WHERE t.merchant_id = ?";
        
        $params = [$merchant_id];
        $types = "i";
        
        if ($from_date !== null) {
            $sql .= " AND t.transaction_date >= ?";
            $params[] = $from_date;
            $types .= "s";
        }
        
        if ($to_date !== null) {
            $sql .= " AND t.transaction_date <= ?";
            $params[] = $to_date;
            $types .= "s";
        }
        
        $sql .= " GROUP BY t.transaction_id ORDER BY t.transaction_date DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        $types .= "ii";
        
        $transactions = [];
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $transactions[] = $row;
        }
        
        $stmt->close();
        
        log_activity($_SESSION["prs_id"], "fetch", "transactions", "api", "success");
        
        $response["status"] = "success";
        $response["message"] = "Transactions retrieved successfully";
        $response["data"] = [
            "transactions" => $transactions,
            "count" => count($transactions)
        ];
    }
    elseif ($request_method === "POST") {
        $json_data = file_get_contents("php://input");
        $data = json_decode($json_data, true);
        
        if (!isset($data["prs_id"]) || !isset($data["items"]) || !is_array($data["items"]) || empty($data["items"])) {
            $response["message"] = "Missing or invalid required fields";
            echo json_encode($response);
            exit;
        }
        
        $prs_id = sanitize_input($data["prs_id"]);
        $items = $data["items"];
        
        $check_sql = "SELECT COUNT(*) as count FROM users WHERE prs_id = ?";
        $stmt = $conn->prepare($check_sql);
        $stmt->bind_param("s", $prs_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        if ($row["count"] == 0) {
            $response["message"] = "Invalid PRS ID";
            $stmt->close();
            echo json_encode($response);
            exit;
        }
        
        $stmt->close();
        
        $conn->begin_transaction();
        
        try {
            $total_amount = 0;
            $verified_items = [];
            
            foreach ($items as $item) {
                if (!isset($item["item_id"]) || !isset($item["quantity"])) {
                    throw new Exception("Invalid item format");
                }
                
                $item_id = (int)$item["item_id"];
                $quantity = (int)$item["quantity"];
                
                if ($quantity <= 0) {
                    throw new Exception("Item quantity must be positive");
                }
                
                $item_sql = "SELECT i.inventory_id, i.quantity, i.price, it.name, it.category, it.rationing_limit 
                            FROM inventory i 
                            JOIN items it ON i.item_id = it.item_id 
                            WHERE i.merchant_id = ? AND i.item_id = ?";
                $stmt = $conn->prepare($item_sql);
                $stmt->bind_param("ii", $merchant_id, $item_id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows == 0) {
                    throw new Exception("Item not found in inventory");
                }
                
                $item_data = $result->fetch_assoc();
                $stmt->close();
                
                if ($item_data["quantity"] < $quantity) {
                    throw new Exception("Not enough stock for item: " . $item_data["name"]);
                }
                
                if ($item_data["rationing_limit"] > 0 && $quantity > $item_data["rationing_limit"]) {
                    throw new Exception("Exceeds rationing limit for item: " . $item_data["name"]);
                }
                
                $item_total = $item_data["price"] * $quantity;
                $total_amount += $item_total;
                
                $verified_items[] = [
                    "item_id" => $item_id,
                    "quantity" => $quantity,
                    "price_per_unit" => $item_data["price"],
                    "inventory_id" => $item_data["inventory_id"],
                    "current_stock" => $item_data["quantity"]
                ];
            }
            
            $transaction_sql = "INSERT INTO transactions (prs_id, merchant_id, total_amount) 
                              VALUES (?, ?, ?)";
            $stmt = $conn->prepare($transaction_sql);
            $stmt->bind_param("sid", $prs_id, $merchant_id, $total_amount);
            $stmt->execute();
            $transaction_id = $stmt->insert_id;
            $stmt->close();
            
            foreach ($verified_items as $item) {
                $item_sql = "INSERT INTO transaction_items (transaction_id, item_id, quantity, price_per_unit) 
                            VALUES (?, ?, ?, ?)";
                $stmt = $conn->prepare($item_sql);
                $stmt->bind_param("iiid", $transaction_id, $item["item_id"], $item["quantity"], $item["price_per_unit"]);
                $stmt->execute();
                $stmt->close();
                
                $new_quantity = $item["current_stock"] - $item["quantity"];
                $update_sql = "UPDATE inventory SET quantity = ? WHERE inventory_id = ?";
                $stmt = $conn->prepare($update_sql);
                $stmt->bind_param("ii", $new_quantity, $item["inventory_id"]);
                $stmt->execute();
                $stmt->close();
            }
            
            $conn->commit();
            
            log_activity($_SESSION["prs_id"], "create", "transaction", $transaction_id, "success");
            
            $response["status"] = "success";
            $response["message"] = "Transaction created successfully";
            $response["data"] = [
                "transaction_id" => $transaction_id,
                "prs_id" => $prs_id,
                "total_amount" => $total_amount,
                "items_count" => count($verified_items)
            ];
        } catch (Exception $e) {
            $conn->rollback();
            
            $response["message"] = "Transaction failed: " . $e->getMessage();
        }
    }
}
elseif ($endpoint === "items") {
    if ($request_method === "GET") {
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
        
        log_activity($_SESSION["prs_id"], "fetch", "items", "api", "success");
        
        $response["status"] = "success";
        $response["message"] = "Items retrieved successfully";
        $response["data"] = [
            "items" => $items,
            "count" => count($items)
        ];
    }
}
elseif ($endpoint === "statistics") {
    if ($request_method === "GET") {
        $stats = [
            "total_sales" => [
                "count" => 0,
                "amount" => 0
            ],
            "today_sales" => [
                "count" => 0,
                "amount" => 0
            ],
            "this_week_sales" => [
                "count" => 0,
                "amount" => 0
            ],
            "this_month_sales" => [
                "count" => 0,
                "amount" => 0
            ],
            "by_category" => [],
            "top_items" => []
        ];
        
        $sql = "SELECT COUNT(*) as count, SUM(total_amount) as amount 
               FROM transactions 
               WHERE merchant_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $merchant_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        $stats["total_sales"]["count"] = $row["count"] ?? 0;
        $stats["total_sales"]["amount"] = $row["amount"] ?? 0;
        
        $stmt->close();
        
        $sql = "SELECT COUNT(*) as count, SUM(total_amount) as amount 
               FROM transactions 
               WHERE merchant_id = ? AND DATE(transaction_date) = CURDATE()";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $merchant_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        $stats["today_sales"]["count"] = $row["count"] ?? 0;
        $stats["today_sales"]["amount"] = $row["amount"] ?? 0;
        
        $stmt->close();
        
        $sql = "SELECT COUNT(*) as count, SUM(total_amount) as amount 
               FROM transactions 
               WHERE merchant_id = ? AND YEARWEEK(transaction_date) = YEARWEEK(CURDATE())";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $merchant_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        $stats["this_week_sales"]["count"] = $row["count"] ?? 0;
        $stats["this_week_sales"]["amount"] = $row["amount"] ?? 0;
        
        $stmt->close();
        
        $sql = "SELECT COUNT(*) as count, SUM(total_amount) as amount 
               FROM transactions 
               WHERE merchant_id = ? AND MONTH(transaction_date) = MONTH(CURDATE()) 
               AND YEAR(transaction_date) = YEAR(CURDATE())";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $merchant_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        $stats["this_month_sales"]["count"] = $row["count"] ?? 0;
        $stats["this_month_sales"]["amount"] = $row["amount"] ?? 0;
        
        $stmt->close();
        
        $sql = "SELECT it.category, COUNT(ti.id) as count, SUM(ti.quantity * ti.price_per_unit) as amount 
               FROM transaction_items ti 
               JOIN items it ON ti.item_id = it.item_id 
               JOIN transactions t ON ti.transaction_id = t.transaction_id 
               WHERE t.merchant_id = ? 
               GROUP BY it.category";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $merchant_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $stats["by_category"][] = [
                "category" => $row["category"],
                "count" => $row["count"],
                "amount" => $row["amount"]
            ];
        }
        
        $stmt->close();
        $sql = "SELECT it.item_id, it.name, it.category, 
               SUM(ti.quantity) as quantity, 
               SUM(ti.quantity * ti.price_per_unit) as amount 
               FROM transaction_items ti 
               JOIN items it ON ti.item_id = it.item_id 
               JOIN transactions t ON ti.transaction_id = t.transaction_id 
               WHERE t.merchant_id = ? 
               GROUP BY it.item_id 
               ORDER BY quantity DESC 
               LIMIT 10";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $merchant_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $stats["top_items"][] = [
                "item_id" => $row["item_id"],
                "name" => $row["name"],
                "category" => $row["category"],
                "quantity" => $row["quantity"],
                "amount" => $row["amount"]
            ];
        }
        
        $stmt->close();
        
        log_activity($_SESSION["prs_id"], "fetch", "statistics", "api", "success");
        
        $response["status"] = "success";
        $response["message"] = "Statistics retrieved successfully";
        $response["data"] = $stats;
    }
}
echo json_encode($response);