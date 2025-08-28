<?php
// shop/cart.php - Shopping Cart Page
require_once '../config/database.php';
require_once '../includes/session.php';
require_once '../includes/functions.php';

$error = '';
$success = '';

// Get cart items and totals
if (isLoggedIn() && isCustomer()) {
    $cartItems = getCartItems(getUserId());
    $cartTotal = getCartTotal(getUserId());
    $cartCount = getCartCount(getUserId());
} else {
    $cartItems = getGuestCartItems();
    $cartTotal = getGuestCartTotal();
    $cartCount = getGuestCartCount();
}

// Calculate totals with tax and discounts
$subtotal = $cartTotal;
$taxRate = 0.08; // 8% tax
$taxAmount = $subtotal * $taxRate;
$discountAmount = 0;
$promoCode = '';

// Check for applied promo code
if (isset($_SESSION['applied_promo'])) {
    $promoCode = $_SESSION['applied_promo']['code'];
    $discountAmount = $_SESSION['applied_promo']['discount'];
}

$finalTotal = max(0, $subtotal - $discountAmount + $taxAmount);

// Get available promo codes
try {
    $stmt = $pdo->prepare("
        SELECT * FROM promo_codes 
        WHERE status = 'active' 
        AND (start_date IS NULL OR start_date <= CURDATE()) 
        AND (end_date IS NULL OR end_date >= CURDATE())
        AND (max_uses IS NULL OR current_uses < max_uses)
        AND min_order_amount <= ?
        ORDER BY discount_value DESC
        LIMIT 5
    ");
    $stmt->execute([$subtotal]);
    $availablePromos = $stmt->fetchAll();
} catch (PDOException $e) {
    $availablePromos = [];
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
    <title>Shopping Cart - ShopFusion</title>
    
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
                
                <!-- Search Bar -->
                <div class="search-bar">
                    <form method="GET" action="../index.php" class="search-form">
                        <input type="text" name="search" placeholder="Search products...">
                        <button type="submit"><i class="fas fa-search"></i></button>
                    </form>
                </div>
                
                <!-- User Menu -->
                <div class="user-menu">
                    <?php if (isLoggedIn()): ?>
                        <div class="cart-icon active">
                            <a href="cart.php">
                                <i class="fas fa-shopping-cart"></i>
                                <span class="cart-count" id="cartCountLoggedIn"><?php echo $cartCount; ?></span>
                            </a>
                        </div>
                        
                        <div class="user-dropdown">
                            <button class="user-btn">
                                <i class="fas fa-user"></i>
                                <?php echo htmlspecialchars($_SESSION['full_name']); ?>
                                <i class="fas fa-chevron-down"></i>
                            </button>
                            <div class="dropdown-content">
                                <?php if (isAdmin()): ?>
                                    <a href="../admin/"><i class="fas fa-tachometer-alt"></i> Admin Dashboard</a>
                                <?php elseif (isTrader()): ?>
                                    <a href="../trader/"><i class="fas fa-store"></i> Trader Dashboard</a>
                                <?php else: ?>
                                    <a href="../customer/profile.php"><i class="fas fa-user"></i> My Profile</a>
                                    <a href="../customer/orders.php"><i class="fas fa-box"></i> My Orders</a>
                                    <a href="../customer/points.php"><i class="fas fa-star"></i> Loyalty Points</a>
                                <?php endif; ?>
                                <a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="cart-icon active">
                            <a href="cart.php">
                                <i class="fas fa-shopping-cart"></i>
                                <span class="cart-count" id="cartCountGuest"><?php echo $cartCount; ?></span>
                            </a>
                        </div>
                        <a href="../auth/login.php" class="login-btn">Login</a>
                        <a href="../auth/register.php" class="register-btn">Register</a>
                    <?php endif; ?>
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
                <span class="current">Shopping Cart</span>
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
            <div class="cart-header">
                <h1><i class="fas fa-shopping-cart"></i> Shopping Cart</h1>
                <p><?php echo $cartCount; ?> item<?php echo $cartCount != 1 ? 's' : ''; ?> in your cart</p>
            </div>

            <?php if (empty($cartItems)): ?>
                <!-- Empty Cart -->
                <div class="empty-cart">
                    <div class="empty-cart-content">
                        <i class="fas fa-shopping-cart"></i>
                        <h2>Your cart is empty</h2>
                        <p>Looks like you haven't added any items to your cart yet.</p>
                        <a href="../index.php" class="btn-primary">
                            <i class="fas fa-arrow-left"></i> Continue Shopping
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <!-- Cart Content -->
                <div class="cart-content">
                    <div class="cart-items-section">
                        <!-- Cart Items -->
                        <div class="cart-items">
                            <?php foreach ($cartItems as $item): ?>
                                <div class="cart-item" data-product-id="<?php echo $item['product_id']; ?>">
                                    <div class="item-image">
                                        <img src="<?php echo htmlspecialchars(getImagePath($item['image_path'], '../')); ?>" 
                                             alt="<?php echo htmlspecialchars($item['product_name']); ?>">
                                    </div>
                                    
                                    <div class="item-details">
                                        <h3 class="item-name">
                                            <a href="product.php?id=<?php echo $item['product_id']; ?>">
                                                <?php echo htmlspecialchars($item['product_name']); ?>
                                            </a>
                                        </h3>
                                        <p class="item-shop">
                                            <i class="fas fa-store"></i>
                                            <?php echo htmlspecialchars($item['shop_name']); ?>
                                        </p>
                                        <p class="item-price"><?php echo formatPrice($item['price']); ?> each</p>
                                        
                                        <?php if ($item['stock_quantity'] < 5): ?>
                                            <div class="stock-warning">
                                                <i class="fas fa-exclamation-triangle"></i>
                                                Only <?php echo $item['stock_quantity']; ?> left in stock
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="item-quantity">
                                        <label>Quantity:</label>
                                        <div class="quantity-controls">
                                            <button class="qty-btn decrease" data-product-id="<?php echo $item['product_id']; ?>">
                                                <i class="fas fa-minus"></i>
                                            </button>
                                            <input type="number" class="quantity-input" 
                                                   value="<?php echo $item['quantity']; ?>" 
                                                   min="1" 
                                                   max="<?php echo $item['stock_quantity']; ?>"
                                                   data-product-id="<?php echo $item['product_id']; ?>">
                                            <button class="qty-btn increase" data-product-id="<?php echo $item['product_id']; ?>">
                                                <i class="fas fa-plus"></i>
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <div class="item-total">
                                        <span class="subtotal" data-product-id="<?php echo $item['product_id']; ?>">
                                            <?php echo formatPrice($item['subtotal']); ?>
                                        </span>
                                    </div>
                                    
                                    <div class="item-actions">
                                        <button class="remove-item" data-product-id="<?php echo $item['product_id']; ?>" title="Remove item">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Cart Actions -->
                        <div class="cart-actions">
                            <button class="btn-secondary clear-cart">
                                <i class="fas fa-trash-alt"></i> Clear Cart
                            </button>
                            <a href="../index.php" class="btn-outline">
                                <i class="fas fa-arrow-left"></i> Continue Shopping
                            </a>
                        </div>
                    </div>
                    
                    <!-- Order Summary -->
                    <div class="order-summary">
                        <div class="summary-card">
                            <h2><i class="fas fa-receipt"></i> Order Summary</h2>
                            
                            <div class="summary-row">
                                <span>Subtotal (<?php echo $cartCount; ?> items):</span>
                                <span id="subtotal"><?php echo formatPrice($subtotal); ?></span>
                            </div>
                            
                            <?php if ($discountAmount > 0): ?>
                                <div class="summary-row discount">
                                    <span>Discount (<?php echo htmlspecialchars($promoCode); ?>):</span>
                                    <span>-<?php echo formatPrice($discountAmount); ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <div class="summary-row">
                                <span>Tax:</span>
                                <span id="tax"><?php echo formatPrice($taxAmount); ?></span>
                            </div>
                            
                            <div class="summary-row shipping">
                                <span>Shipping:</span>
                                <span class="free">FREE</span>
                            </div>
                            
                            <hr>
                            
                            <div class="summary-row total">
                                <strong>
                                    <span>Total:</span>
                                    <span id="finalTotal"><?php echo formatPrice($finalTotal); ?></span>
                                </strong>
                            </div>
                            
                            <!-- Promo Code Section -->
                            <div class="promo-section">
                                <?php if (!$promoCode): ?>
                                    <div class="promo-input">
                                        <input type="text" id="promoCode" placeholder="Enter promo code">
                                        <button id="applyPromo" class="btn-promo">Apply</button>
                                    </div>
                                <?php else: ?>
                                    <div class="promo-applied">
                                        <span class="promo-code">
                                            <i class="fas fa-tag"></i>
                                            <?php echo htmlspecialchars($promoCode); ?>
                                        </span>
                                        <button id="removePromo" class="btn-remove-promo">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Checkout Button -->
                            <?php if (isLoggedIn()): ?>
                                <a href="checkout.php" class="btn-checkout">
                                    <i class="fas fa-credit-card"></i>
                                    Proceed to Checkout
                                </a>
                            <?php else: ?>
                                <div class="guest-checkout">
                                    <p class="checkout-notice">
                                        <i class="fas fa-info-circle"></i>
                                        Sign in or create an account to checkout
                                    </p>
                                    <a href="../auth/login.php?redirect=<?php echo urlencode('shop/checkout.php'); ?>" class="btn-checkout">
                                        <i class="fas fa-sign-in-alt"></i>
                                        Sign In to Checkout
                                    </a>
                                    <a href="../auth/register.php?redirect=<?php echo urlencode('shop/checkout.php'); ?>" class="btn-outline">
                                        <i class="fas fa-user-plus"></i>
                                        Create Account
                                    </a>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Trust Badges -->
                            <div class="trust-badges">
                                <div class="trust-item">
                                    <i class="fas fa-shield-alt"></i>
                                    <span>Secure Checkout</span>
                                </div>
                                <div class="trust-item">
                                    <i class="fas fa-truck"></i>
                                    <span>Free Shipping</span>
                                </div>
                                <div class="trust-item">
                                    <i class="fas fa-undo"></i>
                                    <span>Easy Returns</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Available Promos -->
                <?php if (!empty($availablePromos) && !$promoCode): ?>
                    <div class="available-promos">
                        <h3><i class="fas fa-tags"></i> Available Discounts</h3>
                        <div class="promo-list">
                            <?php foreach ($availablePromos as $promo): ?>
                                <div class="promo-item" onclick="applyPromoCode('<?php echo htmlspecialchars($promo['code']); ?>')">
                                    <div class="promo-code"><?php echo htmlspecialchars($promo['code']); ?></div>
                                    <div class="promo-details">
                                        <span class="discount">
                                            <?php if ($promo['discount_type'] === 'percentage'): ?>
                                                <?php echo $promo['discount_value']; ?>% OFF
                                            <?php else: ?>
                                                <?php echo formatPrice($promo['discount_value']); ?> OFF
                                            <?php endif; ?>
                                        </span>
                                        <?php if ($promo['min_order_amount'] > 0): ?>
                                            <small>Min order: <?php echo formatPrice($promo['min_order_amount']); ?></small>
                                        <?php endif; ?>
                                    </div>
                                    <div class="promo-action">
                                        <i class="fas fa-plus"></i>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
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
                    <h4>Quick Links</h4>
                    <ul>
                        <li><a href="../index.php">Home</a></li>
                        <li><a href="search.php">Search</a></li>
                        <li><a href="../auth/register.php">Become a Customer</a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h4>For Sellers</h4>
                    <ul>
                        <li><a href="../auth/register.php?type=trader">Become a Trader</a></li>
                        <li><a href="../trader/">Trader Dashboard</a></li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2024 Shopfusion. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script src="../js/main.js"></script>
    <script src="../js/cart.js"></script>
    
    <script>
        // Cart page specific functionality
        document.addEventListener('DOMContentLoaded', function() {
            initializeCartPage();
        });
        
        function initializeCartPage() {
            // Quantity controls
            document.querySelectorAll('.qty-btn.increase').forEach(btn => {
                btn.addEventListener('click', function() {
                    const productId = this.dataset.productId;
                    const input = document.querySelector(`input[data-product-id="${productId}"]`);
                    const maxQuantity = parseInt(input.max);
                    const currentQuantity = parseInt(input.value);
                    
                    if (currentQuantity < maxQuantity) {
                        input.value = currentQuantity + 1;
                        updateCartQuantity(productId, currentQuantity + 1);
                    }
                });
            });
            
            document.querySelectorAll('.qty-btn.decrease').forEach(btn => {
                btn.addEventListener('click', function() {
                    const productId = this.dataset.productId;
                    const input = document.querySelector(`input[data-product-id="${productId}"]`);
                    const currentQuantity = parseInt(input.value);
                    
                    if (currentQuantity > 1) {
                        input.value = currentQuantity - 1;
                        updateCartQuantity(productId, currentQuantity - 1);
                    }
                });
            });
            
            // Quantity input changes
            document.querySelectorAll('.quantity-input').forEach(input => {
                let timeoutId;
                
                input.addEventListener('input', function() {
                    clearTimeout(timeoutId);
                    const productId = this.dataset.productId;
                    const quantity = parseInt(this.value);
                    const maxQuantity = parseInt(this.max);
                    
                    if (quantity > maxQuantity) {
                        this.value = maxQuantity;
                        showNotification(`Only ${maxQuantity} items available in stock`, 'warning');
                        return;
                    }
                    
                    if (quantity >= 1) {
                        timeoutId = setTimeout(() => {
                            updateCartQuantity(productId, quantity);
                        }, 500);
                    }
                });
            });
            
            // Remove item buttons
            document.querySelectorAll('.remove-item').forEach(btn => {
                btn.addEventListener('click', function() {
                    const productId = this.dataset.productId;
                    removeFromCart(productId);
                });
            });
            
            // Clear cart button
            document.querySelector('.clear-cart')?.addEventListener('click', function() {
                if (confirm('Are you sure you want to clear your entire cart?')) {
                    clearCart();
                }
            });
            
            // Promo code functionality
            document.getElementById('applyPromo')?.addEventListener('click', applyPromoCode);
            document.getElementById('promoCode')?.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    applyPromoCode();
                }
            });
            
            document.getElementById('removePromo')?.addEventListener('click', removePromoCode);
        }
        
        // Update cart quantity
        function updateCartQuantity(productId, quantity) {
            const formData = new FormData();
            formData.append('action', 'update');
            formData.append('product_id', productId);
            formData.append('quantity', quantity);
            
            fetch('../api/cart_actions.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateCartDisplay(data);
                    showNotification('Cart updated', 'success');
                } else {
                    showNotification(data.message || 'Failed to update cart', 'error');
                    location.reload(); // Refresh to show correct quantities
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Something went wrong', 'error');
            });
        }
        
        // Remove from cart
        function removeFromCart(productId) {
            const formData = new FormData();
            formData.append('action', 'remove');
            formData.append('product_id', productId);
            
            fetch('../api/cart_actions.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Remove the cart item element with animation
                    const cartItem = document.querySelector(`[data-product-id="${productId}"]`);
                    if (cartItem) {
                        cartItem.style.transition = 'opacity 0.3s, transform 0.3s';
                        cartItem.style.opacity = '0';
                        cartItem.style.transform = 'translateX(-100%)';
                        setTimeout(() => {
                            cartItem.remove();
                            
                            // Check if cart is empty
                            const remainingItems = document.querySelectorAll('.cart-item');
                            if (remainingItems.length === 0) {
                                location.reload(); // Refresh to show empty cart
                            }
                        }, 300);
                    }
                    
                    updateCartDisplay(data);
                    showNotification('Item removed from cart', 'success');
                } else {
                    showNotification(data.message || 'Failed to remove item', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Something went wrong', 'error');
            });
        }
        
        // Clear cart
        function clearCart() {
            const formData = new FormData();
            formData.append('action', 'clear');
            
            fetch('../api/cart_actions.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload(); // Refresh to show empty cart
                } else {
                    showNotification(data.message || 'Failed to clear cart', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Something went wrong', 'error');
            });
        }
        
        // Apply promo code
        function applyPromoCode(code = null) {
            const promoInput = document.getElementById('promoCode');
            const promoCodeValue = code || (promoInput ? promoInput.value.trim() : '');
            
            if (!promoCodeValue) {
                showNotification('Please enter a promo code', 'warning');
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'apply_promo');
            formData.append('promo_code', promoCodeValue);
            
            fetch('../api/cart_actions.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Promo code applied successfully!', 'success');
                    setTimeout(() => location.reload(), 1000); // Refresh to show discount
                } else {
                    showNotification(data.message || 'Invalid promo code', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Something went wrong', 'error');
            });
        }
        
        // Remove promo code
        function removePromoCode() {
            const formData = new FormData();
            formData.append('action', 'remove_promo');
            
            fetch('../api/cart_actions.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Promo code removed', 'success');
                    setTimeout(() => location.reload(), 1000); // Refresh to remove discount
                } else {
                    showNotification(data.message || 'Failed to remove promo code', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Something went wrong', 'error');
            });
        }
        
        // Update cart display
        function updateCartDisplay(data) {
            // Update cart count
            document.getElementById('cartCount').textContent = data.cart_count || 0;
            
            // Update totals if provided
            if (data.cart_total !== undefined) {
                const subtotalElement = document.getElementById('subtotal');
                const taxElement = document.getElementById('tax');
                const finalTotalElement = document.getElementById('finalTotal');
                
                if (subtotalElement) {
                    const subtotal = data.cart_total;
                    const tax = subtotal * 0.08;
                    const total = subtotal + tax;
                    
                    subtotalElement.textContent = formatPrice(subtotal);
                    taxElement.textContent = formatPrice(tax);
                    finalTotalElement.textContent = formatPrice(total);
                }
            }
        }
        
        // Format price for display
        function formatPrice(price) {
            return new Intl.NumberFormat('en-US', {
                style: 'currency',
                currency: 'USD'
            }).format(price);
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
        /* Cart Page Alert Styles - Make them appear on top */
        .alert {
            position: fixed;
            top: 80px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 999999;
            min-width: 300px;
            max-width: 600px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            animation: slideDown 0.3s ease-out;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateX(-50%) translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(-50%) translateY(0);
            }
        }
        
        /* Cart Page Styles */
        .cart-header {
            text-align: center;
            margin: 2rem 0;
        }
        
        .cart-header h1 {
            color: #333;
            margin-bottom: 0.5rem;
        }
        
        .cart-header p {
            color: #666;
        }
        
        .empty-cart {
            text-align: center;
            padding: 4rem 2rem;
        }
        
        .empty-cart-content {
            max-width: 400px;
            margin: 0 auto;
        }
        
        .empty-cart i {
            font-size: 4rem;
            color: #ddd;
            margin-bottom: 1rem;
        }
        
        .empty-cart h2 {
            color: #333;
            margin-bottom: 1rem;
        }
        
        .empty-cart p {
            color: #666;
            margin-bottom: 2rem;
        }
        
        .cart-content {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
            margin: 2rem 0;
        }
        
        .cart-items {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .cart-item {
            display: grid;
            grid-template-columns: 100px 1fr auto auto auto;
            gap: 1rem;
            padding: 1.5rem;
            border-bottom: 1px solid #eee;
            align-items: center;
        }
        
        .cart-item:last-child {
            border-bottom: none;
        }
        
        .item-image img {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 8px;
        }
        
        .item-details h3 {
            margin-bottom: 0.5rem;
        }
        
        .item-details a {
            color: #333;
            text-decoration: none;
        }
        
        .item-details a:hover {
            color: #007bff;
        }
        
        .item-shop {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }
        
        .item-price {
            color: #28a745;
            font-weight: 600;
        }
        
        .stock-warning {
            color: #856404;
            background: #fff3cd;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.8rem;
            margin-top: 0.5rem;
        }
        
        .quantity-controls {
            display: flex;
            align-items: center;
            border: 1px solid #ddd;
            border-radius: 5px;
            overflow: hidden;
        }
        
        .qty-btn {
            background: #f8f9fa;
            border: none;
            padding: 0.5rem;
            cursor: pointer;
            transition: background 0.2s;
            width: 35px;
            height: 35px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .qty-btn:hover {
            background: #e9ecef;
        }
        
        .quantity-input {
            border: none;
            padding: 0.5rem;
            width: 60px;
            text-align: center;
            outline: none;
        }
        
        .item-total {
            font-weight: 600;
            font-size: 1.1rem;
            color: #28a745;
        }
        
        .remove-item {
            background: none;
            border: none;
            color: #dc3545;
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 4px;
            transition: all 0.2s;
        }
        
        .remove-item:hover {
            background: #f8d7da;
        }
        
        .cart-actions {
            padding: 1.5rem;
            background: #f8f9fa;
            display: flex;
            gap: 1rem;
            justify-content: space-between;
        }
        
        .order-summary {
            position: sticky;
            top: 2rem;
            height: fit-content;
        }
        
        .summary-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 2rem;
        }
        
        .summary-card h2 {
            color: #333;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1rem;
            padding: 0.5rem 0;
        }
        
        .summary-row.discount {
            color: #28a745;
        }
        
        .summary-row.shipping .free {
            color: #28a745;
            font-weight: 600;
        }
        
        .summary-row.total {
            border-top: 2px solid #eee;
            padding-top: 1rem;
            font-size: 1.2rem;
        }
        
        .promo-section {
            margin: 1.5rem 0;
            padding: 1rem 0;
            border-top: 1px solid #eee;
            border-bottom: 1px solid #eee;
        }
        
        .promo-input {
            display: flex;
            gap: 0.5rem;
        }
        
        .promo-input input {
            flex: 1;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 5px;
            outline: none;
        }
        
        .btn-promo {
            background: #28a745;
            color: white;
            border: none;
            padding: 0.75rem 1rem;
            border-radius: 5px;
            cursor: pointer;
            transition: background 0.2s;
        }
        
        .btn-promo:hover {
            background: #218838;
        }
        
        .promo-applied {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #d4edda;
            padding: 0.75rem;
            border-radius: 5px;
        }
        
        .promo-code {
            color: #155724;
            font-weight: 600;
        }
        
        .btn-remove-promo {
            background: none;
            border: none;
            color: #155724;
            cursor: pointer;
            padding: 0.25rem;
            border-radius: 3px;
        }
        
        .btn-remove-promo:hover {
            background: rgba(21, 87, 36, 0.1);
        }
        
        .btn-checkout {
            width: 100%;
            background: #007bff;
            color: white;
            border: none;
            padding: 1rem;
            border-radius: 8px;
            font-weight: 600;
            font-size: 1.1rem;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 1rem;
        }
        
        .btn-checkout:hover {
            background: #0056b3;
            transform: translateY(-2px);
        }
        
        .guest-checkout {
            margin-top: 1rem;
        }
        
        .checkout-notice {
            background: #e7f3ff;
            color: #0c5460;
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
            font-size: 0.9rem;
        }
        
        .btn-outline {
            width: 100%;
            background: white;
            color: #007bff;
            border: 2px solid #007bff;
            padding: 1rem;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 0.5rem;
        }
        
        .btn-outline:hover {
            background: #007bff;
            color: white;
        }
        
        .trust-badges {
            display: flex;
            justify-content: space-around;
            margin-top: 2rem;
            padding-top: 1rem;
            border-top: 1px solid #eee;
        }
        
        .trust-item {
            text-align: center;
            color: #28a745;
        }
        
        .trust-item i {
            display: block;
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }
        
        .trust-item span {
            font-size: 0.8rem;
        }
        
        .available-promos {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 2rem;
            margin-top: 2rem;
        }
        
        .available-promos h3 {
            color: #333;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .promo-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
        }
        
        .promo-item {
            background: #f8f9fa;
            border: 2px solid transparent;
            border-radius: 8px;
            padding: 1rem;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .promo-item:hover {
            border-color: #007bff;
            transform: translateY(-2px);
        }
        
        .promo-item .promo-code {
            font-family: 'Courier New', monospace;
            font-weight: 600;
            color: #007bff;
        }
        
        .promo-item .discount {
            color: #28a745;
            font-weight: 600;
        }
        
        .promo-action {
            color: #007bff;
        }
        
        .cart-icon.active a {
            color: #007bff;
        }
        
        @media (max-width: 768px) {
            .cart-content {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .cart-item {
                grid-template-columns: 1fr;
                gap: 1rem;
                text-align: center;
            }
            
            .item-image {
                justify-self: center;
            }
            
            .cart-actions {
                flex-direction: column;
            }
            
            .promo-list {
                grid-template-columns: 1fr;
            }
            
            .trust-badges {
                flex-direction: column;
                gap: 1rem;
            }
        }
    </style>
</body>
</html>