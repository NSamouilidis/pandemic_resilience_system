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

// Process merchant actions if any
$action_message = "";
$action_status = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST["action"]) && isset($_POST["merchant_id"])) {
        $action = $_POST["action"];
        $merchant_id = (int)$_POST["merchant_id"];
        
        // Get merchant PRS ID for logging
        $sql = "SELECT prs_id FROM merchants WHERE merchant_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $merchant_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $merchant = $result->fetch_assoc();
        $prs_id = $merchant["prs_id"] ?? "";
        $stmt->close();
        
        if (!empty($prs_id)) {
            switch ($action) {
                case "approve":
                    // Update merchant status
                    $sql = "UPDATE merchants SET status = 'active' WHERE merchant_id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("i", $merchant_id);
                    
                    if ($stmt->execute()) {
                        $action_message = "Merchant approved successfully.";
                        $action_status = "success";
                        
                        // Log activity
                        log_activity($_SESSION["prs_id"], "approve", "merchant", $prs_id, "success");
                    } else {
                        $action_message = "Error approving merchant.";
                        $action_status = "danger";
                    }
                    
                    $stmt->close();
                    break;
                    
                case "suspend":
                    // Update merchant status
                    $sql = "UPDATE merchants SET status = 'suspended' WHERE merchant_id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("i", $merchant_id);
                    
                    if ($stmt->execute()) {
                        $action_message = "Merchant suspended successfully.";
                        $action_status = "success";
                        
                        // Log activity
                        log_activity($_SESSION["prs_id"], "suspend", "merchant", $prs_id, "success");
                    } else {
                        $action_message = "Error suspending merchant.";
                        $action_status = "danger";
                    }
                    
                    $stmt->close();
                    break;
                    
                case "reject":
                    // Update merchant status
                    $sql = "UPDATE merchants SET status = 'rejected' WHERE merchant_id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("i", $merchant_id);
                    
                    if ($stmt->execute()) {
                        $action_message = "Merchant application rejected.";
                        $action_status = "success";
                        
                        // Log activity
                        log_activity($_SESSION["prs_id"], "reject", "merchant", $prs_id, "success");
                    } else {
                        $action_message = "Error rejecting merchant application.";
                        $action_status = "danger";
                    }
                    
                    $stmt->close();
                    break;
            }
        } else {
            $action_message = "Invalid merchant ID.";
            $action_status = "danger";
        }
    }
}

// Get merchants list with pagination
$search = isset($_GET["search"]) ? sanitize_input($_GET["search"]) : "";
$status_filter = isset($_GET["status"]) ? sanitize_input($_GET["status"]) : "";
$page = isset($_GET["page"]) ? (int)$_GET["page"] : 1;
$items_per_page = 10;
$offset = ($page - 1) * $items_per_page;

// Build query
$sql = "SELECT m.merchant_id, m.prs_id, m.business_name, m.business_type, m.license_number, 
        m.status, u.first_name, u.last_name, u.email, u.city, u.postal_code, u.created_at 
        FROM merchants m 
        JOIN users u ON m.prs_id = u.prs_id 
        WHERE 1=1";
$count_sql = "SELECT COUNT(*) as total FROM merchants m JOIN users u ON m.prs_id = u.prs_id WHERE 1=1";

$params = [];
$types = "";

if (!empty($search)) {
    $search_condition = " AND (m.business_name LIKE ? OR m.prs_id LIKE ? OR 
                         u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
    $sql .= $search_condition;
    $count_sql .= $search_condition;
    
    $search_param = "%" . $search . "%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param, $search_param]);
    $types .= "sssss";
}

if (!empty($status_filter)) {
    $status_condition = " AND m.status = ?";
    $sql .= $status_condition;
    $count_sql .= $status_condition;
    
    $params[] = $status_filter;
    $types .= "s";
}

// Add sorting and pagination
$sql .= " ORDER BY u.created_at DESC LIMIT ? OFFSET ?";
$params[] = $items_per_page;
$params[] = $offset;
$types .= "ii";

// Get total count for pagination
$total_merchants = 0;
if (!empty($types) && strlen($types) > 2) {
    $count_stmt = $conn->prepare($count_sql);
    $count_stmt->bind_param(substr($types, 0, -2), ...array_slice($params, 0, -2));
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $count_row = $count_result->fetch_assoc();
    $total_merchants = $count_row["total"];
    $count_stmt->close();
} else {
    $count_result = $conn->query($count_sql);
    $count_row = $count_result->fetch_assoc();
    $total_merchants = $count_row["total"];
}

// Calculate total pages
$total_pages = ceil($total_merchants / $items_per_page);

// Get merchants
$merchants = [];
$stmt = $conn->prepare($sql);

if (!empty($types)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $merchants[] = $row;
}

$stmt->close();

// Log page access
log_activity($_SESSION["prs_id"], "view", "merchants", "page", "success");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Merchants - Government Dashboard</title>
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
                    <li><a href="users.php">Manage Users</a></li>
                    <li><a href="merchants.php" class="active">Manage Merchants</a></li>
                    <li><a href="inventory.php">Inventory Status</a></li>
                    <li><a href="reports.php">Reports & Analytics</a></li>
                    <li><a href="#">System Settings</a></li>
                </ul>
            </nav>
        </aside>
        
        <div class="dashboard-content">
            <h2>Manage Merchants</h2>
            
            <?php if (!empty($action_message)): ?>
                <div class="alert alert-<?php echo $action_status; ?>">
                    <?php echo $action_message; ?>
                </div>
            <?php endif; ?>
            
            <div class="filters-container">
                <form method="get" action="merchants.php" class="search-form">
                    <div class="form-row">
                        <div class="form-group">
                            <input type="text" name="search" placeholder="Search merchants..." class="form-control" value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="form-group">
                            <select name="status" class="form-control">
                                <option value="">All Status</option>
                                <option value="pending" <?php echo ($status_filter == 'pending') ? 'selected' : ''; ?>>Pending</option>
                                <option value="active" <?php echo ($status_filter == 'active') ? 'selected' : ''; ?>>Active</option>
                                <option value="suspended" <?php echo ($status_filter == 'suspended') ? 'selected' : ''; ?>>Suspended</option>
                                <option value="rejected" <?php echo ($status_filter == 'rejected') ? 'selected' : ''; ?>>Rejected</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">Filter</button>
                            <a href="merchants.php" class="btn btn-secondary">Reset</a>
                        </div>
                    </div>
                </form>
            </div>
            
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Business Name</th>
                            <th>Owner</th>
                            <th>Business Type</th>
                            <th>License</th>
                            <th>Location</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($merchants as $merchant): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($merchant["business_name"]); ?></td>
                            <td><?php echo htmlspecialchars($merchant["first_name"] . " " . $merchant["last_name"]); ?></td>
                            <td><?php echo htmlspecialchars($merchant["business_type"]); ?></td>
                            <td><?php echo htmlspecialchars($merchant["license_number"]); ?></td>
                            <td><?php echo htmlspecialchars($merchant["city"]); ?></td>
                            <td>
                                <span class="status-indicator <?php echo $merchant["status"]; ?>">
                                    <?php echo ucfirst($merchant["status"]); ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <a href="#" class="btn btn-primary btn-sm" data-action="view" data-merchant-id="<?php echo $merchant["merchant_id"]; ?>">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    
                                    <a href="#" class="btn btn-info btn-sm" data-action="inventory" data-merchant-id="<?php echo $merchant["merchant_id"]; ?>">
                                        <i class="fas fa-boxes"></i>
                                    </a>
                                    
                                    <form method="post" action="merchants.php" class="action-form" onsubmit="return confirm('Are you sure you want to perform this action?');">
                                        <input type="hidden" name="merchant_id" value="<?php echo $merchant["merchant_id"]; ?>">
                                        
                                        <?php if ($merchant["status"] == 'pending'): ?>
                                            <button type="submit" name="action" value="approve" class="btn btn-success btn-sm">
                                                <i class="fas fa-check"></i>
                                            </button>
                                            
                                            <button type="submit" name="action" value="reject" class="btn btn-danger btn-sm">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        <?php elseif ($merchant["status"] == 'active'): ?>
                                            <button type="submit" name="action" value="suspend" class="btn btn-warning btn-sm">
                                                <i class="fas fa-ban"></i>
                                            </button>
                                        <?php elseif ($merchant["status"] == 'suspended'): ?>
                                            <button type="submit" name="action" value="approve" class="btn btn-success btn-sm">
                                                <i class="fas fa-check"></i>
                                            </button>
                                        <?php endif; ?>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <?php if (count($merchants) == 0): ?>
                        <tr>
                            <td colspan="7" class="text-center">No merchants found.</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>" class="btn btn-secondary">&laquo; Previous</a>
                <?php endif; ?>
                
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>" class="btn <?php echo ($i == $page) ? 'btn-primary' : 'btn-secondary'; ?>"><?php echo $i; ?></a>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>" class="btn btn-secondary">Next &raquo;</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </main>
    
    <footer>
        <p>&copy; 2025 Pandemic Resilience System | Data-driven Systems (5CM506)</p>
    </footer>
    
    <script src="../../assets/js/main.js"></script>
    <script>
        // View merchant details (modal popup would be implemented here)
        document.querySelectorAll('[data-action="view"]').forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                const merchantId = this.getAttribute('data-merchant-id');
                alert(`View details for merchant: ${merchantId}`);
                // In a real implementation, this would open a modal with merchant details
            });
        });
        
        // View merchant inventory
        document.querySelectorAll('[data-action="inventory"]').forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                const merchantId = this.getAttribute('data-merchant-id');
                window.location.href = `inventory.php?merchant_id=${merchantId}`;
            });
        });
    </script>
</body>
</html>