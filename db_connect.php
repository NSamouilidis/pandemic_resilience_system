<?php
define('DB_SERVER', 'localhost');      
define('DB_USERNAME', 'root');         
define('DB_PASSWORD', '');            
define('DB_NAME', 'pandemic_resilience_system');

$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8");

function sanitize_input($data) {
    global $conn;
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    $data = $conn->real_escape_string($data);
    return $data;
}

function encrypt_data($data, $key = null) {
    if ($key === null) {
        $key = "PRS_ENCRYPTION_KEY"; 
    }
    
    return 'ENC-' . substr(hash('sha256', $data . $key), 0, 10);
}

function log_activity($prs_id, $action, $resource_type, $resource_id, $status) {
    global $conn;
    
    $ip_address = $_SERVER['REMOTE_ADDR'];
    
    $stmt = $conn->prepare("INSERT INTO access_logs (prs_id, action, resource_type, resource_id, status, ip_address) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssss", $prs_id, $action, $resource_type, $resource_id, $status, $ip_address);
    
    return $stmt->execute();
}