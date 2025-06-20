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

// Process report generation if form submitted
$report_message = "";
$report_status = "";
$report_data = [];
$selected_report = isset($_GET["report_type"]) ? sanitize_input($_GET["report_type"]) : "vaccination";
$time_period = isset($_GET["time_period"]) ? sanitize_input($_GET["time_period"]) : "month";

// Get report data based on selection
switch ($selected_report) {
    case "vaccination":
        // Vaccination report
        
        // Get vaccination timeline data
        $timeline_sql = "SELECT 
                        DATE_FORMAT(vaccination_date, '%Y-%m') as period, 
                        COUNT(*) as count 
                        FROM vaccinations 
                        GROUP BY period 
                        ORDER BY period DESC 
                        LIMIT 12";
        
        if ($time_period == "week") {
            $timeline_sql = "SELECT 
                            YEARWEEK(vaccination_date) as period, 
                            COUNT(*) as count 
                            FROM vaccinations 
                            GROUP BY period 
                            ORDER BY period DESC 
                            LIMIT 12";
        } elseif ($time_period == "day") {
            $timeline_sql = "SELECT 
                            DATE_FORMAT(vaccination_date, '%Y-%m-%d') as period, 
                            COUNT(*) as count 
                            FROM vaccinations 
                            GROUP BY period 
                            ORDER BY period DESC 
                            LIMIT 30";
        }
        
        $timeline_result = $conn->query($timeline_sql);
        $timeline_data = [];
        
        while ($row = $timeline_result->fetch_assoc()) {
            $timeline_data[] = $row;
        }
        
        // Get vaccination by vaccine type
        $by_vaccine_sql = "SELECT 
                          vaccine_name, 
                          COUNT(*) as count 
                          FROM vaccinations 
                          GROUP BY vaccine_name 
                          ORDER BY count DESC";
        $by_vaccine_result = $conn->query($by_vaccine_sql);
        $by_vaccine_data = [];
        
        while ($row = $by_vaccine_result->fetch_assoc()) {
            $by_vaccine_data[] = $row;
        }
        
        // Get vaccination coverage - Fix for the error: unknown column 'prs_id'
        // Get the column name from the database or table structure
        // For now, let's try a simple count instead
        $coverage_sql = "SELECT 
                        COUNT(*) as vaccinated_count, 
                        (SELECT COUNT(*) FROM users WHERE role = 'public') as total_users 
                        FROM vaccinations";
        $coverage_result = $conn->query($coverage_sql);
        $coverage_data = $coverage_result->fetch_assoc();
        
        $report_data = [
            'timeline' => $timeline_data,
            'by_vaccine' => $by_vaccine_data,
            'coverage' => $coverage_data
        ];
        
        break;
        
    case "inventory":
        // Inventory report
        
        // Get inventory by category
        $by_category_sql = "SELECT 
                           it.category, 
                           COUNT(DISTINCT i.item_id) as item_types, 
                           SUM(i.quantity) as total_quantity, 
                           SUM(i.quantity * i.price) as total_value 
                           FROM inventory i 
                           JOIN items it ON i.item_id = it.item_id 
                           GROUP BY it.category";
        $by_category_result = $conn->query($by_category_sql);
        $by_category_data = [];
        
        while ($row = $by_category_result->fetch_assoc()) {
            $by_category_data[] = $row;
        }
        
        // Get inventory by merchant type
        $by_merchant_sql = "SELECT 
                           m.business_type, 
                           COUNT(DISTINCT m.merchant_id) as merchant_count, 
                           SUM(i.quantity) as total_quantity, 
                           SUM(i.quantity * i.price) as total_value 
                           FROM inventory i 
                           JOIN merchants m ON i.merchant_id = m.merchant_id 
                           GROUP BY m.business_type";
        $by_merchant_result = $conn->query($by_merchant_sql);
        $by_merchant_data = [];
        
        while ($row = $by_merchant_result->fetch_assoc()) {
            $by_merchant_data[] = $row;
        }
        
        // Get low stock items
        $low_stock_sql = "SELECT 
                         it.name, 
                         it.category, 
                         i.quantity, 
                         m.business_name 
                         FROM inventory i 
                         JOIN items it ON i.item_id = it.item_id 
                         JOIN merchants m ON i.merchant_id = m.merchant_id 
                         WHERE i.quantity < 10 
                         ORDER BY i.quantity ASC 
                         LIMIT 20";
        $low_stock_result = $conn->query($low_stock_sql);
        $low_stock_data = [];
        
        while ($row = $low_stock_result->fetch_assoc()) {
            $low_stock_data[] = $row;
        }
        
        $report_data = [
            'by_category' => $by_category_data,
            'by_merchant' => $by_merchant_data,
            'low_stock' => $low_stock_data
        ];
        
        break;
        
    case "transactions":
        // Transactions report
        
        // Get transaction timeline data
        $timeline_sql = "SELECT 
                        DATE_FORMAT(transaction_date, '%Y-%m') as period, 
                        COUNT(*) as count, 
                        SUM(total_amount) as total_amount 
                        FROM transactions 
                        GROUP BY period 
                        ORDER BY period DESC 
                        LIMIT 12";
        
        if ($time_period == "week") {
            $timeline_sql = "SELECT 
                            YEARWEEK(transaction_date) as period, 
                            COUNT(*) as count, 
                            SUM(total_amount) as total_amount 
                            FROM transactions 
                            GROUP BY period 
                            ORDER BY period DESC 
                            LIMIT 12";
        } elseif ($time_period == "day") {
            $timeline_sql = "SELECT 
                            DATE_FORMAT(transaction_date, '%Y-%m-%d') as period, 
                            COUNT(*) as count, 
                            SUM(total_amount) as total_amount 
                            FROM transactions 
                            GROUP BY period 
                            ORDER BY period DESC 
                            LIMIT 30";
        }
        
        $timeline_result = $conn->query($timeline_sql);
        $timeline_data = [];
        
        while ($row = $timeline_result->fetch_assoc()) {
            $timeline_data[] = $row;
        }
        
        // Get transactions by merchant category
        $by_merchant_sql = "SELECT 
                           m.business_type, 
                           COUNT(*) as transaction_count, 
                           SUM(t.total_amount) as total_amount 
                           FROM transactions t 
                           JOIN merchants m ON t.merchant_id = m.merchant_id 
                           GROUP BY m.business_type 
                           ORDER BY total_amount DESC";
        $by_merchant_result = $conn->query($by_merchant_sql);
        $by_merchant_data = [];
        
        while ($row = $by_merchant_result->fetch_assoc()) {
            $by_merchant_data[] = $row;
        }
        
        // Get top selling items
        $top_items_sql = "SELECT 
                         it.name, 
                         it.category, 
                         SUM(ti.quantity) as total_quantity, 
                         SUM(ti.quantity * ti.price_per_unit) as total_value 
                         FROM transaction_items ti 
                         JOIN items it ON ti.item_id = it.item_id 
                         GROUP BY ti.item_id 
                         ORDER BY total_quantity DESC 
                         LIMIT 10";
        $top_items_result = $conn->query($top_items_sql);
        $top_items_data = [];
        
        while ($row = $top_items_result->fetch_assoc()) {
            $top_items_data[] = $row;
        }
        
        $report_data = [
            'timeline' => $timeline_data,
            'by_merchant' => $by_merchant_data,
            'top_items' => $top_items_data
        ];
        
        break;
    
    case "users":
        // User activity report
        
        // Get user registration timeline
        if ($time_period == "day") {
            $period_sql = "DATE_FORMAT(created_at, '%Y-%m-%d') as period";
            $group_sql = "DATE_FORMAT(created_at, '%Y-%m-%d')";
            $limit = 30; // Last 30 days
        } elseif ($time_period == "week") {
            $period_sql = "YEARWEEK(created_at) as period";
            $group_sql = "YEARWEEK(created_at)";
            $limit = 12; // Last 12 weeks
        } else { // month
            $period_sql = "DATE_FORMAT(created_at, '%Y-%m') as period";
            $group_sql = "DATE_FORMAT(created_at, '%Y-%m')";
            $limit = 12; // Last 12 months
        }
        
        $timeline_sql = "SELECT $period_sql, COUNT(*) as count FROM users GROUP BY $group_sql ORDER BY period DESC LIMIT $limit";
        $timeline_result = $conn->query($timeline_sql);
        $timeline_data = [];
        
        while ($row = $timeline_result->fetch_assoc()) {
            $timeline_data[] = $row;
        }
        
        // Get users by role
        $by_role_sql = "SELECT role, COUNT(*) as count FROM users GROUP BY role";
        $by_role_result = $conn->query($by_role_sql);
        $by_role_data = [];
        
        while ($row = $by_role_result->fetch_assoc()) {
            $by_role_data[] = $row;
        }
        
        // Get users by location
        $by_location_sql = "SELECT city, COUNT(*) as count FROM users WHERE city IS NOT NULL AND city != '' GROUP BY city ORDER BY count DESC LIMIT 10";
        $by_location_result = $conn->query($by_location_sql);
        $by_location_data = [];
        
        while ($row = $by_location_result->fetch_assoc()) {
            $by_location_data[] = $row;
        }
        
        $report_data = [
            'timeline' => $timeline_data,
            'by_role' => $by_role_data,
            'by_location' => $by_location_data
        ];
        
        break;
}

// Log report generation
log_activity($_SESSION["prs_id"], "generate", "report", $selected_report, "success");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics - Government Dashboard</title>
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
                    <li><a href="inventory.php">Inventory Status</a></li>
                    <li><a href="reports.php" class="active">Reports & Analytics</a></li>
                    <li><a href="#">System Settings</a></li>
                </ul>
            </nav>
        </aside>
        
        <div class="dashboard-content">
            <h2>Reports & Analytics</h2>
            
            <?php if (!empty($report_message)): ?>
                <div class="alert alert-<?php echo $report_status; ?>">
                    <?php echo $report_message; ?>
                </div>
            <?php endif; ?>
            
            <div class="report-controls">
                <form method="get" action="reports.php" class="report-form">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="report_type">Report Type</label>
                            <select id="report_type" name="report_type" class="form-control">
                                <option value="vaccination" <?php echo ($selected_report == 'vaccination') ? 'selected' : ''; ?>>Vaccination Report</option>
                                <option value="inventory" <?php echo ($selected_report == 'inventory') ? 'selected' : ''; ?>>Inventory Report</option>
                                <option value="transactions" <?php echo ($selected_report == 'transactions') ? 'selected' : ''; ?>>Transactions Report</option>
                                <option value="users" <?php echo ($selected_report == 'users') ? 'selected' : ''; ?>>User Activity Report</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="time_period">Time Period</label>
                            <select id="time_period" name="time_period" class="form-control">
                                <option value="day" <?php echo ($time_period == 'day') ? 'selected' : ''; ?>>Daily</option>
                                <option value="week" <?php echo ($time_period == 'week') ? 'selected' : ''; ?>>Weekly</option>
                                <option value="month" <?php echo ($time_period == 'month') ? 'selected' : ''; ?>>Monthly</option>
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
                <?php if ($selected_report == 'vaccination'): ?>
                    <div class="report-header">
                        <h3>Vaccination Report</h3>
                        <p class="report-timestamp">Generated on <?php echo date('F j, Y g:i A'); ?></p>
                    </div>
                    
                    <div class="chart-container">
                        <div class="chart-card">
                            <h4>Vaccination Timeline</h4>
                            <canvas id="vaccinationTimeline"></canvas>
                        </div>
                        
                        <div class="chart-card">
                            <h4>Vaccination by Type</h4>
                            <canvas id="vaccinationByType"></canvas>
                        </div>
                    </div>
                    
                    <div class="chart-container">
                        <div class="chart-card">
                            <h4>Vaccination Coverage</h4>
                            <div class="coverage-container">
                                <div class="coverage-gauge">
                                    <canvas id="coverageGauge"></canvas>
                                </div>
                                <div class="coverage-stats">
                                    <div class="stat-item">
                                        <span class="stat-label">Vaccinated Users:</span>
                                        <span class="stat-value"><?php echo number_format($report_data['coverage']['vaccinated_count']); ?></span>
                                    </div>
                                    <div class="stat-item">
                                        <span class="stat-label">Total Users:</span>
                                        <span class="stat-value"><?php echo number_format($report_data['coverage']['total_users']); ?></span>
                                    </div>
                                    <div class="stat-item">
                                        <span class="stat-label">Coverage Rate:</span>
                                        <span class="stat-value">
                                            <?php 
                                            $coverage_rate = 0;
                                            if ($report_data['coverage']['total_users'] > 0) {
                                                $coverage_rate = ($report_data['coverage']['vaccinated_count'] / $report_data['coverage']['total_users']) * 100;
                                            }
                                            echo number_format($coverage_rate, 1) . '%';
                                            ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="chart-card">
                            <h4>Vaccination by Type Breakdown</h4>
                            <div class="table-responsive">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Vaccine Name</th>
                                            <th>Count</th>
                                            <th>Percentage</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $total_vaccinations = 0;
                                        foreach ($report_data['by_vaccine'] as $vaccine) {
                                            $total_vaccinations += $vaccine['count'];
                                        }
                                        
                                        foreach ($report_data['by_vaccine'] as $vaccine): 
                                        $percentage = 0;
                                        if ($total_vaccinations > 0) {
                                            $percentage = ($vaccine['count'] / $total_vaccinations) * 100;
                                        }
                                        ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($vaccine['vaccine_name']); ?></td>
                                            <td><?php echo number_format($vaccine['count']); ?></td>
                                            <td><?php echo number_format($percentage, 1) . '%'; ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                        
                                        <?php if (count($report_data['by_vaccine']) == 0): ?>
                                        <tr>
                                            <td colspan="3" class="text-center">No vaccination data available.</td>
                                        </tr>
                                        <?php endif; ?>
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <th>Total</th>
                                            <th><?php echo number_format($total_vaccinations); ?></th>
                                            <th>100%</th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                <?php elseif ($selected_report == 'inventory'): ?>
                    <div class="report-header">
                        <h3>Inventory Report</h3>
                        <p class="report-timestamp">Generated on <?php echo date('F j, Y g:i A'); ?></p>
                    </div>
                    
                    <div class="chart-container">
                        <div class="chart-card">
                            <h4>Inventory by Category</h4>
                            <canvas id="inventoryByCategory"></canvas>
                        </div>
                        
                        <div class="chart-card">
                            <h4>Inventory by Merchant Type</h4>
                            <canvas id="inventoryByMerchant"></canvas>
                        </div>
                    </div>
                    
                    <div class="chart-container">
                        <div class="chart-card">
                            <h4>Category Breakdown</h4>
                            <div class="table-responsive">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Category</th>
                                            <th>Item Types</th>
                                            <th>Total Quantity</th>
                                            <th>Total Value</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($report_data['by_category'] as $category): ?>
                                        <tr>
                                            <td><?php echo ucfirst(htmlspecialchars($category['category'])); ?></td>
                                            <td><?php echo number_format($category['item_types']); ?></td>
                                            <td><?php echo number_format($category['total_quantity']); ?></td>
                                            <td>$<?php echo number_format($category['total_value'], 2); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                        
                                        <?php if (count($report_data['by_category']) == 0): ?>
                                        <tr>
                                            <td colspan="4" class="text-center">No inventory data available.</td>
                                        </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        
                        <div class="chart-card">
                            <h4>Low Stock Alert</h4>
                            <div class="table-responsive">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Item Name</th>
                                            <th>Category</th>
                                            <th>Quantity</th>
                                            <th>Merchant</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($report_data['low_stock'] as $item): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($item['name']); ?></td>
                                            <td>
                                                <span class="category-badge <?php echo $item['category']; ?>">
                                                    <?php echo ucfirst($item['category']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo number_format($item['quantity']); ?></td>
                                            <td><?php echo htmlspecialchars($item['business_name']); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                        
                                        <?php if (count($report_data['low_stock']) == 0): ?>
                                        <tr>
                                            <td colspan="4" class="text-center">No low stock items found.</td>
                                        </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                <?php elseif ($selected_report == 'transactions'): ?>
                    <div class="report-header">
                        <h3>Transactions Report</h3>
                        <p class="report-timestamp">Generated on <?php echo date('F j, Y g:i A'); ?></p>
                    </div>
                    
                    <div class="chart-container">
                        <div class="chart-card">
                            <h4>Transaction Timeline</h4>
                            <canvas id="transactionTimeline"></canvas>
                        </div>
                        
                        <div class="chart-card">
                            <h4>Transactions by Merchant Type</h4>
                            <canvas id="transactionsByMerchant"></canvas>
                        </div>
                    </div>
                    
                    <div class="chart-container">
                        <div class="chart-card">
                            <h4>Top Selling Items</h4>
                            <div class="table-responsive">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Item Name</th>
                                            <th>Category</th>
                                            <th>Total Quantity</th>
                                            <th>Total Value</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($report_data['top_items'] as $item): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($item['name']); ?></td>
                                            <td>
                                                <span class="category-badge <?php echo $item['category']; ?>">
                                                    <?php echo ucfirst($item['category']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo number_format($item['total_quantity']); ?></td>
                                            <td>$<?php echo number_format($item['total_value'], 2); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                        
                                        <?php if (count($report_data['top_items']) == 0): ?>
                                        <tr>
                                            <td colspan="4" class="text-center">No transaction data available.</td>
                                        </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        
                        <div class="chart-card">
                            <h4>Transactions by Merchant Type Breakdown</h4>
                            <div class="table-responsive">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Business Type</th>
                                            <th>Transaction Count</th>
                                            <th>Total Amount</th>
                                            <th>Average Transaction</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($report_data['by_merchant'] as $merchant): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($merchant['business_type']); ?></td>
                                            <td><?php echo number_format($merchant['transaction_count']); ?></td>
                                            <td>$<?php echo number_format($merchant['total_amount'], 2); ?></td>
                                            <td>$<?php echo number_format($merchant['total_amount'] / $merchant['transaction_count'], 2); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                        
                                        <?php if (count($report_data['by_merchant']) == 0): ?>
                                        <tr>
                                            <td colspan="4" class="text-center">No transaction data available.</td>
                                        </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                <?php elseif ($selected_report == 'users'): ?>
                    <div class="report-header">
                        <h3>User Activity Report</h3>
                        <p class="report-timestamp">Generated on <?php echo date('F j, Y g:i A'); ?></p>
                    </div>
                    
                    <div class="chart-container">
                        <div class="chart-card">
                            <h4>User Registration Timeline</h4>
                            <canvas id="userTimeline"></canvas>
                        </div>
                        
                        <div class="chart-card">
                            <h4>Users by Role</h4>
                            <canvas id="usersByRole"></canvas>
                        </div>
                    </div>
                    
                    <div class="chart-container">
                        <div class="chart-card">
                            <h4>Users by Location</h4>
                            <canvas id="usersByLocation"></canvas>
                        </div>
                        
                        <div class="chart-card">
                            <h4>User Breakdown by Role</h4>
                            <div class="table-responsive">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Role</th>
                                            <th>Count</th>
                                            <th>Percentage</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $total_users = 0;
                                        foreach ($report_data['by_role'] as $role) {
                                            $total_users += $role['count'];
                                        }
                                        
                                        foreach ($report_data['by_role'] as $role): 
                                        $percentage = 0;
                                        if ($total_users > 0) {
                                            $percentage = ($role['count'] / $total_users) * 100;
                                        }
                                        ?>
                                        <tr>
                                            <td><?php echo ucfirst(htmlspecialchars($role['role'])); ?></td>
                                            <td><?php echo number_format($role['count']); ?></td>
                                            <td><?php echo number_format($percentage, 1) . '%'; ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                        
                                        <?php if (count($report_data['by_role']) == 0): ?>
                                        <tr>
                                            <td colspan="3" class="text-center">No user data available.</td>
                                        </tr>
                                        <?php endif; ?>
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <th>Total</th>
                                            <th><?php echo number_format($total_users); ?></th>
                                            <th>100%</th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
    
    <footer>
        <p>&copy; 2025 Pandemic Resilience System | Data-driven Systems (5CM506)</p>
    </footer>
    
    <script>
        // Initialize charts based on selected report
        document.addEventListener('DOMContentLoaded', function() {
            <?php if ($selected_report == 'vaccination'): ?>
                // Vaccination timeline chart
                const timelineData = {
                    labels: [
                        <?php 
                        foreach ($report_data['timeline'] as $point) {
                            echo "'" . $point['period'] . "', ";
                        }
                        ?>
                    ],
                    datasets: [{
                        label: 'Vaccinations',
                        data: [
                            <?php 
                            foreach ($report_data['timeline'] as $point) {
                                echo $point['count'] . ", ";
                            }
                            ?>
                        ],
                        backgroundColor: 'rgba(75, 192, 192, 0.2)',
                        borderColor: 'rgba(75, 192, 192, 1)',
                        borderWidth: 2,
                        tension: 0.4
                    }]
                };
                
                const timelineChart = new Chart(
                    document.getElementById('vaccinationTimeline'),
                    {
                        type: 'line',
                        data: timelineData,
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
                
                // Vaccination by type chart
                const byTypeData = {
                    labels: [
                        <?php 
                        foreach ($report_data['by_vaccine'] as $vaccine) {
                            echo "'" . $vaccine['vaccine_name'] . "', ";
                        }
                        ?>
                    ],
                    datasets: [{
                        label: 'Count',
                        data: [
                            <?php 
                            foreach ($report_data['by_vaccine'] as $vaccine) {
                                echo $vaccine['count'] . ", ";
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
                
                const byTypeChart = new Chart(
                    document.getElementById('vaccinationByType'),
                    {
                        type: 'pie',
                        data: byTypeData,
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
                
                // Vaccination coverage gauge chart
                const coverageRate = <?php echo $coverage_rate ?? 0; ?>;
                
                const coverageData = {
                    labels: ['Vaccinated', 'Unvaccinated'],
                    datasets: [{
                        data: [coverageRate, 100 - coverageRate],
                        backgroundColor: [
                            'rgba(75, 192, 192, 0.6)',
                            'rgba(220, 220, 220, 0.6)'
                        ],
                        borderWidth: 0
                    }]
                };
                
                const coverageChart = new Chart(
                    document.getElementById('coverageGauge'),
                    {
                        type: 'doughnut',
                        data: coverageData,
                        options: {
                            responsive: true,
                            circumference: 180,
                            rotation: 270,
                            plugins: {
                                legend: {
                                    display: false
                                },
                                tooltip: {
                                    enabled: false
                                }
                            }
                        }
                    }
                );
                
            <?php elseif ($selected_report == 'inventory'): ?>
                // Inventory by category chart
                const categoryData = {
                    labels: [
                        <?php 
                        foreach ($report_data['by_category'] as $category) {
                            echo "'" . ucfirst($category['category']) . "', ";
                        }
                        ?>
                    ],
                    datasets: [{
                        label: 'Total Quantity',
                        data: [
                            <?php 
                            foreach ($report_data['by_category'] as $category) {
                                echo $category['total_quantity'] . ", ";
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
                    document.getElementById('inventoryByCategory'),
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
                
                // Inventory by merchant type chart
                const merchantData = {
                    labels: [
                        <?php 
                        foreach ($report_data['by_merchant'] as $merchant) {
                            echo "'" . $merchant['business_type'] . "', ";
                        }
                        ?>
                    ],
                    datasets: [{
                        label: 'Total Quantity',
                        data: [
                            <?php 
                            foreach ($report_data['by_merchant'] as $merchant) {
                                echo $merchant['total_quantity'] . ", ";
                            }
                            ?>
                        ],
                        backgroundColor: 'rgba(54, 162, 235, 0.6)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 1
                    }]
                };
                
                const merchantChart = new Chart(
                    document.getElementById('inventoryByMerchant'),
                    {
                        type: 'bar',
                        data: merchantData,
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
                
            <?php elseif ($selected_report == 'transactions'): ?>
                // Transaction timeline chart
                const timelineData = {
                    labels: [
                        <?php 
                        foreach ($report_data['timeline'] as $point) {
                            echo "'" . $point['period'] . "', ";
                        }
                        ?>
                    ],
                    datasets: [{
                        label: 'Transaction Count',
                        data: [
                            <?php 
                            foreach ($report_data['timeline'] as $point) {
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
                        label: 'Total Amount ($)',
                        data: [
                            <?php 
                            foreach ($report_data['timeline'] as $point) {
                                echo $point['total_amount'] . ", ";
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
                
                const timelineChart = new Chart(
                    document.getElementById('transactionTimeline'),
                    {
                        type: 'line',
                        data: timelineData,
                        options: {
                            responsive: true,
                            scales: {
                                y: {
                                    type: 'linear',
                                    display: true,
                                    position: 'left',
                                    beginAtZero: true
                                },
                                y1: {
                                    type: 'linear',
                                    display: true,
                                    position: 'right',
                                    beginAtZero: true,
                                    grid: {
                                        drawOnChartArea: false
                                    }
                                }
                            }
                        }
                    }
                );
                
                // Transactions by merchant type chart
                const merchantData = {
                    labels: [
                        <?php 
                        foreach ($report_data['by_merchant'] as $merchant) {
                            echo "'" . $merchant['business_type'] . "', ";
                        }
                        ?>
                    ],
                    datasets: [{
                        label: 'Transaction Count',
                        data: [
                            <?php 
                            foreach ($report_data['by_merchant'] as $merchant) {
                                echo $merchant['transaction_count'] . ", ";
                            }
                            ?>
                        ],
                        backgroundColor: 'rgba(75, 192, 192, 0.6)',
                        borderColor: 'rgba(75, 192, 192, 1)',
                        borderWidth: 1
                    }]
                };
                
                const merchantChart = new Chart(
                    document.getElementById('transactionsByMerchant'),
                    {
                        type: 'bar',
                        data: merchantData,
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
                
            <?php elseif ($selected_report == 'users'): ?>
                // User registration timeline chart
                const timelineData = {
                    labels: [
                        <?php 
                        foreach ($report_data['timeline'] as $point) {
                            echo "'" . $point['period'] . "', ";
                        }
                        ?>
                    ],
                    datasets: [{
                        label: 'New Users',
                        data: [
                            <?php 
                            foreach ($report_data['timeline'] as $point) {
                                echo $point['count'] . ", ";
                            }
                            ?>
                        ],
                        backgroundColor: 'rgba(153, 102, 255, 0.2)',
                        borderColor: 'rgba(153, 102, 255, 1)',
                        borderWidth: 2,
                        tension: 0.4
                    }]
                };
                
                const timelineChart = new Chart(
                    document.getElementById('userTimeline'),
                    {
                        type: 'line',
                        data: timelineData,
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
                
                // Users by role chart
                const roleData = {
                    labels: [
                        <?php 
                        foreach ($report_data['by_role'] as $role) {
                            echo "'" . ucfirst($role['role']) . "', ";
                        }
                        ?>
                    ],
                    datasets: [{
                        label: 'User Count',
                        data: [
                            <?php 
                            foreach ($report_data['by_role'] as $role) {
                                echo $role['count'] . ", ";
                            }
                            ?>
                        ],
                        backgroundColor: [
                            'rgba(75, 192, 192, 0.6)',
                            'rgba(54, 162, 235, 0.6)',
                            'rgba(255, 99, 132, 0.6)'
                        ],
                        borderColor: [
                            'rgba(75, 192, 192, 1)',
                            'rgba(54, 162, 235, 1)',
                            'rgba(255, 99, 132, 1)'
                        ],
                        borderWidth: 1
                    }]
                };
                
                const roleChart = new Chart(
                    document.getElementById('usersByRole'),
                    {
                        type: 'pie',
                        data: roleData,
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
                
                // Users by location chart
                const locationData = {
                    labels: [
                        <?php 
                        foreach ($report_data['by_location'] as $location) {
                            echo "'" . $location['city'] . "', ";
                        }
                        ?>
                    ],
                    datasets: [{
                        label: 'User Count',
                        data: [
                            <?php 
                            foreach ($report_data['by_location'] as $location) {
                                echo $location['count'] . ", ";
                            }
                            ?>
                        ],
                        backgroundColor: 'rgba(255, 159, 64, 0.6)',
                        borderColor: 'rgba(255, 159, 64, 1)',
                        borderWidth: 1
                    }]
                };
                
                const locationChart = new Chart(
                    document.getElementById('usersByLocation'),
                    {
                        type: 'bar',
                        data: locationData,
                        options: {
                            responsive: true,
                            scales: {
                                y: {
                                    beginAtZero: true
                                }
                            },
                            plugins: {
                                legend: {
                                    display: false
                                }
                            }
                        }
                    }
                );
            <?php endif; ?>
        });
        
        // Handle export report button click
        document.getElementById('export-report').addEventListener('click', function(e) {
            e.preventDefault();
            alert('Export functionality would be implemented here.');
            // In a real implementation, this would generate a PDF or Excel report
        });
    </script>
</body>
</html>