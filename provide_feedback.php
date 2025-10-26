<?php
$pageTitle = "Provide Feedback";
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Check if user is logged in
if (!isLoggedIn()) {
    $_SESSION['error'] = "You must be logged in to provide feedback.";
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

// Check if user created this ticket
if ($ticket['created_by'] !== $_SESSION['user_id']) {
    $_SESSION['error'] = "You can only provide feedback for tickets you created.";
    redirect('manage_tickets.php');
}

// Check if ticket is resolved
if ($ticket['status'] !== 'resolved') {
    $_SESSION['error'] = "You can only provide feedback for resolved tickets.";
    redirect('view_ticket.php?id=' . $ticketId);
}

// Check if feedback already exists
$existingFeedback = getTicketFeedback($ticketId);
if ($existingFeedback) {
    $_SESSION['error'] = "Feedback has already been provided for this ticket.";
    redirect('view_ticket.php?id=' . $ticketId);
}

// Process feedback submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $_SESSION['error'] = "Invalid request";
        redirect('provide_feedback.php?id=' . $ticketId);
    }
    
    // Get and sanitize input
    $rating = isset($_POST['rating']) ? intval($_POST['rating']) : 0;
    $comments = isset($_POST['comments']) ? sanitizeInput($_POST['comments']) : '';
    
    // Validate rating
    if ($rating < 1 || $rating > 5) {
        $_SESSION['error'] = "Please provide a rating between 1 and 5.";
        redirect('provide_feedback.php?id=' . $ticketId);
    }
    
    // Save feedback
    $feedbackData = [
        'ticket_id' => $ticketId,
        'rating' => $rating,
        'comments' => $comments
    ];
    
    $feedbackId = insert('feedback', $feedbackData);
    
    if ($feedbackId) {
        // Update ticket status to closed
        update('tickets', ['status' => 'closed'], 'id', $ticketId);
        
        // Log ticket history
        logTicketHistory($ticketId, $_SESSION['user_id'], 'provided feedback and closed ticket', 'Rating: ' . $rating . '/5');
        
        $_SESSION['success'] = "Thank you for your feedback! The ticket has been closed.";
        redirect('view_ticket.php?id=' . $ticketId);
    } else {
        $_SESSION['error'] = "Failed to save feedback. Please try again.";
        redirect('provide_feedback.php?id=' . $ticketId);
    }
}

// Get assigned staff member
$assignedStaff = $ticket['assigned_to'] ? getUserById($ticket['assigned_to']) : null;

include 'includes/header.php';
?>

<div class="row">
    <div class="col-md-3">
        <?php include 'includes/sidebar.php'; ?>
    </div>
    <div class="col-md-9">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="mb-0"><i class="fas fa-star me-2"></i>Provide Feedback</h1>
            
            <a href="view_ticket.php?id=<?php echo $ticketId; ?>" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back to Ticket
            </a>
        </div>
        
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Ticket Summary</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-8">
                        <h4><?php echo htmlspecialchars($ticket['title']); ?></h4>
                        <p class="text-muted">
                            <strong>ID:</strong> <?php echo formatTicketId($ticket['id']); ?> |
                            <strong>Status:</strong> <?php echo getStatusBadge($ticket['status']); ?> |
                            <strong>Created:</strong> <?php echo formatDate($ticket['created_at'], false); ?>
                        </p>
                        
                        <?php if ($assignedStaff): ?>
                            <p>
                                <strong>Resolved by:</strong> <?php echo htmlspecialchars($assignedStaff['username']); ?>
                                (<?php echo ucfirst(str_replace('_', ' ', $assignedStaff['role'])); ?>)
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Your Feedback</h5>
            </div>
            <div class="card-body">
                <p class="mb-4">Your feedback helps us improve our service. Please rate your experience with this ticket.</p>
                
                <form method="POST" action="provide_feedback.php?id=<?php echo $ticketId; ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="rating" id="rating" value="0">
                    
                    <div class="mb-4">
                        <label class="form-label">Rating</label>
                        <div class="rating-stars mb-2">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="far fa-star rating-star" data-value="<?php echo $i; ?>"></i>
                            <?php endfor; ?>
                        </div>
                        <div class="text-muted small">Click on a star to rate</div>
                    </div>
                    
                    <div class="mb-4">
                        <label for="comments" class="form-label">Comments (Optional)</label>
                        <textarea class="form-control" id="comments" name="comments" rows="4" placeholder="Please provide any additional feedback about your experience..."></textarea>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="view_ticket.php?id=<?php echo $ticketId; ?>" class="btn btn-secondary">
                            <i class="fas fa-times me-2"></i>Cancel
                        </a>
                        <button type="submit" class="btn btn-primary" id="submitFeedback" disabled>
                            <i class="fas fa-paper-plane me-2"></i>Submit Feedback
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const stars = document.querySelectorAll('.rating-star');
    const ratingInput = document.getElementById('rating');
    const submitButton = document.getElementById('submitFeedback');
    
    stars.forEach(function(star) {
        star.addEventListener('click', function() {
            const rating = parseInt(this.getAttribute('data-value'));
            ratingInput.value = rating;
            
            // Update star display
            stars.forEach(function(s, index) {
                if (index < rating) {
                    s.classList.remove('far');
                    s.classList.add('fas');
                } else {
                    s.classList.remove('fas');
                    s.classList.add('far');
                }
            });
            
            // Enable submit button
            submitButton.disabled = false;
        });
        
        star.addEventListener('mouseover', function() {
            const rating = parseInt(this.getAttribute('data-value'));
            
            // Temporarily update star display on hover
            stars.forEach(function(s, index) {
                if (index < rating) {
                    s.classList.remove('far');
                    s.classList.add('fas');
                } else {
                    s.classList.remove('fas');
                    s.classList.add('far');
                }
            });
        });
        
        star.addEventListener('mouseout', function() {
            const currentRating = parseInt(ratingInput.value) || 0;
            
            // Reset star display based on actual rating
            stars.forEach(function(s, index) {
                if (index < currentRating) {
                    s.classList.remove('far');
                    s.classList.add('fas');
                } else {
                    s.classList.remove('fas');
                    s.classList.add('far');
                }
            });
        });
    });
});
</script>

<?php include 'includes/footer.php'; ?>
