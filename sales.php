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

$from_date = isset($_GET["from_date"]) ? sanitize_input($_GET["from_date"]) : "";
$to_date = isset($_GET["to_date"]) ? sanitize_input($_GET["to_date"]) : "";
$min_amount = isset($_GET["min_amount"]) ? (float)$_GET["min_amount"] : 0;
$search = isset($_GET["search"]) ? sanitize_input($_GET["search"]) : "";
$page = isset($_GET["page"]) ? (int)$_GET["page"] : 1;
$items_per_page = 10;
$offset = ($page - 1) * $items_per_page;

$sql = "SELECT t.transaction_id, t.prs_id, t.transaction_date, t.total_amount, 
        COUNT(ti.id) as item_count 
        FROM transactions t 
        JOIN transaction_items ti ON t.transaction_id = ti.transaction_id 
        WHERE t.merchant_id = ?";
$count_sql = "SELECT COUNT(DISTINCT t.transaction_id) as total 
             FROM transactions t 
             JOIN transaction_items ti ON t.transaction_id = ti.transaction_id 
             WHERE t.merchant_id = ?";

$params = [$merchant_id];
$types = "i";

if (!empty($from_date)) {
    $sql .= " AND t.transaction_date >= ?";
    $count_sql .= " AND t.transaction_date >= ?";
    $params[] = $from_date;
    $types .= "s";
}

if (!empty($to_date)) {
    $sql .= " AND t.transaction_date <= ?";
    $count_sql .= " AND t.transaction_date <= ?";
    $params[] = $to_date . " 23:59:59";
    $types .= "s";
}

if ($min_amount > 0) {
    $sql .= " AND t.total_amount >= ?";
    $count_sql .= " AND t.total_amount >= ?";
    $params[] = $min_amount;
    $types .= "d";
}

if (!empty($search)) {
    $sql .= " AND (t.prs_id LIKE ? OR t.transaction_id LIKE ?)";
    $count_sql .= " AND (t.prs_id LIKE ? OR t.transaction_id LIKE ?)";
    $search_param = "%" . $search . "%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ss";
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

$stats = [
    'total_sales' => 0,
    'total_amount' => 0,
    'avg_sale' => 0
];

$stats_sql = "SELECT COUNT(*) as total_sales, SUM(total_amount) as total_amount 
             FROM transactions WHERE merchant_id = ?";
$stats_stmt = $conn->prepare($stats_sql);
$stats_stmt->bind_param("i", $merchant_id);
$stats_stmt->execute();
$stats_result = $stats_stmt->get_result();
$stats_row = $stats_result->fetch_assoc();

$stats['total_sales'] = $stats_row['total_sales'];
$stats['total_amount'] = $stats_row['total_amount'];
$stats['avg_sale'] = ($stats['total_sales'] > 0) ? ($stats['total_amount'] / $stats['total_sales']) : 0;
$stats_stmt->close();

log_activity($_SESSION["prs_id"], "view", "sales", "page", "success");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales History - Merchant Dashboard</title>
    <link rel="stylesheet" href="../../assets/css/main.css">
    <link rel="stylesheet" href="../../assets/css/merchant-dashboard.css">
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
                    <li><a href="sales.php" class="active">Sales History</a></li>
                    <li><a href="reports.php">Reports</a></li>
                    <li><a href="#">Business Profile</a></li>
                </ul>
            </nav>
        </aside>
        
        <div class="dashboard-content">
            <h2>Sales History</h2>
            
            <div class="stats-container">
                <div class="stat-card">
                    <div class="stat-icon merchants">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Total Sales</h3>
                        <p class="stat-value"><?php echo number_format($stats['total_sales']); ?></p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon value">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Total Revenue</h3>
                        <p class="stat-value">$<?php echo number_format($stats['total_amount'], 2); ?></p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon inventory">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Average Sale</h3>
                        <p class="stat-value">$<?php echo number_format($stats['avg_sale'], 2); ?></p>
                    </div>
                </div>
            </div>
            
            <div class="filters-container">
                <form method="get" action="sales.php" class="search-form">
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
                            <label for="min_amount">Min Amount ($)</label>
                            <input type="number" id="min_amount" name="min_amount" class="form-control" min="0" step="0.01" value="<?php echo $min_amount; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="search">Search</label>
                            <input type="text" id="search" name="search" class="form-control" placeholder="Search by ID..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">Apply Filters</button>
                        <a href="sales.php" class="btn btn-secondary">Reset</a>
                    </div>
                </form>
            </div>
            
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Transaction ID</th>
                            <th>Customer ID</th>
                            <th>Date & Time</th>
                            <th>Items</th>
                            <th>Total Amount</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $transaction): ?>
                        <tr>
                            <td><?php echo $transaction["transaction_id"]; ?></td>
                            <td><?php echo htmlspecialchars($transaction["prs_id"]); ?></td>
                            <td><?php echo date("M j, Y g:i A", strtotime($transaction["transaction_date"])); ?></td>
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
                            <td colspan="6" class="text-center">No transactions found.</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>&from_date=<?php echo urlencode($from_date); ?>&to_date=<?php echo urlencode($to_date); ?>&min_amount=<?php echo $min_amount; ?>&search=<?php echo urlencode($search); ?>" class="btn btn-secondary">&laquo; Previous</a>
                <?php endif; ?>
                
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?page=<?php echo $i; ?>&from_date=<?php echo urlencode($from_date); ?>&to_date=<?php echo urlencode($to_date); ?>&min_amount=<?php echo $min_amount; ?>&search=<?php echo urlencode($search); ?>" class="btn <?php echo ($i == $page) ? 'btn-primary' : 'btn-secondary'; ?>"><?php echo $i; ?></a>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&from_date=<?php echo urlencode($from_date); ?>&to_date=<?php echo urlencode($to_date); ?>&min_amount=<?php echo $min_amount; ?>&search=<?php echo urlencode($search); ?>" class="btn btn-secondary">Next &raquo;</a>
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
                                <p><strong>Customer ID:</strong> <span id="modal-customer-id"></span></p>
                                <p><strong>Date & Time:</strong> <span id="modal-transaction-date"></span></p>
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
                        if (data.status === 'success') {
                            document.getElementById('modal-customer-id').textContent = data.transaction.prs_id;
                            document.getElementById('modal-transaction-date').textContent = data.transaction.formatted_date;
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
                                    <td>${parseFloat(item.price_per_unit).toFixed(2)}</td>
                                    <td>${(item.quantity * item.price_per_unit).toFixed(2)}</td>
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