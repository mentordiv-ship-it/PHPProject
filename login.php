<?php
$pageTitle = "Login";
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirect('dashboard.php');
}

$username = '';
$error = '';

// Process login form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $_SESSION['error'] = "Invalid request";
        redirect('login.php');
    }
    
    $username = sanitizeInput($_POST['username']);
    $password = sanitizeInput($_POST['password']); // Don't sanitize password
    
    // Validate input
    if (empty($username) || empty($password)) {
        $error = "Please enter both username and password";
    } else {
        // Authenticate user
        $user = authenticateUser($username, $password);
        
        if ($user) {
            // Set user session
            setUserSession($user);
            
            // Redirect to dashboard
            $_SESSION['success'] = "Welcome back, " . $user['username'] . "!";
            redirect('dashboard.php');
        } else {
            $error = "Invalid username or password";
        }
    }
}

include 'includes/header.php';
?>

<div class="row">
    <div class="col-md-6 offset-md-3">
        <div class="login-form mt-5">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0"><i class="fas fa-sign-in-alt me-2"></i>Login</h4>
                </div>
                <div class="card-body p-4">
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>
                    
                    <form method="POST" action="login.php">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-user"></i></span>
                                <input type="text" class="form-control" id="username" name="username" 
                                       value="<?php echo htmlspecialchars($username); ?>" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-sign-in-alt me-2"></i>Login
                            </button>
                        </div>
                    </form>
                    
                    <div class="mt-3 text-center">
                        <p>Don't have an account? <a href="register.php">Register</a></p>
                    </div>
                </div>
                <div class="card-footer bg-light text-center">
                    <p class="text-muted mb-0">Use your credentials to access the system</p>
                </div>
            </div>
            
            <!-- Login Information for Testing -->
            <div class="card mt-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0">Default Login - For Development Only</h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-warning">
                        <p class="mb-1"><strong>Super Admin:</strong></p>
                        <p class="mb-1">Username: superadmin</p>
                        <p class="mb-3">Password: admin123</p>
                        <p class="mb-0 small text-danger">Note: Change this password immediately after first login.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
