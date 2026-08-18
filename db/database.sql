-- Create and reset the database automatically
CREATE DATABASE IF NOT EXISTS `waterbottle_shop`;
USE `waterbottle_shop`;

-- Drop existing tables in reverse order of dependencies
DROP TABLE IF EXISTS `user_addresses`; 
DROP TABLE IF EXISTS `order_feedback`;
DROP TABLE IF EXISTS `order_items`;
DROP TABLE IF EXISTS `orders`;
DROP TABLE IF EXISTS `cart_items`;
DROP TABLE IF EXISTS `carts`;
DROP TABLE IF EXISTS `product_variants`;  
DROP TABLE IF EXISTS `products`;
DROP TABLE IF EXISTS `categories`;
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
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`product_id`),
  CONSTRAINT `fk_product_category` FOREIGN KEY (`category_id`) 
    REFERENCES `categories`(`category_id`) 
    ON DELETE RESTRICT 
    ON UPDATE CASCADE
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
  `role` ENUM('admin', 'member') NOT NULL DEFAULT 'member',
  `profilepic` VARCHAR(255) DEFAULT NULL,
  `reward_points` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
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
  `total_amount` DECIMAL(10, 2) NOT NULL,
  `subtotal_amount` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
  `points_used` INT UNSIGNED NOT NULL DEFAULT 0,
  `points_discount` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
  `points_earned` INT UNSIGNED NOT NULL DEFAULT 0,
  `status` ENUM('pending', 'shipped', 'completed', 'cancelled') NOT NULL DEFAULT 'pending',
  `recipient_name` VARCHAR(100) NOT NULL,
  `shipping_address` TEXT NOT NULL,
  `phone_number` VARCHAR(20) NOT NULL,
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
-- SAMPLE DATA INSERTIONS
-- =========================================================
INSERT INTO `users` (`user_id`, `username`, `email`, `password`, `role`, `profilepic`, `reward_points`) VALUES
(1, 'member1', 'member1@example.com', '$2y$10$Qws0vG.ePjJm4X5Z0F6Ym.G7Ym.G7Ym.G7Ym.G7Ym.G7Ym.G7Ym.G7Ym.G7Ym', 'member', NULL, 0),
(2, 'admin', 'admin@example.com', '$2y$10$Qws0vG.ePjJm4X5Z0F6Ym.G7Ym.G7Ym.G7Ym.G7Ym.G7Ym.G7Ym.G7Ym.G7Ym', 'admin', NULL, 0),
(3, 'John', 'john@example.com', '$2y$10$JtoR6U8QJ4faEJarm2IJgOjacO/rV6faPFI/FvOlZvpQZlarYNMLO', 'member', NULL, 500),
(4, 'Jane', 'jane@example.com', '$2y$10$23mamuogsv2sta9f1T0kIeHVraQcAvJfs5zLZidkRQkrMCxMr6Hwa', 'member', NULL, 250),
(5, 'Ali', 'ali@example.com', '$2y$10$23mamuogsv2sta9f1T0kIeHVraQcAvJfs5zLZidkRQkrMCxMr6Hwa', 'member', NULL, 0),
(6, 'admin2', 'admin2@example.com', '$2y$10$vwPjuG9/msNZkRsvsjqzBO7ItAcrGbIa75oPYfRNPz337CXA23VL2', 'admin', NULL, 0);

INSERT INTO `user_addresses` (`user_id`, `address_label`, `recipient_name`, `phone_number`, `address_text`) VALUES
(3, 'Home (Default)', 'Member One', '012-3456789', '123, Jalan Sultan Ismail, Bukit Bintang, 50250 Kuala Lumpur'),
(3, 'Office HQ', 'Member One (Corp)', '013-9876543', 'Level 45, Tower 2, Petronas Twin Towers, KLCC, 50088 Kuala Lumpur'),
(4, 'Home (Default)', 'Member One', '012-3456789', '123, Jalan Sultan Ismail, Bukit Bintang, 50250 Kuala Lumpur'),
(4, 'Office HQ', 'Member One (Corp)', '013-9876543', 'Level 45, Tower 2, Petronas Twin Towers, KLCC, 50088 Kuala Lumpur'); 

INSERT INTO `categories` (`category_name`) VALUES
('Hydration Bottles'),
('Tumblers & Coffee Cups'),
('Kids Bottles'),
('Bottle Care & Accessories');

-- Curated product catalogue - fictional product names and locally generated images.
INSERT INTO `products` (`product_id`, `category_id`, `name`, `description`, `price`, `image_url`) VALUES
(1, 1, 'Nova Everyday Bottle 530ml', 'A lightweight insulated bottle for daily commutes, classes and desk hydration.', 79.00, 'img/products/bottle-coral.png'),
(2, 1, 'Nova Active Bottle 750ml', 'A larger leakproof bottle designed for gym sessions and long days out.', 89.00, 'img/products/bottle-coral.png'),
(3, 1, 'Nova Explorer Bottle 1L', 'High-capacity stainless-steel bottle that keeps drinks cold for outdoor days.', 109.00, 'img/products/bottle-coral.png'),
(4, 2, 'Halo Straw Tumbler 590ml', 'A double-wall tumbler with reusable straw for iced drinks and everyday sipping.', 69.00, 'img/products/tumbler-sage.png'),
(5, 2, 'Halo Coffee Tumbler 450ml', 'A compact insulated tumbler with a secure lid for coffee, tea and cocoa.', 65.00, 'img/products/tumbler-sage.png'),
(6, 2, 'Halo Carry Tumbler 900ml', 'A large handled tumbler made for all-day hydration at work or travel.', 85.00, 'img/products/tumbler-sage.png'),
(7, 3, 'Little Sip Bottle 350ml', 'A child-friendly bottle with an easy flip straw and comfortable carry loop.', 45.00, 'img/products/kids-bottle.png'),
(8, 3, 'Little Sip Dino Bottle 420ml', 'A playful school bottle with a spill-resistant straw lid for younger children.', 49.00, 'img/products/kids-bottle.png'),
(9, 3, 'Little Sip School Bottle 500ml', 'A durable everyday bottle sized for school bags and after-class activities.', 55.00, 'img/products/kids-bottle.png'),
(10, 4, 'Silicone Bottle Boot', 'A protective silicone base that helps reduce dents and adds grip.', 15.00, 'img/products/accessory-kit.png'),
(11, 4, 'Bottle Cleaning Kit', 'A practical three-piece brush set for bottles, lids and reusable straws.', 22.00, 'img/products/accessory-kit.png'),
(12, 4, 'Adjustable Carry Strap', 'A comfortable woven strap with a clip for hands-free bottle carrying.', 18.00, 'img/products/accessory-kit.png');

INSERT INTO `product_variants` (`product_id`, `size`, `colour`, `stock`) VALUES
(1, 'Micro (12oz / 350ml)', 'Coral Pink', 18),
(1, 'Medium (18oz / 530ml)', 'Coral Pink', 27),
(1, 'Mega (32oz / 950ml)', 'Coral Pink', 8),
(2, 'Medium (18oz / 530ml)', 'Coral Pink', 20),
(2, 'Mega (32oz / 950ml)', 'Coral Pink', 12),
(3, 'Mega (32oz / 950ml)', 'Coral Pink', 15),
(4, 'Medium (18oz / 530ml)', 'Sage Green', 17),
(4, 'Mega (32oz / 950ml)', 'Sage Green', 10),
(5, 'Micro (12oz / 350ml)', 'Sage Green', 18),
(5, 'Medium (18oz / 530ml)', 'Sage Green', 14),
(6, 'Mega (32oz / 950ml)', 'Sage Green', 16),
(7, 'Micro (12oz / 350ml)', 'Sunny Yellow', 25),
(8, 'Micro (12oz / 350ml)', 'Sky Blue', 16),
(9, 'Mini (15oz / 450ml)', 'Sky Blue', 20),
(10, 'Medium (18oz / 530ml)', 'Charcoal', 40),
(11, 'Medium (18oz / 530ml)', 'Natural', 35),
(12, 'Medium (18oz / 530ml)', 'Sand', 30);

-- Demo orders make the Top Selling section meaningful immediately after import.
INSERT INTO `orders` (`order_id`, `user_id`, `order_date`, `total_amount`, `subtotal_amount`, `points_used`, `points_discount`, `points_earned`, `status`, `recipient_name`, `shipping_address`, `phone_number`, `payment_method`, `payment_reference`, `payment_status`) VALUES
(1, 3, '2026-07-20 10:15:00', 189.60, 189.60, 0, 0.00, 180, 'completed', 'John Tan', '123, Jalan Sultan Ismail, Kuala Lumpur', '012-3456789', 'online_banking', 'DEMO-JOHN-001', 'paid'),
(2, 4, '2026-07-22 14:30:00', 165.60, 165.60, 0, 0.00, 160, 'shipped', 'Jane Lee', '88, Jalan Ampang, Kuala Lumpur', '013-9876543', 'ewallet', 'DEMO-JANE-002', 'paid'),
(3, 3, '2026-07-25 09:45:00', 177.60, 177.60, 0, 0.00, 170, 'completed', 'John Tan', '123, Jalan Sultan Ismail, Kuala Lumpur', '012-3456789', 'credit_debit_card', 'DEMO-4321', 'paid'),
(4, 4, '2026-07-29 16:00:00', 124.60, 124.60, 0, 0.00, 120, 'pending', 'Jane Lee', '88, Jalan Ampang, Kuala Lumpur', '013-9876543', 'cash_on_delivery', NULL, 'pending');

INSERT INTO `order_items` (`order_id`, `product_id`, `size`, `quantity`, `price`) VALUES
(1, 1, 'Medium (18oz / 530ml)', 2, 94.80),
(2, 4, 'Medium (18oz / 530ml)', 2, 82.80),
(3, 1, 'Medium (18oz / 530ml)', 1, 94.80),
(3, 4, 'Medium (18oz / 530ml)', 1, 82.80),
(4, 2, 'Mega (32oz / 950ml)', 1, 124.60);
