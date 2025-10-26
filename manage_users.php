<?php
$pageTitle = "Manage Users";
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Check if user is logged in and has appropriate role
if (!isLoggedIn() || !hasRole(['super_admin', 'college_admin'])) {
    $_SESSION['error'] = "You don't have permission to access this page.";
    redirect('dashboard.php');
}

// Get user information
$user = getUserById($_SESSION['user_id']);

// Get college ID based on user role
$collegeId = null;
if ($user['role'] !== 'super_admin') {
    $collegeId = $user['college_id'];
}

// Process user deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_user') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $_SESSION['error'] = "Invalid request";
        redirect('manage_users.php');
    }
    
    $userId = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    
    // Cannot delete yourself
    if ($userId === $_SESSION['user_id']) {
        $_SESSION['error'] = "You cannot delete your own account.";
        redirect('manage_users.php');
    }
    
    // Get user to check permissions
    $userToDelete = getUserById($userId);
    
    // Check if user exists
    if (!$userToDelete) {
        $_SESSION['error'] = "User not found.";
        redirect('manage_users.php');
    }
    
    // College admin can only delete users in their college
    if ($user['role'] === 'college_admin' && $userToDelete['college_id'] !== $user['college_id']) {
        $_SESSION['error'] = "You don't have permission to delete this user.";
        redirect('manage_users.php');
    }
    
    // College admin cannot delete other college admins
    if ($user['role'] === 'college_admin' && $userToDelete['role'] === 'college_admin') {
        $_SESSION['error'] = "You don't have permission to delete another college admin.";
        redirect('manage_users.php');
    }
    
    // Super admin cannot be deleted except by another super admin
    if ($userToDelete['role'] === 'super_admin' && $user['role'] !== 'super_admin') {
        $_SESSION['error'] = "You don't have permission to delete a super admin.";
        redirect('manage_users.php');
    }
    
    // Delete user
    $result = delete('users', 'id', $userId);
    
    if ($result) {
        $_SESSION['success'] = "User deleted successfully.";
    } else {
        $_SESSION['error'] = "Failed to delete user.";
    }
    
    redirect('manage_users.php');
}

// Get users based on role
$sql = "SELECT u.*, c.name as college_name
        FROM users u
        LEFT JOIN colleges c ON u.college_id = c.id
        WHERE 1=1";

$params = [];

// Restrict to college for college admin
if ($user['role'] === 'college_admin') {
    $sql .= " AND u.college_id = ?";
    $params[] = $user['college_id'];
}

$sql .= " ORDER BY u.username";

$users = fetchAll($sql, $params);

// Get colleges for filter
$colleges = [];
if ($user['role'] === 'super_admin') {
    $colleges = fetchAll("SELECT * FROM colleges ORDER BY name");
}

include 'includes/header.php';
?>

<div class="row">
    <div class="col-md-3">
        <?php include 'includes/sidebar.php'; ?>
    </div>
    <div class="col-md-9">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="mb-0"><i class="fas fa-users me-2"></i>Manage Users</h1>
            
            <a href="register.php" class="btn btn-primary">
                <i class="fas fa-user-plus me-2"></i>Add New User
            </a>
        </div>
        
        <!-- Users Table -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-list me-2"></i>Users</h5>
                <span class="badge bg-primary"><?php echo count($users); ?> Users</span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($users)): ?>
                    <div class="alert alert-info m-3 mb-0">
                        No users found.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Username</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <?php if ($user['role'] === 'super_admin'): ?>
                                        <th>College</th>
                                    <?php endif; ?>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $userItem): ?>
                                    <tr>
                                        <td><?php echo $userItem['id']; ?></td>
                                        <td><?php echo htmlspecialchars($userItem['username']); ?></td>
                                        <td><?php echo htmlspecialchars($userItem['email']); ?></td>
                                        <td>
                                            <span class="badge 
                                                <?php 
                                                switch($userItem['role']) {
                                                    case 'super_admin':
                                                        echo 'bg-danger';
                                                        break;
                                                    case 'college_admin':
                                                        echo 'bg-warning';
                                                        break;
                                                    case 'technical_staff':
                                                        echo 'bg-info';
                                                        break;
                                                    case 'non_technical_staff':
                                                        echo 'bg-success';
                                                        break;
                                                    default:
                                                        echo 'bg-secondary';
                                                }
                                                ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $userItem['role'])); ?>
                                            </span>
                                        </td>
                                        <?php if ($user['role'] === 'super_admin'): ?>
                                            <td>
                                                <?php echo $userItem['college_name'] ? htmlspecialchars($userItem['college_name']) : 'N/A'; ?>
                                            </td>
                                        <?php endif; ?>
                                        <td><?php echo formatDate($userItem['created_at'], false); ?></td>
                                        <td>
                                            <?php if ($userItem['id'] !== $_SESSION['user_id']): ?>
                                                <form method="POST" action="manage_users.php" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this user? This action cannot be undone.');">
                                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                    <input type="hidden" name="action" value="delete_user">
                                                    <input type="hidden" name="user_id" value="<?php echo $userItem['id']; ?>">
                                                    
                                                    <button type="submit" class="btn btn-sm btn-danger" data-bs-toggle="tooltip" title="Delete User">
                                                        <i class="fas fa-trash-alt"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
