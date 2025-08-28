<?php
// admin/users.php - User Management
require_once '../config/database.php';
require_once '../includes/session.php';
require_once '../includes/functions.php';

// Require admin access
requireAdmin();

$error = '';
$success = '';

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        $userId = intval($_POST['user_id']);
        
        try {
            switch ($action) {
                case 'disable_user':
                    $reason = sanitize($_POST['reason'] ?? '');
                    
                    $stmt = $pdo->prepare("UPDATE users SET status = 'disabled' WHERE user_id = ? AND role != 'admin'");
                    $stmt->execute([$userId]);
                    
                    // Add violation if reason provided
                    if ($reason) {
                        addViolation($userId, getUserId(), 'admin_action', "Account disabled by admin. Reason: $reason");
                    }
                    
                    $success = "User disabled successfully.";
                    logActivity(getUserId(), 'user_disabled', "Disabled user ID: $userId");
                    break;
                    
                case 'enable_user':
                    $stmt = $pdo->prepare("UPDATE users SET status = 'active' WHERE user_id = ? AND role != 'admin'");
                    $stmt->execute([$userId]);
                    
                    $success = "User enabled successfully.";
                    logActivity(getUserId(), 'user_enabled', "Enabled user ID: $userId");
                    break;
                    
                case 'reset_violations':
                    $stmt = $pdo->prepare("UPDATE users SET violation_count = 0 WHERE user_id = ?");
                    $stmt->execute([$userId]);
                    
                    $success = "User violations reset successfully.";
                    logActivity(getUserId(), 'violations_reset', "Reset violations for user ID: $userId");
                    break;
                    
                case 'update_loyalty_points':
                    $newPoints = intval($_POST['loyalty_points']);
                    
                    $stmt = $pdo->prepare("UPDATE users SET loyalty_points = ? WHERE user_id = ?");
                    $stmt->execute([$newPoints, $userId]);
                    
                    $success = "Loyalty points updated successfully.";
                    logActivity(getUserId(), 'loyalty_points_updated', "Updated loyalty points for user ID: $userId to $newPoints");
                    break;
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
        
        // Set flash message and redirect
        if ($success) {
            setFlashMessage('success', $success);
        }
        if ($error) {
            setFlashMessage('error', $error);
        }
        
        header('Location: users.php');
        exit();
    }
}

// Get filter parameters
$roleFilter = $_GET['role'] ?? 'all';
$statusFilter = $_GET['status'] ?? 'all';
$searchQuery = $_GET['search'] ?? '';

// Build query conditions
$whereConditions = ["role != 'admin'"];
$params = [];

if ($roleFilter !== 'all') {
    $whereConditions[] = "role = ?";
    $params[] = $roleFilter;
}

if ($statusFilter !== 'all') {
    $whereConditions[] = "status = ?";
    $params[] = $statusFilter;
}

if ($searchQuery) {
    $whereConditions[] = "(full_name LIKE ? OR email LIKE ? OR username LIKE ?)";
    $params[] = '%' . $searchQuery . '%';
    $params[] = '%' . $searchQuery . '%';
    $params[] = '%' . $searchQuery . '%';
}

$whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

try {
    // Get users
    $stmt = $pdo->prepare("
        SELECT u.*, s.shop_name,
               (SELECT COUNT(*) FROM orders WHERE customer_id = u.user_id AND payment_status = 'completed') as total_orders,
               (SELECT SUM(total_amount) FROM orders WHERE customer_id = u.user_id AND payment_status = 'completed') as total_spent
        FROM users u
        LEFT JOIN shops s ON u.user_id = s.trader_id
        $whereClause
        ORDER BY u.created_at DESC
        LIMIT 100
    ");
    $stmt->execute($params);
    $users = $stmt->fetchAll();
    
    // Get user statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_users,
            SUM(CASE WHEN role = 'customer' THEN 1 ELSE 0 END) as total_customers,
            SUM(CASE WHEN role = 'trader' THEN 1 ELSE 0 END) as total_traders,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_users,
            SUM(CASE WHEN status = 'disabled' THEN 1 ELSE 0 END) as disabled_users,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_users
        FROM users
        WHERE role != 'admin'
    ");
    $stmt->execute();
    $stats = $stmt->fetch();
    
} catch (PDOException $e) {
    $error = "Failed to load users: " . $e->getMessage();
    $users = [];
    $stats = ['total_users' => 0, 'total_customers' => 0, 'total_traders' => 0, 'active_users' => 0, 'disabled_users' => 0, 'pending_users' => 0];
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
    <title>User Management - Admin Dashboard</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/theme.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="admin-layout">
    <!-- Sidebar -->
    <nav class="admin-sidebar">
        <div class="sidebar-header">
            <div class="logo">
                <h2>Shopfusion</h2>
                <span>Admin Panel</span>
            </div>
        </div>
        
        <ul class="sidebar-menu">
            <li>
                <a href="index.php">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
            </li>
            <li>
                <a href="traders.php">
                    <i class="fas fa-store"></i> Traders
                </a>
            </li>
            <li>
                <a href="products.php">
                    <i class="fas fa-box"></i> Products
                </a>
            </li>
            <li class="active">
                <a href="users.php">
                    <i class="fas fa-users"></i> Users
                </a>
            </li>
            <li>
                <a href="orders.php">
                    <i class="fas fa-shopping-bag"></i> Orders
                </a>
            </li>
            <li>
                <a href="violations.php">
                    <i class="fas fa-exclamation-triangle"></i> Violations
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
    <main class="admin-main">
        <!-- Top Bar -->
        <div class="admin-topbar">
            <div class="topbar-left">
                <h1><i class="fas fa-users"></i> User Management</h1>
                <p>Manage customers, traders, and user accounts</p>
            </div>
            <div class="topbar-right">
                <div class="admin-user">
                    <i class="fas fa-user-shield"></i>
                    <span><?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="admin-content">
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

            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo number_format($stats['total_users']); ?></h3>
                        <p>Total Users</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon customer">
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo number_format($stats['total_customers']); ?></h3>
                        <p>Customers</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon trader">
                        <i class="fas fa-store"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo number_format($stats['total_traders']); ?></h3>
                        <p>Traders</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon active">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo number_format($stats['active_users']); ?></h3>
                        <p>Active Users</p>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="filters-section">
                <form method="GET" class="filters-form">
                    <div class="filter-group">
                        <label>Role:</label>
                        <select name="role">
                            <option value="all" <?php echo $roleFilter === 'all' ? 'selected' : ''; ?>>All Roles</option>
                            <option value="customer" <?php echo $roleFilter === 'customer' ? 'selected' : ''; ?>>Customers</option>
                            <option value="trader" <?php echo $roleFilter === 'trader' ? 'selected' : ''; ?>>Traders</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label>Status:</label>
                        <select name="status">
                            <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All Status</option>
                            <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="disabled" <?php echo $statusFilter === 'disabled' ? 'selected' : ''; ?>>Disabled</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label>Search:</label>
                        <input type="text" name="search" value="<?php echo htmlspecialchars($searchQuery); ?>" placeholder="Name, email, username...">
                    </div>
                    
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-search"></i> Filter
                    </button>
                    
                    <a href="users.php" class="btn-secondary">
                        <i class="fas fa-refresh"></i> Reset
                    </a>
                </form>
            </div>

            <!-- Users Table -->
            <div class="table-section">
                <div class="table-header">
                    <h2><i class="fas fa-list"></i> Users (<?php echo count($users); ?> found)</h2>
                </div>
                
                <?php if (empty($users)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <h3>No Users Found</h3>
                        <p>No users match your current filters.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Violations</th>
                                    <th>Orders/Sales</th>
                                    <th>Loyalty Points</th>
                                    <th>Joined</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td>
                                            <div class="user-info">
                                                <div class="user-details">
                                                    <h4><?php echo htmlspecialchars($user['full_name']); ?></h4>
                                                    <small><?php echo htmlspecialchars($user['email']); ?></small>
                                                    <small>@<?php echo htmlspecialchars($user['username']); ?></small>
                                                    <?php if ($user['shop_name']): ?>
                                                        <small class="shop-name">Shop: <?php echo htmlspecialchars($user['shop_name']); ?></small>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="role-badge role-<?php echo $user['role']; ?>">
                                                <i class="fas fa-<?php echo $user['role'] === 'trader' ? 'store' : 'user'; ?>"></i>
                                                <?php echo ucfirst($user['role']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?php echo $user['status']; ?>">
                                                <?php echo ucfirst($user['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($user['violation_count'] > 0): ?>
                                                <span class="violation-count high">
                                                    <i class="fas fa-exclamation-triangle"></i>
                                                    <?php echo $user['violation_count']; ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="violation-count clean">
                                                    <i class="fas fa-check-circle"></i>
                                                    Clean
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($user['role'] === 'customer'): ?>
                                                <div class="customer-stats">
                                                    <div><?php echo number_format($user['total_orders']); ?> orders</div>
                                                    <div><?php echo formatPrice($user['total_spent'] ?? 0); ?> spent</div>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="loyalty-points">
                                                <i class="fas fa-coins"></i>
                                                <?php echo number_format($user['loyalty_points']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <small><?php echo formatDate($user['created_at']); ?></small>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <?php if ($user['status'] === 'active'): ?>
                                                    <button class="btn-action danger" title="Disable User" 
                                                            onclick="showDisableModal(<?php echo $user['user_id']; ?>, '<?php echo addslashes($user['full_name']); ?>')">
                                                        <i class="fas fa-ban"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to enable this user?')">
                                                        <input type="hidden" name="action" value="enable_user">
                                                        <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                                        <button type="submit" class="btn-action success" title="Enable User">
                                                            <i class="fas fa-check"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                                
                                                <button class="btn-action primary" title="Edit Loyalty Points" 
                                                        onclick="showPointsModal(<?php echo $user['user_id']; ?>, '<?php echo addslashes($user['full_name']); ?>', <?php echo $user['loyalty_points']; ?>)">
                                                    <i class="fas fa-coins"></i>
                                                </button>
                                                
                                                <?php if ($user['violation_count'] > 0): ?>
                                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to reset violations for this user?')">
                                                        <input type="hidden" name="action" value="reset_violations">
                                                        <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                                        <button type="submit" class="btn-action warning" title="Reset Violations">
                                                            <i class="fas fa-undo"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Disable User Modal -->
    <div id="disableModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-ban"></i> Disable User</h3>
                <span class="close" onclick="closeModal('disableModal')">&times;</span>
            </div>
            
            <form method="POST" id="disableForm">
                <input type="hidden" name="action" value="disable_user">
                <input type="hidden" name="user_id" id="disableUserId">
                
                <div class="modal-body">
                    <p>You are about to disable the user: <strong id="disableUserName"></strong></p>
                    <p class="warning-text">
                        <i class="fas fa-exclamation-triangle"></i>
                        This will prevent the user from accessing their account.
                    </p>
                    
                    <div class="form-group">
                        <label for="reason">Reason for disabling:</label>
                        <textarea name="reason" id="reason" rows="3" 
                                  placeholder="Explain why this user is being disabled..."></textarea>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn-secondary" onclick="closeModal('disableModal')">Cancel</button>
                    <button type="submit" class="btn-danger">
                        <i class="fas fa-ban"></i> Disable User
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Loyalty Points Modal -->
    <div id="pointsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-coins"></i> Update Loyalty Points</h3>
                <span class="close" onclick="closeModal('pointsModal')">&times;</span>
            </div>
            
            <form method="POST" id="pointsForm">
                <input type="hidden" name="action" value="update_loyalty_points">
                <input type="hidden" name="user_id" id="pointsUserId">
                
                <div class="modal-body">
                    <p>Update loyalty points for: <strong id="pointsUserName"></strong></p>
                    
                    <div class="form-group">
                        <label for="loyalty_points">Loyalty Points:</label>
                        <input type="number" name="loyalty_points" id="loyalty_points" min="0" required>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn-secondary" onclick="closeModal('pointsModal')">Cancel</button>
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-save"></i> Update Points
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="../js/main.js"></script>
    
    <script>
        // Modal functions
        function showDisableModal(userId, userName) {
            document.getElementById('disableUserId').value = userId;
            document.getElementById('disableUserName').textContent = userName;
            document.getElementById('disableModal').style.display = 'block';
        }
        
        function showPointsModal(userId, userName, currentPoints) {
            document.getElementById('pointsUserId').value = userId;
            document.getElementById('pointsUserName').textContent = userName;
            document.getElementById('loyalty_points').value = currentPoints;
            document.getElementById('pointsModal').style.display = 'block';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
            if (modalId === 'disableModal') {
                document.getElementById('disableForm').reset();
            } else if (modalId === 'pointsModal') {
                document.getElementById('pointsForm').reset();
            }
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modals = ['disableModal', 'pointsModal'];
            modals.forEach(modalId => {
                const modal = document.getElementById(modalId);
                if (event.target === modal) {
                    closeModal(modalId);
                }
            });
        }
        
        // Auto-hide alerts
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 300);
            });
        }, 5000);
    </script>

    <style>
        /* User Management Styles */
        .stat-icon.customer {
            background: #e7f3ff;
            color: #0c5460;
        }
        
        .stat-icon.trader {
            background: #fff3cd;
            color: #856404;
        }
        
        .stat-icon.active {
            background: #d4edda;
            color: #155724;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .user-details h4 {
            margin: 0 0 0.25rem 0;
            color: #333;
            font-size: 0.9rem;
        }
        
        .user-details small {
            display: block;
            color: #666;
            font-size: 0.8rem;
            margin-bottom: 0.1rem;
        }
        
        .shop-name {
            color: #007bff !important;
            font-weight: 500;
        }
        
        .role-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 500;
            text-transform: uppercase;
            display: flex;
            align-items: center;
            gap: 0.3rem;
            width: fit-content;
        }
        
        .role-badge.role-customer {
            background: #e7f3ff;
            color: #0c5460;
        }
        
        .role-badge.role-trader {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 500;
            text-transform: uppercase;
        }
        
        .status-badge.status-active {
            background: #d4edda;
            color: #155724;
        }
        
        .status-badge.status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-badge.status-disabled {
            background: #f8d7da;
            color: #721c24;
        }
        
        .violation-count {
            display: flex;
            align-items: center;
            gap: 0.3rem;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .violation-count.high {
            color: #dc3545;
        }
        
        .violation-count.clean {
            color: #28a745;
        }
        
        .customer-stats {
            font-size: 0.8rem;
        }
        
        .customer-stats div {
            margin-bottom: 0.2rem;
        }
        
        .loyalty-points {
            display: flex;
            align-items: center;
            gap: 0.3rem;
            color: #ffc107;
            font-weight: 500;
        }
        
        .text-muted {
            color: #999;
            font-style: italic;
        }
        
        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }
        
        .btn-action {
            width: 30px;
            height: 30px;
            border-radius: 5px;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            transition: all 0.2s;
        }
        
        .btn-action.primary {
            background: #007bff;
            color: white;
        }
        
        .btn-action.primary:hover {
            background: #0056b3;
        }
        
        .btn-action.success {
            background: #28a745;
            color: white;
        }
        
        .btn-action.success:hover {
            background: #218838;
        }
        
        .btn-action.warning {
            background: #ffc107;
            color: #212529;
        }
        
        .btn-action.warning:hover {
            background: #e0a800;
        }
        
        .btn-action.danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-action.danger:hover {
            background: #c82333;
        }
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background-color: white;
            margin: 5% auto;
            border-radius: 10px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
        }
        
        .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h3 {
            margin: 0;
            color: #333;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .close {
            font-size: 1.5rem;
            cursor: pointer;
            color: #999;
        }
        
        .close:hover {
            color: #333;
        }
        
        .modal-body {
            padding: 1.5rem;
        }
        
        .warning-text {
            background: #fff3cd;
            color: #856404;
            padding: 1rem;
            border-radius: 5px;
            margin: 1rem 0;
            display: flex;
            align-items: flex-start;
            gap: 0.5rem;
        }
        
        .modal-footer {
            padding: 1.5rem;
            border-top: 1px solid #eee;
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
        }
        
        @media (max-width: 768px) {
            .user-info {
                flex-direction: column;
                text-align: center;
            }
            
            .action-buttons {
                flex-direction: column;
            }
        }
    </style>
</body>
</html>
