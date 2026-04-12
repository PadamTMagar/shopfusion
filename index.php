<?php
// index.php - Homepage with product listing
require_once 'config/database.php';
require_once 'includes/session.php';
require_once 'includes/functions.php';
require_once 'ai/includes/bootstrap.php';

// Get products for homepage
$products = getActiveProducts(12); // Show 12 products on homepage
$categories = getCategories();

// Get search parameters
$searchQuery = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$categoryFilter = isset($_GET['category']) ? (int)$_GET['category'] : null;
$minPrice = isset($_GET['min_price']) ? (float)$_GET['min_price'] : null;
$maxPrice = isset($_GET['max_price']) ? (float)$_GET['max_price'] : null;
$minRating = isset($_GET['min_rating']) ? (float)$_GET['min_rating'] : null;
$sortBy = isset($_GET['sort']) ? sanitize($_GET['sort']) : 'latest';
$allowedSorts = ['latest', 'popular', 'price_low', 'price_high', 'rating'];

if (!in_array($sortBy, $allowedSorts, true)) {
    $sortBy = 'latest';
}

// If search parameters exist, get filtered results
if ($searchQuery || $categoryFilter || $minPrice || $maxPrice || $minRating) {
    $products = searchProducts($searchQuery, $minPrice, $maxPrice, $minRating, $categoryFilter);
}

$featuredProducts = array_slice($products, 0, 4);
$newArrivals = [];
$bestSellingProducts = [];
$storeStats = [
    'active_products' => count($products),
    'categories' => count($categories),
    'traders' => 0,
    'reviews' => 0,
];

try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'trader' AND status = 'active'");
    $storeStats['traders'] = (int) $stmt->fetch()['total'];

    $stmt = $pdo->query("SELECT COUNT(*) as total FROM reviews");
    $storeStats['reviews'] = (int) $stmt->fetch()['total'];

    $stmt = $pdo->query("
        SELECT p.*, s.shop_name, s.trader_id, u.full_name as trader_name, c.category_name
        FROM products p
        JOIN shops s ON p.shop_id = s.shop_id
        JOIN users u ON s.trader_id = u.user_id
        JOIN categories c ON p.category_id = c.category_id
        WHERE p.status = 'active' AND s.status = 'active' AND u.status = 'active'
        ORDER BY p.created_at DESC
        LIMIT 4
    ");
    $newArrivals = $stmt->fetchAll();

    $stmt = $pdo->query("
        SELECT p.*, s.shop_name, s.trader_id, u.full_name as trader_name, c.category_name,
               COALESCE(SUM(oi.quantity), 0) as total_sold
        FROM products p
        JOIN shops s ON p.shop_id = s.shop_id
        JOIN users u ON s.trader_id = u.user_id
        JOIN categories c ON p.category_id = c.category_id
        LEFT JOIN order_items oi ON p.product_id = oi.product_id
        LEFT JOIN orders o ON oi.order_id = o.order_id AND o.payment_status = 'completed'
        WHERE p.status = 'active' AND s.status = 'active' AND u.status = 'active'
        GROUP BY p.product_id, s.shop_name, s.trader_id, u.full_name, c.category_name
        ORDER BY total_sold DESC, p.rating DESC, p.created_at DESC
        LIMIT 4
    ");
    $bestSellingProducts = $stmt->fetchAll();
} catch (PDOException $exception) {
    $newArrivals = array_slice($products, 0, 4);
    $bestSellingProducts = array_slice($products, 0, 4);
}

// Keep the main product grid sortable without changing the curated homepage shelves.
if (!empty($products)) {
    usort($products, static function (array $a, array $b) use ($sortBy): int {
        switch ($sortBy) {
            case 'popular':
                $scoreA = ((float) ($a['rating'] ?? 0) * 20) + (int) ($a['total_reviews'] ?? 0);
                $scoreB = ((float) ($b['rating'] ?? 0) * 20) + (int) ($b['total_reviews'] ?? 0);
                return $scoreB <=> $scoreA;

            case 'price_low':
                return (float) ($a['price'] ?? 0) <=> (float) ($b['price'] ?? 0);

            case 'price_high':
                return (float) ($b['price'] ?? 0) <=> (float) ($a['price'] ?? 0);

            case 'rating':
                return (float) ($b['rating'] ?? 0) <=> (float) ($a['rating'] ?? 0);

            case 'latest':
            default:
                return strtotime((string) ($b['created_at'] ?? '')) <=> strtotime((string) ($a['created_at'] ?? ''));
        }
    });
}

$showHomepageHighlights = !$searchQuery && !$categoryFilter && !$minPrice && !$maxPrice && !$minRating && $sortBy === 'latest';
$heroVisualProducts = array_slice(!empty($featuredProducts) ? $featuredProducts : $products, 0, 3);

$pageTitle = 'ShopFusion - Your Online Shopping Destination';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/theme.css">
    <link rel="stylesheet" href="css/theme-refresh.css">
    <?php echo ai_render_head_assets('.'); ?>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <!-- <h1><a href="index.php">Shopfusion</a></h1> -->
                     <img src="images/logo.png" alt="Shopfusion Logo" class="logo-image" style="width: 190px; height: 40px;">
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
                                <span class="cart-count" id="cartCountGuest"><?php echo getGuestCartCount(); ?></span>
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
            <?php if ($showHomepageHighlights): ?>
            <!-- Hero Section -->
            <section class="hero">
                <div class="hero-layout">
                    <div class="hero-copy">
                        <span class="hero-eyebrow"><i class="fas fa-bolt"></i> Fresh marketplace picks</span>
                        <h2>Shop bold. Buy easy.</h2>
                        <p>Clean browsing. Fast checkout.</p>
                        <div class="hero-cta-group">
                            <a href="#product-filters" class="cta-button">Browse Products</a>
                        </div>
                    </div>
                    <div class="hero-visual" aria-hidden="true">
                        <div class="hero-glow hero-glow-one"></div>
                        <div class="hero-glow hero-glow-two"></div>
                        <div class="hero-ring hero-ring-one"></div>
                        <div class="hero-ring hero-ring-two"></div>
                        <div class="hero-centerpiece">
                            <span class="hero-center-label">ShopFusion</span>
                            <div class="hero-center-icon">
                                <i class="fas fa-bag-shopping"></i>
                            </div>
                            <span class="hero-center-subtitle"><?php echo number_format($storeStats['active_products']); ?>+ picks</span>
                        </div>
                        <?php foreach ($heroVisualProducts as $index => $heroProduct): ?>
                            <article class="hero-float-card hero-float-card-<?php echo $index + 1; ?>">
                                <div class="hero-float-thumb">
                                    <img src="<?php echo htmlspecialchars(getImagePath($heroProduct['image_path'])); ?>" alt="">
                                </div>
                                <div class="hero-float-copy">
                                    <strong>
                                        <?php
                                        $heroName = $heroProduct['product_name'];
                                        echo htmlspecialchars(strlen($heroName) > 18 ? substr($heroName, 0, 18) . '...' : $heroName);
                                        ?>
                                    </strong>
                                    <span><?php echo formatPrice($heroProduct['price']); ?></span>
                                </div>
                            </article>
                        <?php endforeach; ?>
                        <span class="hero-floating-badge hero-floating-badge-one"><i class="fas fa-truck-fast"></i> Fast</span>
                        <span class="hero-floating-badge hero-floating-badge-two"><i class="fas fa-shield-alt"></i> Secure</span>
                        <span class="hero-floating-badge hero-floating-badge-three"><i class="fas fa-star"></i> Popular</span>
                    </div>
                </div>
            </section>
            <?php endif; ?>

            <!-- Filters Section -->
            <section class="filters" id="product-filters">
                <div class="filter-container">
                    <div class="filter-heading">
                        <div>
                            <span class="ai-kicker">Browse</span>
                            <h3>Filter products</h3>
                        </div>
                        <p>Find the right pick faster.</p>
                    </div>
                    <form method="GET" action="index.php" class="filter-form">
                        <div class="filter-group">
                            <label for="homeSearch">Search</label>
                            <input type="text" id="homeSearch" name="search" value="<?php echo htmlspecialchars($searchQuery); ?>" placeholder="Product name...">
                        </div>
                        
                        <div class="filter-group">
                            <label for="homeCategory">Category</label>
                            <select name="category" id="homeCategory">
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
                            <label for="homeMinPrice">Min Price</label>
                            <input type="number" id="homeMinPrice" name="min_price" placeholder="Min" value="<?php echo $minPrice; ?>" step="0.01">
                        </div>
                        
                        <div class="filter-group">
                            <label for="homeMaxPrice">Max Price</label>
                            <input type="number" id="homeMaxPrice" name="max_price" placeholder="Max" value="<?php echo $maxPrice; ?>" step="0.01">
                        </div>

                        <div class="filter-group">
                            <label for="homeRating">Min Rating</label>
                            <select name="min_rating" id="homeRating">
                                <option value="">Any Rating</option>
                                <option value="4" <?php echo $minRating == 4 ? 'selected' : ''; ?>>4+ Stars</option>
                                <option value="3" <?php echo $minRating == 3 ? 'selected' : ''; ?>>3+ Stars</option>
                                <option value="2" <?php echo $minRating == 2 ? 'selected' : ''; ?>>2+ Stars</option>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label for="homeSort">Sort</label>
                            <select name="sort" id="homeSort">
                                <option value="latest" <?php echo $sortBy === 'latest' ? 'selected' : ''; ?>>Latest</option>
                                <option value="popular" <?php echo $sortBy === 'popular' ? 'selected' : ''; ?>>Popular</option>
                                <option value="rating" <?php echo $sortBy === 'rating' ? 'selected' : ''; ?>>Top Rated</option>
                                <option value="price_low" <?php echo $sortBy === 'price_low' ? 'selected' : ''; ?>>Price: Low to High</option>
                                <option value="price_high" <?php echo $sortBy === 'price_high' ? 'selected' : ''; ?>>Price: High to Low</option>
                            </select>
                        </div>

                        <div class="filter-actions">
                            <button type="submit" class="filter-btn">Apply</button>
                            <a href="index.php" class="clear-btn">Clear</a>
                        </div>
                    </form>
                </div>
            </section>

            <!-- Products Section -->
            <section class="products" id="products">
                <div class="section-header">
                    <h2>
                        <?php if ($searchQuery || $categoryFilter || $minPrice || $maxPrice || $minRating || $sortBy !== 'latest'): ?>
                            Search Results
                            <?php if ($searchQuery): ?>
                                for "<?php echo htmlspecialchars($searchQuery); ?>"
                            <?php endif; ?>
                        <?php else: ?>
                            Browse Products
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
                                    <img src="<?php echo htmlspecialchars(getImagePath($product['image_path'])); ?>" 
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

            <?php if ($showHomepageHighlights): ?>
            <section class="featured-shelf" id="featured-products">
                <div class="shelf-header">
                    <div>
                        <span class="ai-kicker">Featured</span>
                        <h2>Curated picks for confident buying</h2>
                    </div>
                    <p>Strong-value products highlighted for quality, clarity, and everyday usefulness.</p>
                </div>
                <div class="shelf-grid">
                    <?php foreach ($featuredProducts as $product): ?>
                        <div class="product-card">
                            <div class="product-image">
                                <img src="<?php echo htmlspecialchars(getImagePath($product['image_path'])); ?>" alt="<?php echo htmlspecialchars($product['product_name']); ?>">
                                <span class="stock-badge">Featured</span>
                            </div>
                            <div class="product-info">
                                <h3 class="product-name"><a href="shop/product.php?id=<?php echo $product['product_id']; ?>"><?php echo htmlspecialchars($product['product_name']); ?></a></h3>
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
                                <div class="product-price"><?php echo formatPrice($product['price']); ?></div>
                                <div class="product-actions">
                                    <button class="btn-cart" onclick="addToCart(<?php echo $product['product_id']; ?>)"><i class="fas fa-cart-plus"></i> Add to Cart</button>
                                    <a href="shop/product.php?id=<?php echo $product['product_id']; ?>" class="btn-view">View Details</a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="featured-shelf">
                <div class="shelf-header">
                    <div>
                        <span class="ai-kicker">Best Sellers</span>
                        <h2>Popular with customers right now</h2>
                    </div>
                    <p>These products surface frequent buying intent and stronger marketplace interest.</p>
                </div>
                <div class="shelf-grid">
                    <?php foreach ($bestSellingProducts as $product): ?>
                        <div class="product-card">
                            <div class="product-image">
                                <img src="<?php echo htmlspecialchars(getImagePath($product['image_path'])); ?>" alt="<?php echo htmlspecialchars($product['product_name']); ?>">
                                <span class="stock-badge">Best Seller</span>
                            </div>
                            <div class="product-info">
                                <h3 class="product-name"><a href="shop/product.php?id=<?php echo $product['product_id']; ?>"><?php echo htmlspecialchars($product['product_name']); ?></a></h3>
                                <p class="product-shop">by <?php echo htmlspecialchars($product['shop_name']); ?></p>
                                <div class="product-price"><?php echo formatPrice($product['price']); ?></div>
                                <div class="product-actions">
                                    <button class="btn-cart" onclick="addToCart(<?php echo $product['product_id']; ?>)"><i class="fas fa-cart-plus"></i> Add to Cart</button>
                                    <a href="shop/product.php?id=<?php echo $product['product_id']; ?>" class="btn-view">View Details</a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="featured-shelf">
                <div class="shelf-header">
                    <div>
                        <span class="ai-kicker">New Arrivals</span>
                        <h2>Fresh additions to the marketplace</h2>
                    </div>
                    <p>Recently added products that keep the catalog feeling active and current.</p>
                </div>
                <div class="shelf-grid">
                    <?php foreach ($newArrivals as $product): ?>
                        <div class="product-card">
                            <div class="product-image">
                                <img src="<?php echo htmlspecialchars(getImagePath($product['image_path'])); ?>" alt="<?php echo htmlspecialchars($product['product_name']); ?>">
                                <span class="stock-badge">New</span>
                            </div>
                            <div class="product-info">
                                <h3 class="product-name"><a href="shop/product.php?id=<?php echo $product['product_id']; ?>"><?php echo htmlspecialchars($product['product_name']); ?></a></h3>
                                <p class="product-shop">by <?php echo htmlspecialchars($product['shop_name']); ?></p>
                                <div class="product-price"><?php echo formatPrice($product['price']); ?></div>
                                <div class="product-actions">
                                    <button class="btn-cart" onclick="addToCart(<?php echo $product['product_id']; ?>)"><i class="fas fa-cart-plus"></i> Add to Cart</button>
                                    <a href="shop/product.php?id=<?php echo $product['product_id']; ?>" class="btn-view">View Details</a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="trust-section">
                <div class="trust-header">
                    <div>
                        <span class="ai-kicker">Why ShopFusion</span>
                        <h2>Built to reduce hesitation and build trust</h2>
                    </div>
                    <p>Clear guidance, easy scanning, and practical buying signals throughout the journey.</p>
                </div>
                <div class="benefits-grid">
                    <article class="benefit-card">
                        <h3><i class="fas fa-shield-alt"></i> Secure and clear checkout</h3>
                        <p>Payment options, totals, and trust messaging stay visible so customers feel confident before placing an order.</p>
                    </article>
                    <article class="benefit-card">
                        <h3><i class="fas fa-truck"></i> Delivery expectations up front</h3>
                        <p>Shipping speed and return messaging are easier to find, lowering friction before customers commit.</p>
                    </article>
                    <article class="benefit-card">
                        <h3><i class="fas fa-comments"></i> Guided support in context</h3>
                        <p>The AI assistant is available on shopping pages for product, payment, delivery, and policy questions.</p>
                    </article>
                    <article class="benefit-card">
                        <h3><i class="fas fa-store"></i> Better visibility for traders</h3>
                        <p>Cleaner dashboards and cards help sellers focus on products, orders, and performance quickly.</p>
                    </article>
                </div>
            </section>

            <section class="faq-section">
                <div class="trust-header">
                    <div>
                        <span class="ai-kicker">Buying Help</span>
                        <h2>Frequently asked questions</h2>
                    </div>
                    <p>Simple answers that support trust without adding clutter.</p>
                </div>
                <div class="faq-list">
                    <article class="faq-item">
                        <h3>How long does delivery take?</h3>
                        <p>Standard shipping usually takes 5-7 business days, while express delivery can arrive within 2-3 business days when available.</p>
                    </article>
                    <article class="faq-item">
                        <h3>Can I return a product if it is not right for me?</h3>
                        <p>Yes. ShopFusion supports a 30-day return window, with free returns for defective products and clear policy messaging on product pages.</p>
                    </article>
                    <article class="faq-item">
                        <h3>How do I check my order details?</h3>
                        <p>Signed-in customers can review purchases and order status from the My Orders area, and the chatbot can guide them to the right step.</p>
                    </article>
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
    <?php echo ai_render_chat_widget('.', [
        'page_type' => 'home',
        'page_title' => $pageTitle,
    ]); ?>
</body>
</html>
