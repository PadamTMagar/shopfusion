<?php
// trader/products.php - Trader Products Management
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

// Handle product actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    try {
        switch ($action) {
            case 'toggle_status':
                $productId = intval($_POST['product_id']);
                
                // Verify product belongs to trader
                $stmt = $pdo->prepare("
                    SELECT p.status FROM products p
                    JOIN shops s ON p.shop_id = s.shop_id
                    WHERE p.product_id = ? AND s.trader_id = ?
                ");
                $stmt->execute([$productId, getUserId()]);
                $product = $stmt->fetch();
                
                if ($product) {
                    $newStatus = $product['status'] === 'active' ? 'inactive' : 'active';
                    $stmt = $pdo->prepare("UPDATE products SET status = ? WHERE product_id = ?");
                    $stmt->execute([$newStatus, $productId]);
                    
                    $success = "Product status updated successfully.";
                    logActivity(getUserId(), 'product_status_updated', "Updated product $productId status to $newStatus");
                } else {
                    $error = "Product not found or access denied.";
                }
                break;
                
            case 'update_stock':
                $productId = intval($_POST['product_id']);
                $newStock = intval($_POST['new_stock']);
                
                // Verify product belongs to trader
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as count FROM products p
                    JOIN shops s ON p.shop_id = s.shop_id
                    WHERE p.product_id = ? AND s.trader_id = ?
                ");
                $stmt->execute([$productId, getUserId()]);
                $hasAccess = $stmt->fetch()['count'] > 0;
                
                if ($hasAccess && $newStock >= 0) {
                    $stmt = $pdo->prepare("UPDATE products SET stock_quantity = ? WHERE product_id = ?");
                    $stmt->execute([$newStock, $productId]);
                    
                    $success = "Stock quantity updated successfully.";
                    logActivity(getUserId(), 'stock_updated', "Updated product $productId stock to $newStock");
                } else {
                    $error = "Invalid stock quantity or access denied.";
                }
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
    
    header('Location: products.php');
    exit();
}

// Get filter parameters
$statusFilter = $_GET['status'] ?? 'all';
$categoryFilter = $_GET['category'] ?? 'all';

// Build query conditions
$whereConditions = ["s.trader_id = ?"];
$params = [getUserId()];

if ($statusFilter !== 'all') {
    $whereConditions[] = "p.status = ?";
    $params[] = $statusFilter;
}

if ($categoryFilter !== 'all') {
    $whereConditions[] = "p.category_id = ?";
    $params[] = $categoryFilter;
}

$whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

try {
    if ($traderShop) {
        // Get trader's products
        $stmt = $pdo->prepare("
            SELECT p.*, c.category_name, s.shop_name,
                   COALESCE(AVG(r.rating), 0) as avg_rating,
                   COUNT(r.review_id) as review_count
            FROM products p
            JOIN shops s ON p.shop_id = s.shop_id
            JOIN categories c ON p.category_id = c.category_id
            LEFT JOIN reviews r ON p.product_id = r.product_id
            $whereClause
            GROUP BY p.product_id
            ORDER BY p.created_at DESC
        ");
        $stmt->execute($params);
        $products = $stmt->fetchAll();
        
        // Get product statistics
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_products,
                SUM(CASE WHEN p.status = 'active' THEN 1 ELSE 0 END) as active_products,
                SUM(CASE WHEN p.status = 'inactive' THEN 1 ELSE 0 END) as inactive_products,
                SUM(CASE WHEN p.stock_quantity = 0 THEN 1 ELSE 0 END) as out_of_stock,
                AVG(p.price) as avg_price
            FROM products p
            JOIN shops s ON p.shop_id = s.shop_id
            WHERE s.trader_id = ?
        ");
        $stmt->execute([getUserId()]);
        $stats = $stmt->fetch();
        
        // Get categories for filter
        $categories = getCategories();
    }
} catch (PDOException $e) {
    $error = "Failed to load products: " . $e->getMessage();
    $products = [];
    $stats = ['total_products' => 0, 'active_products' => 0, 'inactive_products' => 0, 'out_of_stock' => 0, 'avg_price' => 0];
    $categories = [];
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
    <title>Products - Trader Dashboard</title>
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
            <li class="active">
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
                <h1><i class="fas fa-box"></i> Products</h1>
                <p>Manage your product inventory and listings</p>
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
                    <div class="stat-icon warning">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo number_format($stats['out_of_stock']); ?></h3>
                        <p>Out of Stock</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo formatPrice($stats['avg_price']); ?></h3>
                        <p>Average Price</p>
                    </div>
                </div>
            </div>

            <!-- Actions Bar -->
            <div class="actions-bar">
                <div class="actions-left">
                    <a href="index.php#add-product-section" class="btn-primary">
                        <i class="fas fa-plus"></i> Add New Product
                    </a>
                </div>
                
                <div class="actions-right">
                    <form method="GET" class="filters-form">
                        <div class="filter-group">
                            <select name="status">
                                <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All Status</option>
                                <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo $statusFilter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <select name="category">
                                <option value="all">All Categories</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['category_id']; ?>" <?php echo $categoryFilter == $category['category_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category['category_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn-secondary">
                            <i class="fas fa-filter"></i> Filter
                        </button>
                    </form>
                </div>
            </div>

            <!-- Products List -->
            <div class="products-section">
                <?php if (empty($products)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <i class="fas fa-box-open"></i>
                        </div>
                        <h3>No Products Found</h3>
                        <p>You haven't added any products yet or no products match your filters.</p>
                        <a href="index.php#add-product-section" class="btn-primary">
                            <i class="fas fa-plus"></i> Add Your First Product
                        </a>
                    </div>
                <?php else: ?>
                    <div class="products-grid">
                        <?php foreach ($products as $product): ?>
                            <div class="product-card">
                                <div class="product-image">
                                    <img src="<?php echo htmlspecialchars(getImagePath($product['image_path'], '../')); ?>" alt="<?php echo htmlspecialchars($product['product_name']); ?>">
                                    
                                    <div class="product-status">
                                        <span class="status-badge status-<?php echo $product['status']; ?>">
                                            <?php echo ucfirst($product['status']); ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="product-info">
                                    <h4><?php echo htmlspecialchars($product['product_name']); ?></h4>
                                    <p class="product-category"><?php echo htmlspecialchars($product['category_name']); ?></p>
                                    
                                    <div class="product-stats">
                                        <div class="stat-item">
                                            <span class="label">Price:</span>
                                            <span class="value price"><?php echo formatPrice($product['price']); ?></span>
                                        </div>
                                        <div class="stat-item">
                                            <span class="label">Stock:</span>
                                            <span class="value stock <?php echo $product['stock_quantity'] > 0 ? 'in-stock' : 'out-of-stock'; ?>">
                                                <?php echo number_format($product['stock_quantity']); ?>
                                            </span>
                                        </div>
                                        <div class="stat-item">
                                            <span class="label">Rating:</span>
                                            <span class="value rating">
                                                <?php
                                                $rating = round($product['avg_rating'], 1);
                                                for ($i = 1; $i <= 5; $i++):
                                                    if ($i <= $rating):
                                                ?>
                                                    <i class="fas fa-star"></i>
                                                <?php else: ?>
                                                    <i class="far fa-star"></i>
                                                <?php endif; endfor; ?>
                                                <span><?php echo $rating; ?> (<?php echo $product['review_count']; ?>)</span>
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <div class="product-actions">
                                        <a href="../shop/product.php?id=<?php echo $product['product_id']; ?>" 
                                           class="btn-action view" title="View Product" target="_blank">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        
                                        <button class="btn-action edit" title="Update Stock" 
                                                onclick="showStockModal(<?php echo $product['product_id']; ?>, '<?php echo addslashes($product['product_name']); ?>', <?php echo $product['stock_quantity']; ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to change the status of this product?')">
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                                            <button type="submit" class="btn-action <?php echo $product['status'] === 'active' ? 'warning' : 'success'; ?>" 
                                                    title="<?php echo $product['status'] === 'active' ? 'Disable' : 'Enable'; ?> Product">
                                                <i class="fas fa-<?php echo $product['status'] === 'active' ? 'pause' : 'play'; ?>"></i>
                                            </button>
                                        </form>
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

    <!-- Stock Update Modal -->
    <div id="stockModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-edit"></i> Update Stock</h3>
                <span class="close" onclick="closeStockModal()">&times;</span>
            </div>
            
            <form method="POST" id="stockForm">
                <input type="hidden" name="action" value="update_stock">
                <input type="hidden" name="product_id" id="stockProductId">
                
                <div class="modal-body">
                    <p>Update stock quantity for: <strong id="stockProductName"></strong></p>
                    
                    <div class="form-group">
                        <label for="new_stock">New Stock Quantity:</label>
                        <input type="number" name="new_stock" id="new_stock" min="0" required>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn-secondary" onclick="closeStockModal()">Cancel</button>
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-save"></i> Update Stock
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="../js/main.js"></script>
    
    <script>
        // Stock modal functions
        function showStockModal(productId, productName, currentStock) {
            document.getElementById('stockProductId').value = productId;
            document.getElementById('stockProductName').textContent = productName;
            document.getElementById('new_stock').value = currentStock;
            document.getElementById('stockModal').style.display = 'block';
        }
        
        function closeStockModal() {
            document.getElementById('stockModal').style.display = 'none';
            document.getElementById('stockForm').reset();
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('stockModal');
            if (event.target === modal) {
                closeStockModal();
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
        /* Products Page Styles */
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
        
        .stat-icon.active {
            background: #d4edda;
            color: #155724;
        }
        
        .stat-icon.warning {
            background: #fff3cd;
            color: #856404;
        }
        
        .actions-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 2rem 0;
            padding: 1.5rem;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .filters-form {
            display: flex;
            gap: 1rem;
            align-items: center;
        }
        
        .filter-group select {
            padding: 0.5rem;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 0.9rem;
        }
        
        .products-section {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 2rem;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem;
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
            margin-bottom: 2rem;
        }
        
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 2rem;
        }
        
        .product-card {
            border: 1px solid #eee;
            border-radius: 10px;
            overflow: hidden;
            transition: all 0.2s;
        }
        
        .product-card:hover {
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        
        .product-image {
            position: relative;
            height: 200px;
            background: #f8f9fa;
        }
        
        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .no-image {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #999;
            font-size: 2rem;
        }
        
        .product-status {
            position: absolute;
            top: 10px;
            right: 10px;
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
        
        .product-info {
            padding: 1.5rem;
        }
        
        .product-info h4 {
            margin: 0 0 0.5rem 0;
            color: #333;
            font-size: 1.1rem;
        }
        
        .product-category {
            color: #666;
            font-size: 0.9rem;
            margin: 0 0 1rem 0;
        }
        
        .product-stats {
            margin: 1rem 0;
        }
        
        .stat-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }
        
        .stat-item .label {
            color: #666;
            font-size: 0.9rem;
        }
        
        .stat-item .value {
            font-weight: 500;
        }
        
        .value.price {
            color: #28a745;
        }
        
        .value.stock.in-stock {
            color: #28a745;
        }
        
        .value.stock.out-of-stock {
            color: #dc3545;
        }
        
        .value.rating {
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }
        
        .value.rating i {
            color: #ffc107;
            font-size: 0.8rem;
        }
        
        .value.rating span {
            font-size: 0.8rem;
            color: #666;
        }
        
        .product-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
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
        
        .btn-action.view {
            background: #17a2b8;
            color: white;
        }
        
        .btn-action.view:hover {
            background: #138496;
        }
        
        .btn-action.edit {
            background: #007bff;
            color: white;
        }
        
        .btn-action.edit:hover {
            background: #0056b3;
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
            margin: 10% auto;
            border-radius: 10px;
            width: 90%;
            max-width: 400px;
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
        
        .modal-footer {
            padding: 1.5rem;
            border-top: 1px solid #eee;
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
        }
        
        @media (max-width: 768px) {
            .actions-bar {
                flex-direction: column;
                gap: 1rem;
            }
            
            .products-grid {
                grid-template-columns: 1fr;
            }
            
            .filters-form {
                flex-direction: column;
                width: 100%;
            }
            
            .filter-group {
                width: 100%;
            }
            
            .filter-group select {
                width: 100%;
            }
        }
    </style>
</body>
</html>
