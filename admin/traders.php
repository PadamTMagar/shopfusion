<?php
// admin/traders.php - Trader Management
require_once '../config/database.php';
require_once '../includes/session.php';
require_once '../includes/functions.php';

// Require admin access
requireAdmin();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && isset($_POST['trader_id'])) {
        $traderId = (int)$_POST['trader_id'];
        $action = $_POST['action'];
        
        try {
            switch ($action) {
                case 'approve':
                    $stmt = $pdo->prepare("UPDATE users SET status = 'active' WHERE user_id = ? AND role = 'trader'");
                    $stmt->execute([$traderId]);
                    
                    // Log activity
                    logActivity(getUserId(), 'trader_approved', "Approved trader ID: $traderId");
                    
                    setFlashMessage('success', 'Trader approved successfully!');
                    break;
                    
                case 'reject':
                    $reason = sanitize($_POST['reason'] ?? '');
                    
                    // Delete the trader and their shop
                    $pdo->beginTransaction();
                    
                    $stmt = $pdo->prepare("DELETE FROM shops WHERE trader_id = ?");
                    $stmt->execute([$traderId]);
                    
                    $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ? AND role = 'trader'");
                    $stmt->execute([$traderId]);
                    
                    $pdo->commit();
                    
                    // Log activity
                    logActivity(getUserId(), 'trader_rejected', "Rejected trader ID: $traderId, Reason: $reason");
                    
                    setFlashMessage('success', 'Trader application rejected.');
                    break;
                    
                case 'disable':
                    $reason = sanitize($_POST['reason'] ?? '');
                    
                    $stmt = $pdo->prepare("UPDATE users SET status = 'disabled' WHERE user_id = ? AND role = 'trader'");
                    $stmt->execute([$traderId]);
                    
                    // Log activity
                    logActivity(getUserId(), 'trader_disabled', "Disabled trader ID: $traderId, Reason: $reason");
                    
                    setFlashMessage('success', 'Trader account disabled.');
                    break;
                    
                case 'enable':
                    $stmt = $pdo->prepare("UPDATE users SET status = 'active' WHERE user_id = ? AND role = 'trader'");
                    $stmt->execute([$traderId]);
                    
                    // Log activity
                    logActivity(getUserId(), 'trader_enabled', "Enabled trader ID: $traderId");
                    
                    setFlashMessage('success', 'Trader account enabled.');
                    break;
            }
        } catch (PDOException $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            setFlashMessage('error', 'Database error: ' . $e->getMessage());
        }
        
        header('Location: traders.php');
        exit();
    }
}

// Get filter parameters
$statusFilter = $_GET['status'] ?? 'all';
$searchQuery = $_GET['search'] ?? '';

// Build query
$whereConditions = ["role = 'trader'"];
$params = [];

if ($statusFilter !== 'all') {
    $whereConditions[] = "status = ?";
    $params[] = $statusFilter;
}

if (!empty($searchQuery)) {
    $whereConditions[] = "(username LIKE ? OR full_name LIKE ? OR email LIKE ?)";
    $searchParam = "%$searchQuery%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

$whereClause = implode(' AND ', $whereConditions);

try {
    // Get traders with their shops
    $stmt = $pdo->prepare("
        SELECT u.*, s.shop_name, s.description as shop_description, s.shop_id,
               (SELECT COUNT(*) FROM products p WHERE p.shop_id = s.shop_id AND p.status = 'active') as product_count,
               (SELECT COUNT(*) FROM violations v WHERE v.reported_user_id = u.user_id) as violation_count
        FROM users u
        LEFT JOIN shops s ON u.user_id = s.trader_id
        WHERE $whereClause
        ORDER BY 
            CASE WHEN u.status = 'pending' THEN 0 ELSE 1 END,
            u.created_at DESC
    ");
    $stmt->execute($params);
    $traders = $stmt->fetchAll();
    
    // Get status counts for tabs
    $stmt = $pdo->prepare("SELECT status, COUNT(*) as count FROM users WHERE role = 'trader' GROUP BY status");
    $stmt->execute();
    $statusCounts = [];
    while ($row = $stmt->fetch()) {
        $statusCounts[$row['status']] = $row['count'];
    }
    
} catch (PDOException $e) {
    $error = "Failed to load traders: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trader Management - Admin</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/theme.css">
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
            <li class="active"><a href="traders.php"><i class="fas fa-store"></i> Traders</a></li>
            <li><a href="violations.php"><i class="fas fa-exclamation-triangle"></i> Violations</a></li>
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
                <h1>Trader Management</h1>
                <p>Approve, manage, and monitor trader accounts</p>
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

            <!-- Filters and Search -->
            <div class="admin-filters">
                <div class="filter-tabs">
                    <a href="?status=all" class="filter-tab <?php echo $statusFilter === 'all' ? 'active' : ''; ?>">
                        All Traders (<?php echo array_sum($statusCounts); ?>)
                    </a>
                    <a href="?status=pending" class="filter-tab <?php echo $statusFilter === 'pending' ? 'active' : ''; ?>">
                        Pending (<?php echo $statusCounts['pending'] ?? 0; ?>)
                    </a>
                    <a href="?status=active" class="filter-tab <?php echo $statusFilter === 'active' ? 'active' : ''; ?>">
                        Active (<?php echo $statusCounts['active'] ?? 0; ?>)
                    </a>
                    <a href="?status=disabled" class="filter-tab <?php echo $statusFilter === 'disabled' ? 'active' : ''; ?>">
                        Disabled (<?php echo $statusCounts['disabled'] ?? 0; ?>)
                    </a>
                </div>
                
                <div class="filter-search">
                    <form method="GET" class="search-form">
                        <input type="hidden" name="status" value="<?php echo htmlspecialchars($statusFilter); ?>">
                        <input type="text" name="search" placeholder="Search traders..." 
                               value="<?php echo htmlspecialchars($searchQuery); ?>">
                        <button type="submit"><i class="fas fa-search"></i></button>
                        <?php if ($searchQuery): ?>
                            <a href="?status=<?php echo $statusFilter; ?>" class="clear-search">
                                <i class="fas fa-times"></i>
                            </a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <!-- Traders Table -->
            <div class="admin-table-container">
                <?php if (empty($traders)): ?>
                    <div class="empty-state">
                        <i class="fas fa-store"></i>
                        <h3>No traders found</h3>
                        <p>
                            <?php if ($searchQuery): ?>
                                No traders match your search criteria.
                            <?php else: ?>
                                No traders registered yet.
                            <?php endif; ?>
                        </p>
                    </div>
                <?php else: ?>
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Trader Info</th>
                                <th>Shop Details</th>
                                <th>Statistics</th>
                                <th>Status</th>
                                <th>Joined</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($traders as $trader): ?>
                                <tr class="trader-row status-<?php echo $trader['status']; ?>">
                                    <td class="trader-info">
                                        <div class="trader-details">
                                            <strong><?php echo htmlspecialchars($trader['full_name']); ?></strong>
                                            <div class="trader-meta">
                                                <span class="username">@<?php echo htmlspecialchars($trader['username']); ?></span>
                                                <span class="email"><?php echo htmlspecialchars($trader['email']); ?></span>
                                                <?php if ($trader['phone']): ?>
                                                    <span class="phone"><?php echo htmlspecialchars($trader['phone']); ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    
                                    <td class="shop-details">
                                        <?php if ($trader['shop_name']): ?>
                                            <div class="shop-info">
                                                <strong><?php echo htmlspecialchars($trader['shop_name']); ?></strong>
                                                <?php if ($trader['shop_description']): ?>
                                                    <p class="shop-desc"><?php echo htmlspecialchars(substr($trader['shop_description'], 0, 100)); ?><?php echo strlen($trader['shop_description']) > 100 ? '...' : ''; ?></p>
                                                <?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="no-shop">No shop created</span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td class="trader-stats">
                                        <div class="stat-item">
                                            <i class="fas fa-box"></i>
                                            <span><?php echo $trader['product_count']; ?> products</span>
                                        </div>
                                        <?php if ($trader['violation_count'] > 0): ?>
                                            <div class="stat-item warning">
                                                <i class="fas fa-exclamation-triangle"></i>
                                                <span><?php echo $trader['violation_count']; ?> violations</span>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td class="status-cell">
                                        <span class="status-badge status-<?php echo $trader['status']; ?>">
                                            <?php
                                            switch($trader['status']) {
                                                case 'pending':
                                                    echo '<i class="fas fa-clock"></i> Pending';
                                                    break;
                                                case 'active':
                                                    echo '<i class="fas fa-check-circle"></i> Active';
                                                    break;
                                                case 'disabled':
                                                    echo '<i class="fas fa-ban"></i> Disabled';
                                                    break;
                                            }
                                            ?>
                                        </span>
                                    </td>
                                    
                                    <td class="date-cell">
                                        <?php echo formatDate($trader['created_at']); ?>
                                    </td>
                                    
                                    <td class="actions-cell">
                                        <div class="action-buttons">
                                            <?php if ($trader['status'] === 'pending'): ?>
                                                <button class="btn-success btn-sm" onclick="approveTrader(<?php echo $trader['user_id']; ?>)">
                                                    <i class="fas fa-check"></i> Approve
                                                </button>
                                                <button class="btn-danger btn-sm" onclick="rejectTrader(<?php echo $trader['user_id']; ?>, '<?php echo htmlspecialchars($trader['full_name']); ?>')">
                                                    <i class="fas fa-times"></i> Reject
                                                </button>
                                            <?php elseif ($trader['status'] === 'active'): ?>
                                                <button class="btn-warning btn-sm" onclick="disableTrader(<?php echo $trader['user_id']; ?>, '<?php echo htmlspecialchars($trader['full_name']); ?>')">
                                                    <i class="fas fa-ban"></i> Disable
                                                </button>
                                            <?php elseif ($trader['status'] === 'disabled'): ?>
                                                <button class="btn-success btn-sm" onclick="enableTrader(<?php echo $trader['user_id']; ?>)">
                                                    <i class="fas fa-check"></i> Enable
                                                </button>
                                            <?php endif; ?>
                                            
                                            <button class="btn-info btn-sm" onclick="viewTraderDetails(<?php echo $trader['user_id']; ?>)">
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                            
                                            <div class="dropdown">
                                                <button class="btn-secondary btn-sm dropdown-toggle">
                                                    <i class="fas fa-ellipsis-v"></i>
                                                </button>
                                                <div class="dropdown-menu">
                                                    <?php if ($trader['shop_id']): ?>
                                                        <a href="../shop/products.php?shop_id=<?php echo $trader['shop_id']; ?>" target="_blank">
                                                            <i class="fas fa-external-link-alt"></i> View Shop
                                                        </a>
                                                    <?php endif; ?>
                                                    <a href="violations.php?trader_id=<?php echo $trader['user_id']; ?>">
                                                        <i class="fas fa-exclamation-triangle"></i> View Violations
                                                    </a>
                                                    <a href="#" onclick="sendMessage(<?php echo $trader['user_id']; ?>)">
                                                        <i class="fas fa-envelope"></i> Send Message
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Modal for Trader Details -->
    <div id="traderModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Trader Details</h3>
                <button class="modal-close" onclick="closeModal('traderModal')">&times;</button>
            </div>
            <div class="modal-body" id="traderModalBody">
                <!-- Content will be loaded here -->
            </div>
        </div>
    </div>

    <!-- Modal for Actions -->
    <div id="actionModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="actionModalTitle">Confirm Action</h3>
                <button class="modal-close" onclick="closeModal('actionModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="actionForm" method="POST">
                    <input type="hidden" name="trader_id" id="actionTraderId">
                    <input type="hidden" name="action" id="actionType">
                    
                    <div id="actionMessage" class="modal-message"></div>
                    
                    <div id="reasonField" class="form-group" style="display: none;">
                        <label for="reason">Reason (Optional):</label>
                        <textarea name="reason" id="reason" rows="3" placeholder="Enter reason for this action..."></textarea>
                    </div>
                    
                    <div class="modal-actions">
                        <button type="button" class="btn-secondary" onclick="closeModal('actionModal')">Cancel</button>
                        <button type="submit" id="confirmActionBtn" class="btn-primary">Confirm</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="../js/main.js"></script>
    <script>
        // Approve trader
        function approveTrader(traderId) {
            showActionModal(
                'Approve Trader',
                'Are you sure you want to approve this trader? They will be able to start selling products.',
                traderId,
                'approve',
                'btn-success',
                false // no reason field needed
            );
        }

        // Reject trader
        function rejectTrader(traderId, traderName) {
            showActionModal(
                'Reject Trader Application',
                `Are you sure you want to reject ${traderName}'s application? This action cannot be undone and will delete their account.`,
                traderId,
                'reject',
                'btn-danger',
                true // reason field needed
            );
        }

        // Disable trader
        function disableTrader(traderId, traderName) {
            showActionModal(
                'Disable Trader Account',
                `Are you sure you want to disable ${traderName}'s account? They will not be able to sell products.`,
                traderId,
                'disable',
                'btn-warning',
                true // reason field needed
            );
        }

        // Enable trader
        function enableTrader(traderId) {
            showActionModal(
                'Enable Trader Account',
                'Are you sure you want to enable this trader account? They will be able to sell products again.',
                traderId,
                'enable',
                'btn-success',
                false // no reason field needed
            );
        }

        // Show action modal
        function showActionModal(title, message, traderId, action, btnClass, showReason) {
            document.getElementById('actionModalTitle').textContent = title;
            document.getElementById('actionMessage').innerHTML = message;
            document.getElementById('actionTraderId').value = traderId;
            document.getElementById('actionType').value = action;
            
            const confirmBtn = document.getElementById('confirmActionBtn');
            confirmBtn.className = `btn ${btnClass}`;
            confirmBtn.textContent = title.split(' ')[0]; // First word of title
            
            const reasonField = document.getElementById('reasonField');
            reasonField.style.display = showReason ? 'block' : 'none';
            
            if (showReason) {
                document.getElementById('reason').required = true;
            } else {
                document.getElementById('reason').required = false;
            }
            
            document.getElementById('actionModal').style.display = 'block';
        }

        // View trader details
        function viewTraderDetails(traderId) {
            const modalBody = document.getElementById('traderModalBody');
            modalBody.innerHTML = '<div class="loading">Loading trader details...</div>';
            
            document.getElementById('traderModal').style.display = 'block';
            
            // Fetch trader details via AJAX
            fetch(`trader_details.php?id=${traderId}`)
                .then(response => response.text())
                .then(html => {
                    modalBody.innerHTML = html;
                })
                .catch(error => {
                    modalBody.innerHTML = '<div class="error">Failed to load trader details.</div>';
                });
        }

        // Close modal
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        // Send message (placeholder)
        function sendMessage(traderId) {
            showNotification('Messaging feature coming soon!', 'info');
        }

        // Handle dropdown toggles
        document.addEventListener('click', function(e) {
            // Close all dropdowns when clicking outside
            if (!e.target.matches('.dropdown-toggle')) {
                const dropdowns = document.querySelectorAll('.dropdown-menu');
                dropdowns.forEach(dropdown => {
                    dropdown.classList.remove('show');
                });
            }
        });

        // Toggle dropdown menus
        document.querySelectorAll('.dropdown-toggle').forEach(toggle => {
            toggle.addEventListener('click', function(e) {
                e.stopPropagation();
                const dropdown = this.nextElementSibling;
                
                // Close other dropdowns
                document.querySelectorAll('.dropdown-menu').forEach(menu => {
                    if (menu !== dropdown) {
                        menu.classList.remove('show');
                    }
                });
                
                dropdown.classList.toggle('show');
            });
        });

        // Close modals when clicking outside
        window.addEventListener('click', function(e) {
            if (e.target.classList.contains('modal')) {
                e.target.style.display = 'none';
            }
        });

        // Auto-refresh pending count
        if (window.location.search.includes('status=pending')) {
            setInterval(() => {
                // Check for new pending traders
                fetch('check_pending.php')
                    .then(response => response.json())
                    .then(data => {
                        if (data.new_pending > 0) {
                            showNotification(`${data.new_pending} new trader application(s) received!`, 'info');
                            setTimeout(() => location.reload(), 2000);
                        }
                    })
                    .catch(error => console.log('Error checking for updates:', error));
            }, 30000); // Check every 30 seconds
        }
    </script>
</body>
</html>
