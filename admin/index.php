<?php
// admin/index.php - Admin Dashboard
require_once '../config/database.php';
require_once '../includes/session.php';
require_once '../includes/functions.php';

// Require admin access
requireAdmin();

// Get dashboard statistics
try {
    // Total statistics
    $stmt = $pdo->prepare("SELECT COUNT(*) as total_users FROM users WHERE role != 'admin'");
    $stmt->execute();
    $totalUsers = $stmt->fetch()['total_users'];
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as total_products FROM products WHERE status = 'active'");
    $stmt->execute();
    $totalProducts = $stmt->fetch()['total_products'];
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as total_orders FROM orders WHERE payment_status = 'completed'");
    $stmt->execute();
    $totalOrders = $stmt->fetch()['total_orders'];
    
    $stmt = $pdo->prepare("SELECT SUM(total_amount) as total_revenue FROM orders WHERE payment_status = 'completed'");
    $stmt->execute();
    $totalRevenue = $stmt->fetch()['total_revenue'] ?? 0;
    
    // Pending approvals
    $pendingTraders = getPendingTraders();
    $pendingTradersCount = count($pendingTraders);
    
    // Pending violations
    $stmt = $pdo->prepare("SELECT COUNT(*) as pending_violations FROM violations WHERE status = 'pending'");
    $stmt->execute();
    $pendingViolations = $stmt->fetch()['pending_violations'];
    
    // Recent orders
    $stmt = $pdo->prepare("
        SELECT o.*, u.full_name as customer_name 
        FROM orders o 
        JOIN users u ON o.customer_id = u.user_id 
        ORDER BY o.created_at DESC 
        LIMIT 10
    ");
    $stmt->execute();
    $recentOrders = $stmt->fetchAll();
    
    // Top selling products
    $stmt = $pdo->prepare("
        SELECT p.product_name, p.price, s.shop_name, SUM(oi.quantity) as total_sold,
               SUM(oi.subtotal) as total_revenue
        FROM order_items oi
        JOIN products p ON oi.product_id = p.product_id
        JOIN shops s ON oi.shop_id = s.shop_id
        JOIN orders o ON oi.order_id = o.order_id
        WHERE o.payment_status = 'completed'
        GROUP BY oi.product_id
        ORDER BY total_sold DESC
        LIMIT 5
    ");
    $stmt->execute();
    $topProducts = $stmt->fetchAll();
    
    // Monthly sales data for chart
    $stmt = $pdo->prepare("
        SELECT DATE_FORMAT(created_at, '%Y-%m') as month, 
               COUNT(*) as orders, 
               SUM(total_amount) as revenue
        FROM orders 
        WHERE payment_status = 'completed' 
        AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month
    ");
    $stmt->execute();
    $monthlySales = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error = "Failed to load dashboard data: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard </title>
    
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/theme.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
</head>
<body class="admin-body">
    <!-- Admin Sidebar -->
    <nav class="admin-sidebar">
        <div class="sidebar-header">
            <h2><i class="fas fa-shield-alt"></i> Admin Panel</h2>
        </div>
        
        <ul class="sidebar-menu">
            <li class="active">
                <a href="index.php">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
            </li>
            <li>
                <a href="traders.php">
                    <i class="fas fa-store"></i> Traders
                    <?php if ($pendingTradersCount > 0): ?>
                        <span class="badge"><?php echo $pendingTradersCount; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li>
                <a href="products.php">
                    <i class="fas fa-box"></i> Products
                </a>
            </li>
            <li>
                <a href="promo_codes.php">
                    <i class="fas fa-tags"></i> Promo Codes
                </a>
            </li>
            <li>
                <a href="violations.php">
                    <i class="fas fa-exclamation-triangle"></i> Violations
                    <?php if ($pendingViolations > 0): ?>
                        <span class="badge"><?php echo $pendingViolations; ?></span>
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
                <i class="fas fa-home"></i> Back to Site
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
                <h1>Dashboard Overview</h1>
                <p>Welcome back, <?php echo htmlspecialchars($_SESSION['full_name']); ?>!</p>
            </div>
            <div class="topbar-right">
                <span class="current-time" id="currentTime"></span>
            </div>
        </div>

        <!-- Dashboard Content -->
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

            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo number_format($totalUsers); ?></h3>
                        <p>Total Users</p>
                        <small class="stat-change positive">
                            <i class="fas fa-arrow-up"></i> Active platform
                        </small>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-box"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo number_format($totalProducts); ?></h3>
                        <p>Active Products</p>
                        <small class="stat-change positive">
                            <i class="fas fa-arrow-up"></i> Growing catalog
                        </small>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-shopping-bag"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo number_format($totalOrders); ?></h3>
                        <p>Total Orders</p>
                        <small class="stat-change positive">
                            <i class="fas fa-arrow-up"></i> Strong sales
                        </small>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo formatPrice($totalRevenue); ?></h3>
                        <p>Total Revenue</p>
                        <small class="stat-change positive">
                            <i class="fas fa-arrow-up"></i> Revenue growth
                        </small>
                    </div>
                </div>
            </div>

            <!-- Action Cards -->
            <?php if ($pendingTradersCount > 0 || $pendingViolations > 0): ?>
            <div class="action-cards">
                <?php if ($pendingTradersCount > 0): ?>
                <div class="action-card urgent">
                    <div class="action-icon">
                        <i class="fas fa-store"></i>
                    </div>
                    <div class="action-content">
                        <h4><?php echo $pendingTradersCount; ?> Trader<?php echo $pendingTradersCount > 1 ? 's' : ''; ?> Awaiting Approval</h4>
                        <p>New trader registrations need your review.</p>
                        <a href="traders.php" class="btn-primary">Review Now</a>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($pendingViolations > 0): ?>
                <div class="action-card warning">
                    <div class="action-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="action-content">
                        <h4><?php echo $pendingViolations; ?> Pending Violation<?php echo $pendingViolations > 1 ? 's' : ''; ?></h4>
                        <p>Violation reports require your attention.</p>
                        <a href="violations.php" class="btn-warning">Handle Violations</a>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Dashboard Grid -->
            <div class="dashboard-grid">
                <!-- Monthly Sales Chart -->
                <div class="dashboard-card chart-card">
                    <div class="card-header">
                        <h3><i class="fas fa-chart-line"></i> Monthly Sales Trend</h3>
                    </div>
                    <div class="card-content">
                        <canvas id="salesChart" width="400" height="200"></canvas>
                    </div>
                </div>

                <!-- Top Products -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3><i class="fas fa-star"></i> Top Selling Products</h3>
                    </div>
                    <div class="card-content">
                        <?php if (empty($topProducts)): ?>
                            <div class="empty-state">
                                <i class="fas fa-box-open"></i>
                                <p>No sales data available yet</p>
                            </div>
                        <?php else: ?>
                            <div class="top-products-list">
                                <?php foreach ($topProducts as $product): ?>
                                    <div class="top-product-item">
                                        <div class="product-info">
                                            <strong><?php echo htmlspecialchars($product['product_name']); ?></strong>
                                            <small>by <?php echo htmlspecialchars($product['shop_name']); ?></small>
                                        </div>
                                        <div class="product-stats">
                                            <span class="sold-count"><?php echo $product['total_sold']; ?> sold</span>
                                            <span class="revenue"><?php echo formatPrice($product['total_revenue']); ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Orders -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3><i class="fas fa-shopping-cart"></i> Recent Orders</h3>
                        <a href="orders.php" class="view-all-link">View All</a>
                    </div>
                    <div class="card-content">
                        <?php if (empty($recentOrders)): ?>
                            <div class="empty-state">
                                <i class="fas fa-shopping-cart"></i>
                                <p>No recent orders</p>
                            </div>
                        <?php else: ?>
                            <div class="recent-orders-list">
                                <?php foreach ($recentOrders as $order): ?>
                                    <div class="recent-order-item">
                                        <div class="order-info">
                                            <strong>#<?php echo $order['order_id']; ?></strong>
                                            <span class="customer-name"><?php echo htmlspecialchars($order['customer_name']); ?></span>
                                        </div>
                                        <div class="order-details">
                                            <span class="order-amount"><?php echo formatPrice($order['total_amount']); ?></span>
                                            <span class="order-status status-<?php echo $order['order_status']; ?>">
                                                <?php echo ucfirst($order['order_status']); ?>
                                            </span>
                                            <small class="order-date"><?php echo formatDateTime($order['created_at']); ?></small>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
                    </div>
                    <div class="card-content">
                        <div class="quick-actions">
                            <a href="traders.php" class="quick-action">
                                <i class="fas fa-store"></i>
                                <span>Manage Traders</span>
                            </a>
                            <a href="violations.php" class="quick-action">
                                <i class="fas fa-exclamation-triangle"></i>
                                <span>Handle Violations</span>
                            </a>
                            <a href="reports.php" class="quick-action">
                                <i class="fas fa-chart-bar"></i>
                                <span>View Reports</span>
                            </a>
                            <a href="users.php" class="quick-action">
                                <i class="fas fa-users"></i>
                                <span>Manage Users</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="../js/main.js"></script>
    <script>
        // Update current time
        function updateTime() {
            const now = new Date();
            const timeString = now.toLocaleString('en-US', {
                weekday: 'short',
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
            document.getElementById('currentTime').textContent = timeString;
        }
        
        updateTime();
        setInterval(updateTime, 60000); // Update every minute

        // Sales Chart
        const salesData = <?php echo json_encode($monthlySales); ?>;
        const ctx = document.getElementById('salesChart').getContext('2d');
        
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: salesData.map(item => {
                    const date = new Date(item.month + '-01');
                    return date.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
                }),
                datasets: [{
                    label: 'Revenue',
                    data: salesData.map(item => item.revenue),
                    borderColor: '#007bff',
                    backgroundColor: 'rgba(0, 123, 255, 0.1)',
                    tension: 0.4,
                    fill: true
                }, {
                    label: 'Orders',
                    data: salesData.map(item => item.orders),
                    borderColor: '#28a745',
                    backgroundColor: 'rgba(40, 167, 69, 0.1)',
                    tension: 0.4,
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Revenue ($)'
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Orders'
                        },
                        grid: {
                            drawOnChartArea: false
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
                    }
                }
            }
        });
    </script>
</body>
</html>
