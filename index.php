<?php
session_start();

if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
    switch ($_SESSION["role"]) {
        case "public":
            header("location: dashboards/public/");
            break;
        case "government":
            header("location: dashboards/government/");
            break;
        case "merchant":
            header("location: dashboards/merchant/");
            break;
        default:
            session_unset();
            session_destroy();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pandemic Resilience System</title>
    <link rel="stylesheet" href="assets/css/main.css">
</head>
<body>
    <header>
        <div class="logo-container">
            <img src="assets/images/logo.png" alt="PRS Logo" class="logo">
            <h1>Pandemic Resilience System</h1>
        </div>
        <nav>
            <ul>
                <li><a href="index.php">Home</a></li>
                <li><a href="login.php">Login</a></li>
                <li><a href="register.php">Register</a></li>
            </ul>
        </nav>
    </header>
    
    <main>
        <section class="hero">
            <div class="hero-content">
                <h2>Stay Safe, Stay Informed</h2>
                <p>The Pandemic Resilience System (PRS) provides a centralized platform for managing essential activities during pandemics.</p>
                <div class="hero-buttons">
                    <a href="login.php" class="btn btn-primary">Login</a>
                    <a href="register.php" class="btn btn-secondary">Register</a>
                </div>
            </div>
        </section>
        
        <section class="features">
            <h2>Key Features</h2>
            <div class="feature-grid">
                <div class="feature-card">
                    <h3>Vaccination Tracking</h3>
                    <p>Securely store and verify your vaccination records in one place.</p>
                </div>
                <div class="feature-card">
                    <h3>Resource Finder</h3>
                    <p>Locate essential supplies and services during pandemic situations.</p>
                </div>
                <div class="feature-card">
                    <h3>Merchant Portal</h3>
                    <p>For businesses to manage inventory and comply with regulations.</p>
                </div>
                <div class="feature-card">
                    <h3>Government Dashboard</h3>
                    <p>Tools for officials to monitor and coordinate pandemic response.</p>
                </div>
            </div>
        </section>
    </main>
    
    <footer>
        <p>&copy; 2025 Pandemic Resilience System | Data-driven Systems (5CM506)</p>
    </footer>
    
    <script src="assets/js/main.js"></script>
</body>
</html>