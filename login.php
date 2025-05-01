<?php
// Include database connection
require_once 'config/db_connect.php';
require_once 'includes/functions.php';

// Initialize variables
$email = $password = '';
$email_err = $password_err = $login_err = '';
$login_success = false;

// Check if user just registered
if (isset($_GET['registered']) && $_GET['registered'] == 1) {
    $login_success = true;
}

// Process form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Validate email
    if (empty(trim($_POST["email"]))) {
        $email_err = "Please enter your email.";
    } else {
        $email = trim($_POST["email"]);
    }
    
    // Validate password
    if (empty(trim($_POST["password"]))) {
        $password_err = "Please enter your password.";
    } else {
        $password = trim($_POST["password"]);
    }
    
    // Validate credentials
    if (empty($email_err) && empty($password_err)) {
        // Prepare a select statement
        $sql = "SELECT id, first_name, last_name, email, password, user_type, status FROM users WHERE email = ?";
        
        if ($stmt = $conn->prepare($sql)) {
            // Bind variables to the prepared statement as parameters
            $stmt->bind_param("s", $param_email);
            
            // Set parameters
            $param_email = $email;
            
            // Attempt to execute the prepared statement
            if ($stmt->execute()) {
                // Store result
                $stmt->store_result();
                
                // Check if email exists, if yes then verify password
                if ($stmt->num_rows == 1) {                    
                    // Bind result variables
                    $stmt->bind_result($id, $first_name, $last_name, $email, $hashed_password, $user_type, $status);
                    if ($stmt->fetch()) {
                        // Check if account is active
                        if ($status == 'active') {
                            // Verify password
                            if (password_verify($password, $hashed_password)) {
                                // Password is correct, start a new session
                                session_start();
                                
                                // Store data in session variables
                                $_SESSION["loggedin"] = true;
                                $_SESSION["id"] = $id;
                                $_SESSION["email"] = $email;
                                $_SESSION["first_name"] = $first_name;
                                $_SESSION["last_name"] = $last_name;
                                $_SESSION["user_type"] = $user_type;
                                
                                // Redirect user based on user type
                                if ($user_type == 'applicant') {
                                    header("location: dashboard/applicant/index.php");
                                } elseif ($user_type == 'admin') {
                                    header("location: dashboard/admin/index.php");
                                } elseif ($user_type == 'member') {
                                    header("location: dashboard/member/index.php");
                                } else {
                                    header("location: index.php");
                                }
                                exit;
                            } else {
                                // Password is not valid
                                $login_err = "Invalid email or password.";
                            }
                        } else {
                            // Account is not active
                            $login_err = "Your account is not active. Please contact support.";
                        }
                    }
                } else {
                    // Email doesn't exist
                    $login_err = "Invalid email or password.";
                }
            } else {
                $login_err = "Oops! Something went wrong. Please try again later.";
            }

            // Close statement
            $stmt->close();
        }
    }
    
    // Close connection
    $conn->close();
}

// Set page title
$page_title = "Login | Visafy";
include 'includes/header.php';
?>

<div class="auth-container">
    <div class="auth-card">
        <div class="auth-header">
            <h2>Login</h2>
        </div>
        <div class="auth-body">
            <?php if ($login_success): ?>
                <div class="auth-success">
                    <p>Registration successful! You can now login with your credentials.</p>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($login_err)): ?>
                <div class="auth-error">
                    <p><?php echo $login_err; ?></p>
                </div>
            <?php endif; ?>
            
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="auth-form">
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" class="form-control <?php echo (!empty($email_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $email; ?>">
                    <span class="invalid-feedback"><?php echo $email_err; ?></span>
                </div>    
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" class="form-control <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>">
                    <span class="invalid-feedback"><?php echo $password_err; ?></span>
                </div>
                <div class="form-group">
                    <div class="remember-me">
                        <input type="checkbox" name="remember" id="remember">
                        <label for="remember">Remember me</label>
                    </div>
                    <a href="forgot-password.php" class="forgot-password">Forgot Password?</a>
                </div>
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Login</button>
                </div>
                <div class="auth-footer">
                    <p>Don't have an account? <a href="register.php">Register here</a></p>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
