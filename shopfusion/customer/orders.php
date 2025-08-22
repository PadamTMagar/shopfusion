<?php
// customer/orders.php - Customer Order History
require_once '../config/database.php';
require_once '../includes/session.php';
require_once '../includes/functions.php';

// Require customer access
requireLogin();
if (!isCustomer()) {
    header('Location: ../index.php');
    exit();
}

$success = '';
$error = '';

// Get current user data
$currentUser = getCurrentUser();
$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$currentUser['user_id']]);
$userData = $stmt->fetch();

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Filter parameters
$status_filter = isset($_GET['status']) ? sanitize($_GET['status']) : '';
$date_from = isset($_GET['date_from']) ? sanitize($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? sanitize($_GET['date_to']) : '';

try {
    // Build query with filters
    $whereClause = "WHERE o.customer_id = ?";
    $params = [$currentUser['user_id']];
    
    if ($status_filter) {
        $whereClause .= " AND o.payment_status = ?";
        $params[] = $status_filter;
    }
    
    if ($date_from) {
        $whereClause .= " AND DATE(o.created_at) >= ?";
        $params[] = $date_from;
    }
    
    if ($date_to) {
        $whereClause .= " AND DATE(o.created_at) <= ?";
        $params[] = $date_to;
    }
    
    // Get total count for pagination
    $countQuery = "SELECT COUNT(*) as total FROM orders o $whereClause";
    $stmt = $pdo->prepare($countQuery);
    $stmt->execute($params);
    $totalOrders = $stmt->fetch()['total'];
    $totalPages = ceil($totalOrders / $limit);
    
    // Get orders with pagination
    $ordersQuery = "
        SELECT o.*, COUNT(oi.order_item_id) as item_count,
               GROUP_CONCAT(DISTINCT s.shop_name SEPARATOR ', ') as shop_names
        FROM orders o
        LEFT JOIN order_items oi ON o.order_id = oi.order_id
        LEFT JOIN shops s ON oi.shop_id = s.shop_id
        $whereClause
        GROUP BY o.order_id
        ORDER BY o.created_at DESC
        LIMIT $limit OFFSET $offset
    ";
    
    $stmt = $pdo->prepare($ordersQuery);
    $stmt->execute($params);
    $orders = $stmt->fetchAll();
    
    // Get order statistics
    $statsQuery = "
        SELECT 
            COUNT(*) as total_orders,
            SUM(CASE WHEN payment_status = 'completed' THEN total_amount ELSE 0 END) as total_spent,
            SUM(CASE WHEN payment_status = 'pending' THEN 1 ELSE 0 END) as pending_orders,
            SUM(CASE WHEN payment_status = 'completed' THEN 1 ELSE 0 END) as completed_orders
        FROM orders 
        WHERE customer_id = ?
    ";
    $stmt = $pdo->prepare($statsQuery);
    $stmt->execute([$currentUser['user_id']]);
    $stats = $stmt->fetch();
    
} catch (PDOException $e) {
    $error = "Failed to load orders: " . $e->getMessage();
}

// Handle order actions (cancel, etc.)
if ($_POST && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'cancel_order':
            $orderId = (int)$_POST['order_id'];
            
            // Verify order belongs to user and can be cancelled
            $stmt = $pdo->prepare("
                SELECT * FROM orders 
                WHERE order_id = ? AND customer_id = ? AND payment_status = 'pending'
            ");
            $stmt->execute([$orderId, $currentUser['user_id']]);
            $order = $stmt->fetch();
            
            if ($order) {
                try {
                    $stmt = $pdo->prepare("
                        UPDATE orders 
                        SET payment_status = 'cancelled', updated_at = NOW() 
                        WHERE order_id = ?
                    ");
                    
                    if ($stmt->execute([$orderId])) {
                        $success = "Order #$orderId has been cancelled successfully.";
                        // Refresh page to show updated status
                        header("Location: orders.php?success=" . urlencode($success));
                        exit();
                    } else {
                        $error = "Failed to cancel order.";
                    }
                } catch (PDOException $e) {
                    $error = "Database error: " . $e->getMessage();
                }
            } else {
                $error = "Order not found or cannot be cancelled.";
            }
            break;
    }
}

// Check for success message from redirect
if (isset($_GET['success'])) {
    $success = sanitize($_GET['success']);
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
    <title>My Orders - ShopFusion</title>
    
    <link rel="stylesheet" href="../css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="customer-body">
    <?php include '../includes/header.php'; ?>

    <!-- Main Content -->
    <div class="customer-container">
        <!-- Customer Sidebar -->
        <nav class="customer-sidebar">
            <div class="sidebar-header">
                <div class="user-avatar">
                    <i class="fas fa-user-circle"></i>
                </div>
                <h3><?php echo htmlspecialchars($userData['full_name']); ?></h3>
                <p class="user-email"><?php echo htmlspecialchars($userData['email']); ?></p>
            </div>
            
            <ul class="sidebar-menu">
                <li>
                    <a href="profile.php">
                        <i class="fas fa-user"></i> My Profile
                    </a>
                </li>
                <li class="active">
                    <a href="orders.php">
                        <i class="fas fa-shopping-bag"></i> My Orders
                        <?php if ($stats['total_orders'] > 0): ?>
                            <span class="badge"><?php echo $stats['total_orders']; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li>
                    <a href="points.php">
                        <i class="fas fa-star"></i> Loyalty Points
                        <span class="badge points-badge"><?php echo $userData['loyalty_points']; ?></span>
                    </a>
                </li>
                <li>
                    <a href="../index.php">
                        <i class="fas fa-shopping-cart"></i> Continue Shopping
                    </a>
                </li>
            </ul>
            
            <div class="sidebar-footer">
                <a href="../auth/logout.php" class="btn-danger">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </nav>

        <!-- Orders Content -->
        <main class="customer-main">
            <div class="page-header">
                <h1><i class="fas fa-shopping-bag"></i> My Orders</h1>
                <p>View and manage your order history</p>
            </div>

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

            <!-- Order Statistics -->
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
                    <div class="stat-icon">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo formatPrice($stats['total_spent']); ?></h3>
                        <p>Total Spent</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo number_format($stats['pending_orders']); ?></h3>
                        <p>Pending Orders</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo number_format($stats['completed_orders']); ?></h3>
                        <p>Completed Orders</p>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="orders-filters">
                <form method="GET" class="filter-form">
                    <div class="filter-group">
                        <label for="status">Status:</label>
                        <select name="status" id="status">
                            <option value="">All Orders</option>
                            <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="failed" <?php echo $status_filter === 'failed' ? 'selected' : ''; ?>>Failed</option>
                            <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            <option value="refunded" <?php echo $status_filter === 'refunded' ? 'selected' : ''; ?>>Refunded</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="date_from">From:</label>
                        <input type="date" name="date_from" id="date_from" value="<?php echo $date_from; ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label for="date_to">To:</label>
                        <input type="date" name="date_to" id="date_to" value="<?php echo $date_to; ?>">
                    </div>
                    
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-filter"></i> Filter
                    </button>
                    
                    <a href="orders.php" class="btn-secondary">
                        <i class="fas fa-times"></i> Clear
                    </a>
                </form>
            </div>

            <!-- Orders List -->
            <div class="orders-container">
                <?php if (empty($orders)): ?>
                    <div class="empty-state">
                        <i class="fas fa-shopping-bag"></i>
                        <h3>No orders found</h3>
                        <p>You haven't placed any orders yet or no orders match your filter criteria.</p>
                        <a href="../index.php" class="btn-primary">Start Shopping</a>
                    </div>
                <?php else: ?>
                    <div class="orders-list">
                        <?php foreach ($orders as $order): ?>
                            <div class="order-card">
                                <div class="order-header">
                                    <div class="order-info">
                                        <h3>Order #<?php echo $order['order_id']; ?></h3>
                                        <p class="order-date">
                                            <i class="fas fa-calendar"></i>
                                            <?php echo formatDateTime($order['created_at']); ?>
                                        </p>
                                    </div>
                                    
                                    <div class="order-status">
                                        <span class="status-badge status-<?php echo $order['payment_status']; ?>">
                                            <?php echo ucfirst($order['payment_status']); ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="order-details">
                                    <div class="order-summary">
                                        <div class="summary-item">
                                            <i class="fas fa-shopping-cart"></i>
                                            <span><?php echo $order['item_count']; ?> item<?php echo $order['item_count'] != 1 ? 's' : ''; ?></span>
                                        </div>
                                        
                                        <div class="summary-item">
                                            <i class="fas fa-store"></i>
                                            <span><?php echo htmlspecialchars($order['shop_names'] ?? 'N/A'); ?></span>
                                        </div>
                                        
                                        <div class="summary-item">
                                            <i class="fas fa-dollar-sign"></i>
                                            <span><?php echo formatPrice($order['total_amount']); ?></span>
                                        </div>
                                        
                                        <?php if ($order['payment_method']): ?>
                                            <div class="summary-item">
                                                <i class="fas fa-credit-card"></i>
                                                <span><?php echo ucfirst($order['payment_method']); ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if ($order['promo_code']): ?>
                                        <div class="promo-info">
                                            <i class="fas fa-tag"></i>
                                            <span>Promo code applied: <strong><?php echo htmlspecialchars($order['promo_code']); ?></strong></span>
                                            <?php if ($order['discount_amount'] > 0): ?>
                                                <span class="discount-amount">(-<?php echo formatPrice($order['discount_amount']); ?>)</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="order-actions">
                                    <a href="order_details.php?id=<?php echo $order['order_id']; ?>" class="btn-primary btn-sm">
                                        <i class="fas fa-eye"></i> View Details
                                    </a>
                                    
                                    <?php if ($order['payment_status'] === 'completed'): ?>
                                        <a href="invoice.php?id=<?php echo $order['order_id']; ?>" class="btn-secondary btn-sm" target="_blank">
                                            <i class="fas fa-file-pdf"></i> Invoice
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php if ($order['payment_status'] === 'pending'): ?>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to cancel this order?');">
                                            <input type="hidden" name="action" value="cancel_order">
                                            <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                                            <button type="submit" class="btn-danger btn-sm">
                                                <i class="fas fa-times"></i> Cancel
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    
                                    <?php if ($order['payment_status'] === 'completed'): ?>
                                        <a href="reorder.php?id=<?php echo $order['order_id']; ?>" class="btn-success btn-sm">
                                            <i class="fas fa-redo"></i> Reorder
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?php echo ($page - 1); ?><?php echo $status_filter ? '&status=' . $status_filter : ''; ?><?php echo $date_from ? '&date_from=' . $date_from : ''; ?><?php echo $date_to ? '&date_to=' . $date_to : ''; ?>" class="page-btn">
                                    <i class="fas fa-chevron-left"></i> Previous
                                </a>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                <a href="?page=<?php echo $i; ?><?php echo $status_filter ? '&status=' . $status_filter : ''; ?><?php echo $date_from ? '&date_from=' . $date_from : ''; ?><?php echo $date_to ? '&date_to=' . $date_to : ''; ?>" 
                                   class="page-btn <?php echo $i === $page ? 'active' : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                            
                            <?php if ($page < $totalPages): ?>
                                <a href="?page=<?php echo ($page + 1); ?><?php echo $status_filter ? '&status=' . $status_filter : ''; ?><?php echo $date_from ? '&date_from=' . $date_from : ''; ?><?php echo $date_to ? '&date_to=' . $date_to : ''; ?>" class="page-btn">
                                    Next <i class="fas fa-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                        
                        <div class="pagination-info">
                            Showing <?php echo (($page - 1) * $limit + 1); ?> to <?php echo min($page * $limit, $totalOrders); ?> of <?php echo $totalOrders; ?> orders
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <?php include '../includes/footer.php'; ?>

    <script src="../js/main.js"></script>
    <script>
        // Auto-hide alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 300);
            });
        }, 5000);

        // Form submission feedback
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const submitBtn = form.querySelector('button[type="submit"]');
                if (submitBtn && !submitBtn.onclick) {
                    const originalText = submitBtn.innerHTML;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
                    submitBtn.disabled = true;
                }
            });
        });

        // Filter form auto-submit on select change
        document.getElementById('status').addEventListener('change', function() {
            this.form.submit();
        });
    </script>
</body>
</html>