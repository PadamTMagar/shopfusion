<?php
// trader/violations.php - Trader Violation History
require_once '../config/database.php';
require_once '../includes/session.php';
require_once '../includes/functions.php';

// Require trader access
requireTrader();

$error = '';
$success = '';

// Get trader's violations
try {
    $stmt = $pdo->prepare("
        SELECT v.*, u.full_name as admin_name
        FROM violations v
        LEFT JOIN users u ON v.reporter_id = u.user_id
        WHERE v.reported_user_id = ?
        ORDER BY v.created_at DESC
    ");
    $stmt->execute([getUserId()]);
    $violations = $stmt->fetchAll();
    
    // Get current user's violation count
    $stmt = $pdo->prepare("SELECT violation_count, status FROM users WHERE user_id = ?");
    $stmt->execute([getUserId()]);
    $userInfo = $stmt->fetch();
    
} catch (PDOException $e) {
    $error = "Failed to load violations: " . $e->getMessage();
    $violations = [];
    $userInfo = ['violation_count' => 0, 'status' => 'active'];
}

// Check for flash messages
if ($flashSuccess = getFlashMessage('success')) {
    $success = $flashSuccess;
}
if ($flashError = getFlashMessage('error')) {
    $error = $flashError;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Violations - Trader Dashboard</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/theme.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="trader-layout">
    <!-- Sidebar -->
    <nav class="trader-sidebar">
        <div class="sidebar-header">
            <div class="logo">
                <h2>Shopfusion</h2>
                <span>Trader Panel</span>
            </div>
        </div>
        
        <ul class="sidebar-menu">
            <li>
                <a href="index.php">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
            </li>
            <li>
                <a href="products.php">
                    <i class="fas fa-box"></i> Products
                </a>
            </li>
            <li>
                <a href="orders.php">
                    <i class="fas fa-shopping-bag"></i> Orders
                </a>
            </li>
            <li class="active">
                <a href="violations.php">
                    <i class="fas fa-exclamation-triangle"></i> Violations
                    <?php if ($userInfo['violation_count'] > 0): ?>
                        <span class="badge badge-danger"><?php echo $userInfo['violation_count']; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li>
                <a href="reports.php">
                    <i class="fas fa-chart-bar"></i> Reports
                </a>
            </li>
        </ul>
        
        <div class="sidebar-footer">
            <a href="../index.php" class="btn-secondary">
                <i class="fas fa-home"></i> Back to Store
            </a>
            <a href="../auth/logout.php" class="btn-danger">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="trader-main">
        <!-- Top Bar -->
        <div class="trader-topbar">
            <div class="topbar-left">
                <h1><i class="fas fa-exclamation-triangle"></i> Violations</h1>
                <p>View your violation history and warnings</p>
            </div>
            <div class="topbar-right">
                <div class="violation-status">
                    <?php if ($userInfo['status'] === 'disabled'): ?>
                        <span class="status-badge status-disabled">
                            <i class="fas fa-ban"></i> Account Disabled
                        </span>
                    <?php elseif ($userInfo['violation_count'] >= 1): ?>
                        <span class="status-badge status-warning">
                            <i class="fas fa-exclamation-triangle"></i> 
                            <?php echo $userInfo['violation_count']; ?> Warning<?php echo $userInfo['violation_count'] > 1 ? 's' : ''; ?>
                        </span>
                    <?php else: ?>
                        <span class="status-badge status-good">
                            <i class="fas fa-check-circle"></i> Good Standing
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="trader-content">
            <!-- Error/Success Messages -->
            <?php if (!empty($error)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <!-- Violation Policy -->
            <div class="info-card">
                <div class="info-header">
                    <h3><i class="fas fa-info-circle"></i> Violation Policy</h3>
                </div>
                <div class="info-content">
                    <div class="policy-grid">
                        <div class="policy-item">
                            <div class="policy-icon warning">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                            <div class="policy-details">
                                <h4>First Violation</h4>
                                <p>Warning issued - Product may be disabled temporarily</p>
                            </div>
                        </div>
                        <div class="policy-item">
                            <div class="policy-icon danger">
                                <i class="fas fa-ban"></i>
                            </div>
                            <div class="policy-details">
                                <h4>Second Violation</h4>
                                <p>Account suspended - All products disabled</p>
                            </div>
                        </div>
                        <div class="policy-item">
                            <div class="policy-icon info">
                                <i class="fas fa-handshake"></i>
                            </div>
                            <div class="policy-details">
                                <h4>Appeal Process</h4>
                                <p>Contact admin to appeal violations or request account reactivation</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Violations List -->
            <div class="violations-section">
                <div class="section-header">
                    <h2><i class="fas fa-list"></i> Violation History</h2>
                    <div class="section-stats">
                        <span class="stat-item">
                            <strong><?php echo count($violations); ?></strong> Total Violations
                        </span>
                    </div>
                </div>

                <?php if (empty($violations)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <h3>No Violations</h3>
                        <p>Great job! You have no violations on record. Keep following our guidelines to maintain good standing.</p>
                    </div>
                <?php else: ?>
                    <div class="violations-list">
                        <?php foreach ($violations as $violation): ?>
                            <div class="violation-card">
                                <div class="violation-header">
                                    <div class="violation-info">
                                        <h4>
                                            <i class="fas fa-exclamation-triangle"></i>
                                            <?php echo htmlspecialchars($violation['violation_type']); ?>
                                        </h4>
                                        <div class="violation-meta">
                                            <span class="violation-date">
                                                <i class="fas fa-calendar"></i>
                                                <?php echo formatDateTime($violation['created_at']); ?>
                                            </span>
                                            <span class="violation-status">
                                                <i class="fas fa-info-circle"></i>
                                                Status: <?php echo ucfirst($violation['status']); ?>
                                            </span>
                                            <?php if ($violation['admin_name']): ?>
                                                <span class="violation-admin">
                                                    <i class="fas fa-user-shield"></i>
                                                    By <?php echo htmlspecialchars($violation['admin_name']); ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="violation-action-taken">
                                        <span class="action-badge action-<?php echo strtolower(str_replace('_', '-', $violation['action_taken'])); ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $violation['action_taken'])); ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="violation-body">
                                    <div class="violation-description">
                                        <h5>Description:</h5>
                                        <p><?php echo htmlspecialchars($violation['description']); ?></p>
                                    </div>
                                    
                                    <?php if ($violation['admin_notes']): ?>
                                        <div class="violation-notes">
                                            <h5>Admin Notes:</h5>
                                            <p><?php echo nl2br(htmlspecialchars($violation['admin_notes'])); ?></p>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($violation['action_taken']): ?>
                                        <div class="violation-action">
                                            <h5>Action Taken:</h5>
                                            <p><?php echo ucfirst(str_replace('_', ' ', $violation['action_taken'])); ?></p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Contact Admin -->
            <div class="contact-section">
                <div class="contact-card">
                    <h3><i class="fas fa-headset"></i> Need Help?</h3>
                    <p>If you have questions about a violation or need to appeal a decision, please contact our admin team.</p>
                    <div class="contact-actions">
                        <a href="mailto:admin@shopfusion.com" class="btn-primary">
                            <i class="fas fa-envelope"></i> Contact Admin
                        </a>
                        <a href="../index.php" class="btn-secondary">
                            <i class="fas fa-home"></i> Back to Store
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="../js/main.js"></script>
    
    <style>
        /* Violations Page Styles */
        .violation-status {
            display: flex;
            align-items: center;
        }
        
        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 500;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .status-badge.status-good {
            background: #d4edda;
            color: #155724;
        }
        
        .status-badge.status-warning {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-badge.status-disabled {
            background: #f8d7da;
            color: #721c24;
        }
        
        .info-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        
        .info-header {
            padding: 1.5rem;
            border-bottom: 1px solid #eee;
        }
        
        .info-header h3 {
            color: #333;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .info-content {
            padding: 1.5rem;
        }
        
        .policy-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
        }
        
        .policy-item {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
        }
        
        .policy-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            flex-shrink: 0;
        }
        
        .policy-icon.warning {
            background: #fff3cd;
            color: #856404;
        }
        
        .policy-icon.danger {
            background: #f8d7da;
            color: #721c24;
        }
        
        .policy-icon.info {
            background: #e7f3ff;
            color: #0c5460;
        }
        
        .policy-details h4 {
            margin: 0 0 0.5rem 0;
            color: #333;
        }
        
        .policy-details p {
            margin: 0;
            color: #666;
            font-size: 0.9rem;
        }
        
        .violations-section {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        
        .section-header {
            padding: 1.5rem;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .section-header h2 {
            color: #333;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .section-stats {
            display: flex;
            gap: 1rem;
        }
        
        .stat-item {
            color: #666;
            font-size: 0.9rem;
        }
        
        .empty-state {
            padding: 3rem;
            text-align: center;
        }
        
        .empty-icon {
            font-size: 4rem;
            color: #28a745;
            margin-bottom: 1rem;
        }
        
        .empty-state h3 {
            color: #333;
            margin-bottom: 1rem;
        }
        
        .empty-state p {
            color: #666;
            max-width: 400px;
            margin: 0 auto;
        }
        
        .violations-list {
            padding: 1.5rem;
        }
        
        .violation-card {
            border: 1px solid #eee;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            overflow: hidden;
        }
        
        .violation-header {
            background: #f8f9fa;
            padding: 1rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        
        .violation-info h4 {
            margin: 0 0 0.5rem 0;
            color: #dc3545;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .violation-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            font-size: 0.85rem;
            color: #666;
        }
        
        .violation-meta span {
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }
        
        .action-badge {
            padding: 0.3rem 0.8rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 500;
            text-transform: uppercase;
        }
        
        .action-badge.action-warning {
            background: #fff3cd;
            color: #856404;
        }
        
        .action-badge.action-account-disabled {
            background: #f8d7da;
            color: #721c24;
        }
        
        .action-badge.action-dismissed {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .violation-body {
            padding: 1.5rem;
        }
        
        .violation-body h5 {
            margin: 0 0 0.5rem 0;
            color: #333;
            font-size: 0.9rem;
        }
        
        .violation-body p {
            margin: 0 0 1rem 0;
            color: #666;
            line-height: 1.5;
        }
        
        .violation-description,
        .violation-notes,
        .violation-action {
            margin-bottom: 1rem;
        }
        
        .violation-notes {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 5px;
        }
        
        .violation-action {
            background: #e7f3ff;
            padding: 1rem;
            border-radius: 5px;
        }
        
        .contact-section {
            margin-top: 2rem;
        }
        
        .contact-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 2rem;
            text-align: center;
        }
        
        .contact-card h3 {
            color: #333;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        
        .contact-card p {
            color: #666;
            margin-bottom: 1.5rem;
        }
        
        .contact-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .badge-danger {
            background: #dc3545;
            color: white;
        }
        
        @media (max-width: 768px) {
            .policy-grid {
                grid-template-columns: 1fr;
            }
            
            .violation-header {
                flex-direction: column;
                gap: 1rem;
            }
            
            .violation-meta {
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .contact-actions {
                flex-direction: column;
            }
        }
    </style>
</body>
</html>
