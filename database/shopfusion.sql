-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 12, 2026 at 12:01 AM
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
-- Table structure for table `ai_review_analysis_cache`
--

CREATE TABLE `ai_review_analysis_cache` (
  `product_id` int(11) NOT NULL,
  `review_count` int(11) NOT NULL DEFAULT 0,
  `average_rating` decimal(4,2) NOT NULL DEFAULT 0.00,
  `sentiment` varchar(30) NOT NULL DEFAULT 'mixed',
  `summary` text DEFAULT NULL,
  `pros_json` text DEFAULT NULL,
  `cons_json` text DEFAULT NULL,
  `source` varchar(30) NOT NULL DEFAULT 'fallback',
  `model_used` varchar(100) DEFAULT NULL,
  `last_review_at` datetime DEFAULT NULL,
  `raw_response` longtext DEFAULT NULL,
  `generated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ai_review_analysis_cache`
--

INSERT INTO `ai_review_analysis_cache` (`product_id`, `review_count`, `average_rating`, `sentiment`, `summary`, `pros_json`, `cons_json`, `source`, `model_used`, `last_review_at`, `raw_response`, `generated_at`) VALUES
(4, 3, 4.30, 'positive', 'Smart Watch has an average rating of 4.3 from 3 reviews. Customers are mostly positive about the product overall.', '[\"design\",\"delivery\",\"easy to use\"]', '[\"packaging\",\"durability\",\"size\"]', 'fallback', NULL, '2026-03-28 16:15:00', NULL, '2026-04-11 21:50:10'),
(13, 0, 0.00, 'mixed', 'This product does not have enough customer feedback yet for an AI review summary.', '[]', '[]', 'fallback', NULL, NULL, NULL, '2026-04-11 20:13:48'),
(17, 3, 0.00, 'negative', 'Over The Head FRONTECH HF 3448 Wired Multimedia Headphone Dual 3.5mm Jack has an average rating of 0.0 from 3 reviews. Customers are mostly critical about the product overall.', '[\"performance\",\"expectation mismatch\",\"quality\"]', '[\"packaging\",\"durability\",\"size\"]', 'fallback', NULL, '2026-03-30 13:00:00', NULL, '2026-04-11 21:43:22'),
(18, 3, 0.00, 'negative', 'Bluetooth Selfie Sticks R1s with Remote and Selfie Light, 3-in-1 Multifunctional Selfie Stick Tripod has an average rating of 0.0 from 3 reviews. Customers are mostly critical about the product overall.', '[\"value\",\"expectation mismatch\",\"quality\"]', '[\"packaging\",\"durability\",\"size\"]', 'fallback', NULL, '2026-03-27 10:00:00', NULL, '2026-04-11 20:33:46'),
(19, 3, 0.00, 'negative', 'Rock 10000mAh Powerbank ll Magsafe Wireless Powerbank ll Fast Charging PD ll 15W Wireless Charging l has an average rating of 0.0 from 3 reviews. Customers are mostly critical about the product overall.', '[\"quality\",\"performance\",\"value\"]', '[\"packaging\",\"durability\",\"size\"]', 'fallback', NULL, '2026-03-29 10:15:00', NULL, '2026-04-11 20:32:56');

-- --------------------------------------------------------

--
-- Table structure for table `ai_review_moderation`
--

CREATE TABLE `ai_review_moderation` (
  `moderation_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `rating` int(11) DEFAULT NULL,
  `review_text` text DEFAULT NULL,
  `decision` varchar(30) NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `confidence` decimal(4,2) DEFAULT NULL,
  `source` varchar(30) NOT NULL,
  `model_used` varchar(100) DEFAULT NULL,
  `raw_response` longtext DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
(5, 'Home', 'Home and garden items'),
(6, 'Beauty & Personal Care', 'Skincare, cosmetics, and grooming products'),
(7, 'Health & Wellness', 'Healthcare products, supplements, and fitness items'),
(8, 'Toys & Games', 'Toys, board games, and entertainment for kids'),
(9, 'Jewelry & Accessories', 'Fashion jewelry, watches, and accessories'),
(10, 'Furniture', 'Home and office furniture'),
(11, 'Automotive', 'Car accessories, parts, and maintenance items'),
(12, 'Grocery & Food', 'Daily groceries and packaged food'),
(13, 'Baby & Kids', 'Baby products, kids clothing, and accessories'),
(14, 'Office Supplies', 'Stationery, printers, and office equipment'),
(15, 'Music & Instruments', 'Musical instruments and accessories');

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
(2, 7, 2.16, 'pending', 'paypal', NULL, 'jwagal 10 lalitpur', 'pending', NULL, 0.00, 0, 2, '2025-08-23 06:33:07', '2025-08-23 06:33:07'),
(3, 9, 3.78, 'pending', 'paypal', NULL, 'jwagal, 10 lalitpur', 'pending', NULL, 0.00, 0, 3, '2025-08-23 18:28:37', '2025-08-23 18:28:37'),
(4, 7, 12.96, 'pending', 'paypal', NULL, 'Bijuli Bajar, Kathmandu', 'pending', NULL, 0.00, 0, 12, '2025-08-28 17:14:43', '2025-08-28 17:14:43');

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
(3, 2, 12, 3, 1, 2.00, 2.00),
(4, 3, 15, 3, 1, 0.50, 0.50),
(5, 3, 14, 3, 1, 3.00, 3.00),
(6, 4, 18, 1, 1, 2.00, 2.00),
(7, 4, 19, 1, 1, 10.00, 10.00);

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
(12, 3, 1, 'Charger 20w', 'Super fast charger', 2.00, 3, 'uploads/products/1755896247_charger.png', 0.00, 0, 'active', '2025-08-22 20:57:27', '2025-08-23 06:33:07'),
(13, 3, 1, '4 in 1 Fast Charging Cable (Type C/USB A/Lightning) Dual Type C PD 27W 65W Nylon Braided Cable', '4 in 1 Fast Charging Cable (Type C/USB A/Lightning) Dual Type C PD 27W 65W Nylon Braided Cable | Flat Braided i-Phone Charging Cable with Velcro Multi Charging Cable Combo Type C/USB-A Ports', 1.00, 5, 'uploads/products/datacable.jpg', 0.00, 0, 'active', '2025-08-23 16:20:12', '2025-08-23 17:14:20'),
(14, 3, 1, '1.8L Fast Boiling Electric Kettle - Stainless Steel, Easy &amp; Convenient', '1.8L Fast Boiling Electric Kettle - Stainless Steel, Easy &amp; Convenient', 3.00, 4, 'uploads/products/ketly.jpg', 0.00, 0, 'active', '2025-08-23 16:24:11', '2025-08-23 18:28:37'),
(15, 3, 12, 'Dabur Hajmola Regular -120 Tab', '', 0.50, 14, 'uploads/products/hajmola.png', 0.00, 0, 'active', '2025-08-23 16:42:45', '2025-08-23 18:28:37'),
(16, 3, 12, 'Combo Dry Fruits Pack Each of 100 Gm ( Cashew Kaju, Almonds, Walnut, Raisins and Pista)', '', 4.00, 10, 'uploads/products/dryfruit.png', 0.00, 0, 'active', '2025-08-23 16:43:51', '2025-08-23 17:15:56'),
(17, 1, 1, 'Over The Head FRONTECH HF 3448 Wired Multimedia Headphone Dual 3.5mm Jack', '', 3.00, 10, 'uploads/products/headphone.png', 0.00, 0, 'active', '2025-08-23 16:49:23', '2025-08-23 17:16:12'),
(18, 1, 1, 'Bluetooth Selfie Sticks R1s with Remote and Selfie Light, 3-in-1 Multifunctional Selfie Stick Tripod', '', 2.00, 9, 'uploads/products/selfiestick.png', 0.00, 0, 'active', '2025-08-23 16:50:53', '2025-08-28 17:14:43'),
(19, 1, 1, 'Rock 10000mAh Powerbank ll Magsafe Wireless Powerbank ll Fast Charging PD ll 15W Wireless Charging l', '', 10.00, 6, 'uploads/products/powerbank.png', 0.00, 0, 'active', '2025-08-23 16:52:36', '2025-08-28 17:14:43');

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
(3, 9, 'Thulo Pasal', 'Grocery stores', 'active', '2025-08-22 17:27:16'),
(4, 11, 'Romi Bakery', 'Bakery shop', 'active', '2025-08-23 19:14:59');

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
(2, 'trader1', 'trader1@shopfusion.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Thu Electronics', '1234567890', '123 Tech Street', 'trader', 'active', 0, 0, '2025-08-22 07:00:48', '2025-08-23 16:36:21'),
(3, 'trader2', 'trader2@shopfusion.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Queen Boutique', '1234567891', '456 Style Avenue', 'trader', 'active', 0, 0, '2025-08-22 07:00:48', '2025-08-23 16:36:08'),
(4, 'trader3', 'trader3@shopfusion.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Online Kinbech', '1234567892', '789 Game Lane', 'trader', 'pending', 0, 0, '2025-08-22 07:00:48', '2025-08-23 16:35:53'),
(5, 'trader4', 'trader4@shopfusion.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Big Mart', '1234567893', '321 Read Road', 'trader', 'active', 0, 0, '2025-08-22 07:00:48', '2025-08-23 16:35:30'),
(6, 'trader5', 'trader5@shopfusion.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Anil Thapa ', '9814489298', 'Thapathali, Kathmandu ', 'trader', 'active', 0, 0, '2025-08-22 07:00:48', '2025-08-23 16:34:54'),
(7, 'customer1', 'customer1@shopfusion.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Sarah Gurung', '9806684477', 'Bijuli Bajar, Kathmandu', 'customer', 'active', 0, 162066, '2025-08-22 07:00:48', '2025-08-28 17:14:43'),
(8, 'customer2', 'customer2@shopfusion.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Sujal Khadka', '9861040669', 'Bhotahiti, Kathmandu', 'customer', 'active', 0, 100, '2025-08-22 07:00:48', '2025-08-23 16:32:51'),
(9, 'Padam', 'thulopasal567@gmail.com', '$2y$10$Jqyf8vJF1Aiq7uihaKVQM.2d9TDD5.8TtYZ2g1enqs0XRFuuyvWdG', 'Padam Thapa Magar', '9861040699', 'jwagal, 10 lalitpur', 'trader', 'active', 1, 3, '2025-08-22 17:27:16', '2025-08-23 18:28:37'),
(10, 'sagarchaudary', 'sagarchaudary@gamil.com', '$2y$10$GAb0IqxLckiPuX16gBxYFuU9hzSQ2K4HAas8yEtIdVvaJwS93D1wq', 'Romi Bakery', '', '', 'customer', 'active', 0, 0, '2025-08-23 19:12:52', '2025-08-23 19:12:52'),
(11, 'sagarchaudary1', 'sagar567@gamil.com', '$2y$10$FfO/9p61s0EKG/sAwrXyz.43W4rW0Wm8b1Exta1jeTO0Rdtcr2d/i', 'Sagar Chaudary', '9115848205', 'jwagal, 10 lalitpur', 'trader', 'pending', 0, 0, '2025-08-23 19:14:59', '2025-08-23 19:14:59');

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
-- Indexes for table `ai_review_analysis_cache`
--
ALTER TABLE `ai_review_analysis_cache`
  ADD PRIMARY KEY (`product_id`);

--
-- Indexes for table `ai_review_moderation`
--
ALTER TABLE `ai_review_moderation`
  ADD PRIMARY KEY (`moderation_id`),
  ADD KEY `idx_product_created` (`product_id`,`created_at`);

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
-- AUTO_INCREMENT for table `ai_review_moderation`
--
ALTER TABLE `ai_review_moderation`
  MODIFY `moderation_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cart_items`
--
ALTER TABLE `cart_items`
  MODIFY `cart_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `order_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `product_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

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
  MODIFY `shop_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

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
