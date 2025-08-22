<?php
// admin/violations.php - Violations Management
require_once '../config/database.php';
require_once '../includes/session.php';
require_once '../includes/functions.php';

// Require admin access
requireAdmin();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && isset($_POST['violation_id'])) {
        $violationId = (int)$_POST['violation_id'];
        $action = $_POST['action'];
        
        try {
            switch ($action) {
                case 'resolve':
                    $actionTaken = sanitize($_POST['action_taken']);
                    $adminNotes = sanitize($_POST['admin_notes'] ?? '');
                    
                    $stmt = $pdo->prepare("
                        UPDATE violations 
                        SET status = 'resolved', 
                            action_taken = ?, 
                            admin_notes = ?,
                            resolved_at = NOW() 
                        WHERE violation_id = ?
                    ");
                    $stmt->execute([$actionTaken, $adminNotes, $violationId]);
                    
                    // If action is account disable, update user status
                    if ($actionTaken === 'account_disabled') {
                        $stmt = $pdo->prepare("
                            SELECT reported_user_id FROM violations WHERE violation_id = ?
                        ");
                        $stmt->execute([$violationId]);
                        $reportedUserId = $stmt->fetch()['reported_user_id'];
                        
                        $stmt = $pdo->prepare("UPDATE users SET status = 'disabled' WHERE user_id = ?");
                        $stmt->execute([$reportedUserId]);
                    }
                    
                    logActivity(getUserId(), 'violation_resolved', "Resolved violation ID: $violationId with action: $actionTaken");
                    setFlashMessage('success', 'Violation resolved successfully!');
                    break;
                    
                case 'dismiss':
                    $adminNotes = sanitize($_POST['admin_notes'] ?? '');
                    
                    $stmt = $pdo->prepare("
                        UPDATE violations 
                        SET status = 'resolved', 
                            action_taken = 'dismissed',
                            admin_notes = ?,
                            resolved_at = NOW() 
                        WHERE violation_id = ?
                    ");
                    $stmt->execute([$adminNotes, $violationId]);
                    
                    logActivity(getUserId(), 'violation_dismissed', "Dismissed violation ID: $violationId");
                    setFlashMessage('success', 'Violation dismissed.');
                    break;
            }
        } catch (PDOException $e) {
            setFlashMessage('error', 'Database error: ' . $e->getMessage());
        }
        
        header('Location: violations.php');
        exit();
    }
    
    // Handle new violation report
    if (isset($_POST['report_violation'])) {
        $reportedUserId = (int)$_POST['reported_user_id'];
        $violationType = sanitize($_POST['violation_type']);
        $description = sanitize($_POST['description']);
        
        try {
            addViolation($reportedUserId, getUserId(), $violationType, $description);
            setFlashMessage('success', 'Violation reported successfully!');
        } catch (Exception $e) {
            setFlashMessage('error', 'Failed to report violation.');
        }
        
        header('Location: violations.php');
        exit();
    }
}

// Get filter parameters
$statusFilter = $_GET['status'] ?? 'pending';
$traderId = $_GET['trader_id'] ?? null;

// Build query
$whereConditions = [];
$params = [];

if ($statusFilter !== 'all') {
    $whereConditions[] = "v.status = ?";
    $params[] = $statusFilter;
}

if ($traderId) {
    $whereConditions[] = "v.reported_user_id = ?";
    $params[] = $traderId;
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

try {
    // Get violations
    $stmt = $pdo->prepare("
        SELECT v.*, 
               u1.username as reported_username, u1.full_name as reported_name, u1.status as reported_status,
               u2.username as reporter_username, u2.full_name as reporter_name,
               s.shop_name
        FROM violations v
        JOIN users u1 ON v.reported_user_id = u1.user_id
        JOIN users u2 ON v.reporter_id = u2.user_id
        LEFT JOIN shops s ON u1.user_id = s.trader_id
        $whereClause
        ORDER BY 
            CASE WHEN v.status = 'pending' THEN 0 ELSE 1 END,
            v.created_at DESC
    ");
    $stmt->execute($params);
    $violations = $stmt->fetchAll();
    
    // Get status counts
    $stmt = $pdo->prepare("SELECT status, COUNT(*) as count FROM violations GROUP BY status");
    $stmt->execute();
    $statusCounts = ['pending' => 0, 'resolved' => 0];
    while ($row = $stmt->fetch()) {
        $statusCounts[$row['status']] = $row['count'];
    }
    
    // Get all traders for reporting form
    $stmt = $pdo->prepare("
        SELECT u.user_id, u.username, u.full_name, s.shop_name 
        FROM users u 
        LEFT JOIN shops s ON u.user_id = s.trader_id 
        WHERE u.role = 'trader' AND u.status = 'active'
        ORDER BY u.full_name
    ");
    $stmt->execute();
    $traders = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error = "Failed to load violations: " . $e->getMessage();
}

$violationTypes = [
    'fraud' => 'Fraud/Scam',
    'fake_products' => 'Fake/Counterfeit Products',
    'poor_quality' => 'Poor Product Quality',
    'non_delivery' => 'Non-delivery of Products',
    'inappropriate_content' => 'Inappropriate Content',
    'spam' => 'Spam/Harassment',
    'terms_violation' => 'Terms of Service Violation',
    'other' => 'Other'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Violations Management - Admin</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/admin.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="admin-body">
    <!-- Admin Sidebar -->
    <nav class="admin-sidebar">
        <div class="sidebar-header">
            <h2><i class="fas fa-shield-alt"></i> Admin Panel</h2>
        </div>
        
        <ul class="sidebar-menu">
            <li><a href="index.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="traders.php"><i class="fas fa-store"></i> Traders</a></li>
            <li class="active"><a href="violations.php"><i class="fas fa-exclamation-triangle"></i> Violations</a></li>
            <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
            <li><a href="users.php"><i class="fas fa-users"></i> Users</a></li>
            <li><a href="products.php"><i class="fas fa-box"></i> Products</a></li>
            <li><a href="orders.php"><i class="fas fa-shopping-bag"></i> Orders</a></li>
        </ul>
        
        <div class="sidebar-footer">
            <a href="../index.php" class="btn-secondary"><i class="fas fa-home"></i> Back to Site</a>
            <a href="../auth/logout.php" class="btn-danger"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="admin-main">
        <div class="admin-topbar">
            <div class="topbar-left">
                <h1>Violations Management</h1>
                <p>Handle violation reports and maintain platform integrity</p>
            </div>
            <div class="topbar-right">
                <button class="btn-primary" onclick="showReportModal()">
                    <i class="fas fa-plus"></i> Report Violation
                </button>
            </div>
        </div>

        <div class="admin-content">
            <?php if (isset($error)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($message = getFlashMessage('success')): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <?php if ($message = getFlashMessage('error')): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <!-- Filter Tabs -->
            <div class="admin-filters">
                <div class="filter-tabs">
                    <a href="?status=pending" class="filter-tab <?php echo $statusFilter === 'pending' ? 'active' : ''; ?>">
                        <i class="fas fa-clock"></i> Pending (<?php echo $statusCounts['pending']; ?>)
                    </a>
                    <a href="?status=resolved" class="filter-tab <?php echo $statusFilter === 'resolved' ? 'active' : ''; ?>">
                        <i class="fas fa-check"></i> Resolved (<?php echo $statusCounts['resolved']; ?>)
                    </a>
                    <a href="?status=all" class="filter-tab <?php echo $statusFilter === 'all' ? 'active' : ''; ?>">
                        <i class="fas fa-list"></i> All (<?php echo array_sum($statusCounts); ?>)
                    </a>
                </div>
            </div>

            <!-- Violations List -->
            <?php if (empty($violations)): ?>
                <div class="empty-state">
                    <i class="fas fa-shield-alt"></i>
                    <h3>
                        <?php if ($statusFilter === 'pending'): ?>
                            No pending violations
                        <?php else: ?>
                            No violations found
                        <?php endif; ?>
                    </h3>
                    <p>
                        <?php if ($statusFilter === 'pending'): ?>
                            Great! No violations need your attention right now.
                        <?php else: ?>
                            The platform is running smoothly with no violation reports.
                        <?php endif; ?>
                    </p>
                </div>
            <?php else: ?>
                <div class="violations-container">
                    <?php foreach ($violations as $violation): ?>
                        <div class="violation-card <?php echo $violation['status']; ?>">
                            <div class="violation-header">
                                <div class="violation-type">
                                    <span class="type-badge type-<?php echo $violation['violation_type']; ?>">
                                        <?php echo $violationTypes[$violation['violation_type']] ?? ucfirst($violation['violation_type']); ?>
                                    </span>
                                    <span class="violation-date"><?php echo formatDateTime($violation['created_at']); ?></span>
                                </div>
                                <div class="violation-status">
                                    <span class="status-badge status-<?php echo $violation['status']; ?>">
                                        <?php if ($violation['status'] === 'pending'): ?>
                                            <i class="fas fa-clock"></i> Pending Review
                                        <?php else: ?>
                                            <i class="fas fa-check"></i> Resolved
                                        <?php endif; ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="violation-body">
                                <div class="violation-parties">
                                    <div class="reported-user">
                                        <h4>Reported User:</h4>
                                        <div class="user-info">
                                            <span class="user-name"><?php echo htmlspecialchars($violation['reported_name']); ?></span>
                                            <span class="username">@<?php echo htmlspecialchars($violation['reported_username']); ?></span>
                                            <?php if ($violation['shop_name']): ?>
                                                <span class="shop-name"><?php echo htmlspecialchars($violation['shop_name']); ?></span>
                                            <?php endif; ?>
                                            <span class="user-status status-<?php echo $violation['reported_status']; ?>">
                                                <?php echo ucfirst($violation['reported_status']); ?>
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <div class="reporter-user">
                                        <h4>Reported By:</h4>
                                        <div class="user-info">
                                            <span class="user-name"><?php echo htmlspecialchars($violation['reporter_name']); ?></span>
                                            <span class="username">@<?php echo htmlspecialchars($violation['reporter_username']); ?></span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="violation-description">
                                    <h4>Description:</h4>
                                    <p><?php echo nl2br(htmlspecialchars($violation['description'])); ?></p>
                                </div>
                                
                                <?php if ($violation['status'] === 'resolved'): ?>
                                    <div class="violation-resolution">
                                        <h4>Resolution:</h4>
                                        <div class="resolution-details">
                                            <span class="action-taken">
                                                Action: <strong><?php echo ucfirst(str_replace('_', ' ', $violation['action_taken'])); ?></strong>
                                            </span>
                                            <?php if ($violation['resolved_at']): ?>
                                                <span class="resolved-date">
                                                    Resolved: <?php echo formatDateTime($violation['resolved_at']); ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($violation['admin_notes']): ?>
                                            <div class="admin-notes">
                                                <strong>Admin Notes:</strong>
                                                <p><?php echo nl2br(htmlspecialchars($violation['admin_notes'])); ?></p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($violation['status'] === 'pending'): ?>
                                <div class="violation-actions">
                                    <button class="btn-warning" onclick="resolveViolation(<?php echo $violation['violation_id']; ?>, 'warning')">
                                        <i class="fas fa-exclamation-triangle"></i> Issue Warning
                                    </button>
                                    <button class="btn-danger" onclick="resolveViolation(<?php echo $violation['violation_id']; ?>, 'account_disabled')">
                                        <i class="fas fa-ban"></i> Disable Account
                                    </button>
                                    <button class="btn-secondary" onclick="dismissViolation(<?php echo $violation['violation_id']; ?>)">
                                        <i class="fas fa-times"></i> Dismiss
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Report Violation Modal -->
    <div id="reportModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Report Violation</h3>
                <button class="modal-close" onclick="closeModal('reportModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <input type="hidden" name="report_violation" value="1">
                    
                    <div class="form-group">
                        <label for="reported_user_id">Select Trader:</label>
                        <select name="reported_user_id" id="reported_user_id" required>
                            <option value="">Choose a trader...</option>
                            <?php foreach ($traders as $trader): ?>
                                <option value="<?php echo $trader['user_id']; ?>">
                                    <?php echo htmlspecialchars($trader['full_name']); ?> 
                                    (@<?php echo htmlspecialchars($trader['username']); ?>)
                                    <?php if ($trader['shop_name']): ?>
                                        - <?php echo htmlspecialchars($trader['shop_name']); ?>
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="violation_type">Violation Type:</label>
                        <select name="violation_type" id="violation_type" required>
                            <option value="">Select violation type...</option>
                            <?php foreach ($violationTypes as $key => $label): ?>
                                <option value="<?php echo $key; ?>"><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Description:</label>
                        <textarea name="description" id="description" rows="4" required 
                                placeholder="Provide detailed information about the violation..."></textarea>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-flag"></i> Report Violation
                        </button>
                        <button type="button" class="btn-secondary" onclick="closeModal('reportModal')">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Resolve Violation Modal -->
    <div id="resolveModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Resolve Violation</h3>
                <button class="modal-close" onclick="closeModal('resolveModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" id="resolveForm">
                    <input type="hidden" name="action" value="resolve">
                    <input type="hidden" name="violation_id" id="resolveViolationId">
                    <input type="hidden" name="action_taken" id="resolveActionTaken">
                    
                    <div class="action-summary" id="actionSummary"></div>
                    
                    <div class="form-group">
                        <label for="admin_notes">Admin Notes:</label>
                        <textarea name="admin_notes" id="admin_notes" rows="3" 
                                placeholder="Optional notes about the resolution..."></textarea>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn-primary" id="resolveButton">
                            <i class="fas fa-gavel"></i> Confirm Resolution
                        </button>
                        <button type="button" class="btn-secondary" onclick="closeModal('resolveModal')">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Dismiss Violation Modal -->
    <div id="dismissModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Dismiss Violation</h3>
                <button class="modal-close" onclick="closeModal('dismissModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" id="dismissForm">
                    <input type="hidden" name="action" value="dismiss">
                    <input type="hidden" name="violation_id" id="dismissViolationId">
                    
                    <div class="alert alert-warning">
                        <i class="fas fa-info-circle"></i>
                        You are about to dismiss this violation report. This action means no further action will be taken.
                    </div>
                    
                    <div class="form-group">
                        <label for="dismiss_admin_notes">Reason for Dismissal:</label>
                        <textarea name="admin_notes" id="dismiss_admin_notes" rows="3" 
                                placeholder="Explain why this violation is being dismissed..."></textarea>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn-warning">
                            <i class="fas fa-times"></i> Dismiss Violation
                        </button>
                        <button type="button" class="btn-secondary" onclick="closeModal('dismissModal')">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Modal Functions
        function showReportModal() {
            document.getElementById('reportModal').style.display = 'block';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        function resolveViolation(violationId, actionType) {
            document.getElementById('resolveViolationId').value = violationId;
            document.getElementById('resolveActionTaken').value = actionType;
            
            const actionSummary = document.getElementById('actionSummary');
            const resolveButton = document.getElementById('resolveButton');
            
            if (actionType === 'warning') {
                actionSummary.innerHTML = '<div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> This will issue a warning to the user. No other action will be taken.</div>';
                resolveButton.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Issue Warning';
                resolveButton.className = 'btn-warning';
            } else if (actionType === 'account_disabled') {
                actionSummary.innerHTML = '<div class="alert alert-danger"><i class="fas fa-ban"></i> This will disable the user\'s account. They will not be able to access the platform.</div>';
                resolveButton.innerHTML = '<i class="fas fa-ban"></i> Disable Account';
                resolveButton.className = 'btn-danger';
            }
            
            document.getElementById('resolveModal').style.display = 'block';
        }

        function dismissViolation(violationId) {
            document.getElementById('dismissViolationId').value = violationId;
            document.getElementById('dismissModal').style.display = 'block';
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            const modals = ['reportModal', 'resolveModal', 'dismissModal'];
            modals.forEach(modalId => {
                const modal = document.getElementById(modalId);
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            });
        }

        // Auto-hide alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.opacity = '0';
                    setTimeout(() => {
                        alert.style.display = 'none';
                    }, 300);
                }, 5000);
            });
        });
    </script>
</body>
</html>