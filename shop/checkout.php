<?php
// shop/checkout.php - Checkout Process
require_once '../config/database.php';
require_once '../includes/session.php';
require_once '../includes/functions.php';

// Require login for checkout
requireLogin();

$error = '';
$success = '';

// Get cart items and validate
if (isCustomer()) {
    $cartItems = getCartItems(getUserId());
    $cartTotal = getCartTotal(getUserId());
    $cartCount = getCartCount(getUserId());
} else {
    // Merge guest cart if customer just logged in
    if (isset($_SESSION['guest_cart']) && !empty($_SESSION['guest_cart'])) {
        foreach ($_SESSION['guest_cart'] as $productId => $quantity) {
            addToCart(getUserId(), $productId, $quantity);
        }
        unset($_SESSION['guest_cart']);
    }
    
    $cartItems = getCartItems(getUserId());
    $cartTotal = getCartTotal(getUserId());
    $cartCount = getCartCount(getUserId());
}

// Redirect if cart is empty
if (empty($cartItems) || $cartTotal <= 0) {
    setFlashMessage('error', 'Your cart is empty. Add some items before checkout.');
    header('Location: cart.php');
    exit();
}

// Get user info for form
$currentUser = getCurrentUser();
$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$currentUser['user_id']]);
$userData = $stmt->fetch();

// Calculate totals
$subtotal = $cartTotal;
$taxRate = 0.08; // 8% tax
$taxAmount = $subtotal * $taxRate;
$discountAmount = 0;
$promoCode = '';
$pointsUsed = 0;
$pointsDiscount = 0;

// Check for applied promo code
if (isset($_SESSION['applied_promo'])) {
    $promoCode = $_SESSION['applied_promo']['code'];
    $discountAmount = $_SESSION['applied_promo']['discount'];
}

// Handle points usage
if (isset($_POST['use_points']) && $_POST['use_points'] > 0) {
    $requestedPoints = intval($_POST['use_points']);
    $maxPoints = min($userData['loyalty_points'], floor($subtotal * 100)); // 100 points = $1
    $pointsUsed = min($requestedPoints, $maxPoints);
    $pointsDiscount = $pointsUsed / 100; // Convert points to dollars
}

$finalTotal = max(0, $subtotal - $discountAmount - $pointsDiscount + $taxAmount);

// Calculate points earned (1 point per dollar spent)
$pointsEarned = floor($finalTotal);

// Handle form submission
if ($_POST && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'place_order':
            $shippingAddress = sanitize($_POST['shipping_address']);
            $paymentMethod = sanitize($_POST['payment_method']);
            $usePoints = intval($_POST['use_points'] ?? 0);
            
            if (empty($shippingAddress)) {
                $error = 'Shipping address is required.';
                break;
            }
            
            if (empty($paymentMethod)) {
                $error = 'Please select a payment method.';
                break;
            }
            
            // Validate points usage
            if ($usePoints > 0) {
                if ($usePoints > $userData['loyalty_points']) {
                    $error = 'You don\'t have enough loyalty points.';
                    break;
                }
                
                $maxPointsUsable = floor($subtotal * 100);
                if ($usePoints > $maxPointsUsable) {
                    $error = 'You can only use up to ' . $maxPointsUsable . ' points for this order.';
                    break;
                }
                
                $pointsUsed = $usePoints;
                $pointsDiscount = $pointsUsed / 100;
                $finalTotal = max(0, $subtotal - $discountAmount - $pointsDiscount + $taxAmount);
                $pointsEarned = floor($finalTotal);
            }
            
            // Validate stock availability
            foreach ($cartItems as $item) {
                $stmt = $pdo->prepare("SELECT stock_quantity FROM products WHERE product_id = ?");
                $stmt->execute([$item['product_id']]);
                $currentStock = $stmt->fetch()['stock_quantity'];
                
                if ($currentStock < $item['quantity']) {
                    $error = "Insufficient stock for {$item['product_name']}. Only {$currentStock} available.";
                    break;
                }
            }
            
            if (!$error) {
                try {
                    $pdo->beginTransaction();
                    
                    // Create order
                    $stmt = $pdo->prepare("
                        INSERT INTO orders (customer_id, total_amount, shipping_address, payment_method, 
                                          promo_code, discount_amount, points_used, points_earned) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $currentUser['user_id'],
                        $finalTotal,
                        $shippingAddress,
                        $paymentMethod,
                        $promoCode ?: null,
                        $discountAmount,
                        $pointsUsed,
                        $pointsEarned
                    ]);
                    
                    $orderId = $pdo->lastInsertId();
                    
                    // Add order items and update stock
                    foreach ($cartItems as $item) {
                        // Insert order item
                        $stmt = $pdo->prepare("
                            INSERT INTO order_items (order_id, product_id, shop_id, quantity, unit_price, subtotal) 
                            VALUES (?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([
                            $orderId,
                            $item['product_id'],
                            $item['shop_id'],
                            $item['quantity'],
                            $item['price'],
                            $item['price'] * $item['quantity']
                        ]);
                        
                        // Update product stock
                        $stmt = $pdo->prepare("
                            UPDATE products 
                            SET stock_quantity = stock_quantity - ? 
                            WHERE product_id = ?
                        ");
                        $stmt->execute([$item['quantity'], $item['product_id']]);
                    }
                    
                    // Update user points
                    if ($pointsUsed > 0) {
                        $stmt = $pdo->prepare("
                            UPDATE users 
                            SET loyalty_points = loyalty_points - ? 
                            WHERE user_id = ?
                        ");
                        $stmt->execute([$pointsUsed, $currentUser['user_id']]);
                    }
                    
                    if ($pointsEarned > 0) {
                        $stmt = $pdo->prepare("
                            UPDATE users 
                            SET loyalty_points = loyalty_points + ? 
                            WHERE user_id = ?
                        ");
                        $stmt->execute([$pointsEarned, $currentUser['user_id']]);
                    }
                    
                    // Apply promo code usage
                    if ($promoCode) {
                        applyPromoCode($_SESSION['applied_promo']['promo_id']);
                        unset($_SESSION['applied_promo']);
                    }
                    
                    // Clear cart
                    clearCart($currentUser['user_id']);
                    
                    $pdo->commit();
                    
                    // Redirect to payment
                    if ($paymentMethod === 'paypal') {
                        $_SESSION['pending_order'] = $orderId;
                        header("Location: ../payment/paypal.php?order_id=$orderId");
                        exit();
                    } else {
                        // For other payment methods, mark as completed for demo
                        $stmt = $pdo->prepare("
                            UPDATE orders 
                            SET payment_status = 'completed', order_status = 'processing' 
                            WHERE order_id = ?
                        ");
                        $stmt->execute([$orderId]);
                        
                        setFlashMessage('success', 'Order placed successfully! Order #' . $orderId);
                        header('Location: ../customer/orders.php');
                        exit();
                    }
                    
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    $error = 'Failed to place order. Please try again.';
                }
            }
            break;
    }
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
    <title>Checkout - ShopFusion</title>
    
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
                    <div class="progress-step active">
                        <i class="fas fa-credit-card"></i>
                        <span>Checkout</span>
                    </div>
                    <div class="progress-line"></div>
                    <div class="progress-step">
                        <i class="fas fa-check"></i>
                        <span>Complete</span>
                    </div>
                </div>
                
                <div class="user-menu">
                    <div class="user-info">
                        <i class="fas fa-user"></i>
                        <span><?php echo htmlspecialchars($userData['full_name']); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Breadcrumb -->
    <div class="breadcrumb">
        <div class="container">
            <nav class="breadcrumb-nav">
                <a href="../index.php"><i class="fas fa-home"></i> Home</a>
                <span class="separator">></span>
                <a href="cart.php">Cart</a>
                <span class="separator">></span>
                <span class="current">Checkout</span>
            </nav>
        </div>
    </div>

    <!-- Flash Messages -->
    <?php if ($error): ?>
        <div class="alert alert-error">
            <div class="container">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="alert alert-success">
            <div class="container">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($success); ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Main Content -->
    <main class="main-content">
        <div class="container">
            <div class="checkout-header">
                <h1><i class="fas fa-credit-card"></i> Checkout</h1>
                <p>Review your order and complete your purchase</p>
            </div>

            <form method="POST" class="checkout-form">
                <input type="hidden" name="action" value="place_order">
                
                <div class="checkout-content">
                    <!-- Shipping & Payment Info -->
                    <div class="checkout-left">
                        <!-- Shipping Address -->
                        <div class="checkout-section">
                            <h2><i class="fas fa-shipping-fast"></i> Shipping Address</h2>
                            
                            <div class="address-options">
                                <label class="address-option">
                                    <input type="radio" name="address_type" value="existing" 
                                           <?php echo $userData['address'] ? 'checked' : ''; ?>>
                                    <div class="address-content">
                                        <strong>Use saved address</strong>
                                        <?php if ($userData['address']): ?>
                                            <p><?php echo nl2br(htmlspecialchars($userData['address'])); ?></p>
                                        <?php else: ?>
                                            <p class="no-address">No saved address found</p>
                                        <?php endif; ?>
                                    </div>
                                </label>
                                
                                <label class="address-option">
                                    <input type="radio" name="address_type" value="new" 
                                           <?php echo !$userData['address'] ? 'checked' : ''; ?>>
                                    <div class="address-content">
                                        <strong>Enter new address</strong>
                                    </div>
                                </label>
                            </div>
                            
                            <div class="form-group">
                                <label for="shipping_address">Shipping Address *</label>
                                <textarea name="shipping_address" id="shipping_address" rows="4" required
                                          placeholder="Enter your complete shipping address..."><?php echo htmlspecialchars($userData['address'] ?? ''); ?></textarea>
                            </div>
                        </div>

                        <!-- Payment Method -->
                        <div class="checkout-section">
                            <h2><i class="fas fa-credit-card"></i> Payment Method</h2>
                            
                            <div class="payment-options">
                                <label class="payment-option">
                                    <input type="radio" name="payment_method" value="paypal" required>
                                    <div class="payment-content">
                                        <div class="payment-icon">
                                            <i class="fab fa-paypal"></i>
                                        </div>
                                        <div class="payment-details">
                                            <strong>PayPal</strong>
                                            <p>Pay securely with your PayPal account</p>
                                        </div>
                                    </div>
                                </label>
                                
                                <label class="payment-option">
                                    <input type="radio" name="payment_method" value="credit_card" required>
                                    <div class="payment-content">
                                        <div class="payment-icon">
                                            <i class="fas fa-credit-card"></i>
                                        </div>
                                        <div class="payment-details">
                                            <strong>Credit/Debit Card</strong>
                                            <p>Visa, Mastercard, American Express</p>
                                        </div>
                                    </div>
                                </label>
                                
                                <label class="payment-option">
                                    <input type="radio" name="payment_method" value="bank_transfer" required>
                                    <div class="payment-content">
                                        <div class="payment-icon">
                                            <i class="fas fa-university"></i>
                                        </div>
                                        <div class="payment-details">
                                            <strong>Bank Transfer</strong>
                                            <p>Direct bank transfer</p>
                                        </div>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <!-- Loyalty Points -->
                        <?php if ($userData['loyalty_points'] > 0): ?>
                            <div class="checkout-section">
                                <h2><i class="fas fa-star"></i> Loyalty Points</h2>
                                
                                <div class="points-info">
                                    <p>You have <strong><?php echo number_format($userData['loyalty_points']); ?> points</strong> available</p>
                                    <p>100 points = $1.00 discount</p>
                                </div>
                                
                                <div class="points-usage">
                                    <label class="checkbox-container">
                                        <input type="checkbox" id="usePointsCheck" onchange="togglePointsUsage()">
                                        <span class="checkmark"></span>
                                        Use loyalty points for discount
                                    </label>
                                    
                                    <div class="points-input" id="pointsInput" style="display: none;">
                                        <label for="use_points">Points to use:</label>
                                        <div class="points-controls">
                                            <input type="number" name="use_points" id="use_points" 
                                                   min="0" max="<?php echo min($userData['loyalty_points'], floor($subtotal * 100)); ?>" 
                                                   value="0" onchange="updatePointsDiscount()">
                                            <button type="button" onclick="useMaxPoints()">Use Maximum</button>
                                        </div>
                                        <small>Maximum usable: <?php echo min($userData['loyalty_points'], floor($subtotal * 100)); ?> points (<?php echo formatPrice(min($userData['loyalty_points'], floor($subtotal * 100)) / 100); ?>)</small>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Order Summary -->
                    <div class="checkout-right">
                        <div class="order-summary">
                            <h2><i class="fas fa-receipt"></i> Order Summary</h2>
                            
                            <!-- Order Items -->
                            <div class="order-items">
                                <?php foreach ($cartItems as $item): ?>
                                    <div class="order-item">
                                        <div class="item-image">
                                            <img src="<?php echo htmlspecialchars(getImagePath($item['image_path'], '../')); ?>" 
                                                 alt="<?php echo htmlspecialchars($item['product_name']); ?>">
                                        </div>
                                        <div class="item-details">
                                            <h4><?php echo htmlspecialchars($item['product_name']); ?></h4>
                                            <p class="item-shop"><?php echo htmlspecialchars($item['shop_name']); ?></p>
                                            <div class="item-pricing">
                                                <span class="quantity">Qty: <?php echo $item['quantity']; ?></span>
                                                <span class="price"><?php echo formatPrice($item['price']); ?></span>
                                            </div>
                                        </div>
                                        <div class="item-total">
                                            <?php echo formatPrice($item['price'] * $item['quantity']); ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <!-- Totals -->
                            <div class="order-totals">
                                <div class="total-row">
                                    <span>Subtotal (<?php echo $cartCount; ?> items):</span>
                                    <span id="subtotalAmount"><?php echo formatPrice($subtotal); ?></span>
                                </div>
                                
                                <?php if ($discountAmount > 0): ?>
                                    <div class="total-row discount">
                                        <span>Discount (<?php echo htmlspecialchars($promoCode); ?>):</span>
                                        <span>-<?php echo formatPrice($discountAmount); ?></span>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="total-row points-discount" id="pointsDiscountRow" style="display: none;">
                                    <span>Points Discount (<span id="pointsUsedDisplay">0</span> points):</span>
                                    <span id="pointsDiscountAmount">-$0.00</span>
                                </div>
                                
                                <div class="total-row">
                                    <span>Tax (8%):</span>
                                    <span id="taxAmount"><?php echo formatPrice($taxAmount); ?></span>
                                </div>
                                
                                <div class="total-row shipping">
                                    <span>Shipping:</span>
                                    <span class="free">FREE</span>
                                </div>
                                
                                <hr>
                                
                                <div class="total-row final">
                                    <strong>
                                        <span>Total:</span>
                                        <span id="finalTotal"><?php echo formatPrice($finalTotal); ?></span>
                                    </strong>
                                </div>
                                
                                <?php if ($pointsEarned > 0): ?>
                                    <div class="points-earned">
                                        <i class="fas fa-star"></i>
                                        You'll earn <strong id="pointsEarnedDisplay"><?php echo $pointsEarned; ?></strong> points with this order!
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Place Order Button -->
                            <button type="submit" class="btn-place-order">
                                <i class="fas fa-lock"></i>
                                Place Order - <span id="orderTotal"><?php echo formatPrice($finalTotal); ?></span>
                            </button>
                            
                            <!-- Security Info -->
                            <div class="security-info">
                                <div class="security-item">
                                    <i class="fas fa-shield-alt"></i>
                                    <span>SSL Encrypted</span>
                                </div>
                                <div class="security-item">
                                    <i class="fas fa-lock"></i>
                                    <span>Secure Payment</span>
                                </div>
                                <div class="security-item">
                                    <i class="fas fa-undo"></i>
                                    <span>Money Back Guarantee</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
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
                    <h4>Support</h4>
                    <ul>
                        <li><a href="#">Help Center</a></li>
                        <li><a href="#">Contact Us</a></li>
                        <li><a href="#">Returns</a></li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2024 Shopfusion. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script src="../js/main.js"></script>
    
    <script>
        // Checkout page functionality
        document.addEventListener('DOMContentLoaded', function() {
            initializeCheckout();
        });
        
        function initializeCheckout() {
            // Address type selection
            document.querySelectorAll('input[name="address_type"]').forEach(radio => {
                radio.addEventListener('change', function() {
                    const addressTextarea = document.getElementById('shipping_address');
                    if (this.value === 'existing') {
                        addressTextarea.value = '<?php echo addslashes($userData['address'] ?? ''); ?>';
                    } else {
                        addressTextarea.value = '';
                        addressTextarea.focus();
                    }
                });
            });
        }
        
        // Toggle points usage
        function togglePointsUsage() {
            const checkbox = document.getElementById('usePointsCheck');
            const pointsInput = document.getElementById('pointsInput');
            const pointsDiscountRow = document.getElementById('pointsDiscountRow');
            
            if (checkbox.checked) {
                pointsInput.style.display = 'block';
                pointsDiscountRow.style.display = 'flex';
            } else {
                pointsInput.style.display = 'none';
                pointsDiscountRow.style.display = 'none';
                document.getElementById('use_points').value = 0;
                updatePointsDiscount();
            }
        }
        
        // Use maximum points
        function useMaxPoints() {
            const pointsInput = document.getElementById('use_points');
            pointsInput.value = pointsInput.max;
            updatePointsDiscount();
        }
        
        // Update points discount display
        function updatePointsDiscount() {
            const pointsUsed = parseInt(document.getElementById('use_points').value) || 0;
            const pointsDiscount = pointsUsed / 100;
            const subtotal = <?php echo $subtotal; ?>;
            const promoDiscount = <?php echo $discountAmount; ?>;
            const tax = (subtotal - promoDiscount - pointsDiscount) * 0.08;
            const finalTotal = Math.max(0, subtotal - promoDiscount - pointsDiscount + tax);
            const pointsEarned = Math.floor(finalTotal);
            
            // Update display
            document.getElementById('pointsUsedDisplay').textContent = pointsUsed;
            document.getElementById('pointsDiscountAmount').textContent = '-' + formatPrice(pointsDiscount);
            document.getElementById('taxAmount').textContent = formatPrice(tax);
            document.getElementById('finalTotal').textContent = formatPrice(finalTotal);
            document.getElementById('orderTotal').textContent = formatPrice(finalTotal);
            document.getElementById('pointsEarnedDisplay').textContent = pointsEarned;
        }
        
        // Format price
        function formatPrice(price) {
            return new Intl.NumberFormat('en-US', {
                style: 'currency',
                currency: 'USD'
            }).format(price);
        }
        
        // Form validation
        document.querySelector('.checkout-form').addEventListener('submit', function(e) {
            const shippingAddress = document.getElementById('shipping_address').value.trim();
            const paymentMethod = document.querySelector('input[name="payment_method"]:checked');
            
            if (!shippingAddress) {
                e.preventDefault();
                showNotification('Please enter a shipping address', 'error');
                return;
            }
            
            if (!paymentMethod) {
                e.preventDefault();
                showNotification('Please select a payment method', 'error');
                return;
            }
            
            // Show loading state
            const submitBtn = document.querySelector('.btn-place-order');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            submitBtn.disabled = true;
        });
        
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
        /* Checkout Page Styles */
        .checkout-progress {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .progress-step {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.5rem;
            color: #999;
            font-size: 0.8rem;
        }
        
        .progress-step i {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }
        
        .progress-step.completed {
            color: #28a745;
        }
        
        .progress-step.completed i {
            background: #28a745;
            color: white;
        }
        
        .progress-step.active {
            color: #007bff;
        }
        
        .progress-step.active i {
            background: #007bff;
            color: white;
        }
        
        .progress-line {
            width: 50px;
            height: 2px;
            background: #ddd;
        }
        
        .checkout-header {
            text-align: center;
            margin: 2rem 0;
        }
        
        .checkout-header h1 {
            color: #333;
            margin-bottom: 0.5rem;
        }
        
        .checkout-content {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
            margin: 2rem 0;
        }
        
        .checkout-section {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        .checkout-section h2 {
            color: #333;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .address-options {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .address-option {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            padding: 1rem;
            border: 2px solid #eee;
            border-radius: 8px;
            cursor: pointer;
            transition: border-color 0.2s;
        }
        
        .address-option:hover {
            border-color: #007bff;
        }
        
        .address-option input[type="radio"] {
            margin-top: 0.2rem;
        }
        
        .address-option input[type="radio"]:checked + .address-content {
            color: #007bff;
        }
        
        .no-address {
            color: #999;
            font-style: italic;
        }
        
        .payment-options {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        .payment-option {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            border: 2px solid #eee;
            border-radius: 8px;
            cursor: pointer;
            transition: border-color 0.2s;
        }
        
        .payment-option:hover {
            border-color: #007bff;
        }
        
        .payment-option input[type="radio"]:checked ~ .payment-content {
            color: #007bff;
        }
        
        .payment-content {
            display: flex;
            align-items: center;
            gap: 1rem;
            width: 100%;
        }
        
        .payment-icon {
            font-size: 2rem;
            width: 60px;
            text-align: center;
        }
        
        .payment-icon .fab.fa-paypal {
            color: #003087;
        }
        
        .payment-icon .fas.fa-credit-card {
            color: #007bff;
        }
        
        .payment-icon .fas.fa-university {
            color: #28a745;
        }
        
        .points-info {
            background: #e7f3ff;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }
        
        .points-usage {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        .points-controls {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .points-controls input {
            width: 120px;
            padding: 0.5rem;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        
        .points-controls button {
            background: #007bff;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            cursor: pointer;
        }
        
        .order-summary {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 2rem;
            position: sticky;
            top: 2rem;
            height: fit-content;
        }
        
        .order-summary h2 {
            color: #333;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .order-items {
            max-height: 300px;
            overflow-y: auto;
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
        
        .order-totals {
            border-top: 1px solid #eee;
            padding-top: 1rem;
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.75rem;
        }
        
        .total-row.discount {
            color: #28a745;
        }
        
        .total-row.points-discount {
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
            margin: 1rem 0;
            text-align: center;
        }
        
        .btn-place-order {
            width: 100%;
            background: #28a745;
            color: white;
            border: none;
            padding: 1rem;
            border-radius: 8px;
            font-weight: 600;
            font-size: 1.1rem;
            cursor: pointer;
            transition: all 0.2s;
            margin-top: 1rem;
        }
        
        .btn-place-order:hover {
            background: #218838;
            transform: translateY(-2px);
        }
        
        .btn-place-order:disabled {
            background: #6c757d;
            cursor: not-allowed;
            transform: none;
        }
        
        .security-info {
            display: flex;
            justify-content: space-around;
            margin-top: 1.5rem;
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
        
        @media (max-width: 768px) {
            .checkout-progress {
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .progress-line {
                width: 2px;
                height: 20px;
            }
            
            .checkout-content {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .address-options,
            .payment-options {
                gap: 0.5rem;
            }
            
            .payment-content {
                flex-direction: column;
                text-align: center;
            }
            
            .points-controls {
                flex-direction: column;
                align-items: stretch;
            }
            
            .security-info {
                flex-direction: column;
                gap: 1rem;
            }
        }
    </style>
</body>
</html>
