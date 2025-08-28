<?php
// trader/reports.php - Trader Sales Reports
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

// Get date range from URL parameters
$startDate = $_GET['start_date'] ?? date('Y-m-01'); // First day of current month
$endDate = $_GET['end_date'] ?? date('Y-m-t'); // Last day of current month
$period = $_GET['period'] ?? 'month';

// Validate dates
if (!$startDate) $startDate = date('Y-m-01');
if (!$endDate) $endDate = date('Y-m-t');

try {
    if ($traderShop) {
        // Get sales summary
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(DISTINCT o.order_id) as total_orders,
                SUM(oi.quantity) as items_sold,
                SUM(oi.subtotal) as gross_revenue,
                AVG(oi.subtotal) as avg_order_value,
                COUNT(DISTINCT o.customer_id) as unique_customers
            FROM order_items oi
            JOIN orders o ON oi.order_id = o.order_id
            WHERE oi.shop_id = ? 
            AND o.payment_status = 'completed'
            AND DATE(o.created_at) BETWEEN ? AND ?
        ");
        $stmt->execute([$traderShop['shop_id'], $startDate, $endDate]);
        $salesSummary = $stmt->fetch();
        
        // Get top selling products
        $stmt = $pdo->prepare("
            SELECT 
                p.product_name,
                p.price,
                SUM(oi.quantity) as units_sold,
                SUM(oi.subtotal) as revenue,
                AVG(p.rating) as avg_rating
            FROM order_items oi
            JOIN orders o ON oi.order_id = o.order_id
            JOIN products p ON oi.product_id = p.product_id
            WHERE oi.shop_id = ? 
            AND o.payment_status = 'completed'
            AND DATE(o.created_at) BETWEEN ? AND ?
            GROUP BY p.product_id, p.product_name, p.price
            ORDER BY units_sold DESC
            LIMIT 10
        ");
        $stmt->execute([$traderShop['shop_id'], $startDate, $endDate]);
        $topProducts = $stmt->fetchAll();
        
        // Get daily sales for chart
        $stmt = $pdo->prepare("
            SELECT 
                DATE(o.created_at) as sale_date,
                COUNT(DISTINCT o.order_id) as orders,
                SUM(oi.quantity) as items,
                SUM(oi.subtotal) as revenue
            FROM order_items oi
            JOIN orders o ON oi.order_id = o.order_id
            WHERE oi.shop_id = ? 
            AND o.payment_status = 'completed'
            AND DATE(o.created_at) BETWEEN ? AND ?
            GROUP BY DATE(o.created_at)
            ORDER BY sale_date
        ");
        $stmt->execute([$traderShop['shop_id'], $startDate, $endDate]);
        $dailySales = $stmt->fetchAll();
        
        // Get customer breakdown
        $stmt = $pdo->prepare("
            SELECT 
                u.full_name,
                u.email,
                COUNT(DISTINCT o.order_id) as orders,
                SUM(oi.quantity) as items_purchased,
                SUM(oi.subtotal) as total_spent,
                MAX(o.created_at) as last_order
            FROM order_items oi
            JOIN orders o ON oi.order_id = o.order_id
            JOIN users u ON o.customer_id = u.user_id
            WHERE oi.shop_id = ? 
            AND o.payment_status = 'completed'
            AND DATE(o.created_at) BETWEEN ? AND ?
            GROUP BY u.user_id, u.full_name, u.email
            ORDER BY total_spent DESC
            LIMIT 10
        ");
        $stmt->execute([$traderShop['shop_id'], $startDate, $endDate]);
        $topCustomers = $stmt->fetchAll();
        
        // Get order status breakdown
        $stmt = $pdo->prepare("
            SELECT 
                o.order_status,
                COUNT(DISTINCT o.order_id) as count,
                SUM(oi.subtotal) as revenue
            FROM order_items oi
            JOIN orders o ON oi.order_id = o.order_id
            WHERE oi.shop_id = ? 
            AND o.payment_status = 'completed'
            AND DATE(o.created_at) BETWEEN ? AND ?
            GROUP BY o.order_status
            ORDER BY count DESC
        ");
        $stmt->execute([$traderShop['shop_id'], $startDate, $endDate]);
        $orderStatuses = $stmt->fetchAll();
        
    }
} catch (PDOException $e) {
    $error = "Failed to load reports: " . $e->getMessage();
    $salesSummary = ['total_orders' => 0, 'items_sold' => 0, 'gross_revenue' => 0, 'avg_order_value' => 0, 'unique_customers' => 0];
    $topProducts = [];
    $dailySales = [];
    $topCustomers = [];
    $orderStatuses = [];
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
    <title>Sales Reports - Trader Dashboard</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/theme.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
    <main class="trader-main">
        <!-- Top Bar -->
        <div class="trader-topbar">
            <div class="topbar-left">
                <h1><i class="fas fa-chart-bar"></i> Sales Reports</h1>
                <p>Analyze your sales performance and trends</p>
            </div>
            <div class="topbar-right">
                <div class="date-range-info">
                    <i class="fas fa-calendar"></i>
                    <?php echo date('M j, Y', strtotime($startDate)); ?> - <?php echo date('M j, Y', strtotime($endDate)); ?>
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

            <?php if (!$traderShop): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    No shop assigned to your account. Please contact administrator to set up your shop.
                </div>
            <?php else: ?>

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

            <!-- Sales Summary Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-shopping-bag"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo number_format($salesSummary['total_orders'] ?? 0); ?></h3>
                        <p>Total Orders</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-box"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo number_format($salesSummary['items_sold'] ?? 0); ?></h3>
                        <p>Items Sold</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo formatPrice($salesSummary['gross_revenue'] ?? 0); ?></h3>
                        <p>Gross Revenue</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo number_format($salesSummary['unique_customers'] ?? 0); ?></h3>
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

            <!-- Data Tables -->
            <div class="reports-grid">
                <!-- Top Products -->
                <div class="report-card">
                    <div class="report-header">
                        <h3><i class="fas fa-trophy"></i> Top Selling Products</h3>
                    </div>
                    <div class="report-content">
                        <?php if (empty($topProducts)): ?>
                            <div class="empty-state">
                                <i class="fas fa-box-open"></i>
                                <p>No sales data available for this period</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Product</th>
                                            <th>Units Sold</th>
                                            <th>Revenue</th>
                                            <th>Rating</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($topProducts as $product): ?>
                                            <tr>
                                                <td>
                                                    <div class="product-info">
                                                        <strong><?php echo htmlspecialchars($product['product_name']); ?></strong>
                                                        <small><?php echo formatPrice($product['price']); ?> each</small>
                                                    </div>
                                                </td>
                                                <td><strong><?php echo number_format($product['units_sold']); ?></strong></td>
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
                <div class="report-card">
                    <div class="report-header">
                        <h3><i class="fas fa-users"></i> Top Customers</h3>
                    </div>
                    <div class="report-content">
                        <?php if (empty($topCustomers)): ?>
                            <div class="empty-state">
                                <i class="fas fa-user-friends"></i>
                                <p>No customer data available for this period</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Customer</th>
                                            <th>Orders</th>
                                            <th>Items</th>
                                            <th>Total Spent</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($topCustomers as $customer): ?>
                                            <tr>
                                                <td>
                                                    <div class="customer-info">
                                                        <strong><?php echo htmlspecialchars($customer['full_name']); ?></strong>
                                                        <small><?php echo htmlspecialchars($customer['email']); ?></small>
                                                    </div>
                                                </td>
                                                <td><?php echo number_format($customer['orders']); ?></td>
                                                <td><?php echo number_format($customer['items_purchased']); ?></td>
                                                <td><strong><?php echo formatPrice($customer['total_spent']); ?></strong></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Order Status Breakdown -->
            <?php if (!empty($orderStatuses)): ?>
                <div class="status-breakdown">
                    <div class="report-card">
                        <div class="report-header">
                            <h3><i class="fas fa-chart-pie"></i> Order Status Breakdown</h3>
                        </div>
                        <div class="report-content">
                            <div class="status-grid">
                                <?php foreach ($orderStatuses as $status): ?>
                                    <div class="status-item">
                                        <div class="status-info">
                                            <h4><?php echo ucfirst($status['order_status']); ?></h4>
                                            <div class="status-stats">
                                                <span class="orders"><?php echo number_format($status['count']); ?> orders</span>
                                                <span class="revenue"><?php echo formatPrice($status['revenue']); ?></span>
                                            </div>
                                        </div>
                                        <div class="status-icon status-<?php echo $status['order_status']; ?>">
                                            <?php
                                            $icons = [
                                                'pending' => 'fas fa-clock',
                                                'processing' => 'fas fa-cog',
                                                'shipped' => 'fas fa-truck',
                                                'delivered' => 'fas fa-check-circle',
                                                'cancelled' => 'fas fa-times-circle'
                                            ];
                                            ?>
                                            <i class="<?php echo $icons[$status['order_status']] ?? 'fas fa-circle'; ?>"></i>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

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
        
        .reports-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin: 2rem 0;
        }
        
        .report-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .report-header {
            padding: 1.5rem;
            border-bottom: 1px solid #eee;
        }
        
        .report-header h3 {
            color: #333;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .report-content {
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
        
        .table-responsive {
            overflow-x: auto;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .data-table th,
        .data-table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .data-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
            font-size: 0.9rem;
        }
        
        .data-table td {
            font-size: 0.9rem;
        }
        
        .product-info,
        .customer-info {
            display: flex;
            flex-direction: column;
            gap: 0.2rem;
        }
        
        .product-info small,
        .customer-info small {
            color: #666;
            font-size: 0.8rem;
        }
        
        .rating {
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }
        
        .rating i {
            color: #ffc107;
            font-size: 0.8rem;
        }
        
        .rating span {
            font-size: 0.8rem;
            color: #666;
        }
        
        .status-breakdown {
            margin: 2rem 0;
        }
        
        .status-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }
        
        .status-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .status-info h4 {
            margin: 0 0 0.5rem 0;
            color: #333;
        }
        
        .status-stats {
            display: flex;
            flex-direction: column;
            gap: 0.2rem;
        }
        
        .status-stats span {
            font-size: 0.8rem;
            color: #666;
        }
        
        .status-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }
        
        .status-icon.status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-icon.status-processing {
            background: #e7f3ff;
            color: #0c5460;
        }
        
        .status-icon.status-shipped {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .status-icon.status-delivered {
            background: #d4edda;
            color: #155724;
        }
        
        .status-icon.status-cancelled {
            background: #f8d7da;
            color: #721c24;
        }
        
        @media (max-width: 768px) {
            .filter-form {
                flex-direction: column;
                align-items: stretch;
            }
            
            .reports-grid {
                grid-template-columns: 1fr;
            }
            
            .status-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</body>
</html>
