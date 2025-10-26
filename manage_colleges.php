<?php
$pageTitle = "Manage Colleges";
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Check if user is logged in and has super admin role
if (!isLoggedIn() || !hasRole('super_admin')) {
    $_SESSION['error'] = "You don't have permission to access this page.";
    redirect('dashboard.php');
}

// Process college actions (add, edit, delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $_SESSION['error'] = "Invalid request";
        redirect('manage_colleges.php');
    }
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_college') {
        // Add new college
        $name = sanitizeInput($_POST['name']);
        $address = sanitizeInput($_POST['address']);
        
        if (empty($name) || empty($address)) {
            $_SESSION['error'] = "College name and address are required.";
        } else {
            $collegeData = [
                'name' => $name,
                'address' => $address
            ];
            
            $collegeId = insert('colleges', $collegeData);
            
            if ($collegeId) {
                $_SESSION['success'] = "College added successfully.";
            } else {
                $_SESSION['error'] = "Failed to add college.";
            }
        }
        
        redirect('manage_colleges.php');
    } elseif ($action === 'edit_college') {
        // Edit existing college
        $collegeId = isset($_POST['college_id']) ? intval($_POST['college_id']) : 0;
        $name = sanitizeInput($_POST['name']);
        $address = sanitizeInput($_POST['address']);
        
        if (empty($collegeId) || empty($name) || empty($address)) {
            $_SESSION['error'] = "College ID, name, and address are required.";
        } else {
            $collegeData = [
                'name' => $name,
                'address' => $address
            ];
            
            $result = update('colleges', $collegeData, 'id', $collegeId);
            
            if ($result) {
                $_SESSION['success'] = "College updated successfully.";
            } else {
                $_SESSION['error'] = "Failed to update college.";
            }
        }
        
        redirect('manage_colleges.php');
    } elseif ($action === 'delete_college') {
        // Delete college
        $collegeId = isset($_POST['college_id']) ? intval($_POST['college_id']) : 0;
        
        if (empty($collegeId)) {
            $_SESSION['error'] = "College ID is required.";
        } else {
            // Check if college has associated users or tickets
            $usersCount = fetchOne("SELECT COUNT(*) as count FROM users WHERE college_id = ?", [$collegeId]);
            $ticketsCount = fetchOne("SELECT COUNT(*) as count FROM tickets WHERE college_id = ?", [$collegeId]);
            
            if ($usersCount['count'] > 0 || $ticketsCount['count'] > 0) {
                $_SESSION['error'] = "Cannot delete college because it has associated users or tickets.";
            } else {
                $result = delete('colleges', 'id', $collegeId);
                
                if ($result) {
                    $_SESSION['success'] = "College deleted successfully.";
                } else {
                    $_SESSION['error'] = "Failed to delete college.";
                }
            }
        }
        
        redirect('manage_colleges.php');
    }
}

// Get all colleges
$colleges = fetchAll("SELECT c.*, 
                    (SELECT COUNT(*) FROM users WHERE college_id = c.id) as user_count,
                    (SELECT COUNT(*) FROM tickets WHERE college_id = c.id) as ticket_count
                    FROM colleges c
                    ORDER BY c.name");

// Get college by ID for editing
$editCollegeId = isset($_GET['edit']) ? intval($_GET['edit']) : 0;
$editCollege = null;

if ($editCollegeId > 0) {
    $editCollege = getCollegeById($editCollegeId);
}

include 'includes/header.php';
?>

<div class="row">
    <div class="col-md-3">
        <?php include 'includes/sidebar.php'; ?>
    </div>
    <div class="col-md-9">
        <h1 class="mb-4"><i class="fas fa-university me-2"></i>Manage Colleges</h1>
        
        <div class="row">
            <!-- College Form -->
            <div class="col-md-4">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <?php echo $editCollege ? 'Edit College' : 'Add New College'; ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="manage_colleges.php">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <input type="hidden" name="action" value="<?php echo $editCollege ? 'edit_college' : 'add_college'; ?>">
                            
                            <?php if ($editCollege): ?>
                                <input type="hidden" name="college_id" value="<?php echo $editCollege['id']; ?>">
                            <?php endif; ?>
                            
                            <div class="mb-3">
                                <label for="name" class="form-label required-field">College Name</label>
                                <input type="text" class="form-control" id="name" name="name" 
                                       value="<?php echo $editCollege ? htmlspecialchars($editCollege['name']) : ''; ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="address" class="form-label required-field">Address</label>
                                <textarea class="form-control" id="address" name="address" rows="3" required><?php echo $editCollege ? htmlspecialchars($editCollege['address']) : ''; ?></textarea>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>
                                    <?php echo $editCollege ? 'Update College' : 'Add College'; ?>
                                </button>
                                
                                <?php if ($editCollege): ?>
                                    <a href="manage_colleges.php" class="btn btn-secondary mt-2">
                                        <i class="fas fa-times me-2"></i>Cancel Editing
                                    </a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Colleges Table -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-list me-2"></i>Colleges</h5>
                        <span class="badge bg-primary"><?php echo count($colleges); ?> Colleges</span>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($colleges)): ?>
                            <div class="alert alert-info m-3 mb-0">
                                No colleges found.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Name</th>
                                            <th>Address</th>
                                            <th>Users</th>
                                            <th>Tickets</th>
                                            <th>Created</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($colleges as $college): ?>
                                            <tr>
                                                <td><?php echo $college['id']; ?></td>
                                                <td><?php echo htmlspecialchars($college['name']); ?></td>
                                                <td><?php echo htmlspecialchars(substr($college['address'], 0, 30) . (strlen($college['address']) > 30 ? '...' : '')); ?></td>
                                                <td><?php echo $college['user_count']; ?></td>
                                                <td><?php echo $college['ticket_count']; ?></td>
                                                <td><?php echo formatDate($college['created_at'], false); ?></td>
                                                <td>
                                                    <a href="manage_colleges.php?edit=<?php echo $college['id']; ?>" class="btn btn-sm btn-primary" data-bs-toggle="tooltip" title="Edit College">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    
                                                    <?php if ($college['user_count'] == 0 && $college['ticket_count'] == 0): ?>
                                                        <form method="POST" action="manage_colleges.php" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this college? This action cannot be undone.');">
                                                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                            <input type="hidden" name="action" value="delete_college">
                                                            <input type="hidden" name="college_id" value="<?php echo $college['id']; ?>">
                                                            
                                                            <button type="submit" class="btn btn-sm btn-danger" data-bs-toggle="tooltip" title="Delete College">
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
    </div>
</div>

<?php include 'includes/footer.php'; ?>
