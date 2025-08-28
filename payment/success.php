<?php
// payment/success.php - Payment Success Page
require_once '../config/database.php';
require_once '../includes/session.php';
require_once '../includes/functions.php';

// Require login
requireLogin();

$orderId = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;

if (!$orderId) {
    header('Location: ../index.php');
    exit();
}

// Get order details
try {
    $stmt = $pdo->prepare("
        SELECT o.*, u.full_name, u.email 
        FROM orders o
        JOIN users u ON o.customer_id = u.user_id
        WHERE o.order_id = ? AND o.customer_id = ? AND o.payment_status = 'completed'
    ");
    $stmt->execute([$orderId, getUserId()]);
    $order = $stmt->fetch();
    
    if (!$order) {
        setFlashMessage('error', 'Order not found');
        header('Location: ../customer/orders.php');
        exit();
    }
    
    // Get order items
    $stmt = $pdo->prepare("
        SELECT oi.*, p.product_name, p.image_path, s.shop_name, s.trader_id, u.full_name as trader_name
        FROM order_items oi
        JOIN products p ON oi.product_id = p.product_id
        JOIN shops s ON oi.shop_id = s.shop_id
        JOIN users u ON s.trader_id = u.user_id
        WHERE oi.order_id = ?
        ORDER BY s.shop_name, p.product_name
    ");
    $stmt->execute([$orderId]);
    $orderItems = $stmt->fetchAll();
    
    // Group items by shop for trader notifications
    $shopGroups = [];
    foreach ($orderItems as $item) {
        $shopGroups[$item['shop_id']]['shop_name'] = $item['shop_name'];
        $shopGroups[$item['shop_id']]['trader_name'] = $item['trader_name'];
        $shopGroups[$item['shop_id']]['items'][] = $item;
    }
    
} catch (PDOException $e) {
    setFlashMessage('error', 'Failed to load order details');
    header('Location: ../customer/orders.php');
    exit();
}

// Calculate estimated delivery date (5-7 business days)
$deliveryDate = date('M j, Y', strtotime('+7 days'));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmation - ShopFusion</title>
    
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/theme.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <h1><a href="../index.php">Shopfusion</a></h1>
                </div>
                
                <div class="checkout-progress">
                    <div class="progress-step completed">
                        <i class="fas fa-shopping-cart"></i>
                        <span>Cart</span>
                    </div>
                    <div class="progress-line"></div>
                    <div class="progress-step completed">
                        <i class="fas fa-credit-card"></i>
                        <span>Checkout</span>
                    </div>
                    <div class="progress-line"></div>
                    <div class="progress-step completed">
                        <i class="fas fa-check"></i>
                        <span>Complete</span>
                    </div>
                </div>
                
                <div class="user-menu">
                    <div class="user-info">
                        <i class="fas fa-user"></i>
                        <span><?php echo htmlspecialchars($order['full_name']); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="main-content">
        <div class="container">
            <!-- Success Header -->
            <div class="success-header">
                <div class="success-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h1>Order Confirmed!</h1>
                <p class="success-message">
                    Thank you for your purchase! Your order has been successfully placed and payment confirmed.
                </p>
                <div class="order-number">
                    <strong>Order #<?php echo $order['order_id']; ?></strong>
                </div>
            </div>

            <!-- Order Summary -->
            <div class="success-content">
                <div class="success-left">
                    <!-- Order Details -->
                    <div class="success-section">
                        <h2><i class="fas fa-info-circle"></i> Order Details</h2>
                        
                        <div class="order-info-grid">
                            <div class="info-item">
                                <label>Order Date:</label>
                                <span><?php echo formatDateTime($order['created_at']); ?></span>
                            </div>
                            <div class="info-item">
                                <label>Order Status:</label>
                                <span class="status-badge status-<?php echo $order['order_status']; ?>">
                                    <?php echo ucfirst($order['order_status']); ?>
                                </span>
                            </div>
                            <div class="info-item">
                                <label>Payment Status:</label>
                                <span class="status-badge status-<?php echo $order['payment_status']; ?>">
                                    <?php echo ucfirst($order['payment_status']); ?>
                                </span>
                            </div>
                            <div class="info-item">
                                <label>Payment Method:</label>
                                <span>
                                    <?php if ($order['payment_method'] === 'paypal'): ?>
                                        <i class="fab fa-paypal"></i> PayPal
                                    <?php else: ?>
                                        <i class="fas fa-credit-card"></i> <?php echo ucfirst(str_replace('_', ' ', $order['payment_method'])); ?>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <?php if ($order['paypal_transaction_id']): ?>
                                <div class="info-item">
                                    <label>Transaction ID:</label>
                                    <span class="transaction-id"><?php echo htmlspecialchars($order['paypal_transaction_id']); ?></span>
                                </div>
                            <?php endif; ?>
                            <div class="info-item">
                                <label>Estimated Delivery:</label>
                                <span class="delivery-date">
                                    <i class="fas fa-truck"></i> <?php echo $deliveryDate; ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Shipping Address -->
                    <div class="success-section">
                        <h2><i class="fas fa-shipping-fast"></i> Shipping Address</h2>
                        <div class="shipping-address">
                            <p><strong><?php echo htmlspecialchars($order['full_name']); ?></strong></p>
                            <p><?php echo nl2br(htmlspecialchars($order['shipping_address'])); ?></p>
                        </div>
                    </div>

                    <!-- Order Items by Shop -->
                    <div class="success-section">
                        <h2><i class="fas fa-box"></i> Your Items</h2>
                        
                        <?php foreach ($shopGroups as $shopId => $shopGroup): ?>
                            <div class="shop-group">
                                <div class="shop-header">
                                    <h3>
                                        <i class="fas fa-store"></i>
                                        <?php echo htmlspecialchars($shopGroup['shop_name']); ?>
                                    </h3>
                                    <p>by <?php echo htmlspecialchars($shopGroup['trader_name']); ?></p>
                                </div>
                                
                                <div class="shop-items">
                                    <?php foreach ($shopGroup['items'] as $item): ?>
                                        <div class="order-item">
                                            <div class="item-image">
                                                <img src="<?php echo htmlspecialchars(getImagePath($item['image_path'], '../')); ?>" 
                                                     alt="<?php echo htmlspecialchars($item['product_name']); ?>">
                                            </div>
                                            <div class="item-details">
                                                <h4><?php echo htmlspecialchars($item['product_name']); ?></h4>
                                                <div class="item-pricing">
                                                    <span>Qty: <?php echo $item['quantity']; ?></span>
                                                    <span><?php echo formatPrice($item['unit_price']); ?> each</span>
                                                </div>
                                            </div>
                                            <div class="item-total">
                                                <?php echo formatPrice($item['subtotal']); ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Right Sidebar -->
                <div class="success-right">
                    <!-- Order Total -->
                    <div class="success-card">
                        <h2><i class="fas fa-receipt"></i> Order Total</h2>
                        
                        <div class="total-breakdown">
                            <?php
                            $subtotal = 0;
                            foreach ($orderItems as $item) {
                                $subtotal += $item['subtotal'];
                            }
                            $taxAmount = ($subtotal - $order['discount_amount'] - ($order['points_used'] / 100)) * 0.08;
                            ?>
                            
                            <div class="total-row">
                                <span>Subtotal:</span>
                                <span><?php echo formatPrice($subtotal); ?></span>
                            </div>
                            
                            <?php if ($order['discount_amount'] > 0): ?>
                                <div class="total-row discount">
                                    <span>Discount<?php echo $order['promo_code'] ? ' (' . $order['promo_code'] . ')' : ''; ?>:</span>
                                    <span>-<?php echo formatPrice($order['discount_amount']); ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($order['points_used'] > 0): ?>
                                <div class="total-row points">
                                    <span>Points Used (<?php echo $order['points_used']; ?>):</span>
                                    <span>-<?php echo formatPrice($order['points_used'] / 100); ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <div class="total-row">
                                <span>Tax:</span>
                                <span><?php echo formatPrice($taxAmount); ?></span>
                            </div>
                            
                            <div class="total-row shipping">
                                <span>Shipping:</span>
                                <span class="free">FREE</span>
                            </div>
                            
                            <hr>
                            
                            <div class="total-row final">
                                <strong>
                                    <span>Total Paid:</span>
                                    <span><?php echo formatPrice($order['total_amount']); ?></span>
                                </strong>
                            </div>
                            
                            <?php if ($order['points_earned'] > 0): ?>
                                <div class="points-earned">
                                    <i class="fas fa-star"></i>
                                    <strong><?php echo $order['points_earned']; ?> points</strong> earned with this order!
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Next Steps -->
                    <div class="success-card">
                        <h2><i class="fas fa-list-check"></i> What's Next?</h2>
                        
                        <div class="next-steps">
                            <div class="step">
                                <div class="step-icon">
                                    <i class="fas fa-envelope"></i>
                                </div>
                                <div class="step-content">
                                    <h4>Order Confirmation</h4>
                                    <p>You'll receive an email confirmation shortly at <?php echo htmlspecialchars($order['email']); ?></p>
                                </div>
                            </div>
                            
                            <div class="step">
                                <div class="step-icon">
                                    <i class="fas fa-cog"></i>
                                </div>
                                <div class="step-content">
                                    <h4>Processing</h4>
                                    <p>Your order is being prepared by our traders</p>
                                </div>
                            </div>
                            
                            <div class="step">
                                <div class="step-icon">
                                    <i class="fas fa-truck"></i>
                                </div>
                                <div class="step-content">
                                    <h4>Shipping</h4>
                                    <p>You'll receive tracking information once shipped</p>
                                </div>
                            </div>
                            
                            <div class="step">
                                <div class="step-icon">
                                    <i class="fas fa-home"></i>
                                </div>
                                <div class="step-content">
                                    <h4>Delivery</h4>
                                    <p>Estimated delivery by <?php echo $deliveryDate; ?></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="success-card">
                        <h2><i class="fas fa-bolt"></i> Quick Actions</h2>
                        
                        <div class="quick-actions">
                            <a href="../customer/orders.php" class="action-btn primary">
                                <i class="fas fa-list"></i>
                                View All Orders
                            </a>
                            
                            <a href="../index.php" class="action-btn secondary">
                                <i class="fas fa-shopping-cart"></i>
                                Continue Shopping
                            </a>
                            
                            <a href="../customer/profile.php" class="action-btn secondary">
                                <i class="fas fa-user"></i>
                                My Account
                            </a>
                        </div>
                    </div>

                    <!-- Support -->
                    <div class="success-card">
                        <h2><i class="fas fa-headset"></i> Need Help?</h2>
                        
                        <div class="support-info">
                            <p>If you have any questions about your order, don't hesitate to contact us.</p>
                            
                            <div class="support-options">
                                <div class="support-item">
                                    <i class="fas fa-envelope"></i>
                                    <span>support@shopfusion.com</span>
                                </div>
                                <div class="support-item">
                                    <i class="fas fa-phone"></i>
                                    <span>1-800-SHOPFUSION</span>
                                </div>
                                <div class="support-item">
                                    <i class="fas fa-clock"></i>
                                    <span>24/7 Customer Support</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h4>Shopfusion</h4>
                    <p>Your trusted online shopping destination.</p>
                </div>
                <div class="footer-section">
                    <h4>Customer Care</h4>
                    <ul>
                        <li><a href="#">Order Status</a></li>
                        <li><a href="#">Shipping Info</a></li>
                        <li><a href="#">Returns</a></li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2024 Shopfusion. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script>
        // Celebration animation
        document.addEventListener('DOMContentLoaded', function() {
            // Animate success icon
            const successIcon = document.querySelector('.success-icon i');
            successIcon.style.transform = 'scale(0)';
            successIcon.style.transition = 'transform 0.5s ease-out';
            
            setTimeout(() => {
                successIcon.style.transform = 'scale(1)';
            }, 200);
            
            // Animate cards
            const cards = document.querySelectorAll('.success-card, .success-section');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    card.style.transition = 'opacity 0.5s, transform 0.5s';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, 300 + (index * 100));
            });
            
            // Confetti effect (simple)
            createConfetti();
        });
        
        function createConfetti() {
            const colors = ['#007bff', '#28a745', '#ffc107', '#dc3545', '#17a2b8'];
            const confettiCount = 50;
            
            for (let i = 0; i < confettiCount; i++) {
                setTimeout(() => {
                    const confetti = document.createElement('div');
                    confetti.style.cssText = `
                        position: fixed;
                        top: -10px;
                        left: ${Math.random() * 100}%;
                        width: 10px;
                        height: 10px;
                        background: ${colors[Math.floor(Math.random() * colors.length)]};
                        z-index: 1000;
                        animation: confetti-fall 3s linear forwards;
                        transform: rotate(${Math.random() * 360}deg);
                    `;
                    
                    document.body.appendChild(confetti);
                    
                    setTimeout(() => confetti.remove(), 3000);
                }, i * 50);
            }
        }
        
        // Add confetti animation CSS
        const style = document.createElement('style');
        style.textContent = `
            @keyframes confetti-fall {
                to {
                    transform: translateY(100vh) rotate(720deg);
                    opacity: 0;
                }
            }
        `;
        document.head.appendChild(style);
    </script>

    <style>
        /* Success Page Styles */
        .success-header {
            text-align: center;
            margin: 2rem 0;
            padding: 2rem;
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            border-radius: 10px;
        }
        
        .success-icon {
            margin-bottom: 1rem;
        }
        
        .success-icon i {
            font-size: 4rem;
            color: white;
        }
        
        .success-header h1 {
            margin-bottom: 1rem;
            font-size: 2.5rem;
        }
        
        .success-message {
            font-size: 1.1rem;
            margin-bottom: 1rem;
            opacity: 0.9;
        }
        
        .order-number {
            background: rgba(255,255,255,0.2);
            padding: 0.75rem 1.5rem;
            border-radius: 25px;
            display: inline-block;
            font-size: 1.2rem;
        }
        
        .success-content {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
            margin: 2rem 0;
        }
        
        .success-section {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        .success-section h2 {
            color: #333;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .order-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
        }
        
        .info-item {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .info-item label {
            font-weight: 600;
            color: #666;
            font-size: 0.9rem;
        }
        
        .info-item span {
            color: #333;
        }
        
        .status-badge {
            padding: 0.3rem 0.8rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 500;
            text-transform: uppercase;
            display: inline-block;
            width: fit-content;
        }
        
        .status-processing { background: #d1ecf1; color: #0c5460; }
        .status-completed { background: #d4edda; color: #155724; }
        
        .transaction-id {
            font-family: 'Courier New', monospace;
            background: #f8f9fa;
            padding: 0.25rem 0.5rem;
            border-radius: 3px;
            font-size: 0.85rem;
        }
        
        .delivery-date {
            color: #28a745;
            font-weight: 600;
        }
        
        .shipping-address {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
            border-left: 4px solid #007bff;
        }
        
        .shop-group {
            border: 1px solid #eee;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            overflow: hidden;
        }
        
        .shop-header {
            background: #f8f9fa;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #eee;
        }
        
        .shop-header h3 {
            color: #007bff;
            margin-bottom: 0.3rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .shop-header p {
            color: #666;
            margin: 0;
        }
        
        .shop-items {
            padding: 1rem;
        }
        
        .order-item {
            display: flex;
            gap: 1rem;
            padding: 1rem 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .order-item:last-child {
            border-bottom: none;
        }
        
        .order-item .item-image img {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 5px;
        }
        
        .order-item .item-details {
            flex: 1;
        }
        
        .order-item h4 {
            margin-bottom: 0.5rem;
            font-size: 0.95rem;
        }
        
        .item-pricing {
            display: flex;
            gap: 2rem;
            font-size: 0.85rem;
            color: #666;
        }
        
        .order-item .item-total {
            font-weight: 600;
            color: #28a745;
        }
        
        .success-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        .success-card h2 {
            color: #333;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .total-breakdown .total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.75rem;
        }
        
        .total-row.discount {
            color: #28a745;
        }
        
        .total-row.points {
            color: #ffc107;
        }
        
        .total-row.shipping .free {
            color: #28a745;
            font-weight: 600;
        }
        
        .total-row.final {
            font-size: 1.2rem;
            font-weight: 600;
            padding-top: 1rem;
            border-top: 2px solid #eee;
        }
        
        .points-earned {
            background: #fff3cd;
            color: #856404;
            padding: 0.75rem;
            border-radius: 5px;
            margin-top: 1rem;
            text-align: center;
        }
        
        .next-steps {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        .step {
            display: flex;
            gap: 1rem;
            align-items: flex-start;
        }
        
        .step-icon {
            width: 40px;
            height: 40px;
            background: #e7f3ff;
            color: #007bff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        
        .step-content h4 {
            margin-bottom: 0.3rem;
            color: #333;
        }
        
        .step-content p {
            color: #666;
            font-size: 0.9rem;
            margin: 0;
        }
        
        .quick-actions {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }
        
        .action-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.75rem 1rem;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.2s;
        }
        
        .action-btn.primary {
            background: #007bff;
            color: white;
        }
        
        .action-btn.primary:hover {
            background: #0056b3;
            transform: translateY(-1px);
        }
        
        .action-btn.secondary {
            background: #f8f9fa;
            color: #333;
            border: 1px solid #ddd;
        }
        
        .action-btn.secondary:hover {
            background: #e9ecef;
            transform: translateY(-1px);
        }
        
        .support-info p {
            margin-bottom: 1rem;
            color: #666;
        }
        
        .support-options {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }
        
        .support-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            color: #666;
        }
        
        .support-item i {
            width: 20px;
            color: #007bff;
        }
        
        @media (max-width: 768px) {
            .success-content {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .success-header h1 {
                font-size: 2rem;
            }
            
            .order-info-grid {
                grid-template-columns: 1fr;
            }
            
            .order-item {
                flex-direction: column;
                text-align: center;
            }
            
            .item-pricing {
                justify-content: center;
            }
            
            .step {
                text-align: center;
                flex-direction: column;
            }
        }
    </style>
</body>
</html>
