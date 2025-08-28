-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 23, 2025 at 08:43 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `shopfusion`
--

-- --------------------------------------------------------

--
-- Table structure for table `cart_items`
--

CREATE TABLE `cart_items` (
  `cart_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `added_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `category_id` int(11) NOT NULL,
  `category_name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`category_id`, `category_name`, `description`) VALUES
(1, 'Electronics', 'Gadgets and electronic devices'),
(2, 'Clothing', 'Fashion and apparel'),
(3, 'Books', 'Educational and entertainment books'),
(4, 'Sports', 'Sports equipment and gear'),
(5, 'Home', 'Home and garden items');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `order_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `payment_status` enum('pending','completed','failed') DEFAULT 'pending',
  `payment_method` varchar(50) DEFAULT NULL,
  `paypal_transaction_id` varchar(100) DEFAULT NULL,
  `shipping_address` text NOT NULL,
  `order_status` enum('pending','processing','shipped','delivered','cancelled') DEFAULT 'pending',
  `promo_code` varchar(20) DEFAULT NULL,
  `discount_amount` decimal(10,2) DEFAULT 0.00,
  `points_used` int(11) DEFAULT 0,
  `points_earned` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`order_id`, `customer_id`, `total_amount`, `payment_status`, `payment_method`, `paypal_transaction_id`, `shipping_address`, `order_status`, `promo_code`, `discount_amount`, `points_used`, `points_earned`, `created_at`, `updated_at`) VALUES
(1, 7, 162002.16, 'pending', 'paypal', NULL, '111 Buyer Blvd', 'shipped', NULL, 0.00, 0, 162002, '2025-08-22 21:21:41', '2025-08-22 22:10:31'),
(2, 7, 2.16, 'pending', 'paypal', NULL, 'jwagal 10 lalitpur', 'pending', NULL, 0.00, 0, 2, '2025-08-23 06:33:07', '2025-08-23 06:33:07');

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `item_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `shop_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`item_id`, `order_id`, `product_id`, `shop_id`, `quantity`, `unit_price`, `subtotal`) VALUES
(1, 1, 11, 3, 1, 150000.00, 150000.00),
(2, 1, 12, 3, 1, 2.00, 2.00),
(3, 2, 12, 3, 1, 2.00, 2.00);

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `product_id` int(11) NOT NULL,
  `shop_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `product_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `stock_quantity` int(11) DEFAULT 0,
  `image_path` varchar(255) DEFAULT NULL,
  `rating` decimal(3,2) DEFAULT 0.00,
  `total_reviews` int(11) DEFAULT 0,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`product_id`, `shop_id`, `category_id`, `product_name`, `description`, `price`, `stock_quantity`, `image_path`, `rating`, `total_reviews`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 'Smartphone X1', 'Latest Android smartphone with 128GB storage', 299.99, 50, 'uploads/products/charger.png', 4.50, 23, 'active', '2025-08-22 07:00:48', '2025-08-22 22:25:09'),
(2, 1, 1, 'Wireless Headphones', 'Bluetooth noise-canceling headphones', 79.99, 30, 'uploads/products/earbuds.png', 4.20, 15, 'active', '2025-08-22 07:00:48', '2025-08-22 22:25:09'),
(3, 1, 1, 'Laptop Pro 15', '15-inch laptop with 8GB RAM and SSD', 699.99, 15, 'uploads/products/laptopbag.png', 4.70, 31, 'active', '2025-08-22 07:00:48', '2025-08-22 22:25:09'),
(4, 1, 1, 'Smart Watch', 'Fitness tracking smartwatch', 149.99, 25, 'uploads/products/smartwatch1.png', 4.30, 18, 'active', '2025-08-22 07:00:48', '2025-08-22 22:25:09'),
(5, 1, 1, 'Portable Charger', '10000mAh power bank with fast charging', 29.99, 100, 'uploads/products/charger.png', 4.10, 42, 'active', '2025-08-22 07:00:48', '2025-08-22 22:25:09'),
(6, 2, 2, 'Designer Jeans', 'Premium denim jeans in various sizes', 89.99, 40, 'uploads/products/jacket.png', 4.40, 28, 'active', '2025-08-22 07:00:48', '2025-08-22 22:25:09'),
(7, 2, 2, 'Summer Dress', 'Floral print summer dress', 49.99, 35, 'uploads/products/jacket.png', 4.60, 19, 'active', '2025-08-22 07:00:48', '2025-08-22 22:25:09'),
(8, 2, 2, 'Leather Jacket', 'Genuine leather motorcycle jacket', 199.99, 12, 'uploads/products/jacket.png', 4.80, 7, 'active', '2025-08-22 07:00:48', '2025-08-22 22:25:09'),
(9, 2, 2, 'Running Shoes', 'Comfortable athletic running shoes', 79.99, 60, 'uploads/products/shoes.png', 4.30, 35, 'active', '2025-08-22 07:00:48', '2025-08-22 22:25:09'),
(10, 2, 2, 'Handbag Premium', 'Luxury leather handbag', 129.99, 20, 'uploads/products/wallet.png', 4.50, 12, 'active', '2025-08-22 07:00:48', '2025-08-22 22:25:09'),
(11, 3, 1, 'Laptop', 'gmaing laptop', 150000.00, 1, 'uploads/products/laptopbag.png', 0.00, 0, 'active', '2025-08-22 20:47:00', '2025-08-22 22:25:09'),
(12, 3, 1, 'Charger 20w', 'Super fast charger', 2.00, 3, 'uploads/products/1755896247_charger.png', 0.00, 0, 'active', '2025-08-22 20:57:27', '2025-08-23 06:33:07');

-- --------------------------------------------------------

--
-- Table structure for table `promo_codes`
--

CREATE TABLE `promo_codes` (
  `promo_id` int(11) NOT NULL,
  `code` varchar(20) NOT NULL,
  `discount_type` enum('percentage','fixed') NOT NULL,
  `discount_value` decimal(10,2) NOT NULL,
  `min_order_amount` decimal(10,2) DEFAULT 0.00,
  `max_uses` int(11) DEFAULT NULL,
  `current_uses` int(11) DEFAULT 0,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `promo_codes`
--

INSERT INTO `promo_codes` (`promo_id`, `code`, `discount_type`, `discount_value`, `min_order_amount`, `max_uses`, `current_uses`, `start_date`, `end_date`, `status`, `created_at`) VALUES
(1, 'WELCOME10', 'percentage', 10.00, 50.00, 100, 0, '2024-01-01', '2025-12-31', 'active', '2025-08-22 07:00:48'),
(2, 'SAVE20', 'fixed', 20.00, 100.00, 50, 0, '2024-01-01', '2024-12-31', 'active', '2025-08-22 07:00:48'),
(3, 'NEWUSER', 'percentage', 15.00, 0.00, 200, 0, '2024-01-01', '2024-12-31', 'active', '2025-08-22 07:00:48');

-- --------------------------------------------------------

--
-- Table structure for table `reviews`
--

CREATE TABLE `reviews` (
  `review_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `rating` int(11) DEFAULT NULL CHECK (`rating` >= 1 and `rating` <= 5),
  `comment` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `shops`
--

CREATE TABLE `shops` (
  `shop_id` int(11) NOT NULL,
  `trader_id` int(11) NOT NULL,
  `shop_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `shops`
--

INSERT INTO `shops` (`shop_id`, `trader_id`, `shop_name`, `description`, `status`, `created_at`) VALUES
(1, 2, 'TechHub Electronics', 'Your one-stop shop for latest electronics', 'active', '2025-08-22 07:00:48'),
(2, 3, 'Fashion Forward', 'Trendy clothes and accessories', 'active', '2025-08-22 07:00:48'),
(3, 9, 'Thulo Pasal', 'Grocery stores', 'active', '2025-08-22 17:27:16');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `role` enum('admin','trader','customer') NOT NULL,
  `status` enum('active','pending','disabled') DEFAULT 'active',
  `violation_count` int(11) DEFAULT 0,
  `loyalty_points` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `email`, `password`, `full_name`, `phone`, `address`, `role`, `status`, `violation_count`, `loyalty_points`, `created_at`, `updated_at`) VALUES
(1, 'admin', 'admin@shopfusion.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', NULL, NULL, 'admin', 'active', 0, 0, '2025-08-22 07:00:48', '2025-08-22 07:00:48'),
(2, 'trader1', 'trader1@shopfusion.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'John Electronics', '1234567890', '123 Tech Street', 'trader', 'active', 0, 0, '2025-08-22 07:00:48', '2025-08-22 07:00:48'),
(3, 'trader2', 'trader2@shopfusion.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Mary Fashion', '1234567891', '456 Style Avenue', 'trader', 'active', 0, 0, '2025-08-22 07:00:48', '2025-08-22 07:00:48'),
(4, 'trader3', 'trader3@shopfusion.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Bob Sports', '1234567892', '789 Game Lane', 'trader', 'pending', 0, 0, '2025-08-22 07:00:48', '2025-08-22 07:00:48'),
(5, 'trader4', 'trader4@shopfusion.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Alice Books', '1234567893', '321 Read Road', 'trader', 'active', 0, 0, '2025-08-22 07:00:48', '2025-08-22 07:00:48'),
(6, 'trader5', 'trader5@shopfusion.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Tom Home', '1234567894', '654 House Hill', 'trader', 'active', 0, 0, '2025-08-22 07:00:48', '2025-08-22 07:00:48'),
(7, 'customer1', 'customer1@shopfusion.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Sarah Johnson', '2234567890', '111 Buyer Blvd', 'customer', 'active', 0, 162054, '2025-08-22 07:00:48', '2025-08-23 06:33:07'),
(8, 'customer2', 'customer2@shopfusion.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Mike Wilson', '2234567891', '222 Shop Street', 'customer', 'active', 0, 100, '2025-08-22 07:00:48', '2025-08-22 07:00:48'),
(9, 'Padam', 'thulopasal567@gmail.com', '$2y$10$Jqyf8vJF1Aiq7uihaKVQM.2d9TDD5.8TtYZ2g1enqs0XRFuuyvWdG', 'Padam Thapa Magar', '9861040699', 'jwagal, 10 lalitpur', 'trader', 'active', 1, 0, '2025-08-22 17:27:16', '2025-08-22 21:53:31');

-- --------------------------------------------------------

--
-- Table structure for table `violations`
--

CREATE TABLE `violations` (
  `violation_id` int(11) NOT NULL,
  `reported_user_id` int(11) NOT NULL,
  `reporter_id` int(11) NOT NULL,
  `violation_type` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `action_taken` enum('warning','account_disabled','dismissed','product_disabled') DEFAULT 'warning',
  `status` enum('pending','resolved') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `resolved_at` timestamp NULL DEFAULT NULL,
  `admin_notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `violations`
--

INSERT INTO `violations` (`violation_id`, `reported_user_id`, `reporter_id`, `violation_type`, `description`, `action_taken`, `status`, `created_at`, `resolved_at`, `admin_notes`) VALUES
(1, 9, 1, 'fraud', 'not working properly', 'warning', 'pending', '2025-08-22 21:53:31', NULL, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `cart_items`
--
ALTER TABLE `cart_items`
  ADD PRIMARY KEY (`cart_id`),
  ADD UNIQUE KEY `unique_cart_item` (`customer_id`,`product_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`category_id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`order_id`),
  ADD KEY `customer_id` (`customer_id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`item_id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `shop_id` (`shop_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`product_id`),
  ADD KEY `shop_id` (`shop_id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `promo_codes`
--
ALTER TABLE `promo_codes`
  ADD PRIMARY KEY (`promo_id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`review_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `customer_id` (`customer_id`);

--
-- Indexes for table `shops`
--
ALTER TABLE `shops`
  ADD PRIMARY KEY (`shop_id`),
  ADD KEY `trader_id` (`trader_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `violations`
--
ALTER TABLE `violations`
  ADD PRIMARY KEY (`violation_id`),
  ADD KEY `idx_reported_user_id` (`reported_user_id`),
  ADD KEY `idx_reporter_id` (`reporter_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `cart_items`
--
ALTER TABLE `cart_items`
  MODIFY `cart_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `order_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `product_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `promo_codes`
--
ALTER TABLE `promo_codes`
  MODIFY `promo_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `reviews`
--
ALTER TABLE `reviews`
  MODIFY `review_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `shops`
--
ALTER TABLE `shops`
  MODIFY `shop_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `violations`
--
ALTER TABLE `violations`
  MODIFY `violation_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `cart_items`
--
ALTER TABLE `cart_items`
  ADD CONSTRAINT `cart_items_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cart_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`),
  ADD CONSTRAINT `order_items_ibfk_3` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`shop_id`);

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`shop_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `products_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`);

--
-- Constraints for table `reviews`
--
ALTER TABLE `reviews`
  ADD CONSTRAINT `reviews_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reviews_ibfk_2` FOREIGN KEY (`customer_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `shops`
--
ALTER TABLE `shops`
  ADD CONSTRAINT `shops_ibfk_1` FOREIGN KEY (`trader_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `violations`
--
ALTER TABLE `violations`
  ADD CONSTRAINT `violations_ibfk_1` FOREIGN KEY (`reported_user_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `violations_ibfk_2` FOREIGN KEY (`reporter_id`) REFERENCES `users` (`user_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
