<?php
$pageTitle = "Create Ticket";
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Check if user is logged in
if (!isLoggedIn()) {
    $_SESSION['error'] = "You must be logged in to create a ticket.";
    redirect('login.php');
}

// Check if user has permission to create tickets
if (!hasRole(['employee', 'college_admin', 'super_admin'])) {
    $_SESSION['error'] = "You don't have permission to create tickets.";
    redirect('dashboard.php');
}

// Get user information
$user = getUserById($_SESSION['user_id']);

// Get colleges for super admin
$colleges = [];
if ($user['role'] === 'super_admin') {
    $colleges = fetchAll("SELECT * FROM colleges ORDER BY name");
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $_SESSION['error'] = "Invalid request";
        redirect('create_ticket.php');
    }
    
    // Get and sanitize input
    $title = sanitizeInput($_POST['title']);
    $description = sanitizeInput($_POST['description']);
    $category = sanitizeInput($_POST['category']);
    $priority = sanitizeInput($_POST['priority']);
    
    // Determine college ID based on user role
    if ($user['role'] === 'super_admin' && isset($_POST['college_id'])) {
        $collegeId = intval($_POST['college_id']);
    } else {
        $collegeId = $user['college_id'];
    }
    
    // Validate input
    $errors = [];
    
    if (empty($title)) {
        $errors[] = "Title is required";
    }
    
    if (empty($description)) {
        $errors[] = "Description is required";
    }
    
    if (empty($category) || !in_array($category, ['technical', 'non_technical'])) {
        $errors[] = "Valid category is required";
    }
    
    if (empty($priority) || !in_array($priority, ['low', 'medium', 'high', 'urgent'])) {
        $errors[] = "Valid priority is required";
    }
    
    if ($user['role'] === 'super_admin' && empty($collegeId)) {
        $errors[] = "Please select a college";
    }
    
    // If no errors, create ticket
    if (empty($errors)) {
        $ticketData = [
            'title' => $title,
            'description' => $description,
            'category' => $category,
            'priority' => $priority,
            'created_by' => $user['id'],
            'college_id' => $collegeId,
            'status' => 'open'
        ];
        
        $ticketId = insert('tickets', $ticketData);
        
        if ($ticketId) {
            // Log ticket creation in history
            logTicketHistory($ticketId, $user['id'], 'created', 'Ticket created');
            
            $_SESSION['success'] = "Ticket created successfully!";
            redirect('view_ticket.php?id=' . $ticketId);
        } else {
            $_SESSION['error'] = "Failed to create ticket. Please try again.";
        }
    } else {
        $_SESSION['error'] = implode("<br>", $errors);
    }
}

include 'includes/header.php';
?>

<div class="row">
    <div class="col-md-3">
        <?php include 'includes/sidebar.php'; ?>
    </div>
    <div class="col-md-9">
        <h1 class="mb-4"><i class="fas fa-plus-circle me-2"></i>Create New Ticket</h1>
        
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Ticket Information</h5>
            </div>
            <div class="card-body">
                <form id="ticketForm" method="POST" action="create_ticket.php">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    
                    <div class="alert alert-info">
                        <p class="mb-0"><strong>Note:</strong> Fields marked with * are required.</p>
                    </div>
                    
                    <div class="alert alert-danger d-none" id="validationMessage">
                        Please fill in all required fields correctly.
                    </div>
                    
                    <div class="mb-3">
                        <label for="title" class="form-label required-field">Title</label>
                        <input type="text" class="form-control" id="title" name="title" 
                               placeholder="Brief summary of the issue" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label required-field">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="5" 
                                  placeholder="Detailed description of the issue" required></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="category" class="form-label required-field">Category</label>
                                <select class="form-select" id="category" name="category" required>
                                    <option value="">Select Category</option>
                                    <option value="technical">Technical</option>
                                    <option value="non_technical">Non-Technical</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="priority" class="form-label required-field">Priority</label>
                                <select class="form-select" id="priority" name="priority" required>
                                    <option value="">Select Priority</option>
                                    <option value="low">Low - Non-urgent</option>
                                    <option value="medium">Medium - Small group impact</option>
                                    <option value="high">High - Large group impact</option>
                                    <option value="urgent">Urgent - Critical, immediate attention</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($user['role'] === 'super_admin' && !empty($colleges)): ?>
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
                    <?php endif; ?>
                    
                    <!-- Category-specific fields (hidden by default) -->
                    <div id="technicalFields" class="d-none border p-3 rounded mb-3 bg-light">
                        <h5 class="mb-3">Technical Details</h5>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="device_type" class="form-label">Device Type</label>
                                    <input type="text" class="form-control" id="device_type" 
                                           placeholder="e.g., Laptop, Printer, Network">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="location" class="form-label">Location</label>
                                    <input type="text" class="form-control" id="location" 
                                           placeholder="Where is the device located?">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div id="nonTechnicalFields" class="d-none border p-3 rounded mb-3 bg-light">
                        <h5 class="mb-3">Non-Technical Details</h5>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="department" class="form-label">Department</label>
                                    <input type="text" class="form-control" id="department" 
                                           placeholder="Related department">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="service_type" class="form-label">Service Type</label>
                                    <input type="text" class="form-control" id="service_type" 
                                           placeholder="e.g., Administrative, Facility">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="attachment" class="form-label">Attachment</label>
                        <input type="file" class="form-control" id="attachment" disabled>
                        <div class="form-text">Note: File attachments are disabled in this version.</div>
                        <div class="text-danger small d-none" id="fileError"></div>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="dashboard.php" class="btn btn-secondary">
                            <i class="fas fa-times me-2"></i>Cancel
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-plus-circle me-2"></i>Create Ticket
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
