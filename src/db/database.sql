-- Create and reset the database automatically
CREATE DATABASE IF NOT EXISTS `waterbottle_shop`;
USE `waterbottle_shop`;

-- Drop existing tables in reverse order of dependencies to avoid constraint blocks
DROP TABLE IF EXISTS `order_items`;
DROP TABLE IF EXISTS `orders`;
DROP TABLE IF EXISTS `cart_items`;
DROP TABLE IF EXISTS `carts`;
DROP TABLE IF EXISTS `products`;
DROP TABLE IF EXISTS `users`;

-- =========================================================
-- Table structure for table `products`
-- =========================================================
CREATE TABLE `products` (
  `product_id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `description` TEXT,
  `price` DECIMAL(10, 2) NOT NULL,
  `stock` INT(11) NOT NULL DEFAULT '0',
  `image_url` VARCHAR(255),
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`product_id`)
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
-- Table structure for table `cart_items`
-- =========================================================
CREATE TABLE `cart_items` (
  `cart_item_id` INT(11) NOT NULL AUTO_INCREMENT,
  `cart_id` INT(11) NOT NULL,
  `product_id` INT(11) NOT NULL,
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
  PRIMARY KEY (`order_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================================================
-- Table structure for table `order_items`
-- =========================================================
CREATE TABLE `order_items` (
  `order_item_id` INT(11) NOT NULL AUTO_INCREMENT,
  `order_id` INT(11) NOT NULL,
  `product_id` INT(11) NOT NULL,
  `quantity` INT(11) NOT NULL,
  `price` DECIMAL(10, 2) NOT NULL,
  PRIMARY KEY (`order_item_id`),
  FOREIGN KEY (`order_id`) REFERENCES `orders`(`order_id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`product_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================================================
-- Sample Data Insertions
-- =========================================================
INSERT INTO `users` (`username`, `email`, `password`, `role`) VALUES
("admin", "admin@example.com", "$2y$10$Qws0vG.ePjJm4X5Z0F6Ym.G7Ym.G7Ym.G7Ym.G7Ym.G7Ym.G7Ym.G7Ym.G7Ym", "admin"),
("member1", "member1@example.com", "$2y$10$Qws0vG.ePjJm4X5Z0F6Ym.G7Ym.G7Ym.G7Ym.G7Ym.G7Ym.G7Ym.G7Ym.G7Ym", "member");

INSERT INTO `products` (`name`, `description`, `price`, `stock`, `image_url`) VALUES
("Water Bottle 1", "A stylish and durable water bottle.", 15.99, 100, "https://images.unsplash.com/photo-1602143407151-7111542de6e8?w=500&auto=format&fit=crop"),
("Water Bottle 2", "Insulated water bottle to keep your drinks cold.", 22.50, 75, "https://images.unsplash.com/photo-1523362628745-0c100150b504?w=500&auto=format&fit=crop"),
("Water Bottle 3", "Lightweight and eco-friendly water bottle.", 12.00, 120, "https://images.unsplash.com/photo-1592892111425-15e04305f961?w=500&auto=format&fit=crop");