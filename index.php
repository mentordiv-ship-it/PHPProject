<?php
$pageTitle = "Home";
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Redirect to dashboard if already logged in
if (isLoggedIn()) {
    redirect('dashboard.php');
}

include 'includes/header.php';
?>

<div class="row">
    <div class="col-md-6 offset-md-3 text-center">
        <div class="mt-5 mb-4">
            <h1 class="display-4"><?php echo APP_NAME; ?></h1>
            <p class="lead">A comprehensive solution for managing technical and non-technical support tickets across college campuses.</p>
        </div>
        
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h2 class="h4 mb-3">Welcome to the Ticket Management System</h2>
                <p>This system allows you to:</p>
                <ul class="list-group list-group-flush mb-4">
                    <li class="list-group-item text-start"><i class="fas fa-ticket-alt text-primary me-2"></i> Create and track support tickets</li>
                    <li class="list-group-item text-start"><i class="fas fa-tasks text-primary me-2"></i> Manage ticket status and priority</li>
                    <li class="list-group-item text-start"><i class="fas fa-comments text-primary me-2"></i> Provide feedback on resolved issues</li>
                    <li class="list-group-item text-start"><i class="fas fa-chart-line text-primary me-2"></i> Generate reports and analytics</li>
                </ul>
                
                <div class="d-grid gap-2">
                    <a href="login.php" class="btn btn-primary btn-lg">
                        <i class="fas fa-sign-in-alt me-2"></i> Log In
                    </a>
                    <div class="text-center mt-3">
                        <span class="text-muted">Don't have an account?</span>
                        <a href="register.php" class="ms-2">Register Now</a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="mt-5">
            <div class="row">
                <div class="col-md-4">
                    <div class="card mb-3">
                        <div class="card-body text-center p-4">
                            <div class="mb-3">
                                <i class="fas fa-headset display-5 text-primary"></i>
                            </div>
                            <h5>Technical Support</h5>
                            <p class="text-muted">Get help with hardware, software, and network issues</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card mb-3">
                        <div class="card-body text-center p-4">
                            <div class="mb-3">
                                <i class="fas fa-clipboard-list display-5 text-success"></i>
                            </div>
                            <h5>Non-Technical Support</h5>
                            <p class="text-muted">Request assistance with administrative or general issues</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card mb-3">
                        <div class="card-body text-center p-4">
                            <div class="mb-3">
                                <i class="fas fa-chart-bar display-5 text-info"></i>
                            </div>
                            <h5>Analytics & Reports</h5>
                            <p class="text-muted">Track performance metrics and generate insights</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
