<?php
$pageTitle = "Register";
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirect('dashboard.php');
}

// Check if registration is allowed (only allowed for college admin and super admin)
$canRegister = false;
$colleges = [];
$error = '';
$success = '';

// If logged in as admin, allow registration
if (isLoggedIn() && hasRole(['super_admin', 'college_admin'])) {
    $canRegister = true;
    
    // Get colleges for dropdown
    if (hasRole('super_admin')) {
        $colleges = fetchAll("SELECT * FROM colleges ORDER BY name");
    } else {
        // College admin can only add users to their college
        $colleges = fetchAll("SELECT * FROM colleges WHERE id = ?", [$_SESSION['college_id']]);
    }
} else {
    // For public registration, check if first user (super admin) exists
    $superAdminExists = fetchOne("SELECT COUNT(*) as count FROM users WHERE role = 'super_admin'");
    
    if (!$superAdminExists || $superAdminExists['count'] == 0) {
        // No super admin exists, allow registration of first super admin
        $canRegister = true;
    } else {
        // See if there are any colleges
        $collegeCount = fetchOne("SELECT COUNT(*) as count FROM colleges");
        
        if ($collegeCount && $collegeCount['count'] > 0) {
            // Allow employee registration
            $canRegister = true;
            $colleges = fetchAll("SELECT * FROM colleges ORDER BY name");
        }
    }
}

// Process registration form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canRegister) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $_SESSION['error'] = "Invalid request";
        redirect('register.php');
    }
    
    // Get and sanitize input
    $username = sanitizeInput($_POST['username']);
    $email = sanitizeInput($_POST['email']);
    $password = $_POST['password']; // Don't sanitize password
    $confirmPassword = $_POST['confirm_password']; // Don't sanitize password
    $role = isset($_POST['role']) ? sanitizeInput($_POST['role']) : 'employee';
    $collegeId = isset($_POST['college_id']) ? intval($_POST['college_id']) : null;
    
    // Validate input
    if (empty($username) || empty($email) || empty($password)) {
        $error = "Please fill in all required fields";
    } elseif (!isValidEmail($email)) {
        $error = "Please enter a valid email address";
    } elseif ($password !== $confirmPassword) {
        $error = "Passwords do not match";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long";
    } elseif (!hasRole(['super_admin', 'college_admin']) && $role !== 'employee') {
        $error = "You can only register as an employee";
    } else {
        // For super admin, college ID is not required
        if ($role === 'super_admin') {
            $collegeId = null;
        } 
        // For other roles, college ID is required
        elseif (empty($collegeId)) {
            $error = "Please select a college";
        }
        
        // If college admin is registering a user, ensure they're registering for their college
        if (hasRole('college_admin') && $collegeId != $_SESSION['college_id']) {
            $error = "You can only register users for your college";
        }
        
        // Role restrictions
        if (!hasRole('super_admin') && in_array($role, ['super_admin'])) {
            $error = "You don't have permission to create this type of user";
        }
        
        if (!hasRole(['super_admin', 'college_admin']) && $role !== 'employee') {
            $error = "You can only register as an employee";
        }
    }
    
    // If no errors, register user
    if (empty($error)) {
        $userData = [
            'username' => $username,
            'email' => $email,
            'password' => $password,
            'role' => $role,
            'college_id' => $collegeId
        ];
        
        $userId = registerUser($userData);
        
        if ($userId) {
            $success = "User registered successfully!";
            
            // If no one is logged in, log in the new user
            if (!isLoggedIn()) {
                $user = getUserById($userId);
                setUserSession($user);
                redirect('dashboard.php');
            } else {
                // Clear form 
                $username = '';
                $email = '';
            }
        } else {
            $error = "Username or email already exists";
        }
    }
}

include 'includes/header.php';
?>

<div class="row">
    <div class="col-md-8 offset-md-2">
        <div class="register-form mt-4">
            <?php if (!$canRegister): ?>
                <div class="alert alert-info">
                    <h4 class="alert-heading">Registration Information</h4>
                    <p>Public registration is not available. Please contact your college administrator to create an account for you.</p>
                </div>
            <?php else: ?>
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0"><i class="fas fa-user-plus me-2"></i>Register User</h4>
                    </div>
                    <div class="card-body p-4">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success"><?php echo $success; ?></div>
                        <?php endif; ?>
                        
                        <form method="POST" action="register.php">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="username" class="form-label required-field">Username</label>
                                        <input type="text" class="form-control" id="username" name="username" 
                                               value="<?php echo isset($username) ? htmlspecialchars($username) : ''; ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="email" class="form-label required-field">Email Address</label>
                                        <input type="email" class="form-control" id="email" name="email" 
                                               value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="password" class="form-label required-field">Password</label>
                                        <input type="password" class="form-control" id="password" name="password" required>
                                        <div class="mt-1">
                                            <small>Strength: <span id="passwordStrength">Not entered</span></small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="confirm_password" class="form-label required-field">Confirm Password</label>
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                        <div class="text-danger small d-none" id="passwordMismatch">Passwords don't match</div>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if (hasRole(['super_admin', 'college_admin'])): ?>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="role" class="form-label required-field">Role</label>
                                            <select class="form-select" id="role" name="role" required>
                                                <?php if (hasRole('super_admin')): ?>
                                                    <option value="super_admin">Super Admin</option>
                                                    <option value="college_admin">College Admin</option>
                                                <?php endif; ?>
                                                <option value="technical_staff">Technical Staff</option>
                                                <option value="non_technical_staff">Non-Technical Staff</option>
                                                <option value="employee" selected>Employee</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="college_id" class="form-label required-field">College</label>
                                            <select class="form-select" id="college_id" name="college_id">
                                                <option value="">Select College</option>
                                                <?php foreach ($colleges as $college): ?>
                                                    <option value="<?php echo $college['id']; ?>">
                                                        <?php echo htmlspecialchars($college['name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            <?php elseif (!empty($colleges)): ?>
                                <input type="hidden" name="role" value="employee">
                                <div class="mb-3">
                                    <label for="college_id" class="form-label required-field">College</label>
                                    <select class="form-select" id="college_id" name="college_id" required>
                                        <option value="">Select College</option>
                                        <?php foreach ($colleges as $college): ?>
                                            <option value="<?php echo $college['id']; ?>">
                                                <?php echo htmlspecialchars($college['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php else: ?>
                                <!-- First user registration (super admin) -->
                                <input type="hidden" name="role" value="super_admin">
                            <?php endif; ?>
                            
                            <div class="d-grid gap-2 mt-4">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-user-plus me-2"></i>Register
                                </button>
                            </div>
                        </form>
                        
                        <?php if (!isLoggedIn()): ?>
                            <div class="mt-3 text-center">
                                <p>Already have an account? <a href="login.php">Login</a></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
