<?php
// customer/orders.php - Customer Order History and Management
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

// Handle order actions
if ($_POST && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'cancel_order':
            $orderId = intval($_POST['order_id']);
            
            // Verify order belongs to current user and can be cancelled
            $stmt = $pdo->prepare("
                SELECT * FROM orders 
                WHERE order_id = ? AND customer_id = ? 
                AND order_status IN ('pending', 'processing') 
                AND payment_status = 'completed'
            ");
            $stmt->execute([$orderId, $currentUser['user_id']]);
            $order = $stmt->fetch();
            
            if ($order) {
                try {
                    $stmt = $pdo->prepare("
                        UPDATE orders 
                        SET order_status = 'cancelled', updated_at = NOW() 
                        WHERE order_id = ?
                    ");
                    
                    if ($stmt->execute([$orderId])) {
                        $success = "Order #$orderId has been cancelled successfully.";
                        setFlashMessage('success', $success);
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
            
        case 'add_review':
            $productId = intval($_POST['product_id']);
            $orderId = intval($_POST['order_id']);
            $rating = intval($_POST['rating']);
            $comment = sanitize($_POST['comment']);
            
            if ($rating >= 1 && $rating <= 5) {
                // Verify customer bought this product
                $stmt = $pdo->prepare("
                    SELECT oi.* FROM order_items oi
                    JOIN orders o ON oi.order_id = o.order_id
                    WHERE oi.product_id = ? AND oi.order_id = ? 
                    AND o.customer_id = ? AND o.payment_status = 'completed'
                ");
                $stmt->execute([$productId, $orderId, $currentUser['user_id']]);
                
                if ($stmt->fetch()) {
                    // Check if review already exists
                    $stmt = $pdo->prepare("
                        SELECT review_id FROM reviews 
                        WHERE product_id = ? AND customer_id = ?
                    ");
                    $stmt->execute([$productId, $currentUser['user_id']]);
                    
                    if (!$stmt->fetch()) {
                        try {
                            $pdo->beginTransaction();
                            
                            // Insert review
                            $stmt = $pdo->prepare("
                                INSERT INTO reviews (product_id, customer_id, rating, comment) 
                                VALUES (?, ?, ?, ?)
                            ");
                            $stmt->execute([$productId, $currentUser['user_id'], $rating, $comment]);
                            
                            // Update product rating
                            updateProductRating($productId);
                            
                            $pdo->commit();
                            $success = "Review added successfully!";
                            setFlashMessage('success', $success);
                        } catch (PDOException $e) {
                            $pdo->rollBack();
                            $error = "Failed to add review.";
                        }
                    } else {
                        $error = "You have already reviewed this product.";
                    }
                } else {
                    $error = "You can only review products you have purchased.";
                }
            } else {
                $error = "Please provide a valid rating (1-5 stars).";
            }
            break;
    }
}

try {
    // Get order filters
    $statusFilter = isset($_GET['status']) ? sanitize($_GET['status']) : '';
    $dateFilter = isset($_GET['date_range']) ? sanitize($_GET['date_range']) : '';
    
    // Build query conditions
    $conditions = ["o.customer_id = ?"];
    $params = [$currentUser['user_id']];
    
    if ($statusFilter && $statusFilter !== 'all') {
        $conditions[] = "o.order_status = ?";
        $params[] = $statusFilter;
    }
    
    if ($dateFilter) {
        switch ($dateFilter) {
            case 'last_30_days':
                $conditions[] = "o.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
                break;
            case 'last_90_days':
                $conditions[] = "o.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)";
                break;
            case 'this_year':
                $conditions[] = "YEAR(o.created_at) = YEAR(NOW())";
                break;
        }
    }
    
    $whereClause = "WHERE " . implode(" AND ", $conditions);
    
    // Get customer's orders with pagination
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = 10;
    $offset = ($page - 1) * $limit;
    
    // Count total orders
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total 
        FROM orders o 
        $whereClause
    ");
    $stmt->execute($params);
    $totalOrders = $stmt->fetch()['total'];
    $totalPages = ceil($totalOrders / $limit);
    
    // Get orders with details
    $stmt = $pdo->prepare("
        SELECT o.*, 
               COUNT(oi.item_id) as item_count,
               SUM(oi.quantity) as total_items
        FROM orders o
        LEFT JOIN order_items oi ON o.order_id = oi.order_id
        $whereClause
        GROUP BY o.order_id
        ORDER BY o.created_at DESC
        LIMIT $limit OFFSET $offset
    ");
    $stmt->execute($params);
    $orders = $stmt->fetchAll();
    
    // Get order statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_orders,
            SUM(CASE WHEN payment_status = 'completed' THEN total_amount ELSE 0 END) as total_spent,
            SUM(CASE WHEN order_status = 'delivered' THEN 1 ELSE 0 END) as delivered_orders,
            SUM(CASE WHEN order_status = 'pending' THEN 1 ELSE 0 END) as pending_orders,
            SUM(points_earned) as total_points_earned
        FROM orders 
        WHERE customer_id = ?
    ");
    $stmt->execute([$currentUser['user_id']]);
    $orderStats = $stmt->fetch();
    
    // Get order items for each order
    $orderItems = [];
    if (!empty($orders)) {
        $orderIds = array_column($orders, 'order_id');
        $placeholders = str_repeat('?,', count($orderIds) - 1) . '?';
        
        $stmt = $pdo->prepare("
            SELECT oi.*, p.product_name, p.image_path, s.shop_name,
                   r.review_id, r.rating as user_rating, r.comment as user_comment
            FROM order_items oi
            JOIN products p ON oi.product_id = p.product_id
            JOIN shops s ON oi.shop_id = s.shop_id
            LEFT JOIN reviews r ON p.product_id = r.product_id AND r.customer_id = ?
            WHERE oi.order_id IN ($placeholders)
            ORDER BY oi.order_id DESC, oi.item_id
        ");
        $stmt->execute(array_merge([$currentUser['user_id']], $orderIds));
        
        while ($item = $stmt->fetch()) {
            $orderItems[$item['order_id']][] = $item;
        }
    }
    
} catch (PDOException $e) {
    $error = "Failed to load orders: " . $e->getMessage();
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
    <link rel="stylesheet" href="../css/theme.css">
    <link rel="stylesheet" href="../css/customer.css">
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
                        <?php if ($orderStats['total_orders'] > 0): ?>
                            <span class="badge"><?php echo $orderStats['total_orders']; ?></span>
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
                <p>Track your orders and manage your purchase history</p>
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
            <div class="orders-stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-shopping-bag"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo number_format($orderStats['total_orders']); ?></h3>
                        <p>Total Orders</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo formatPrice($orderStats['total_spent']); ?></h3>
                        <p>Total Spent</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo number_format($orderStats['delivered_orders']); ?></h3>
                        <p>Delivered</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-star"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo number_format($orderStats['total_points_earned']); ?></h3>
                        <p>Points Earned</p>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="orders-filters">
                <div class="filter-section">
                    <form method="GET" class="filters-form">
                        <div class="filter-group">
                            <label for="status">Order Status:</label>
                            <select name="status" id="status">
                                <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All Orders</option>
                                <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="processing" <?php echo $statusFilter === 'processing' ? 'selected' : ''; ?>>Processing</option>
                                <option value="shipped" <?php echo $statusFilter === 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                                <option value="delivered" <?php echo $statusFilter === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                                <option value="cancelled" <?php echo $statusFilter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="date_range">Date Range:</label>
                            <select name="date_range" id="date_range">
                                <option value="" <?php echo $dateFilter === '' ? 'selected' : ''; ?>>All Time</option>
                                <option value="last_30_days" <?php echo $dateFilter === 'last_30_days' ? 'selected' : ''; ?>>Last 30 Days</option>
                                <option value="last_90_days" <?php echo $dateFilter === 'last_90_days' ? 'selected' : ''; ?>>Last 90 Days</option>
                                <option value="this_year" <?php echo $dateFilter === 'this_year' ? 'selected' : ''; ?>>This Year</option>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-filter"></i> Apply Filters
                        </button>
                        
                        <a href="orders.php" class="btn-secondary">
                            <i class="fas fa-times"></i> Clear
                        </a>
                    </form>
                </div>
            </div>

            <!-- Orders List -->
            <div class="orders-section">
                <?php if (empty($orders)): ?>
                    <div class="empty-state">
                        <i class="fas fa-shopping-bag"></i>
                        <h3>No orders found</h3>
                        <p>
                            <?php if ($statusFilter || $dateFilter): ?>
                                No orders match your current filters. Try adjusting your search criteria.
                            <?php else: ?>
                                You haven't placed any orders yet. Start shopping to see your orders here!
                            <?php endif; ?>
                        </p>
                        <a href="../index.php" class="btn-primary">
                            <i class="fas fa-shopping-cart"></i> Start Shopping
                        </a>
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
                                            <span class="order-total">
                                                <i class="fas fa-dollar-sign"></i>
                                                <?php echo formatPrice($order['total_amount']); ?>
                                            </span>
                                            <span class="order-items">
                                                <i class="fas fa-box"></i>
                                                <?php echo $order['total_items']; ?> item<?php echo $order['total_items'] != 1 ? 's' : ''; ?>
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <div class="order-status-section">
                                        <span class="order-status status-<?php echo $order['order_status']; ?>">
                                            <?php echo ucfirst($order['order_status']); ?>
                                        </span>
                                        <span class="payment-status status-<?php echo $order['payment_status']; ?>">
                                            Payment: <?php echo ucfirst($order['payment_status']); ?>
                                        </span>
                                    </div>
                                </div>

                                <!-- Order Items -->
                                <?php if (isset($orderItems[$order['order_id']])): ?>
                                    <div class="order-items-list">
                                        <?php foreach ($orderItems[$order['order_id']] as $item): ?>
                                            <div class="order-item">
                                                <div class="item-image">
                                                    <img src="<?php echo htmlspecialchars(getImagePath($item['image_path'], '../')); ?>" 
                                                         alt="<?php echo htmlspecialchars($item['product_name']); ?>">
                                                </div>
                                                
                                                <div class="item-details">
                                                    <h4><?php echo htmlspecialchars($item['product_name']); ?></h4>
                                                    <p class="item-shop">by <?php echo htmlspecialchars($item['shop_name']); ?></p>
                                                    <div class="item-specs">
                                                        <span>Qty: <?php echo $item['quantity']; ?></span>
                                                        <span>Price: <?php echo formatPrice($item['unit_price']); ?></span>
                                                        <span>Subtotal: <?php echo formatPrice($item['subtotal']); ?></span>
                                                    </div>
                                                </div>
                                                
                                                <div class="item-actions">
                                                    <?php if ($order['order_status'] === 'delivered' && $order['payment_status'] === 'completed'): ?>
                                                        <?php if (!$item['review_id']): ?>
                                                            <button class="btn-small btn-primary" onclick="openReviewModal(<?php echo $item['product_id']; ?>, <?php echo $order['order_id']; ?>, '<?php echo htmlspecialchars($item['product_name']); ?>')">
                                                                <i class="fas fa-star"></i> Write Review
                                                            </button>
                                                        <?php else: ?>
                                                            <div class="user-review">
                                                                <div class="review-rating">
                                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                                        <i class="fas fa-star <?php echo $i <= $item['user_rating'] ? 'rated' : ''; ?>"></i>
                                                                    <?php endfor; ?>
                                                                </div>
                                                                <small>You rated this product</small>
                                                            </div>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                    
                                                    <a href="../shop/product.php?id=<?php echo $item['product_id']; ?>" class="btn-small btn-secondary">
                                                        <i class="fas fa-eye"></i> View Product
                                                    </a>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <!-- Order Actions -->
                                <div class="order-actions">
                                    <?php if ($order['order_status'] === 'delivered'): ?>
                                        <div class="order-success">
                                            <i class="fas fa-check-circle"></i>
                                            <span>Order delivered successfully</span>
                                            <?php if ($order['points_earned'] > 0): ?>
                                                <span class="points-earned">
                                                    +<?php echo $order['points_earned']; ?> points earned
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    <?php elseif ($order['order_status'] === 'cancelled'): ?>
                                        <div class="order-cancelled">
                                            <i class="fas fa-times-circle"></i>
                                            <span>Order was cancelled</span>
                                        </div>
                                    <?php elseif (in_array($order['order_status'], ['pending', 'processing']) && $order['payment_status'] === 'completed'): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="cancel_order">
                                            <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                                            <button type="submit" class="btn-small btn-warning" 
                                                    onclick="return confirm('Are you sure you want to cancel this order?')">
                                                <i class="fas fa-times"></i> Cancel Order
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    
                                    <?php if ($order['paypal_transaction_id']): ?>
                                        <div class="payment-info">
                                            <small>
                                                <i class="fab fa-paypal"></i>
                                                PayPal Transaction: <?php echo htmlspecialchars($order['paypal_transaction_id']); ?>
                                            </small>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($order['promo_code']): ?>
                                        <div class="promo-info">
                                            <small>
                                                <i class="fas fa-tag"></i>
                                                Promo code used: <?php echo htmlspecialchars($order['promo_code']); ?>
                                                (<?php echo formatPrice($order['discount_amount']); ?> discount)
                                            </small>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?php echo $page - 1; ?>&status=<?php echo $statusFilter; ?>&date_range=<?php echo $dateFilter; ?>" class="page-btn">
                                    <i class="fas fa-chevron-left"></i> Previous
                                </a>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                <a href="?page=<?php echo $i; ?>&status=<?php echo $statusFilter; ?>&date_range=<?php echo $dateFilter; ?>" 
                                   class="page-btn <?php echo $i === $page ? 'active' : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                            
                            <?php if ($page < $totalPages): ?>
                                <a href="?page=<?php echo $page + 1; ?>&status=<?php echo $statusFilter; ?>&date_range=<?php echo $dateFilter; ?>" class="page-btn">
                                    Next <i class="fas fa-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Review Modal -->
    <div id="reviewModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-star"></i> Write a Review</h3>
                <button class="modal-close" onclick="closeReviewModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="modal-body">
                <form method="POST" id="reviewForm">
                    <input type="hidden" name="action" value="add_review">
                    <input type="hidden" name="product_id" id="reviewProductId">
                    <input type="hidden" name="order_id" id="reviewOrderId">
                    <input type="hidden" name="rating" id="reviewRating" value="5">
                    
                    <div class="review-product">
                        <h4 id="reviewProductName"></h4>
                    </div>
                    
                    <div class="form-group">
                        <label>Your Rating:</label>
                        <div class="star-rating">
                            <i class="fas fa-star" data-rating="1"></i>
                            <i class="fas fa-star" data-rating="2"></i>
                            <i class="fas fa-star" data-rating="3"></i>
                            <i class="fas fa-star" data-rating="4"></i>
                            <i class="fas fa-star" data-rating="5"></i>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="reviewComment">Your Review:</label>
                        <textarea id="reviewComment" name="comment" rows="4" 
                                  placeholder="Share your experience with this product..."></textarea>
                    </div>
                    
                    <div class="modal-actions">
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-star"></i> Submit Review
                        </button>
                        <button type="button" class="btn-secondary" onclick="closeReviewModal()">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>

    <script src="../js/main.js"></script>
    <script>
        // Review modal functionality
        function openReviewModal(productId, orderId, productName) {
            document.getElementById('reviewProductId').value = productId;
            document.getElementById('reviewOrderId').value = orderId;
            document.getElementById('reviewProductName').textContent = productName;
            document.getElementById('reviewModal').style.display = 'flex';
            
            // Reset rating
            setRating(5);
        }

        function closeReviewModal() {
            document.getElementById('reviewModal').style.display = 'none';
            document.getElementById('reviewForm').reset();
        }

        // Star rating functionality
        document.addEventListener('DOMContentLoaded', function() {
            const stars = document.querySelectorAll('.star-rating .fas');
            
            stars.forEach(star => {
                star.addEventListener('click', function() {
                    const rating = parseInt(this.dataset.rating);
                    setRating(rating);
                });
                
                star.addEventListener('mouseover', function() {
                    const rating = parseInt(this.dataset.rating);
                    highlightStars(rating);
                });
            });
            
            document.querySelector('.star-rating').addEventListener('mouseleave', function() {
                const currentRating = parseInt(document.getElementById('reviewRating').value);
                highlightStars(currentRating);
            });
        });

        function setRating(rating) {
            document.getElementById('reviewRating').value = rating;
            highlightStars(rating);
        }

        function highlightStars(rating) {
            const stars = document.querySelectorAll('.star-rating .fas');
            stars.forEach((star, index) => {
                if (index < rating) {
                    star.classList.add('rated');
                } else {
                    star.classList.remove('rated');
                }
            });
        }

        // Close modal when clicking outside
        document.getElementById('reviewModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeReviewModal();
            }
        });

        // Auto-hide alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 300);
            });
        }, 5000);

        // Order card animations
        document.addEventListener('DOMContentLoaded', function() {
            const orderCards = document.querySelectorAll('.order-card');
            orderCards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    card.style.transition = 'opacity 0.5s, transform 0.5s';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });

        // Filter form auto-submit
        document.addEventListener('DOMContentLoaded', function() {
            const statusSelect = document.getElementById('status');
            const dateSelect = document.getElementById('date_range');
            
            statusSelect.addEventListener('change', function() {
                this.form.submit();
            });
            
            dateSelect.addEventListener('change', function() {
                this.form.submit();
            });
        });
    </script>

    <style>
        /* Additional styles for orders page */
        .orders-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .orders-filters {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }

        .filters-form {
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
        }

        .filter-group select {
            padding: 0.5rem;
            border: 1px solid #ddd;
            border-radius: 5px;
            min-width: 150px;
        }

        .order-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 1.5rem;
            overflow: hidden;
        }

        .order-header {
            padding: 1.5rem;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .order-info h3 {
            color: #333;
            margin-bottom: 0.5rem;
        }

        .order-meta {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .order-meta span {
            color: #666;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }

        .order-status-section {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            align-items: flex-end;
        }

        .order-status, .payment-status {
            padding: 0.3rem 0.8rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 500;
            text-transform: uppercase;
        }

        .status-pending { background: #fff3cd; color: #856404; }
        .status-processing { background: #d1ecf1; color: #0c5460; }
        .status-shipped { background: #d4edda; color: #155724; }
        .status-delivered { background: #d4edda; color: #155724; }
        .status-cancelled { background: #f8d7da; color: #721c24; }
        .status-completed { background: #d4edda; color: #155724; }
        .status-failed { background: #f8d7da; color: #721c24; }

        .order-items-list {
            padding: 1rem 1.5rem;
            background: #f8f9fa;
        }

        .order-item {
            display: flex;
            gap: 1rem;
            padding: 1rem 0;
            border-bottom: 1px solid #eee;
        }

        .order-item:last-child {
            border-bottom: none;
        }

        .item-image img {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 5px;
        }

        .item-details {
            flex: 1;
        }

        .item-details h4 {
            margin-bottom: 0.3rem;
            color: #333;
        }

        .item-shop {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }

        .item-specs {
            display: flex;
            gap: 1rem;
            font-size: 0.85rem;
            color: #666;
        }

        .item-actions {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            align-items: flex-end;
        }

        .user-review {
            text-align: center;
        }

        .review-rating {
            color: #ffc107;
            margin-bottom: 0.2rem;
        }

        .order-actions {
            padding: 1rem 1.5rem;
            background: #f8f9fa;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .order-success, .order-cancelled {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 500;
        }

        .order-success {
            color: #28a745;
        }

        .order-cancelled {
            color: #dc3545;
        }

        .points-earned {
            background: #ffc107;
            color: #212529;
            padding: 0.2rem 0.5rem;
            border-radius: 10px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .payment-info, .promo-info {
            font-size: 0.85rem;
            color: #666;
        }

        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }

        .modal-content {
            background: white;
            border-radius: 10px;
            width: 90%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 1.2rem;
            cursor: pointer;
            color: #666;
        }

        .modal-body {
            padding: 1.5rem;
        }

        .star-rating {
            display: flex;
            gap: 0.3rem;
            font-size: 1.5rem;
            margin: 1rem 0;
        }

        .star-rating .fas {
            color: #ddd;
            cursor: pointer;
            transition: color 0.2s;
        }

        .star-rating .fas:hover,
        .star-rating .fas.rated {
            color: #ffc107;
        }

        .modal-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 1.5rem;
        }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 2rem;
        }

        .page-btn {
            padding: 0.5rem 1rem;
            background: white;
            border: 1px solid #ddd;
            border-radius: 5px;
            text-decoration: none;
            color: #333;
            transition: all 0.2s;
        }

        .page-btn:hover,
        .page-btn.active {
            background: #007bff;
            color: white;
            border-color: #007bff;
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

            .order-status-section {
                align-items: flex-start;
            }

            .order-item {
                flex-direction: column;
                text-align: center;
            }

            .item-actions {
                align-items: center;
            }

            .filters-form {
                flex-direction: column;
                align-items: stretch;
            }

            .filter-group select {
                min-width: auto;
            }
        }
    </style>
</body>
</html>
