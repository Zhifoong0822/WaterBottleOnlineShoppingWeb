-- Create and reset the database automatically
CREATE DATABASE IF NOT EXISTS `waterbottle_shop`;
USE `waterbottle_shop`;

-- Drop existing tables in reverse order of dependencies to avoid constraint blocks
-- Added user_addresses here at the very top since it depends on users!
DROP TABLE IF EXISTS `user_addresses`; 
DROP TABLE IF EXISTS `order_items`;
DROP TABLE IF EXISTS `orders`;
DROP TABLE IF EXISTS `cart_items`;
DROP TABLE IF EXISTS `carts`;
DROP TABLE IF EXISTS `products`;
DROP TABLE IF EXISTS `categories`;
DROP TABLE IF EXISTS `users`;

-- =========================================================
-- Table structure for table `categories` (New Structure)
-- =========================================================
CREATE TABLE `categories` (
  `category_id` INT(11) NOT NULL AUTO_INCREMENT,
  `category_name` VARCHAR(100) NOT NULL UNIQUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================================================
-- Table structure for table `products` (Updated with category_id)
-- =========================================================
CREATE TABLE `products` (
  `product_id` INT(11) NOT NULL AUTO_INCREMENT,
  `category_id` INT(11) DEFAULT NULL,
  `name` VARCHAR(255) NOT NULL,
  `description` TEXT,
  `price` DECIMAL(10, 2) NOT NULL,
  `stock` INT(11) NOT NULL DEFAULT '0',
  `image_url` VARCHAR(255),
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`product_id`),
  FOREIGN KEY (`category_id`) REFERENCES `categories`(`category_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================================================
-- Table structure for table `users`
-- =========================================================
CREATE TABLE `users` (
  `user_id` INT(11) NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('admin', 'member') NOT NULL DEFAULT 'member',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================================================
-- Table structure for table `carts`
-- =========================================================
CREATE TABLE `carts` (
  `cart_id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`cart_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================================================
-- Table structure for table `cart_items` (Updated with size)
-- =========================================================
CREATE TABLE `cart_items` (
  `cart_item_id` INT(11) NOT NULL AUTO_INCREMENT,
  `cart_id` INT(11) NOT NULL,
  `product_id` INT(11) NOT NULL,
  `size` VARCHAR(50) NOT NULL DEFAULT 'Medium (18oz / 530ml)',
  `quantity` INT(11) NOT NULL DEFAULT '1',
  `added_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`cart_item_id`),
  FOREIGN KEY (`cart_id`) REFERENCES `carts`(`cart_id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`product_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================================================
-- Table structure for table `orders`
-- =========================================================
CREATE TABLE `orders` (
  `order_id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `order_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `total_amount` DECIMAL(10, 2) NOT NULL,
  `status` ENUM('pending', 'completed', 'cancelled') NOT NULL DEFAULT 'pending',
  `recipient_name` VARCHAR(100) NOT NULL,       
  `shipping_address` TEXT NOT NULL,             
  `phone_number` VARCHAR(20) NOT NULL,          
  PRIMARY KEY (`order_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================================================
-- Table structure for table `order_items` (Updated with size)
-- =========================================================
CREATE TABLE `order_items` (
  `order_item_id` INT(11) NOT NULL AUTO_INCREMENT,
  `order_id` INT(11) NOT NULL,
  `product_id` INT(11) NOT NULL,
  `size` VARCHAR(50) NOT NULL,
  `quantity` INT(11) NOT NULL,
  `price` DECIMAL(10, 2) NOT NULL,
  PRIMARY KEY (`order_item_id`),
  FOREIGN KEY (`order_id`) REFERENCES `orders`(`order_id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`product_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================================================
-- Table structure for table `user_addresses` (New Profile Matrix)
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
  FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================================================
-- Sample Data Insertions (Forced ID Assignment for Session Stability)
-- =========================================================
INSERT INTO `users` (`user_id`, `username`, `email`, `password`, `role`) VALUES
(1, "member1", "member1@example.com", "$2y$10$Qws0vG.ePjJm4X5Z0F6Ym.G7Ym.G7Ym.G7Ym.G7Ym.G7Ym.G7Ym.G7Ym.G7Ym", "member"),
(2, "admin", "admin@example.com", "$2y$10$Qws0vG.ePjJm4X5Z0F6Ym.G7Ym.G7Ym.G7Ym.G7Ym.G7Ym.G7Ym.G7Ym.G7Ym", "admin");

-- Inject seed profiles for user_id = 1 safely
INSERT INTO `user_addresses` (`user_id`, `address_label`, `recipient_name`, `phone_number`, `address_text`) VALUES
(1, 'Home (Default)', 'Member One', '012-3456789', '123, Jalan Sultan Ismail, Bukit Bintang, 50250 Kuala Lumpur'),
(1, 'Office HQ', 'Member One (Corp)', '013-9876543', 'Level 45, Tower 2, Petronas Twin Towers, KLCC, 50088 Kuala Lumpur'); 

-- Insert Montigo-style Categories
INSERT INTO `categories` (`category_name`) VALUES 
("Ace Bottle Series"), 
("Sense Cup Series"),
("Knight Tumbler Series");

-- Insert Products associated with Categories
INSERT INTO `products` (`category_id`, `name`, `description`, `price`, `stock`, `image_url`) VALUES
(1, "Ace Active Bottle", "A stylish, heavy-duty insulated water bottle built for sports.", 15.99, 100, "https://images.unsplash.com/photo-1602143407151-7111542de6e8?w=500&auto=format&fit=crop"),
(2, "Sense Coffee Cup", "Insulated travel cup designed to keep your drinks hot for 12 hours.", 22.50, 75, "https://images.unsplash.com/photo-1523362628745-0c100150b504?w=500&auto=format&fit=crop"),
(1, "Ace Mega Explorer", "Massive container capacity for ultra endurance trips.", 12.00, 120, "https://images.unsplash.com/photo-1592892111425-15e04305f961?w=500&auto=format&fit=crop");