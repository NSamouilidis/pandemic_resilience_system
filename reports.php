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

$merchant_id = null;
$sql = "SELECT merchant_id FROM merchants WHERE prs_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $_SESSION["prs_id"]);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $row = $result->fetch_assoc();
    $merchant_id = $row["merchant_id"];
} else {
    header("location: ../../index.php");
    exit;
}
$stmt->close();

$report_type = isset($_GET["report_type"]) ? sanitize_input($_GET["report_type"]) : "sales";
$time_period = isset($_GET["time_period"]) ? sanitize_input($_GET["time_period"]) : "month";
$category = isset($_GET["category"]) ? sanitize_input($_GET["category"]) : "";

$report_data = [];

switch ($report_type) {
    case "sales":
        if ($time_period == "day") {
            $period_sql = "DATE(transaction_date) as period";
            $group_sql = "DATE(transaction_date)";
            $limit = 30;
        } elseif ($time_period == "week") {
            $period_sql = "YEARWEEK(transaction_date) as period";
            $group_sql = "YEARWEEK(transaction_date)";
            $limit = 12;
        } else {
            $period_sql = "DATE_FORMAT(transaction_date, '%Y-%m') as period";
            $group_sql = "DATE_FORMAT(transaction_date, '%Y-%m')";
            $limit = 12;
        }
        
        $trend_sql = "SELECT $period_sql, COUNT(*) as count, SUM(total_amount) as amount 
                     FROM transactions 
                     WHERE merchant_id = ? 
                     GROUP BY $group_sql 
                     ORDER BY period DESC 
                     LIMIT $limit";
        $trend_stmt = $conn->prepare($trend_sql);
        $trend_stmt->bind_param("i", $merchant_id);
        $trend_stmt->execute();
        $trend_result = $trend_stmt->get_result();
        
        $trend_data = [];
        while ($row = $trend_result->fetch_assoc()) {
            $trend_data[] = $row;
        }
        $trend_stmt->close();
        
        $category_sql = "SELECT it.category, COUNT(ti.id) as count, SUM(ti.quantity) as quantity, 
                         SUM(ti.quantity * ti.price_per_unit) as amount 
                         FROM transaction_items ti 
                         JOIN items it ON ti.item_id = it.item_id 
                         JOIN transactions t ON ti.transaction_id = t.transaction_id 
                         WHERE t.merchant_id = ? 
                         GROUP BY it.category 
                         ORDER BY amount DESC";
        $category_stmt = $conn->prepare($category_sql);
        $category_stmt->bind_param("i", $merchant_id);
        $category_stmt->execute();
        $category_result = $category_stmt->get_result();
        
        $category_data = [];
        while ($row = $category_result->fetch_assoc()) {
            $category_data[] = $row;
        }
        $category_stmt->close();
        
        $top_items_sql = "SELECT it.item_id, it.name, it.category, 
                          SUM(ti.quantity) as quantity, 
                          SUM(ti.quantity * ti.price_per_unit) as amount 
                          FROM transaction_items ti 
                          JOIN items it ON ti.item_id = it.item_id 
                          JOIN transactions t ON ti.transaction_id = t.transaction_id 
                          WHERE t.merchant_id = ? ";
        
        $params = [$merchant_id];
        $types = "i";
        if (!empty($category)) {
            $top_items_sql .= "AND it.category = ? ";
            $params[] = $category;
            $types .= "s";
        }
        
        $top_items_sql .= "GROUP BY it.item_id 
                           ORDER BY quantity DESC 
                           LIMIT 10";
        
        $top_items_stmt = $conn->prepare($top_items_sql);
        $top_items_stmt->bind_param($types, ...$params);
        $top_items_stmt->execute();
        $top_items_result = $top_items_stmt->get_result();
        
        $top_items_data = [];
        while ($row = $top_items_result->fetch_assoc()) {
            $top_items_data[] = $row;
        }
        $top_items_stmt->close();
        
        $report_data = [
            'trend' => $trend_data,
            'by_category' => $category_data,
            'top_items' => $top_items_data
        ];
        
        break;
        
    case "inventory":
        $category_sql = "SELECT it.category, COUNT(i.inventory_id) as count, 
                         SUM(i.quantity) as quantity, 
                         SUM(i.quantity * i.price) as value 
                         FROM inventory i 
                         JOIN items it ON i.item_id = it.item_id 
                         WHERE i.merchant_id = ? 
                         GROUP BY it.category 
                         ORDER BY quantity DESC";
        $category_stmt = $conn->prepare($category_sql);
        $category_stmt->bind_param("i", $merchant_id);
        $category_stmt->execute();
        $category_result = $category_stmt->get_result();
        
        $category_data = [];
        while ($row = $category_result->fetch_assoc()) {
            $category_data[] = $row;
        }
        $category_stmt->close();
        
        $low_stock_sql = "SELECT i.inventory_id, i.item_id, it.name, it.category, 
                          i.quantity, i.price, i.quantity * i.price as value 
                          FROM inventory i 
                          JOIN items it ON i.item_id = it.item_id 
                          WHERE i.merchant_id = ? AND i.quantity < 10 
                          ORDER BY i.quantity ASC";
        $low_stock_stmt = $conn->prepare($low_stock_sql);
        $low_stock_stmt->bind_param("i", $merchant_id);
        $low_stock_stmt->execute();
        $low_stock_result = $low_stock_stmt->get_result();
        
        $low_stock_data = [];
        while ($row = $low_stock_result->fetch_assoc()) {
            $low_stock_data[] = $row;
        }
        $low_stock_stmt->close();
        
        $high_value_sql = "SELECT i.inventory_id, i.item_id, it.name, it.category, 
                           i.quantity, i.price, i.quantity * i.price as value 
                           FROM inventory i 
                           JOIN items it ON i.item_id = it.item_id 
                           WHERE i.merchant_id = ? ";
        
        $params = [$merchant_id];
        $types = "i";
        if (!empty($category)) {
            $high_value_sql .= "AND it.category = ? ";
            $params[] = $category;
            $types .= "s";
        }
        
        $high_value_sql .= "ORDER BY value DESC 
                           LIMIT 10";
        
        $high_value_stmt = $conn->prepare($high_value_sql);
        $high_value_stmt->bind_param($types, ...$params);
        $high_value_stmt->execute();
        $high_value_result = $high_value_stmt->get_result();
        
        $high_value_data = [];
        while ($row = $high_value_result->fetch_assoc()) {
            $high_value_data[] = $row;
        }
        $high_value_stmt->close();
        
        $report_data = [
            'by_category' => $category_data,
            'low_stock' => $low_stock_data,
            'high_value' => $high_value_data
        ];
        
        break;
        
    case "customers":
        $top_customers_sql = "SELECT t.prs_id, 
                             COUNT(t.transaction_id) as transaction_count, 
                             SUM(t.total_amount) as total_amount, 
                             MAX(t.transaction_date) as last_purchase 
                             FROM transactions t 
                             WHERE t.merchant_id = ? 
                             GROUP BY t.prs_id 
                             ORDER BY total_amount DESC 
                             LIMIT 10";
        $top_customers_stmt = $conn->prepare($top_customers_sql);
        $top_customers_stmt->bind_param("i", $merchant_id);
        $top_customers_stmt->execute();
        $top_customers_result = $top_customers_stmt->get_result();
        
        $top_customers_data = [];
        while ($row = $top_customers_result->fetch_assoc()) {
            $top_customers_data[] = $row;
        }
        $top_customers_stmt->close();
        
        $category_sql = "SELECT it.category, 
                         COUNT(DISTINCT t.prs_id) as customer_count, 
                         SUM(ti.quantity) as quantity, 
                         SUM(ti.quantity * ti.price_per_unit) as amount 
                         FROM transaction_items ti 
                         JOIN items it ON ti.item_id = it.item_id 
                         JOIN transactions t ON ti.transaction_id = t.transaction_id 
                         WHERE t.merchant_id = ? 
                         GROUP BY it.category 
                         ORDER BY customer_count DESC";
        $category_stmt = $conn->prepare($category_sql);
        $category_stmt->bind_param("i", $merchant_id);
        $category_stmt->execute();
        $category_result = $category_stmt->get_result();
        
        $category_data = [];
        while ($row = $category_result->fetch_assoc()) {
            $category_data[] = $row;
        }
        $category_stmt->close();
        
        $report_data = [
            'top_customers' => $top_customers_data,
            'by_category' => $category_data
        ];
        
        break;
}

$categories = [];
$category_sql = "SELECT DISTINCT category FROM items ORDER BY category";
$category_result = $conn->query($category_sql);

while ($row = $category_result->fetch_assoc()) {
    $categories[] = $row["category"];
}

log_activity($_SESSION["prs_id"], "view", "reports", "page", "success");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Merchant Dashboard</title>
    <link rel="stylesheet" href="../../assets/css/main.css">
    <link rel="stylesheet" href="../../assets/css/merchant-dashboard.css">
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
                <div class="profile-pic merchant">
                    <span><?php echo substr($_SESSION["name"], 0, 1); ?></span>
                </div>
                <div class="profile-info">
                    <h3><?php echo htmlspecialchars($_SESSION["name"]); ?></h3>
                    <p>Merchant</p>
                </div>
            </div>
            
            <nav class="sidebar-nav">
                <ul>
                    <li><a href="index.php">Dashboard</a></li>
                    <li><a href="inventory.php">Manage Inventory</a></li>
                    <li><a href="sales.php">Sales History</a></li>
                    <li><a href="reports.php" class="active">Reports</a></li>
                    <li><a href="#">Business Profile</a></li>
                </ul>
            </nav>
        </aside>
        
        <div class="dashboard-content">
            <h2>Reports & Analytics</h2>
            
            <div class="report-controls">
                <form method="get" action="reports.php" class="report-form">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="report_type">Report Type</label>
                            <select id="report_type" name="report_type" class="form-control">
                                <option value="sales" <?php echo ($report_type == 'sales') ? 'selected' : ''; ?>>Sales Report</option>
                                <option value="inventory" <?php echo ($report_type == 'inventory') ? 'selected' : ''; ?>>Inventory Report</option>
                                <option value="customers" <?php echo ($report_type == 'customers') ? 'selected' : ''; ?>>Customer Report</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="time_period">Time Period</label>
                            <select id="time_period" name="time_period" class="form-control" <?php echo ($report_type != 'sales') ? 'disabled' : ''; ?>>
                                <option value="day" <?php echo ($time_period == 'day') ? 'selected' : ''; ?>>Daily</option>
                                <option value="week" <?php echo ($time_period == 'week') ? 'selected' : ''; ?>>Weekly</option>
                                <option value="month" <?php echo ($time_period == 'month') ? 'selected' : ''; ?>>Monthly</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="category">Category Filter</label>
                            <select id="category" name="category" class="form-control">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat; ?>" <?php echo ($category == $cat) ? 'selected' : ''; ?>>
                                        <?php echo ucfirst($cat); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <button type="submit" class="btn btn-primary">Generate Report</button>
                            <a href="#" id="export-report" class="btn btn-secondary">Export Report</a>
                        </div>
                    </div>
                </form>
            </div>
            
            <div class="report-container">
                <?php if ($report_type == 'sales'): ?>
                    <div class="report-header">
                        <h3>Sales Report</h3>
                        <p class="report-timestamp">Generated on <?php echo date('F j, Y g:i A'); ?></p>
                    </div>
                    
                    <div class="chart-container">
                        <div class="chart-card">
                            <h4>Sales Trend</h4>
                            <canvas id="salesTrendChart"></canvas>
                        </div>
                        
                        <div class="chart-card">
                            <h4>Sales by Category</h4>
                            <canvas id="salesByCategoryChart"></canvas>
                        </div>
                    </div>
                    
                    <div class="report-section">
                        <h4>Top Selling Items</h4>
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Item Name</th>
                                        <th>Category</th>
                                        <th>Quantity Sold</th>
                                        <th>Total Sales</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($report_data['top_items'] as $item): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($item["name"]); ?></td>
                                        <td>
                                            <span class="category-badge <?php echo $item["category"]; ?>">
                                                <?php echo ucfirst($item["category"]); ?>
                                            </span>
                                        </td>
                                        <td><?php echo number_format($item["quantity"]); ?></td>
                                        <td>$<?php echo number_format($item["amount"], 2); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    
                                    <?php if (count($report_data['top_items']) == 0): ?>
                                    <tr>
                                        <td colspan="4" class="text-center">No sales data available.</td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                <?php elseif ($report_type == 'inventory'): ?>
                    <div class="report-header">
                        <h3>Inventory Report</h3>
                        <p class="report-timestamp">Generated on <?php echo date('F j, Y g:i A'); ?></p>
                    </div>
                    
                    <div class="chart-container">
                        <div class="chart-card">
                            <h4>Inventory by Category</h4>
                            <canvas id="inventoryByCategoryChart"></canvas>
                        </div>
                        
                        <div class="chart-card">
                            <h4>Inventory Value by Category</h4>
                            <canvas id="inventoryValueChart"></canvas>
                        </div>
                    </div>
                    
                    <div class="report-section">
                        <h4>Low Stock Items</h4>
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Item Name</th>
                                        <th>Category</th>
                                        <th>Quantity</th>
                                        <th>Price</th>
                                        <th>Total Value</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($report_data['low_stock'] as $item): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($item["name"]); ?></td>
                                        <td>
                                            <span class="category-badge <?php echo $item["category"]; ?>">
                                                <?php echo ucfirst($item["category"]); ?>
                                            </span>
                                        </td>
                                        <td><?php echo number_format($item["quantity"]); ?></td>
                                        <td>$<?php echo number_format($item["price"], 2); ?></td>
                                        <td>$<?php echo number_format($item["value"], 2); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    
                                    <?php if (count($report_data['low_stock']) == 0): ?>
                                    <tr>
                                        <td colspan="5" class="text-center">No low stock items found.</td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <div class="report-section">
                        <h4>Highest Value Items</h4>
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Item Name</th>
                                        <th>Category</th>
                                        <th>Quantity</th>
                                        <th>Price</th>
                                        <th>Total Value</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($report_data['high_value'] as $item): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($item["name"]); ?></td>
                                        <td>
                                            <span class="category-badge <?php echo $item["category"]; ?>">
                                                <?php echo ucfirst($item["category"]); ?>
                                            </span>
                                        </td>
                                        <td><?php echo number_format($item["quantity"]); ?></td>
                                        <td>$<?php echo number_format($item["price"], 2); ?></td>
                                        <td>$<?php echo number_format($item["value"], 2); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    
                                    <?php if (count($report_data['high_value']) == 0): ?>
                                    <tr>
                                        <td colspan="5" class="text-center">No inventory data available.</td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                <?php elseif ($report_type == 'customers'): ?>
                    <div class="report-header">
                        <h3>Customer Report</h3>
                        <p class="report-timestamp">Generated on <?php echo date('F j, Y g:i A'); ?></p>
                    </div>
                    
                    <div class="chart-container">
                        <div class="chart-card">
                            <h4>Customer Purchases by Category</h4>
                            <canvas id="customerByCategoryChart"></canvas>
                        </div>
                    </div>
                    
                    <div class="report-section">
                        <h4>Top Customers</h4>
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Customer ID</th>
                                        <th>Transactions</th>
                                        <th>Total Spent</th>
                                        <th>Last Purchase</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($report_data['top_customers'] as $customer): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($customer["prs_id"]); ?></td>
                                        <td><?php echo number_format($customer["transaction_count"]); ?></td>
                                        <td>$<?php echo number_format($customer["total_amount"], 2); ?></td>
                                        <td><?php echo date("M j, Y", strtotime($customer["last_purchase"])); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    
                                    <?php if (count($report_data['top_customers']) == 0): ?>
                                    <tr>
                                        <td colspan="4" class="text-center">No customer data available.</td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <div class="report-section">
                        <h4>Category Breakdown</h4>
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Category</th>
                                        <th>Customer Count</th>
                                        <th>Items Sold</th>
                                        <th>Total Sales</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($report_data['by_category'] as $cat): ?>
                                    <tr>
                                        <td>
                                            <span class="category-badge <?php echo $cat["category"]; ?>">
                                                <?php echo ucfirst($cat["category"]); ?>
                                            </span>
                                        </td>
                                        <td><?php echo number_format($cat["customer_count"]); ?></td>
                                        <td><?php echo number_format($cat["quantity"]); ?></td>
                                        <td>$<?php echo number_format($cat["amount"], 2); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    
                                    <?php if (count($report_data['by_category']) == 0): ?>
                                    <tr>
                                        <td colspan="4" class="text-center">No category data available.</td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
    
    <footer>
        <p>&copy; 2025 Pandemic Resilience System | Data-driven Systems (5CM506)</p>
    </footer>
    
    <style>
        .report-controls {
            background-color: #f9f9f9;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
        }
        
        .report-container {
            background-color: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }
        
        .report-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #eee;
        }
        
        .report-timestamp {
            color: #777;
            font-size: 0.9rem;
        }
        
        .report-section {
            margin-bottom: 2rem;
        }
        
        .chart-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .chart-card {
            background-color: #f9f9f9;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        
        .chart-card h4 {
            margin-top: 0;
            margin-bottom: 1rem;
            color: #444;
        }
        
        .data-table th, .data-table td {
            padding: 0.75rem;
        }
    </style>
    
    <script src="../../assets/js/main.js"></script>
    <script>
        document.getElementById('report_type').addEventListener('change', function() {
            const timePeriodSelect = document.getElementById('time_period');
            timePeriodSelect.disabled = this.value !== 'sales';
        });
        
        document.addEventListener('DOMContentLoaded', function() {
            <?php if ($report_type == 'sales'): ?>
                const trendLabels = [
                    <?php 
                    $trend_data_reverse = array_reverse($report_data['trend']);
                    foreach ($trend_data_reverse as $point) {
                        if ($time_period == 'day') {
                            echo "'" . date('M j', strtotime($point['period'])) . "', ";
                        } elseif ($time_period == 'week') {
                            echo "'" . $point['period'] . "', ";
                        } else {
                            echo "'" . date('M Y', strtotime($point['period'] . '-01')) . "', ";
                        }
                    }
                    ?>
                ];
                
                const trendData = {
                    labels: trendLabels,
                    datasets: [{
                        label: 'Sales Count',
                        data: [
                            <?php 
                            foreach ($trend_data_reverse as $point) {
                                echo $point['count'] . ", ";
                            }
                            ?>
                        ],
                        backgroundColor: 'rgba(54, 162, 235, 0.2)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 2,
                        tension: 0.4,
                        yAxisID: 'y'
                    }, {
                        label: 'Sales Amount ($)',
                        data: [
                            <?php 
                            foreach ($trend_data_reverse as $point) {
                                echo $point['amount'] . ", ";
                            }
                            ?>
                        ],
                        backgroundColor: 'rgba(255, 99, 132, 0.2)',
                        borderColor: 'rgba(255, 99, 132, 1)',
                        borderWidth: 2,
                        tension: 0.4,
                        yAxisID: 'y1'
                    }]
                };
                
                const trendChart = new Chart(
                    document.getElementById('salesTrendChart'),
                    {
                        type: 'line',
                        data: trendData,
                        options: {
                            responsive: true,
                            interaction: {
                                mode: 'index',
                                intersect: false,
                            },
                            scales: {
                                y: {
                                    type: 'linear',
                                    display: true,
                                    position: 'left',
                                    title: {
                                        display: true,
                                        text: 'Number of Sales'
                                    }
                                },
                                y1: {
                                    type: 'linear',
                                    display: true,
                                    position: 'right',
                                    grid: {
                                        drawOnChartArea: false,
                                    },
                                    title: {
                                        display: true,
                                        text: 'Sales Amount ($)'
                                    }
                                }
                            }
                        }
                    }
                );
                
                const categoryData = {
                    labels: [
                        <?php 
                        foreach ($report_data['by_category'] as $category) {
                            echo "'" . ucfirst($category['category']) . "', ";
                        }
                        ?>
                    ],
                    datasets: [{
                        label: 'Sales Amount',
                        data: [
                            <?php 
                            foreach ($report_data['by_category'] as $category) {
                                echo $category['amount'] . ", ";
                            }
                            ?>
                        ],
                        backgroundColor: [
                            'rgba(75, 192, 192, 0.6)',
                            'rgba(54, 162, 235, 0.6)',
                            'rgba(255, 99, 132, 0.6)',
                            'rgba(255, 206, 86, 0.6)',
                            'rgba(153, 102, 255, 0.6)'
                        ],
                        borderColor: [
                            'rgba(75, 192, 192, 1)',
                            'rgba(54, 162, 235, 1)',
                            'rgba(255, 99, 132, 1)',
                            'rgba(255, 206, 86, 1)',
                            'rgba(153, 102, 255, 1)'
                        ],
                        borderWidth: 1
                    }]
                };
                
                const categoryChart = new Chart(
                    document.getElementById('salesByCategoryChart'),
                    {
                        type: 'pie',
                        data: categoryData,
                        options: {
                            responsive: true,
                            plugins: {
                                legend: {
                                    position: 'right',
                                },
                                tooltip: {
                                    callbacks: {
                                        label: function(context) {
                                            let label = context.label || '';
                                            if (label) {
                                                label += ': ';
                                            }
                                            if (context.parsed !== null) {
                                                label += new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(context.parsed);
                                            }
                                            return label;
                                        }
                                    }
                                }
                            }
                        }
                    }
                );
                
            <?php elseif ($report_type == 'inventory'): ?>
                const categoryData = {
                    labels: [
                        <?php 
                        foreach ($report_data['by_category'] as $category) {
                            echo "'" . ucfirst($category['category']) . "', ";
                        }
                        ?>
                    ],
                    datasets: [{
                        label: 'Quantity',
                        data: [
                            <?php 
                            foreach ($report_data['by_category'] as $category) {
                                echo $category['quantity'] . ", ";
                            }
                            ?>
                        ],
                        backgroundColor: [
                            'rgba(75, 192, 192, 0.6)',
                            'rgba(54, 162, 235, 0.6)',
                            'rgba(255, 99, 132, 0.6)',
                            'rgba(255, 206, 86, 0.6)',
                            'rgba(153, 102, 255, 0.6)'
                        ],
                        borderColor: [
                            'rgba(75, 192, 192, 1)',
                            'rgba(54, 162, 235, 1)',
                            'rgba(255, 99, 132, 1)',
                            'rgba(255, 206, 86, 1)',
                            'rgba(153, 102, 255, 1)'
                        ],
                        borderWidth: 1
                    }]
                };
                
                const categoryChart = new Chart(
                    document.getElementById('inventoryByCategoryChart'),
                    {
                        type: 'pie',
                        data: categoryData,
                        options: {
                            responsive: true,
                            plugins: {
                                legend: {
                                    position: 'right',
                                },
                                tooltip: {
                                    callbacks: {
                                        label: function(context) {
                                            let label = context.label || '';
                                            if (label) {
                                                label += ': ';
                                            }
                                            if (context.parsed !== null) {
                                                label += new Intl.NumberFormat('en-US').format(context.parsed) + ' items';
                                            }
                                            return label;
                                        }
                                    }
                                }
                            }
                        }
                    }
                );
                
                const valueData = {
                    labels: [
                        <?php 
                        foreach ($report_data['by_category'] as $category) {
                            echo "'" . ucfirst($category['category']) . "', ";
                        }
                        ?>
                    ],
                    datasets: [{
                        label: 'Total Value ($)',
                        data: [
                            <?php 
                            foreach ($report_data['by_category'] as $category) {
                                echo $category['value'] . ", ";
                            }
                            ?>
                        ],
                        backgroundColor: 'rgba(54, 162, 235, 0.6)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 1
                    }]
                };
                
                const valueChart = new Chart(
                    document.getElementById('inventoryValueChart'),
                    {
                        type: 'bar',
                        data: valueData,
                        options: {
                            responsive: true,
                            plugins: {
                                legend: {
                                    display: false
                                },
                                tooltip: {
                                    callbacks: {
                                        label: function(context) {
                                            let label = context.dataset.label || '';
                                            if (label) {
                                                label += ': ';
                                            }
                                            if (context.parsed.y !== null) {
                                                label += new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(context.parsed.y);
                                            }
                                            return label;
                                        }
                                    }
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: {
                                        callback: function(value) {
                                            return '$' + value.toLocaleString();
                                        }
                                    }
                                }
                            }
                        }
                    }
                );
                
            <?php elseif ($report_type == 'customers'): ?>
                const categoryData = {
                    labels: [
                        <?php 
                        foreach ($report_data['by_category'] as $category) {
                            echo "'" . ucfirst($category['category']) . "', ";
                        }
                        ?>
                    ],
                    datasets: [{
                        label: 'Customer Count',
                        data: [
                            <?php 
                            foreach ($report_data['by_category'] as $category) {
                                echo $category['customer_count'] . ", ";
                            }
                            ?>
                        ],
                        backgroundColor: 'rgba(75, 192, 192, 0.6)',
                        borderColor: 'rgba(75, 192, 192, 1)',
                        borderWidth: 1,
                        yAxisID: 'y'
                    }, {
                        label: 'Sales Amount ($)',
                        data: [
                            <?php 
                            foreach ($report_data['by_category'] as $category) {
                                echo $category['amount'] . ", ";
                            }
                            ?>
                        ],
                        backgroundColor: 'rgba(255, 99, 132, 0.6)',
                        borderColor: 'rgba(255, 99, 132, 1)',
                        borderWidth: 1,
                        yAxisID: 'y1'
                    }]
                };
                
                const categoryChart = new Chart(
                    document.getElementById('customerByCategoryChart'),
                    {
                        type: 'bar',
                        data: categoryData,
                        options: {
                            responsive: true,
                            scales: {
                                y: {
                                    type: 'linear',
                                    display: true,
                                    position: 'left',
                                    title: {
                                        display: true,
                                        text: 'Number of Customers'
                                    }
                                },
                                y1: {
                                    type: 'linear',
                                    display: true,
                                    position: 'right',
                                    grid: {
                                        drawOnChartArea: false,
                                    },
                                    title: {
                                        display: true,
                                        text: 'Sales Amount ($)'
                                    },
                                    ticks: {
                                        callback: function(value) {
                                            return '$' + value.toLocaleString();
                                        }
                                    }
                                }
                            }
                        }
                    }
                );
            <?php endif; ?>
        });
        
        document.getElementById('export-report').addEventListener('click', function(e) {
            e.preventDefault();
            alert('Export functionality would be implemented here.');
        });
    </script>
</body>
</html>