<?php
// trader/orders.php - Trader Orders Management
require_once '../config/database.php';
require_once '../includes/session.php';
require_once '../includes/functions.php';

// Require trader access
requireTrader();

// Get trader's shop
$traderShop = getTraderShop($_SESSION['user_id']);
if (!$traderShop) {
    $error = "No shop assigned to your account. Please contact administrator.";
}

$error = '';
$success = '';

// Handle order status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $orderId = intval($_POST['order_id']);
    
    try {
        switch ($action) {
            case 'update_status':
                $newStatus = sanitize($_POST['new_status']);
                
                // Verify this order contains items from trader's shop
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as count 
                    FROM order_items oi 
                    WHERE oi.order_id = ? AND oi.shop_id = ?
                ");
                $stmt->execute([$orderId, $traderShop['shop_id']]);
                $hasItems = $stmt->fetch()['count'] > 0;
                
                if ($hasItems) {
                    $stmt = $pdo->prepare("UPDATE orders SET order_status = ? WHERE order_id = ?");
                    $stmt->execute([$newStatus, $orderId]);
                    
                    $success = "Order status updated successfully.";
                    logActivity(getUserId(), 'order_status_updated', "Updated order $orderId status to $newStatus");
                } else {
                    $error = "Access denied for this order.";
                }
                break;
        }
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
    
    if ($success) {
        setFlashMessage('success', $success);
    }
    if ($error) {
        setFlashMessage('error', $error);
    }
    
    header('Location: orders.php');
    exit();
}

// Get filter parameters
$statusFilter = $_GET['status'] ?? 'all';
$dateFilter = $_GET['date'] ?? 'all';

// Build query conditions
$whereConditions = ["oi.shop_id = ?"];
$params = [$traderShop['shop_id']];

if ($statusFilter !== 'all') {
    $whereConditions[] = "o.order_status = ?";
    $params[] = $statusFilter;
}

if ($dateFilter !== 'all') {
    switch ($dateFilter) {
        case 'today':
            $whereConditions[] = "DATE(o.created_at) = CURDATE()";
            break;
        case 'week':
            $whereConditions[] = "o.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            break;
        case 'month':
            $whereConditions[] = "o.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            break;
    }
}

$whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

try {
    if ($traderShop) {
        // Get orders with trader's items
        $stmt = $pdo->prepare("
            SELECT o.*, u.full_name as customer_name, u.email as customer_email,
                   GROUP_CONCAT(CONCAT(p.product_name, ' (', oi.quantity, ')') SEPARATOR ', ') as products,
                   SUM(oi.subtotal) as trader_total,
                   COUNT(oi.item_id) as item_count
            FROM orders o
            JOIN order_items oi ON o.order_id = oi.order_id
            JOIN products p ON oi.product_id = p.product_id
            JOIN users u ON o.customer_id = u.user_id
            $whereClause
            AND o.payment_status = 'completed'
            GROUP BY o.order_id, o.created_at, o.order_status, o.total_amount, u.full_name, u.email
            ORDER BY o.created_at DESC
            LIMIT 50
        ");
        $stmt->execute($params);
        $orders = $stmt->fetchAll();
        
        // Get order statistics
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(DISTINCT o.order_id) as total_orders,
                SUM(CASE WHEN o.order_status = 'pending' THEN 1 ELSE 0 END) as pending_orders,
                SUM(CASE WHEN o.order_status = 'processing' THEN 1 ELSE 0 END) as processing_orders,
                SUM(CASE WHEN o.order_status = 'shipped' THEN 1 ELSE 0 END) as shipped_orders,
                SUM(CASE WHEN o.order_status = 'delivered' THEN 1 ELSE 0 END) as delivered_orders,
                SUM(oi.subtotal) as total_revenue
            FROM orders o
            JOIN order_items oi ON o.order_id = oi.order_id
            WHERE oi.shop_id = ? AND o.payment_status = 'completed'
        ");
        $stmt->execute([$traderShop['shop_id']]);
        $stats = $stmt->fetch();
    }
} catch (PDOException $e) {
    $error = "Failed to load orders: " . $e->getMessage();
    $orders = [];
    $stats = ['total_orders' => 0, 'pending_orders' => 0, 'processing_orders' => 0, 'shipped_orders' => 0, 'delivered_orders' => 0, 'total_revenue' => 0];
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
    <title>Orders - Trader Dashboard</title>
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
            <li class="active">
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
    <main class="trader-main">
        <!-- Top Bar -->
        <div class="trader-topbar">
            <div class="topbar-left">
                <h1><i class="fas fa-shopping-bag"></i> Orders</h1>
                <p>Manage your orders and track sales</p>
            </div>
            <div class="topbar-right">
                <?php if ($traderShop): ?>
                    <div class="shop-info">
                        <i class="fas fa-store"></i>
                        <span><?php echo htmlspecialchars($traderShop['shop_name']); ?></span>
                    </div>
                <?php endif; ?>
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

            <?php if (!$traderShop): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    No shop assigned to your account. Please contact administrator to set up your shop.
                </div>
            <?php else: ?>

            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-shopping-bag"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo number_format($stats['total_orders']); ?></h3>
                        <p>Total Orders</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon pending">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo number_format($stats['pending_orders']); ?></h3>
                        <p>Pending</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon processing">
                        <i class="fas fa-cog"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo number_format($stats['processing_orders']); ?></h3>
                        <p>Processing</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon delivered">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo number_format($stats['delivered_orders']); ?></h3>
                        <p>Delivered</p>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="filters-section">
                <form method="GET" class="filters-form">
                    <div class="filter-group">
                        <label>Status:</label>
                        <select name="status">
                            <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All Status</option>
                            <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="processing" <?php echo $statusFilter === 'processing' ? 'selected' : ''; ?>>Processing</option>
                            <option value="shipped" <?php echo $statusFilter === 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                            <option value="delivered" <?php echo $statusFilter === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                            <option value="cancelled" <?php echo $statusFilter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label>Date:</label>
                        <select name="date">
                            <option value="all" <?php echo $dateFilter === 'all' ? 'selected' : ''; ?>>All Time</option>
                            <option value="today" <?php echo $dateFilter === 'today' ? 'selected' : ''; ?>>Today</option>
                            <option value="week" <?php echo $dateFilter === 'week' ? 'selected' : ''; ?>>This Week</option>
                            <option value="month" <?php echo $dateFilter === 'month' ? 'selected' : ''; ?>>This Month</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-search"></i> Filter
                    </button>
                    
                    <a href="orders.php" class="btn-secondary">
                        <i class="fas fa-refresh"></i> Reset
                    </a>
                </form>
            </div>

            <!-- Orders Table -->
            <div class="orders-section">
                <div class="section-header">
                    <h2><i class="fas fa-list"></i> Recent Orders (<?php echo count($orders); ?> found)</h2>
                </div>
                
                <?php if (empty($orders)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <i class="fas fa-shopping-bag"></i>
                        </div>
                        <h3>No Orders Found</h3>
                        <p>No orders match your current filters.</p>
                    </div>
                <?php else: ?>
                    <div class="orders-list">
                        <?php foreach ($orders as $order): ?>
                            <div class="order-card">
                                <div class="order-header">
                                    <div class="order-info">
                                        <h3>Order #<?php echo $order['order_id']; ?></h3>
                                        <div class="order-meta">
                                            <span class="order-date">
                                                <i class="fas fa-calendar"></i>
                                                <?php echo formatDateTime($order['created_at']); ?>
                                            </span>
                                            <span class="order-customer">
                                                <i class="fas fa-user"></i>
                                                <?php echo htmlspecialchars($order['customer_name']); ?>
                                            </span>
                                            <span class="order-items">
                                                <i class="fas fa-box"></i>
                                                <?php echo $order['item_count']; ?> item<?php echo $order['item_count'] > 1 ? 's' : ''; ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="order-status">
                                        <span class="status-badge status-<?php echo $order['order_status']; ?>">
                                            <?php echo ucfirst($order['order_status']); ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="order-body">
                                    <div class="order-products">
                                        <h5>Products:</h5>
                                        <p><?php echo htmlspecialchars($order['products']); ?></p>
                                    </div>
                                    
                                    <div class="order-totals">
                                        <div class="total-row">
                                            <span>Your Earnings:</span>
                                            <span class="earnings"><?php echo formatPrice($order['trader_total']); ?></span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="order-actions">
                                    <div class="status-update">
                                        <form method="POST" class="status-form">
                                            <input type="hidden" name="action" value="update_status">
                                            <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                                            <select name="new_status" onchange="this.form.submit()">
                                                <option value="pending" <?php echo $order['order_status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                <option value="processing" <?php echo $order['order_status'] === 'processing' ? 'selected' : ''; ?>>Processing</option>
                                                <option value="shipped" <?php echo $order['order_status'] === 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                                                <option value="delivered" <?php echo $order['order_status'] === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                                            </select>
                                        </form>
                                    </div>
                                    
                                    <div class="action-buttons">
                                        <a href="invoice.php?order_id=<?php echo $order['order_id']; ?>" 
                                           class="btn-action primary" title="View Invoice" target="_blank">
                                            <i class="fas fa-file-invoice"></i>
                                        </a>
                                        <a href="mailto:<?php echo htmlspecialchars($order['customer_email']); ?>" 
                                           class="btn-action secondary" title="Contact Customer">
                                            <i class="fas fa-envelope"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <?php endif; ?>
        </div>
    </main>

    <script src="../js/main.js"></script>
    
    <script>
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
        /* Orders Page Styles */
        .shop-info {
            background: #f8f9fa;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.9rem;
            color: #666;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .stat-icon.pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .stat-icon.processing {
            background: #e7f3ff;
            color: #0c5460;
        }
        
        .stat-icon.delivered {
            background: #d4edda;
            color: #155724;
        }
        
        .orders-section {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .section-header {
            padding: 1.5rem;
            border-bottom: 1px solid #eee;
        }
        
        .section-header h2 {
            color: #333;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .empty-state {
            padding: 3rem;
            text-align: center;
        }
        
        .empty-icon {
            font-size: 4rem;
            color: #ddd;
            margin-bottom: 1rem;
        }
        
        .empty-state h3 {
            color: #333;
            margin-bottom: 1rem;
        }
        
        .empty-state p {
            color: #666;
        }
        
        .orders-list {
            padding: 1.5rem;
        }
        
        .order-card {
            border: 1px solid #eee;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            overflow: hidden;
            transition: box-shadow 0.2s;
        }
        
        .order-card:hover {
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .order-header {
            background: #f8f9fa;
            padding: 1rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        
        .order-info h3 {
            margin: 0 0 0.5rem 0;
            color: #007bff;
        }
        
        .order-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            font-size: 0.85rem;
            color: #666;
        }
        
        .order-meta span {
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }
        
        .status-badge {
            padding: 0.3rem 0.8rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 500;
            text-transform: uppercase;
        }
        
        .status-badge.status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-badge.status-processing {
            background: #e7f3ff;
            color: #0c5460;
        }
        
        .status-badge.status-shipped {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .status-badge.status-delivered {
            background: #d4edda;
            color: #155724;
        }
        
        .status-badge.status-cancelled {
            background: #f8d7da;
            color: #721c24;
        }
        
        .order-body {
            padding: 1.5rem;
        }
        
        .order-products h5 {
            margin: 0 0 0.5rem 0;
            color: #333;
            font-size: 0.9rem;
        }
        
        .order-products p {
            margin: 0 0 1rem 0;
            color: #666;
            line-height: 1.5;
        }
        
        .order-totals {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 5px;
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .earnings {
            font-weight: 600;
            color: #28a745;
            font-size: 1.1rem;
        }
        
        .order-actions {
            padding: 1rem 1.5rem;
            background: #f8f9fa;
            border-top: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .status-form select {
            padding: 0.5rem;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 0.9rem;
            background: white;
        }
        
        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }
        
        .btn-action {
            width: 35px;
            height: 35px;
            border-radius: 5px;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            transition: all 0.2s;
        }
        
        .btn-action.primary {
            background: #007bff;
            color: white;
        }
        
        .btn-action.primary:hover {
            background: #0056b3;
        }
        
        .btn-action.secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-action.secondary:hover {
            background: #545b62;
        }
        
        @media (max-width: 768px) {
            .order-header {
                flex-direction: column;
                gap: 1rem;
            }
            
            .order-meta {
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .order-actions {
                flex-direction: column;
                gap: 1rem;
                align-items: stretch;
            }
            
            .action-buttons {
                justify-content: center;
            }
        }
    </style>
</body>
</html>
