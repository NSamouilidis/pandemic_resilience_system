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

$dob = "";
$sql = "SELECT dob FROM users WHERE prs_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $_SESSION["prs_id"]);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $row = $result->fetch_assoc();
    $dob = $row["dob"];
}
$stmt->close();

$year = date("Y", strtotime($dob));
$lastDigit = substr($year, -1);

$faceDigitMap = [
    "0" => "Monday",
    "1" => "Tuesday",
    "2" => ["Monday", "Wednesday"],
    "3" => ["Tuesday", "Thursday"],
    "4" => ["Wednesday", "Friday"],
    "5" => ["Thursday", "Saturday"],
    "6" => ["Friday", "Sunday"],
    "7" => "Saturday",
    "8" => "Sunday",
    "9" => "Monday" 
];

$purchaseDays = isset($faceDigitMap[$lastDigit]) ? $faceDigitMap[$lastDigit] : ["Any day"];
if (!is_array($purchaseDays)) {
    $purchaseDays = [$purchaseDays];
}

$today = date("l"); 
$canPurchaseToday = in_array($today, $purchaseDays);

$currentHour = (int)date("G"); 
$isStoreOpen = ($currentHour >= 9 && $currentHour < 17);

$purchaseStatus = "";
if ($canPurchaseToday && $isStoreOpen) {
    $purchaseStatus = "<span class='status-indicator ok'>You can purchase face masks today</span>";
} elseif ($canPurchaseToday && !$isStoreOpen) {
    $purchaseStatus = "<span class='status-indicator warning'>You can purchase face masks today, but stores are currently closed</span>";
} else {
    $purchaseStatus = "<span class='status-indicator low'>You cannot purchase face masks today. Your purchase day(s): " . implode(", ", $purchaseDays) . "</span>";
}

$from_date = isset($_GET["from_date"]) ? sanitize_input($_GET["from_date"]) : "";
$to_date = isset($_GET["to_date"]) ? sanitize_input($_GET["to_date"]) : "";
$merchant = isset($_GET["merchant"]) ? (int)$_GET["merchant"] : 0;
$category = isset($_GET["category"]) ? sanitize_input($_GET["category"]) : "";
$page = isset($_GET["page"]) ? (int)$_GET["page"] : 1;
$items_per_page = 10;
$offset = ($page - 1) * $items_per_page;

$sql = "SELECT t.transaction_id, t.transaction_date, t.total_amount, 
        m.merchant_id, m.business_name, 
        COUNT(ti.id) as item_count 
        FROM transactions t 
        JOIN merchants m ON t.merchant_id = m.merchant_id 
        JOIN transaction_items ti ON t.transaction_id = ti.transaction_id 
        LEFT JOIN items i ON ti.item_id = i.item_id 
        WHERE t.prs_id = ?";
$count_sql = "SELECT COUNT(DISTINCT t.transaction_id) as total 
              FROM transactions t 
              JOIN transaction_items ti ON t.transaction_id = ti.transaction_id 
              LEFT JOIN items i ON ti.item_id = i.item_id 
              WHERE t.prs_id = ?";

$params = [$_SESSION["prs_id"]];
$types = "s";

if (!empty($from_date)) {
    $sql .= " AND t.transaction_date >= ?";
    $count_sql .= " AND t.transaction_date >= ?";
    $params[] = $from_date;
    $types .= "s";
}

if (!empty($to_date)) {
    $sql .= " AND t.transaction_date <= ?";
    $count_sql .= " AND t.transaction_date <= ?";
    $params[] = $to_date . " 23:59:59"; // Include entire day
    $types .= "s";
}

if ($merchant > 0) {
    $sql .= " AND t.merchant_id = ?";
    $count_sql .= " AND t.merchant_id = ?";
    $params[] = $merchant;
    $types .= "i";
}

if (!empty($category)) {
    $sql .= " AND i.category = ?";
    $count_sql .= " AND i.category = ?";
    $params[] = $category;
    $types .= "s";
}

$sql .= " GROUP BY t.transaction_id ORDER BY t.transaction_date DESC LIMIT ? OFFSET ?";
$params[] = $items_per_page;
$params[] = $offset;
$types .= "ii";

$total_transactions = 0;
$count_stmt = $conn->prepare($count_sql);
$count_stmt->bind_param(substr($types, 0, -2), ...array_slice($params, 0, -2));
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$count_row = $count_result->fetch_assoc();
$total_transactions = $count_row["total"];
$count_stmt->close();

$total_pages = ceil($total_transactions / $items_per_page);

$transactions = [];
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $transactions[] = $row;
}
$stmt->close();

$merchants = [];
$merchant_sql = "SELECT DISTINCT m.merchant_id, m.business_name 
                FROM transactions t 
                JOIN merchants m ON t.merchant_id = m.merchant_id 
                WHERE t.prs_id = ? 
                ORDER BY m.business_name";
$merchant_stmt = $conn->prepare($merchant_sql);
$merchant_stmt->bind_param("s", $_SESSION["prs_id"]);
$merchant_stmt->execute();
$merchant_result = $merchant_stmt->get_result();

while ($row = $merchant_result->fetch_assoc()) {
    $merchants[] = $row;
}
$merchant_stmt->close();

$categories = [];
$category_sql = "SELECT DISTINCT i.category 
                FROM transactions t 
                JOIN transaction_items ti ON t.transaction_id = ti.transaction_id 
                JOIN items i ON ti.item_id = i.item_id 
                WHERE t.prs_id = ? 
                ORDER BY i.category";
$category_stmt = $conn->prepare($category_sql);
$category_stmt->bind_param("s", $_SESSION["prs_id"]);
$category_stmt->execute();
$category_result = $category_stmt->get_result();

while ($row = $category_result->fetch_assoc()) {
    $categories[] = $row["category"];
}
$category_stmt->close();

$stats = [
    'total_transactions' => 0,
    'total_amount' => 0,
    'total_items' => 0
];

$stats_sql = "SELECT COUNT(DISTINCT t.transaction_id) as transaction_count, 
              SUM(t.total_amount) as total_amount, 
              COUNT(ti.id) as item_count 
              FROM transactions t 
              JOIN transaction_items ti ON t.transaction_id = ti.transaction_id 
              WHERE t.prs_id = ?";
$stats_stmt = $conn->prepare($stats_sql);
$stats_stmt->bind_param("s", $_SESSION["prs_id"]);
$stats_stmt->execute();
$stats_result = $stats_stmt->get_result();
$stats_row = $stats_result->fetch_assoc();

$stats['total_transactions'] = $stats_row['transaction_count'];
$stats['total_amount'] = $stats_row['total_amount'];
$stats['total_items'] = $stats_row['item_count'];
$stats_stmt->close();

log_activity($_SESSION["prs_id"], "view", "purchase_history", "page", "success");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase History - Pandemic Resilience System</title>
    <link rel="stylesheet" href="../../assets/css/main.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/public-dashboard.css">
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
                    <li><a href="resource_finder.php">Resource Finder</a></li>
                    <li><a href="purchase_history.php" class="active">Purchase History</a></li>
                </ul>
            </nav>
        </aside>
        
        <div class="dashboard-content">
            <h2>Purchase History</h2>
            
            <div class="purchase-eligibility">
                <h3>Purchase Eligibility</h3>
                <div class="eligibility-card">
                    <div class="eligibility-item">
                        <strong>Face Masks:</strong> <?php echo $purchaseStatus; ?>
                    </div>
                    <div class="eligibility-info">
                        <p>Based on your year of birth (<?php echo $year; ?>), you can purchase face masks on: <?php echo implode(", ", $purchaseDays); ?></p>
                        <p>Store hours: 9:00 AM - 5:00 PM</p>
                    </div>
                </div>
            </div>
            
            <div class="purchase-stats">
                <div class="stat-card">
                    <div class="stat-icon merchants">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Total Transactions</h3>
                        <p class="stat-value"><?php echo number_format($stats['total_transactions']); ?></p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon value">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Total Spent</h3>
                        <p class="stat-value">$<?php echo number_format($stats['total_amount'], 2); ?></p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon inventory">
                        <i class="fas fa-box"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Items Purchased</h3>
                        <p class="stat-value"><?php echo number_format($stats['total_items']); ?></p>
                    </div>
                </div>
            </div>
            
            <div class="filters-container">
                <form method="get" action="purchase_history.php" class="search-form">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="from_date">From Date</label>
                            <input type="date" id="from_date" name="from_date" class="form-control" value="<?php echo $from_date; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="to_date">To Date</label>
                            <input type="date" id="to_date" name="to_date" class="form-control" value="<?php echo $to_date; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="merchant">Merchant</label>
                            <select id="merchant" name="merchant" class="form-control">
                                <option value="0">All Merchants</option>
                                <?php foreach ($merchants as $m): ?>
                                    <option value="<?php echo $m['merchant_id']; ?>" <?php echo ($merchant == $m['merchant_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($m['business_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="category">Category</label>
                            <select id="category" name="category" class="form-control">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat; ?>" <?php echo ($category == $cat) ? 'selected' : ''; ?>>
                                        <?php echo ucfirst($cat); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">Apply Filters</button>
                        <a href="purchase_history.php" class="btn btn-secondary">Reset</a>
                    </div>
                </form>
            </div>
            
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date & Time</th>
                            <th>Transaction ID</th>
                            <th>Merchant</th>
                            <th>Items</th>
                            <th>Total Amount</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $transaction): ?>
                        <tr>
                            <td><?php echo date("M j, Y g:i A", strtotime($transaction["transaction_date"])); ?></td>
                            <td><?php echo $transaction["transaction_id"]; ?></td>
                            <td><?php echo htmlspecialchars($transaction["business_name"]); ?></td>
                            <td><?php echo $transaction["item_count"]; ?> items</td>
                            <td>$<?php echo number_format($transaction["total_amount"], 2); ?></td>
                            <td>
                                <button type="button" class="btn btn-primary btn-sm view-details-btn" 
                                    data-transaction-id="<?php echo $transaction["transaction_id"]; ?>">
                                    View Details
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <?php if (count($transactions) == 0): ?>
                        <tr>
                            <td colspan="6" class="text-center">No purchase history found.</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>&from_date=<?php echo urlencode($from_date); ?>&to_date=<?php echo urlencode($to_date); ?>&merchant=<?php echo $merchant; ?>&category=<?php echo urlencode($category); ?>" class="btn btn-secondary">&laquo; Previous</a>
                <?php endif; ?>
                
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?page=<?php echo $i; ?>&from_date=<?php echo urlencode($from_date); ?>&to_date=<?php echo urlencode($to_date); ?>&merchant=<?php echo $merchant; ?>&category=<?php echo urlencode($category); ?>" class="btn <?php echo ($i == $page) ? 'btn-primary' : 'btn-secondary'; ?>"><?php echo $i; ?></a>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&from_date=<?php echo urlencode($from_date); ?>&to_date=<?php echo urlencode($to_date); ?>&merchant=<?php echo $merchant; ?>&category=<?php echo urlencode($category); ?>" class="btn btn-secondary">Next &raquo;</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <div id="transaction-modal" class="modal" style="display: none;">
                <div class="modal-content modal-lg">
                    <span class="close">&times;</span>
                    <h3>Transaction Details</h3>
                    <div id="transaction-details-container">
                        <div class="transaction-header">
                            <div class="transaction-info">
                                <p><strong>Transaction ID:</strong> <span id="modal-transaction-id"></span></p>
                                <p><strong>Date & Time:</strong> <span id="modal-transaction-date"></span></p>
                                <p><strong>Merchant:</strong> <span id="modal-merchant-name"></span></p>
                            </div>
                        </div>
                        
                        <h4>Items Purchased</h4>
                        <div class="table-responsive">
                            <table class="data-table" id="transaction-items-table">
                                <thead>
                                    <tr>
                                        <th>Item</th>
                                        <th>Category</th>
                                        <th>Quantity</th>
                                        <th>Price</th>
                                        <th>Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody id="transaction-items-body">
                                    
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <th colspan="4" class="text-right">Total:</th>
                                        <th id="modal-total-amount"></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <footer>
        <p>&copy; 2025 Pandemic Resilience System | Data-driven Systems (5CM506)</p>
    </footer>
    
    <style>
        .purchase-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            display: flex;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            color: white;
            font-size: 1.5rem;
        }
        
        .stat-icon.merchants {
            background-color: #2a5caa;
        }
        
        .stat-icon.value {
            background-color: #28a745;
        }
        
        .stat-icon.inventory {
            background-color: #5bc0de;
        }
        
        .stat-info h3 {
            font-size: 0.9rem;
            color: #777;
            margin-bottom: 0.5rem;
        }
        
        .stat-value {
            font-size: 1.5rem;
            font-weight: 600;
            color: #333;
            margin: 0;
        }
        
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
            max-height: 80vh;
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
        
        .transaction-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #eee;
        }
        
        .transaction-info p {
            margin: 0.5rem 0;
        }
        
        .text-right {
            text-align: right;
        }
        
        .purchase-eligibility {
            margin-bottom: 2rem;
        }

        .eligibility-card {
            background-color: white;
            border-radius: 8px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .eligibility-item {
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .eligibility-info {
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #eee;
        }
    </style>
    
    <script src="../../assets/js/main.js"></script>
    <script>
        const modal = document.getElementById('transaction-modal');
        const viewDetailsButtons = document.querySelectorAll('.view-details-btn');
        const closeButtons = document.querySelectorAll('.close');
        
        viewDetailsButtons.forEach(button => {
            button.addEventListener('click', function() {
                const transactionId = this.getAttribute('data-transaction-id');
                
                document.getElementById('transaction-items-body').innerHTML = '<tr><td colspan="5" class="text-center">Loading...</td></tr>';
                document.getElementById('modal-transaction-id').textContent = transactionId;
                
                const row = this.closest('tr');
                const transactionDate = row.cells[0].textContent;
                const merchantName = row.cells[2].textContent;
                
                document.getElementById('modal-transaction-date').textContent = transactionDate;
                document.getElementById('modal-merchant-name').textContent = merchantName;
                
                modal.style.display = 'flex';
                
                fetchTransactionDetails(transactionId);
            });
        });
        
        closeButtons.forEach(button => {
            button.addEventListener('click', function() {
                modal.style.display = 'none';
            });
        });
        
        window.addEventListener('click', function(event) {
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        });
        
        function fetchTransactionDetails(transactionId) {
            
            setTimeout(() => {
                fetch(`get_transaction_details.php?transaction_id=${transactionId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'success') {s
                            document.getElementById('modal-total-amount').textContent = '$' + data.transaction.total_amount;
                            
                            const itemsBody = document.getElementById('transaction-items-body');
                            itemsBody.innerHTML = '';
                            
                            data.items.forEach(item => {
                                const row = document.createElement('tr');
                                row.innerHTML = `
                                    <td>${item.item_name}</td>
                                    <td>
                                        <span class="category-badge ${item.category}">
                                            ${item.category.charAt(0).toUpperCase() + item.category.slice(1)}
                                        </span>
                                    </td>
                                    <td>${item.quantity}</td>
                                    <td>$${parseFloat(item.price_per_unit).toFixed(2)}</td>
                                    <td>$${(item.quantity * item.price_per_unit).toFixed(2)}</td>
                                `;
                                itemsBody.appendChild(row);
                            });
                        } else {
                            document.getElementById('transaction-items-body').innerHTML = 
                                `<tr><td colspan="5" class="text-center">Error: ${data.message}</td></tr>`;
                        }
                    })
                    .catch(error => {
                        document.getElementById('transaction-items-body').innerHTML = 
                            '<tr><td colspan="5" class="text-center">Error loading transaction details. Please try again.</td></tr>';
                        console.error('Error:', error);
                    });
            }, 500); 
        }
    </script>
</body>
</html>