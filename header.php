<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$root_path = "";
$parent_count = substr_count($_SERVER['PHP_SELF'], '/') - 1;
for ($i = 0; $i < $parent_count; $i++) {
    $root_path .= "../";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'Pandemic Resilience System'; ?></title>
    <link rel="stylesheet" href="<?php echo $root_path; ?>assets/css/main.css">
    <?php if (isset($additional_css)): ?>
        <?php foreach ($additional_css as $css_file): ?>
            <link rel="stylesheet" href="<?php echo $root_path . $css_file; ?>">
        <?php endforeach; ?>
    <?php endif; ?>
</head>
<body>
    <header>
        <div class="logo-container">
            <img src="<?php echo $root_path; ?>assets/images/logo.png" alt="PRS Logo" class="logo">
            <h1>Pandemic Resilience System</h1>
        </div>
        
        <?php if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true): ?>
            <div class="user-menu">
                <span>Welcome, <?php echo htmlspecialchars($_SESSION["name"]); ?></span>
                <a href="<?php echo $root_path; ?>logout.php" class="btn btn-secondary btn-sm">Logout</a>
            </div>
        <?php else: ?>
            <nav>
                <ul>
                    <li><a href="<?php echo $root_path; ?>index.php" <?php echo (basename($_SERVER['PHP_SELF']) == 'index.php') ? 'class="active"' : ''; ?>>Home</a></li>
                    <li><a href="<?php echo $root_path; ?>login.php" <?php echo (basename($_SERVER['PHP_SELF']) == 'login.php') ? 'class="active"' : ''; ?>>Login</a></li>
                    <li><a href="<?php echo $root_path; ?>register.php" <?php echo (basename($_SERVER['PHP_SELF']) == 'register.php') ? 'class="active"' : ''; ?>>Register</a></li>
                </ul>
            </nav>
        <?php endif; ?>
    </header>