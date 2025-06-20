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

// Get filter parameters
$merchant_id = isset($_GET["merchant_id"]) ? (int)$_GET["merchant_id"] : null;
$category = isset($_GET["category"]) ? sanitize_input($_GET["category"]) : "";
$low_stock = isset($_GET["low_stock"]) ? (bool)$_GET["low_stock"] : false;
$search = isset($_GET["search"]) ? sanitize_input($_GET["search"]) : "";

// Build query
$sql = "SELECT i.inventory_id, i.merchant_id, m.business_name, i.item_id, 
        it.name as item_name, it.category, i.quantity, i.price, 
        it.rationing_limit, u.city 
        FROM inventory i 
        JOIN items it ON i.item_id = it.item_id 
        JOIN merchants m ON i.merchant_id = m.merchant_id 
        JOIN users u ON m.prs_id = u.prs_id 
        WHERE 1=1";

$params = [];
$types = "";

if ($merchant_id !== null) {
    $sql .= " AND i.merchant_id = ?";
    $params[] = $merchant_id;
    $types .= "i";
}

if (!empty($category)) {
    $sql .= " AND it.category = ?";
    $params[] = $category;
    $types .= "s";
}

if ($low_stock) {
    $sql .= " AND i.quantity < 10";
}

if (!empty($search)) {
    $sql .= " AND (it.name LIKE ? OR m.business_name LIKE ?)";
    $search_param = "%" . $search . "%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ss";
}

$sql .= " ORDER BY i.quantity ASC, m.business_name, it.category, it.name";

// Execute query
$inventory = [];
$stmt = $conn->prepare($sql);

if (!empty($types)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $inventory[] = $row;
}

$stmt->close();

// Get merchant list for filter dropdown
$merchants = [];
$merchant_sql = "SELECT m.merchant_id, m.business_name, u.city 
                FROM merchants m 
                JOIN users u ON m.prs_id = u.prs_id 
                WHERE m.status = 'active' 
                ORDER BY u.city, m.business_name";
$merchant_result = $conn->query($merchant_sql);

while ($row = $merchant_result->fetch_assoc()) {
    $merchants[] = $row;
}

// Get category list for filter dropdown
$categories = [];
$category_sql = "SELECT DISTINCT category FROM items ORDER BY category";
$category_result = $conn->query($category_sql);

while ($row = $category_result->fetch_assoc()) {
    $categories[] = $row["category"];
}

// Calculate inventory statistics
$stats = [
    'total_items' => 0,
    'total_value' => 0,
    'low_stock_count' => 0,
    'by_category' => []
];

// Get total inventory stats
$stats_sql = "SELECT COUNT(*) as total_items, 
             SUM(i.quantity) as total_quantity, 
             SUM(i.quantity * i.price) as total_value,
             SUM(CASE WHEN i.quantity < 10 THEN 1 ELSE 0 END) as low_stock
             FROM inventory i";
$stats_result = $conn->query($stats_sql);
$stats_row = $stats_result->fetch_assoc();

$stats['total_items'] = $stats_row['total_items'];
$stats['total_value'] = $stats_row['total_value'];
$stats['low_stock_count'] = $stats_row['low_stock'];

// Get stats by category
$category_stats_sql = "SELECT it.category, 
                      COUNT(*) as item_count, 
                      SUM(i.quantity) as total_quantity,
                      SUM(i.quantity * i.price) as total_value
                      FROM inventory i
                      JOIN items it ON i.item_id = it.item_id
                      GROUP BY it.category
                      ORDER BY it.category";
$category_stats_result = $conn->query($category_stats_sql);

while ($row = $category_stats_result->fetch_assoc()) {
    $stats['by_category'][$row['category']] = [
        'item_count' => $row['item_count'],
        'total_quantity' => $row['total_quantity'],
        'total_value' => $row['total_value']
    ];
}

// Log page access
log_activity($_SESSION["prs_id"], "view", "inventory", "page", "success");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Status - Government Dashboard</title>
    <link rel="stylesheet" href="../../assets/css/main.css">
    <link rel="stylesheet" href="../../assets/css/gov-dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                    <li><a href="merchants.php">Manage Merchants</a></li>
                    <li><a href="inventory.php" class="active">Inventory Status</a></li>
                    <li><a href="reports.php">Reports & Analytics</a></li>
                    <li><a href="#">System Settings</a></li>
                </ul>
            </nav>
        </aside>
        
        <div class="dashboard-content">
            <h2>Inventory Status</h2>
            
            <div class="stats-container">
                <div class="stat-card">
                    <div class="stat-icon inventory">
                        <i class="fas fa-boxes"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Total Inventory Items</h3>
                        <p class="stat-value"><?php echo number_format($stats['total_items']); ?></p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon value">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Total Inventory Value</h3>
                        <p class="stat-value">$<?php echo number_format($stats['total_value'], 2); ?></p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon warning">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Low Stock Items</h3>
                        <p class="stat-value"><?php echo number_format($stats['low_stock_count']); ?></p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon restricted">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Essential Items</h3>
                        <p class="stat-value"><?php echo number_format($stats['by_category']['essential']['total_quantity'] ?? 0); ?></p>
                    </div>
                </div>
            </div>
            
            <div class="chart-container">
                <div class="chart-card">
                    <h3>Inventory by Category</h3>
                    <canvas id="categoryChart"></canvas>
                </div>
                
                <div class="chart-card">
                    <h3>Low Stock Alert</h3>
                    <canvas id="lowStockChart"></canvas>
                </div>
            </div>
            
            <div class="filters-container">
                <form method="get" action="inventory.php" class="search-form">
                    <div class="form-row">
                        <div class="form-group">
                            <input type="text" name="search" placeholder="Search items or merchants..." class="form-control" value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="form-group">
                            <select name="merchant_id" class="form-control">
                                <option value="">All Merchants</option>
                                <?php foreach ($merchants as $m): ?>
                                    <option value="<?php echo $m['merchant_id']; ?>" <?php echo ($merchant_id == $m['merchant_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($m['business_name'] . ' (' . $m['city'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <select name="category" class="form-control">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat; ?>" <?php echo ($category == $cat) ? 'selected' : ''; ?>>
                                        <?php echo ucfirst($cat); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="checkbox-container">
                                <input type="checkbox" name="low_stock" value="1" <?php echo $low_stock ? 'checked' : ''; ?>>
                                <span class="checkbox-label">Low Stock Only</span>
                            </label>
                        </div>
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">Apply Filters</button>
                            <a href="inventory.php" class="btn btn-secondary">Reset</a>
                        </div>
                    </div>
                </form>
            </div>
            
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Merchant</th>
                            <th>Location</th>
                            <th>Item</th>
                            <th>Category</th>
                            <th>Quantity</th>
                            <th>Price</th>
                            <th>Value</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($inventory as $item): ?>
                        <tr <?php echo ($item["quantity"] < 10) ? 'class="low-stock"' : ''; ?>>
                            <td><?php echo htmlspecialchars($item["business_name"]); ?></td>
                            <td><?php echo htmlspecialchars($item["city"]); ?></td>
                            <td><?php echo htmlspecialchars($item["item_name"]); ?></td>
                            <td>
                                <span class="category-badge <?php echo $item["category"]; ?>">
                                    <?php echo ucfirst($item["category"]); ?>
                                </span>
                            </td>
                            <td><?php echo number_format($item["quantity"]); ?></td>
                            <td>$<?php echo number_format($item["price"], 2); ?></td>
                            <td>$<?php echo number_format($item["quantity"] * $item["price"], 2); ?></td>
                            <td>
                                <?php if ($item["quantity"] < 10): ?>
                                    <span class="status-indicator low">
                                        Low Stock
                                    </span>
                                <?php else: ?>
                                    <span class="status-indicator ok">
                                        In Stock
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <?php if (count($inventory) == 0): ?>
                        <tr>
                            <td colspan="8" class="text-center">No inventory items found.</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
    
    <footer>
        <p>&copy; 2025 Pandemic Resilience System | Data-driven Systems (5CM506)</p>
    </footer>
    
    <script>
        // Prepare data for category chart
        const categoryData = {
            labels: [
                <?php 
                foreach ($stats['by_category'] as $cat => $data) {
                    echo "'" . ucfirst($cat) . "', ";
                }
                ?>
            ],
            datasets: [{
                label: 'Item Count',
                data: [
                    <?php 
                    foreach ($stats['by_category'] as $cat => $data) {
                        echo $data['item_count'] . ", ";
                    }
                    ?>
                ],
                backgroundColor: [
                    'rgba(75, 192, 192, 0.6)',
                    'rgba(54, 162, 235, 0.6)',
                    'rgba(255, 99, 132, 0.6)',
                    'rgba(255, 206, 86, 0.6)'
                ],
                borderColor: [
                    'rgba(75, 192, 192, 1)',
                    'rgba(54, 162, 235, 1)',
                    'rgba(255, 99, 132, 1)',
                    'rgba(255, 206, 86, 1)'
                ],
                borderWidth: 1
            }]
        };

        const categoryChart = new Chart(
            document.getElementById('categoryChart'),
            {
                type: 'pie',
                data: categoryData,
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'right',
                        }
                    }
                }
            }
        );
        
        // Prepare data for low stock chart
        const lowStockData = {
            labels: ['Essential', 'Medical', 'Regular', 'Restricted'],
            datasets: [{
                label: 'Low Stock Items',
                data: [12, 19, 5, 2],
                backgroundColor: 'rgba(255, 99, 132, 0.6)',
                borderColor: 'rgba(255, 99, 132, 1)',
                borderWidth: 1
            }]
        };

        const lowStockChart = new Chart(
            document.getElementById('lowStockChart'),
            {
                type: 'bar',
                data: lowStockData,
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            }
        );
    </script>
    
    <script src="../../assets/js/main.js"></script>
</body>
</html>