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

$action_message = "";
$action_status = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST["action"])) {
        $action = $_POST["action"];
        
        switch ($action) {
            case "add":
                $item_id = isset($_POST["item_id"]) ? (int)$_POST["item_id"] : 0;
                $quantity = isset($_POST["quantity"]) ? (int)$_POST["quantity"] : 0;
                $price = isset($_POST["price"]) ? (float)$_POST["price"] : 0;
                
                if ($item_id <= 0 || $quantity < 0 || $price <= 0) {
                    $action_message = "Invalid item data. Please check your input.";
                    $action_status = "danger";
                } else {
                    $check_sql = "SELECT inventory_id FROM inventory WHERE merchant_id = ? AND item_id = ?";
                    $stmt = $conn->prepare($check_sql);
                    $stmt->bind_param("ii", $merchant_id, $item_id);
                    $stmt->execute();
                    $check_result = $stmt->get_result();
                    
                    if ($check_result->num_rows > 0) {
                        $row = $check_result->fetch_assoc();
                        $inventory_id = $row["inventory_id"];
                        
                        $update_sql = "UPDATE inventory SET quantity = ?, price = ? WHERE inventory_id = ?";
                        $stmt = $conn->prepare($update_sql);
                        $stmt->bind_param("idi", $quantity, $price, $inventory_id);
                        
                        if ($stmt->execute()) {
                            $action_message = "Item updated successfully.";
                            $action_status = "success";
                            
                            log_activity($_SESSION["prs_id"], "update", "inventory", $inventory_id, "success");
                        } else {
                            $action_message = "Error updating item: " . $stmt->error;
                            $action_status = "danger";
                        }
                    } else {
                        $insert_sql = "INSERT INTO inventory (merchant_id, item_id, quantity, price) VALUES (?, ?, ?, ?)";
                        $stmt = $conn->prepare($insert_sql);
                        $stmt->bind_param("iiid", $merchant_id, $item_id, $quantity, $price);
                        
                        if ($stmt->execute()) {
                            $inventory_id = $stmt->insert_id;
                            $action_message = "Item added to inventory successfully.";
                            $action_status = "success";
                            
                            log_activity($_SESSION["prs_id"], "create", "inventory", $inventory_id, "success");
                        } else {
                            $action_message = "Error adding item: " . $stmt->error;
                            $action_status = "danger";
                        }
                    }
                    $stmt->close();
                }
                break;
                
            case "update":
                $inventory_id = isset($_POST["inventory_id"]) ? (int)$_POST["inventory_id"] : 0;
                $quantity = isset($_POST["quantity"]) ? (int)$_POST["quantity"] : 0;
                $price = isset($_POST["price"]) ? (float)$_POST["price"] : 0;
                
                if ($inventory_id <= 0 || $quantity < 0 || $price <= 0) {
                    $action_message = "Invalid inventory data. Please check your input.";
                    $action_status = "danger";
                } else {
                    $check_sql = "SELECT COUNT(*) as count FROM inventory WHERE inventory_id = ? AND merchant_id = ?";
                    $stmt = $conn->prepare($check_sql);
                    $stmt->bind_param("ii", $inventory_id, $merchant_id);
                    $stmt->execute();
                    $check_result = $stmt->get_result();
                    $row = $check_result->fetch_assoc();
                    
                    if ($row["count"] > 0) {
                        $update_sql = "UPDATE inventory SET quantity = ?, price = ? WHERE inventory_id = ?";
                        $stmt = $conn->prepare($update_sql);
                        $stmt->bind_param("idi", $quantity, $price, $inventory_id);
                        
                        if ($stmt->execute()) {
                            $action_message = "Inventory updated successfully.";
                            $action_status = "success";
                            
                            log_activity($_SESSION["prs_id"], "update", "inventory", $inventory_id, "success");
                        } else {
                            $action_message = "Error updating inventory: " . $stmt->error;
                            $action_status = "danger";
                        }
                    } else {
                        $action_message = "You don't have permission to update this item.";
                        $action_status = "danger";
                    }
                    $stmt->close();
                }
                break;
                
            case "delete":
                $inventory_id = isset($_POST["inventory_id"]) ? (int)$_POST["inventory_id"] : 0;
                
                if ($inventory_id <= 0) {
                    $action_message = "Invalid inventory ID.";
                    $action_status = "danger";
                } else {
                    $check_sql = "SELECT COUNT(*) as count FROM inventory WHERE inventory_id = ? AND merchant_id = ?";
                    $stmt = $conn->prepare($check_sql);
                    $stmt->bind_param("ii", $inventory_id, $merchant_id);
                    $stmt->execute();
                    $check_result = $stmt->get_result();
                    $row = $check_result->fetch_assoc();
                    
                    if ($row["count"] > 0) {
                        $delete_sql = "DELETE FROM inventory WHERE inventory_id = ?";
                        $stmt = $conn->prepare($delete_sql);
                        $stmt->bind_param("i", $inventory_id);
                        
                        if ($stmt->execute()) {
                            $action_message = "Item removed from inventory successfully.";
                            $action_status = "success";
                            
                            log_activity($_SESSION["prs_id"], "delete", "inventory", $inventory_id, "success");
                        } else {
                            $action_message = "Error removing item: " . $stmt->error;
                            $action_status = "danger";
                        }
                    } else {
                        $action_message = "You don't have permission to delete this item.";
                        $action_status = "danger";
                    }
                    $stmt->close();
                }
                break;
        }
    }
}

$page = isset($_GET["page"]) ? (int)$_GET["page"] : 1;
$items_per_page = 10;
$offset = ($page - 1) * $items_per_page;

$category_filter = isset($_GET["category"]) ? sanitize_input($_GET["category"]) : "";
$low_stock = isset($_GET["low_stock"]) ? (bool)$_GET["low_stock"] : false;
$search = isset($_GET["search"]) ? sanitize_input($_GET["search"]) : "";

$sql = "SELECT i.inventory_id, i.item_id, it.name as item_name, it.category, 
        it.description, i.quantity, i.price, it.rationing_limit 
        FROM inventory i 
        JOIN items it ON i.item_id = it.item_id 
        WHERE i.merchant_id = ?";
$count_sql = "SELECT COUNT(*) as total FROM inventory i JOIN items it ON i.item_id = it.item_id WHERE i.merchant_id = ?";

$params = [$merchant_id];
$types = "i";

if (!empty($category_filter)) {
    $sql .= " AND it.category = ?";
    $count_sql .= " AND it.category = ?";
    $params[] = $category_filter;
    $types .= "s";
}

if ($low_stock) {
    $sql .= " AND i.quantity < 10";
    $count_sql .= " AND i.quantity < 10";
}

if (!empty($search)) {
    $sql .= " AND (it.name LIKE ? OR it.description LIKE ?)";
    $count_sql .= " AND (it.name LIKE ? OR it.description LIKE ?)";
    $search_param = "%" . $search . "%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ss";
}

$sql .= " ORDER BY it.category, it.name LIMIT ? OFFSET ?";
$params[] = $items_per_page;
$params[] = $offset;
$types .= "ii";

$total_items = 0;
$count_stmt = $conn->prepare($count_sql);
$count_stmt->bind_param(substr($types, 0, -2), ...array_slice($params, 0, -2));
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$count_row = $count_result->fetch_assoc();
$total_items = $count_row["total"];
$count_stmt->close();

$total_pages = ceil($total_items / $items_per_page);

$inventory = [];
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $inventory[] = $row;
}
$stmt->close();

$available_items = [];
$items_sql = "SELECT i.item_id, i.name, i.category, i.description 
             FROM items i 
             LEFT JOIN inventory inv ON i.item_id = inv.item_id AND inv.merchant_id = ? 
             WHERE inv.inventory_id IS NULL 
             ORDER BY i.category, i.name";
$items_stmt = $conn->prepare($items_sql);
$items_stmt->bind_param("i", $merchant_id);
$items_stmt->execute();
$items_result = $items_stmt->get_result();

while ($row = $items_result->fetch_assoc()) {
    $available_items[] = $row;
}
$items_stmt->close();

$categories = [];
$category_sql = "SELECT DISTINCT category FROM items ORDER BY category";
$category_result = $conn->query($category_sql);

while ($row = $category_result->fetch_assoc()) {
    $categories[] = $row["category"];
}

log_activity($_SESSION["prs_id"], "view", "inventory", "page", "success");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Inventory - Merchant Dashboard</title>
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
                    <li><a href="inventory.php" class="active">Manage Inventory</a></li>
                    <li><a href="sales.php">Sales History</a></li>
                    <li><a href="reports.php">Reports</a></li>
                    <li><a href="#">Business Profile</a></li>
                </ul>
            </nav>
        </aside>
        
        <div class="dashboard-content">
            <h2>Manage Inventory</h2>
            
            <?php if (!empty($action_message)): ?>
                <div class="alert alert-<?php echo $action_status; ?>">
                    <?php echo $action_message; ?>
                </div>
            <?php endif; ?>
            
            <div class="action-container">
                <button id="add-item-btn" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Add New Item
                </button>
            </div>
            
            <div id="add-item-form" class="form-container" style="display: none;">
                <h3>Add New Item to Inventory</h3>
                <form method="post" action="inventory.php">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="item_id">Item</label>
                            <select id="item_id" name="item_id" class="form-control" required>
                                <option value="">Select an item...</option>
                                <?php foreach ($available_items as $item): ?>
                                    <option value="<?php echo $item["item_id"]; ?>">
                                        <?php echo htmlspecialchars($item["name"] . " (" . ucfirst($item["category"]) . ")"); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="quantity">Quantity</label>
                            <input type="number" id="quantity" name="quantity" class="form-control" min="1" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="price">Price ($)</label>
                            <input type="number" id="price" name="price" class="form-control" min="0.01" step="0.01" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">Add to Inventory</button>
                            <button type="button" id="cancel-add" class="btn btn-secondary">Cancel</button>
                        </div>
                    </div>
                </form>
            </div>
            
            <div class="filters-container">
                <form method="get" action="inventory.php" class="search-form">
                    <div class="form-row">
                        <div class="form-group">
                            <input type="text" name="search" placeholder="Search items..." class="form-control" value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        
                        <div class="form-group">
                            <select name="category" class="form-control">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat; ?>" <?php echo ($category_filter == $cat) ? 'selected' : ''; ?>>
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
                            <button type="submit" class="btn btn-primary">Filter</button>
                            <a href="inventory.php" class="btn btn-secondary">Reset</a>
                        </div>
                    </div>
                </form>
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
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($inventory as $item): ?>
                        <tr <?php echo ($item["quantity"] < 10) ? 'class="low-stock"' : ''; ?>>
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
                            <td>
                                <div class="action-buttons">
                                    <button type="button" class="btn btn-primary btn-sm edit-btn" 
                                        data-id="<?php echo $item["inventory_id"]; ?>" 
                                        data-name="<?php echo htmlspecialchars($item["item_name"]); ?>" 
                                        data-quantity="<?php echo $item["quantity"]; ?>" 
                                        data-price="<?php echo $item["price"]; ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    
                                    <form method="post" action="inventory.php" class="delete-form" onsubmit="return confirm('Are you sure you want to remove this item from inventory?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="inventory_id" value="<?php echo $item["inventory_id"]; ?>">
                                        <button type="submit" class="btn btn-danger btn-sm">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
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
            
            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>&category=<?php echo urlencode($category_filter); ?>&low_stock=<?php echo $low_stock ? '1' : '0'; ?>&search=<?php echo urlencode($search); ?>" class="btn btn-secondary">&laquo; Previous</a>
                <?php endif; ?>
                
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?page=<?php echo $i; ?>&category=<?php echo urlencode($category_filter); ?>&low_stock=<?php echo $low_stock ? '1' : '0'; ?>&search=<?php echo urlencode($search); ?>" class="btn <?php echo ($i == $page) ? 'btn-primary' : 'btn-secondary'; ?>"><?php echo $i; ?></a>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&category=<?php echo urlencode($category_filter); ?>&low_stock=<?php echo $low_stock ? '1' : '0'; ?>&search=<?php echo urlencode($search); ?>" class="btn btn-secondary">Next &raquo;</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <div id="edit-modal" class="modal" style="display: none;">
                <div class="modal-content">
                    <span class="close">&times;</span>
                    <h3>Edit Inventory Item</h3>
                    <form method="post" action="inventory.php" id="edit-form">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="inventory_id" id="edit-id">
                        
                        <div class="form-group">
                            <label>Item:</label>
                            <p id="edit-name"></p>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="edit-quantity">Quantity</label>
                                <input type="number" id="edit-quantity" name="quantity" class="form-control" min="0" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="edit-price">Price ($)</label>
                                <input type="number" id="edit-price" name="price" class="form-control" min="0.01" step="0.01" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">Update Inventory</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>
    
    <footer>
        <p>&copy; 2025 Pandemic Resilience System | Data-driven Systems (5CM506)</p>
    </footer>
    
    <script>
        const addItemBtn = document.getElementById('add-item-btn');
        const addItemForm = document.getElementById('add-item-form');
        const cancelAddBtn = document.getElementById('cancel-add');
        
        addItemBtn.addEventListener('click', function() {
            addItemForm.style.display = 'block';
            addItemBtn.style.display = 'none';
        });
        
        cancelAddBtn.addEventListener('click', function() {
            addItemForm.style.display = 'none';
            addItemBtn.style.display = 'block';
        });
        
        const editModal = document.getElementById('edit-modal');
        const editBtns = document.querySelectorAll('.edit-btn');
        const closeBtn = document.querySelector('.close');
        
        editBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const name = this.getAttribute('data-name');
                const quantity = this.getAttribute('data-quantity');
                const price = this.getAttribute('data-price');
                
                document.getElementById('edit-id').value = id;
                document.getElementById('edit-name').textContent = name;
                document.getElementById('edit-quantity').value = quantity;
                document.getElementById('edit-price').value = price;
                
                editModal.style.display = 'block';
            });
        });
        
        closeBtn.addEventListener('click', function() {
            editModal.style.display = 'none';
        });
        
        window.addEventListener('click', function(event) {
            if (event.target == editModal) {
                editModal.style.display = 'none';
            }
        });
    </script>
</body>
</html>