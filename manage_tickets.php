<?php
$pageTitle = "Manage Tickets";
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Check if user is logged in
if (!isLoggedIn()) {
    $_SESSION['error'] = "You must be logged in to manage tickets.";
    redirect('login.php');
}

// Get user information
$user = getUserById($_SESSION['user_id']);

// Initialize filters
$statusFilter = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';
$priorityFilter = isset($_GET['priority']) ? sanitizeInput($_GET['priority']) : '';
$categoryFilter = isset($_GET['category']) ? sanitizeInput($_GET['category']) : '';
$searchTerm = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';

// Get college ID based on user role
$collegeId = null;
if ($user['role'] !== 'super_admin') {
    $collegeId = $user['college_id'];
}

// Build SQL query with filters
$sql = "SELECT t.*, u.username as created_by_name, a.username as assigned_to_name, c.name as college_name
        FROM tickets t
        LEFT JOIN users u ON t.created_by = u.id
        LEFT JOIN users a ON t.assigned_to = a.id
        LEFT JOIN colleges c ON t.college_id = c.id
        WHERE 1=1";

$params = [];

// Add role-specific conditions
switch ($user['role']) {
    case 'super_admin':
        // Super admin can see all tickets
        break;
        
    case 'college_admin':
        // College admin can see tickets from their college
        $sql .= " AND t.college_id = ?";
        $params[] = $user['college_id'];
        break;
        
    case 'technical_staff':
        // Technical staff can see technical tickets assigned to them or unassigned in their college
        $sql .= " AND t.college_id = ? AND t.category = 'technical' AND (t.assigned_to = ? OR t.assigned_to IS NULL)";
        $params[] = $user['college_id'];
        $params[] = $user['id'];
        break;
        
    case 'non_technical_staff':
        // Non-technical staff can see non-technical tickets assigned to them or unassigned in their college
        $sql .= " AND t.college_id = ? AND t.category = 'non_technical' AND (t.assigned_to = ? OR t.assigned_to IS NULL)";
        $params[] = $user['college_id'];
        $params[] = $user['id'];
        break;
        
    case 'employee':
        // Employees can see tickets they created
        $sql .= " AND t.created_by = ?";
        $params[] = $user['id'];
        break;
}

// Add filters
if (!empty($statusFilter)) {
    $sql .= " AND t.status = ?";
    $params[] = $statusFilter;
}

if (!empty($priorityFilter)) {
    $sql .= " AND t.priority = ?";
    $params[] = $priorityFilter;
}

if (!empty($categoryFilter)) {
    $sql .= " AND t.category = ?";
    $params[] = $categoryFilter;
}

if (!empty($searchTerm)) {
    $sql .= " AND (t.title LIKE ? OR t.description LIKE ?)";
    $searchParam = "%{$searchTerm}%";
    $params[] = $searchParam;
    $params[] = $searchParam;
}

// Add sorting
$sql .= " ORDER BY CASE 
            WHEN t.priority = 'urgent' THEN 1
            WHEN t.priority = 'high' THEN 2
            WHEN t.priority = 'medium' THEN 3
            WHEN t.priority = 'low' THEN 4
          END, 
          CASE 
            WHEN t.status = 'open' THEN 1
            WHEN t.status = 'in_progress' THEN 2
            WHEN t.status = 'resolved' THEN 3
            WHEN t.status = 'closed' THEN 4
            WHEN t.status = 'rejected' THEN 5
          END,
          t.created_at DESC";

// Execute query
$tickets = fetchAll($sql, $params);

// Get colleges for filter (super admin only)
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
            <h1 class="mb-0"><i class="fas fa-ticket-alt me-2"></i>Manage Tickets</h1>
            
            <?php if (hasRole(['employee', 'college_admin', 'super_admin'])): ?>
                <a href="create_ticket.php" class="btn btn-primary">
                    <i class="fas fa-plus-circle me-2"></i>Create New Ticket
                </a>
            <?php endif; ?>
        </div>
        
        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filter Tickets</h5>
            </div>
            <div class="card-body">
                <form method="GET" action="manage_tickets.php" class="row g-3">
                    <div class="col-md-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="">All Statuses</option>
                            <option value="open" <?php echo $statusFilter === 'open' ? 'selected' : ''; ?>>Open</option>
                            <option value="in_progress" <?php echo $statusFilter === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                            <option value="resolved" <?php echo $statusFilter === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                            <option value="closed" <?php echo $statusFilter === 'closed' ? 'selected' : ''; ?>>Closed</option>
                            <option value="rejected" <?php echo $statusFilter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label for="priority" class="form-label">Priority</label>
                        <select class="form-select" id="priority" name="priority">
                            <option value="">All Priorities</option>
                            <option value="low" <?php echo $priorityFilter === 'low' ? 'selected' : ''; ?>>Low</option>
                            <option value="medium" <?php echo $priorityFilter === 'medium' ? 'selected' : ''; ?>>Medium</option>
                            <option value="high" <?php echo $priorityFilter === 'high' ? 'selected' : ''; ?>>High</option>
                            <option value="urgent" <?php echo $priorityFilter === 'urgent' ? 'selected' : ''; ?>>Urgent</option>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label for="category" class="form-label">Category</label>
                        <select class="form-select" id="category" name="category">
                            <option value="">All Categories</option>
                            <option value="technical" <?php echo $categoryFilter === 'technical' ? 'selected' : ''; ?>>Technical</option>
                            <option value="non_technical" <?php echo $categoryFilter === 'non_technical' ? 'selected' : ''; ?>>Non-Technical</option>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label for="search" class="form-label">Search</label>
                        <input type="text" class="form-control" id="search" name="search" placeholder="Search tickets..." value="<?php echo htmlspecialchars($searchTerm); ?>">
                    </div>
                    
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary me-2">
                            <i class="fas fa-search me-2"></i>Apply Filters
                        </button>
                        <a href="manage_tickets.php" class="btn btn-secondary">
                            <i class="fas fa-times me-2"></i>Clear Filters
                        </a>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Tickets Table -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-list me-2"></i>Tickets</h5>
                <span class="badge bg-primary"><?php echo count($tickets); ?> Tickets</span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($tickets)): ?>
                    <div class="alert alert-info m-3 mb-0">
                        No tickets found matching your criteria.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Title</th>
                                    <th>Category</th>
                                    <th>Priority</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <?php if ($user['role'] === 'super_admin'): ?>
                                        <th>College</th>
                                    <?php endif; ?>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tickets as $ticket): ?>
                                    <tr>
                                        <td><?php echo formatTicketId($ticket['id']); ?></td>
                                        <td>
                                            <a href="view_ticket.php?id=<?php echo $ticket['id']; ?>">
                                                <?php echo htmlspecialchars($ticket['title']); ?>
                                            </a>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $ticket['category'] === 'technical' ? 'bg-info' : 'bg-success'; ?>">
                                                <?php echo ucfirst($ticket['category']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo getPriorityBadge($ticket['priority']); ?></td>
                                        <td><?php echo getStatusBadge($ticket['status']); ?></td>
                                        <td><?php echo formatDate($ticket['created_at'], false); ?></td>
                                        <?php if ($user['role'] === 'super_admin'): ?>
                                            <td><?php echo htmlspecialchars($ticket['college_name']); ?></td>
                                        <?php endif; ?>
                                        <td>
                                            <a href="view_ticket.php?id=<?php echo $ticket['id']; ?>" class="btn btn-sm btn-primary" data-bs-toggle="tooltip" title="View Ticket">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            
                                            <?php if ($ticket['status'] === 'resolved' && $ticket['created_by'] === $_SESSION['user_id']): ?>
                                                <?php 
                                                $hasFeedback = getTicketFeedback($ticket['id']);
                                                if (!$hasFeedback):
                                                ?>
                                                    <a href="provide_feedback.php?id=<?php echo $ticket['id']; ?>" class="btn btn-sm btn-success" data-bs-toggle="tooltip" title="Provide Feedback">
                                                        <i class="fas fa-star"></i>
                                                    </a>
                                                <?php endif; ?>
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
