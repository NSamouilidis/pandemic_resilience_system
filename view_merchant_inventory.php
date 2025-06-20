<?php
session_start();

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: ../../login.php");
    exit;
}

require_once "../../config/db_connect.php";

// Check if merchant_id is provided
if (!isset($_GET["merchant_id"]) || empty($_GET["merchant_id"])) {
    header("location: resource_finder.php");
    exit;
}

$merchant_id = (int)$_GET["merchant_id"];

// Get merchant information
$merchant = [];
$merchant_sql = "SELECT m.merchant_id, m.business_name, m.business_type, u.address, u.city, u.postal_code 
                FROM merchants m 
                JOIN users u ON m.prs_id = u.prs_id 
                WHERE m.merchant_id = ? AND m.status = 'active'";
$stmt = $conn->prepare($merchant_sql);
$stmt->bind_param("i", $merchant_id);
$stmt->execute();
$merchant_result = $stmt->get_result();

if ($merchant_result->num_rows === 0) {
    header("location: resource_finder.php");
    exit;
}

$merchant = $merchant_result->fetch_assoc();
$stmt->close();

// Get inventory items
$category = isset($_GET["category"]) ? sanitize_input($_GET["category"]) : "";

$sql = "SELECT i.inventory_id, i.item_id, it.name as item_name, it.category, 
        it.description, i.quantity, i.price, it.rationing_limit 
        FROM inventory i 
        JOIN items it ON i.item_id = it.item_id 
        WHERE i.merchant_id = ? AND i.quantity > 0";

$params = [$merchant_id];
$types = "i";

if (!empty($category)) {
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

// Get categories for filter
$categories = [];
$category_sql = "SELECT DISTINCT it.category 
                FROM inventory i 
                JOIN items it ON i.item_id = it.item_id 
                WHERE i.merchant_id = ? AND i.quantity > 0 
                ORDER BY it.category";
$stmt = $conn->prepare($category_sql);
$stmt->bind_param("i", $merchant_id);
$stmt->execute();
$category_result = $stmt->get_result();

while ($row = $category_result->fetch_assoc()) {
    $categories[] = $row["category"];
}
$stmt->close();

log_activity($_SESSION["prs_id"], "view", "merchant_inventory", $merchant_id, "success");

$page_title = "View Inventory - " . htmlspecialchars($merchant["business_name"]);
$additional_css = ["assets/css/dashboard.css", "assets/css/public-dashboard.css"];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="../../assets/css/main.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/public-dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        .back-button {
            margin-right: 1rem;
        }
        
        .merchant-info-card {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
            overflow: hidden;
        }
        
        .merchant-header {
            background-color: #f0f5ff;
            padding: 1rem;
            border-bottom: 1px solid #e0e0ff;
        }
        
        .merchant-header h3 {
            margin: 0;
            color: #2a5caa;
        }
        
        .merchant-details {
            padding: 1rem;
            display: flex;
            flex-wrap: wrap;
        }
        
        .detail-group {
            margin-right: 2rem;
            margin-bottom: 0.5rem;
            flex: 1 0 200px;
        }
        
        .detail-label {
            font-weight: 600;
            color: #555;
            margin-right: 0.5rem;
        }
        
        .inventory-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .inventory-card {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        
        .inventory-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }
        
        .inventory-header {
            padding: 1rem;
            background-color: #f0f5ff;
            border-bottom: 1px solid #e0e0ff;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .inventory-header h4 {
            margin: 0;
            color: #2a5caa;
        }
        
        .category-badge {
            display: inline-block;
            padding: 0.35rem 0.7rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .category-badge.essential {
            background-color: #cce5ff;
            color: #004085;
        }
        
        .category-badge.medical {
            background-color: #d4edda;
            color: #155724;
        }
        
        .category-badge.restricted {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .category-badge.normal {
            background-color: #e2e3e5;
            color: #383d41;
        }
        
        .inventory-content {
            padding: 1rem;
        }
        
        .item-description {
            margin-bottom: 1rem;
            color: #555;
        }
        
        .item-details {
            border-top: 1px solid #eee;
            padding-top: 1rem;
            margin-bottom: 1rem;
        }
        
        .detail-row {
            margin-bottom: 0.5rem;
            display: flex;
            justify-content: space-between;
        }
        
        .detail-value.price {
            font-weight: 600;
            color: #28a745;
        }
        
        .detail-value.quantity {
            font-weight: 600;
            color: #2a5caa;
        }
        
        .detail-value.limit {
            font-weight: 600;
            color: #dc3545;
        }
        
        .item-alert {
            background-color: #fff3cd;
            color: #856404;
            padding: 0.75rem;
            border-radius: 4px;
            font-size: 0.9rem;
        }
        
        .inventory-contact {
            background-color: #f8f9fa;
            padding: 1rem;
            border-radius: 8px;
            margin-top: 2rem;
        }
        
        .inventory-contact p {
            margin: 0;
            color: #555;
        }
        
        .no-results {
            text-align: center;
            padding: 2rem;
            background-color: #f9f9f9;
            border-radius: 8px;
            margin: 1.5rem 0;
        }
    </style>
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
                <div class="profile-pic">
                    <span><?php echo substr($_SESSION["name"], 0, 1); ?></span>
                </div>
                <div class="profile-info">
                    <h3><?php echo htmlspecialchars($_SESSION["name"]); ?></h3>
                    <p><?php echo htmlspecialchars($_SESSION["prs_id"]); ?></p>
                </div>
            </div>
            
            <nav class="sidebar-nav">
                <ul>
                    <li><a href="index.php">Dashboard</a></li>
                    <li><a href="profile.php">My Profile</a></li>
                    <li><a href="vaccinations.php">Vaccination Records</a></li>
                    <li><a href="resource_finder.php" class="active">Resource Finder</a></li>
                    <li><a href="purchase_history.php">Purchase History</a></li>
                </ul>
            </nav>
        </aside>
        
        <div class="dashboard-content">
            <h2>
                <a href="resource_finder.php" class="btn btn-secondary btn-sm back-button">
                    <i class="fas fa-arrow-left"></i> Back to Resource Finder
                </a>
                Available Inventory
            </h2>
            
            <div class="merchant-info-card">
                <div class="merchant-header">
                    <h3><?php echo htmlspecialchars($merchant["business_name"]); ?></h3>
                </div>
                <div class="merchant-details">
                    <div class="detail-group">
                        <span class="detail-label">Business Type:</span>
                        <span><?php echo htmlspecialchars($merchant["business_type"]); ?></span>
                    </div>
                    <div class="detail-group">
                        <span class="detail-label">Address:</span>
                        <span><?php echo htmlspecialchars($merchant["address"] ?? "Not provided"); ?></span>
                    </div>
                    <div class="detail-group">
                        <span class="detail-label">City:</span>
                        <span><?php echo htmlspecialchars($merchant["city"]); ?></span>
                    </div>
                    <div class="detail-group">
                        <span class="detail-label">Postal Code:</span>
                        <span><?php echo htmlspecialchars($merchant["postal_code"] ?? "Not provided"); ?></span>
                    </div>
                </div>
            </div>
            
            <div class="filters-container">
                <form method="get" action="view_merchant_inventory.php" class="search-form">
                    <input type="hidden" name="merchant_id" value="<?php echo $merchant_id; ?>">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <select name="category" class="form-control" onchange="this.form.submit()">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat; ?>" <?php echo ($category == $cat) ? 'selected' : ''; ?>>
                                        <?php echo ucfirst($cat); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <?php if (!empty($category)): ?>
                            <div class="form-group">
                                <a href="view_merchant_inventory.php?merchant_id=<?php echo $merchant_id; ?>" class="btn btn-secondary">Reset Filter</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
            
            <?php if (count($items) > 0): ?>
                <div class="inventory-grid">
                    <?php foreach ($items as $item): ?>
                        <div class="inventory-card">
                            <div class="inventory-header">
                                <h4><?php echo htmlspecialchars($item["item_name"]); ?></h4>
                                <span class="category-badge <?php echo $item["category"]; ?>">
                                    <?php echo ucfirst($item["category"]); ?>
                                </span>
                            </div>
                            <div class="inventory-content">
                                <?php if (!empty($item["description"])): ?>
                                    <div class="item-description">
                                        <?php echo htmlspecialchars($item["description"]); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="item-details">
                                    <div class="detail-row">
                                        <span>Price:</span>
                                        <span class="detail-value price">$<?php echo number_format($item["price"], 2); ?></span>
                                    </div>
                                    <div class="detail-row">
                                        <span>Available:</span>
                                        <span class="detail-value quantity"><?php echo number_format($item["quantity"]); ?> in stock</span>
                                    </div>
                                    <?php if ($item["rationing_limit"] > 0): ?>
                                        <div class="detail-row">
                                            <span>Purchase Limit:</span>
                                            <span class="detail-value limit"><?php echo $item["rationing_limit"]; ?> per customer</span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if ($item["category"] === "restricted" || $item["rationing_limit"] > 0): ?>
                                    <div class="item-alert">
                                        <?php if ($item["category"] === "restricted"): ?>
                                            <p><strong>Note:</strong> This is a restricted item and may require additional verification for purchase.</p>
                                        <?php endif; ?>
                                        
                                        <?php if ($item["rationing_limit"] > 0): ?>
                                            <p><strong>Purchase Limit:</strong> Maximum of <?php echo $item["rationing_limit"]; ?> per customer due to current rationing measures.</p>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="inventory-contact">
                    <p><strong>Note:</strong> To purchase these items, please visit the merchant's location with your PRS ID. Items and availability are subject to change. For essential or medical items, please ensure you are eligible for purchase on your designated days.</p>
                </div>
            <?php else: ?>
                <div class="no-results">
                    <p>No inventory items found for this merchant<?php echo !empty($category) ? ' in the selected category' : ''; ?>.</p>
                    <?php if (!empty($category)): ?>
                        <p><a href="view_merchant_inventory.php?merchant_id=<?php echo $merchant_id; ?>">View all items</a></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>
    
    <footer>
        <p>&copy; 2025 Pandemic Resilience System | Data-driven Systems (5CM506)</p>
    </footer>
    
    <script src="../../assets/js/main.js"></script>
</body>
</html>