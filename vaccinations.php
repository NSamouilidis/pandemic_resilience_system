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

$upload_message = "";
$upload_status = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["submit_vaccination"])) {
    $vaccine_name = sanitize_input($_POST["vaccine_name"] ?? "");
    $vaccination_date = sanitize_input($_POST["vaccination_date"] ?? "");
    $facility_name = sanitize_input($_POST["facility_name"] ?? "");
    $batch_number = sanitize_input($_POST["batch_number"] ?? "");
    $certificate_file = ""; // Will be set if file uploaded
    
    // Validate required fields
    if (empty($vaccine_name) || empty($vaccination_date) || empty($facility_name) || empty($batch_number)) {
        $upload_message = "Please fill all required fields";
        $upload_status = "danger";
    } else {
        if (!empty($_FILES["certificate_file"]["name"])) {
            $file_name = $_FILES["certificate_file"]["name"];
            $file_size = $_FILES["certificate_file"]["size"];
            $file_tmp = $_FILES["certificate_file"]["tmp_name"];
            $file_type = $_FILES["certificate_file"]["type"];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            
            $allowed_extensions = array("pdf", "txt");
            
            if (in_array($file_ext, $allowed_extensions) === false) {
                $upload_message = "Only PDF and TXT files are allowed";
                $upload_status = "danger";
            } elseif ($file_size > 5 * 1024 * 1024) { // 5MB limit
                $upload_message = "File size must be less than 5MB";
                $upload_status = "danger";
            } else {
                $upload_dir = "../../uploads/certificates/";
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $new_file_name = uniqid($_SESSION["prs_id"] . "_", true) . "." . $file_ext;
                $upload_path = $upload_dir . $new_file_name;
                
                if (move_uploaded_file($file_tmp, $upload_path)) {
                    $certificate_file = $new_file_name;
                } else {
                    $upload_message = "Error uploading file";
                    $upload_status = "danger";
                }
            }
        }
        
        if (empty($upload_status) || $upload_status !== "danger") {
            $sql = "INSERT INTO vaccinations (prs_id, vaccine_name, vaccination_date, facility_name, batch_number, certificate_file) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("ssssss", $_SESSION["prs_id"], $vaccine_name, $vaccination_date, $facility_name, $batch_number, $certificate_file);
                
                if ($stmt->execute()) {
                    $upload_message = "Vaccination record added successfully";
                    $upload_status = "success";
                    
                    log_activity($_SESSION["prs_id"], "create", "vaccination", "record", "success");
                } else {
                    $upload_message = "Error: " . $stmt->error;
                    $upload_status = "danger";
                }
                
                $stmt->close();
            } else {
                $upload_message = "Error: " . $conn->error;
                $upload_status = "danger";
            }
        }
    }
}

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

log_activity($_SESSION["prs_id"], "view", "vaccinations", "page", "success");

$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vaccination Records - Pandemic Resilience System</title>
    <link rel="stylesheet" href="../../assets/css/main.css">
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
                    <li><a href="index.php" class="<?php echo ($current_page == 'index.php') ? 'active' : ''; ?>">Dashboard</a></li>
                    <li><a href="profile.php" class="<?php echo ($current_page == 'profile.php') ? 'active' : ''; ?>">My Profile</a></li>
                    <li><a href="vaccinations.php" class="<?php echo ($current_page == 'vaccinations.php') ? 'active' : ''; ?>">Vaccination Records</a></li>
                    <li><a href="resource_finder.php" class="<?php echo ($current_page == 'resource_finder.php') ? 'active' : ''; ?>">Resource Finder</a></li>
                    <li><a href="purchase_history.php" class="<?php echo ($current_page == 'purchase_history.php') ? 'active' : ''; ?>">Purchase History</a></li>
                </ul>
            </nav>
        </aside>
        
        <!-- Main Content -->
        <div class="dashboard-content">
            <h2>My Vaccination Records</h2>
            
            <?php if (!empty($upload_message)): ?>
                <div class="alert alert-<?php echo $upload_status; ?>">
                    <?php echo $upload_message; ?>
                </div>
            <?php endif; ?>
            
            <div class="add-vaccination-section">
                <h3>Add New Vaccination Record</h3>
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="vaccination-form" enctype="multipart/form-data">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="vaccine_name">Vaccine Name*</label>
                            <input type="text" id="vaccine_name" name="vaccine_name" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="vaccination_date">Vaccination Date*</label>
                            <input type="date" id="vaccination_date" name="vaccination_date" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="facility_name">Facility Name*</label>
                            <input type="text" id="facility_name" name="facility_name" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="batch_number">Batch Number*</label>
                            <input type="text" id="batch_number" name="batch_number" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="certificate_file">Vaccination Certificate (PDF or TXT only)</label>
                            <input type="file" id="certificate_file" name="certificate_file" class="form-control" accept=".pdf,.txt">
                            <small class="form-text text-muted">Upload your vaccination certificate (max 5MB)</small>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" name="submit_vaccination" class="btn btn-primary">Add Vaccination Record</button>
                    </div>
                </form>
            </div>
            
            <div class="vaccination-records-section">
                <h3>Vaccination History</h3>
                
                <?php if (count($vaccinations) > 0): ?>
                    <div class="status-badge complete">
                        <span>Vaccinated</span>
                    </div>
                    
                    <div class="vaccination-list">
                        <?php foreach ($vaccinations as $vax): ?>
                            <div class="vaccination-card">
                                <div class="vaccination-header">
                                    <h4><?php echo htmlspecialchars($vax["vaccine_name"]); ?></h4>
                                    <span class="vax-date"><?php echo date("F j, Y", strtotime($vax["vaccination_date"])); ?></span>
                                </div>
                                <div class="vaccination-details">
                                    <p><strong>Facility:</strong> <?php echo htmlspecialchars($vax["facility_name"]); ?></p>
                                    <p><strong>Batch Number:</strong> <?php echo htmlspecialchars($vax["batch_number"]); ?></p>
                                    <?php if (!empty($vax["certificate_file"])): ?>
                                    <p>
                                        <a href="../../uploads/certificates/<?php echo $vax["certificate_file"]; ?>" target="_blank" class="certificate-link">
                                            View Certificate
                                        </a>
                                    </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="status-badge incomplete">
                        <span>Not Vaccinated</span>
                    </div>
                    <p>You have no vaccination records. Please add your vaccination information using the form above.</p>
                <?php endif; ?>
            </div>
        </div>
    </main>
    
    <footer>
        <p>&copy; 2025 Pandemic Resilience System | Data-driven Systems (5CM506)</p>
    </footer>
    
    <script src="../../assets/js/main.js"></script>
</body>
</html>