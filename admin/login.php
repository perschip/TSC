<?php
// Include database connection and auth check
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Function to log users in
function loginUser($user_id, $username, $role) {
    $_SESSION['user_id'] = $user_id;
    $_SESSION['username'] = $username;
    $_SESSION['user_role'] = $role;
    $_SESSION['logged_in_time'] = time();
}

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    // Redirect to dashboard
    header('Location: index.php');
    exit;
}

// Check for timeout message
$timeout_message = '';
if (isset($_SESSION['timeout_message'])) {
    $timeout_message = $_SESSION['timeout_message'];
    unset($_SESSION['timeout_message']);
}

// Check for login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    // Validate inputs
    $errors = [];
    
    if (empty($username)) {
        $errors[] = 'Username is required';
    }
    
    if (empty($password)) {
        $errors[] = 'Password is required';
    }
    
    // If no validation errors, attempt login
    if (empty($errors)) {
        try {
            $query = "SELECT id, username, password, role FROM users WHERE username = :username LIMIT 1";
            $stmt = $pdo->prepare($query);
            $stmt->bindParam(':username', $username);
            $stmt->execute();
            
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                // Login successful
                loginUser($user['id'], $user['username'], $user['role']);
                
                // Log the successful login
                try {
                    $log_query = "INSERT INTO login_logs (user_id, username, ip_address, success) 
                                VALUES (:user_id, :username, :ip, 1)";
                    $log_stmt = $pdo->prepare($log_query);
                    $log_stmt->bindParam(':user_id', $user['id']);
                    $log_stmt->bindParam(':username', $user['username']);
                    $log_stmt->bindParam(':ip', $_SERVER['REMOTE_ADDR']);
                    $log_stmt->execute();
                } catch (PDOException $e) {
                    // Silent fail - don't block login if logging fails
                }
                
                // Redirect to original requested page or dashboard
                if (isset($_SESSION['redirect_after_login'])) {
                    $redirect = $_SESSION['redirect_after_login'];
                    unset($_SESSION['redirect_after_login']);
                    header('Location: ' . $redirect);
                } else {
                    header('Location: index.php');
                }
                exit;
            } else {
                // Invalid credentials
                $errors[] = 'Invalid username or password';
                
                // Log the failed login attempt
                try {
                    $log_query = "INSERT INTO login_logs (username, ip_address, success) 
                                VALUES (:username, :ip, 0)";
                    $log_stmt = $pdo->prepare($log_query);
                    $log_stmt->bindParam(':username', $username);
                    $log_stmt->bindParam(':ip', $_SERVER['REMOTE_ADDR']);
                    $log_stmt->execute();
                } catch (PDOException $e) {
                    // Silent fail - don't block login if logging fails
                }
            }
        } catch (PDOException $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Tristate Cards</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="/admin/assets/css/admin.css" rel="stylesheet">
</head>
<body>
    <div class="login-container">
        <div class="card login-card">
            <div class="login-header">
                <div class="login-logo">Tristate Cards</div>
                <div class="login-subtext">Admin Portal</div>
            </div>
            <div class="login-body">
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($timeout_message)): ?>
                    <div class="alert alert-warning">
                        <?php echo htmlspecialchars($timeout_message); ?>
                    </div>
                <?php endif; ?>
                
                <form method="post" action="login.php">
                    <div class="mb-4">
                        <label for="username" class="form-label">Username</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-user"></i>
                            </span>
                            <input type="text" class="form-control login-input" id="username" name="username" 
                                   value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" 
                                   required autofocus>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-lock"></i>
                            </span>
                            <input type="password" class="form-control login-input" id="password" name="password" required>
                        </div>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="remember_me" name="remember_me">
                        <label class="form-check-label" for="remember_me">Remember me</label>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-login">
                            <i class="fas fa-sign-in-alt me-2"></i> Login
                        </button>
                    </div>
                </form>
                
                <div class="text-center mt-4">
                    <a href="/emergency_access.php" class="text-decoration-none">Trouble logging in? Use Emergency Access</a>
                </div>
            </div>
        </div>
        
        <div class="text-center mt-3 text-muted">
            <small>&copy; <?php echo date('Y'); ?> Tristate Cards. All rights reserved.</small>
        </div>
    </div>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>
</html>