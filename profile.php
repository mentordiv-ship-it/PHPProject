<?php
$pageTitle = "My Profile";
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Check if user is logged in
if (!isLoggedIn()) {
    $_SESSION['error'] = "You must be logged in to view your profile.";
    redirect('login.php');
}

// Get user information
$user = getUserById($_SESSION['user_id']);
$college = $user['college_id'] ? getCollegeById($user['college_id']) : null;

// Process profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $_SESSION['error'] = "Invalid request";
        redirect('profile.php');
    }
    
    $action = $_POST['action'];
    
    if ($action === 'update_profile') {
        // Update email
        $email = sanitizeInput($_POST['email']);
        
        // Validate email
        if (empty($email)) {
            $_SESSION['error'] = "Email is required.";
        } elseif (!isValidEmail($email)) {
            $_SESSION['error'] = "Please enter a valid email address.";
        } else {
            // Check if email already exists (excluding current user)
            $emailCheck = fetchOne("SELECT id FROM users WHERE email = ? AND id != ?", [$email, $user['id']]);
            
            if ($emailCheck) {
                $_SESSION['error'] = "Email is already in use by another account.";
            } else {
                // Update user profile
                $result = updateUserProfile($user['id'], ['email' => $email]);
                
                if ($result) {
                    $_SESSION['success'] = "Profile updated successfully.";
                } else {
                    $_SESSION['error'] = "Failed to update profile.";
                }
            }
        }
        
        redirect('profile.php');
    } elseif ($action === 'change_password') {
        // Change password
        $currentPassword = $_POST['current_password'];
        $newPassword = $_POST['new_password'];
        $confirmPassword = $_POST['confirm_password'];
        
        // Validate passwords
        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $_SESSION['error'] = "All password fields are required.";
        } elseif ($newPassword !== $confirmPassword) {
            $_SESSION['error'] = "New passwords do not match.";
        } elseif (strlen($newPassword) < 6) {
            $_SESSION['error'] = "New password must be at least 6 characters long.";
        } else {
            // Update password
            $result = updateUserPassword($user['id'], $currentPassword, $newPassword);
            
            if ($result) {
                $_SESSION['success'] = "Password changed successfully.";
            } else {
                $_SESSION['error'] = "Current password is incorrect.";
            }
        }
        
        redirect('profile.php');
    }
}

// Get user statistics
$ticketStats = [];

// Total tickets created
$sql = "SELECT COUNT(*) as count FROM tickets WHERE created_by = ?";
$result = fetchOne($sql, [$user['id']]);
$ticketStats['created'] = $result['count'];

// Open tickets
$sql = "SELECT COUNT(*) as count FROM tickets WHERE created_by = ? AND status = 'open'";
$result = fetchOne($sql, [$user['id']]);
$ticketStats['open'] = $result['count'];

// Resolved tickets
$sql = "SELECT COUNT(*) as count FROM tickets WHERE created_by = ? AND status IN ('resolved', 'closed')";
$result = fetchOne($sql, [$user['id']]);
$ticketStats['resolved'] = $result['count'];

// If staff member, get assigned tickets
if (in_array($user['role'], ['technical_staff', 'non_technical_staff'])) {
    $sql = "SELECT COUNT(*) as count FROM tickets WHERE assigned_to = ?";
    $result = fetchOne($sql, [$user['id']]);
    $ticketStats['assigned'] = $result['count'];
    
    $sql = "SELECT COUNT(*) as count FROM tickets WHERE assigned_to = ? AND status IN ('resolved', 'closed')";
    $result = fetchOne($sql, [$user['id']]);
    $ticketStats['resolved_by_user'] = $result['count'];
    
    // Get average resolution time
    $sql = "SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, resolved_at)) as avg_time 
            FROM tickets 
            WHERE assigned_to = ? AND status IN ('resolved', 'closed') AND resolved_at IS NOT NULL";
    $result = fetchOne($sql, [$user['id']]);
    $ticketStats['avg_resolution_time'] = $result['avg_time'] ? round($result['avg_time'], 1) : 0;
    
    // Get average rating
    $sql = "SELECT AVG(f.rating) as avg_rating
            FROM feedback f
            JOIN tickets t ON f.ticket_id = t.id
            WHERE t.assigned_to = ?";
    $result = fetchOne($sql, [$user['id']]);
    $ticketStats['avg_rating'] = $result['avg_rating'] ? round($result['avg_rating'], 1) : 0;
}

include 'includes/header.php';
?>

<div class="row">
    <div class="col-md-3">
        <?php include 'includes/sidebar.php'; ?>
    </div>
    <div class="col-md-9">
        <h1 class="mb-4"><i class="fas fa-user me-2"></i>My Profile</h1>
        
        <div class="row">
            <!-- Profile Information -->
            <div class="col-md-4">
                <div class="card profile-card mb-4">
                    <div class="card-body text-center">
                        <div class="avatar mb-3">
                            <i class="fas fa-user"></i>
                        </div>
                        
                        <h4><?php echo htmlspecialchars($user['username']); ?></h4>
                        <p class="text-muted"><?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?></p>
                        
                        <?php if ($college): ?>
                            <p class="text-muted"><?php echo htmlspecialchars($college['name']); ?></p>
                        <?php endif; ?>
                        
                        <p class="text-muted small">Member since <?php echo date('M d, Y', strtotime($user['created_at'])); ?></p>
                    </div>
                </div>
                
                <!-- User Statistics -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i>Statistics</h5>
                    </div>
                    <div class="card-body">
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Tickets Created
                                <span class="badge bg-primary rounded-pill"><?php echo $ticketStats['created']; ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Open Tickets
                                <span class="badge bg-warning rounded-pill"><?php echo $ticketStats['open']; ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Resolved Tickets
                                <span class="badge bg-success rounded-pill"><?php echo $ticketStats['resolved']; ?></span>
                            </li>
                            
                            <?php if (isset($ticketStats['assigned'])): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    Tickets Assigned
                                    <span class="badge bg-info rounded-pill"><?php echo $ticketStats['assigned']; ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    Tickets Resolved
                                    <span class="badge bg-success rounded-pill"><?php echo $ticketStats['resolved_by_user']; ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    Avg. Resolution Time
                                    <span class="badge bg-secondary rounded-pill"><?php echo $ticketStats['avg_resolution_time']; ?> hours</span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    Avg. Rating
                                    <span class="badge bg-warning rounded-pill">
                                        <?php echo $ticketStats['avg_rating']; ?>/5
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="fas fa-star <?php echo ($i <= $ticketStats['avg_rating']) ? 'text-warning' : 'text-muted'; ?>" style="font-size: 0.7em;"></i>
                                        <?php endfor; ?>
                                    </span>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>
            
            <!-- Update Profile Forms -->
            <div class="col-md-8">
                <!-- Update Email -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-envelope me-2"></i>Update Email</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="profile.php">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <input type="hidden" name="action" value="update_profile">
                            
                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($user['email']); ?>" required>
                            </div>
                            
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Update Email
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Change Password -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-lock me-2"></i>Change Password</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="profile.php">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <input type="hidden" name="action" value="change_password">
                            
                            <div class="mb-3">
                                <label for="current_password" class="form-label">Current Password</label>
                                <input type="password" class="form-control" id="current_password" name="current_password" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="new_password" class="form-label">New Password</label>
                                <input type="password" class="form-control" id="password" name="new_password" required>
                                <div class="mt-1">
                                    <small>Strength: <span id="passwordStrength">Not entered</span></small>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirm New Password</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                <div class="text-danger small d-none" id="passwordMismatch">Passwords don't match</div>
                            </div>
                            
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-key me-2"></i>Change Password
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
