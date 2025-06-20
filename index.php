<?php
session_start();

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: ../../login.php");
    exit;
}

if ($_SESSION["role"] !== "public") {
    header("location: ../../index.php");
    exit;
}

require_once "../../config/db_connect.php";

$vaccinations = [];
$sql = "SELECT vaccination_id, vaccine_name, vaccination_date, facility_name, batch_number, certificate_file 
        FROM vaccinations 
        WHERE prs_id = ? 
        ORDER BY vaccination_date DESC";

if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("s", $_SESSION["prs_id"]);
    
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $vaccinations[] = $row;
        }
    }
    
    $stmt->close();
}

$merchants = [];
$sql = "SELECT m.merchant_id, m.business_name, u.city, COUNT(i.item_id) as item_count 
        FROM merchants m 
        JOIN users u ON m.prs_id = u.prs_id 
        JOIN inventory i ON m.merchant_id = i.merchant_id 
        JOIN items it ON i.item_id = it.item_id 
        WHERE m.status = 'active' AND it.category IN ('essential', 'medical') AND i.quantity > 0 
        GROUP BY m.merchant_id 
        ORDER BY u.city, m.business_name";

if ($result = $conn->query($sql)) {
    while ($row = $result->fetch_assoc()) {
        $merchants[] = $row;
    }
}

log_activity($_SESSION["prs_id"], "view", "dashboard", "public", "success");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Public Dashboard - Pandemic Resilience System</title>
    <link rel="stylesheet" href="../../assets/css/main.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/public-dashboard.css">
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
        <?php include "../../includes/nav.php"; ?>
        
        <div class="dashboard-content">
            <h2>My Dashboard</h2>
            
            <div class="dashboard-cards">
                <div class="card">
                    <h3>Vaccination Status</h3>
                    <div class="card-content">
                        <?php if (count($vaccinations) > 0): ?>
                            <div class="status-badge complete">
                                <span>Vaccinated</span>
                            </div>
                            <p>Last vaccination: <?php echo date("F j, Y", strtotime($vaccinations[0]["vaccination_date"])); ?></p>
                            <p>Vaccine: <?php echo htmlspecialchars($vaccinations[0]["vaccine_name"]); ?></p>
                        <?php else: ?>
                            <div class="status-badge incomplete">
                                <span>Not Vaccinated</span>
                            </div>
                            <p>No vaccination records found.</p>
                            <a href="vaccinations.php" class="btn btn-primary btn-sm">Add Vaccination Record</a>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="card">
                    <h3>Essential Supplies</h3>
                    <div class="card-content">
                        <div class="supply-status">
                            <canvas id="supplyChart"></canvas>
                        </div>
                        <p>Nearby merchants with essential supplies: <?php echo count($merchants); ?></p>
                        <a href="#" class="btn btn-primary btn-sm">Find Supplies</a>
                    </div>
                </div>
                
                <div class="card">
                    <h3>Latest Alerts</h3>
                    <div class="card-content alerts-list">
                        <div class="alert-item">
                            <span class="alert-date">May 3, 2025</span>
                            <span class="alert-type warning">Warning</span>
                            <p>New restrictions for essential items start next week.</p>
                        </div>
                        <div class="alert-item">
                            <span class="alert-date">May 1, 2025</span>
                            <span class="alert-type info">Info</span>
                            <p>Additional vaccination slots available at Central Hospital.</p>
                        </div>
                        <div class="alert-item">
                            <span class="alert-date">Apr 28, 2025</span>
                            <span class="alert-type success">Update</span>
                            <p>Medical supplies restocked at Essential Supplies Store.</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="dashboard-section">
                <h3>Nearby Essential Suppliers</h3>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Business Name</th>
                                <th>Location</th>
                                <th>Available Items</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($merchants as $merchant): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($merchant["business_name"]); ?></td>
                                <td><?php echo htmlspecialchars($merchant["city"]); ?></td>
                                <td><?php echo $merchant["item_count"]; ?> items</td>
                                <td>
                                    <a href="#" class="btn btn-primary btn-sm">View Items</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            
                            <?php if (count($merchants) == 0): ?>
                            <tr>
                                <td colspan="4" class="text-center">No nearby merchants found.</td>
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
        const supplyData = {
            labels: ['Masks', 'Sanitizers', 'Medications', 'Food'],
            datasets: [{
                label: 'Availability',
                data: [85, 65, 50, 90],
                backgroundColor: [
                    'rgba(75, 192, 192, 0.6)',
                    'rgba(54, 162, 235, 0.6)',
                    'rgba(255, 206, 86, 0.6)',
                    'rgba(75, 192, 192, 0.6)'
                ],
                borderColor: [
                    'rgba(75, 192, 192, 1)',
                    'rgba(54, 162, 235, 1)',
                    'rgba(255, 206, 86, 1)',
                    'rgba(75, 192, 192, 1)'
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
                            beginAtZero: true,
                            max: 100
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        },
                        title: {
                            display: true,
                            text: 'Supply Availability (%)'
                        }
                    }
                }
            }
        );
    </script>
    
    <script src="../../assets/js/main.js"></script>
    <script src="../../assets/js/public-dashboard.js"></script>
</body>
</html>