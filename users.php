<?php
// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: ../../login.php");
    exit;
}

// Check if role is correct
if ($_SESSION["role"] !== "government") {
    header("location: ../../index.php");
    exit;
}

// Include database connection
require_once "../../config/db_connect.php";

// Process user actions if any
$action_message = "";
$action_status = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["action"])) {
    $action = $_POST["action"];
    $prs_id = isset($_POST["prs_id"]) ? sanitize_input($_POST["prs_id"]) : "";
    
    if ($action == "edit") {
        // Edit user details
        if (empty($prs_id)) {
            $action_message = "PRS ID is required.";
            $action_status = "danger";
        } else {
            $sql = "SELECT * FROM users WHERE prs_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $prs_id);
            $stmt->execute();
            $original_user = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            if (!$original_user) {
                $action_message = "User not found.";
                $action_status = "danger";
            } else {
                $sql = "UPDATE users SET ";
                $params = [];
                $types = "";
                $updates = [];
                
                if (isset($_POST["first_name"]) && $_POST["first_name"] != $original_user["first_name"]) {
                    $updates[] = "first_name = ?";
                    $params[] = sanitize_input($_POST["first_name"]);
                    $types .= "s";
                }
                
                if (isset($_POST["last_name"]) && $_POST["last_name"] != $original_user["last_name"]) {
                    $updates[] = "last_name = ?";
                    $params[] = sanitize_input($_POST["last_name"]);
                    $types .= "s";
                }
                
                if (isset($_POST["email"]) && $_POST["email"] != $original_user["email"]) {
                    $email_check = "SELECT COUNT(*) as count FROM users WHERE email = ? AND prs_id != ?";
                    $stmt = $conn->prepare($email_check);
                    $email = sanitize_input($_POST["email"]);
                    $stmt->bind_param("ss", $email, $prs_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $row = $result->fetch_assoc();
                    
                    if ($row["count"] > 0) {
                        $action_message = "Email already in use by another user.";
                        $action_status = "danger";
                        $stmt->close();
                    } else {
                        $updates[] = "email = ?";
                        $params[] = $email;
                        $types .= "s";
                        $stmt->close();
                    }
                }
                
                if (isset($_POST["phone"]) && $_POST["phone"] != $original_user["phone"]) {
                    $updates[] = "phone = ?";
                    $params[] = sanitize_input($_POST["phone"]);
                    $types .= "s";
                }
                
                if (isset($_POST["address"]) && $_POST["address"] != $original_user["address"]) {
                    $updates[] = "address = ?";
                    $params[] = sanitize_input($_POST["address"]);
                    $types .= "s";
                }
                
                if (isset($_POST["city"]) && $_POST["city"] != $original_user["city"]) {
                    $updates[] = "city = ?";
                    $params[] = sanitize_input($_POST["city"]);
                    $types .= "s";
                }
                
                if (isset($_POST["postal_code"]) && $_POST["postal_code"] != $original_user["postal_code"]) {
                    $updates[] = "postal_code = ?";
                    $params[] = sanitize_input($_POST["postal_code"]);
                    $types .= "s";
                }
                
                if (isset($_POST["role"]) && $_POST["role"] != $original_user["role"]) {
                    $updates[] = "role = ?";
                    $params[] = sanitize_input($_POST["role"]);
                    $types .= "s";
                }
                
                if (isset($_POST["status"]) && $_POST["status"] != $original_user["status"]) {
                    $updates[] = "status = ?";
                    $params[] = sanitize_input($_POST["status"]);
                    $types .= "s";
                }
                
                if (isset($_POST["new_password"]) && !empty($_POST["new_password"])) {
                    $updates[] = "password = ?";
                    $params[] = password_hash($_POST["new_password"], PASSWORD_DEFAULT);
                    $types .= "s";
                }
                
                if (count($updates) > 0 && empty($action_message)) {
                    $sql .= implode(", ", $updates);
                    $sql .= " WHERE prs_id = ?";
                    $params[] = $prs_id;
                    $types .= "s";
                    
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param($types, ...$params);
                    
                    if ($stmt->execute()) {
                        $action_message = "User updated successfully.";
                        $action_status = "success";
                        log_activity($_SESSION["prs_id"], "update", "user", $prs_id, "success");
                    } else {
                        $action_message = "Error updating user: " . $stmt->error;
                        $action_status = "danger";
                    }
                    
                    $stmt->close();
                } elseif (empty($action_message)) {
                    $action_message = "No changes made.";
                    $action_status = "info";
                }
            }
        }
    } else {
        // Other actions (activate, suspend, delete)
        if (!empty($prs_id)) {
            switch ($action) {
                case "activate":
                    // Check if the column exists first
                    $check_sql = "SHOW COLUMNS FROM users LIKE 'status'";
                    $check_result = $conn->query($check_sql);
                    
                    if ($check_result->num_rows > 0) {
                        // Update user status
                        $sql = "UPDATE users SET status = 'active' WHERE prs_id = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("s", $prs_id);
                        
                        if ($stmt->execute()) {
                            $action_message = "User activated successfully.";
                            $action_status = "success";
                            
                            // Log activity
                            log_activity($_SESSION["prs_id"], "activate", "user", $prs_id, "success");
                        } else {
                            $action_message = "Error activating user.";
                            $action_status = "danger";
                        }
                        
                        $stmt->close();
                    } else {
                        $action_message = "Unable to activate user. Status column does not exist.";
                        $action_status = "warning";
                    }
                    break;
                    
                case "suspend":
                    // Check if the column exists first
                    $check_sql = "SHOW COLUMNS FROM users LIKE 'status'";
                    $check_result = $conn->query($check_sql);
                    
                    if ($check_result->num_rows > 0) {
                        // Update user status
                        $sql = "UPDATE users SET status = 'suspended' WHERE prs_id = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("s", $prs_id);
                        
                        if ($stmt->execute()) {
                            $action_message = "User suspended successfully.";
                            $action_status = "success";
                            
                            // Log activity
                            log_activity($_SESSION["prs_id"], "suspend", "user", $prs_id, "success");
                        } else {
                            $action_message = "Error suspending user.";
                            $action_status = "danger";
                        }
                        
                        $stmt->close();
                    } else {
                        $action_message = "Unable to suspend user. Status column does not exist.";
                        $action_status = "warning";
                    }
                    break;
                    
                case "delete":
                    // Check if user can be deleted (no dependent records)
                    $can_delete = true;
                    
                    // Check for vaccinations
                    $sql = "SELECT COUNT(*) as count FROM vaccinations WHERE prs_id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("s", $prs_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $row = $result->fetch_assoc();
                    
                    if ($row["count"] > 0) {
                        $can_delete = false;
                    }
                    
                    $stmt->close();
                    
                    // Check for transactions
                    if ($can_delete) {
                        $sql = "SELECT COUNT(*) as count FROM transactions WHERE prs_id = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("s", $prs_id);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $row = $result->fetch_assoc();
                        
                        if ($row["count"] > 0) {
                            $can_delete = false;
                        }
                        
                        $stmt->close();
                    }
                    
                    // If user can be deleted
                    if ($can_delete) {
                        $sql = "DELETE FROM users WHERE prs_id = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("s", $prs_id);
                        
                        if ($stmt->execute()) {
                            $action_message = "User deleted successfully.";
                            $action_status = "success";
                            
                            // Log activity
                            log_activity($_SESSION["prs_id"], "delete", "user", $prs_id, "success");
                        } else {
                            $action_message = "Error deleting user.";
                            $action_status = "danger";
                        }
                        
                        $stmt->close();
                    } else {
                        $action_message = "Cannot delete user. User has related records.";
                        $action_status = "warning";
                    }
                    
                    break;
            }
        } else {
            $action_message = "Invalid user ID.";
            $action_status = "danger";
        }
    }
}

// Get users list with pagination
$search = isset($_GET["search"]) ? sanitize_input($_GET["search"]) : "";
$role_filter = isset($_GET["role"]) ? sanitize_input($_GET["role"]) : "";
$page = isset($_GET["page"]) ? (int)$_GET["page"] : 1;
$items_per_page = 10;
$offset = ($page - 1) * $items_per_page;

// Check if status column exists
$has_status_column = false;
$check_sql = "SHOW COLUMNS FROM users LIKE 'status'";
$check_result = $conn->query($check_sql);
if ($check_result->num_rows > 0) {
    $has_status_column = true;
}

// Build query - modified to exclude status if it doesn't exist
if ($has_status_column) {
    $sql = "SELECT prs_id, first_name, last_name, email, role, status, created_at FROM users WHERE 1=1";
} else {
    $sql = "SELECT prs_id, first_name, last_name, email, role, created_at FROM users WHERE 1=1";
}
$count_sql = "SELECT COUNT(*) as total FROM users WHERE 1=1";

$params = [];
$types = "";

if (!empty($search)) {
    $search_condition = " AND (prs_id LIKE ? OR first_name LIKE ? OR last_name LIKE ? OR email LIKE ?)";
    $sql .= $search_condition;
    $count_sql .= $search_condition;
    
    $search_param = "%" . $search . "%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
    $types .= "ssss";
}

if (!empty($role_filter)) {
    $role_condition = " AND role = ?";
    $sql .= $role_condition;
    $count_sql .= $role_condition;
    
    $params[] = $role_filter;
    $types .= "s";
}

// Add sorting and pagination
$sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
$params[] = $items_per_page;
$params[] = $offset;
$types .= "ii";

// Get total count for pagination
$total_users = 0;
if (!empty($types) && strlen($types) > 2) {
    $count_stmt = $conn->prepare($count_sql);
    $count_stmt->bind_param(substr($types, 0, -2), ...array_slice($params, 0, -2));
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $count_row = $count_result->fetch_assoc();
    // Get total count for pagination
$total_users = 0;
}
if (!empty($types) && strlen($types) > 2) {
    $count_stmt = $conn->prepare($count_sql);
    $count_stmt->bind_param(substr($types, 0, -2), ...array_slice($params, 0, -2));
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $count_row = $count_result->fetch_assoc();
    $total_users = $count_row["total"];
    $count_stmt->close();
} else {
    $count_result = $conn->query($count_sql);
    $count_row = $count_result->fetch_assoc();
    $total_users = $count_row["total"];
}

// Calculate total pages
$total_pages = ceil($total_users / $items_per_page);

// Get users
$users = [];
$stmt = $conn->prepare($sql);

if (!empty($types)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}

$stmt->close();

// Log page access
log_activity($_SESSION["prs_id"], "view", "users", "page", "success");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Government Dashboard</title>
    <link rel="stylesheet" href="../../assets/css/main.css">
    <link rel="stylesheet" href="../../assets/css/gov-dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
</head>
<body>
    <header>
        <div class="logo-container">
            <img src="../../assets/images/logo.png" alt="PRS Logo" class="logo">
            <h1>Pandemic Resilience System</h1>
        </div>
        <div class="user-menu">
            <span>Welcome, <?php echo htmlspecialchars($_SESSION["name"]); ?></span>
            <a href="../../logout.php" class="btn btn-secondary btn-sm">Logout</a>
        </div>
    </header>
    
    <main class="dashboard-container">
        <aside class="sidebar">
            <div class="user-profile">
                <div class="profile-pic gov">
                    <span><?php echo substr($_SESSION["name"], 0, 1); ?></span>
                </div>
                <div class="profile-info">
                    <h3><?php echo htmlspecialchars($_SESSION["name"]); ?></h3>
                    <p>Government Official</p>
                </div>
            </div>
            
            <nav class="sidebar-nav">
                <ul>
                    <li><a href="index.php">Dashboard</a></li>
                    <li><a href="users.php" class="active">Manage Users</a></li>
                    <li><a href="merchants.php">Manage Merchants</a></li>
                    <li><a href="inventory.php">Inventory Status</a></li>
                    <li><a href="reports.php">Reports & Analytics</a></li>
                    <li><a href="#">System Settings</a></li>
                </ul>
            </nav>
        </aside>
        
        <div class="dashboard-content">
            <h2>Manage Users</h2>
            
            <?php if (!empty($action_message)): ?>
                <div class="alert alert-<?php echo $action_status; ?>">
                    <?php echo $action_message; ?>
                </div>
            <?php endif; ?>
            
            <div class="filters-container">
                <form method="get" action="users.php" class="search-form">
                    <div class="form-row">
                        <div class="form-group">
                            <input type="text" name="search" placeholder="Search users..." class="form-control" value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="form-group">
                            <select name="role" class="form-control">
                                <option value="">All Roles</option>
                                <option value="public" <?php echo ($role_filter == 'public') ? 'selected' : ''; ?>>Public</option>
                                <option value="merchant" <?php echo ($role_filter == 'merchant') ? 'selected' : ''; ?>>Merchant</option>
                                <option value="government" <?php echo ($role_filter == 'government') ? 'selected' : ''; ?>>Government</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">Filter</button>
                            <a href="users.php" class="btn btn-secondary">Reset</a>
                        </div>
                    </div>
                </form>
            </div>
            
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>PRS ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <?php if ($has_status_column): ?>
                            <th>Status</th>
                            <?php endif; ?>
                            <th>Registered</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($user["prs_id"]); ?></td>
                            <td><?php echo htmlspecialchars($user["first_name"] . " " . $user["last_name"]); ?></td>
                            <td><?php echo htmlspecialchars($user["email"]); ?></td>
                            <td>
                                <span class="role-badge <?php echo $user["role"]; ?>">
                                    <?php echo ucfirst($user["role"]); ?>
                                </span>
                            </td>
                            <?php if ($has_status_column): ?>
                            <td>
                                <span class="status-indicator <?php echo $user["status"]; ?>">
                                    <?php echo ucfirst($user["status"]); ?>
                                </span>
                            </td>
                            <?php endif; ?>
                            <td><?php echo date("M j, Y", strtotime($user["created_at"])); ?></td>
                            <td>
                                <div class="action-buttons">
                                    <a href="#" class="btn btn-primary btn-sm" data-action="view" data-prs-id="<?php echo $user["prs_id"]; ?>">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    
                                    <form method="post" action="users.php" class="action-form" onsubmit="return confirm('Are you sure you want to perform this action?');">
                                        <input type="hidden" name="prs_id" value="<?php echo $user["prs_id"]; ?>">
                                        
                                        <?php if ($has_status_column): ?>
                                            <?php if ($user["status"] == 'suspended'): ?>
                                                <button type="submit" name="action" value="activate" class="btn btn-success btn-sm">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            <?php elseif ($user["status"] == 'active'): ?>
                                                <button type="submit" name="action" value="suspend" class="btn btn-warning btn-sm">
                                                    <i class="fas fa-ban"></i>
                                                </button>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        
                                        <button type="submit" name="action" value="delete" class="btn btn-danger btn-sm">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <?php if (count($users) == 0): ?>
                        <tr>
                            <td colspan="<?php echo $has_status_column ? 7 : 6; ?>" class="text-center">No users found.</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role_filter); ?>" class="btn btn-secondary">&laquo; Previous</a>
                <?php endif; ?>
                
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role_filter); ?>" class="btn <?php echo ($i == $page) ? 'btn-primary' : 'btn-secondary'; ?>"><?php echo $i; ?></a>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role_filter); ?>" class="btn btn-secondary">Next &raquo;</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </main>
    
    <footer>
        <p>&copy; 2025 Pandemic Resilience System | Data-driven Systems (5CM506)</p>
    </footer>
    
    <div id="edit-user-modal" class="modal" style="display: none;">
        <div class="modal-content modal-lg">
            <span class="close">&times;</span>
            <h3>Edit User</h3>
            <form id="edit-user-form" method="post" action="users.php">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" id="edit-prs-id" name="prs_id" value="">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit-first-name">First Name</label>
                        <input type="text" id="edit-first-name" name="first_name" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="edit-last-name">Last Name</label>
                        <input type="text" id="edit-last-name" name="last_name" class="form-control">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit-email">Email</label>
                        <input type="email" id="edit-email" name="email" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="edit-phone">Phone</label>
                        <input type="text" id="edit-phone" name="phone" class="form-control">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit-address">Address</label>
                        <input type="text" id="edit-address" name="address" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="edit-city">City</label>
                        <input type="text" id="edit-city" name="city" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="edit-postal-code">Postal Code</label>
                        <input type="text" id="edit-postal-code" name="postal_code" class="form-control">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit-role">Role</label>
                        <select id="edit-role" name="role" class="form-control">
                            <option value="public">Public</option>
                            <option value="merchant">Merchant</option>
                            <option value="government">Government</option>
                        </select>
                    </div>
                    <?php if ($has_status_column): ?>
                    <div class="form-group">
                        <label for="edit-status">Status</label>
                        <select id="edit-status" name="status" class="form-control">
                            <option value="active">Active</option>
                            <option value="suspended">Suspended</option>
                            <option value="pending">Pending</option>
                        </select>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit-new-password">New Password (leave blank to keep current)</label>
                        <input type="password" id="edit-new-password" name="new_password" class="form-control">
                    </div>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                    <button type="button" class="btn btn-secondary close-modal">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    
    <style>
        .modal {
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background-color: #fff;
            padding: 2rem;
            border-radius: 8px;
            width: 500px;
            max-width: 90%;
            position: relative;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-lg {
            width: 800px;
        }
        
        .close {
            position: absolute;
            right: 20px;
            top: 10px;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .action-buttons {
            display: flex;
            gap: 5px;
        }
        
        .role-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .role-badge.public {
            background-color: #cce5ff;
            color: #004085;
        }
        
        .role-badge.merchant {
            background-color: #d4edda;
            color: #155724;
        }
        
        .role-badge.government {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .status-indicator {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .status-indicator.active {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-indicator.suspended {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .status-indicator.pending {
            background-color: #fff3cd;
            color: #856404;
        }
    </style>
    
    <script src="../../assets/js/main.js"></script>
    <script>
        document.querySelectorAll('[data-action="view"]').forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                const prsId = this.getAttribute('data-prs-id');
                
                fetch(`get_user_details.php?prs_id=${prsId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'success') {
                            document.getElementById('edit-prs-id').value = data.user.prs_id;
                            document.getElementById('edit-first-name').value = data.user.first_name;
                            document.getElementById('edit-last-name').value = data.user.last_name;
                            document.getElementById('edit-email').value = data.user.email;
                            document.getElementById('edit-phone').value = data.user.phone || '';
                            document.getElementById('edit-address').value = data.user.address || '';
                            document.getElementById('edit-city').value = data.user.city || '';
                            document.getElementById('edit-postal-code').value = data.user.postal_code || '';
                            document.getElementById('edit-role').value = data.user.role;
                            
                            if (document.getElementById('edit-status')) {
                                document.getElementById('edit-status').value = data.user.status;
                            }
                            
                            document.getElementById('edit-user-modal').style.display = 'flex';
                        } else {
                            alert('Error: ' + data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('An error occurred while fetching user data');
                    });
            });
        });

        document.querySelectorAll('.close, .close-modal').forEach(element => {
            element.addEventListener('click', function() {
                document.getElementById('edit-user-modal').style.display = 'none';
            });
        });

        window.addEventListener('click', function(event) {
            const modal = document.getElementById('edit-user-modal');
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        });
    </script>
</body>
</html> 