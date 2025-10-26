<?php
$pageTitle = "Dashboard";
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Check if user is logged in
if (!isLoggedIn()) {
    $_SESSION['error'] = "You must be logged in to view the dashboard.";
    redirect('login.php');
}

// Get user information
$user = getUserById($_SESSION['user_id']);

// Get dashboard statistics based on user role and college
$collegeId = null;
if ($user['role'] !== 'super_admin') {
    $collegeId = $user['college_id'];
}

$stats = getDashboardStats($collegeId);

// Get recent tickets based on user role
$recentTickets = getUserTickets($user['id'], 5);

// Get tickets by status for user's college
$openTickets = getTicketsByStatus('open', $collegeId, 5);
$inProgressTickets = getTicketsByStatus('in_progress', $collegeId, 5);

// Prepare chart data
$statusData = [
    'open' => $stats['open_tickets'],
    'in_progress' => $stats['in_progress_tickets'],
    'resolved' => $stats['resolved_tickets'],
    'closed' => $stats['closed_tickets']
];

$categoryData = [
    'technical' => $stats['technical_tickets'],
    'non_technical' => $stats['non_technical_tickets']
];

include 'includes/header.php';
?>

<div class="row">
    <div class="col-md-3">
        <?php include 'includes/sidebar.php'; ?>
    </div>
    <div class="col-md-9">
        <h1 class="mb-4"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</h1>
        
        <!-- Welcome Message -->
        <div class="alert alert-info mb-4">
            <h4 class="alert-heading">Welcome, <?php echo htmlspecialchars($user['username']); ?>!</h4>
            <p class="mb-0">You are logged in as <strong><?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?></strong>
            <?php if (isset($_SESSION['college_name']) && $_SESSION['college_name']): ?>
                for <strong><?php echo $_SESSION['college_name']; ?></strong>
            <?php endif; ?>
            </p>
        </div>
        
        <!-- Statistics Cards -->
        <div class="row">
            <div class="col-md-3">
                <div class="card stats-card mb-4 border-primary">
                    <div class="card-body">
                        <div class="stats-icon text-primary">
                            <i class="fas fa-ticket-alt"></i>
                        </div>
                        <div class="stats-number"><?php echo $stats['total_tickets']; ?></div>
                        <div class="stats-text">Total Tickets</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card mb-4 border-secondary">
                    <div class="card-body">
                        <div class="stats-icon text-secondary">
                            <i class="fas fa-hourglass-half"></i>
                        </div>
                        <div class="stats-number"><?php echo $stats['open_tickets']; ?></div>
                        <div class="stats-text">Open Tickets</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card mb-4 border-primary">
                    <div class="card-body">
                        <div class="stats-icon text-primary">
                            <i class="fas fa-spinner"></i>
                        </div>
                        <div class="stats-number"><?php echo $stats['in_progress_tickets']; ?></div>
                        <div class="stats-text">In Progress</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card mb-4 border-success">
                    <div class="card-body">
                        <div class="stats-icon text-success">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stats-number"><?php echo $stats['resolved_tickets']; ?></div>
                        <div class="stats-text">Resolved</div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Charts and Tables -->
        <div class="row">
            <!-- Left Column: Charts -->
            <div class="col-md-5">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Ticket Status Distribution</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="ticketStatusChart" height="200" data-status='<?php echo json_encode($statusData); ?>'></canvas>
                    </div>
                </div>
                
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Ticket Categories</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="ticketCategoryChart" height="200" data-category='<?php echo json_encode($categoryData); ?>'></canvas>
                    </div>
                </div>
            </div>
            
            <!-- Right Column: Recent Tickets & Quick Actions -->
            <div class="col-md-7">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-list me-2"></i>Recent Tickets</h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($recentTickets)): ?>
                            <div class="alert alert-info m-3 mb-0">
                                No tickets found.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Title</th>
                                            <th>Status</th>
                                            <th>Priority</th>
                                            <th>Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentTickets as $ticket): ?>
                                            <tr>
                                                <td><?php echo formatTicketId($ticket['id']); ?></td>
                                                <td>
                                                    <a href="view_ticket.php?id=<?php echo $ticket['id']; ?>">
                                                        <?php echo htmlspecialchars($ticket['title']); ?>
                                                    </a>
                                                </td>
                                                <td><?php echo getStatusBadge($ticket['status']); ?></td>
                                                <td><?php echo getPriorityBadge($ticket['priority']); ?></td>
                                                <td><?php echo formatDate($ticket['created_at']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer">
                        <a href="manage_tickets.php" class="btn btn-sm btn-primary">View All Tickets</a>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-bolt me-2"></i>Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php if (hasRole(['employee', 'college_admin', 'super_admin'])): ?>
                                <div class="col-md-4 mb-3">
                                    <a href="create_ticket.php" class="btn btn-outline-primary w-100 py-2">
                                        <i class="fas fa-plus-circle mb-2 d-block fs-4"></i>
                                        Create Ticket
                                    </a>
                                </div>
                            <?php endif; ?>
                            
                            <div class="col-md-4 mb-3">
                                <a href="manage_tickets.php?status=open" class="btn btn-outline-secondary w-100 py-2">
                                    <i class="fas fa-clipboard-list mb-2 d-block fs-4"></i>
                                    View Open Tickets
                                </a>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <a href="manage_tickets.php?status=in_progress" class="btn btn-outline-primary w-100 py-2">
                                    <i class="fas fa-spinner mb-2 d-block fs-4"></i>
                                    In Progress Tickets
                                </a>
                            </div>
                            
                            <?php if (hasRole(['college_admin', 'super_admin'])): ?>
                                <div class="col-md-4 mb-3">
                                    <a href="manage_users.php" class="btn btn-outline-info w-100 py-2">
                                        <i class="fas fa-users mb-2 d-block fs-4"></i>
                                        Manage Users
                                    </a>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (hasRole('super_admin')): ?>
                                <div class="col-md-4 mb-3">
                                    <a href="manage_colleges.php" class="btn btn-outline-dark w-100 py-2">
                                        <i class="fas fa-university mb-2 d-block fs-4"></i>
                                        Manage Colleges
                                    </a>
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <a href="reports.php" class="btn btn-outline-success w-100 py-2">
                                        <i class="fas fa-chart-bar mb-2 d-block fs-4"></i>
                                        View Reports
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Ticket Summary Section -->
        <?php if (hasRole(['technical_staff', 'non_technical_staff', 'college_admin'])): ?>
        <div class="row">
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-clipboard-list me-2"></i>Open Tickets</h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($openTickets)): ?>
                            <div class="alert alert-info m-3 mb-0">
                                No open tickets found.
                            </div>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($openTickets as $ticket): ?>
                                    <a href="view_ticket.php?id=<?php echo $ticket['id']; ?>" class="list-group-item list-group-item-action">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($ticket['title']); ?></h6>
                                            <small><?php echo formatDate($ticket['created_at'], false); ?></small>
                                        </div>
                                        <p class="mb-1 text-muted small"><?php echo substr(htmlspecialchars($ticket['description']), 0, 100) . '...'; ?></p>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <small>
                                                By: <?php echo htmlspecialchars($ticket['created_by_name']); ?>
                                            </small>
                                            <span>
                                                <?php echo getPriorityBadge($ticket['priority']); ?>
                                            </span>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-spinner me-2"></i>In Progress Tickets</h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($inProgressTickets)): ?>
                            <div class="alert alert-info m-3 mb-0">
                                No in-progress tickets found.
                            </div>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($inProgressTickets as $ticket): ?>
                                    <a href="view_ticket.php?id=<?php echo $ticket['id']; ?>" class="list-group-item list-group-item-action">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($ticket['title']); ?></h6>
                                            <small><?php echo formatDate($ticket['created_at'], false); ?></small>
                                        </div>
                                        <p class="mb-1 text-muted small"><?php echo substr(htmlspecialchars($ticket['description']), 0, 100) . '...'; ?></p>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <small>
                                                Assigned to: <?php echo htmlspecialchars($ticket['assigned_to_name'] ?? 'Unassigned'); ?>
                                            </small>
                                            <span>
                                                <?php echo getPriorityBadge($ticket['priority']); ?>
                                            </span>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<?php include 'includes/footer.php'; ?>
