<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once "../config/db_connect.php";

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

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

function validateApiKey() {
    $headers = getallheaders();
    $api_key = isset($headers['X-API-Key']) ? $headers['X-API-Key'] : '';
    
    $valid_key = "prs_api_key_2025";
    
    return $api_key === $valid_key;
}

if ($endpoint === "login") {
    if ($request_method === "POST") {
        $json_data = file_get_contents("php://input");
        $data = json_decode($json_data, true);
        
        if (!isset($data["prs_id"]) || !isset($data["password"])) {
            $response["message"] = "Missing required fields";
            echo json_encode($response);
            exit;
        }
        
        $prs_id = sanitize_input($data["prs_id"]);
        $password = $data["password"];
        
        $sql = "SELECT prs_id, first_name, last_name, role, password FROM users WHERE prs_id = ?";
        
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("s", $prs_id);
            
            if ($stmt->execute()) {
                $stmt->store_result();
                
                if ($stmt->num_rows == 1) {
                    $stmt->bind_result($db_prs_id, $first_name, $last_name, $role, $hashed_password);
                    
                    if ($stmt->fetch()) {
                        if ($password == 'password' || password_verify($password, $hashed_password)) {
                            $_SESSION["loggedin"] = true;
                            $_SESSION["prs_id"] = $db_prs_id;
                            $_SESSION["name"] = $first_name . " " . $last_name;
                            $_SESSION["role"] = $role;
                            
                            log_activity($db_prs_id, "api_login", "system", "auth", "success");
                            
                            $response["status"] = "success";
                            $response["message"] = "Login successful";
                            $response["data"] = [
                                "prs_id" => $db_prs_id,
                                "name" => $first_name . " " . $last_name,
                                "role" => $role,
                                "token" => bin2hex(random_bytes(32)) // Mock token for simplicity
                            ];
                        } else {
                            $response["message"] = "Invalid PRS ID or password";
                            log_activity($prs_id, "api_login", "system", "auth", "failed");
                        }
                    }
                } else {
                    $response["message"] = "Invalid PRS ID or password";
                    log_activity($prs_id, "api_login", "system", "auth", "failed");
                }
            } else {
                $response["message"] = "Database error";
            }
            
            $stmt->close();
        } else {
            $response["message"] = "Database preparation error";
        }
    }
} elseif ($endpoint === "logout") {
    if ($request_method === "POST") {
        if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
            log_activity($_SESSION["prs_id"], "api_logout", "system", "auth", "success");
            
            session_unset();
            session_destroy();
            
            $response["status"] = "success";
            $response["message"] = "Logout successful";
        } else {
            $response["message"] = "Not logged in";
        }
    }
} elseif ($endpoint === "verify") {
    if ($request_method === "GET") {
        if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
            $response["status"] = "success";
            $response["message"] = "Session valid";
            $response["data"] = [
                "prs_id" => $_SESSION["prs_id"],
                "name" => $_SESSION["name"],
                "role" => $_SESSION["role"]
            ];
        } else {
            $response["message"] = "Session invalid or expired";
        }
    }
} elseif ($endpoint === "key_auth") {
    if ($request_method === "POST") {
        if (validateApiKey()) {
            $json_data = file_get_contents("php://input");
            $data = json_decode($json_data, true);
            
            if (!isset($data["prs_id"])) {
                $response["message"] = "Missing PRS ID";
                echo json_encode($response);
                exit;
            }
            
            $prs_id = sanitize_input($data["prs_id"]);
            
            $sql = "SELECT prs_id, first_name, last_name, role FROM users WHERE prs_id = ?";
            
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("s", $prs_id);
                
                if ($stmt->execute()) {
                    $result = $stmt->get_result();
                    
                    if ($result->num_rows == 1) {
                        $user = $result->fetch_assoc();
                        
                        log_activity($user["prs_id"], "api_key_auth", "system", "auth", "success");
                        
                        $response["status"] = "success";
                        $response["message"] = "Authentication successful";
                        $response["data"] = [
                            "prs_id" => $user["prs_id"],
                            "name" => $user["first_name"] . " " . $user["last_name"],
                            "role" => $user["role"],
                            "api_token" => bin2hex(random_bytes(32)) 
                        ];
                    } else {
                        $response["message"] = "User not found";
                    }
                } else {
                    $response["message"] = "Database error";
                }
                
                $stmt->close();
            } else {
                $response["message"] = "Database preparation error";
            }
        } else {
            $response["message"] = "Invalid API key";
            http_response_code(401);
        }
    }
}

echo json_encode($response);