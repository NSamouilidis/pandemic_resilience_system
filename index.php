<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$api = isset($_GET["api"]) ? $_GET["api"] : "";

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

switch ($api) {
    case "auth":
        require_once "auth.php";
        break;
    
    case "public":
        require_once "public.php";
        break;
    
    case "government":
        require_once "government.php";
        break;
    
    case "merchant":
        require_once "merchant.php";
        break;
    
    default:
        $response = [
            "status" => "error",
            "message" => "Invalid API type",
            "data" => null
        ];
        
        echo json_encode($response);
}