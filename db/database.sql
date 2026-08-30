-- Create and reset the database automatically
CREATE DATABASE IF NOT EXISTS `waterbottle_shop`;
USE `waterbottle_shop`;

-- Drop existing tables in reverse order of dependencies
-- Drop existing tables in reverse order of dependencies
DROP TABLE IF EXISTS `password_reset_otp`;
DROP TABLE IF EXISTS `wishlist`;
DROP TABLE IF EXISTS `user_addresses`;
DROP TABLE IF EXISTS `order_feedback`;
DROP TABLE IF EXISTS `order_items`;
DROP TABLE IF EXISTS `orders`;
DROP TABLE IF EXISTS `cart_items`;
DROP TABLE IF EXISTS `carts`;
DROP TABLE IF EXISTS `product_images`;
DROP TABLE IF EXISTS `product_variants`;
DROP TABLE IF EXISTS `products`;
DROP TABLE IF EXISTS `categories`;
DROP TABLE IF EXISTS `user_permissions`;
DROP TABLE IF EXISTS `role_permissions`;
DROP TABLE IF EXISTS `stores`;
DROP TABLE IF EXISTS `chat_messages`;
DROP TABLE IF EXISTS `chat_sessions`;
DROP TABLE IF EXISTS `roles`;
DROP TABLE IF EXISTS `users`;

-- =========================================================
-- 1. Table structure for table `categories`
-- =========================================================
CREATE TABLE `categories` (
  `category_id` INT(11) NOT NULL AUTO_INCREMENT,
  `category_name` VARCHAR(100) NOT NULL UNIQUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================================================
-- 2. Table structure for table `products`
-- =========================================================
CREATE TABLE `products` (
  `product_id` INT(11) NOT NULL AUTO_INCREMENT,
  `category_id` INT(11) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `description` TEXT,
  `price` DECIMAL(10,2) NOT NULL,
  `image_url` VARCHAR(255),
  `video_url` VARCHAR(500) DEFAULT NULL,
  `status` ENUM('active', 'archived') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`product_id`),
  CONSTRAINT `fk_product_category` FOREIGN KEY (`category_id`) 
    REFERENCES `categories`(`category_id`) 
    ON DELETE RESTRICT 
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================================================
-- 2b. Additional product-gallery images
-- =========================================================
CREATE TABLE `product_images` (
  `image_id` INT(11) NOT NULL AUTO_INCREMENT,
  `product_id` INT(11) NOT NULL,
  `image_url` VARCHAR(255) NOT NULL,
  `sort_order` INT(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`image_id`),
  CONSTRAINT `fk_product_image_product` FOREIGN KEY (`product_id`)
    REFERENCES `products`(`product_id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================================================
-- 3. Table structure for table `product_variants`
-- =========================================================
CREATE TABLE `product_variants` (
  `variant_id` INT(11) NOT NULL AUTO_INCREMENT,
  `product_id` INT(11) NOT NULL,
  `size` VARCHAR(50) NOT NULL,
  `colour` VARCHAR(50) DEFAULT 'Standard',
  `stock` INT(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`variant_id`),
  CONSTRAINT `fk_variant_product` FOREIGN KEY (`product_id`) 
    REFERENCES `products`(`product_id`) 
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================================================
-- 4. Table structure for table `users`
-- =========================================================
CREATE TABLE `users` (
  `user_id` INT(11) NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `role` VARCHAR(50) NOT NULL DEFAULT 'member',
  `profilepic` VARCHAR(255) DEFAULT NULL,
  `reward_points` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `failed_attempts` INT NOT NULL DEFAULT 0,
  `locked_until` DATETIME DEFAULT NULL,
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================================================
-- 5. Table structure for table `carts`
-- =========================================================
CREATE TABLE `carts` (
  `cart_id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`cart_id`),
  CONSTRAINT `fk_cart_user` FOREIGN KEY (`user_id`) 
    REFERENCES `users`(`user_id`) 
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================================================
-- 6. Table structure for table `cart_items`
-- =========================================================
CREATE TABLE `cart_items` (
  `cart_item_id` INT(11) NOT NULL AUTO_INCREMENT,
  `cart_id` INT(11) NOT NULL,
  `product_id` INT(11) NOT NULL,
  `size` VARCHAR(50) NOT NULL DEFAULT 'Medium (18oz / 530ml)',
  `quantity` INT(11) NOT NULL DEFAULT 1,
  `added_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`cart_item_id`),
  CONSTRAINT `fk_cart_item_cart` FOREIGN KEY (`cart_id`) 
    REFERENCES `carts`(`cart_id`) 
    ON DELETE CASCADE,
  CONSTRAINT `fk_cart_item_product` FOREIGN KEY (`product_id`) 
    REFERENCES `products`(`product_id`) 
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================================================
-- 7. Table structure for table `orders`
-- =========================================================
CREATE TABLE `orders` (
  `order_id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `order_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `shipped_at` TIMESTAMP NULL DEFAULT NULL,
  `completed_at` TIMESTAMP NULL DEFAULT NULL,
  `cancelled_at` TIMESTAMP NULL DEFAULT NULL,
  `total_amount` DECIMAL(10, 2) NOT NULL,
  `subtotal_amount` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
  `points_used` INT UNSIGNED NOT NULL DEFAULT 0,
  `points_discount` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
  `points_earned` INT UNSIGNED NOT NULL DEFAULT 0,
  `status` ENUM('pending', 'shipped', 'completed', 'cancelled') NOT NULL DEFAULT 'pending',
  `recipient_name` VARCHAR(100) NOT NULL,
  `shipping_address` TEXT NOT NULL,
  `phone_number` VARCHAR(20) NOT NULL,
  `address_updated` TINYINT(1) NOT NULL DEFAULT 0,
  `payment_method` VARCHAR(30) NOT NULL DEFAULT 'cash_on_delivery',
  `payment_reference` VARCHAR(100) DEFAULT NULL,
  `payment_status` ENUM('pending', 'paid') NOT NULL DEFAULT 'pending',
  PRIMARY KEY (`order_id`),
  CONSTRAINT `fk_order_user` FOREIGN KEY (`user_id`) 
    REFERENCES `users`(`user_id`) 
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================================================
-- 8. Table structure for table `order_items`
-- =========================================================
CREATE TABLE `order_items` (
  `order_item_id` INT(11) NOT NULL AUTO_INCREMENT,
  `order_id` INT(11) NOT NULL,
  `product_id` INT(11) NOT NULL,
  `size` VARCHAR(50) NOT NULL,
  `quantity` INT(11) NOT NULL,
  `price` DECIMAL(10, 2) NOT NULL,
  PRIMARY KEY (`order_item_id`),
  CONSTRAINT `fk_order_item_order` FOREIGN KEY (`order_id`) 
    REFERENCES `orders`(`order_id`) 
    ON DELETE CASCADE,
  CONSTRAINT `fk_order_item_product` FOREIGN KEY (`product_id`) 
    REFERENCES `products`(`product_id`) 
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================================================
-- Table structure for table `order_feedback`
-- =========================================================
CREATE TABLE `order_feedback` (
  `feedback_id` INT(11) NOT NULL AUTO_INCREMENT,
  `order_id` INT(11) NOT NULL,
  `user_id` INT(11) NOT NULL,
  `rating` TINYINT NOT NULL,
  `feedback` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
      ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`feedback_id`),

  UNIQUE KEY `unique_order_feedback` (`order_id`, `user_id`),

  FOREIGN KEY (`order_id`)
      REFERENCES `orders`(`order_id`)
      ON DELETE CASCADE,

  FOREIGN KEY (`user_id`)
      REFERENCES `users`(`user_id`)
      ON DELETE CASCADE,

  CHECK (`rating` BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================================================
-- 9. Table structure for table `user_addresses`
-- =========================================================
CREATE TABLE `user_addresses` (
  `address_id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `address_label` VARCHAR(50) NOT NULL DEFAULT 'Home',
  `recipient_name` VARCHAR(100) NOT NULL,
  `phone_number` VARCHAR(20) NOT NULL,
  `address_text` TEXT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`address_id`),
  CONSTRAINT `fk_address_user` FOREIGN KEY (`user_id`) 
    REFERENCES `users`(`user_id`) 
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================================================
-- 10. Admin create new admin role
-- =========================================================
CREATE TABLE IF NOT EXISTS roles (
  `role_id` INT AUTO_INCREMENT PRIMARY KEY,
  `role_name` VARCHAR(50) NOT NULL UNIQUE,
  `description` VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- =========================================================
-- 11. Different role will access to different page
-- =========================================================
CREATE TABLE IF NOT EXISTS role_permissions (
  `role_id` INT NOT NULL,
  `page_slug` VARCHAR(100) NOT NULL,
  PRIMARY KEY (role_id, page_slug),
  FOREIGN KEY (role_id) REFERENCES roles(role_id) ON DELETE CASCADE
);

-- =========================================================
-- 12. Set page access for each role
-- =========================================================
CREATE TABLE user_permissions (
  `permission_id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `page_slug` VARCHAR(100) NOT NULL,
  FOREIGN KEY (user_id)REFERENCES users(user_id) ON DELETE CASCADE
);

-- =========================================================
-- 13. Store Location
-- =========================================================
CREATE TABLE stores (
  `store_id` INT AUTO_INCREMENT PRIMARY KEY,
  `store_name` VARCHAR(100) NOT NULL,
  `state` VARCHAR(50) NOT NULL,
  `address` VARCHAR(255) NOT NULL,
  `opening_hours` VARCHAR(100) NOT NULL,
  `contact` VARCHAR(30) NOT NULL,
  `map_query` VARCHAR(255) NOT NULL,
  `store_image` VARCHAR(255) DEFAULT NULL,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);


-- =========================================================
-- 14. Live Chat
-- =========================================================
CREATE TABLE IF NOT EXISTS chat_sessions (
    chat_session_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    status INT NOT NULL DEFAULT 1, -- 0: closed, 1: active, 2: resolved
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS chat_messages (
    chat_message_id INT AUTO_INCREMENT PRIMARY KEY,
    chat_session_id INT NOT NULL,
    user_id INT NOT NULL,
    message VARCHAR(1000) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (chat_session_id) REFERENCES chat_sessions(chat_session_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- =========================================================
-- 15. Wishlist
-- =========================================================
CREATE TABLE wishlist (
    wishlist_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    variant_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY unique_wishlist (user_id, product_id, variant_id),

    FOREIGN KEY (user_id) REFERENCES users(user_id),
    FOREIGN KEY (product_id) REFERENCES products(product_id),
    FOREIGN KEY (variant_id) REFERENCES product_variants(variant_id)
);

-- =========================================================
-- 16. Reset Password
-- =========================================================
CREATE TABLE password_reset_otp (
    reset_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    otp_code VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    verified TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id)
);

-- =========================================================
-- SAMPLE DATA INSERTIONS
-- =========================================================
INSERT INTO `users` (`user_id`, `username`, `email`, `password`, `role`, `profilepic`, `reward_points`) VALUES
(1, 'daniel', 'daniel@example.com', '$2y$10$3ovrxmv7E7.IvYUnOUJ5teH/nkI6Rg.3eKhVXhMPsIerWwD0tHt6q', 'admin', '1.jpeg', 0),
(2, 'admin', 'admin@example.com', '$2y$10$vwPjuG9/msNZkRsvsjqzBO7ItAcrGbIa75oPYfRNPz337CXA23VL2', 'admin', '2.jpeg', 0),
(3, 'John', 'john@example.com', '$2y$10$JtoR6U8QJ4faEJarm2IJgOjacO/rV6faPFI/FvOlZvpQZlarYNMLO', 'member', '3.jpeg', 500),
(4, 'Jane', 'jane@example.com', '$2y$10$23mamuogsv2sta9f1T0kIeHVraQcAvJfs5zLZidkRQkrMCxMr6Hwa', 'member', '4.jpeg', 250),
(5, 'Ali', 'ali@example.com', '$2y$10$jq85KwxEwRJ18ZVcJ9DE7uDi0eOGTSLqYtMdvNg0GKaix1sHEj5yu', 'member', '5.jpeg', 0);

INSERT INTO `user_addresses` (`user_id`, `address_label`, `recipient_name`, `phone_number`, `address_text`) VALUES
(3, 'Home (Default)', 'John Tan', '012-3456789', '123, Jalan Sultan Ismail, Bukit Bintang, 50250 Kuala Lumpur'),
(3, 'Office HQ', 'John Tan (Corp)', '013-9876543', 'Level 45, Tower 2, Petronas Twin Towers, KLCC, 50088 Kuala Lumpur'),
(4, 'Home (Default)', 'Jane Lee', '012-3456789', '88, Jalan Ampang, Kuala Lumpur'),
(4, 'Office HQ', 'Jane Lee (Corp)', '013-9876543', 'Level 45, Tower 2, Petronas Twin Towers, KLCC, 50088 Kuala Lumpur'); 

INSERT INTO `categories` (`category_name`) VALUES
('Hydration Bottles'),
('Tumblers & Coffee Cups'),
('Kids Bottles'),
('Bottle Care & Accessories');

-- Curated product catalogue - fictional product names and locally generated images.
INSERT INTO `products` (`product_id`, `category_id`, `name`, `description`, `price`, `image_url`, `video_url`) VALUES
(1, 1, 'Nova Everyday Bottle 530ml', 'A lightweight insulated bottle for daily commutes, classes and desk hydration.', 79.00, 'img/products/nova-everyday-coral.png',NULL),
(2, 1, 'Nova Active Bottle 750ml', 'A larger leakproof bottle designed for gym sessions and long days out.', 89.00, 'img/products/nova-active-navy.png',NULL),
(4, 2, 'Halo Straw Tumbler 590ml', 'A double-wall tumbler with reusable straw for iced drinks and everyday sipping.', 69.00, 'img/products/halo-straw-sage.png',NULL),
(5, 2, 'Halo Coffee Tumbler 450ml', 'A compact insulated tumbler with a secure lid for coffee, tea and cocoa.', 65.00, 'img/products/halo-coffee-terracotta.png',NULL),
(7, 3, 'Little Sip Bottle 350ml', 'A child-friendly bottle with an easy flip straw and comfortable carry loop.', 45.00, 'img/products/little-sip-yellow.png',NULL),
(10, 4, 'Silicone Bottle Boot', 'A protective silicone base that helps reduce dents and adds grip.', 15.00, 'img/products/silicone-boot-charcoal.png',NULL),
(11, 4, 'Hydro Exclusive Bottle', 'A bright bottle.', 88.00, 'img/products/hydro-exclusive.png', 'https://youtube.com/shorts/x4GBAmr4EBc?si=OQzV_uXDeyWzfS6K');

-- Each product has a primary card image plus an alternative gallery angle.
INSERT INTO `product_images` (`product_id`, `image_url`, `sort_order`) VALUES
(1, 'img/products/nova-everyday-coral.png', 1),
(1, 'img/products/nova-everyday-coral-side.png', 2),
(2, 'img/products/nova-active-navy.png', 1),
(2, 'img/products/nova-active-navy-side.png', 2),
(4, 'img/products/halo-straw-sage.png', 1),
(4, 'img/products/halo-straw-sage-side.png', 2),
(5, 'img/products/halo-coffee-terracotta.png', 1),
(5, 'img/products/halo-coffee-terracotta-side.png', 2),
(7, 'img/products/little-sip-yellow.png', 1),
(7, 'img/products/little-sip-yellow-side.png', 2),
(10, 'img/products/silicone-boot-charcoal.png', 1),
(10, 'img/products/silicone-boot-charcoal-side.png', 2);

INSERT INTO `product_variants` (`product_id`, `size`, `colour`, `stock`) VALUES
(1, 'Medium (18oz / 530ml)', 'Coral Pink', 27),
(2, 'Mega (32oz / 950ml)', 'Coral Pink', 12),
(4, 'Medium (18oz / 530ml)', 'Sage Green', 17),
(5, 'Medium (18oz / 530ml)', 'Sage Green', 14),
(7, 'Micro (12oz / 350ml)', 'Sunny Yellow', 25),
(10, 'Medium (18oz / 530ml)', 'Charcoal', 40),
(11, 'Mega (32oz / 950ml)', 'Blue', 40);

-- Demo orders make the Top Selling section meaningful immediately after import.
INSERT INTO `orders` (`order_id`, `user_id`, `order_date`, `shipped_at`, `completed_at`, `cancelled_at`, `total_amount`, `subtotal_amount`, `points_used`, `points_discount`, `points_earned`, `status`, `recipient_name`, `shipping_address`, `phone_number`, `payment_method`, `payment_reference`, `payment_status`) VALUES
(1, 3, '2026-07-20 10:15:00', '2026-07-21 11:20:00', '2026-07-23 15:40:00', NULL, 189.60, 189.60, 0, 0.00, 180, 'completed', 'John Tan', '123, Jalan Sultan Ismail, Kuala Lumpur', '012-3456789', 'online_banking', 'DEMO-JOHN-001', 'paid'),
(2, 4, '2026-07-22 14:30:00', '2026-07-23 10:15:00', NULL, NULL, 165.60, 165.60, 0, 0.00, 160, 'shipped', 'Jane Lee', '88, Jalan Ampang, Kuala Lumpur', '013-9876543', 'ewallet', 'DEMO-JANE-002', 'paid'),
(3, 3, '2026-07-25 09:45:00', '2026-07-26 13:10:00', '2026-07-28 16:35:00', NULL, 177.60, 177.60, 0, 0.00, 170, 'completed', 'John Tan', '123, Jalan Sultan Ismail, Kuala Lumpur', '012-3456789', 'credit_debit_card', 'DEMO-4321', 'paid'),
(4, 4, '2026-07-29 16:00:00', NULL, NULL, NULL, 124.60, 124.60, 0, 0.00, 120, 'pending', 'Jane Lee', '88, Jalan Ampang, Kuala Lumpur', '013-9876543', 'cash_on_delivery', NULL, 'pending');

INSERT INTO `order_items` (`order_id`, `product_id`, `size`, `quantity`, `price`) VALUES
(1, 1, 'Medium (18oz / 530ml)', 2, 94.80),
(2, 4, 'Medium (18oz / 530ml)', 2, 82.80),
(3, 1, 'Medium (18oz / 530ml)', 1, 94.80),
(3, 4, 'Medium (18oz / 530ml)', 1, 82.80),
(4, 2, 'Mega (32oz / 950ml)', 1, 124.60);

INSERT INTO `stores` (`store_id`, `store_name`, `state`, `address`, `opening_hours`, `contact`, `map_query`, `store_image`, `status`, `created_at`) VALUES 
(1, 'Sippy Go KLCC, Kuala Lumpur', 'Kuala Lumpur', 'Suria KLCC, Kuala Lumpur', '10 - 10', '03-889 9741', 'Suria KLCC, Kuala Lumpur', 'store_1787811914_b2536965.png', 'active', '2026-08-27 11:41:51'), 
(2, 'Sippy Go Sunway Pyramid', 'Selangor', 'Darul Ehsan, Level CP6, Blue Atrium, 3, Jalan PJS 11/15, Bandar Sunway, 47500 Petaling Jaya, Selangor', '10 - 10', '03 779 8125', 'Sunway Pyramid, Selangor', 'store_1787812087_8fa5a0d3.png', 'active', '2026-08-27 14:26:50');

-- Prefilled chat data
INSERT INTO `chat_sessions` (`chat_session_id`, `user_id`, `status`, `created_at`) VALUES
(1, 3, 2, '2026-08-13 14:58:00'),
(2, 4, 0, '2026-08-16 10:15:00'),
(3, 5, 1, '2026-08-19 09:30:00'),
(4, 3, 1, '2026-08-20 11:44:00');

INSERT INTO `chat_messages` (`chat_session_id`, `user_id`, `created_at`, `message`)
SELECT
    cs.chat_session_id, 2, cs.created_at,
    CONCAT(
        'Hi! 👋 Welcome! This is an automatically generated message.',
        CHAR(10), CHAR(10),
        'Thank you for starting a chat with us. Before a staff member can assist you, ',
        'please send us a message with your question, request, or any details you would like to share.',
        CHAR(10), CHAR(10),
        'Once we receive your message, a staff member will review it and get back to you as soon as possible.',
        CHAR(10), CHAR(10),
        'We look forward to hearing from you!'
    )
FROM `chat_sessions` cs;


INSERT INTO `chat_messages` (`chat_session_id`, `user_id`, `message`, `created_at`) VALUES
(1, 3, 'Hi! I have a question about my order.', '2026-08-13 14:59:00'),
(1, 2, 'Sure! Please provide your order ID.', '2026-08-13 15:00:00'),
(1, 3, 'My order ID is #12345.', '2026-08-13 15:01:00'),
(1, 2, 'Thank you! Let me check the status for you.', '2026-08-13 15:02:00'),

(2, 4, 'Hi! I need help with a product.', '2026-08-16 10:16:00'),
(2, 2, 'Of course! What product are you referring to?', '2026-08-16 10:17:00'),
(2, 4, 'I am looking for the Nova Active Bottle.', '2026-08-16 10:18:00'),
(2, 2, 'Great choice! How can I assist you with that?', '2026-08-16 10:19:00'),

(3, 5, 'Hi! I have a question about my account.', '2026-08-19 09:31:00'),
(3, 2, 'Sure! Please provide your account details.', '2026-08-19 09:32:00'),
(3, 5, 'My username is "Ali".', '2026-08-19 09:33:00'),
(3, 2, 'Thank you! Let me check your account information.', '2026-08-19 09:34:00'),

(4, 3, 'Hi! I need help with a product return.', '2026-08-20 11:46:00'),
(4, 2, 'Of course! Can you provide the order ID for the return?', '2026-08-20 11:47:00'),
(4, 3, 'My order ID is #67890.', '2026-08-20 11:48:00'),
(4, 2, 'Thank you! Let me guide you through the return process.', '2026-08-20 11:49:00');