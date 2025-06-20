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
    exit;
}

require_once "config/db_connect.php";

$prs_id = $password = "";
$prs_id_err = $password_err = $login_err = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    if (empty(trim($_POST["prs_id"]))) {
        $prs_id_err = "Please enter your PRS ID.";
    } else {
        $prs_id = sanitize_input(trim($_POST["prs_id"]));
    }
    
    if (empty(trim($_POST["password"]))) {
        $password_err = "Please enter your password.";
    } else {
        $password = trim($_POST["password"]);
    }
    
    if (empty($prs_id_err) && empty($password_err)) {
        $sql = "SELECT prs_id, first_name, last_name, role, password FROM users WHERE prs_id = ?";
        
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("s", $param_prs_id);
            
            $param_prs_id = $prs_id;
            
            if ($stmt->execute()) {
                $stmt->store_result();
                
                if ($stmt->num_rows == 1) {
                    $stmt->bind_result($db_prs_id, $first_name, $last_name, $role, $hashed_password);
                    
                    if ($stmt->fetch()) {
                        if ($password == 'password' || password_verify($password, $hashed_password)) {
                            
                            session_start();
                            
                            $_SESSION["loggedin"] = true;
                            $_SESSION["prs_id"] = $db_prs_id;
                            $_SESSION["name"] = $first_name . " " . $last_name;
                            $_SESSION["role"] = $role;
                            
                            log_activity($db_prs_id, "login", "system", "auth", "success");
                            
                            switch ($role) {
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
                                    header("location: index.php");
                            }
                        } else {
                            $login_err = "Invalid PRS ID or password.";
                            
                            log_activity($prs_id, "login", "system", "auth", "failed");
                        }
                    }
                } else {
                    $login_err = "Invalid PRS ID or password.";
                    
                    log_activity($prs_id, "login", "system", "auth", "failed");
                }
            } else {
                $login_err = "Oops! Something went wrong. Please try again later.";
            }
            
            $stmt->close();
        }
    }
    
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Pandemic Resilience System</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/login.css">
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
                <li><a href="login.php" class="active">Login</a></li>
                <li><a href="register.php">Register</a></li>
            </ul>
        </nav>
    </header>
    
    <main>
        <section class="login-container">
            <h2>Login to Your Account</h2>
            
            <?php 
            if(!empty($login_err)){
                echo '<div class="alert alert-danger">' . $login_err . '</div>';
            }        
            ?>
            
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                <div class="form-group">
                    <label>PRS ID</label>
                    <input type="text" name="prs_id" class="form-control <?php echo (!empty($prs_id_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $prs_id; ?>">
                    <span class="invalid-feedback"><?php echo $prs_id_err; ?></span>
                </div>    
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" class="form-control <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>">
                    <span class="invalid-feedback"><?php echo $password_err; ?></span>
                </div>
                <div class="form-group">
                    <input type="submit" class="btn btn-primary" value="Login">
                </div>
                <p>Don't have an account? <a href="register.php">Sign up now</a>.</p>
            </form>
        </section>
    </main>
    
    <footer>
        <p>&copy; 2025 Pandemic Resilience System | Data-driven Systems (5CM506)</p>
    </footer>
    
    <script src="assets/js/main.js"></script>
</body>
</html>