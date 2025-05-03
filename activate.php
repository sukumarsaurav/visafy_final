<?php
// Include database connection
require_once 'config/db_connect.php';
require_once 'includes/functions.php';

// Initialize variables
$token = $password = $confirm_password = '';
$token_err = $password_err = $confirm_password_err = $general_err = '';
$success = false;

// Check if token is provided in URL
if (isset($_GET['token']) && !empty(trim($_GET['token']))) {
    $token = trim($_GET['token']);
    
    // Check if token exists and is valid
    $sql = "SELECT id, first_name, last_name, email, email_verification_token, email_verification_expires 
            FROM users 
            WHERE email_verification_token = ? AND email_verified = 0 AND status = 'suspended'"; 
    
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("s", $token);
        
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            
            if ($result->num_rows == 1) {
                $user = $result->fetch_assoc();
                
                // Check if token is expired
                $expiry_time = strtotime($user['email_verification_expires']);
                $current_time = time();
                
                if ($expiry_time < $current_time) {
                    $token_err = "This activation link has expired. Please contact your administrator to resend the invitation.";
                }
            } else {
                $token_err = "Invalid activation link or account already activated.";
            }
        } else {
            $general_err = "Oops! Something went wrong. Please try again later.";
        }
        
        $stmt->close();
    }
} else {
    $token_err = "No activation token provided.";
}

// Process form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["activate"])) {
    // Validate token again
    if (empty(trim($_POST["token"]))) {
        $token_err = "Invalid request.";
    } else {
        $token = trim($_POST["token"]);
    }
    
    // Validate password
    if (empty(trim($_POST["password"]))) {
        $password_err = "Please enter a password.";
    } elseif (strlen(trim($_POST["password"])) < 8) {
        $password_err = "Password must have at least 8 characters.";
    } else {
        $password = trim($_POST["password"]);
    }
    
    // Validate confirm password
    if (empty(trim($_POST["confirm_password"]))) {
        $confirm_password_err = "Please confirm password.";
    } else {
        $confirm_password = trim($_POST["confirm_password"]);
        if (empty($password_err) && ($password != $confirm_password)) {
            $confirm_password_err = "Password did not match.";
        }
    }
    
    // Check input errors before updating the database
    if (empty($token_err) && empty($password_err) && empty($confirm_password_err)) {
        // Get user details from token
        $sql = "SELECT id FROM users WHERE email_verification_token = ? AND email_verified = 0 AND status = 'suspended'";
        
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("s", $token);
            
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                
                if ($result->num_rows == 1) {
                    $user = $result->fetch_assoc();
                    
                    // Begin transaction
                    $conn->begin_transaction();
                    
                    try {
                        // Update user password and status
                        $update_sql = "UPDATE users SET 
                                      password = ?, 
                                      email_verified = 1, 
                                      email_verification_token = NULL, 
                                      email_verification_expires = NULL, 
                                      status = 'active', 
                                      updated_at = NOW() 
                                      WHERE id = ?";
                        
                        if ($update_stmt = $conn->prepare($update_sql)) {
                            // Hash the password
                            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                            
                            $update_stmt->bind_param("si", $hashed_password, $user['id']);
                            $update_stmt->execute();
                            $update_stmt->close();
                            
                            // Commit transaction
                            $conn->commit();
                            
                            // Set success flag
                            $success = true;
                        } else {
                            throw new Exception("Error preparing update statement");
                        }
                    } catch (Exception $e) {
                        // Rollback transaction on error
                        $conn->rollback();
                        $general_err = "Error activating account: " . $e->getMessage();
                    }
                } else {
                    $token_err = "Invalid activation link or account already activated.";
                }
            } else {
                $general_err = "Oops! Something went wrong. Please try again later.";
            }
            
            $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activate Account | Visafy</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <h2><?php echo $success ? "Account Activated" : "Activate Your Account"; ?></h2>
            </div>
            <div class="auth-body">
                <?php if ($success): ?>
                    <div class="auth-success">
                        <p>Your account has been successfully activated! You can now login with your email and password.</p>
                        <div class="form-group">
                            <a href="login.php" class="btn btn-primary">Go to Login</a>
                        </div>
                    </div>
                <?php else: ?>
                    <?php if (!empty($general_err)): ?>
                        <div class="auth-error">
                            <p><?php echo $general_err; ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($token_err)): ?>
                        <div class="auth-error">
                            <p><?php echo $token_err; ?></p>
                            <div class="form-group">
                                <a href="login.php" class="btn btn-primary">Go to Login</a>
                            </div>
                        </div>
                    <?php else: ?>
                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . '?token=' . $token); ?>" method="post" class="auth-form">
                            <input type="hidden" name="token" value="<?php echo $token; ?>">
                            
                            <div class="form-group">
                                <label>New Password</label>
                                <input type="password" name="password" id="password" class="form-control <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $password; ?>" minlength="8">
                                <span class="invalid-feedback"><?php echo $password_err; ?></span>
                            </div>
                            
                            <div class="form-group">
                                <label>Confirm Password</label>
                                <input type="password" name="confirm_password" class="form-control <?php echo (!empty($confirm_password_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $confirm_password; ?>" minlength="8">
                                <span class="invalid-feedback"><?php echo $confirm_password_err; ?></span>
                            </div>
                            
                            <div class="password-strength" id="password-strength">
                                <div class="strength-bar" id="strength-bar"></div>
                            </div>
                            <div class="strength-text" id="strength-text">Password strength</div>
                            
                            <div class="form-group">
                                <h3>Password Security Tips</h3>
                                <ul>
                                    <li>Use at least 8 characters</li>
                                    <li>Include uppercase and lowercase letters</li>
                                    <li>Include numbers and special characters</li>
                                    <li>Don't reuse passwords from other websites</li>
                                </ul>
                            </div>
                            
                            <div class="form-group">
                                <button type="submit" name="activate" class="btn btn-primary">Activate Account</button>
                            </div>
                        </form>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
    
    <script>
    // Password strength meter functionality
    const passwordInput = document.getElementById('password');
    const strengthBar = document.getElementById('strength-bar');
    const strengthText = document.getElementById('strength-text');
    
    if (passwordInput && strengthBar && strengthText) {
        passwordInput.addEventListener('input', function() {
            const password = this.value;
            let strength = 0;
            let status = '';
            
            if (password.length >= 8) strength += 25;
            if (password.match(/[A-Z]/)) strength += 25;
            if (password.match(/[0-9]/)) strength += 25;
            if (password.match(/[^A-Za-z0-9]/)) strength += 25;
            
            strengthBar.style.width = strength + '%';
            
            if (strength <= 25) {
                strengthBar.style.backgroundColor = '#dc3545';
                status = 'Weak';
            } else if (strength <= 50) {
                strengthBar.style.backgroundColor = '#ffc107';
                status = 'Fair';
            } else if (strength <= 75) {
                strengthBar.style.backgroundColor = '#28a745';
                status = 'Good';
            } else {
                strengthBar.style.backgroundColor = '#20c997';
                status = 'Strong';
            }
            
            strengthText.textContent = 'Password strength: ' + status;
        });
    }
    </script>
</body>
</html>