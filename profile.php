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

$user = [];
$sql = "SELECT * FROM users WHERE prs_id = ?";

if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("s", $_SESSION["prs_id"]);
    
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        
        if ($result->num_rows == 1) {
            $user = $result->fetch_assoc();
        }
    }
    
    $stmt->close();
}

log_activity($_SESSION["prs_id"], "view", "profile", "public", "success");

$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Pandemic Resilience System</title>
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
        
        <div class="dashboard-content">
            <h2>My Profile</h2>
            
            <div class="profile-section">
                <h3>Personal Information</h3>
                <div class="profile-info-container">
                    <div class="profile-info-group">
                        <label>PRS ID:</label>
                        <p><?php echo htmlspecialchars($user["prs_id"] ?? ""); ?></p>
                    </div>
                    
                    <div class="profile-info-group">
                        <label>Name:</label>
                        <p><?php echo htmlspecialchars(($user["first_name"] ?? "") . " " . ($user["last_name"] ?? "")); ?></p>
                    </div>
                    
                    <div class="profile-info-group">
                        <label>Email:</label>
                        <p><?php echo htmlspecialchars($user["email"] ?? ""); ?></p>
                    </div>
                    
                    <div class="profile-info-group">
                        <label>Date of Birth:</label>
                        <p><?php echo date("F j, Y", strtotime($user["dob"] ?? "")); ?></p>
                    </div>
                    
                    <div class="profile-info-group">
                        <label>Phone:</label>
                        <p><?php echo htmlspecialchars($user["phone"] ?? "Not provided"); ?></p>
                    </div>
                    
                    <div class="profile-info-group">
                        <label>Address:</label>
                        <p><?php echo htmlspecialchars($user["address"] ?? "Not provided"); ?></p>
                    </div>
                    
                    <div class="profile-info-group">
                        <label>City:</label>
                        <p><?php echo htmlspecialchars($user["city"] ?? "Not provided"); ?></p>
                    </div>
                    
                    <div class="profile-info-group">
                        <label>Postal Code:</label>
                        <p><?php echo htmlspecialchars($user["postal_code"] ?? "Not provided"); ?></p>
                    </div>
                </div>
            </div>
            
            <div class="profile-section">
                <div class="section-header">
                    <h3>Family Members</h3>
                    <button id="add-family-btn" class="btn btn-primary btn-sm">Add Family Member</button>
                </div>
                
                <div id="family-members-container">
                    <?php
                    $family_members = [];
                    $sql = "SELECT id, first_name, last_name, dob, relationship FROM family_members WHERE prs_id = ? ORDER BY first_name";
                    
                    if ($stmt = $conn->prepare($sql)) {
                        $stmt->bind_param("s", $_SESSION["prs_id"]);
                        
                        if ($stmt->execute()) {
                            $result = $stmt->get_result();
                            
                            while ($row = $result->fetch_assoc()) {
                                $family_members[] = $row;
                            }
                        }
                        
                        $stmt->close();
                    }
                    
                    if (count($family_members) > 0):
                    ?>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Date of Birth</th>
                                    <th>Relationship</th>
                                    <th>Face Mask Day</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($family_members as $member): 
                                    $year = date("Y", strtotime($member["dob"]));
                                    $lastDigit = substr($year, -1);
                                    
                                    $dayMap = [
                                        "0" => "Monday",
                                        "1" => "Tuesday",
                                        "2" => "Wednesday",
                                        "3" => "Thursday", 
                                        "4" => "Friday",
                                        "5" => "Saturday",
                                        "6" => "Sunday",
                                        "7" => "Saturday",
                                        "8" => "Sunday",
                                        "9" => "Monday"
                                    ];
                                    
                                    $faceDay = $dayMap[$lastDigit] ?? "Any day";
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($member["first_name"] . " " . $member["last_name"]); ?></td>
                                    <td><?php echo date("M j, Y", strtotime($member["dob"])); ?></td>
                                    <td><?php echo htmlspecialchars($member["relationship"]); ?></td>
                                    <td><?php echo $faceDay; ?></td>
                                    <td>
                                        <button class="btn btn-primary btn-sm edit-family-btn" data-id="<?php echo $member["id"]; ?>">Edit</button>
                                        <button class="btn btn-danger btn-sm delete-family-btn" data-id="<?php echo $member["id"]; ?>">Delete</button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="no-data-message">
                        <p>No family members added yet. Click the "Add Family Member" button to add one.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
    
    <div id="family-modal" class="modal" style="display: none;">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h3 id="modal-title">Add Family Member</h3>
            <form id="family-form" method="post" action="ajax/family_members.php">
                <input type="hidden" id="family_id" name="family_id" value="">
                <input type="hidden" id="action" name="action" value="add">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="first_name">First Name*</label>
                        <input type="text" id="first_name" name="first_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="last_name">Last Name*</label>
                        <input type="text" id="last_name" name="last_name" class="form-control" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="dob">Date of Birth*</label>
                        <input type="date" id="dob" name="dob" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="relationship">Relationship*</label>
                        <select id="relationship" name="relationship" class="form-control" required>
                            <option value="">Select relationship</option>
                            <option value="Spouse">Spouse</option>
                            <option value="Child">Child</option>
                            <option value="Parent">Parent</option>
                            <option value="Sibling">Sibling</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Save</button>
                    <button type="button" class="btn btn-secondary" id="cancel-btn">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    
    <footer>
        <p>&copy; 2025 Pandemic Resilience System | Data-driven Systems (5CM506)</p>
    </footer>
    
    <style>
        .profile-section {
            background-color: white;
            border-radius: 8px;
            padding: 1.5rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            margin-bottom: 2rem;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .profile-info-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .profile-info-group {
            margin-bottom: 1rem;
        }
        
        .profile-info-group label {
            font-weight: 600;
            color: #555;
            display: block;
            margin-bottom: 0.25rem;
        }
        
        .profile-info-group p {
            margin: 0;
            padding: 0.5rem 0;
            border-bottom: 1px solid #eee;
        }
        
        .action-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }
        
        .no-data-message {
            background-color: #f8f9fa;
            padding: 2rem;
            border-radius: 8px;
            text-align: center;
            color: #6c757d;
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
        }
        
        .close {
            position: absolute;
            right: 20px;
            top: 10px;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
    </style>
    
    <script src="../../assets/js/main.js"></script>
    <script>
        const modal = document.getElementById('family-modal');
        const addFamilyBtn = document.getElementById('add-family-btn');
        const closeBtn = document.querySelector('.close');
        const cancelBtn = document.getElementById('cancel-btn');
        const form = document.getElementById('family-form');
        const modalTitle = document.getElementById('modal-title');
        
        addFamilyBtn.addEventListener('click', () => {
            form.reset();
            document.getElementById('family_id').value = '';
            document.getElementById('action').value = 'add';
            modalTitle.textContent = 'Add Family Member';
            
            modal.style.display = 'flex';
        });
        
        closeBtn.addEventListener('click', () => {
            modal.style.display = 'none';
        });
        
        cancelBtn.addEventListener('click', () => {
            modal.style.display = 'none';
        });
        
        window.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.style.display = 'none';
            }
        });
        
        document.querySelectorAll('.edit-family-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                
                fetch(`ajax/family_members.php?action=get&id=${id}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'success') {
                            document.getElementById('family_id').value = data.member.id;
                            document.getElementById('first_name').value = data.member.first_name;
                            document.getElementById('last_name').value = data.member.last_name;
                            document.getElementById('dob').value = data.member.dob;
                            document.getElementById('relationship').value = data.member.relationship;
                            document.getElementById('action').value = 'edit';
                            
                            modalTitle.textContent = 'Edit Family Member';
                            modal.style.display = 'flex';
                        } else {
                            alert('Error: ' + data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('An error occurred while fetching data');
                    });
            });
        });
        
        document.querySelectorAll('.delete-family-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                if (confirm('Are you sure you want to delete this family member?')) {
                    const id = this.getAttribute('data-id');
                    
                    fetch(`ajax/family_members.php?action=delete&id=${id}`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.status === 'success') {
                                location.reload();
                            } else {
                                alert('Error: ' + data.message);
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('An error occurred while deleting');
                        });
                }
            });
        });
        
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch('ajax/family_members.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    modal.style.display = 'none';
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while saving');
            });
        });
    </script>
</body>
</html>