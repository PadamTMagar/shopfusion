<?php
// index.php - Homepage with product listing
require_once 'config/database.php';
require_once 'includes/session.php';
require_once 'includes/functions.php';

// Get products for homepage
$products = getActiveProducts(12); // Show 12 products on homepage
$categories = getCategories();

// Get search parameters
$searchQuery = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$categoryFilter = isset($_GET['category']) ? (int)$_GET['category'] : null;
$minPrice = isset($_GET['min_price']) ? (float)$_GET['min_price'] : null;
$maxPrice = isset($_GET['max_price']) ? (float)$_GET['max_price'] : null;
$minRating = isset($_GET['min_rating']) ? (float)$_GET['min_rating'] : null;

// If search parameters exist, get filtered results
if ($searchQuery || $categoryFilter || $minPrice || $maxPrice || $minRating) {
    $products = searchProducts($searchQuery, $minPrice, $maxPrice, $minRating, $categoryFilter);
}

$pageTitle = 'ShopFusion - Your Online Shopping Destination';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <h1><a href="index.php">Shopfusion</a></h1>
                </div>
                
                <!-- Search Bar -->
                <div class="search-bar">
                    <form method="GET" action="index.php" class="search-form">
                        <input type="text" name="search" placeholder="Search products..." 
                               value="<?php echo htmlspecialchars($searchQuery); ?>">
                        <button type="submit"><i class="fas fa-search"></i></button>
                    </form>
                </div>
                
                <!-- User Menu -->
                <div class="user-menu">
                    <?php if (isLoggedIn()): ?>
                        <div class="cart-icon">
                            <a href="shop/cart.php">
                                <i class="fas fa-shopping-cart"></i>
                                <span class="cart-count" id="cartCount">
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
                                    <a href="admin/"><i class="fas fa-tachometer-alt"></i> Admin Dashboard</a>
                                <?php elseif (isTrader()): ?>
                                    <a href="trader/"><i class="fas fa-store"></i> Trader Dashboard</a>
                                <?php else: ?>
                                    <a href="customer/profile.php"><i class="fas fa-user"></i> My Profile</a>
                                    <a href="customer/orders.php"><i class="fas fa-box"></i> My Orders</a>
                                    <a href="customer/points.php"><i class="fas fa-star"></i> Loyalty Points</a>
                                <?php endif; ?>
                                <a href="auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="cart-icon">
                            <a href="shop/cart.php">
                                <i class="fas fa-shopping-cart"></i>
                                <span class="cart-count" id="cartCount"><?php echo getGuestCartCount(); ?></span>
                            </a>
                        </div>
                        <a href="auth/login.php" class="login-btn">Login</a>
                        <a href="auth/register.php" class="register-btn">Register</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>

    <!-- Flash Messages -->
    <?php if ($message = getFlashMessage('success')): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    <?php if ($message = getFlashMessage('error')): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <!-- Main Content -->
    <main class="main-content">
        <div class="container">
            <!-- Hero Section -->
            <?php if (!$searchQuery && !$categoryFilter): ?>
            <section class="hero">
                <div class="hero-content">
                    <h2>Welcome to Shopfusion</h2>
                    <p>Discover amazing products from trusted traders</p>
                    <a href="#products" class="cta-button">Shop Now</a>
                </div>
            </section>
            <?php endif; ?>

            <!-- Filters Section -->
            <section class="filters">
                <div class="filter-container">
                    <h3>Filter Products</h3>
                    <form method="GET" action="index.php" class="filter-form">
                        <div class="filter-group">
                            <label>Search:</label>
                            <input type="text" name="search" value="<?php echo htmlspecialchars($searchQuery); ?>" placeholder="Product name...">
                        </div>
                        
                        <div class="filter-group">
                            <label>Category:</label>
                            <select name="category">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['category_id']; ?>" 
                                            <?php echo $categoryFilter == $category['category_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category['category_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label>Price Range:</label>
                            <input type="number" name="min_price" placeholder="Min" value="<?php echo $minPrice; ?>" step="0.01">
                            <input type="number" name="max_price" placeholder="Max" value="<?php echo $maxPrice; ?>" step="0.01">
                        </div>
                        
                        <div class="filter-group">
                            <label>Min Rating:</label>
                            <select name="min_rating">
                                <option value="">Any Rating</option>
                                <option value="4" <?php echo $minRating == 4 ? 'selected' : ''; ?>>4+ Stars</option>
                                <option value="3" <?php echo $minRating == 3 ? 'selected' : ''; ?>>3+ Stars</option>
                                <option value="2" <?php echo $minRating == 2 ? 'selected' : ''; ?>>2+ Stars</option>
                            </select>
                        </div>
                        
                        <button type="submit" class="filter-btn">Apply Filters</button>
                        <a href="index.php" class="clear-btn">Clear</a>
                    </form>
                </div>
            </section>

            <!-- Products Section -->
            <section class="products" id="products">
                <div class="section-header">
                    <h2>
                        <?php if ($searchQuery || $categoryFilter): ?>
                            Search Results
                            <?php if ($searchQuery): ?>
                                for "<?php echo htmlspecialchars($searchQuery); ?>"
                            <?php endif; ?>
                        <?php else: ?>
                            Featured Products
                        <?php endif; ?>
                    </h2>
                    <p><?php echo count($products); ?> products found</p>
                </div>
                
                <?php if (empty($products)): ?>
                    <div class="no-products">
                        <i class="fas fa-search"></i>
                        <h3>No products found</h3>
                        <p>Try adjusting your search criteria or browse our categories.</p>
                        <a href="index.php" class="btn-primary">View All Products</a>
                    </div>
                <?php else: ?>
                    <div class="products-grid">
                        <?php foreach ($products as $product): ?>
                            <div class="product-card">
                                <div class="product-image">
                                    <img src="<?php echo file_exists($product['image_path']) ? $product['image_path'] : 'images/placeholder.jpg'; ?>" 
                                         alt="<?php echo htmlspecialchars($product['product_name']); ?>">
                                    <?php if ($product['stock_quantity'] <= 5): ?>
                                        <span class="stock-badge">Only <?php echo $product['stock_quantity']; ?> left!</span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="product-info">
                                    <h3 class="product-name">
                                        <a href="shop/product.php?id=<?php echo $product['product_id']; ?>">
                                            <?php echo htmlspecialchars($product['product_name']); ?>
                                        </a>
                                    </h3>
                                    
                                    <p class="product-shop">by <?php echo htmlspecialchars($product['shop_name']); ?></p>
                                    
                                    <div class="product-rating">
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
                                        <span class="rating-text">(<?php echo $product['total_reviews']; ?>)</span>
                                    </div>
                                    
                                    <div class="product-price">
                                        <?php echo formatPrice($product['price']); ?>
                                    </div>
                                    
                                    <div class="product-actions">
                                        <button class="btn-cart" onclick="addToCart(<?php echo $product['product_id']; ?>)">
                                            <i class="fas fa-cart-plus"></i> Add to Cart
                                        </button>
                                        <a href="shop/product.php?id=<?php echo $product['product_id']; ?>" class="btn-view">
                                            View Details
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
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
                        <li><a href="index.php">Home</a></li>
                        <li><a href="shop/search.php">Search</a></li>
                        <li><a href="auth/register.php">Become a Customer</a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h4>For Sellers</h4>
                    <ul>
                        <li><a href="auth/register.php?type=trader">Become a Trader</a></li>
                        <li><a href="trader/">Trader Dashboard</a></li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2024 Shopfusion. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script src="js/main.js"></script>
    <script src="js/cart.js"></script>
</body>
</html>