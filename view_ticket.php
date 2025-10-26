<?php
$pageTitle = "View Ticket";
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Check if user is logged in
if (!isLoggedIn()) {
    $_SESSION['error'] = "You must be logged in to view tickets.";
    redirect('login.php');
}

// Check if ticket ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "Invalid ticket ID.";
    redirect('manage_tickets.php');
}

$ticketId = intval($_GET['id']);
$ticket = getTicketById($ticketId);

// Check if ticket exists
if (!$ticket) {
    $_SESSION['error'] = "Ticket not found.";
    redirect('manage_tickets.php');
}

// Check if user has permission to view this ticket
if (!canViewTicket($_SESSION['user_id'], $ticket)) {
    $_SESSION['error'] = "You don't have permission to view this ticket.";
    redirect('manage_tickets.php');
}

// Get additional ticket information
$createdBy = getUserById($ticket['created_by']);
$assignedTo = $ticket['assigned_to'] ? getUserById($ticket['assigned_to']) : null;
$college = getCollegeById($ticket['college_id']);
$ticketHistory = getTicketHistory($ticketId);
$feedback = getTicketFeedback($ticketId);

// Process status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $_SESSION['error'] = "Invalid request";
        redirect('view_ticket.php?id=' . $ticketId);
    }
    
    $action = $_POST['action'];
    
    if ($action === 'update_status') {
        $newStatus = sanitizeInput($_POST['status']);
        $comment = sanitizeInput($_POST['comment'] ?? '');
        
        // Validate new status
        if (empty($newStatus) || !in_array($newStatus, ['open', 'in_progress', 'resolved', 'closed', 'rejected'])) {
            $_SESSION['error'] = "Invalid status.";
            redirect('view_ticket.php?id=' . $ticketId);
        }
        
        // Update ticket status
        $data = ['status' => $newStatus];
        
        // If resolving, set resolved_at timestamp
        if ($newStatus === 'resolved' && $ticket['status'] !== 'resolved') {
            $data['resolved_at'] = date('Y-m-d H:i:s');
        }
        
        $result = update('tickets', $data, 'id', $ticketId);
        
        if ($result) {
            // Log ticket history
            $action = "changed status to " . str_replace('_', ' ', $newStatus);
            logTicketHistory($ticketId, $_SESSION['user_id'], $action, $comment);
            
            $_SESSION['success'] = "Ticket status updated successfully.";
        } else {
            $_SESSION['error'] = "Failed to update ticket status.";
        }
        
        redirect('view_ticket.php?id=' . $ticketId);
    } elseif ($action === 'assign_ticket') {
        $assignedTo = isset($_POST['assigned_to']) ? intval($_POST['assigned_to']) : null;
        $comment = sanitizeInput($_POST['comment'] ?? '');
        
        // Validate assigned to
        if (empty($assignedTo)) {
            $_SESSION['error'] = "Please select a staff member to assign the ticket to.";
            redirect('view_ticket.php?id=' . $ticketId);
        }
        
        // Update ticket assignment
        $data = ['assigned_to' => $assignedTo];
        
        // If ticket is open, change to in_progress
        if ($ticket['status'] === 'open') {
            $data['status'] = 'in_progress';
        }
        
        $result = update('tickets', $data, 'id', $ticketId);
        
        if ($result) {
            // Get staff member name
            $staff = getUserById($assignedTo);
            
            // Log ticket history
            $actionText = "assigned ticket to " . $staff['username'];
            logTicketHistory($ticketId, $_SESSION['user_id'], $actionText, $comment);
            
            $_SESSION['success'] = "Ticket assigned successfully.";
        } else {
            $_SESSION['error'] = "Failed to assign ticket.";
        }
        
        redirect('view_ticket.php?id=' . $ticketId);
    }
}

// Get staff for assignment
$staff = [];
if (hasRole(['college_admin', 'super_admin']) && in_array($ticket['status'], ['open', 'in_progress'])) {
    $staff = getStaffForAssignment($ticket['category'], $ticket['college_id']);
}

include 'includes/header.php';
?>

<div class="row">
    <div class="col-md-3">
        <?php include 'includes/sidebar.php'; ?>
    </div>
    <div class="col-md-9">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="mb-0">
                <i class="fas fa-ticket-alt me-2"></i>
                <?php echo formatTicketId($ticket['id']); ?>
            </h1>
            <div>
                <a href="manage_tickets.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Back to Tickets
                </a>
                
                <?php if ($ticket['status'] === 'resolved' && $ticket['created_by'] === $_SESSION['user_id'] && !$feedback): ?>
                    <a href="provide_feedback.php?id=<?php echo $ticket['id']; ?>" class="btn btn-primary ms-2">
                        <i class="fas fa-star me-2"></i>Provide Feedback
                    </a>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Ticket Details -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Ticket Details</h5>
                <div>
                    <?php echo getStatusBadge($ticket['status']); ?>
                    <?php echo getPriorityBadge($ticket['priority']); ?>
                </div>
            </div>
            <div class="card-body ticket-details">
                <div class="row mb-3">
                    <div class="col-md-8">
                        <h4><?php echo htmlspecialchars($ticket['title']); ?></h4>
                    </div>
                    <div class="col-md-4 text-md-end">
                        <span class="text-muted"><?php echo formatDate($ticket['created_at']); ?></span>
                    </div>
                </div>
                
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="border p-3 rounded bg-light">
                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($ticket['description'])); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <p><span class="label">Created By:</span> <?php echo htmlspecialchars($createdBy['username']); ?></p>
                        <p><span class="label">Category:</span> <?php echo ucfirst($ticket['category']); ?></p>
                        <p><span class="label">Priority:</span> <?php echo ucfirst($ticket['priority']); ?></p>
                    </div>
                    <div class="col-md-6">
                        <p>
                            <span class="label">Assigned To:</span> 
                            <?php echo $assignedTo ? htmlspecialchars($assignedTo['username']) : 'Not Assigned'; ?>
                        </p>
                        <p><span class="label">College:</span> <?php echo htmlspecialchars($college['name']); ?></p>
                        <p>
                            <span class="label">Resolved At:</span> 
                            <?php echo $ticket['resolved_at'] ? formatDate($ticket['resolved_at']) : 'Not Resolved'; ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Ticket Actions -->
        <?php if (in_array($ticket['status'], ['open', 'in_progress']) && hasRole(['college_admin', 'technical_staff', 'non_technical_staff', 'super_admin'])): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Ticket Actions</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <!-- Update Status -->
                        <div class="col-md-6">
                            <form method="POST" action="view_ticket.php?id=<?php echo $ticketId; ?>">
                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                <input type="hidden" name="action" value="update_status">
                                
                                <div class="mb-3">
                                    <label for="status" class="form-label">Update Status</label>
                                    <select class="form-select" id="status" name="status" required>
                                        <option value="">Select Status</option>
                                        <?php if ($ticket['status'] === 'open'): ?>
                                            <option value="in_progress">In Progress</option>
                                        <?php endif; ?>
                                        
                                        <?php if (in_array($ticket['status'], ['open', 'in_progress'])): ?>
                                            <option value="resolved">Resolved</option>
                                            <option value="rejected">Rejected</option>
                                        <?php endif; ?>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="comment" class="form-label">Comment (Optional)</label>
                                    <textarea class="form-control" id="comment" name="comment" rows="3"></textarea>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Update Status
                                </button>
                            </form>
                        </div>
                        
                        <!-- Assign Ticket -->
                        <?php if (hasRole(['college_admin', 'super_admin']) && !empty($staff)): ?>
                            <div class="col-md-6">
                                <form method="POST" action="view_ticket.php?id=<?php echo $ticketId; ?>">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                    <input type="hidden" name="action" value="assign_ticket">
                                    
                                    <div class="mb-3">
                                        <label for="assigned_to" class="form-label">Assign To</label>
                                        <select class="form-select" id="assigned_to" name="assigned_to" required>
                                            <option value="">Select Staff Member</option>
                                            <?php foreach ($staff as $member): ?>
                                                <option value="<?php echo $member['id']; ?>" <?php echo ($ticket['assigned_to'] == $member['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($member['username']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="assignComment" class="form-label">Comment (Optional)</label>
                                        <textarea class="form-control" id="assignComment" name="comment" rows="3"></textarea>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-success">
                                        <i class="fas fa-user-check me-2"></i>Assign Ticket
                                    </button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Feedback Section -->
        <?php if ($feedback): ?>
            <div class="card mb-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-star me-2"></i>Feedback</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <h5>Rating: <?php echo $feedback['rating']; ?>/5</h5>
                            <div class="rating-stars">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="fas fa-star <?php echo ($i <= $feedback['rating']) ? 'text-warning' : 'text-muted'; ?>"></i>
                                <?php endfor; ?>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <?php if (!empty($feedback['comments'])): ?>
                                <h5>Comments:</h5>
                                <p><?php echo nl2br(htmlspecialchars($feedback['comments'])); ?></p>
                            <?php else: ?>
                                <p class="text-muted">No comments provided</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Ticket History -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-history me-2"></i>Ticket History</h5>
            </div>
            <div class="card-body">
                <?php if (empty($ticketHistory)): ?>
                    <div class="alert alert-info">
                        No history found for this ticket.
                    </div>
                <?php else: ?>
                    <div class="ticket-timeline">
                        <?php foreach ($ticketHistory as $history): ?>
                            <div class="timeline-item">
                                <div class="d-flex">
                                    <div class="flex-grow-1">
                                        <p class="mb-1">
                                            <strong><?php echo htmlspecialchars($history['username']); ?></strong> 
                                            <?php echo htmlspecialchars($history['action']); ?>
                                        </p>
                                        <?php if (!empty($history['details'])): ?>
                                            <p class="mb-1">
                                                <em>"<?php echo htmlspecialchars($history['details']); ?>"</em>
                                            </p>
                                        <?php endif; ?>
                                        <p class="timeline-date mb-0">
                                            <?php echo formatDate($history['action_time']); ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
