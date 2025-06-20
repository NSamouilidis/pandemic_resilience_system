<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>

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