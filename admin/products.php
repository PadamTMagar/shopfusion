<?php
// admin/products.php - Product Management for Admin
require_once '../config/database.php';
require_once '../includes/session.php';
require_once '../includes/functions.php';

// Require admin access
requireAdmin();

$error = '';
$success = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        try {
            switch ($action) {
                case 'disable_product':
                    $productId = (int)$_POST['product_id'];
                    $reason = sanitize($_POST['reason']);
                    $severity = sanitize($_POST['severity'] ?? 'medium');
                    
                    // Get product and trader info
                    $stmt = $pdo->prepare("
                        SELECT p.*, s.trader_id, u.full_name as trader_name
                        FROM products p
                        JOIN shops s ON p.shop_id = s.shop_id
                        JOIN users u ON s.trader_id = u.user_id
                        WHERE p.product_id = ?
                    ");
                    $stmt->execute([$productId]);
                    $product = $stmt->fetch();
                    
                    if ($product) {
                        $pdo->beginTransaction();
                        
                        // Disable the product
                        $stmt = $pdo->prepare("UPDATE products SET status = 'inactive' WHERE product_id = ?");
                        $stmt->execute([$productId]);
                        
                        // Add violation for trader
                        $violationDescription = "Product '{$product['product_name']}' (ID: $productId) was disabled. Reason: $reason. Severity: $severity";
                        $stmt = $pdo->prepare("
                            INSERT INTO violations (reported_user_id, reporter_id, violation_type, description, action_taken) 
                            VALUES (?, ?, 'product_violation', ?, 'warning')
                        ");
                        $stmt->execute([
                            $product['trader_id'], 
                            getUserId(), 
                            $violationDescription
                        ]);
                        
                        // Update trader's violation count
                        $stmt = $pdo->prepare("UPDATE users SET violation_count = violation_count + 1 WHERE user_id = ?");
                        $stmt->execute([$product['trader_id']]);
                        
                        // Check if trader should be disabled (2 violations = disable as per requirements)
                        $stmt = $pdo->prepare("SELECT violation_count FROM users WHERE user_id = ?");
                        $stmt->execute([$product['trader_id']]);
                        $violationCount = $stmt->fetch()['violation_count'];
                        
                        if ($violationCount >= 2) {
                            $stmt = $pdo->prepare("UPDATE users SET status = 'disabled' WHERE user_id = ?");
                            $stmt->execute([$product['trader_id']]);
                            
                            // Disable all trader's products
                            $stmt = $pdo->prepare("
                                UPDATE products p
                                JOIN shops s ON p.shop_id = s.shop_id
                                SET p.status = 'inactive'
                                WHERE s.trader_id = ?
                            ");
                            $stmt->execute([$product['trader_id']]);
                            
                            $success = "Product disabled and trader account suspended due to multiple violations.";
                        } else {
                            $success = "Product disabled and violation warning sent to trader.";
                        }
                        
                        $pdo->commit();
                        
                        // Log activity
                        logActivity(getUserId(), 'product_disabled', "Disabled product ID: $productId, Reason: $reason");
                    }
                    break;
                    
                case 'enable_product':
                    $productId = (int)$_POST['product_id'];
                    
                    $stmt = $pdo->prepare("UPDATE products SET status = 'active' WHERE product_id = ?");
                    $stmt->execute([$productId]);
                    
                    $success = "Product enabled successfully.";
                    logActivity(getUserId(), 'product_enabled', "Enabled product ID: $productId");
                    break;
                    
                case 'delete_product':
                    $productId = (int)$_POST['product_id'];
                    
                    $stmt = $pdo->prepare("DELETE FROM products WHERE product_id = ?");
                    $stmt->execute([$productId]);
                    
                    $success = "Product deleted successfully.";
                    logActivity(getUserId(), 'product_deleted', "Deleted product ID: $productId");
                    break;
            }
        } catch (PDOException $e) {
            if (isset($pdo)) {
                $pdo->rollBack();
            }
            $error = "Database error: " . $e->getMessage();
        }
        
        // Set flash message and redirect
        if ($success) {
            setFlashMessage('success', $success);
        }
        if ($error) {
            setFlashMessage('error', $error);
        }
        
        header('Location: products.php');
        exit();
    }
}

// Get filter parameters
$statusFilter = $_GET['status'] ?? 'all';
$traderFilter = $_GET['trader'] ?? '';
$searchQuery = $_GET['search'] ?? '';

// Build query
$whereConditions = [];
$params = [];

if ($statusFilter !== 'all') {
    $whereConditions[] = "p.status = ?";
    $params[] = $statusFilter;
}

if ($traderFilter) {
    $whereConditions[] = "s.trader_id = ?";
    $params[] = $traderFilter;
}

if ($searchQuery) {
    $whereConditions[] = "p.product_name LIKE ?";
    $params[] = '%' . $searchQuery . '%';
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

try {
    // Get products
    $stmt = $pdo->prepare("
        SELECT p.*, s.shop_name, s.trader_id, u.full_name as trader_name, u.status as trader_status,
               c.category_name
        FROM products p
        JOIN shops s ON p.shop_id = s.shop_id
        JOIN users u ON s.trader_id = u.user_id
        JOIN categories c ON p.category_id = c.category_id
        $whereClause
        ORDER BY p.created_at DESC
        LIMIT 50
    ");
    $stmt->execute($params);
    $products = $stmt->fetchAll();
    
    // Get statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_products,
            SUM(CASE WHEN p.status = 'active' THEN 1 ELSE 0 END) as active_products,
            SUM(CASE WHEN p.status = 'inactive' THEN 1 ELSE 0 END) as inactive_products
        FROM products p
        JOIN shops s ON p.shop_id = s.shop_id
        JOIN users u ON s.trader_id = u.user_id
        WHERE u.role = 'trader'
    ");
    $stmt->execute();
    $stats = $stmt->fetch();
    
    // Get all traders for filter
    $stmt = $pdo->prepare("
        SELECT u.user_id, u.full_name, s.shop_name 
        FROM users u
        JOIN shops s ON u.user_id = s.trader_id
        WHERE u.role = 'trader'
        ORDER BY u.full_name
    ");
    $stmt->execute();
    $traders = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error = "Failed to load products: " . $e->getMessage();
    $products = [];
    $stats = ['total_products' => 0, 'active_products' => 0, 'inactive_products' => 0];
    $traders = [];
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
    <title>Product Management - Admin Dashboard</title>
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
                    <i class="fas fa-users"></i> Traders
                </a>
            </li>
            <li class="active">
                <a href="products.php">
                    <i class="fas fa-box"></i> Products
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
                <h1><i class="fas fa-box"></i> Product Management</h1>
                <p>Monitor and manage all products on the platform</p>
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
                        <i class="fas fa-box"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo number_format($stats['total_products']); ?></h3>
                        <p>Total Products</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon active">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo number_format($stats['active_products']); ?></h3>
                        <p>Active Products</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon inactive">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo number_format($stats['inactive_products']); ?></h3>
                        <p>Disabled Products</p>
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
                            <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $statusFilter === 'inactive' ? 'selected' : ''; ?>>Disabled</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label>Trader:</label>
                        <select name="trader">
                            <option value="">All Traders</option>
                            <?php foreach ($traders as $trader): ?>
                                <option value="<?php echo $trader['user_id']; ?>" <?php echo $traderFilter == $trader['user_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($trader['full_name']); ?> (<?php echo htmlspecialchars($trader['shop_name']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label>Search:</label>
                        <input type="text" name="search" value="<?php echo htmlspecialchars($searchQuery); ?>" placeholder="Product name...">
                    </div>
                    
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-search"></i> Filter
                    </button>
                    
                    <a href="products.php" class="btn-secondary">
                        <i class="fas fa-refresh"></i> Reset
                    </a>
                </form>
            </div>

            <!-- Products Table -->
            <div class="table-section">
                <div class="table-header">
                    <h2><i class="fas fa-list"></i> Products (<?php echo count($products); ?> found)</h2>
                </div>
                
                <?php if (empty($products)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <i class="fas fa-box-open"></i>
                        </div>
                        <h3>No Products Found</h3>
                        <p>No products match your current filters.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Trader/Shop</th>
                                    <th>Price</th>
                                    <th>Stock</th>
                                    <th>Status</th>
                                    <th>Rating</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($products as $product): ?>
                                    <tr>
                                        <td>
                                            <div class="product-info">
                                                <div class="product-image">
                                                    <img src="<?php echo htmlspecialchars(getImagePath($product['image_path'], '../')); ?>" alt="Product Image">
                                                </div>
                                                <div class="product-details">
                                                    <h4><?php echo htmlspecialchars($product['product_name']); ?></h4>
                                                    <small><?php echo htmlspecialchars($product['category_name']); ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="trader-info">
                                                <strong><?php echo htmlspecialchars($product['trader_name']); ?></strong>
                                                <small><?php echo htmlspecialchars($product['shop_name']); ?></small>
                                                <div class="trader-status">
                                                    <span class="status-badge status-<?php echo $product['trader_status']; ?>">
                                                        <?php echo ucfirst($product['trader_status']); ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <strong><?php echo formatPrice($product['price']); ?></strong>
                                        </td>
                                        <td>
                                            <span class="stock-badge <?php echo $product['stock_quantity'] > 0 ? 'in-stock' : 'out-of-stock'; ?>">
                                                <?php echo number_format($product['stock_quantity']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?php echo $product['status']; ?>">
                                                <?php echo ucfirst($product['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="rating">
                                                <?php
                                                $rating = round($product['rating'], 1);
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
                                        <td>
                                            <small><?php echo formatDate($product['created_at']); ?></small>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <a href="../shop/product.php?id=<?php echo $product['product_id']; ?>" 
                                                   class="btn-action view" title="View Product" target="_blank">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                
                                                <?php if ($product['status'] === 'active'): ?>
                                                    <button class="btn-action warning" title="Disable Product" 
                                                            onclick="showDisableModal(<?php echo $product['product_id']; ?>, '<?php echo addslashes($product['product_name']); ?>')">
                                                        <i class="fas fa-ban"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to enable this product?')">
                                                        <input type="hidden" name="action" value="enable_product">
                                                        <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                                                        <button type="submit" class="btn-action success" title="Enable Product">
                                                            <i class="fas fa-check"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                                
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to permanently delete this product?')">
                                                    <input type="hidden" name="action" value="delete_product">
                                                    <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                                                    <button type="submit" class="btn-action danger" title="Delete Product">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
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

    <!-- Disable Product Modal -->
    <div id="disableModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-exclamation-triangle"></i> Disable Product</h3>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            
            <form method="POST" id="disableForm">
                <input type="hidden" name="action" value="disable_product">
                <input type="hidden" name="product_id" id="disableProductId">
                
                <div class="modal-body">
                    <p>You are about to disable the product: <strong id="disableProductName"></strong></p>
                    <p class="warning-text">
                        <i class="fas fa-exclamation-triangle"></i>
                        This will send a violation warning to the trader. If they already have 1 violation, their account will be suspended.
                    </p>
                    
                    <div class="form-group">
                        <label for="reason">Reason for disabling: *</label>
                        <textarea name="reason" id="reason" rows="3" required 
                                  placeholder="Explain why this product is being disabled..."></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="severity">Severity:</label>
                        <select name="severity" id="severity" required>
                            <option value="low">Low - Minor issue</option>
                            <option value="medium" selected>Medium - Standard violation</option>
                            <option value="high">High - Serious violation</option>
                        </select>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn-secondary" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn-danger">
                        <i class="fas fa-ban"></i> Disable Product
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="../js/main.js"></script>
    
    <script>
        // Modal functions
        function showDisableModal(productId, productName) {
            document.getElementById('disableProductId').value = productId;
            document.getElementById('disableProductName').textContent = productName;
            document.getElementById('disableModal').style.display = 'block';
        }
        
        function closeModal() {
            document.getElementById('disableModal').style.display = 'none';
            document.getElementById('disableForm').reset();
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('disableModal');
            if (event.target === modal) {
                closeModal();
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
        /* Product Management Styles */
        .product-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .product-image {
            width: 50px;
            height: 50px;
            border-radius: 5px;
            overflow: hidden;
            flex-shrink: 0;
        }
        
        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .no-image {
            width: 100%;
            height: 100%;
            background: #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #999;
        }
        
        .product-details h4 {
            margin: 0 0 0.25rem 0;
            color: #333;
            font-size: 0.9rem;
        }
        
        .product-details small {
            color: #666;
            font-size: 0.8rem;
        }
        
        .trader-info {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }
        
        .trader-info strong {
            color: #333;
            font-size: 0.9rem;
        }
        
        .trader-info small {
            color: #666;
            font-size: 0.8rem;
        }
        
        .trader-status {
            margin-top: 0.25rem;
        }
        
        .stock-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .stock-badge.in-stock {
            background: #d4edda;
            color: #155724;
        }
        
        .stock-badge.out-of-stock {
            background: #f8d7da;
            color: #721c24;
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
        
        .status-badge.status-inactive {
            background: #f8d7da;
            color: #721c24;
        }
        
        .status-badge.status-disabled {
            background: #f8d7da;
            color: #721c24;
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
            text-decoration: none;
            transition: all 0.2s;
        }
        
        .btn-action.view {
            background: #17a2b8;
            color: white;
        }
        
        .btn-action.view:hover {
            background: #138496;
        }
        
        .btn-action.warning {
            background: #ffc107;
            color: #212529;
        }
        
        .btn-action.warning:hover {
            background: #e0a800;
        }
        
        .btn-action.success {
            background: #28a745;
            color: white;
        }
        
        .btn-action.success:hover {
            background: #218838;
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
        
        .stat-icon.active {
            background: #d4edda;
            color: #155724;
        }
        
        .stat-icon.inactive {
            background: #f8d7da;
            color: #721c24;
        }
        
        @media (max-width: 768px) {
            .product-info {
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
