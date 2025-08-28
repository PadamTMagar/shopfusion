<?php
// admin/orders.php - Order Management
require_once '../config/database.php';
require_once '../includes/session.php';
require_once '../includes/functions.php';

// Require admin access
requireAdmin();

$error = '';
$success = '';

// Handle order actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        $orderId = intval($_POST['order_id']);
        
        try {
            switch ($action) {
                case 'update_status':
                    $newStatus = sanitize($_POST['new_status']);
                    
                    $stmt = $pdo->prepare("UPDATE orders SET order_status = ? WHERE order_id = ?");
                    $stmt->execute([$newStatus, $orderId]);
                    
                    $success = "Order status updated successfully.";
                    logActivity(getUserId(), 'order_status_updated', "Admin updated order $orderId status to $newStatus");
                    break;
                    
                case 'refund_order':
                    $reason = sanitize($_POST['refund_reason'] ?? '');
                    
                    // Update order status
                    $stmt = $pdo->prepare("UPDATE orders SET order_status = 'cancelled', payment_status = 'refunded' WHERE order_id = ?");
                    $stmt->execute([$orderId]);
                    
                    // Get order details for customer notification
                    $stmt = $pdo->prepare("
                        SELECT o.*, u.full_name as customer_name, u.email as customer_email
                        FROM orders o
                        JOIN users u ON o.customer_id = u.user_id
                        WHERE o.order_id = ?
                    ");
                    $stmt->execute([$orderId]);
                    $order = $stmt->fetch();
                    
                    $success = "Order refunded successfully.";
                    logActivity(getUserId(), 'order_refunded', "Admin refunded order $orderId. Reason: $reason");
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
        
        header('Location: orders.php');
        exit();
    }
}

// Get filter parameters
$statusFilter = $_GET['status'] ?? 'all';
$paymentFilter = $_GET['payment'] ?? 'all';
$dateFilter = $_GET['date'] ?? 'all';
$searchQuery = $_GET['search'] ?? '';

// Build query conditions
$whereConditions = [];
$params = [];

if ($statusFilter !== 'all') {
    $whereConditions[] = "o.order_status = ?";
    $params[] = $statusFilter;
}

if ($paymentFilter !== 'all') {
    $whereConditions[] = "o.payment_status = ?";
    $params[] = $paymentFilter;
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

if ($searchQuery) {
    $whereConditions[] = "(o.order_id LIKE ? OR u.full_name LIKE ? OR u.email LIKE ?)";
    $params[] = '%' . $searchQuery . '%';
    $params[] = '%' . $searchQuery . '%';
    $params[] = '%' . $searchQuery . '%';
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

try {
    // Get orders
    $stmt = $pdo->prepare("
        SELECT o.*, u.full_name as customer_name, u.email as customer_email, u.phone as customer_phone,
               COUNT(oi.item_id) as item_count,
               GROUP_CONCAT(DISTINCT s.shop_name SEPARATOR ', ') as shops
        FROM orders o
        JOIN users u ON o.customer_id = u.user_id
        LEFT JOIN order_items oi ON o.order_id = oi.order_id
        LEFT JOIN products p ON oi.product_id = p.product_id
        LEFT JOIN shops s ON p.shop_id = s.shop_id
        $whereClause
        GROUP BY o.order_id
        ORDER BY o.created_at DESC
        LIMIT 100
    ");
    $stmt->execute($params);
    $orders = $stmt->fetchAll();
    
    // Get order statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_orders,
            SUM(CASE WHEN order_status = 'pending' THEN 1 ELSE 0 END) as pending_orders,
            SUM(CASE WHEN order_status = 'processing' THEN 1 ELSE 0 END) as processing_orders,
            SUM(CASE WHEN order_status = 'shipped' THEN 1 ELSE 0 END) as shipped_orders,
            SUM(CASE WHEN order_status = 'delivered' THEN 1 ELSE 0 END) as delivered_orders,
            SUM(CASE WHEN order_status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_orders,
            SUM(CASE WHEN payment_status = 'completed' THEN total_amount ELSE 0 END) as total_revenue,
            AVG(CASE WHEN payment_status = 'completed' THEN total_amount ELSE NULL END) as avg_order_value
        FROM orders
    ");
    $stmt->execute();
    $stats = $stmt->fetch();
    
} catch (PDOException $e) {
    $error = "Failed to load orders: " . $e->getMessage();
    $orders = [];
    $stats = ['total_orders' => 0, 'pending_orders' => 0, 'processing_orders' => 0, 'shipped_orders' => 0, 'delivered_orders' => 0, 'cancelled_orders' => 0, 'total_revenue' => 0, 'avg_order_value' => 0];
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
    <title>Order Management - Admin Dashboard</title>
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
            <li>
                <a href="users.php">
                    <i class="fas fa-users"></i> Users
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
    <main class="admin-main">
        <!-- Top Bar -->
        <div class="admin-topbar">
            <div class="topbar-left">
                <h1><i class="fas fa-shopping-bag"></i> Order Management</h1>
                <p>Monitor and manage all platform orders</p>
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
                        <p>Pending Orders</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon success">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo number_format($stats['delivered_orders']); ?></h3>
                        <p>Delivered</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon revenue">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo formatPrice($stats['total_revenue']); ?></h3>
                        <p>Total Revenue</p>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="filters-section">
                <form method="GET" class="filters-form">
                    <div class="filter-group">
                        <label>Order Status:</label>
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
                        <label>Payment:</label>
                        <select name="payment">
                            <option value="all" <?php echo $paymentFilter === 'all' ? 'selected' : ''; ?>>All Payments</option>
                            <option value="completed" <?php echo $paymentFilter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="pending" <?php echo $paymentFilter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="failed" <?php echo $paymentFilter === 'failed' ? 'selected' : ''; ?>>Failed</option>
                            <option value="refunded" <?php echo $paymentFilter === 'refunded' ? 'selected' : ''; ?>>Refunded</option>
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
                    
                    <div class="filter-group">
                        <label>Search:</label>
                        <input type="text" name="search" value="<?php echo htmlspecialchars($searchQuery); ?>" placeholder="Order ID, customer name, email...">
                    </div>
                    
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-search"></i> Filter
                    </button>
                    
                    <a href="orders.php" class="btn-secondary">
                        <i class="fas fa-refresh"></i> Reset
                    </a>
                </form>
            </div>

            <!-- Orders List -->
            <div class="orders-section">
                <div class="section-header">
                    <h2><i class="fas fa-list"></i> Orders (<?php echo count($orders); ?> found)</h2>
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
                                            <span class="order-shops">
                                                <i class="fas fa-store"></i>
                                                <?php echo htmlspecialchars($order['shops']); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="order-badges">
                                        <span class="status-badge status-<?php echo $order['order_status']; ?>">
                                            <?php echo ucfirst($order['order_status']); ?>
                                        </span>
                                        <span class="payment-badge payment-<?php echo $order['payment_status']; ?>">
                                            <?php echo ucfirst($order['payment_status']); ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="order-body">
                                    <div class="order-details">
                                        <div class="detail-group">
                                            <label>Customer Email:</label>
                                            <span><?php echo htmlspecialchars($order['customer_email']); ?></span>
                                        </div>
                                        <?php if ($order['customer_phone']): ?>
                                            <div class="detail-group">
                                                <label>Phone:</label>
                                                <span><?php echo htmlspecialchars($order['customer_phone']); ?></span>
                                            </div>
                                        <?php endif; ?>
                                        <div class="detail-group">
                                            <label>Payment Method:</label>
                                            <span><?php echo ucfirst(str_replace('_', ' ', $order['payment_method'])); ?></span>
                                        </div>
                                        <div class="detail-group">
                                            <label>Total Amount:</label>
                                            <span class="amount"><?php echo formatPrice($order['total_amount']); ?></span>
                                        </div>
                                    </div>
                                    
                                    <div class="shipping-address">
                                        <label>Shipping Address:</label>
                                        <address><?php echo nl2br(htmlspecialchars($order['shipping_address'])); ?></address>
                                    </div>
                                </div>
                                
                                <div class="order-actions">
                                    <div class="status-update">
                                        <form method="POST" class="status-form">
                                            <input type="hidden" name="action" value="update_status">
                                            <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                                            <label>Update Status:</label>
                                            <select name="new_status" onchange="this.form.submit()">
                                                <option value="pending" <?php echo $order['order_status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                <option value="processing" <?php echo $order['order_status'] === 'processing' ? 'selected' : ''; ?>>Processing</option>
                                                <option value="shipped" <?php echo $order['order_status'] === 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                                                <option value="delivered" <?php echo $order['order_status'] === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                                                <option value="cancelled" <?php echo $order['order_status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                            </select>
                                        </form>
                                    </div>
                                    
                                    <div class="action-buttons">
                                        <a href="mailto:<?php echo htmlspecialchars($order['customer_email']); ?>" 
                                           class="btn-action secondary" title="Contact Customer">
                                            <i class="fas fa-envelope"></i>
                                        </a>
                                        
                                        <?php if ($order['payment_status'] === 'completed' && $order['order_status'] !== 'cancelled'): ?>
                                            <button class="btn-action danger" title="Refund Order" 
                                                    onclick="showRefundModal(<?php echo $order['order_id']; ?>, '<?php echo addslashes($order['customer_name']); ?>')">
                                                <i class="fas fa-undo"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Refund Modal -->
    <div id="refundModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-undo"></i> Refund Order</h3>
                <span class="close" onclick="closeRefundModal()">&times;</span>
            </div>
            
            <form method="POST" id="refundForm">
                <input type="hidden" name="action" value="refund_order">
                <input type="hidden" name="order_id" id="refundOrderId">
                
                <div class="modal-body">
                    <p>You are about to refund the order for: <strong id="refundCustomerName"></strong></p>
                    <p class="warning-text">
                        <i class="fas fa-exclamation-triangle"></i>
                        This action will cancel the order and mark it as refunded. This cannot be undone.
                    </p>
                    
                    <div class="form-group">
                        <label for="refund_reason">Reason for refund: *</label>
                        <textarea name="refund_reason" id="refund_reason" rows="3" required 
                                  placeholder="Explain why this order is being refunded..."></textarea>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn-secondary" onclick="closeRefundModal()">Cancel</button>
                    <button type="submit" class="btn-danger">
                        <i class="fas fa-undo"></i> Process Refund
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="../js/main.js"></script>
    
    <script>
        // Refund modal functions
        function showRefundModal(orderId, customerName) {
            document.getElementById('refundOrderId').value = orderId;
            document.getElementById('refundCustomerName').textContent = customerName;
            document.getElementById('refundModal').style.display = 'block';
        }
        
        function closeRefundModal() {
            document.getElementById('refundModal').style.display = 'none';
            document.getElementById('refundForm').reset();
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('refundModal');
            if (event.target === modal) {
                closeRefundModal();
            }
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
        /* Order Management Styles */
        .stat-icon.pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .stat-icon.success {
            background: #d4edda;
            color: #155724;
        }
        
        .stat-icon.revenue {
            background: #e7f3ff;
            color: #0c5460;
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
        
        .order-badges {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .status-badge, .payment-badge {
            padding: 0.3rem 0.8rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 500;
            text-transform: uppercase;
            text-align: center;
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
        
        .payment-badge.payment-completed {
            background: #d4edda;
            color: #155724;
        }
        
        .payment-badge.payment-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .payment-badge.payment-failed {
            background: #f8d7da;
            color: #721c24;
        }
        
        .payment-badge.payment-refunded {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .order-body {
            padding: 1.5rem;
        }
        
        .order-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .detail-group {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }
        
        .detail-group label {
            font-weight: 500;
            color: #333;
            font-size: 0.9rem;
        }
        
        .detail-group span {
            color: #666;
        }
        
        .amount {
            font-weight: 600;
            color: #28a745;
            font-size: 1.1rem;
        }
        
        .shipping-address {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 5px;
        }
        
        .shipping-address label {
            font-weight: 500;
            color: #333;
            display: block;
            margin-bottom: 0.5rem;
        }
        
        .shipping-address address {
            color: #666;
            font-style: normal;
            line-height: 1.5;
        }
        
        .order-actions {
            padding: 1rem 1.5rem;
            background: #f8f9fa;
            border-top: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .status-form {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .status-form label {
            font-weight: 500;
            color: #333;
            font-size: 0.9rem;
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
        
        .btn-action.secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-action.secondary:hover {
            background: #545b62;
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
            .order-header {
                flex-direction: column;
                gap: 1rem;
            }
            
            .order-meta {
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .order-details {
                grid-template-columns: 1fr;
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
