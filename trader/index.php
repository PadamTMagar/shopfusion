<?php
// trader/index.php - Trader Dashboard
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

$success = '';
$error = '';

// Handle form submissions
if ($_POST) {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_product':
                if ($traderShop) {
                    $productName = sanitize($_POST['product_name']);
                    $description = sanitize($_POST['description']);
                    $price = floatval($_POST['price']);
                    $stockQuantity = intval($_POST['stock_quantity']);
                    $categoryId = intval($_POST['category_id']);
                    
                    // Handle image upload
                    $imagePath = '';
                    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] == 0) {
                        $uploadResult = uploadImage($_FILES['product_image']);
                        if ($uploadResult['success']) {
                            $imagePath = $uploadResult['path'];
                        } else {
                            $error = $uploadResult['message'];
                        }
                    }
                    
                    if (empty($error) && $productName && $price > 0 && $stockQuantity >= 0) {
                        try {
                            $stmt = $pdo->prepare("
                                INSERT INTO products (shop_id, category_id, product_name, description, price, stock_quantity, image_path) 
                                VALUES (?, ?, ?, ?, ?, ?, ?)
                            ");
                            
                            if ($stmt->execute([$traderShop['shop_id'], $categoryId, $productName, $description, $price, $stockQuantity, $imagePath])) {
                                $success = "Product added successfully!";
                                setFlashMessage('success', $success);
                            } else {
                                $error = "Failed to add product.";
                            }
                        } catch (PDOException $e) {
                            $error = "Database error: " . $e->getMessage();
                        }
                    } else if (empty($error)) {
                        $error = "Please fill all required fields correctly.";
                    }
                }
                break;
                
            case 'toggle_product':
                if (isset($_POST['product_id']) && $traderShop) {
                    $productId = intval($_POST['product_id']);
                    $newStatus = $_POST['status'] === 'active' ? 'inactive' : 'active';
                    
                    try {
                        $stmt = $pdo->prepare("
                            UPDATE products 
                            SET status = ? 
                            WHERE product_id = ? AND shop_id = ?
                        ");
                        
                        if ($stmt->execute([$newStatus, $productId, $traderShop['shop_id']])) {
                            $success = "Product status updated successfully!";
                            setFlashMessage('success', $success);
                        }
                    } catch (PDOException $e) {
                        $error = "Failed to update product status.";
                    }
                }
                break;
        }
    }
}

try {
    // Get trader statistics
    $traderStats = [];
    
    if ($traderShop) {
        // Total products
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM products WHERE shop_id = ?");
        $stmt->execute([$traderShop['shop_id']]);
        $traderStats['total_products'] = $stmt->fetch()['total'];
        
        // Active products
        $stmt = $pdo->prepare("SELECT COUNT(*) as active FROM products WHERE shop_id = ? AND status = 'active'");
        $stmt->execute([$traderShop['shop_id']]);
        $traderStats['active_products'] = $stmt->fetch()['active'];
        
        // Total orders (items sold)
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT oi.order_id) as total_orders, 
                   SUM(oi.quantity) as items_sold,
                   SUM(oi.subtotal) as total_revenue
            FROM order_items oi 
            JOIN orders o ON oi.order_id = o.order_id 
            WHERE oi.shop_id = ? AND o.payment_status = 'completed'
        ");
        $stmt->execute([$traderShop['shop_id']]);
        $orderStats = $stmt->fetch();
        $traderStats['total_orders'] = $orderStats['total_orders'] ?? 0;
        $traderStats['items_sold'] = $orderStats['items_sold'] ?? 0;
        $traderStats['total_revenue'] = $orderStats['total_revenue'] ?? 0;
        
        // Recent orders for trader
        $stmt = $pdo->prepare("
            SELECT o.order_id, o.created_at, o.order_status, o.total_amount,
                   u.full_name as customer_name, u.phone as customer_phone,
                   GROUP_CONCAT(CONCAT(p.product_name, ' (', oi.quantity, ')') SEPARATOR ', ') as products
            FROM orders o
            JOIN order_items oi ON o.order_id = oi.order_id
            JOIN products p ON oi.product_id = p.product_id
            JOIN users u ON o.customer_id = u.user_id
            WHERE oi.shop_id = ? AND o.payment_status = 'completed'
            GROUP BY o.order_id, o.created_at, o.order_status, o.total_amount, u.full_name, u.phone
            ORDER BY o.created_at DESC
            LIMIT 10
        ");
        $stmt->execute([$traderShop['shop_id']]);
        $recentOrders = $stmt->fetchAll();
        
        // Get trader's products
        $traderProducts = getTraderProducts($_SESSION['user_id']);
        
        // Monthly sales for chart
        $stmt = $pdo->prepare("
            SELECT DATE_FORMAT(o.created_at, '%Y-%m') as month, 
                   COUNT(DISTINCT o.order_id) as orders, 
                   SUM(oi.subtotal) as revenue
            FROM orders o
            JOIN order_items oi ON o.order_id = oi.order_id
            WHERE oi.shop_id = ? AND o.payment_status = 'completed'
            AND o.created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
            GROUP BY DATE_FORMAT(o.created_at, '%Y-%m')
            ORDER BY month
        ");
        $stmt->execute([$traderShop['shop_id']]);
        $monthlySales = $stmt->fetchAll();
        
        // Get categories for add product form
        $categories = getCategories();
    }
    
} catch (PDOException $e) {
    $error = "Failed to load dashboard data: " . $e->getMessage();
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
    <title>Trader Dashboard - ShopFusion</title>
    
    <link rel="stylesheet" href="../css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
</head>
<body class="trader-body">
    <!-- Trader Sidebar -->
    <nav class="trader-sidebar">
        <div class="sidebar-header">
            <h2><i class="fas fa-store"></i> Trader Panel</h2>
            <?php if ($traderShop): ?>
                <p class="shop-name"><?php echo htmlspecialchars($traderShop['shop_name']); ?></p>
            <?php endif; ?>
        </div>
        
        <ul class="sidebar-menu">
            <li class="active">
                <a href="index.php">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
            </li>
            <li>
                <a href="#products-section">
                    <i class="fas fa-box"></i> Products
                    <?php if (isset($traderStats['active_products'])): ?>
                        <span class="badge"><?php echo $traderStats['active_products']; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li>
                <a href="#add-product-section">
                    <i class="fas fa-plus"></i> Add Product
                </a>
            </li>
            <li>
                <a href="#orders-section">
                    <i class="fas fa-shopping-bag"></i> Orders
                    <?php if (isset($traderStats['total_orders']) && $traderStats['total_orders'] > 0): ?>
                        <span class="badge"><?php echo $traderStats['total_orders']; ?></span>
                    <?php endif; ?>
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
                <h1>Dashboard Overview</h1>
                <p>Welcome back, <?php echo htmlspecialchars($_SESSION['full_name']); ?>!</p>
            </div>
            <div class="topbar-right">
                <span class="current-time" id="currentTime"></span>
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
                        <i class="fas fa-box"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo number_format($traderStats['total_products']); ?></h3>
                        <p>Total Products</p>
                        <small class="stat-change">
                            <?php echo $traderStats['active_products']; ?> active
                        </small>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo number_format($traderStats['items_sold']); ?></h3>
                        <p>Items Sold</p>
                        <small class="stat-change positive">
                            <i class="fas fa-arrow-up"></i> <?php echo $traderStats['total_orders']; ?> orders
                        </small>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo formatPrice($traderStats['total_revenue']); ?></h3>
                        <p>Total Revenue</p>
                        <small class="stat-change positive">
                            <i class="fas fa-arrow-up"></i> Lifetime earnings
                        </small>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $traderStats['total_orders'] > 0 ? formatPrice($traderStats['total_revenue'] / $traderStats['total_orders']) : '$0.00'; ?></h3>
                        <p>Avg Order Value</p>
                        <small class="stat-change">
                            Per order average
                        </small>
                    </div>
                </div>
            </div>

            <!-- Dashboard Grid -->
            <div class="dashboard-grid">
                <!-- Add Product Form -->
                <div class="dashboard-card" id="add-product-section">
                    <div class="card-header">
                        <h3><i class="fas fa-plus"></i> Add New Product</h3>
                    </div>
                    <div class="card-content">
                        <form method="POST" enctype="multipart/form-data" class="add-product-form">
                            <input type="hidden" name="action" value="add_product">
                            
                            <div class="form-group">
                                <label for="product_name">Product Name *</label>
                                <input type="text" id="product_name" name="product_name" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="description">Description</label>
                                <textarea id="description" name="description" rows="3"></textarea>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="price">Price ($) *</label>
                                    <input type="number" id="price" name="price" step="0.01" min="0" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="stock_quantity">Stock Quantity *</label>
                                    <input type="number" id="stock_quantity" name="stock_quantity" min="0" required>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="category_id">Category *</label>
                                    <select id="category_id" name="category_id" required>
                                        <option value="">Select Category</option>
                                        <?php foreach ($categories as $category): ?>
                                            <option value="<?php echo $category['category_id']; ?>">
                                                <?php echo htmlspecialchars($category['category_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="product_image">Product Image</label>
                                    <input type="file" id="product_image" name="product_image" accept="image/*">
                                </div>
                            </div>
                            
                            <button type="submit" class="btn-primary">
                                <i class="fas fa-plus"></i> Add Product
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Products Management -->
                <div class="dashboard-card" id="products-section">
                    <div class="card-header">
                        <h3><i class="fas fa-box"></i> Your Products</h3>
                    </div>
                    <div class="card-content">
                        <?php if (empty($traderProducts)): ?>
                            <div class="empty-state">
                                <i class="fas fa-box-open"></i>
                                <p>No products added yet</p>
                                <a href="#add-product-section" class="btn-primary">Add Your First Product</a>
                            </div>
                        <?php else: ?>
                            <div class="products-table">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Product</th>
                                            <th>Price</th>
                                            <th>Stock</th>
                                            <th>Status</th>
                                            <th>Rating</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($traderProducts as $product): ?>
                                            <tr>
                                                <td>
                                                    <div class="product-info">
                                                        <strong><?php echo htmlspecialchars($product['product_name']); ?></strong>
                                                        <small><?php echo htmlspecialchars($product['category_name']); ?></small>
                                                    </div>
                                                </td>
                                                <td><?php echo formatPrice($product['price']); ?></td>
                                                <td>
                                                    <span class="stock-badge <?php echo $product['stock_quantity'] > 0 ? 'in-stock' : 'out-stock'; ?>">
                                                        <?php echo $product['stock_quantity']; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="status-badge status-<?php echo $product['status']; ?>">
                                                        <?php echo ucfirst($product['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="rating">
                                                        <span class="stars"><?php echo str_repeat('★', floor($product['rating'])); ?></span>
                                                        <span class="rating-number"><?php echo $product['rating']; ?></span>
                                                        <small>(<?php echo $product['total_reviews']; ?>)</small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="action" value="toggle_product">
                                                        <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                                                        <input type="hidden" name="status" value="<?php echo $product['status']; ?>">
                                                        <button type="submit" class="btn-small <?php echo $product['status'] === 'active' ? 'btn-warning' : 'btn-success'; ?>">
                                                            <i class="fas fa-<?php echo $product['status'] === 'active' ? 'pause' : 'play'; ?>"></i>
                                                            <?php echo $product['status'] === 'active' ? 'Disable' : 'Enable'; ?>
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Sales Chart -->
                <?php if (!empty($monthlySales)): ?>
                <div class="dashboard-card chart-card">
                    <div class="card-header">
                        <h3><i class="fas fa-chart-line"></i> Sales Trend</h3>
                    </div>
                    <div class="card-content">
                        <canvas id="salesChart" width="400" height="200"></canvas>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Recent Orders -->
                <div class="dashboard-card" id="orders-section">
                    <div class="card-header">
                        <h3><i class="fas fa-shopping-bag"></i> Recent Orders</h3>
                    </div>
                    <div class="card-content">
                        <?php if (empty($recentOrders)): ?>
                            <div class="empty-state">
                                <i class="fas fa-shopping-bag"></i>
                                <p>No orders received yet</p>
                            </div>
                        <?php else: ?>
                            <div class="orders-list">
                                <?php foreach ($recentOrders as $order): ?>
                                    <div class="order-item">
                                        <div class="order-header">
                                            <strong>Order #<?php echo $order['order_id']; ?></strong>
                                            <span class="order-status status-<?php echo $order['order_status']; ?>">
                                                <?php echo ucfirst($order['order_status']); ?>
                                            </span>
                                        </div>
                                        <div class="order-details">
                                            <p><strong>Customer:</strong> <?php echo htmlspecialchars($order['customer_name']); ?></p>
                                            <?php if ($order['customer_phone']): ?>
                                                <p><strong>Phone:</strong> <?php echo htmlspecialchars($order['customer_phone']); ?></p>
                                            <?php endif; ?>
                                            <p><strong>Products:</strong> <?php echo htmlspecialchars($order['products']); ?></p>
                                            <small class="order-date">
                                                <i class="fas fa-calendar"></i> <?php echo formatDateTime($order['created_at']); ?>
                                            </small>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php endif; // End if ($traderShop) ?>
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
        setInterval(updateTime, 60000);

        // Sales Chart
        <?php if (!empty($monthlySales)): ?>
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
                    label: 'Revenue ($)',
                    data: salesData.map(item => item.revenue),
                    borderColor: '#28a745',
                    backgroundColor: 'rgba(40, 167, 69, 0.1)',
                    tension: 0.4,
                    fill: true
                }, {
                    label: 'Orders',
                    data: salesData.map(item => item.orders),
                    borderColor: '#17a2b8',
                    backgroundColor: 'rgba(23, 162, 184, 0.1)',
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
        <?php endif; ?>

        // Smooth scrolling for sidebar links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Auto-hide alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 300);
            });
        }, 5000);
    </script>
</body>
</html>