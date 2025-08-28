<?php
// admin/reports.php - Admin Reports Dashboard
require_once '../config/database.php';
require_once '../includes/session.php';
require_once '../includes/functions.php';

// Require admin access
requireAdmin();

$error = '';
$success = '';

// Get date range from URL parameters
$startDate = $_GET['start_date'] ?? date('Y-m-01'); // First day of current month
$endDate = $_GET['end_date'] ?? date('Y-m-t'); // Last day of current month
$period = $_GET['period'] ?? 'month';

// Validate dates
if (!$startDate) $startDate = date('Y-m-01');
if (!$endDate) $endDate = date('Y-m-t');

try {
    // Overall Platform Statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT o.order_id) as total_orders,
            SUM(o.total_amount) as total_revenue,
            AVG(o.total_amount) as avg_order_value,
            COUNT(DISTINCT o.customer_id) as unique_customers,
            SUM(o.discount_amount) as total_discounts
        FROM orders o
        WHERE o.payment_status = 'completed'
        AND DATE(o.created_at) BETWEEN ? AND ?
    ");
    $stmt->execute([$startDate, $endDate]);
    $overallStats = $stmt->fetch();
    
    // Platform Commission (5% of all sales)
    $platformCommission = ($overallStats['total_revenue'] ?? 0) * 0.05;
    
    // Top Performing Traders
    $stmt = $pdo->prepare("
        SELECT 
            u.full_name as trader_name,
            s.shop_name,
            COUNT(DISTINCT o.order_id) as orders,
            SUM(oi.subtotal) as revenue,
            AVG(oi.subtotal) as avg_order_value,
            COUNT(DISTINCT o.customer_id) as unique_customers
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.order_id
        JOIN shops s ON oi.shop_id = s.shop_id
        JOIN users u ON s.trader_id = u.user_id
        WHERE o.payment_status = 'completed'
        AND DATE(o.created_at) BETWEEN ? AND ?
        GROUP BY s.shop_id, u.full_name, s.shop_name
        ORDER BY revenue DESC
        LIMIT 10
    ");
    $stmt->execute([$startDate, $endDate]);
    $topTraders = $stmt->fetchAll();
    
    // Top Selling Products
    $stmt = $pdo->prepare("
        SELECT 
            p.product_name,
            s.shop_name,
            u.full_name as trader_name,
            SUM(oi.quantity) as units_sold,
            SUM(oi.subtotal) as revenue,
            AVG(p.rating) as avg_rating
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.order_id
        JOIN products p ON oi.product_id = p.product_id
        JOIN shops s ON p.shop_id = s.shop_id
        JOIN users u ON s.trader_id = u.user_id
        WHERE o.payment_status = 'completed'
        AND DATE(o.created_at) BETWEEN ? AND ?
        GROUP BY p.product_id, p.product_name, s.shop_name, u.full_name
        ORDER BY units_sold DESC
        LIMIT 10
    ");
    $stmt->execute([$startDate, $endDate]);
    $topProducts = $stmt->fetchAll();
    
    // Customer Analytics
    $stmt = $pdo->prepare("
        SELECT 
            u.full_name,
            u.email,
            COUNT(DISTINCT o.order_id) as orders,
            SUM(o.total_amount) as total_spent,
            AVG(o.total_amount) as avg_order_value,
            MAX(o.created_at) as last_order
        FROM orders o
        JOIN users u ON o.customer_id = u.user_id
        WHERE o.payment_status = 'completed'
        AND DATE(o.created_at) BETWEEN ? AND ?
        GROUP BY u.user_id, u.full_name, u.email
        ORDER BY total_spent DESC
        LIMIT 10
    ");
    $stmt->execute([$startDate, $endDate]);
    $topCustomers = $stmt->fetchAll();
    
    // Daily Sales for Chart
    $stmt = $pdo->prepare("
        SELECT 
            DATE(o.created_at) as sale_date,
            COUNT(DISTINCT o.order_id) as orders,
            SUM(o.total_amount) as revenue,
            COUNT(DISTINCT o.customer_id) as customers
        FROM orders o
        WHERE o.payment_status = 'completed'
        AND DATE(o.created_at) BETWEEN ? AND ?
        GROUP BY DATE(o.created_at)
        ORDER BY sale_date
    ");
    $stmt->execute([$startDate, $endDate]);
    $dailySales = $stmt->fetchAll();
    
    // Category Performance
    $stmt = $pdo->prepare("
        SELECT 
            c.category_name,
            COUNT(DISTINCT oi.product_id) as products_sold,
            SUM(oi.quantity) as units_sold,
            SUM(oi.subtotal) as revenue
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.order_id
        JOIN products p ON oi.product_id = p.product_id
        JOIN categories c ON p.category_id = c.category_id
        WHERE o.payment_status = 'completed'
        AND DATE(o.created_at) BETWEEN ? AND ?
        GROUP BY c.category_id, c.category_name
        ORDER BY revenue DESC
    ");
    $stmt->execute([$startDate, $endDate]);
    $categoryStats = $stmt->fetchAll();
    
    // Promo Code Usage
    $stmt = $pdo->prepare("
        SELECT 
            o.promo_code,
            COUNT(*) as usage_count,
            SUM(o.discount_amount) as total_discount,
            AVG(o.total_amount) as avg_order_value
        FROM orders o
        WHERE o.promo_code IS NOT NULL 
        AND o.payment_status = 'completed'
        AND DATE(o.created_at) BETWEEN ? AND ?
        GROUP BY o.promo_code
        ORDER BY usage_count DESC
        LIMIT 10
    ");
    $stmt->execute([$startDate, $endDate]);
    $promoStats = $stmt->fetchAll();
    
    // User Growth
    $stmt = $pdo->prepare("
        SELECT 
            DATE(created_at) as date,
            COUNT(*) as new_users,
            SUM(CASE WHEN role = 'customer' THEN 1 ELSE 0 END) as new_customers,
            SUM(CASE WHEN role = 'trader' THEN 1 ELSE 0 END) as new_traders
        FROM users
        WHERE DATE(created_at) BETWEEN ? AND ?
        GROUP BY DATE(created_at)
        ORDER BY date
    ");
    $stmt->execute([$startDate, $endDate]);
    $userGrowth = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error = "Failed to load reports: " . $e->getMessage();
    // Initialize empty arrays to prevent errors
    $overallStats = ['total_orders' => 0, 'total_revenue' => 0, 'avg_order_value' => 0, 'unique_customers' => 0, 'total_discounts' => 0];
    $topTraders = [];
    $topProducts = [];
    $topCustomers = [];
    $dailySales = [];
    $categoryStats = [];
    $promoStats = [];
    $userGrowth = [];
    $platformCommission = 0;
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
    <title>Reports - Admin Dashboard</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/theme.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            <li>
                <a href="orders.php">
                    <i class="fas fa-shopping-bag"></i> Orders
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
                </a>
            </li>
            <li class="active">
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
                <h1><i class="fas fa-chart-bar"></i> Analytics & Reports</h1>
                <p>Comprehensive platform performance insights</p>
            </div>
            <div class="topbar-right">
                <div class="date-range-info">
                    <i class="fas fa-calendar"></i>
                    <?php echo date('M j, Y', strtotime($startDate)); ?> - <?php echo date('M j, Y', strtotime($endDate)); ?>
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

            <!-- Filter Controls -->
            <div class="filter-controls">
                <form method="GET" class="filter-form">
                    <div class="filter-group">
                        <label>Period:</label>
                        <select name="period" onchange="setPeriod(this.value)">
                            <option value="today" <?php echo $period === 'today' ? 'selected' : ''; ?>>Today</option>
                            <option value="week" <?php echo $period === 'week' ? 'selected' : ''; ?>>This Week</option>
                            <option value="month" <?php echo $period === 'month' ? 'selected' : ''; ?>>This Month</option>
                            <option value="quarter" <?php echo $period === 'quarter' ? 'selected' : ''; ?>>This Quarter</option>
                            <option value="year" <?php echo $period === 'year' ? 'selected' : ''; ?>>This Year</option>
                            <option value="custom" <?php echo $period === 'custom' ? 'selected' : ''; ?>>Custom Range</option>
                        </select>
                    </div>
                    
                    <div class="filter-group custom-range" style="<?php echo $period === 'custom' ? '' : 'display: none;'; ?>">
                        <label>From:</label>
                        <input type="date" name="start_date" value="<?php echo $startDate; ?>">
                    </div>
                    
                    <div class="filter-group custom-range" style="<?php echo $period === 'custom' ? '' : 'display: none;'; ?>">
                        <label>To:</label>
                        <input type="date" name="end_date" value="<?php echo $endDate; ?>">
                    </div>
                    
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-search"></i> Apply Filter
                    </button>
                    
                    <a href="?period=month" class="btn-secondary">
                        <i class="fas fa-refresh"></i> Reset
                    </a>
                </form>
            </div>

            <!-- Overview Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-shopping-bag"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo number_format($overallStats['total_orders']); ?></h3>
                        <p>Total Orders</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon revenue">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo formatPrice($overallStats['total_revenue']); ?></h3>
                        <p>Total Revenue</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon commission">
                        <i class="fas fa-percentage"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo formatPrice($platformCommission); ?></h3>
                        <p>Platform Commission (5%)</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon customers">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo number_format($overallStats['unique_customers']); ?></h3>
                        <p>Unique Customers</p>
                    </div>
                </div>
            </div>

            <!-- Charts Section -->
            <div class="charts-section">
                <div class="chart-container">
                    <div class="chart-header">
                        <h3><i class="fas fa-chart-line"></i> Sales Trend</h3>
                    </div>
                    <div class="chart-content">
                        <canvas id="salesChart" width="400" height="200"></canvas>
                    </div>
                </div>
            </div>

            <!-- Analytics Grid -->
            <div class="analytics-grid">
                <!-- Top Traders -->
                <div class="analytics-card">
                    <div class="analytics-header">
                        <h3><i class="fas fa-crown"></i> Top Performing Traders</h3>
                    </div>
                    <div class="analytics-content">
                        <?php if (empty($topTraders)): ?>
                            <div class="empty-state">
                                <i class="fas fa-store"></i>
                                <p>No trader data available for this period</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="analytics-table">
                                    <thead>
                                        <tr>
                                            <th>Trader/Shop</th>
                                            <th>Orders</th>
                                            <th>Revenue</th>
                                            <th>Customers</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($topTraders as $index => $trader): ?>
                                            <tr>
                                                <td>
                                                    <div class="trader-info">
                                                        <div class="rank">#<?php echo $index + 1; ?></div>
                                                        <div class="details">
                                                            <strong><?php echo htmlspecialchars($trader['trader_name']); ?></strong>
                                                            <small><?php echo htmlspecialchars($trader['shop_name']); ?></small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td><?php echo number_format($trader['orders']); ?></td>
                                                <td><strong><?php echo formatPrice($trader['revenue']); ?></strong></td>
                                                <td><?php echo number_format($trader['unique_customers']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Top Products -->
                <div class="analytics-card">
                    <div class="analytics-header">
                        <h3><i class="fas fa-trophy"></i> Best Selling Products</h3>
                    </div>
                    <div class="analytics-content">
                        <?php if (empty($topProducts)): ?>
                            <div class="empty-state">
                                <i class="fas fa-box"></i>
                                <p>No product data available for this period</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="analytics-table">
                                    <thead>
                                        <tr>
                                            <th>Product</th>
                                            <th>Units Sold</th>
                                            <th>Revenue</th>
                                            <th>Rating</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($topProducts as $index => $product): ?>
                                            <tr>
                                                <td>
                                                    <div class="product-info">
                                                        <div class="rank">#<?php echo $index + 1; ?></div>
                                                        <div class="details">
                                                            <strong><?php echo htmlspecialchars($product['product_name']); ?></strong>
                                                            <small><?php echo htmlspecialchars($product['shop_name']); ?></small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td><?php echo number_format($product['units_sold']); ?></td>
                                                <td><strong><?php echo formatPrice($product['revenue']); ?></strong></td>
                                                <td>
                                                    <div class="rating">
                                                        <?php
                                                        $rating = round($product['avg_rating'] ?? 0, 1);
                                                        for ($i = 1; $i <= 5; $i++):
                                                            if ($i <= $rating):
                                                        ?>
                                                            <i class="fas fa-star"></i>
                                                        <?php else: ?>
                                                            <i class="far fa-star"></i>
                                                        <?php endif; endfor; ?>
                                                        <span><?php echo $rating; ?></span>
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

                <!-- Top Customers -->
                <div class="analytics-card">
                    <div class="analytics-header">
                        <h3><i class="fas fa-star"></i> Top Customers</h3>
                    </div>
                    <div class="analytics-content">
                        <?php if (empty($topCustomers)): ?>
                            <div class="empty-state">
                                <i class="fas fa-users"></i>
                                <p>No customer data available for this period</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="analytics-table">
                                    <thead>
                                        <tr>
                                            <th>Customer</th>
                                            <th>Orders</th>
                                            <th>Total Spent</th>
                                            <th>Avg Order</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($topCustomers as $index => $customer): ?>
                                            <tr>
                                                <td>
                                                    <div class="customer-info">
                                                        <div class="rank">#<?php echo $index + 1; ?></div>
                                                        <div class="details">
                                                            <strong><?php echo htmlspecialchars($customer['full_name']); ?></strong>
                                                            <small><?php echo htmlspecialchars($customer['email']); ?></small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td><?php echo number_format($customer['orders']); ?></td>
                                                <td><strong><?php echo formatPrice($customer['total_spent']); ?></strong></td>
                                                <td><?php echo formatPrice($customer['avg_order_value']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Category Performance -->
                <div class="analytics-card">
                    <div class="analytics-header">
                        <h3><i class="fas fa-tags"></i> Category Performance</h3>
                    </div>
                    <div class="analytics-content">
                        <?php if (empty($categoryStats)): ?>
                            <div class="empty-state">
                                <i class="fas fa-tags"></i>
                                <p>No category data available for this period</p>
                            </div>
                        <?php else: ?>
                            <div class="category-stats">
                                <?php foreach ($categoryStats as $category): ?>
                                    <div class="category-item">
                                        <div class="category-name"><?php echo htmlspecialchars($category['category_name']); ?></div>
                                        <div class="category-metrics">
                                            <span><?php echo number_format($category['units_sold']); ?> units</span>
                                            <span><?php echo formatPrice($category['revenue']); ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Promo Code Performance -->
            <?php if (!empty($promoStats)): ?>
                <div class="promo-section">
                    <div class="analytics-card">
                        <div class="analytics-header">
                            <h3><i class="fas fa-percentage"></i> Promo Code Performance</h3>
                        </div>
                        <div class="analytics-content">
                            <div class="promo-grid">
                                <?php foreach ($promoStats as $promo): ?>
                                    <div class="promo-item">
                                        <div class="promo-code"><?php echo htmlspecialchars($promo['promo_code']); ?></div>
                                        <div class="promo-stats">
                                            <span><?php echo number_format($promo['usage_count']); ?> uses</span>
                                            <span><?php echo formatPrice($promo['total_discount']); ?> saved</span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script src="../js/main.js"></script>
    <script>
        // Sales Chart
        const ctx = document.getElementById('salesChart').getContext('2d');
        const salesData = <?php echo json_encode($dailySales); ?>;
        
        const labels = salesData.map(item => {
            const date = new Date(item.sale_date);
            return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
        });
        
        const revenueData = salesData.map(item => parseFloat(item.revenue));
        const ordersData = salesData.map(item => parseInt(item.orders));
        
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Revenue ($)',
                    data: revenueData,
                    borderColor: '#007bff',
                    backgroundColor: 'rgba(0, 123, 255, 0.1)',
                    tension: 0.4,
                    yAxisID: 'y'
                }, {
                    label: 'Orders',
                    data: ordersData,
                    borderColor: '#28a745',
                    backgroundColor: 'rgba(40, 167, 69, 0.1)',
                    tension: 0.4,
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                scales: {
                    x: {
                        display: true,
                        title: {
                            display: true,
                            text: 'Date'
                        }
                    },
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
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
                            drawOnChartArea: false,
                        },
                    }
                },
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                if (context.dataset.label === 'Revenue ($)') {
                                    label += '$' + context.parsed.y.toFixed(2);
                                } else {
                                    label += context.parsed.y;
                                }
                                return label;
                            }
                        }
                    }
                }
            }
        });
        
        // Period selector
        function setPeriod(period) {
            const customRanges = document.querySelectorAll('.custom-range');
            customRanges.forEach(range => {
                range.style.display = period === 'custom' ? '' : 'none';
            });
            
            if (period !== 'custom') {
                // Auto-submit for predefined periods
                const form = document.querySelector('.filter-form');
                const today = new Date();
                let startDate, endDate;
                
                switch (period) {
                    case 'today':
                        startDate = endDate = today.toISOString().split('T')[0];
                        break;
                    case 'week':
                        const weekStart = new Date(today.setDate(today.getDate() - today.getDay()));
                        const weekEnd = new Date(today.setDate(today.getDate() - today.getDay() + 6));
                        startDate = weekStart.toISOString().split('T')[0];
                        endDate = weekEnd.toISOString().split('T')[0];
                        break;
                    case 'month':
                        startDate = new Date(today.getFullYear(), today.getMonth(), 1).toISOString().split('T')[0];
                        endDate = new Date(today.getFullYear(), today.getMonth() + 1, 0).toISOString().split('T')[0];
                        break;
                    case 'quarter':
                        const quarter = Math.floor(today.getMonth() / 3);
                        startDate = new Date(today.getFullYear(), quarter * 3, 1).toISOString().split('T')[0];
                        endDate = new Date(today.getFullYear(), quarter * 3 + 3, 0).toISOString().split('T')[0];
                        break;
                    case 'year':
                        startDate = new Date(today.getFullYear(), 0, 1).toISOString().split('T')[0];
                        endDate = new Date(today.getFullYear(), 11, 31).toISOString().split('T')[0];
                        break;
                }
                
                if (startDate && endDate) {
                    form.querySelector('input[name="start_date"]').value = startDate;
                    form.querySelector('input[name="end_date"]').value = endDate;
                    form.submit();
                }
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
        /* Reports Page Styles */
        .date-range-info {
            background: #f8f9fa;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.9rem;
            color: #666;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .filter-controls {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .filter-form {
            display: flex;
            gap: 1rem;
            align-items: end;
            flex-wrap: wrap;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .filter-group label {
            font-weight: 500;
            color: #333;
            font-size: 0.9rem;
        }
        
        .filter-group select,
        .filter-group input {
            padding: 0.5rem;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 0.9rem;
        }
        
        .stat-icon.revenue {
            background: #d4edda;
            color: #155724;
        }
        
        .stat-icon.commission {
            background: #fff3cd;
            color: #856404;
        }
        
        .stat-icon.customers {
            background: #e7f3ff;
            color: #0c5460;
        }
        
        .charts-section {
            margin: 2rem 0;
        }
        
        .chart-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .chart-header {
            padding: 1.5rem;
            border-bottom: 1px solid #eee;
        }
        
        .chart-header h3 {
            color: #333;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .chart-content {
            padding: 1.5rem;
            height: 300px;
        }
        
        .analytics-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin: 2rem 0;
        }
        
        .analytics-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .analytics-header {
            padding: 1.5rem;
            border-bottom: 1px solid #eee;
        }
        
        .analytics-header h3 {
            color: #333;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .analytics-content {
            padding: 1.5rem;
        }
        
        .empty-state {
            text-align: center;
            padding: 2rem;
            color: #666;
        }
        
        .empty-state i {
            font-size: 2rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
        
        .analytics-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .analytics-table th,
        .analytics-table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .analytics-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
            font-size: 0.9rem;
        }
        
        .analytics-table td {
            font-size: 0.9rem;
        }
        
        .trader-info,
        .product-info,
        .customer-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .rank {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: #007bff;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 0.8rem;
        }
        
        .details strong {
            display: block;
            color: #333;
        }
        
        .details small {
            color: #666;
            font-size: 0.8rem;
        }
        
        .rating {
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }
        
        .rating i {
            color: #ffc107;
            font-size: 0.8rem;
        }
        
        .rating span {
            font-size: 0.8rem;
            color: #666;
        }
        
        .category-stats {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        .category-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .category-name {
            font-weight: 500;
            color: #333;
        }
        
        .category-metrics {
            display: flex;
            flex-direction: column;
            text-align: right;
            gap: 0.25rem;
        }
        
        .category-metrics span {
            font-size: 0.8rem;
            color: #666;
        }
        
        .promo-section {
            margin: 2rem 0;
        }
        
        .promo-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }
        
        .promo-item {
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 8px;
            text-align: center;
        }
        
        .promo-code {
            font-family: 'Courier New', monospace;
            font-weight: bold;
            color: #007bff;
            margin-bottom: 0.5rem;
        }
        
        .promo-stats {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }
        
        .promo-stats span {
            font-size: 0.8rem;
            color: #666;
        }
        
        @media (max-width: 768px) {
            .filter-form {
                flex-direction: column;
                align-items: stretch;
            }
            
            .analytics-grid {
                grid-template-columns: 1fr;
            }
            
            .promo-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</body>
</html>
