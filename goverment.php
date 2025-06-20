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

function validateGovernmentAccess() {
    if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true && $_SESSION["role"] === "government") {
        return true;
    }
    
    $headers = getallheaders();
    $api_key = isset($headers['X-API-Key']) ? $headers['X-API-Key'] : '';
    
    $valid_key = "prs_gov_api_key_2025";
    
    return $api_key === $valid_key;
}

if (!validateGovernmentAccess()) {
    $response["message"] = "Unauthorized: Government access required";
    http_response_code(403);
    echo json_encode($response);
    exit;
}

if ($endpoint === "users") {
    if ($request_method === "GET") {
        $role = isset($_GET["role"]) ? sanitize_input($_GET["role"]) : null;
        $search = isset($_GET["search"]) ? sanitize_input($_GET["search"]) : null;
        $limit = isset($_GET["limit"]) ? (int)$_GET["limit"] : 100;
        $offset = isset($_GET["offset"]) ? (int)$_GET["offset"] : 0;
        
        $sql = "SELECT prs_id, first_name, last_name, email, phone, city, role, created_at 
                FROM users WHERE 1=1";
        
        $params = [];
        $types = "";
        
        if ($role !== null) {
            $sql .= " AND role = ?";
            $params[] = $role;
            $types .= "s";
        }
        
        if ($search !== null) {
            $sql .= " AND (prs_id LIKE ? OR first_name LIKE ? OR last_name LIKE ? OR email LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $types .= "ssss";
        }
        $count_sql = str_replace("SELECT prs_id, first_name, last_name, email, phone, city, role, created_at", "SELECT COUNT(*) as total", $sql);
        $total = 0;
        
        if (count($params) > 0) {
            $count_stmt = $conn->prepare($count_sql);
            $count_stmt->bind_param($types, ...$params);
            $count_stmt->execute();
            $count_result = $count_stmt->get_result();
            $count_row = $count_result->fetch_assoc();
            $total = $count_row["total"];
            $count_stmt->close();
        } else {
            $count_result = $conn->query($count_sql);
            $count_row = $count_result->fetch_assoc();
            $total = $count_row["total"];
        }
        
        $sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        $types .= "ii";
        
        $users = [];
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
        
        $stmt->close();
        
        log_activity($_SESSION["prs_id"], "fetch", "users", "api", "success");
        
        $response["status"] = "success";
        $response["message"] = "Users retrieved successfully";
        $response["data"] = [
            "users" => $users,
            "count" => count($users),
            "total" => $total,
            "limit" => $limit,
            "offset" => $offset
        ];
    }
    elseif ($request_method === "POST") {
        $json_data = file_get_contents("php://input");
        $data = json_decode($json_data, true);
        
        $required_fields = ["national_id", "first_name", "last_name", "dob", "email", "role", "password"];
        foreach ($required_fields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $response["message"] = "Missing required field: $field";
                echo json_encode($response);
                exit;
            }
        }
        
        $national_id = sanitize_input($data["national_id"]);
        $first_name = sanitize_input($data["first_name"]);
        $last_name = sanitize_input($data["last_name"]);
        $dob = sanitize_input($data["dob"]);
        $email = sanitize_input($data["email"]);
        $phone = isset($data["phone"]) ? sanitize_input($data["phone"]) : "";
        $address = isset($data["address"]) ? sanitize_input($data["address"]) : "";
        $city = isset($data["city"]) ? sanitize_input($data["city"]) : "";
        $postal_code = isset($data["postal_code"]) ? sanitize_input($data["postal_code"]) : "";
        $role = sanitize_input($data["role"]);
        $password = $data["password"];
        
        $email_check = "SELECT COUNT(*) as count FROM users WHERE email = ?";
        $stmt = $conn->prepare($email_check);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        if ($row["count"] > 0) {
            $response["message"] = "Email already in use";
            $stmt->close();
            echo json_encode($response);
            exit;
        }
        
        $stmt->close();
        
        $prefix = "PRS-";
        switch ($role) {
            case 'government':
                $prefix .= "GOV-";
                break;
            case 'merchant':
                $prefix .= "MER-";
                break;
            default:
                $prefix .= "PUB-";
        }
        
        $last_id_sql = "SELECT prs_id FROM users WHERE prs_id LIKE ? ORDER BY prs_id DESC LIMIT 1";
        $stmt = $conn->prepare($last_id_sql);
        $prefix_param = $prefix . "%";
        $stmt->bind_param("s", $prefix_param);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $next_number = 1;
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $last_id = $row["prs_id"];
            $last_number = intval(substr($last_id, -3));
            $next_number = $last_number + 1;
        }
        
        $stmt->close();
        
        $prs_id = $prefix . str_pad($next_number, 3, "0", STR_PAD_LEFT);
        
        $encrypted_national_id = encrypt_data($national_id);
        
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        $insert_sql = "INSERT INTO users (prs_id, national_id, first_name, last_name, dob, email, phone, address, city, postal_code, role, password) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($insert_sql);
        $stmt->bind_param("ssssssssssss", $prs_id, $encrypted_national_id, $first_name, $last_name, $dob, $email, $phone, $address, $city, $postal_code, $role, $hashed_password);
        
        if ($stmt->execute()) {
            log_activity($_SESSION["prs_id"], "create", "user", $prs_id, "success");
            
            $response["status"] = "success";
            $response["message"] = "User created successfully";
            $response["data"] = [
                "prs_id" => $prs_id,
                "name" => $first_name . " " . $last_name,
                "role" => $role
            ];
        } else {
            $response["message"] = "Error creating user: " . $stmt->error;
        }
        
        $stmt->close();
    }
    elseif ($request_method === "PUT") {
        if (!isset($_GET["prs_id"])) {
            $response["message"] = "Missing PRS ID";
            echo json_encode($response);
            exit;
        }
        
        $prs_id = sanitize_input($_GET["prs_id"]);
        
        $json_data = file_get_contents("php://input");
        $data = json_decode($json_data, true);
        
        $sql = "UPDATE users SET ";
        $params = [];
        $types = "";
        $updates = [];
        
        if (isset($data["first_name"])) {
            $updates[] = "first_name = ?";
            $params[] = sanitize_input($data["first_name"]);
            $types .= "s";
        }
        
        if (isset($data["last_name"])) {
            $updates[] = "last_name = ?";
            $params[] = sanitize_input($data["last_name"]);
            $types .= "s";
        }
        
        if (isset($data["email"])) {
            $updates[] = "email = ?";
            $params[] = sanitize_input($data["email"]);
            $types .= "s";
        }
        
        if (isset($data["phone"])) {
            $updates[] = "phone = ?";
            $params[] = sanitize_input($data["phone"]);
            $types .= "s";
        }
        
        if (isset($data["address"])) {
            $updates[] = "address = ?";
            $params[] = sanitize_input($data["address"]);
            $types .= "s";
        }
        
        if (isset($data["city"])) {
            $updates[] = "city = ?";
            $params[] = sanitize_input($data["city"]);
            $types .= "s";
        }
        
        if (isset($data["postal_code"])) {
            $updates[] = "postal_code = ?";
            $params[] = sanitize_input($data["postal_code"]);
            $types .= "s";
        }
        
        if (isset($data["role"])) {
            $updates[] = "role = ?";
            $params[] = sanitize_input($data["role"]);
            $types .= "s";
        }
        
        if (isset($data["password"])) {
            $updates[] = "password = ?";
            $hashed_password = password_hash($data["password"], PASSWORD_DEFAULT);
            $params[] = $hashed_password;
            $types .= "s";
        }
        
        if (count($updates) == 0) {
            $response["message"] = "No fields to update";
            echo json_encode($response);
            exit;
        }
        
        $sql .= implode(", ", $updates);
        $sql .= " WHERE prs_id = ?";
        $params[] = $prs_id;
        $types .= "s";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        
        if ($stmt->execute()) {
            log_activity($_SESSION["prs_id"], "update", "user", $prs_id, "success");
            
            $response["status"] = "success";
            $response["message"] = "User updated successfully";
            $response["data"] = [
                "prs_id" => $prs_id,
                "updated_fields" => array_keys($data)
            ];
        } else {
            $response["message"] = "Error updating user: " . $stmt->error;
        }
        
        $stmt->close();
    }
}
elseif ($endpoint === "merchants") {
    if ($request_method === "GET") {
        $status = isset($_GET["status"]) ? sanitize_input($_GET["status"]) : null;
        $search = isset($_GET["search"]) ? sanitize_input($_GET["search"]) : null;
        $limit = isset($_GET["limit"]) ? (int)$_GET["limit"] : 100;
        $offset = isset($_GET["offset"]) ? (int)$_GET["offset"] : 0;
        
        $sql = "SELECT m.merchant_id, m.prs_id, m.business_name, m.business_type, 
                m.license_number, m.status, u.first_name, u.last_name, u.email, 
                u.phone, u.city, u.created_at 
                FROM merchants m 
                JOIN users u ON m.prs_id = u.prs_id 
                WHERE 1=1";
        
        $params = [];
        $types = "";
        
        if ($status !== null) {
            $sql .= " AND m.status = ?";
            $params[] = $status;
            $types .= "s";
        }
        
        if ($search !== null) {
            $sql .= " AND (m.business_name LIKE ? OR m.prs_id LIKE ? OR 
                    u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $types .= "sssss";
        }
        
        $count_sql = str_replace("SELECT m.merchant_id, m.prs_id, m.business_name, m.business_type, 
                m.license_number, m.status, u.first_name, u.last_name, u.email, 
                u.phone, u.city, u.created_at", "SELECT COUNT(*) as total", $sql);
        
        $total = 0;
        
        if (count($params) > 0) {
            $count_stmt = $conn->prepare($count_sql);
            $count_stmt->bind_param($types, ...$params);
            $count_stmt->execute();
            $count_result = $count_stmt->get_result();
            $count_row = $count_result->fetch_assoc();
            $total = $count_row["total"];
            $count_stmt->close();
        } else {
            $count_result = $conn->query($count_sql);
            $count_row = $count_result->fetch_assoc();
            $total = $count_row["total"];
        }
        
        $sql .= " ORDER BY u.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        $types .= "ii";
        
        $merchants = [];
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $merchants[] = $row;
        }
        
        $stmt->close();
        
        log_activity($_SESSION["prs_id"], "fetch", "merchants", "api", "success");
        
        $response["status"] = "success";
        $response["message"] = "Merchants retrieved successfully";
        $response["data"] = [
            "merchants" => $merchants,
            "count" => count($merchants),
            "total" => $total,
            "limit" => $limit,
            "offset" => $offset
        ];
    }
    elseif ($request_method === "PUT") {
        if (!isset($_GET["merchant_id"])) {
            $response["message"] = "Missing merchant ID";
            echo json_encode($response);
            exit;
        }
        
        $merchant_id = (int)$_GET["merchant_id"];
        
        $json_data = file_get_contents("php://input");
        $data = json_decode($json_data, true);
        
        if (!isset($data["status"]) || !in_array($data["status"], ["active", "suspended", "pending"])) {
            $response["message"] = "Invalid or missing status field";
            echo json_encode($response);
            exit;
        }
        
        $status = sanitize_input($data["status"]);
        
        $sql = "UPDATE merchants SET status = ? WHERE merchant_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $status, $merchant_id);
        
        if ($stmt->execute()) {
            $sql = "SELECT prs_id FROM merchants WHERE merchant_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $merchant_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $prs_id = $row["prs_id"];
            

            log_activity($_SESSION["prs_id"], "update", "merchant_status", $prs_id, "success");
            
            $response["status"] = "success";
            $response["message"] = "Merchant status updated successfully";
            $response["data"] = [
                "merchant_id" => $merchant_id,
                "status" => $status
            ];
        } else {
            $response["message"] = "Error updating merchant status: " . $stmt->error;
        }
        
        $stmt->close();
    }
}
elseif ($endpoint === "inventory") {
    if ($request_method === "GET") {
        $merchant_id = isset($_GET["merchant_id"]) ? (int)$_GET["merchant_id"] : null;
        $category = isset($_GET["category"]) ? sanitize_input($_GET["category"]) : null;
        $low_stock = isset($_GET["low_stock"]) ? (int)$_GET["low_stock"] : null;
        
        $sql = "SELECT i.inventory_id, i.merchant_id, m.business_name, i.item_id, 
                it.name as item_name, it.category, i.quantity, i.price, 
                it.rationing_limit 
                FROM inventory i 
                JOIN items it ON i.item_id = it.item_id 
                JOIN merchants m ON i.merchant_id = m.merchant_id 
                WHERE 1=1";
        
        $params = [];
        $types = "";
        
        if ($merchant_id !== null) {
            $sql .= " AND i.merchant_id = ?";
            $params[] = $merchant_id;
            $types .= "i";
        }
        
        if ($category !== null) {
            $sql .= " AND it.category = ?";
            $params[] = $category;
            $types .= "s";
        }
        
        if ($low_stock !== null) {
            $sql .= " AND i.quantity <= ?";
            $params[] = $low_stock;
            $types .= "i";
        }
        
        $sql .= " ORDER BY m.business_name, it.category, it.name";
        
        $inventory = [];
        
        if (count($params) > 0) {
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $inventory[] = $row;
            }
            
            $stmt->close();
        } else {
            $result = $conn->query($sql);
            
            while ($row = $result->fetch_assoc()) {
                $inventory[] = $row;
            }
        }
        
        log_activity($_SESSION["prs_id"], "fetch", "inventory", "api", "success");
        
        $response["status"] = "success";
        $response["message"] = "Inventory retrieved successfully";
        $response["data"] = [
            "inventory" => $inventory,
            "count" => count($inventory)
        ];
    }
}
elseif ($endpoint === "vaccinations") {
    if ($request_method === "GET") {
        $prs_id = isset($_GET["prs_id"]) ? sanitize_input($_GET["prs_id"]) : null;
        $vaccine = isset($_GET["vaccine"]) ? sanitize_input($_GET["vaccine"]) : null;
        $from_date = isset($_GET["from_date"]) ? sanitize_input($_GET["from_date"]) : null;
        $to_date = isset($_GET["to_date"]) ? sanitize_input($_GET["to_date"]) : null;
        
        $sql = "SELECT v.vaccination_id, v.prs_id, u.first_name, u.last_name, 
                v.vaccine_name, v.vaccination_date, v.facility_name, v.batch_number, 
                v.certificate_file 
                FROM vaccinations v 
                JOIN users u ON v.prs_id = u.prs_id 
                WHERE 1=1";
        
        $params = [];
        $types = "";
        
        if ($prs_id !== null) {
            $sql .= " AND v.prs_id = ?";
            $params[] = $prs_id;
            $types .= "s";
        }
        
        if ($vaccine !== null) {
            $sql .= " AND v.vaccine_name LIKE ?";
            $params[] = "%$vaccine%";
            $types .= "s";
        }
        
        if ($from_date !== null) {
            $sql .= " AND v.vaccination_date >= ?";
            $params[] = $from_date;
            $types .= "s";
        }
        
        if ($to_date !== null) {
            $sql .= " AND v.vaccination_date <= ?";
            $params[] = $to_date;
            $types .= "s";
        }
        
        $sql .= " ORDER BY v.vaccination_date DESC";
        
        $vaccinations = [];
        
        if (count($params) > 0) {
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $vaccinations[] = $row;
            }
            
            $stmt->close();
        } else {
            $result = $conn->query($sql);
            
            while ($row = $result->fetch_assoc()) {
                $vaccinations[] = $row;
            }
        }
        
        log_activity($_SESSION["prs_id"], "fetch", "vaccinations", "api", "success");
        
        $response["status"] = "success";
        $response["message"] = "Vaccinations retrieved successfully";
        $response["data"] = [
            "vaccinations" => $vaccinations,
            "count" => count($vaccinations)
        ];
    }
    elseif ($request_method === "POST") {
        $json_data = file_get_contents("php://input");
        $data = json_decode($json_data, true);
        
        $required_fields = ["prs_id", "vaccine_name", "vaccination_date", "facility_name", "batch_number"];
        foreach ($required_fields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $response["message"] = "Missing required field: $field";
                echo json_encode($response);
                exit;
            }
        }
        
        $prs_id = sanitize_input($data["prs_id"]);
        $vaccine_name = sanitize_input($data["vaccine_name"]);
        $vaccination_date = sanitize_input($data["vaccination_date"]);
        $facility_name = sanitize_input($data["facility_name"]);
        $batch_number = sanitize_input($data["batch_number"]);
        $certificate_file = isset($data["certificate_file"]) ? sanitize_input($data["certificate_file"]) : null;
        
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
        
        $insert_sql = "INSERT INTO vaccinations (prs_id, vaccine_name, vaccination_date, facility_name, batch_number, certificate_file) 
                      VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($insert_sql);
        $stmt->bind_param("ssssss", $prs_id, $vaccine_name, $vaccination_date, $facility_name, $batch_number, $certificate_file);
        
        if ($stmt->execute()) {
            $vaccination_id = $stmt->insert_id;
            
            log_activity($_SESSION["prs_id"], "create", "vaccination", $prs_id, "success");
            
            $response["status"] = "success";
            $response["message"] = "Vaccination record created successfully";
            $response["data"] = [
                "vaccination_id" => $vaccination_id,
                "prs_id" => $prs_id,
                "vaccine_name" => $vaccine_name,
                "vaccination_date" => $vaccination_date
            ];
        } else {
            $response["message"] = "Error creating vaccination record: " . $stmt->error;
        }
        
        $stmt->close();
    }
}
elseif ($endpoint === "statistics") {
    if ($request_method === "GET") {
        $type = isset($_GET["type"]) ? sanitize_input($_GET["type"]) : "general";
        
        if ($type === "general") {
            $stats = [
                "users" => [
                    "total" => 0,
                    "public" => 0,
                    "merchants" => 0,
                    "government" => 0
                ],
                "vaccinations" => [
                    "total" => 0,
                    "percentage" => 0
                ],
                "merchants" => [
                    "total" => 0,
                    "active" => 0,
                    "pending" => 0,
                    "suspended" => 0
                ],
                "inventory" => [
                    "total_items" => 0,
                    "essential_items" => 0,
                    "medical_items" => 0,
                    "restricted_items" => 0
                ],
                "transactions" => [
                    "total" => 0,
                    "today" => 0,
                    "this_week" => 0,
                    "this_month" => 0
                ]
            ];
            
            $sql = "SELECT role, COUNT(*) as count FROM users GROUP BY role";
            $result = $conn->query($sql);
            
            $stats["users"]["total"] = 0;
            while ($row = $result->fetch_assoc()) {
                $role = $row["role"];
                $count = $row["count"];
                $stats["users"]["total"] += $count;
                
                if ($role === "public") {
                    $stats["users"]["public"] = $count;
                } elseif ($role === "merchant") {
                    $stats["users"]["merchants"] = $count;
                } elseif ($role === "government") {
                    $stats["users"]["government"] = $count;
                }
            }
            
            $sql = "SELECT COUNT(*) as count FROM vaccinations";
            $result = $conn->query($sql);
            $row = $result->fetch_assoc();
            $stats["vaccinations"]["total"] = $row["count"];
            
            $sql = "SELECT COUNT(DISTINCT prs_id) as count FROM vaccinations";
            $result = $conn->query($sql);
            $row = $result->fetch_assoc();
            $vaccinated_users = $row["count"];
            
            if ($stats["users"]["public"] > 0) {
                $stats["vaccinations"]["percentage"] = round(($vaccinated_users / $stats["users"]["public"]) * 100, 2);
            }
            
            $sql = "SELECT status, COUNT(*) as count FROM merchants GROUP BY status";
            $result = $conn->query($sql);
            
            $stats["merchants"]["total"] = 0;
            while ($row = $result->fetch_assoc()) {
                $status = $row["status"];
                $count = $row["count"];
                $stats["merchants"]["total"] += $count;
                
                if ($status === "active") {
                    $stats["merchants"]["active"] = $count;
                } elseif ($status === "pending") {
                    $stats["merchants"]["pending"] = $count;
                } elseif ($status === "suspended") {
                    $stats["merchants"]["suspended"] = $count;
                }
            }
            
            $sql = "SELECT it.category, COUNT(*) as count 
                   FROM inventory i 
                   JOIN items it ON i.item_id = it.item_id 
                   GROUP BY it.category";
            $result = $conn->query($sql);
            
            $stats["inventory"]["total_items"] = 0;
            while ($row = $result->fetch_assoc()) {
                $category = $row["category"];
                $count = $row["count"];
                $stats["inventory"]["total_items"] += $count;
                
                if ($category === "essential") {
                    $stats["inventory"]["essential_items"] = $count;
                } elseif ($category === "medical") {
                    $stats["inventory"]["medical_items"] = $count;
                } elseif ($category === "restricted") {
                    $stats["inventory"]["restricted_items"] = $count;
                }
            }
            
            $sql = "SELECT COUNT(*) as count FROM transactions";
            $result = $conn->query($sql);
            $row = $result->fetch_assoc();
            $stats["transactions"]["total"] = $row["count"];
            
            $sql = "SELECT COUNT(*) as count FROM transactions 
                   WHERE DATE(transaction_date) = CURDATE()";
            $result = $conn->query($sql);
            $row = $result->fetch_assoc();
            $stats["transactions"]["today"] = $row["count"];
            
            $sql = "SELECT COUNT(*) as count FROM transactions 
                   WHERE YEARWEEK(transaction_date) = YEARWEEK(CURDATE())";
            $result = $conn->query($sql);
            $row = $result->fetch_assoc();
            $stats["transactions"]["this_week"] = $row["count"];
            
            $sql = "SELECT COUNT(*) as count FROM transactions 
                   WHERE MONTH(transaction_date) = MONTH(CURDATE()) 
                   AND YEAR(transaction_date) = YEAR(CURDATE())";
            $result = $conn->query($sql);
            $row = $result->fetch_assoc();
            $stats["transactions"]["this_month"] = $row["count"];
            
            log_activity($_SESSION["prs_id"], "fetch", "statistics", "general", "success");
            
            $response["status"] = "success";
            $response["message"] = "Statistics retrieved successfully";
            $response["data"] = $stats;
        }
        elseif ($type === "vaccinations") {
            $stats = [
                "total_count" => 0,
                "vaccinated_users" => 0,
                "vaccination_percentage" => 0,
                "by_vaccine" => [],
                "by_facility" => [],
                "by_month" => []
            ];
            
            $sql = "SELECT COUNT(*) as total FROM vaccinations";
            $result = $conn->query($sql);
            $row = $result->fetch_assoc();
            $stats["total_count"] = $row["total"];
            
            $sql = "SELECT COUNT(DISTINCT prs_id) as count FROM vaccinations";
            $result = $conn->query($sql);
            $row = $result->fetch_assoc();
            $stats["vaccinated_users"] = $row["count"];
            
            $sql = "SELECT COUNT(*) as count FROM users WHERE role = 'public'";
            $result = $conn->query($sql);
            $row = $result->fetch_assoc();
            $public_user_count = $row["count"];
            
            if ($public_user_count > 0) {
                $stats["vaccination_percentage"] = round(($stats["vaccinated_users"] / $public_user_count) * 100, 2);
            }
            
            $sql = "SELECT vaccine_name, COUNT(*) as count 
                   FROM vaccinations 
                   GROUP BY vaccine_name 
                   ORDER BY count DESC";
            $result = $conn->query($sql);
            
            while ($row = $result->fetch_assoc()) {
                $stats["by_vaccine"][] = [
                    "vaccine" => $row["vaccine_name"],
                    "count" => $row["count"]
                ];
            }
            
            $sql = "SELECT facility_name, COUNT(*) as count 
                   FROM vaccinations 
                   GROUP BY facility_name 
                   ORDER BY count DESC 
                   LIMIT 10";
            $result = $conn->query($sql);
            
            while ($row = $result->fetch_assoc()) {
                $stats["by_facility"][] = [
                    "facility" => $row["facility_name"],
                    "count" => $row["count"]
                ];
            }
            
            $sql = "SELECT DATE_FORMAT(vaccination_date, '%Y-%m') as month, 
                   COUNT(*) as count 
                   FROM vaccinations 
                   GROUP BY month 
                   ORDER BY month DESC 
                   LIMIT 12";
            $result = $conn->query($sql);
            
            while ($row = $result->fetch_assoc()) {
                $stats["by_month"][] = [
                    "month" => $row["month"],
                    "count" => $row["count"]
                ];
            }
            
            log_activity($_SESSION["prs_id"], "fetch", "statistics", "vaccinations", "success");
            
            $response["status"] = "success";
            $response["message"] = "Vaccination statistics retrieved successfully";
            $response["data"] = $stats;
        }
        elseif ($type === "inventory") {
            $stats = [
                "total_items" => 0,
                "by_category" => [],
                "low_stock" => 0,
                "by_merchant" => [],
                "most_stocked" => [],
                "least_stocked" => []
            ];
            
            $sql = "SELECT SUM(quantity) as total FROM inventory";
            $result = $conn->query($sql);
            $row = $result->fetch_assoc();
            $stats["total_items"] = $row["total"] ?? 0;
            
            $sql = "SELECT it.category, SUM(i.quantity) as count 
                   FROM inventory i 
                   JOIN items it ON i.item_id = it.item_id 
                   GROUP BY it.category";
            $result = $conn->query($sql);
            
            while ($row = $result->fetch_assoc()) {
                $stats["by_category"][] = [
                    "category" => $row["category"],
                    "count" => $row["count"]
                ];
            }
            
            $sql = "SELECT COUNT(*) as count FROM inventory WHERE quantity < 10";
            $result = $conn->query($sql);
            $row = $result->fetch_assoc();
            $stats["low_stock"] = $row["count"];
            
            $sql = "SELECT m.business_name, SUM(i.quantity) as count 
                   FROM inventory i 
                   JOIN merchants m ON i.merchant_id = m.merchant_id 
                   GROUP BY m.merchant_id 
                   ORDER BY count DESC 
                   LIMIT 10";
            $result = $conn->query($sql);
            
            while ($row = $result->fetch_assoc()) {
                $stats["by_merchant"][] = [
                    "merchant" => $row["business_name"],
                    "count" => $row["count"]
                ];
            }
            
            $sql = "SELECT it.name, it.category, SUM(i.quantity) as total 
                   FROM inventory i 
                   JOIN items it ON i.item_id = it.item_id 
                   GROUP BY it.item_id 
                   ORDER BY total DESC 
                   LIMIT 10";
            $result = $conn->query($sql);
            
            while ($row = $result->fetch_assoc()) {
                $stats["most_stocked"][] = [
                    "item" => $row["name"],
                    "category" => $row["category"],
                    "count" => $row["total"]
                ];
            }
            $sql = "SELECT it.name, it.category, SUM(i.quantity) as total 
                   FROM inventory i 
                   JOIN items it ON i.item_id = it.item_id 
                   WHERE i.quantity > 0 
                   GROUP BY it.item_id 
                   ORDER BY total ASC 
                   LIMIT 10";
            $result = $conn->query($sql);
            
            while ($row = $result->fetch_assoc()) {
                $stats["least_stocked"][] = [
                    "item" => $row["name"],
                    "category" => $row["category"],
                    "count" => $row["total"]
                ];
            }
            
            log_activity($_SESSION["prs_id"], "fetch", "statistics", "inventory", "success");
            
            $response["status"] = "success";
            $response["message"] = "Inventory statistics retrieved successfully";
            $response["data"] = $stats;
        }
    }
}

echo json_encode($response);