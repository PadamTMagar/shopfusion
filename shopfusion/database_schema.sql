-- ShopFusion Database Schema
-- Complete schema for e-commerce system

CREATE DATABASE IF NOT EXISTS shopfusion;
USE shopfusion;

-- Users table (admin, traders, customers)
CREATE TABLE users (
    user_id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    address TEXT,
    role ENUM('admin', 'trader', 'customer') NOT NULL,
    status ENUM('active', 'pending', 'disabled') DEFAULT 'active',
    violation_count INT DEFAULT 0,
    loyalty_points INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Categories table
CREATE TABLE categories (
    category_id INT PRIMARY KEY AUTO_INCREMENT,
    category_name VARCHAR(100) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Shops table
CREATE TABLE shops (
    shop_id INT PRIMARY KEY AUTO_INCREMENT,
    trader_id INT NOT NULL,
    shop_name VARCHAR(100) NOT NULL,
    description TEXT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (trader_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Products table
CREATE TABLE products (
    product_id INT PRIMARY KEY AUTO_INCREMENT,
    shop_id INT NOT NULL,
    category_id INT NOT NULL,
    product_name VARCHAR(200) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    stock_quantity INT DEFAULT 0,
    image_path VARCHAR(255),
    rating DECIMAL(3,2) DEFAULT 0,
    total_reviews INT DEFAULT 0,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (shop_id) REFERENCES shops(shop_id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(category_id) ON DELETE RESTRICT
);

-- Cart items table
CREATE TABLE cart_items (
    cart_id INT PRIMARY KEY AUTO_INCREMENT,
    customer_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE,
    UNIQUE KEY unique_cart_item (customer_id, product_id)
);

-- Orders table
CREATE TABLE orders (
    order_id INT PRIMARY KEY AUTO_INCREMENT,
    customer_id INT NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    discount_amount DECIMAL(10,2) DEFAULT 0,
    promo_code VARCHAR(50),
    payment_status ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'pending',
    payment_method VARCHAR(50),
    payment_id VARCHAR(100),
    shipping_address TEXT,
    order_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES users(user_id) ON DELETE RESTRICT
);

-- Order items table
CREATE TABLE order_items (
    order_item_id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    shop_id INT NOT NULL,
    quantity INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    total DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE RESTRICT,
    FOREIGN KEY (shop_id) REFERENCES shops(shop_id) ON DELETE RESTRICT
);

-- Promo codes table
CREATE TABLE promo_codes (
    promo_id INT PRIMARY KEY AUTO_INCREMENT,
    code VARCHAR(50) UNIQUE NOT NULL,
    discount_type ENUM('percentage', 'fixed') NOT NULL,
    discount_value DECIMAL(10,2) NOT NULL,
    min_order_amount DECIMAL(10,2) DEFAULT 0,
    max_uses INT,
    current_uses INT DEFAULT 0,
    start_date DATE,
    end_date DATE,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL
);

-- Violations table
CREATE TABLE violations (
    violation_id INT PRIMARY KEY AUTO_INCREMENT,
    reported_user_id INT NOT NULL,
    reporter_id INT NOT NULL,
    violation_type VARCHAR(100) NOT NULL,
    description TEXT NOT NULL,
    status ENUM('pending', 'resolved', 'dismissed') DEFAULT 'pending',
    action_taken VARCHAR(255),
    resolved_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (reported_user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (reporter_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Reviews table
CREATE TABLE reviews (
    review_id INT PRIMARY KEY AUTO_INCREMENT,
    product_id INT NOT NULL,
    customer_id INT NOT NULL,
    order_id INT NOT NULL,
    rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    comment TEXT,
    status ENUM('active', 'hidden') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE,
    UNIQUE KEY unique_review (product_id, customer_id, order_id)
);

-- Activity log table
CREATE TABLE activity_log (
    log_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    action VARCHAR(255) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
);

-- Insert default admin user
INSERT INTO users (username, email, password, full_name, role, status) VALUES
('admin', 'admin@shopfusion.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'admin', 'active');

-- Insert categories
INSERT INTO categories (category_name, description) VALUES
('Electronics', 'Electronic devices and accessories'),
('Clothing', 'Fashion and apparel'),
('Books', 'Books and literature'),
('Home & Garden', 'Home improvement and garden supplies'),
('Sports', 'Sports equipment and accessories');

-- Insert sample traders (5 traders as required)
INSERT INTO users (username, email, password, full_name, phone, role, status) VALUES
('trader1', 'trader1@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'John Electronics', '123-456-7890', 'trader', 'active'),
('trader2', 'trader2@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Sarah Fashion', '123-456-7891', 'trader', 'active'),
('trader3', 'trader3@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Mike Books', '123-456-7892', 'trader', 'active'),
('trader4', 'trader4@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Lisa Home', '123-456-7893', 'trader', 'active'),
('trader5', 'trader5@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Tom Sports', '123-456-7894', 'trader', 'active');

-- Insert shops (2 shops as required, multiple traders can share shops)
INSERT INTO shops (trader_id, shop_name, description) VALUES
(2, 'TechHub Store', 'Your one-stop shop for electronics and gadgets'),
(3, 'Fashion Central', 'Trendy clothing and accessories for everyone');

-- Insert sample products (10 products total, 5 in each shop)
INSERT INTO products (shop_id, category_id, product_name, description, price, stock_quantity, rating, total_reviews) VALUES
-- TechHub Store products
(1, 1, 'Wireless Bluetooth Headphones', 'High-quality wireless headphones with noise cancellation', 89.99, 50, 4.5, 120),
(1, 1, 'Smartphone Case', 'Protective case for latest smartphone models', 19.99, 200, 4.2, 89),
(1, 1, 'USB-C Charging Cable', 'Fast charging cable compatible with most devices', 12.99, 150, 4.0, 67),
(1, 1, 'Portable Power Bank', '10000mAh portable charger with dual USB ports', 34.99, 75, 4.3, 156),
(1, 1, 'Bluetooth Speaker', 'Compact wireless speaker with excellent sound quality', 45.99, 60, 4.4, 203),

-- Fashion Central products
(2, 2, 'Cotton T-Shirt', 'Comfortable 100% cotton t-shirt in various colors', 24.99, 100, 4.1, 78),
(2, 2, 'Denim Jeans', 'Classic fit denim jeans for everyday wear', 59.99, 80, 4.3, 145),
(2, 2, 'Running Shoes', 'Lightweight running shoes with cushioned sole', 79.99, 40, 4.6, 234),
(2, 2, 'Leather Wallet', 'Genuine leather wallet with multiple card slots', 39.99, 90, 4.2, 67),
(2, 2, 'Winter Jacket', 'Warm and stylish winter jacket for cold weather', 129.99, 30, 4.5, 89);

-- Insert sample promo codes
INSERT INTO promo_codes (code, discount_type, discount_value, min_order_amount, max_uses, start_date, end_date, created_by) VALUES
('WELCOME10', 'percentage', 10.00, 50.00, 100, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 30 DAY), 1),
('SAVE20', 'fixed', 20.00, 100.00, 50, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 15 DAY), 1),
('NEWUSER', 'percentage', 15.00, 25.00, NULL, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 60 DAY), 1);

-- Insert sample customer
INSERT INTO users (username, email, password, full_name, phone, role, status, loyalty_points) VALUES
('customer1', 'customer1@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Jane Customer', '555-123-4567', 'customer', 'active', 150);