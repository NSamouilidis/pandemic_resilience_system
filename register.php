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

$national_id = $first_name = $last_name = $dob = $email = $phone = $address = $city = $postal_code = $password = $confirm_password = "";
$national_id_err = $first_name_err = $last_name_err = $dob_err = $email_err = $phone_err = $address_err = $city_err = $postal_code_err = $password_err = $confirm_password_err = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    if (empty(trim($_POST["national_id"]))) {
        $national_id_err = "Please enter your National ID.";
    } else {
        $national_id = sanitize_input(trim($_POST["national_id"]));
    }
    
    if (empty(trim($_POST["first_name"]))) {
        $first_name_err = "Please enter your first name.";
    } else {
        $first_name = sanitize_input(trim($_POST["first_name"]));
    }
    
    if (empty(trim($_POST["last_name"]))) {
        $last_name_err = "Please enter your last name.";
    } else {
        $last_name = sanitize_input(trim($_POST["last_name"]));
    }
    
    if (empty(trim($_POST["dob"]))) {
        $dob_err = "Please enter your date of birth.";
    } else {
        $dob = sanitize_input(trim($_POST["dob"]));
    }
    
    if (empty(trim($_POST["email"]))) {
        $email_err = "Please enter your email.";
    } else {
        $sql = "SELECT prs_id FROM users WHERE email = ?";
        
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("s", $param_email);
            $param_email = trim($_POST["email"]);
            
            if ($stmt->execute()) {
                $stmt->store_result();
                
                if ($stmt->num_rows > 0) {
                    $email_err = "This email is already registered.";
                } else {
                    $email = sanitize_input(trim($_POST["email"]));
                }
            } else {
                echo "Oops! Something went wrong. Please try again later.";
            }
            
            $stmt->close();
        }
    }
    
    $phone = sanitize_input(trim($_POST["phone"] ?? ""));
    $address = sanitize_input(trim($_POST["address"] ?? ""));
    $city = sanitize_input(trim($_POST["city"] ?? ""));
    $postal_code = sanitize_input(trim($_POST["postal_code"] ?? ""));
    
    if (empty(trim($_POST["password"]))) {
        $password_err = "Please enter a password.";     
    } elseif (strlen(trim($_POST["password"])) < 6) {
        $password_err = "Password must have at least 6 characters.";
    } else {
        $password = trim($_POST["password"]);
    }
    
    if (empty(trim($_POST["confirm_password"]))) {
        $confirm_password_err = "Please confirm password.";     
    } else {
        $confirm_password = trim($_POST["confirm_password"]);
        if (empty($password_err) && ($password != $confirm_password)) {
            $confirm_password_err = "Passwords did not match.";
        }
    }
    
    if (empty($national_id_err) && empty($first_name_err) && empty($last_name_err) && empty($dob_err) && empty($email_err) && empty($password_err) && empty($confirm_password_err)) {
        
        $prefix = "PRS-PUB-";
        
        $last_id_sql = "SELECT prs_id FROM users WHERE prs_id LIKE ? ORDER BY prs_id DESC LIMIT 1";
        $stmt = $conn->prepare($last_id_sql);
        $prefix_param = $prefix . "%";
        $stmt->bind_param("s", $prefix_param);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $next_number = 1;
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $last_id = $row["prs_id"];
            $last_number = intval(substr($last_id, -3));
            $next_number = $last_number + 1;
        }
        
        $stmt->close();
        
        $prs_id = $prefix . str_pad($next_number, 3, "0", STR_PAD_LEFT);
        
        $encrypted_national_id = encrypt_data($national_id);
        
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        $sql = "INSERT INTO users (prs_id, national_id, first_name, last_name, dob, email, phone, address, city, postal_code, role, password) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'public', ?)";
         
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("sssssssssss", $prs_id, $encrypted_national_id, $first_name, $last_name, $dob, $email, $phone, $address, $city, $postal_code, $hashed_password);
            
            if ($stmt->execute()) {
                log_activity($prs_id, "register", "system", "auth", "success");
                
                header("location: login.php?registration=success");
            } else {
                echo "Oops! Something went wrong. Please try again later.";
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
    <title>Register - Pandemic Resilience System</title>
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
                <li><a href="login.php">Login</a></li>
                <li><a href="register.php" class="active">Register</a></li>
            </ul>
        </nav>
    </header>
    
    <main>
        <section class="register-container">
            <h2>Create New Account</h2>
            <p>Please fill this form to create an account.</p>
            
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                <div class="form-row">
                    <div class="form-group">
                        <label>National ID*</label>
                        <input type="text" name="national_id" class="form-control <?php echo (!empty($national_id_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $national_id; ?>">
                        <span class="invalid-feedback"><?php echo $national_id_err; ?></span>
                        <small class="form-text text-muted">This will be securely encrypted.</small>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>First Name*</label>
                        <input type="text" name="first_name" class="form-control <?php echo (!empty($first_name_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $first_name; ?>">
                        <span class="invalid-feedback"><?php echo $first_name_err; ?></span>
                    </div>
                    
                    <div class="form-group">
                        <label>Last Name*</label>
                        <input type="text" name="last_name" class="form-control <?php echo (!empty($last_name_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $last_name; ?>">
                        <span class="invalid-feedback"><?php echo $last_name_err; ?></span>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Date of Birth*</label>
                        <input type="date" name="dob" class="form-control <?php echo (!empty($dob_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $dob; ?>">
                        <span class="invalid-feedback"><?php echo $dob_err; ?></span>
                    </div>
                    
                    <div class="form-group">
                        <label>Email*</label>
                        <input type="email" name="email" class="form-control <?php echo (!empty($email_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $email; ?>">
                        <span class="invalid-feedback"><?php echo $email_err; ?></span>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="tel" name="phone" class="form-control" value="<?php echo $phone; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Address</label>
                        <input type="text" name="address" class="form-control" value="<?php echo $address; ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>City</label>
                        <input type="text" name="city" class="form-control" value="<?php echo $city; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Postal Code</label>
                        <input type="text" name="postal_code" class="form-control" value="<?php echo $postal_code; ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Password*</label>
                        <input type="password" name="password" class="form-control <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $password; ?>">
                        <span class="invalid-feedback"><?php echo $password_err; ?></span>
                        <small class="form-text text-muted">Minimum 6 characters</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Confirm Password*</label>
                        <input type="password" name="confirm_password" class="form-control <?php echo (!empty($confirm_password_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $confirm_password; ?>">
                        <span class="invalid-feedback"><?php echo $confirm_password_err; ?></span>
                    </div>
                </div>
                
                <div class="form-group">
                    <input type="submit" class="btn btn-primary" value="Register">
                    <input type="reset" class="btn btn-secondary" value="Reset">
                </div>
                
                <p>Already have an account? <a href="login.php">Login here</a>.</p>
            </form>
        </section>
    </main>
    
    <footer>
        <p>&copy; 2025 Pandemic Resilience System | Data-driven Systems (5CM506)</p>
    </footer>
    
    <script src="assets/js/main.js"></script>
</body>
</html>