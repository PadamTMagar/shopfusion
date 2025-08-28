<?php
// payment/paypal.php - PayPal Payment Integration
require_once '../config/database.php';
require_once '../includes/session.php';
require_once '../includes/functions.php';

// Require login
requireLogin();

$orderId = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;

if (!$orderId) {
    setFlashMessage('error', 'Invalid order ID');
    header('Location: ../shop/cart.php');
    exit();
}

// Verify order belongs to current user
try {
    $stmt = $pdo->prepare("
        SELECT * FROM orders 
        WHERE order_id = ? AND customer_id = ? AND payment_status = 'pending'
    ");
    $stmt->execute([$orderId, getUserId()]);
    $order = $stmt->fetch();
    
    if (!$order) {
        setFlashMessage('error', 'Order not found or already processed');
        header('Location: ../customer/orders.php');
        exit();
    }
} catch (PDOException $e) {
    setFlashMessage('error', 'Database error occurred');
    header('Location: ../shop/cart.php');
    exit();
}

// PayPal Sandbox Configuration
$paypalClientId = 'YOUR_PAYPAL_CLIENT_ID'; // Replace with actual PayPal Client ID
$paypalClientSecret = 'YOUR_PAYPAL_CLIENT_SECRET'; // Replace with actual PayPal Client Secret
$paypalBaseUrl = 'https://api.sandbox.paypal.com'; // Use https://api.paypal.com for production

// Handle PayPal return
if (isset($_GET['paymentId']) && isset($_GET['PayerID'])) {
    $paymentId = sanitize($_GET['paymentId']);
    $payerId = sanitize($_GET['PayerID']);
    
    // Execute PayPal payment (simplified for demo)
    try {
        // In a real implementation, you would verify the payment with PayPal API
        // For this demo, we'll mark the order as completed
        
        $stmt = $pdo->prepare("
            UPDATE orders 
            SET payment_status = 'completed', 
                order_status = 'processing',
                paypal_transaction_id = ?,
                updated_at = NOW()
            WHERE order_id = ?
        ");
        $stmt->execute([$paymentId, $orderId]);
        
        // Update user points if earned
        if ($order['points_earned'] > 0) {
            $stmt = $pdo->prepare("
                UPDATE users 
                SET loyalty_points = loyalty_points + ? 
                WHERE user_id = ?
            ");
            $stmt->execute([$order['points_earned'], $order['customer_id']]);
        }
        
        // Clear pending order session
        unset($_SESSION['pending_order']);
        
        setFlashMessage('success', 'Payment successful! Your order has been placed.');
        header('Location: success.php?order_id=' . $orderId);
        exit();
        
    } catch (PDOException $e) {
        setFlashMessage('error', 'Failed to process payment. Please contact support.');
        header('Location: ../shop/checkout.php');
        exit();
    }
}

// Handle PayPal cancel
if (isset($_GET['cancel'])) {
    // Mark order as cancelled and restore cart items
    try {
        $pdo->beginTransaction();
        
        // Get order items
        $stmt = $pdo->prepare("
            SELECT oi.product_id, oi.quantity 
            FROM order_items oi
            WHERE oi.order_id = ?
        ");
        $stmt->execute([$orderId]);
        $orderItems = $stmt->fetchAll();
        
        // Restore stock
        foreach ($orderItems as $item) {
            $stmt = $pdo->prepare("
                UPDATE products 
                SET stock_quantity = stock_quantity + ? 
                WHERE product_id = ?
            ");
            $stmt->execute([$item['quantity'], $item['product_id']]);
            
            // Add back to cart
            addToCart($order['customer_id'], $item['product_id'], $item['quantity']);
        }
        
        // Restore points if used
        if ($order['points_used'] > 0) {
            $stmt = $pdo->prepare("
                UPDATE users 
                SET loyalty_points = loyalty_points + ? 
                WHERE user_id = ?
            ");
            $stmt->execute([$order['points_used'], $order['customer_id']]);
        }
        
        // Delete order and order items
        $stmt = $pdo->prepare("DELETE FROM order_items WHERE order_id = ?");
        $stmt->execute([$orderId]);
        
        $stmt = $pdo->prepare("DELETE FROM orders WHERE order_id = ?");
        $stmt->execute([$orderId]);
        
        $pdo->commit();
        
        unset($_SESSION['pending_order']);
        
        setFlashMessage('info', 'Payment cancelled. Items have been restored to your cart.');
        header('Location: ../shop/cart.php');
        exit();
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        setFlashMessage('error', 'Error processing cancellation.');
        header('Location: ../shop/cart.php');
        exit();
    }
}

// Get order items for display
try {
    $stmt = $pdo->prepare("
        SELECT oi.*, p.product_name, p.image_path, s.shop_name
        FROM order_items oi
        JOIN products p ON oi.product_id = p.product_id
        JOIN shops s ON oi.shop_id = s.shop_id
        WHERE oi.order_id = ?
    ");
    $stmt->execute([$orderId]);
    $orderItems = $stmt->fetchAll();
} catch (PDOException $e) {
    $orderItems = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PayPal Payment - ShopFusion</title>
    
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/theme.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- PayPal SDK -->
    <script src="https://www.paypal.com/sdk/js?client-id=<?php echo $paypalClientId; ?>&currency=USD"></script>
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
                    <div class="progress-step active">
                        <i class="fab fa-paypal"></i>
                        <span>Payment</span>
                    </div>
                </div>
                
                <div class="user-menu">
                    <div class="user-info">
                        <i class="fas fa-user"></i>
                        <span><?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="main-content">
        <div class="container">
            <div class="payment-container">
                <div class="payment-header">
                    <h1><i class="fab fa-paypal"></i> PayPal Payment</h1>
                    <p>Complete your payment securely with PayPal</p>
                </div>
                
                <div class="payment-content">
                    <!-- Order Summary -->
                    <div class="payment-summary">
                        <h2><i class="fas fa-receipt"></i> Order Summary</h2>
                        <div class="order-details">
                            <div class="order-info">
                                <h3>Order #<?php echo $order['order_id']; ?></h3>
                                <p class="order-date"><?php echo formatDateTime($order['created_at']); ?></p>
                            </div>
                            
                            <!-- Order Items -->
                            <div class="order-items">
                                <?php foreach ($orderItems as $item): ?>
                                    <div class="order-item">
                                        <div class="item-image">
                                            <img src="<?php echo htmlspecialchars(getImagePath($item['image_path'], '../')); ?>" 
                                                 alt="<?php echo htmlspecialchars($item['product_name']); ?>">
                                        </div>
                                        <div class="item-details">
                                            <h4><?php echo htmlspecialchars($item['product_name']); ?></h4>
                                            <p class="item-shop"><?php echo htmlspecialchars($item['shop_name']); ?></p>
                                            <div class="item-pricing">
                                                <span>Qty: <?php echo $item['quantity']; ?></span>
                                                <span><?php echo formatPrice($item['unit_price']); ?></span>
                                            </div>
                                        </div>
                                        <div class="item-total">
                                            <?php echo formatPrice($item['subtotal']); ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <!-- Payment Totals -->
                            <div class="payment-totals">
                                <?php if ($order['discount_amount'] > 0): ?>
                                    <div class="total-row">
                                        <span>Discount<?php echo $order['promo_code'] ? ' (' . $order['promo_code'] . ')' : ''; ?>:</span>
                                        <span>-<?php echo formatPrice($order['discount_amount']); ?></span>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($order['points_used'] > 0): ?>
                                    <div class="total-row">
                                        <span>Points Discount (<?php echo $order['points_used']; ?> points):</span>
                                        <span>-<?php echo formatPrice($order['points_used'] / 100); ?></span>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="total-row final">
                                    <strong>
                                        <span>Total Amount:</span>
                                        <span><?php echo formatPrice($order['total_amount']); ?></span>
                                    </strong>
                                </div>
                                
                                <?php if ($order['points_earned'] > 0): ?>
                                    <div class="points-earned">
                                        <i class="fas fa-star"></i>
                                        You'll earn <strong><?php echo $order['points_earned']; ?></strong> points with this order!
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- PayPal Payment -->
                    <div class="paypal-section">
                        <div class="payment-methods">
                            <h3>Choose your payment method</h3>
                            
                            <!-- PayPal Button Container -->
                            <div id="paypal-button-container"></div>
                            
                            <!-- Alternative Payment Message -->
                            <div class="payment-note">
                                <p><i class="fas fa-info-circle"></i> You can pay with your PayPal account or credit card</p>
                            </div>
                            
                            <!-- Demo Mode Notice -->
                            <div class="demo-notice">
                                <i class="fas fa-exclamation-triangle"></i>
                                <strong>Demo Mode:</strong> This is a PayPal Sandbox environment for testing purposes only.
                                No real money will be charged.
                            </div>
                        </div>
                        
                        <!-- Security Info -->
                        <div class="security-info">
                            <div class="security-item">
                                <i class="fas fa-shield-alt"></i>
                                <span>256-bit SSL Encryption</span>
                            </div>
                            <div class="security-item">
                                <i class="fab fa-paypal"></i>
                                <span>PayPal Buyer Protection</span>
                            </div>
                            <div class="security-item">
                                <i class="fas fa-lock"></i>
                                <span>Secure Payment</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Back to Checkout -->
                <div class="payment-actions">
                    <a href="../shop/checkout.php" class="btn-back">
                        <i class="fas fa-arrow-left"></i> Back to Checkout
                    </a>
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
            </div>
            <div class="footer-bottom">
                <p>&copy; 2024 Shopfusion. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script>
        // PayPal Button Configuration
        paypal.Buttons({
            style: {
                layout: 'vertical',
                color: 'blue',
                shape: 'rect',
                label: 'paypal'
            },
            
            createOrder: function(data, actions) {
                return actions.order.create({
                    purchase_units: [{
                        amount: {
                            value: '<?php echo number_format($order['total_amount'], 2, '.', ''); ?>',
                            currency_code: 'USD'
                        },
                        description: 'ShopFusion Order #<?php echo $order['order_id']; ?>',
                        reference_id: '<?php echo $order['order_id']; ?>'
                    }]
                });
            },
            
            onApprove: function(data, actions) {
                return actions.order.capture().then(function(details) {
                    // Show success message
                    showNotification('Payment completed successfully!', 'success');
                    
                    // Redirect to success page with payment details
                    window.location.href = 'paypal.php?order_id=<?php echo $orderId; ?>&paymentId=' + 
                                         data.orderID + '&PayerID=' + data.payerID;
                });
            },
            
            onError: function(err) {
                console.error('PayPal Error:', err);
                showNotification('Payment error occurred. Please try again.', 'error');
            },
            
            onCancel: function(data) {
                if (confirm('Are you sure you want to cancel the payment? Your order will be cancelled and items will be returned to your cart.')) {
                    window.location.href = 'paypal.php?order_id=<?php echo $orderId; ?>&cancel=1';
                }
            }
        }).render('#paypal-button-container');
        
        // Show notification function
        function showNotification(message, type) {
            const notification = document.createElement('div');
            notification.className = `notification notification-${type}`;
            notification.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
                <span>${message}</span>
            `;
            
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 1rem 1.5rem;
                border-radius: 5px;
                color: white;
                font-weight: 500;
                z-index: 999999;
                opacity: 0;
                transform: translateX(100%);
                transition: all 0.3s ease;
                display: flex;
                align-items: center;
                gap: 0.5rem;
            `;
            
            if (type === 'success') {
                notification.style.backgroundColor = '#28a745';
            } else {
                notification.style.backgroundColor = '#dc3545';
            }
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.style.opacity = '1';
                notification.style.transform = 'translateX(0)';
            }, 10);
            
            setTimeout(() => {
                notification.style.opacity = '0';
                notification.style.transform = 'translateX(100%)';
                setTimeout(() => notification.remove(), 300);
            }, 5000);
        }
    </script>

    <style>
        /* Payment Page Styles */
        .payment-container {
            max-width: 1000px;
            margin: 2rem auto;
        }
        
        .payment-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .payment-header h1 {
            color: #003087;
            margin-bottom: 0.5rem;
        }
        
        .payment-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }
        
        .payment-summary {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 2rem;
        }
        
        .payment-summary h2 {
            color: #333;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .order-info h3 {
            color: #007bff;
            margin-bottom: 0.5rem;
        }
        
        .order-date {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 1.5rem;
        }
        
        .order-items {
            margin-bottom: 1.5rem;
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
            margin-bottom: 0.3rem;
            font-size: 0.9rem;
        }
        
        .item-shop {
            color: #666;
            font-size: 0.8rem;
            margin-bottom: 0.5rem;
        }
        
        .item-pricing {
            display: flex;
            justify-content: space-between;
            font-size: 0.8rem;
            color: #666;
        }
        
        .order-item .item-total {
            font-weight: 600;
            color: #28a745;
        }
        
        .payment-totals {
            border-top: 1px solid #eee;
            padding-top: 1rem;
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.75rem;
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
        
        .paypal-section {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 2rem;
        }
        
        .payment-methods h3 {
            color: #333;
            margin-bottom: 1.5rem;
            text-align: center;
        }
        
        #paypal-button-container {
            margin: 2rem 0;
        }
        
        .payment-note {
            background: #e7f3ff;
            color: #0c5460;
            padding: 1rem;
            border-radius: 5px;
            margin: 1rem 0;
            text-align: center;
        }
        
        .demo-notice {
            background: #fff3cd;
            color: #856404;
            padding: 1rem;
            border-radius: 5px;
            margin: 1rem 0;
            text-align: center;
        }
        
        .security-info {
            display: flex;
            justify-content: space-around;
            margin-top: 2rem;
            padding-top: 1rem;
            border-top: 1px solid #eee;
        }
        
        .security-item {
            text-align: center;
            color: #28a745;
            font-size: 0.8rem;
        }
        
        .security-item i {
            display: block;
            font-size: 1.2rem;
            margin-bottom: 0.3rem;
        }
        
        .security-item .fab.fa-paypal {
            color: #003087;
        }
        
        .payment-actions {
            text-align: center;
            margin: 2rem 0;
        }
        
        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: #6c757d;
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 5px;
            text-decoration: none;
            transition: background 0.2s;
        }
        
        .btn-back:hover {
            background: #5a6268;
        }
        
        .progress-step .fab.fa-paypal {
            color: #003087;
        }
        
        @media (max-width: 768px) {
            .payment-content {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .order-item {
                flex-direction: column;
                text-align: center;
            }
            
            .item-pricing {
                justify-content: center;
                gap: 2rem;
            }
            
            .security-info {
                flex-direction: column;
                gap: 1rem;
            }
        }
    </style>
</body>
</html>
