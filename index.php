<?php
session_start();

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: ../../login.php");
    exit;
}

if ($_SESSION["role"] !== "government") {
    header("location: ../../index.php");
    exit;
}

require_once "../../config/db_connect.php";

$stats = [
    'total_users' => 0,
    'vaccinated_users' => 0,
    'active_merchants' => 0,
    'essential_items' => 0
];

$sql = "SELECT COUNT(*) as count FROM users WHERE role = 'public'";
if ($result = $conn->query($sql)) {
    if ($row = $result->fetch_assoc()) {
        $stats['total_users'] = $row['count'];
    }
}

$sql = "SELECT COUNT(DISTINCT prs_id) as count FROM vaccinations";
if ($result = $conn->query($sql)) {
    if ($row = $result->fetch_assoc()) {
        $stats['vaccinated_users'] = $row['count'];
    }
}

$sql = "SELECT COUNT(*) as count FROM merchants WHERE status = 'active'";
if ($result = $conn->query($sql)) {
    if ($row = $result->fetch_assoc()) {
        $stats['active_merchants'] = $row['count'];
    }
}

$sql = "SELECT SUM(i.quantity) as count 
        FROM inventory i 
        JOIN items it ON i.item_id = it.item_id 
        WHERE it.category IN ('essential', 'medical')";
if ($result = $conn->query($sql)) {
    if ($row = $result->fetch_assoc()) {
        $stats['essential_items'] = $row['count'] ?? 0;
    }
}

$transactions = [];
$sql = "SELECT t.transaction_id, t.prs_id, t.transaction_date, m.business_name, t.total_amount 
        FROM transactions t 
        JOIN merchants m ON t.merchant_id = m.merchant_id 
        ORDER BY t.transaction_date DESC 
        LIMIT 10";

if ($result = $conn->query($sql)) {
    while ($row = $result->fetch_assoc()) {
        $transactions[] = $row;
    }
}

log_activity($_SESSION["prs_id"], "view", "dashboard", "government", "success");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Government Dashboard - Pandemic Resilience System</title>
    <link rel="stylesheet" href="../../assets/css/main.css">
    <link rel="stylesheet" href="../../assets/css/gov-dashboard.css">
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
                    <li><a href="index.php" class="active">Dashboard</a></li>
                    <li><a href="users.php">Manage Users</a></li>
                    <li><a href="merchants.php">Manage Merchants</a></li>
                    <li><a href="inventory.php">Inventory Status</a></li>
                    <li><a href="reports.php">Reports & Analytics</a></li>
                    <li><a href="#">System Settings</a></li>
                </ul>
            </nav>
        </aside>
        
        <div class="dashboard-content">
            <h2>Government Dashboard</h2>
            
            <div class="stats-container">
                <div class="stat-card">
                    <div class="stat-icon users">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Total Citizens</h3>
                        <p class="stat-value"><?php echo number_format($stats['total_users']); ?></p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon vaccinations">
                        <i class="fas fa-syringe"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Vaccinated</h3>
                        <p class="stat-value"><?php echo number_format($stats['vaccinated_users']); ?></p>
                        <p class="stat-percent">
                            <?php 
                            $percent = ($stats['total_users'] > 0) ? round(($stats['vaccinated_users'] / $stats['total_users']) * 100) : 0;
                            echo $percent . '%';
                            ?>
                        </p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon merchants">
                        <i class="fas fa-store"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Active Merchants</h3>
                        <p class="stat-value"><?php echo number_format($stats['active_merchants']); ?></p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon inventory">
                        <i class="fas fa-box"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Essential Items</h3>
                        <p class="stat-value"><?php echo number_format($stats['essential_items']); ?></p>
                    </div>
                </div>
            </div>
            
            <div class="chart-container">
                <div class="chart-card">
                    <h3>Vaccination Progress</h3>
                    <canvas id="vaccinationChart"></canvas>
                </div>
                
                <div class="chart-card">
                    <h3>Essential Supplies Status</h3>
                    <canvas id="supplyChart"></canvas>
                </div>
            </div>
            
            <div class="dashboard-section">
                <div class="section-header">
                    <h3>Recent Transactions</h3>
                    <a href="#" class="btn btn-primary btn-sm">View All</a>
                </div>
                
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Transaction ID</th>
                                <th>User ID</th>
                                <th>Merchant</th>
                                <th>Date</th>
                                <th>Amount</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($transactions as $transaction): ?>
                            <tr>
                                <td><?php echo $transaction["transaction_id"]; ?></td>
                                <td><?php echo htmlspecialchars($transaction["prs_id"]); ?></td>
                                <td><?php echo htmlspecialchars($transaction["business_name"]); ?></td>
                                <td><?php echo date("M j, Y g:i A", strtotime($transaction["transaction_date"])); ?></td>
                                <td>$<?php echo number_format($transaction["total_amount"], 2); ?></td>
                                <td>
                                    <a href="#" class="btn btn-primary btn-sm">View Details</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            
                            <?php if (count($transactions) == 0): ?>
                            <tr>
                                <td colspan="6" class="text-center">No recent transactions found.</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="dashboard-section">
                <div class="section-header">
                    <h3>System Alerts</h3>
                    <a href="#" class="btn btn-primary btn-sm">Manage Alerts</a>
                </div>
                
                <div class="alerts-container">
                    <div class="alert-card warning">
                        <div class="alert-header">
                            <h4>Low Inventory Warning</h4>
                            <span class="alert-date">May 4, 2025</span>
                        </div>
                        <p>Medical supplies (N95 masks) are running low in 3 districts. Consider distribution adjustment.</p>
                        <div class="alert-actions">
                            <a href="#" class="btn btn-secondary btn-sm">View Details</a>
                            <a href="#" class="btn btn-primary btn-sm">Take Action</a>
                        </div>
                    </div>
                    
                    <div class="alert-card info">
                        <div class="alert-header">
                            <h4>Vaccination Campaign Update</h4>
                            <span class="alert-date">May 3, 2025</span>
                        </div>
                        <p>Vaccination rate has increased by 5% in the past week. Current coverage: 78%.</p>
                        <div class="alert-actions">
                            <a href="#" class="btn btn-secondary btn-sm">View Details</a>
                        </div>
                    </div>
                    
                    <div class="alert-card danger">
                        <div class="alert-header">
                            <h4>Security Alert</h4>
                            <span class="alert-date">May 2, 2025</span>
                        </div>
                        <p>Multiple failed login attempts detected for government accounts. Security team has been notified.</p>
                        <div class="alert-actions">
                            <a href="#" class="btn btn-secondary btn-sm">View Details</a>
                            <a href="#" class="btn btn-primary btn-sm">Review Logs</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <footer>
        <p>&copy; 2025 Pandemic Resilience System | Data-driven Systems (5CM506)</p>
    </footer>
    
    <script>
        const vaccinationData = {
            labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May'],
            datasets: [{
                label: 'Vaccination Rate (%)',
                data: [45, 52, 60, 72, 78],
                backgroundColor: 'rgba(54, 162, 235, 0.2)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 2,
                tension: 0.4
            }]
        };

        const vaccinationChart = new Chart(
            document.getElementById('vaccinationChart'),
            {
                type: 'line',
                data: vaccinationData,
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100
                        }
                    }
                }
            }
        );
        
        const supplyData = {
            labels: ['Masks', 'Sanitizers', 'Medications', 'Food', 'Water'],
            datasets: [{
                label: 'Current Stock',
                data: [8500, 12000, 6000, 15000, 22000],
                backgroundColor: [
                    'rgba(75, 192, 192, 0.6)',
                    'rgba(54, 162, 235, 0.6)',
                    'rgba(255, 206, 86, 0.6)',
                    'rgba(75, 192, 192, 0.6)',
                    'rgba(153, 102, 255, 0.6)'
                ],
                borderColor: [
                    'rgba(75, 192, 192, 1)',
                    'rgba(54, 162, 235, 1)',
                    'rgba(255, 206, 86, 1)',
                    'rgba(75, 192, 192, 1)',
                    'rgba(153, 102, 255, 1)'
                ],
                borderWidth: 1
            }]
        };

        const supplyChart = new Chart(
            document.getElementById('supplyChart'),
            {
                type: 'bar',
                data: supplyData,
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
    
    <script src="../../assets/js/gov-dashboard.js"></script>
</body>
</html>