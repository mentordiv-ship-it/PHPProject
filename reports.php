<?php
$pageTitle = "Reports";
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Check if user is logged in and has super admin role
if (!isLoggedIn() || !hasRole('super_admin')) {
    $_SESSION['error'] = "You don't have permission to access this page.";
    redirect('dashboard.php');
}

// Process report parameters
$reportType = isset($_GET['type']) ? sanitizeInput($_GET['type']) : 'ticket_status';
$collegeId = isset($_GET['college_id']) ? intval($_GET['college_id']) : 0;
$dateFrom = isset($_GET['date_from']) ? sanitizeInput($_GET['date_from']) : date('Y-m-d', strtotime('-30 days'));
$dateTo = isset($_GET['date_to']) ? sanitizeInput($_GET['date_to']) : date('Y-m-d');

// Get all colleges
$colleges = fetchAll("SELECT * FROM colleges ORDER BY name");

// Initialize report data
$reportData = [];
$reportTitle = '';

// Generate report based on type
switch ($reportType) {
    case 'ticket_status':
        $reportTitle = 'Ticket Status Report';
        
        $sql = "SELECT 
                    status,
                    COUNT(*) as total,
                    ROUND(AVG(TIMESTAMPDIFF(HOUR, created_at, 
                        CASE 
                            WHEN status IN ('resolved', 'closed') AND resolved_at IS NOT NULL 
                            THEN resolved_at 
                            ELSE NOW() 
                        END
                    )), 1) as avg_resolution_time
                FROM tickets
                WHERE created_at BETWEEN ? AND ?";
        
        $params = [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'];
        
        if ($collegeId > 0) {
            $sql .= " AND college_id = ?";
            $params[] = $collegeId;
        }
        
        $sql .= " GROUP BY status";
        
        $reportData = fetchAll($sql, $params);
        break;
        
    case 'ticket_category':
        $reportTitle = 'Ticket Category Report';
        
        $sql = "SELECT 
                    category,
                    COUNT(*) as total,
                    ROUND(AVG(TIMESTAMPDIFF(HOUR, created_at, 
                        CASE 
                            WHEN status IN ('resolved', 'closed') AND resolved_at IS NOT NULL 
                            THEN resolved_at 
                            ELSE NOW() 
                        END
                    )), 1) as avg_resolution_time
                FROM tickets
                WHERE created_at BETWEEN ? AND ?";
        
        $params = [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'];
        
        if ($collegeId > 0) {
            $sql .= " AND college_id = ?";
            $params[] = $collegeId;
        }
        
        $sql .= " GROUP BY category";
        
        $reportData = fetchAll($sql, $params);
        break;
        
    case 'ticket_priority':
        $reportTitle = 'Ticket Priority Report';
        
        $sql = "SELECT 
                    priority,
                    COUNT(*) as total,
                    ROUND(AVG(TIMESTAMPDIFF(HOUR, created_at, 
                        CASE 
                            WHEN status IN ('resolved', 'closed') AND resolved_at IS NOT NULL 
                            THEN resolved_at 
                            ELSE NOW() 
                        END
                    )), 1) as avg_resolution_time
                FROM tickets
                WHERE created_at BETWEEN ? AND ?";
        
        $params = [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'];
        
        if ($collegeId > 0) {
            $sql .= " AND college_id = ?";
            $params[] = $collegeId;
        }
        
        $sql .= " GROUP BY priority";
        
        $reportData = fetchAll($sql, $params);
        break;
        
    case 'college_performance':
        $reportTitle = 'College Performance Report';
        
        $sql = "SELECT 
                    c.id as college_id,
                    c.name as college_name,
                    COUNT(t.id) as total_tickets,
                    SUM(CASE WHEN t.status = 'resolved' OR t.status = 'closed' THEN 1 ELSE 0 END) as resolved_tickets,
                    ROUND(SUM(CASE WHEN t.status = 'resolved' OR t.status = 'closed' THEN 1 ELSE 0 END) / COUNT(t.id) * 100, 1) as resolution_rate,
                    ROUND(AVG(CASE WHEN t.status IN ('resolved', 'closed') AND t.resolved_at IS NOT NULL 
                              THEN TIMESTAMPDIFF(HOUR, t.created_at, t.resolved_at)
                              ELSE NULL END), 1) as avg_resolution_time
                FROM colleges c
                LEFT JOIN tickets t ON c.id = t.college_id AND t.created_at BETWEEN ? AND ?";
        
        $params = [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'];
        
        if ($collegeId > 0) {
            $sql .= " WHERE c.id = ?";
            $params[] = $collegeId;
        }
        
        $sql .= " GROUP BY c.id, c.name
                  ORDER BY resolution_rate DESC";
        
        $reportData = fetchAll($sql, $params);
        break;
        
    case 'feedback_analysis':
        $reportTitle = 'Feedback Analysis Report';
        
        $sql = "SELECT 
                    f.rating,
                    COUNT(*) as total,
                    ROUND(AVG(TIMESTAMPDIFF(HOUR, t.created_at, t.resolved_at)), 1) as avg_resolution_time
                FROM feedback f
                JOIN tickets t ON f.ticket_id = t.id
                WHERE t.created_at BETWEEN ? AND ?";
        
        $params = [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'];
        
        if ($collegeId > 0) {
            $sql .= " AND t.college_id = ?";
            $params[] = $collegeId;
        }
        
        $sql .= " GROUP BY f.rating
                  ORDER BY f.rating DESC";
        
        $reportData = fetchAll($sql, $params);
        break;
        
    case 'staff_performance':
        $reportTitle = 'Staff Performance Report';
        
        $sql = "SELECT 
                    u.id as user_id,
                    u.username,
                    u.role,
                    c.name as college_name,
                    COUNT(t.id) as assigned_tickets,
                    SUM(CASE WHEN t.status = 'resolved' OR t.status = 'closed' THEN 1 ELSE 0 END) as resolved_tickets,
                    ROUND(SUM(CASE WHEN t.status = 'resolved' OR t.status = 'closed' THEN 1 ELSE 0 END) / COUNT(t.id) * 100, 1) as resolution_rate,
                    ROUND(AVG(CASE WHEN t.status IN ('resolved', 'closed') AND t.resolved_at IS NOT NULL 
                              THEN TIMESTAMPDIFF(HOUR, t.created_at, t.resolved_at)
                              ELSE NULL END), 1) as avg_resolution_time,
                    (SELECT AVG(f.rating)
                     FROM feedback f
                     JOIN tickets ft ON f.ticket_id = ft.id
                     WHERE ft.assigned_to = u.id) as avg_rating
                FROM users u
                LEFT JOIN colleges c ON u.college_id = c.id
                LEFT JOIN tickets t ON u.id = t.assigned_to AND t.created_at BETWEEN ? AND ?
                WHERE u.role IN ('technical_staff', 'non_technical_staff')";
        
        $params = [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'];
        
        if ($collegeId > 0) {
            $sql .= " AND u.college_id = ?";
            $params[] = $collegeId;
        }
        
        $sql .= " GROUP BY u.id, u.username, u.role, c.name
                  HAVING COUNT(t.id) > 0
                  ORDER BY resolution_rate DESC, avg_resolution_time ASC";
        
        $reportData = fetchAll($sql, $params);
        break;
}

include 'includes/header.php';
?>

<div class="row">
    <div class="col-md-3">
        <?php include 'includes/sidebar.php'; ?>
    </div>
    <div class="col-md-9">
        <h1 class="mb-4"><i class="fas fa-chart-bar me-2"></i>Reports</h1>
        
        <!-- Report Parameters -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Report Parameters</h5>
            </div>
            <div class="card-body">
                <form method="GET" action="reports.php" class="row g-3">
                    <div class="col-md-3">
                        <label for="type" class="form-label">Report Type</label>
                        <select class="form-select" id="type" name="type" required>
                            <option value="ticket_status" <?php echo $reportType === 'ticket_status' ? 'selected' : ''; ?>>Ticket Status Report</option>
                            <option value="ticket_category" <?php echo $reportType === 'ticket_category' ? 'selected' : ''; ?>>Ticket Category Report</option>
                            <option value="ticket_priority" <?php echo $reportType === 'ticket_priority' ? 'selected' : ''; ?>>Ticket Priority Report</option>
                            <option value="college_performance" <?php echo $reportType === 'college_performance' ? 'selected' : ''; ?>>College Performance Report</option>
                            <option value="feedback_analysis" <?php echo $reportType === 'feedback_analysis' ? 'selected' : ''; ?>>Feedback Analysis Report</option>
                            <option value="staff_performance" <?php echo $reportType === 'staff_performance' ? 'selected' : ''; ?>>Staff Performance Report</option>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label for="college_id" class="form-label">College</label>
                        <select class="form-select" id="college_id" name="college_id">
                            <option value="0">All Colleges</option>
                            <?php foreach ($colleges as $college): ?>
                                <option value="<?php echo $college['id']; ?>" <?php echo $collegeId === intval($college['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($college['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label for="date_from" class="form-label">Date From</label>
                        <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo $dateFrom; ?>" required>
                    </div>
                    
                    <div class="col-md-3">
                        <label for="date_to" class="form-label">Date To</label>
                        <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo $dateTo; ?>" required>
                    </div>
                    
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-sync-alt me-2"></i>Generate Report
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Report Results -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-file-alt me-2"></i><?php echo $reportTitle; ?></h5>
                <div>
                    <span class="badge bg-primary">
                        <?php echo $collegeId > 0 ? htmlspecialchars(getCollegeById($collegeId)['name']) : 'All Colleges'; ?>
                    </span>
                    <span class="badge bg-secondary ms-2">
                        <?php echo date('M d, Y', strtotime($dateFrom)); ?> - <?php echo date('M d, Y', strtotime($dateTo)); ?>
                    </span>
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($reportData)): ?>
                    <div class="alert alert-info">
                        No data available for the selected parameters.
                    </div>
                <?php else: ?>
                    <?php if ($reportType === 'ticket_status'): ?>
                        <div class="row">
                            <div class="col-md-8">
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Status</th>
                                                <th>Total Tickets</th>
                                                <th>Percentage</th>
                                                <th>Avg. Resolution Time (hours)</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $totalTickets = array_sum(array_column($reportData, 'total'));
                                            foreach ($reportData as $row): 
                                            ?>
                                                <tr>
                                                    <td><?php echo ucfirst(str_replace('_', ' ', $row['status'])); ?></td>
                                                    <td><?php echo $row['total']; ?></td>
                                                    <td><?php echo round(($row['total'] / $totalTickets) * 100, 1); ?>%</td>
                                                    <td><?php echo $row['avg_resolution_time'] ? $row['avg_resolution_time'] : 'N/A'; ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                        <tfoot>
                                            <tr>
                                                <th>Total</th>
                                                <th><?php echo $totalTickets; ?></th>
                                                <th>100%</th>
                                                <th></th>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <canvas id="statusChart" height="200"></canvas>
                                </div>
                            </div>
                        </div>
                        
                    <?php elseif ($reportType === 'ticket_category'): ?>
                        <div class="row">
                            <div class="col-md-8">
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Category</th>
                                                <th>Total Tickets</th>
                                                <th>Percentage</th>
                                                <th>Avg. Resolution Time (hours)</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $totalTickets = array_sum(array_column($reportData, 'total'));
                                            foreach ($reportData as $row): 
                                            ?>
                                                <tr>
                                                    <td><?php echo ucfirst(str_replace('_', ' ', $row['category'])); ?></td>
                                                    <td><?php echo $row['total']; ?></td>
                                                    <td><?php echo round(($row['total'] / $totalTickets) * 100, 1); ?>%</td>
                                                    <td><?php echo $row['avg_resolution_time'] ? $row['avg_resolution_time'] : 'N/A'; ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                        <tfoot>
                                            <tr>
                                                <th>Total</th>
                                                <th><?php echo $totalTickets; ?></th>
                                                <th>100%</th>
                                                <th></th>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <canvas id="categoryChart" height="200"></canvas>
                                </div>
                            </div>
                        </div>
                        
                    <?php elseif ($reportType === 'ticket_priority'): ?>
                        <div class="row">
                            <div class="col-md-8">
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Priority</th>
                                                <th>Total Tickets</th>
                                                <th>Percentage</th>
                                                <th>Avg. Resolution Time (hours)</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $totalTickets = array_sum(array_column($reportData, 'total'));
                                            foreach ($reportData as $row): 
                                            ?>
                                                <tr>
                                                    <td><?php echo ucfirst($row['priority']); ?></td>
                                                    <td><?php echo $row['total']; ?></td>
                                                    <td><?php echo round(($row['total'] / $totalTickets) * 100, 1); ?>%</td>
                                                    <td><?php echo $row['avg_resolution_time'] ? $row['avg_resolution_time'] : 'N/A'; ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                        <tfoot>
                                            <tr>
                                                <th>Total</th>
                                                <th><?php echo $totalTickets; ?></th>
                                                <th>100%</th>
                                                <th></th>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <canvas id="priorityChart" height="200"></canvas>
                                </div>
                            </div>
                        </div>
                        
                    <?php elseif ($reportType === 'college_performance'): ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>College</th>
                                        <th>Total Tickets</th>
                                        <th>Resolved Tickets</th>
                                        <th>Resolution Rate</th>
                                        <th>Avg. Resolution Time (hours)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($reportData as $row): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($row['college_name']); ?></td>
                                            <td><?php echo $row['total_tickets']; ?></td>
                                            <td><?php echo $row['resolved_tickets']; ?></td>
                                            <td><?php echo $row['resolution_rate']; ?>%</td>
                                            <td><?php echo $row['avg_resolution_time'] ? $row['avg_resolution_time'] : 'N/A'; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-4">
                            <canvas id="collegePerformanceChart" height="200"></canvas>
                        </div>
                        
                    <?php elseif ($reportType === 'feedback_analysis'): ?>
                        <div class="row">
                            <div class="col-md-8">
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Rating</th>
                                                <th>Total Tickets</th>
                                                <th>Percentage</th>
                                                <th>Avg. Resolution Time (hours)</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $totalTickets = array_sum(array_column($reportData, 'total'));
                                            foreach ($reportData as $row): 
                                            ?>
                                                <tr>
                                                    <td><?php echo $row['rating']; ?> star<?php echo $row['rating'] > 1 ? 's' : ''; ?></td>
                                                    <td><?php echo $row['total']; ?></td>
                                                    <td><?php echo round(($row['total'] / $totalTickets) * 100, 1); ?>%</td>
                                                    <td><?php echo $row['avg_resolution_time'] ? $row['avg_resolution_time'] : 'N/A'; ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                        <tfoot>
                                            <tr>
                                                <th>Total</th>
                                                <th><?php echo $totalTickets; ?></th>
                                                <th>100%</th>
                                                <th></th>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <canvas id="feedbackChart" height="200"></canvas>
                                </div>
                            </div>
                        </div>
                        
                    <?php elseif ($reportType === 'staff_performance'): ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Staff Member</th>
                                        <th>Role</th>
                                        <th>College</th>
                                        <th>Assigned Tickets</th>
                                        <th>Resolved Tickets</th>
                                        <th>Resolution Rate</th>
                                        <th>Avg. Resolution Time (hours)</th>
                                        <th>Avg. Rating</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($reportData as $row): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($row['username']); ?></td>
                                            <td><?php echo ucfirst(str_replace('_', ' ', $row['role'])); ?></td>
                                            <td><?php echo htmlspecialchars($row['college_name']); ?></td>
                                            <td><?php echo $row['assigned_tickets']; ?></td>
                                            <td><?php echo $row['resolved_tickets']; ?></td>
                                            <td><?php echo $row['resolution_rate']; ?>%</td>
                                            <td><?php echo $row['avg_resolution_time'] ? $row['avg_resolution_time'] : 'N/A'; ?></td>
                                            <td>
                                                <?php if ($row['avg_rating']): ?>
                                                    <?php echo round($row['avg_rating'], 1); ?> / 5
                                                <?php else: ?>
                                                    N/A
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php if (!empty($reportData)): ?>
        <?php if ($reportType === 'ticket_status'): ?>
            // Status Chart
            var statusCtx = document.getElementById('statusChart').getContext('2d');
            var statusChart = new Chart(statusCtx, {
                type: 'pie',
                data: {
                    labels: [
                        <?php foreach ($reportData as $row): ?>
                            '<?php echo ucfirst(str_replace('_', ' ', $row['status'])); ?>',
                        <?php endforeach; ?>
                    ],
                    datasets: [{
                        data: [
                            <?php foreach ($reportData as $row): ?>
                                <?php echo $row['total']; ?>,
                            <?php endforeach; ?>
                        ],
                        backgroundColor: [
                            '#6c757d',
                            '#007bff',
                            '#28a745',
                            '#343a40',
                            '#dc3545'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });
            
        <?php elseif ($reportType === 'ticket_category'): ?>
            // Category Chart
            var categoryCtx = document.getElementById('categoryChart').getContext('2d');
            var categoryChart = new Chart(categoryCtx, {
                type: 'pie',
                data: {
                    labels: [
                        <?php foreach ($reportData as $row): ?>
                            '<?php echo ucfirst(str_replace('_', ' ', $row['category'])); ?>',
                        <?php endforeach; ?>
                    ],
                    datasets: [{
                        data: [
                            <?php foreach ($reportData as $row): ?>
                                <?php echo $row['total']; ?>,
                            <?php endforeach; ?>
                        ],
                        backgroundColor: [
                            '#007bff',
                            '#28a745'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });
            
        <?php elseif ($reportType === 'ticket_priority'): ?>
            // Priority Chart
            var priorityCtx = document.getElementById('priorityChart').getContext('2d');
            var priorityChart = new Chart(priorityCtx, {
                type: 'pie',
                data: {
                    labels: [
                        <?php foreach ($reportData as $row): ?>
                            '<?php echo ucfirst($row['priority']); ?>',
                        <?php endforeach; ?>
                    ],
                    datasets: [{
                        data: [
                            <?php foreach ($reportData as $row): ?>
                                <?php echo $row['total']; ?>,
                            <?php endforeach; ?>
                        ],
                        backgroundColor: [
                            '#17a2b8',
                            '#ffc107',
                            '#dc3545',
                            '#6610f2'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });
            
        <?php elseif ($reportType === 'college_performance'): ?>
            // College Performance Chart
            var collegeCtx = document.getElementById('collegePerformanceChart').getContext('2d');
            var collegeChart = new Chart(collegeCtx, {
                type: 'bar',
                data: {
                    labels: [
                        <?php foreach ($reportData as $row): ?>
                            '<?php echo $row['college_name']; ?>',
                        <?php endforeach; ?>
                    ],
                    datasets: [{
                        label: 'Resolution Rate (%)',
                        data: [
                            <?php foreach ($reportData as $row): ?>
                                <?php echo $row['resolution_rate']; ?>,
                            <?php endforeach; ?>
                        ],
                        backgroundColor: '#007bff'
                    },
                    {
                        label: 'Avg. Resolution Time (hours)',
                        data: [
                            <?php foreach ($reportData as $row): ?>
                                <?php echo $row['avg_resolution_time'] ? $row['avg_resolution_time'] : 0; ?>,
                            <?php endforeach; ?>
                        ],
                        backgroundColor: '#28a745'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
            
        <?php elseif ($reportType === 'feedback_analysis'): ?>
            // Feedback Chart
            var feedbackCtx = document.getElementById('feedbackChart').getContext('2d');
            var feedbackChart = new Chart(feedbackCtx, {
                type: 'bar',
                data: {
                    labels: [
                        <?php foreach ($reportData as $row): ?>
                            '<?php echo $row['rating']; ?> star<?php echo $row['rating'] > 1 ? 's' : ''; ?>',
                        <?php endforeach; ?>
                    ],
                    datasets: [{
                        label: 'Number of Ratings',
                        data: [
                            <?php foreach ($reportData as $row): ?>
                                <?php echo $row['total']; ?>,
                            <?php endforeach; ?>
                        ],
                        backgroundColor: '#ffc107'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        <?php endif; ?>
    <?php endif; ?>
});
</script>

<?php include 'includes/footer.php'; ?>
