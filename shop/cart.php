<?php
// shop/cart.php - Shopping cart page
require_once '../config/database.php';
require_once '../includes/session.php';
require_once '../includes/functions.php';

$page_title = 'Shopping Cart';

// Get cart items
$cart_items = getCartItems();
$cart_total = getCartTotal();
$cart_count = getCartItemCount();

// Calculate savings if any promo codes are available
$available_promos = [];
$stmt = $pdo->prepare("SELECT * FROM promo_codes WHERE status = 'active' AND 
                       (max_uses IS NULL OR current_uses < max_uses) AND
                       (end_date IS NULL OR end_date >= CURDATE())");
$stmt->execute();
$available_promos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> - EcommerceDemo</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <div class="navbar-brand">
                <a href="../index.php">EcommerceDemo</a>
            </div>
            <div class="navbar-nav">
                <a href="../index.php">Home</a>
                
                <?php if (isLoggedIn()): ?>
                    <?php if (getCurrentUserRole() === 'admin'): ?>
                        <a href="../admin/index.php">Admin Dashboard</a>
                    <?php elseif (getCurrentUserRole() === 'trader'): ?>
                        <a href="../trader/index.php">Trader Dashboard</a>
                    <?php else: ?>
                        <a href="../customer/profile.php">My Profile</a>
                        <a href="../customer/orders.php">My Orders</a>
                    <?php endif; ?>
                    <a href="../auth/logout.php">Logout</a>
                <?php else: ?>
                    <a href="../auth/login.php">Login</a>
                    <a href="../auth/register.php">Register</a>
                <?php endif; ?>
                
                <a href="cart.php" class="cart-icon">
                    <i class="fas fa-shopping-cart"></i>
                    <span class="cart-count"><?= $cart_count ?></span>
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h2><i class="fas fa-shopping-cart"></i> Shopping Cart (<?= $cart_count ?> items)</h2>
                    </div>
                    <div class="card-body">
                        <?php if (empty($cart_items)): ?>
                            <div class="empty-cart text-center">
                                <i class="fas fa-shopping-cart fa-3x text-muted mb-3"></i>
                                <h3>Your cart is empty</h3>
                                <p class="text-muted">Looks like you haven't added any items to your cart yet.</p>
                                <a href="../index.php" class="btn btn-primary">
                                    <i class="fas fa-arrow-left"></i> Continue Shopping
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="cart-items">
                                <?php foreach ($cart_items as $item): ?>
                                    <div class="cart-item" data-product-id="<?= $item['product_id'] ?>">
                                        <div class="row align-items-center">
                                            <div class="col-md-2">
                                                <div class="item-image">
                                                    <img src="../<?= htmlspecialchars($item['image_path'] ?: 'images/placeholder.jpg') ?>" 
                                                         alt="<?= htmlspecialchars($item['product_name']) ?>" 
                                                         class="img-fluid">
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="item-details">
                                                    <h5><?= htmlspecialchars($item['product_name']) ?></h5>
                                                    <p class="text-muted"><?= htmlspecialchars($item['shop_name']) ?></p>
                                                    <p class="item-price">$<?= number_format($item['price'], 2) ?> each</p>
                                                    <?php if ($item['stock_quantity'] < 5): ?>
                                                        <small class="text-warning">
                                                            <i class="fas fa-exclamation-triangle"></i> Only <?= $item['stock_quantity'] ?> left in stock
                                                        </small>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="item-quantity">
                                                    <label>Quantity:</label>
                                                    <div class="quantity-controls">
                                                        <button class="quantity-btn" data-action="decrease" data-product-id="<?= $item['product_id'] ?>">
                                                            <i class="fas fa-minus"></i>
                                                        </button>
                                                        <input type="number" class="quantity-input" 
                                                               value="<?= $item['quantity'] ?>" 
                                                               min="1" max="<?= $item['stock_quantity'] ?>"
                                                               data-product-id="<?= $item['product_id'] ?>">
                                                        <button class="quantity-btn" data-action="increase" data-product-id="<?= $item['product_id'] ?>">
                                                            <i class="fas fa-plus"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-2">
                                                <div class="item-total">
                                                    <strong>$<?= number_format($item['quantity'] * $item['price'], 2) ?></strong>
                                                </div>
                                            </div>
                                            <div class="col-md-1">
                                                <button class="remove-item btn btn-sm btn-outline-danger" 
                                                        data-product-id="<?= $item['product_id'] ?>"
                                                        title="Remove item">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <hr>
                                <?php endforeach; ?>
                            </div>
                            
                            <div class="cart-actions mt-3">
                                <div class="row">
                                    <div class="col-md-6">
                                        <button class="btn btn-outline-secondary clear-cart">
                                            <i class="fas fa-trash-alt"></i> Clear Cart
                                        </button>
                                        <a href="../index.php" class="btn btn-outline-primary">
                                            <i class="fas fa-arrow-left"></i> Continue Shopping
                                        </a>
                                    </div>
                                    <div class="col-md-6 text-right">
                                        <button class="btn btn-secondary" onclick="location.reload()">
                                            <i class="fas fa-sync-alt"></i> Update Cart
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <!-- Order Summary -->
                <div class="card">
                    <div class="card-header">
                        <h4>Order Summary</h4>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($cart_items)): ?>
                            <div class="order-summary">
                                <div class="summary-row">
                                    <span>Subtotal (<?= $cart_count ?> items):</span>
                                    <span class="cart-total">$<?= number_format($cart_total, 2) ?></span>
                                </div>
                                <div class="summary-row">
                                    <span>Shipping:</span>
                                    <span class="text-success">Free</span>
                                </div>
                                <div class="summary-row">
                                    <span>Tax:</span>
                                    <span>$<?= number_format($cart_total * 0.08, 2) ?></span>
                                </div>
                                <hr>
                                <div class="summary-row total">
                                    <strong>
                                        <span>Total:</span>
                                        <span>$<?= number_format($cart_total * 1.08, 2) ?></span>
                                    </strong>
                                </div>
                                
                                <?php if (isLoggedIn()): ?>
                                    <a href="checkout.php" class="btn btn-primary btn-lg btn-block mt-3">
                                        <i class="fas fa-credit-card"></i> Proceed to Checkout
                                    </a>
                                <?php else: ?>
                                    <div class="guest-checkout mt-3">
                                        <p class="text-muted text-center">Sign in to checkout</p>
                                        <a href="../auth/login.php?redirect=<?= urlencode($_SERVER['REQUEST_URI']) ?>" 
                                           class="btn btn-primary btn-block">
                                            <i class="fas fa-sign-in-alt"></i> Sign In
                                        </a>
                                        <a href="../auth/register.php?redirect=<?= urlencode('shop/checkout.php') ?>" 
                                           class="btn btn-outline-primary btn-block mt-2">
                                            <i class="fas fa-user-plus"></i> Create Account
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-muted text-center">Your order summary will appear here</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Promo Codes -->
                <?php if (!empty($available_promos) && !empty($cart_items)): ?>
                <div class="card mt-3">
                    <div class="card-header">
                        <h5>Available Discounts</h5>
                    </div>
                    <div class="card-body">
                        <div class="promo-codes">
                            <?php foreach ($available_promos as $promo): ?>
                                <div class="promo-item">
                                    <div class="promo-code">
                                        <strong><?= htmlspecialchars($promo['code']) ?></strong>
                                    </div>
                                    <div class="promo-details">
                                        <?php if ($promo['discount_type'] === 'percentage'): ?>
                                            <span class="discount"><?= $promo['discount_value'] ?>% OFF</span>
                                        <?php else: ?>
                                            <span class="discount">$<?= number_format($promo['discount_value'], 2) ?> OFF</span>
                                        <?php endif; ?>
                                        <?php if ($promo['min_order_amount'] > 0): ?>
                                            <small class="text-muted">
                                                (Min order: $<?= number_format($promo['min_order_amount'], 2) ?>)
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <small class="text-muted">Promo codes can be applied during checkout.</small>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Security & Trust -->
                <div class="card mt-3">
                    <div class="card-body text-center">
                        <h6>Shop with Confidence</h6>
                        <div class="trust-badges">
                            <div class="trust-item">
                                <i class="fas fa-shield-alt text-success"></i>
                                <small>Secure Checkout</small>
                            </div>
                            <div class="trust-item">
                                <i class="fas fa-truck text-primary"></i>
                                <small>Free Shipping</small>
                            </div>
                            <div class="trust-item">
                                <i class="fas fa-undo text-info"></i>
                                <small>Easy Returns</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recently Viewed (if any) -->
                <?php if (isset($_SESSION['recently_viewed']) && !empty($_SESSION['recently_viewed'])): ?>
                <div class="card mt-3">
                    <div class="card-header">
                        <h6>Recently Viewed</h6>
                    </div>
                    <div class="card-body">
                        <?php 
                        $recent_ids = array_slice($_SESSION['recently_viewed'], -3);
                        $placeholders = str_repeat('?,', count($recent_ids) - 1) . '?';
                        $stmt = $pdo->prepare("SELECT product_id, product_name, price, image_path 
                                               FROM products WHERE product_id IN ($placeholders) 
                                               AND status = 'active' LIMIT 3");
                        $stmt->execute($recent_ids);
                        $recent_products = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        ?>
                        
                        <?php foreach ($recent_products as $product): ?>
                            <div class="recent-item">
                                <div class="row align-items-center">
                                    <div class="col-4">
                                        <img src="../<?= htmlspecialchars($product['image_path'] ?: 'images/placeholder.jpg') ?>" 
                                             alt="<?= htmlspecialchars($product['product_name']) ?>" 
                                             class="img-fluid">
                                    </div>
                                    <div class="col-8">
                                        <small>
                                            <a href="product.php?id=<?= $product['product_id'] ?>" class="text-decoration-none">
                                                <?= htmlspecialchars($product['product_name']) ?>
                                            </a>
                                        </small>
                                        <div class="text-success">
                                            <strong>$<?= number_format($product['price'], 2) ?></strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <hr>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <footer class="footer mt-5">
        <div class="container text-center">
            <p>&copy; 2024 EcommerceDemo. Built for educational purposes.</p>
        </div>
    </footer>

    <script src="../js/main.js"></script>
    <script src="../js/cart.js"></script>
    
    <style>
        .cart-item {
            padding: 1rem 0;
        }
        
        .item-image img {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 8px;
        }
        
        .quantity-controls {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .quantity-btn {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            color: #495057;
            width: 32px;
            height: 32px;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .quantity-btn:hover {
            background: #e9ecef;
            border-color: #adb5bd;
        }
        
        .quantity-input {
            width: 60px;
            text-align: center;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 0.375rem;
            font-size: 0.9rem;
        }
        
        .order-summary .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }
        
        .order-summary .total {
            font-size: 1.1rem;
            padding-top: 0.5rem;
        }
        
        .promo-item {
            background: #f8f9fa;
            padding: 0.75rem;
            border-radius: 6px;
            margin-bottom: 0.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .promo-code {
            font-family: 'Courier New', monospace;
            background: #fff;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            border: 1px dashed #007bff;
        }
        
        .discount {
            color: #28a745;
            font-weight: bold;
        }
        
        .trust-badges {
            display: flex;
            justify-content: space-around;
            margin-top: 1rem;
        }
        
        .trust-item {
            text-align: center;
        }
        
        .trust-item i {
            display: block;
            font-size: 1.5rem;
            margin-bottom: 0.25rem;
        }
        
        .recent-item {
            margin-bottom: 0.5rem;
        }
        
        .empty-cart {
            padding: 3rem 1rem;
        }
        
        @media (max-width: 768px) {
            .cart-item .row > div {
                margin-bottom: 0.75rem;
            }
            
            .cart-item .col-md-1,
            .cart-item .col-md-2 {
                text-align: center;
            }
            
            .trust-badges {
                flex-direction: column;
                gap: 1rem;
            }
        }
    </style>
</body>
</html>