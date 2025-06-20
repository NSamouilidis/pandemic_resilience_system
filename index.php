<?php
session_start();

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: ../../login.php");
    exit;
}

if ($_SESSION["role"] !== "merchant") {
    header("location: ../../index.php");
    exit;
}

require_once "../../config/db_connect.php";

$merchant = [];
$sql = "SELECT m.*, u.city, u.postal_code 
        FROM merchants m 
        JOIN users u ON m.prs_id = u.prs_id 
        WHERE m.prs_id = ?";

if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("s", $_SESSION["prs_id"]);
    
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        
        if ($result->num_rows == 1) {
            $merchant = $result->fetch_assoc();
        }
    }
    
    $stmt->close();
}

$inventory = [];
$sql = "SELECT i.inventory_id, i.item_id, it.name as item_name, it.category, i.quantity, i.price, 
        CASE 
            WHEN it.category IN ('essential', 'medical') AND i.quantity < 10 THEN 'low' 
            ELSE 'ok' 
        END as stock_status
        FROM inventory i 
        JOIN items it ON i.item_id = it.item_id 
        JOIN merchants m ON i.merchant_id = m.merchant_id 
        WHERE m.prs_id = ? 
        ORDER BY it.category, it.name";

if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("s", $_SESSION["prs_id"]);
    
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $inventory[] = $row;
        }
    }
    
    $stmt->close();
}

$sales = [];
$sql = "SELECT t.transaction_id, t.prs_id, t.transaction_date, t.total_amount, 
        COUNT(ti.id) as item_count 
        FROM transactions t 
        JOIN transaction_items ti ON t.transaction_id = ti.transaction_id 
        JOIN merchants m ON t.merchant_id = m.merchant_id 
        WHERE m.prs_id = ? 
        GROUP BY t.transaction_id 
        ORDER BY t.transaction_date DESC 
        LIMIT 10";

if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("s", $_SESSION["prs_id"]);
    
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $sales[] = $row;
        }
    }
    
    $stmt->close();
}

$inventory_stats = [
    'total_items' => count($inventory),
    'total_value' => 0,
    'low_stock' => 0,
    'restricted_items' => 0
];

foreach ($inventory as $item) {
    $inventory_stats['total_value'] += $item['quantity'] * $item['price'];
    
    if ($item['stock_status'] == 'low') {
        $inventory_stats['low_stock']++;
    }
    
    if ($item['category'] == 'restricted') {
        $inventory_stats['restricted_items']++;
    }
}

log_activity($_SESSION["prs_id"], "view", "dashboard", "merchant", "success");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Merchant Dashboard - Pandemic Resilience System</title>
    <link rel="stylesheet" href="../../assets/css/main.css">
    <link rel="stylesheet" href="../../assets/css/merchant-dashboard.css">
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
                <div class="profile-pic merchant">
                    <span><?php echo substr($merchant["business_name"] ?? $_SESSION["name"], 0, 1); ?></span>
                </div>
                <div class="profile-info">
                    <h3><?php echo htmlspecialchars($merchant["business_name"] ?? $_SESSION["name"]); ?></h3>
                    <p>
                        <?php 
                        if (!empty($merchant)) {
                            echo htmlspecialchars($merchant["status"]);
                            echo ' • License #' . htmlspecialchars(substr($merchant["license_number"], -5));
                        } else {
                            echo "Merchant";
                        }
                        ?>
                    </p>
                </div>
            </div>
            
            <nav class="sidebar-nav">
                <ul>
                    <li><a href="index.php" class="active">Dashboard</a></li>
                    <li><a href="inventory.php">Manage Inventory</a></li>
                    <li><a href="sales.php">Sales History</a></li>
                    <li><a href="reports.php">Reports</a></li>
                    <li><a href="#">Business Profile</a></li>
                </ul>
            </nav>
        </aside>
        
        <div class="dashboard-content">
            <h2>Merchant Dashboard</h2>
            
            <?php if ($merchant['status'] !== 'active'): ?>
            <div class="alert alert-warning">
                <strong>Note:</strong> Your merchant account is currently <?php echo htmlspecialchars($merchant['status']); ?>. 
                <?php if ($merchant['status'] == 'pending'): ?>
                Your application is under review by government officials.
                <?php elseif ($merchant['status'] == 'suspended'): ?>
                Please contact support for more information.
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <div class="stats-container">
                <div class="stat-card">
                    <div class="stat-icon inventory">
                        <i class="fas fa-box"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Inventory Items</h3>
                        <p class="stat-value"><?php echo number_format($inventory_stats['total_items']); ?></p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon value">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Inventory Value</h3>
                        <p class="stat-value">$<?php echo number_format($inventory_stats['total_value'], 2); ?></p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon warning">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Low Stock Items</h3>
                        <p class="stat-value"><?php echo number_format($inventory_stats['low_stock']); ?></p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon restricted">
                        <i class="fas fa-lock"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Restricted Items</h3>
                        <p class="stat-value"><?php echo number_format($inventory_stats['restricted_items']); ?></p>
                    </div>
                </div>
            </div>
            
            <div class="chart-container">
                <div class="chart-card">
                    <h3>Sales Trend</h3>
                    <canvas id="salesChart"></canvas>
                </div>
                
                <div class="chart-card">
                    <h3>Inventory by Category</h3>
                    <canvas id="inventoryChart"></canvas>
                </div>
            </div>
            
            <div class="dashboard-section">
                <div class="section-header">
                    <h3>Inventory Status</h3>
                    <a href="inventory.php" class="btn btn-primary btn-sm">Manage Inventory</a>
                </div>
                
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Category</th>
                                <th>Quantity</th>
                                <th>Price</th>
                                <th>Value</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($inventory as $item): ?>
                            <tr>
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
                                    <span class="status-indicator <?php echo $item["stock_status"]; ?>">
                                        <?php echo ($item["stock_status"] == 'low') ? 'Low Stock' : 'In Stock'; ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="#" class="btn btn-primary btn-sm">Update</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            
                            <?php if (count($inventory) == 0): ?>
                            <tr>
                                <td colspan="7" class="text-center">No inventory items found.</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="dashboard-section">
                <div class="section-header">
                    <h3>Recent Sales</h3>
                    <a href="sales.php" class="btn btn-primary btn-sm">View All Sales</a>
                </div>
                
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Transaction ID</th>
                                <th>Customer ID</th>
                                <th>Date</th>
                                <th>Items</th>
                                <th>Amount</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sales as $sale): ?>
                            <tr>
                                <td><?php echo $sale["transaction_id"]; ?></td>
                                <td><?php echo htmlspecialchars($sale["prs_id"]); ?></td>
                                <td><?php echo date("M j, Y g:i A", strtotime($sale["transaction_date"])); ?></td>
                                <td><?php echo $sale["item_count"]; ?> items</td>
                                <td>$<?php echo number_format($sale["total_amount"], 2); ?></td>
                                <td>
                                    <a href="#" class="btn btn-primary btn-sm">View Details</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            
                            <?php if (count($sales) == 0): ?>
                            <tr>
                                <td colspan="6" class="text-center">No recent sales found.</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
    
    <footer>
        <p>&copy; 2025 Pandemic Resilience System | Data-driven Systems (5CM506)</p>
    </footer>
    
    <script>
        const salesData = {
            labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May'],
            datasets: [{
                label: 'Sales Amount ($)',
                data: [4500, 5200, 6800, 5500, 7500],
                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                borderColor: 'rgba(75, 192, 192, 1)',
                borderWidth: 2,
                tension: 0.4
            }]
        };

        const salesChart = new Chart(
            document.getElementById('salesChart'),
            {
                type: 'line',
                data: salesData,
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
        
        const inventoryData = {
            labels: ['Essential', 'Medical', 'Restricted', 'Normal'],
            datasets: [{
                label: 'Items by Category',
                data: [12, 8, 3, 5],
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

        const inventoryChart = new Chart(
            document.getElementById('inventoryChart'),
            {
                type: 'pie',
                data: inventoryData,
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'right'
                        }
                    }
                }
            }
        );
    </script>
    
    <script src="../../assets/js/merchant-dashboard.js"></script>
</body>
</html>