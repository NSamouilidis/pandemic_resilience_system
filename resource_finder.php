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

$category = isset($_GET["category"]) ? sanitize_input($_GET["category"]) : "";
$city = isset($_GET["city"]) ? sanitize_input($_GET["city"]) : "";
$search = isset($_GET["search"]) ? sanitize_input($_GET["search"]) : "";

// Get merchant data
$merchant_sql = "SELECT m.merchant_id, m.business_name, u.city, u.postal_code, 
                 COUNT(DISTINCT i.item_id) as item_count,
                 u.address, m.business_type
                 FROM merchants m 
                 JOIN users u ON m.prs_id = u.prs_id 
                 JOIN inventory i ON m.merchant_id = i.merchant_id 
                 JOIN items it ON i.item_id = it.item_id 
                 WHERE m.status = 'active' AND i.quantity > 0";

$params = [];
$types = "";

if (!empty($category)) {
    $merchant_sql .= " AND it.category = ?";
    $params[] = $category;
    $types .= "s";
}

if (!empty($city)) {
    $merchant_sql .= " AND u.city = ?";
    $params[] = $city;
    $types .= "s";
}

if (!empty($search)) {
    $merchant_sql .= " AND (m.business_name LIKE ? OR it.name LIKE ?)";
    $search_param = "%" . $search . "%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ss";
}

$merchant_sql .= " GROUP BY m.merchant_id ORDER BY u.city, m.business_name";

$merchants = [];
$stmt = $conn->prepare($merchant_sql);

if (!empty($types)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    // Get merchant coordinates from a new merchant_locations table
    // If this table doesn't exist in your schema, you would need to create it
    $location_sql = "SELECT latitude, longitude FROM merchant_locations WHERE merchant_id = ?";
    $loc_stmt = $conn->prepare($location_sql);
    $loc_stmt->bind_param("i", $row["merchant_id"]);
    $loc_stmt->execute();
    $loc_result = $loc_stmt->get_result();
    
    $latitude = 40.6401; // Default coordinates for Thessaloniki
    $longitude = 22.9444;
    
    if ($loc_result->num_rows > 0) {
        $loc_row = $loc_result->fetch_assoc();
        $latitude = $loc_row["latitude"];
        $longitude = $loc_row["longitude"];
    } else {
        // If no location data exists, use geocoding based on address
        // In a real scenario, you would use a geocoding API
        // For now, we'll generate coordinates within Thessaloniki as an example
        $latitude += (mt_rand(-100, 100) / 1000);
        $longitude += (mt_rand(-100, 100) / 1000);
        
        // Save these coordinates for future use
        $save_loc_sql = "INSERT INTO merchant_locations (merchant_id, latitude, longitude) VALUES (?, ?, ?)";
        $save_stmt = $conn->prepare($save_loc_sql);
        $save_stmt->bind_param("idd", $row["merchant_id"], $latitude, $longitude);
        $save_stmt->execute();
        $save_stmt->close();
    }
    
    $loc_stmt->close();
    
    $merchants[] = array_merge($row, [
        "latitude" => $latitude,
        "longitude" => $longitude
    ]);
}

$stmt->close();

$categories = [];
$category_sql = "SELECT DISTINCT category FROM items ORDER BY category";
$category_result = $conn->query($category_sql);

while ($row = $category_result->fetch_assoc()) {
    $categories[] = $row["category"];
}

$cities = [];
$city_sql = "SELECT DISTINCT city FROM users WHERE city IS NOT NULL AND city != '' ORDER BY city";
$city_result = $conn->query($city_sql);

while ($row = $city_result->fetch_assoc()) {
    $cities[] = $row["city"];
}

log_activity($_SESSION["prs_id"], "view", "resource_finder", "page", "success");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resource Finder - Pandemic Resilience System</title>
    <link rel="stylesheet" href="../../assets/css/main.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/public-dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
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
            <h2>Resource Finder</h2>
            
            <div class="resource-finder-intro">
                <p>Find essential supplies, medical equipment, and other resources available from registered merchants during pandemic situations.</p>
            </div>
            
            <div class="filters-container">
                <form method="get" action="resource_finder.php" class="search-form">
                    <div class="form-row">
                        <div class="form-group">
                            <input type="text" name="search" placeholder="Search for items or businesses..." class="form-control" value="<?php echo htmlspecialchars($search); ?>">
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
                            <select name="city" class="form-control">
                                <option value="">All Locations</option>
                                <?php foreach ($cities as $c): ?>
                                    <option value="<?php echo $c; ?>" <?php echo ($city == $c) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($c); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">Search</button>
                            <a href="resource_finder.php" class="btn btn-secondary">Reset</a>
                        </div>
                    </div>
                </form>
            </div>
            
            <div class="map-container">
                <div id="merchant-map" style="height: 400px; width: 100%; border-radius: 8px;"></div>
            </div>
            
            <div class="merchant-results">
                <h3>Available Merchants</h3>
                
                <?php if (count($merchants) > 0): ?>
                    <div class="merchant-cards">
                        <?php foreach ($merchants as $merchant): ?>
                            <div class="merchant-card">
                                <div class="merchant-header">
                                    <h4><?php echo htmlspecialchars($merchant["business_name"]); ?></h4>
                                    <span class="merchant-location">
                                        <i class="fas fa-map-marker-alt"></i>
                                        <?php echo htmlspecialchars($merchant["city"]); ?>
                                    </span>
                                </div>
                                <div class="merchant-content">
                                    <p><strong>Type:</strong> <?php echo htmlspecialchars($merchant["business_type"]); ?></p>
                                    <p><strong>Available Items:</strong> <?php echo $merchant["item_count"]; ?> different items</p>
                                    <p><strong>Address:</strong> <?php echo htmlspecialchars($merchant["address"] ?? "Not provided"); ?></p>
                                    <p><strong>Postal Code:</strong> <?php echo htmlspecialchars($merchant["postal_code"]); ?></p>
                                </div>
                                <div class="merchant-actions">
                                    <a href="view_merchant_inventory.php?merchant_id=<?php echo $merchant["merchant_id"]; ?>" class="btn btn-primary btn-sm">
                                        View Inventory
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="no-results">
                        <p>No merchants found matching your criteria.</p>
                        <p>Try adjusting your search filters or <a href="resource_finder.php">view all merchants</a>.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
    
    <footer>
        <p>&copy; 2025 Pandemic Resilience System | Data-driven Systems (5CM506)</p>
    </footer>
    
    <style>
        .resource-finder-intro {
            margin-bottom: 1.5rem;
        }
        
        .map-container {
            width: 100%;
            height: 400px;
            margin-bottom: 2rem;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .merchant-cards {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
            margin: 1.5rem 0;
        }
        
        .merchant-card {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        
        .merchant-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }
        
        .merchant-header {
            padding: 1rem;
            background-color: #f0f5ff;
            border-bottom: 1px solid #e0e0ff;
        }
        
        .merchant-header h4 {
            margin: 0 0 0.5rem 0;
            color: #2a5caa;
        }
        
        .merchant-location {
            font-size: 0.9rem;
            color: #555;
        }
        
        .merchant-content {
            padding: 1rem;
        }
        
        .merchant-content p {
            margin: 0.5rem 0;
        }
        
        .merchant-actions {
            padding: 1rem;
            border-top: 1px solid #eee;
            display: flex;
            justify-content: flex-end;
        }
        
        .no-results {
            text-align: center;
            padding: 2rem;
            background-color: #f9f9f9;
            border-radius: 8px;
            margin: 1.5rem 0;
        }
        
        .user-location-marker .user-dot {
            background-color: #2a5caa;
            border-radius: 50%;
            width: 12px;
            height: 12px;
            border: 3px solid white;
            box-shadow: 0 0 5px rgba(0,0,0,0.5);
        }
        
        .merchant-popup h4 {
            margin: 0 0 8px 0;
            color: #2a5caa;
        }
        
        .merchant-popup p {
            margin: 5px 0;
        }
    </style>
    
    <script src="../../assets/js/main.js"></script>
    // Map initialization code - Place this in your resource_finder.php file at the bottom before the closing </body> tag
<script>
document.addEventListener('DOMContentLoaded', function() {
    const mapContainer = document.getElementById('merchant-map');
    if (!mapContainer) {
        console.error('Map container not found');
        return;
    }

    const map = L.map('merchant-map').setView([40.6401, 22.9444], 12);
    
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);
    
    const merchants = <?php echo json_encode($merchants); ?>;
    
    merchants.forEach(function(merchant) {
        if (!merchant.latitude || !merchant.longitude) {
           
            merchant.latitude = 40.6401 + (Math.random() * 0.1 - 0.05);
            merchant.longitude = 22.9444 + (Math.random() * 0.1 - 0.05);
            console.log('Using fallback location for merchant: ' + merchant.business_name);
        }
        
        const marker = L.marker([merchant.latitude, merchant.longitude]).addTo(map);
        
        const popupContent = `
            <div class="merchant-popup">
                <h4>${merchant.business_name || 'Merchant'}</h4>
                <p><strong>Type:</strong> ${merchant.business_type || 'N/A'}</p>
                <p><strong>Location:</strong> ${merchant.city || 'N/A'}</p>
                <p><strong>Available Items:</strong> ${merchant.item_count || '0'}</p>
                <a href="view_merchant_inventory.php?merchant_id=${merchant.merchant_id}" class="btn btn-primary btn-sm">
                    View Inventory
                </a>
            </div>
        `;
        
        marker.bindPopup(popupContent);
    });
    
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(function(position) {
            const userLat = position.coords.latitude;
            const userLng = position.coords.longitude;
            
            const userMarker = L.marker([userLat, userLng], {
                icon: L.divIcon({
                    className: 'user-location-marker',
                    html: '<div class="user-dot"></div>',
                    iconSize: [20, 20]
                })
            }).addTo(map);
            
            userMarker.bindPopup('<strong>Your Location</strong>').openPopup();
            
            map.setView([userLat, userLng], 12);
        }, function(error) {
            console.error('Error getting location:', error);
            if (merchants.length > 0 && merchants[0].latitude && merchants[0].longitude) {
                map.setView([merchants[0].latitude, merchants[0].longitude], 12);
            } else {
                map.setView([40.6401, 22.9444], 12);
            }
        }, {
            timeout: 10000,
            enableHighAccuracy: true
        });
    } else {
        console.error('Geolocation not supported');
        if (merchants.length > 0 && merchants[0].latitude && merchants[0].longitude) {
            map.setView([merchants[0].latitude, merchants[0].longitude], 12);
        }
    }
});
</script>
</body>
</html>