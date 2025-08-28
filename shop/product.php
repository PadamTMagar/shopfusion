<?php
// shop/product.php - Product Details Page
require_once '../config/database.php';
require_once '../includes/session.php';
require_once '../includes/functions.php';

$error = '';
$success = '';

// Get product ID from URL
$productId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$productId) {
    header('Location: ../index.php');
    exit();
}

// Get product details with shop and trader information
try {
    $stmt = $pdo->prepare("
        SELECT p.*, s.shop_name, s.description as shop_description, s.trader_id, 
               u.full_name as trader_name, u.phone as trader_phone, 
               c.category_name, c.description as category_description
        FROM products p 
        JOIN shops s ON p.shop_id = s.shop_id 
        JOIN users u ON s.trader_id = u.user_id 
        JOIN categories c ON p.category_id = c.category_id 
        WHERE p.product_id = ? AND p.status = 'active' 
        AND s.status = 'active' AND u.status = 'active'
    ");
    $stmt->execute([$productId]);
    $product = $stmt->fetch();
    
    if (!$product) {
        header('Location: ../index.php');
        exit();
    }
    
    // Get product reviews
    $stmt = $pdo->prepare("
        SELECT r.*, u.full_name as customer_name, u.username
        FROM reviews r
        JOIN users u ON r.customer_id = u.user_id
        WHERE r.product_id = ?
        ORDER BY r.created_at DESC
    ");
    $stmt->execute([$productId]);
    $reviews = $stmt->fetchAll();
    
    // Get related products from same category
    $stmt = $pdo->prepare("
        SELECT p.*, s.shop_name, s.trader_id, u.full_name as trader_name, c.category_name 
        FROM products p 
        JOIN shops s ON p.shop_id = s.shop_id 
        JOIN users u ON s.trader_id = u.user_id 
        JOIN categories c ON p.category_id = c.category_id 
        WHERE p.category_id = ? AND p.product_id != ? 
        AND p.status = 'active' AND s.status = 'active' AND u.status = 'active'
        ORDER BY p.rating DESC, p.created_at DESC
        LIMIT 4
    ");
    $stmt->execute([$product['category_id'], $productId]);
    $relatedProducts = $stmt->fetchAll();
    
    // Get more products from same shop
    $stmt = $pdo->prepare("
        SELECT p.*, c.category_name 
        FROM products p 
        JOIN categories c ON p.category_id = c.category_id 
        WHERE p.shop_id = ? AND p.product_id != ? AND p.status = 'active'
        ORDER BY p.rating DESC, p.created_at DESC
        LIMIT 4
    ");
    $stmt->execute([$product['shop_id'], $productId]);
    $shopProducts = $stmt->fetchAll();
    
    // Check if current user has purchased this product (for review eligibility)
    $canReview = false;
    $hasReviewed = false;
    $userReview = null;
    
    if (isLoggedIn() && isCustomer()) {
        // Check if user has purchased this product
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as purchased
            FROM order_items oi
            JOIN orders o ON oi.order_id = o.order_id
            WHERE oi.product_id = ? AND o.customer_id = ? 
            AND o.payment_status = 'completed' AND o.order_status = 'delivered'
        ");
        $stmt->execute([$productId, getUserId()]);
        $canReview = $stmt->fetch()['purchased'] > 0;
        
        // Check if user has already reviewed
        $stmt = $pdo->prepare("
            SELECT r.*, u.full_name as customer_name 
            FROM reviews r
            JOIN users u ON r.customer_id = u.user_id
            WHERE r.product_id = ? AND r.customer_id = ?
        ");
        $stmt->execute([$productId, getUserId()]);
        $userReview = $stmt->fetch();
        $hasReviewed = (bool)$userReview;
    }
    
    // Add to recently viewed (session)
    if (!isset($_SESSION['recently_viewed'])) {
        $_SESSION['recently_viewed'] = [];
    }
    
    // Remove if already exists and add to front
    $recentKey = array_search($productId, $_SESSION['recently_viewed']);
    if ($recentKey !== false) {
        unset($_SESSION['recently_viewed'][$recentKey]);
    }
    array_unshift($_SESSION['recently_viewed'], $productId);
    
    // Keep only last 10 items
    $_SESSION['recently_viewed'] = array_slice($_SESSION['recently_viewed'], 0, 10);
    
} catch (PDOException $e) {
    $error = "Failed to load product details.";
}

// Handle review submission
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'add_review') {
    if (!isLoggedIn() || !isCustomer()) {
        $error = "You must be logged in as a customer to write reviews.";
    } elseif (!$canReview) {
        $error = "You can only review products you have purchased and received.";
    } elseif ($hasReviewed) {
        $error = "You have already reviewed this product.";
    } else {
        $rating = intval($_POST['rating']);
        $comment = sanitize($_POST['comment']);
        
        if ($rating >= 1 && $rating <= 5) {
            try {
                $pdo->beginTransaction();
                
                // Insert review
                $stmt = $pdo->prepare("
                    INSERT INTO reviews (product_id, customer_id, rating, comment) 
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$productId, getUserId(), $rating, $comment]);
                
                // Update product rating
                updateProductRating($productId);
                
                $pdo->commit();
                $success = "Review added successfully!";
                setFlashMessage('success', $success);
                
                // Refresh page to show new review
                header("Location: product.php?id=$productId");
                exit();
                
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error = "Failed to add review. Please try again.";
            }
        } else {
            $error = "Please provide a valid rating (1-5 stars).";
        }
    }
}

// Check for flash messages
if ($flashSuccess = getFlashMessage('success')) {
    $success = $flashSuccess;
}
if ($flashError = getFlashMessage('error')) {
    $error = $flashError;
}

$pageTitle = htmlspecialchars($product['product_name']) . ' - ShopFusion';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/theme.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <meta name="description" content="<?php echo htmlspecialchars(substr($product['description'], 0, 160)); ?>">
    <meta property="og:title" content="<?php echo htmlspecialchars($product['product_name']); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars(substr($product['description'], 0, 160)); ?>">
    <meta property="og:image" content="<?php echo htmlspecialchars($product['image_path'] ?: '../uploads/products/placeholder.jpg'); ?>">
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
                        <input type="text" name="search" placeholder="Search products..." 
                               value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                        <button type="submit"><i class="fas fa-search"></i></button>
                    </form>
                </div>
                
                <!-- User Menu -->
                <div class="user-menu">
                    <?php if (isLoggedIn()): ?>
                        <div class="cart-icon">
                            <a href="cart.php">
                                <i class="fas fa-shopping-cart"></i>
                                <span class="cart-count" id="cartCountLoggedIn">
                                    <?php echo isCustomer() ? getCartCount(getUserId()) : getGuestCartCount(); ?>
                                </span>
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
                        <div class="cart-icon">
                            <a href="cart.php">
                                <i class="fas fa-shopping-cart"></i>
                                <span class="cart-count" id="cartCountGuest"><?php echo getGuestCartCount(); ?></span>
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
                <a href="../index.php?category=<?php echo $product['category_id']; ?>"><?php echo htmlspecialchars($product['category_name']); ?></a>
                <span class="separator">></span>
                <span class="current"><?php echo htmlspecialchars($product['product_name']); ?></span>
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
            <!-- Product Details Section -->
            <div class="product-details">
                <div class="product-gallery">
                    <div class="main-image">
                        <img id="mainProductImage" 
                             src="<?php echo htmlspecialchars(getImagePath($product['image_path'], '../')); ?>" 
                             alt="<?php echo htmlspecialchars($product['product_name']); ?>">
                        
                        <?php if ($product['stock_quantity'] <= 5 && $product['stock_quantity'] > 0): ?>
                            <div class="stock-warning">
                                <i class="fas fa-exclamation-triangle"></i>
                                Only <?php echo $product['stock_quantity']; ?> left in stock!
                            </div>
                        <?php elseif ($product['stock_quantity'] <= 0): ?>
                            <div class="out-of-stock">
                                <i class="fas fa-times-circle"></i>
                                Out of Stock
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Placeholder for multiple images (future enhancement) -->
                    <div class="image-thumbnails">
                        <img class="thumbnail active" 
                             src="<?php echo htmlspecialchars(getImagePath($product['image_path'], '../')); ?>" 
                             alt="<?php echo htmlspecialchars($product['product_name']); ?>"
                             onclick="changeMainImage(this.src)">
                    </div>
                </div>
                
                <div class="product-info">
                    <h1 class="product-title"><?php echo htmlspecialchars($product['product_name']); ?></h1>
                    
                    <div class="product-meta">
                        <div class="product-rating">
                            <div class="stars">
                                <?php
                                $rating = $product['rating'];
                                for ($i = 1; $i <= 5; $i++) {
                                    if ($i <= $rating) {
                                        echo '<i class="fas fa-star"></i>';
                                    } elseif ($i - 0.5 <= $rating) {
                                        echo '<i class="fas fa-star-half-alt"></i>';
                                    } else {
                                        echo '<i class="far fa-star"></i>';
                                    }
                                }
                                ?>
                            </div>
                            <span class="rating-text">
                                <?php echo number_format($product['rating'], 1); ?> 
                                (<?php echo $product['total_reviews']; ?> review<?php echo $product['total_reviews'] != 1 ? 's' : ''; ?>)
                            </span>
                        </div>
                        
                        <div class="product-category">
                            <i class="fas fa-tag"></i>
                            <a href="../index.php?category=<?php echo $product['category_id']; ?>">
                                <?php echo htmlspecialchars($product['category_name']); ?>
                            </a>
                        </div>
                        
                        <div class="product-sku">
                            <i class="fas fa-barcode"></i>
                            SKU: <?php echo str_pad($product['product_id'], 6, '0', STR_PAD_LEFT); ?>
                        </div>
                    </div>
                    
                    <div class="product-price">
                        <span class="current-price"><?php echo formatPrice($product['price']); ?></span>
                        <!-- Placeholder for discount price -->
                    </div>
                    
                    <div class="product-description">
                        <h3>Product Description</h3>
                        <p><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>
                    </div>
                    
                    <div class="product-stock">
                        <?php if ($product['stock_quantity'] > 0): ?>
                            <div class="stock-status in-stock">
                                <i class="fas fa-check-circle"></i>
                                <span><?php echo $product['stock_quantity']; ?> in stock</span>
                            </div>
                        <?php else: ?>
                            <div class="stock-status out-of-stock">
                                <i class="fas fa-times-circle"></i>
                                <span>Out of stock</span>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Add to Cart Section -->
                    <div class="add-to-cart-section">
                        <?php if ($product['stock_quantity'] > 0): ?>
                            <div class="quantity-selector">
                                <label for="quantity">Quantity:</label>
                                <div class="quantity-controls">
                                    <button type="button" class="qty-btn" onclick="decreaseQuantity()">
                                        <i class="fas fa-minus"></i>
                                    </button>
                                    <input type="number" id="quantity" value="1" min="1" max="<?php echo $product['stock_quantity']; ?>">
                                    <button type="button" class="qty-btn" onclick="increaseQuantity()">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <button class="btn-add-to-cart" onclick="addToCart(<?php echo $product['product_id']; ?>)">
                                <i class="fas fa-cart-plus"></i>
                                Add to Cart
                            </button>
                        <?php else: ?>
                            <button class="btn-out-of-stock" disabled>
                                <i class="fas fa-times-circle"></i>
                                Out of Stock
                            </button>
                        <?php endif; ?>
                        
                        <button class="btn-wishlist" onclick="addToWishlist(<?php echo $product['product_id']; ?>)">
                            <i class="far fa-heart"></i>
                            Add to Wishlist
                        </button>
                    </div>
                    
                    <!-- Shop Information -->
                    <div class="shop-info">
                        <h3><i class="fas fa-store"></i> Sold by</h3>
                        <div class="shop-details">
                            <h4><?php echo htmlspecialchars($product['shop_name']); ?></h4>
                            <p class="shop-owner">by <?php echo htmlspecialchars($product['trader_name']); ?></p>
                            <?php if ($product['shop_description']): ?>
                                <p class="shop-description"><?php echo htmlspecialchars($product['shop_description']); ?></p>
                            <?php endif; ?>
                            <a href="../index.php?shop=<?php echo $product['shop_id']; ?>" class="view-shop-btn">
                                <i class="fas fa-external-link-alt"></i> View Shop
                            </a>
                        </div>
                    </div>
                    
                    <!-- Product Features -->
                    <div class="product-features">
                        <div class="feature">
                            <i class="fas fa-shipping-fast"></i>
                            <span>Free Shipping</span>
                        </div>
                        <div class="feature">
                            <i class="fas fa-undo"></i>
                            <span>30-Day Returns</span>
                        </div>
                        <div class="feature">
                            <i class="fas fa-shield-alt"></i>
                            <span>Secure Payment</span>
                        </div>
                        <div class="feature">
                            <i class="fas fa-headset"></i>
                            <span>24/7 Support</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Product Tabs -->
            <div class="product-tabs">
                <div class="tab-navigation">
                    <button class="tab-btn active" onclick="showTab('description')">
                        <i class="fas fa-info-circle"></i> Description
                    </button>
                    <button class="tab-btn" onclick="showTab('reviews')">
                        <i class="fas fa-star"></i> Reviews (<?php echo count($reviews); ?>)
                    </button>
                    <button class="tab-btn" onclick="showTab('shipping')">
                        <i class="fas fa-truck"></i> Shipping & Returns
                    </button>
                </div>
                
                <div class="tab-content">
                    <!-- Description Tab -->
                    <div id="description" class="tab-pane active">
                        <div class="description-content">
                            <h3>Product Details</h3>
                            <p><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>
                            
                            <div class="product-specs">
                                <h4>Specifications</h4>
                                <table>
                                    <tr>
                                        <td>Product ID</td>
                                        <td><?php echo str_pad($product['product_id'], 6, '0', STR_PAD_LEFT); ?></td>
                                    </tr>
                                    <tr>
                                        <td>Category</td>
                                        <td><?php echo htmlspecialchars($product['category_name']); ?></td>
                                    </tr>
                                    <tr>
                                        <td>Availability</td>
                                        <td><?php echo $product['stock_quantity'] > 0 ? 'In Stock' : 'Out of Stock'; ?></td>
                                    </tr>
                                    <tr>
                                        <td>Added</td>
                                        <td><?php echo formatDate($product['created_at']); ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Reviews Tab -->
                    <div id="reviews" class="tab-pane">
                        <div class="reviews-section">
                            <div class="reviews-summary">
                                <div class="rating-overview">
                                    <div class="average-rating">
                                        <span class="rating-number"><?php echo number_format($product['rating'], 1); ?></span>
                                        <div class="rating-stars">
                                            <?php
                                            for ($i = 1; $i <= 5; $i++) {
                                                if ($i <= $product['rating']) {
                                                    echo '<i class="fas fa-star"></i>';
                                                } elseif ($i - 0.5 <= $product['rating']) {
                                                    echo '<i class="fas fa-star-half-alt"></i>';
                                                } else {
                                                    echo '<i class="far fa-star"></i>';
                                                }
                                            }
                                            ?>
                                        </div>
                                        <p><?php echo $product['total_reviews']; ?> review<?php echo $product['total_reviews'] != 1 ? 's' : ''; ?></p>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Write Review Section -->
                            <?php if (isLoggedIn() && isCustomer()): ?>
                                <?php if ($canReview && !$hasReviewed): ?>
                                    <div class="write-review-section">
                                        <h3><i class="fas fa-edit"></i> Write a Review</h3>
                                        <form method="POST" class="review-form">
                                            <input type="hidden" name="action" value="add_review">
                                            
                                            <div class="form-group">
                                                <label>Your Rating:</label>
                                                <div class="star-rating">
                                                    <input type="hidden" name="rating" id="rating" value="5">
                                                    <i class="fas fa-star" data-rating="1"></i>
                                                    <i class="fas fa-star" data-rating="2"></i>
                                                    <i class="fas fa-star" data-rating="3"></i>
                                                    <i class="fas fa-star" data-rating="4"></i>
                                                    <i class="fas fa-star" data-rating="5"></i>
                                                </div>
                                            </div>
                                            
                                            <div class="form-group">
                                                <label for="comment">Your Review:</label>
                                                <textarea name="comment" id="comment" rows="4" 
                                                          placeholder="Share your experience with this product..."></textarea>
                                            </div>
                                            
                                            <button type="submit" class="btn-primary">
                                                <i class="fas fa-star"></i> Submit Review
                                            </button>
                                        </form>
                                    </div>
                                <?php elseif ($hasReviewed && $userReview): ?>
                                    <div class="user-review-section">
                                        <h3><i class="fas fa-check-circle"></i> Your Review</h3>
                                        <div class="review-item user-review">
                                            <div class="review-header">
                                                <div class="reviewer-info">
                                                    <span class="reviewer-name"><?php echo htmlspecialchars($userReview['customer_name']); ?></span>
                                                    <span class="review-date"><?php echo formatDate($userReview['created_at']); ?></span>
                                                </div>
                                                <div class="review-rating">
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <i class="fas fa-star <?php echo $i <= $userReview['rating'] ? 'rated' : ''; ?>"></i>
                                                    <?php endfor; ?>
                                                </div>
                                            </div>
                                            <div class="review-content">
                                                <p><?php echo nl2br(htmlspecialchars($userReview['comment'])); ?></p>
                                            </div>
                                        </div>
                                    </div>
                                <?php elseif (!$canReview): ?>
                                    <div class="review-notice">
                                        <i class="fas fa-info-circle"></i>
                                        <p>You can write a review after purchasing and receiving this product.</p>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="review-notice">
                                    <i class="fas fa-sign-in-alt"></i>
                                    <p><a href="../auth/login.php">Login</a> to write a review.</p>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Reviews List -->
                            <div class="reviews-list">
                                <?php if (empty($reviews)): ?>
                                    <div class="no-reviews">
                                        <i class="fas fa-comment-slash"></i>
                                        <h3>No reviews yet</h3>
                                        <p>Be the first to review this product!</p>
                                    </div>
                                <?php else: ?>
                                    <h3>Customer Reviews</h3>
                                    <?php foreach ($reviews as $review): ?>
                                        <div class="review-item">
                                            <div class="review-header">
                                                <div class="reviewer-info">
                                                    <span class="reviewer-name"><?php echo htmlspecialchars($review['customer_name']); ?></span>
                                                    <span class="review-date"><?php echo formatDate($review['created_at']); ?></span>
                                                </div>
                                                <div class="review-rating">
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <i class="fas fa-star <?php echo $i <= $review['rating'] ? 'rated' : ''; ?>"></i>
                                                    <?php endfor; ?>
                                                </div>
                                            </div>
                                            <div class="review-content">
                                                <p><?php echo nl2br(htmlspecialchars($review['comment'])); ?></p>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Shipping Tab -->
                    <div id="shipping" class="tab-pane">
                        <div class="shipping-content">
                            <h3><i class="fas fa-truck"></i> Shipping Information</h3>
                            <div class="shipping-options">
                                <div class="shipping-option">
                                    <h4>Standard Shipping</h4>
                                    <p>Free shipping on all orders. Delivery within 5-7 business days.</p>
                                </div>
                                <div class="shipping-option">
                                    <h4>Express Shipping</h4>
                                    <p>Fast delivery within 2-3 business days. Additional charges may apply.</p>
                                </div>
                            </div>
                            
                            <h3><i class="fas fa-undo"></i> Return Policy</h3>
                            <div class="return-policy">
                                <ul>
                                    <li>30-day return policy on all items</li>
                                    <li>Items must be in original condition</li>
                                    <li>Free returns for defective items</li>
                                    <li>Customer pays return shipping for non-defective items</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Related Products -->
            <?php if (!empty($relatedProducts)): ?>
                <section class="related-products">
                    <div class="section-header">
                        <h2><i class="fas fa-heart"></i> You Might Also Like</h2>
                        <p>Similar products in <?php echo htmlspecialchars($product['category_name']); ?></p>
                    </div>
                    
                    <div class="products-grid">
                        <?php foreach ($relatedProducts as $relatedProduct): ?>
                            <div class="product-card">
                                <div class="product-image">
                                    <a href="product.php?id=<?php echo $relatedProduct['product_id']; ?>">
                                        <img src="<?php echo htmlspecialchars(getImagePath($relatedProduct['image_path'], '../')); ?>" 
                                             alt="<?php echo htmlspecialchars($relatedProduct['product_name']); ?>">
                                    </a>
                                    <?php if ($relatedProduct['stock_quantity'] <= 5 && $relatedProduct['stock_quantity'] > 0): ?>
                                        <span class="stock-badge">Only <?php echo $relatedProduct['stock_quantity']; ?> left!</span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="product-info">
                                    <h3 class="product-name">
                                        <a href="product.php?id=<?php echo $relatedProduct['product_id']; ?>">
                                            <?php echo htmlspecialchars($relatedProduct['product_name']); ?>
                                        </a>
                                    </h3>
                                    
                                    <p class="product-shop">by <?php echo htmlspecialchars($relatedProduct['shop_name']); ?></p>
                                    
                                    <div class="product-rating">
                                        <?php
                                        $rating = $relatedProduct['rating'];
                                        for ($i = 1; $i <= 5; $i++) {
                                            if ($i <= $rating) {
                                                echo '<i class="fas fa-star"></i>';
                                            } elseif ($i - 0.5 <= $rating) {
                                                echo '<i class="fas fa-star-half-alt"></i>';
                                            } else {
                                                echo '<i class="far fa-star"></i>';
                                            }
                                        }
                                        ?>
                                        <span class="rating-text">(<?php echo $relatedProduct['total_reviews']; ?>)</span>
                                    </div>
                                    
                                    <div class="product-price">
                                        <?php echo formatPrice($relatedProduct['price']); ?>
                                    </div>
                                    
                                    <div class="product-actions">
                                        <button class="btn-cart" onclick="addToCart(<?php echo $relatedProduct['product_id']; ?>)">
                                            <i class="fas fa-cart-plus"></i> Add to Cart
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
            
            <!-- More from Shop -->
            <?php if (!empty($shopProducts)): ?>
                <section class="shop-products">
                    <div class="section-header">
                        <h2><i class="fas fa-store"></i> More from <?php echo htmlspecialchars($product['shop_name']); ?></h2>
                        <a href="../index.php?shop=<?php echo $product['shop_id']; ?>" class="view-all-link">View All</a>
                    </div>
                    
                    <div class="products-grid">
                        <?php foreach ($shopProducts as $shopProduct): ?>
                            <div class="product-card">
                                <div class="product-image">
                                    <a href="product.php?id=<?php echo $shopProduct['product_id']; ?>">
                                        <img src="<?php echo htmlspecialchars(getImagePath($shopProduct['image_path'], '../')); ?>" 
                                             alt="<?php echo htmlspecialchars($shopProduct['product_name']); ?>">
                                    </a>
                                    <?php if ($shopProduct['stock_quantity'] <= 5 && $shopProduct['stock_quantity'] > 0): ?>
                                        <span class="stock-badge">Only <?php echo $shopProduct['stock_quantity']; ?> left!</span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="product-info">
                                    <h3 class="product-name">
                                        <a href="product.php?id=<?php echo $shopProduct['product_id']; ?>">
                                            <?php echo htmlspecialchars($shopProduct['product_name']); ?>
                                        </a>
                                    </h3>
                                    
                                    <p class="product-category"><?php echo htmlspecialchars($shopProduct['category_name']); ?></p>
                                    
                                    <div class="product-rating">
                                        <?php
                                        $rating = $shopProduct['rating'];
                                        for ($i = 1; $i <= 5; $i++) {
                                            if ($i <= $rating) {
                                                echo '<i class="fas fa-star"></i>';
                                            } elseif ($i - 0.5 <= $rating) {
                                                echo '<i class="fas fa-star-half-alt"></i>';
                                            } else {
                                                echo '<i class="far fa-star"></i>';
                                            }
                                        }
                                        ?>
                                        <span class="rating-text">(<?php echo $shopProduct['total_reviews']; ?>)</span>
                                    </div>
                                    
                                    <div class="product-price">
                                        <?php echo formatPrice($shopProduct['price']); ?>
                                    </div>
                                    
                                    <div class="product-actions">
                                        <button class="btn-cart" onclick="addToCart(<?php echo $shopProduct['product_id']; ?>)">
                                            <i class="fas fa-cart-plus"></i> Add to Cart
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
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
        // Product page specific JavaScript
        
        // Quantity controls
        function increaseQuantity() {
            const quantityInput = document.getElementById('quantity');
            const maxQuantity = parseInt(quantityInput.max);
            const currentQuantity = parseInt(quantityInput.value);
            
            if (currentQuantity < maxQuantity) {
                quantityInput.value = currentQuantity + 1;
            }
        }
        
        function decreaseQuantity() {
            const quantityInput = document.getElementById('quantity');
            const currentQuantity = parseInt(quantityInput.value);
            
            if (currentQuantity > 1) {
                quantityInput.value = currentQuantity - 1;
            }
        }
        
        // Add to cart with quantity
        function addToCart(productId) {
            const quantity = document.getElementById('quantity') ? parseInt(document.getElementById('quantity').value) : 1;
            Cart.addItem(productId, quantity);
        }
        
        // Image gallery
        function changeMainImage(src) {
            document.getElementById('mainProductImage').src = src;
            
            // Update active thumbnail
            document.querySelectorAll('.thumbnail').forEach(thumb => {
                thumb.classList.remove('active');
            });
            event.target.classList.add('active');
        }
        
        // Tab functionality
        function showTab(tabName) {
            // Hide all tab panes
            document.querySelectorAll('.tab-pane').forEach(pane => {
                pane.classList.remove('active');
            });
            
            // Remove active class from all buttons
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Show selected tab
            document.getElementById(tabName).classList.add('active');
            event.target.classList.add('active');
        }
        
        // Star rating for reviews
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
            
            const starRating = document.querySelector('.star-rating');
            if (starRating) {
                starRating.addEventListener('mouseleave', function() {
                    const currentRating = parseInt(document.getElementById('rating').value);
                    highlightStars(currentRating);
                });
            }
            
            // Initialize with 5 stars
            highlightStars(5);
        });
        
        function setRating(rating) {
            document.getElementById('rating').value = rating;
            highlightStars(rating);
        }
        
        function highlightStars(rating) {
            const stars = document.querySelectorAll('.star-rating .fas');
            stars.forEach((star, index) => {
                if (index < rating) {
                    star.style.color = '#ffc107';
                } else {
                    star.style.color = '#ddd';
                }
            });
        }
        
        // Auto-hide alerts
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 300);
            });
        }, 5000);
        
        // Smooth scroll to reviews when clicking review count
        document.addEventListener('DOMContentLoaded', function() {
            const reviewLinks = document.querySelectorAll('[href*="#reviews"]');
            reviewLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    showTab('reviews');
                    document.getElementById('reviews').scrollIntoView({ behavior: 'smooth' });
                });
            });
        });
        
        // Product card animations
        document.addEventListener('DOMContentLoaded', function() {
            const productCards = document.querySelectorAll('.product-card');
            productCards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    card.style.transition = 'opacity 0.5s, transform 0.5s';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });
    </script>

    <style>
        /* Product Details Page Styles */
        .breadcrumb {
            background: #f8f9fa;
            padding: 1rem 0;
            border-bottom: 1px solid #eee;
        }
        
        .breadcrumb-nav {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
        }
        
        .breadcrumb-nav a {
            color: #007bff;
            text-decoration: none;
        }
        
        .breadcrumb-nav a:hover {
            text-decoration: underline;
        }
        
        .separator {
            color: #666;
        }
        
        .current {
            color: #666;
            font-weight: 500;
        }
        
        .product-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 3rem;
            margin: 2rem 0;
        }
        
        .product-gallery {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        .main-image {
            position: relative;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .main-image img {
            width: 100%;
            height: 400px;
            object-fit: cover;
        }
        
        .stock-warning, .out-of-stock {
            position: absolute;
            top: 1rem;
            right: 1rem;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 500;
            font-size: 0.85rem;
        }
        
        .stock-warning {
            background: #fff3cd;
            color: #856404;
        }
        
        .out-of-stock {
            background: #f8d7da;
            color: #721c24;
        }
        
        .image-thumbnails {
            display: flex;
            gap: 0.5rem;
        }
        
        .thumbnail {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 5px;
            cursor: pointer;
            border: 2px solid transparent;
            transition: border-color 0.2s;
        }
        
        .thumbnail.active,
        .thumbnail:hover {
            border-color: #007bff;
        }
        
        .product-info {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }
        
        .product-title {
            font-size: 2rem;
            font-weight: 600;
            color: #333;
            line-height: 1.2;
        }
        
        .product-meta {
            display: flex;
            flex-direction: column;
            gap: 0.8rem;
        }
        
        .product-rating {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .stars {
            color: #ffc107;
            font-size: 1.1rem;
        }
        
        .rating-text {
            color: #666;
            font-size: 0.9rem;
        }
        
        .product-category a,
        .product-sku {
            color: #666;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }
        
        .product-category a {
            text-decoration: none;
        }
        
        .product-category a:hover {
            color: #007bff;
        }
        
        .product-price {
            margin: 1rem 0;
        }
        
        .current-price {
            font-size: 2.5rem;
            font-weight: 700;
            color: #28a745;
        }
        
        .product-description {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 10px;
        }
        
        .product-description h3 {
            margin-bottom: 1rem;
            color: #333;
        }
        
        .stock-status {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 500;
            padding: 0.75rem 1rem;
            border-radius: 8px;
        }
        
        .stock-status.in-stock {
            background: #d4edda;
            color: #155724;
        }
        
        .stock-status.out-of-stock {
            background: #f8d7da;
            color: #721c24;
        }
        
        .add-to-cart-section {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            padding: 1.5rem;
            background: #f8f9fa;
            border-radius: 10px;
        }
        
        .quantity-selector {
            display: flex;
            align-items: center;
            gap: 1rem;
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
        }
        
        .qty-btn:hover {
            background: #e9ecef;
        }
        
        #quantity {
            border: none;
            padding: 0.5rem;
            width: 60px;
            text-align: center;
            outline: none;
        }
        
        .btn-add-to-cart,
        .btn-out-of-stock,
        .btn-wishlist {
            padding: 1rem 2rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 1.1rem;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        
        .btn-add-to-cart {
            background: #007bff;
            color: white;
        }
        
        .btn-add-to-cart:hover {
            background: #0056b3;
            transform: translateY(-2px);
        }
        
        .btn-out-of-stock {
            background: #6c757d;
            color: white;
            cursor: not-allowed;
        }
        
        .btn-wishlist {
            background: white;
            color: #dc3545;
            border: 2px solid #dc3545;
        }
        
        .btn-wishlist:hover {
            background: #dc3545;
            color: white;
        }
        
        .shop-info {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .shop-info h3 {
            margin-bottom: 1rem;
            color: #333;
        }
        
        .shop-details h4 {
            color: #007bff;
            margin-bottom: 0.5rem;
        }
        
        .shop-owner {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }
        
        .shop-description {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }
        
        .view-shop-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            color: #007bff;
            text-decoration: none;
            font-weight: 500;
        }
        
        .view-shop-btn:hover {
            text-decoration: underline;
        }
        
        .product-features {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
        }
        
        .feature {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #28a745;
            font-size: 0.9rem;
        }
        
        .product-tabs {
            margin: 3rem 0;
        }
        
        .tab-navigation {
            display: flex;
            border-bottom: 2px solid #eee;
            margin-bottom: 2rem;
        }
        
        .tab-btn {
            background: none;
            border: none;
            padding: 1rem 2rem;
            cursor: pointer;
            font-weight: 500;
            color: #666;
            border-bottom: 3px solid transparent;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .tab-btn:hover,
        .tab-btn.active {
            color: #007bff;
            border-bottom-color: #007bff;
        }
        
        .tab-pane {
            display: none;
        }
        
        .tab-pane.active {
            display: block;
        }
        
        .description-content {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .product-specs table {
            width: 100%;
            margin-top: 1rem;
            border-collapse: collapse;
        }
        
        .product-specs td {
            padding: 0.75rem;
            border-bottom: 1px solid #eee;
        }
        
        .product-specs td:first-child {
            font-weight: 500;
            color: #333;
            width: 150px;
        }
        
        .reviews-section {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .reviews-summary {
            margin-bottom: 2rem;
            padding-bottom: 2rem;
            border-bottom: 1px solid #eee;
        }
        
        .rating-overview {
            display: flex;
            align-items: center;
            gap: 2rem;
        }
        
        .average-rating {
            text-align: center;
        }
        
        .rating-number {
            font-size: 3rem;
            font-weight: 700;
            color: #ffc107;
        }
        
        .rating-stars {
            color: #ffc107;
            font-size: 1.5rem;
            margin: 0.5rem 0;
        }
        
        .write-review-section,
        .user-review-section {
            background: #f8f9fa;
            padding: 2rem;
            border-radius: 10px;
            margin-bottom: 2rem;
        }
        
        .review-form {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        .star-rating {
            display: flex;
            gap: 0.3rem;
            font-size: 1.5rem;
        }
        
        .star-rating .fas {
            cursor: pointer;
            transition: color 0.2s;
        }
        
        .review-notice {
            background: #e7f3ff;
            padding: 1.5rem;
            border-radius: 10px;
            text-align: center;
            color: #0c5460;
            margin-bottom: 2rem;
        }
        
        .review-item {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 10px;
            margin-bottom: 1rem;
        }
        
        .review-item.user-review {
            border: 2px solid #007bff;
        }
        
        .review-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .reviewer-name {
            font-weight: 600;
            color: #333;
        }
        
        .review-date {
            color: #666;
            font-size: 0.85rem;
            margin-left: 1rem;
        }
        
        .review-rating .fas.rated {
            color: #ffc107;
        }
        
        .review-rating .fas:not(.rated) {
            color: #ddd;
        }
        
        .no-reviews {
            text-align: center;
            padding: 3rem;
            color: #666;
        }
        
        .shipping-content {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .shipping-option {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }
        
        .return-policy ul {
            list-style: none;
            padding-left: 0;
        }
        
        .return-policy li {
            padding: 0.5rem 0;
            padding-left: 1.5rem;
            position: relative;
        }
        
        .return-policy li:before {
            content: '✓';
            position: absolute;
            left: 0;
            color: #28a745;
            font-weight: bold;
        }
        
        .related-products,
        .shop-products {
            margin: 3rem 0;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }
        
        .section-header h2 {
            color: #333;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .view-all-link {
            color: #007bff;
            text-decoration: none;
            font-weight: 500;
        }
        
        .view-all-link:hover {
            text-decoration: underline;
        }
        
        @media (max-width: 768px) {
            .product-details {
                grid-template-columns: 1fr;
                gap: 2rem;
            }
            
            .product-title {
                font-size: 1.5rem;
            }
            
            .current-price {
                font-size: 2rem;
            }
            
            .tab-navigation {
                flex-wrap: wrap;
            }
            
            .tab-btn {
                padding: 0.75rem 1rem;
                font-size: 0.9rem;
            }
            
            .rating-overview {
                flex-direction: column;
                text-align: center;
            }
            
            .product-features {
                grid-template-columns: 1fr;
            }
            
            .section-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
        }
    </style>
</body>
</html>
