<?php
session_start();

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Authentication required']);
    exit;
}

require_once "../../config/db_connect.php";

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = isset($_GET['action']) ? $_GET['action'] : '';
    
    if ($action === 'get' && isset($_GET['id'])) {
        $id = (int)$_GET['id'];
        
        $sql = "SELECT id, first_name, last_name, dob, relationship FROM family_members 
                WHERE id = ? AND prs_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("is", $id, $_SESSION["prs_id"]);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $member = $result->fetch_assoc();
            $member['dob'] = date('Y-m-d', strtotime($member['dob']));
            
            echo json_encode(['status' => 'success', 'member' => $member]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Family member not found']);
        }
        
        $stmt->close();
    } elseif ($action === 'delete' && isset($_GET['id'])) {
        $id = (int)$_GET['id'];
        
        $sql = "DELETE FROM family_members WHERE id = ? AND prs_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("is", $id, $_SESSION["prs_id"]);
        
        if ($stmt->execute()) {
            log_activity($_SESSION["prs_id"], "delete", "family_member", $id, "success");
            
            echo json_encode(['status' => 'success', 'message' => 'Family member deleted successfully']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Error deleting family member']);
        }
        
        $stmt->close();
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    }
}
elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    
    if ($action === 'add' || $action === 'edit') {
        $first_name = sanitize_input($_POST['first_name'] ?? '');
        $last_name = sanitize_input($_POST['last_name'] ?? '');
        $dob = sanitize_input($_POST['dob'] ?? '');
        $relationship = sanitize_input($_POST['relationship'] ?? '');
        
        if (empty($first_name) || empty($last_name) || empty($dob) || empty($relationship)) {
            echo json_encode(['status' => 'error', 'message' => 'All fields are required']);
            exit;
        }
        
        if ($action === 'add') {
            $sql = "INSERT INTO family_members (prs_id, first_name, last_name, dob, relationship) 
                    VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssss", $_SESSION["prs_id"], $first_name, $last_name, $dob, $relationship);
            
            if ($stmt->execute()) {
                $id = $stmt->insert_id;
                
                log_activity($_SESSION["prs_id"], "create", "family_member", $id, "success");
                
                echo json_encode(['status' => 'success', 'message' => 'Family member added successfully']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Error adding family member']);
            }
            
            $stmt->close();
        } elseif ($action === 'edit' && isset($_POST['family_id'])) {
            $id = (int)$_POST['family_id'];
            
            $check_sql = "SELECT COUNT(*) as count FROM family_members WHERE id = ? AND prs_id = ?";
            $stmt = $conn->prepare($check_sql);
            $stmt->bind_param("is", $id, $_SESSION["prs_id"]);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            
            if ($row['count'] === 0) {
                echo json_encode(['status' => 'error', 'message' => 'Family member not found or access denied']);
                $stmt->close();
                exit;
            }
            
            $stmt->close();
            
            $sql = "UPDATE family_members SET first_name = ?, last_name = ?, dob = ?, relationship = ? 
                    WHERE id = ? AND prs_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssis", $first_name, $last_name, $dob, $relationship, $id, $_SESSION["prs_id"]);
            
            if ($stmt->execute()) {
                log_activity($_SESSION["prs_id"], "update", "family_member", $id, "success");
                
                echo json_encode(['status' => 'success', 'message' => 'Family member updated successfully']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Error updating family member']);
            }
            
            $stmt->close();
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
}