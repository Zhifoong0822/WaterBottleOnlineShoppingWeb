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
  `status` ENUM('pending', 'shipped', 'completed', 'cancelled') NOT NULL DEFAULT 'pending',
  `recipient_name` VARCHAR(100) NOT NULL,
  `shipping_address` TEXT NOT NULL,
  `phone_number` VARCHAR(20) NOT NULL,
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
INSERT INTO `users` (`user_id`, `username`, `email`, `password`, `role`) VALUES
(1, 'member1', 'member1@example.com', '$2y$10$Qws0vG.ePjJm4X5Z0F6Ym.G7Ym.G7Ym.G7Ym.G7Ym.G7Ym.G7Ym.G7Ym.G7Ym', 'member'),
(2, 'admin', 'admin@example.com', '$2y$10$Qws0vG.ePjJm4X5Z0F6Ym.G7Ym.G7Ym.G7Ym.G7Ym.G7Ym.G7Ym.G7Ym.G7Ym', 'admin');
(3, 'John', 'john@example.com','$2y$10$JtoR6U8QJ4faEJarm2IJgOjacO/rV6faPFI/FvOlZvpQZlarYNMLO','member'),
(4, 'Jane', 'jane@example.com','$2y$10$23mamuogsv2sta9f1T0kIeHVraQcAvJfs5zLZidkRQkrMCxMr6Hwa','member');

INSERT INTO `user_addresses` (`user_id`, `address_label`, `recipient_name`, `phone_number`, `address_text`) VALUES
(1, 'Home (Default)', 'Member One', '012-3456789', '123, Jalan Sultan Ismail, Bukit Bintang, 50250 Kuala Lumpur'),
(1, 'Office HQ', 'Member One (Corp)', '013-9876543', 'Level 45, Tower 2, Petronas Twin Towers, KLCC, 50088 Kuala Lumpur'); 

INSERT INTO `categories` (`category_name`) VALUES 
('Ace Bottle Series'), 
('Sense Cup Series'),
('Knight Tumbler Series'),
('Kids Collection'),
('Accessories');

-- Base Products Catalog (20 Products Total)
INSERT INTO `products` (`product_id`, `category_id`, `name`, `description`, `price`, `image_url`) VALUES
(1, 1, 'Ace Active Bottle', 'Double-wall insulated bottle for everyday hydration.', 89.00, 'https://images.unsplash.com/photo-1602143407151-7111542de6e8?w=500&auto=format&fit=crop'),
(2, 1, 'Ace Mega Explorer', 'Large capacity insulated bottle for outdoor adventures.', 129.00, 'https://images.unsplash.com/photo-1592892111425-15e04305f961?w=500&auto=format&fit=crop'),
(3, 2, 'Sense Coffee Cup', 'Premium insulated coffee tumbler for your daily brew.', 89.00, 'https://images.unsplash.com/photo-1523362628745-0c100150b504?w=500&auto=format&fit=crop'),
(4, 3, 'Knight Steel Tumbler', 'Heavy-duty stainless steel travel tumbler with leakproof lid.', 99.00, 'https://images.unsplash.com/photo-1517256064527-09c73fc73e38?w=500&auto=format&fit=crop'),
(5, 1, 'Ace Pastel Edition', 'Limited edition aesthetic pastel finish insulated bottle.', 95.00, 'https://images.unsplash.com/photo-1544816155-12df9643f363?w=500&auto=format&fit=crop'),
(6, 4, 'Junior Straw Bottle', 'BPA-free kid friendly lightweight sports water bottle.', 59.00, 'https://images.unsplash.com/photo-1570831739425-8753f027002e?w=500&auto=format&fit=crop'),
(7, 3, 'Knight Thermal Flask', 'Vacuum sealed thermal flask keeping drinks hot or cold for 24 hours.', 119.00, 'https://images.unsplash.com/photo-1589365278144-c9e705f843ba?w=500&auto=format&fit=crop'),
(8, 5, 'Silicone Protective Sleeve', 'Shock-absorbing silicone boot sleeve fitting all Ace series bottles.', 19.00, 'https://images.unsplash.com/photo-1556905055-8f358a7a47b2?w=500&auto=format&fit=crop'),
(9, 1, 'Ace Matte Sport Bottle', 'Non-slip matte powder coat bottle built for intense gym workouts.', 79.00, 'https://images.unsplash.com/photo-1570554886111-e80fcca6a029?w=500&auto=format&fit=crop'),
(10, 2, 'Sense Ceramic Mug', 'Ceramic lined thermal mug preserving true coffee and tea flavor.', 69.00, 'https://images.unsplash.com/photo-1536939459926-301728717817?w=500&auto=format&fit=crop'),
(11, 3, 'Knight Slim Insulated Flask', 'Sleek ultra-slim vacuum flask that fits effortlessly into bag pockets.', 109.00, 'https://images.unsplash.com/photo-1610824352934-c10d87b700cc?w=500&auto=format&fit=crop'),
(12, 4, 'Junior Dino Flip Bottle', 'Fun dinosaur themed squeeze bottle with soft-touch straw cap.', 49.00, 'https://images.unsplash.com/photo-1517686469429-8bdb88b9f907?w=500&auto=format&fit=crop'),
(13, 5, 'Cleaning Brush Kit', '3-in-1 bottle cleaner kit with bendable straw and cap brushes.', 25.00, 'https://images.unsplash.com/photo-1578319439584-104c94d37305?w=500&auto=format&fit=crop'),
(14, 1, 'Ace Luxe Metallic Gold', 'Special edition gold coated vacuum bottle with premium gift box.', 149.00, 'https://images.unsplash.com/photo-1581009146145-b5ef050c2e1e?w=500&auto=format&fit=crop'),
(15, 2, 'Sense Espresso Travel Cup', 'Compact double-walled cup designed for double espresso shots.', 75.00, 'https://images.unsplash.com/photo-1541167760496-1628856ab772?w=500&auto=format&fit=crop'),
(16, 1, 'Ice Infuser Fruit Bottle', 'Removable fruit infuser core for refreshing natural water infusions.', 85.00, 'https://images.unsplash.com/photo-1514432324607-a09d9b4aefdd?w=500&auto=format&fit=crop'),
(17, 5, 'Paracord Bottle Strap', 'Heavy duty tactical braided handle carrying cord with carabiner.', 15.00, 'https://images.unsplash.com/photo-1509042239860-f550ce710b93?w=500&auto=format&fit=crop'),
(18, 3, 'Knight Wide-Mouth Hydrator', 'Extra-wide spout flask designed for fast flow and large ice cubes.', 139.00, 'https://images.unsplash.com/photo-1577937927133-66ef06acdf18?w=500&auto=format&fit=crop'),
(19, 2, 'Sense Cold Brew Tumbler', 'Includes stainless steel mesh filter for smooth cold brew coffee on the go.', 92.00, 'https://images.unsplash.com/photo-1513558161293-cdaf765ed2fd?w=500&auto=format&fit=crop'),
(20, 1, 'Ace Pocket Mini Flask', 'Pocket-sized 250ml flask for quick sips on short commutes.', 45.00, 'https://images.unsplash.com/photo-1559825481-12a05cc00344?w=500&auto=format&fit=crop');

-- Variant Stock Allocations (Products 1 to 20)
INSERT INTO `product_variants` (`product_id`, `size`, `colour`, `stock`) VALUES
-- Product 1: Ace Active Bottle
(1, 'Micro (12oz / 350ml)', 'Navy Apricot', 10),
(1, 'Mini (15oz / 450ml)', 'Navy Apricot', 0),
(1, 'Medium (18oz / 530ml)', 'Navy Apricot', 25),
(1, 'Mega (32oz / 950ml)', 'Navy Apricot', 3),

-- Product 2: Ace Mega Explorer
(2, 'Micro (12oz / 350ml)', 'Linen Blue', 0),
(2, 'Mini (15oz / 450ml)', 'Linen Blue', 5),
(2, 'Medium (18oz / 530ml)', 'Linen Blue', 10),
(2, 'Mega (32oz / 950ml)', 'Linen Blue', 15),

-- Product 3: Sense Coffee Cup
(3, 'Micro (12oz / 350ml)', 'Matte Black', 15),
(3, 'Mini (15oz / 450ml)', 'Matte Black', 20),
(3, 'Medium (18oz / 530ml)', 'Matte Black', 28),
(3, 'Mega (32oz / 950ml)', 'Matte Black', 8),

-- Product 4: Knight Steel Tumbler
(4, 'Medium (18oz / 530ml)', 'Brushed Steel', 12),
(4, 'Mega (32oz / 950ml)', 'Brushed Steel', 6),

-- Product 5: Ace Pastel Edition
(5, 'Micro (12oz / 350ml)', 'Sakura Pink', 2),
(5, 'Mini (15oz / 450ml)', 'Sakura Pink', 1),

-- Product 6: Junior Straw Bottle (Sold Out test)
(6, 'Micro (12oz / 350ml)', 'Bright Yellow', 0),
(6, 'Mini (15oz / 450ml)', 'Bright Yellow', 0),

-- Product 7: Knight Thermal Flask
(7, 'Medium (18oz / 530ml)', 'Gunmetal Grey', 18),
(7, 'Mega (32oz / 950ml)', 'Gunmetal Grey', 22),

-- Product 8: Silicone Protective Sleeve
(8, 'Medium (18oz / 530ml)', 'Black', 50),

-- Product 9: Ace Matte Sport Bottle
(9, 'Medium (18oz / 530ml)', 'Stealth Black', 14),
(9, 'Mega (32oz / 950ml)', 'Stealth Black', 8),

-- Product 10: Sense Ceramic Mug
(10, 'Micro (12oz / 350ml)', 'Cream White', 30),

-- Product 11: Knight Slim Insulated Flask
(11, 'Mini (15oz / 450ml)', 'Silver Metallic', 12),

-- Product 12: Junior Dino Flip Bottle
(12, 'Micro (12oz / 350ml)', 'Dino Green', 19),

-- Product 13: Cleaning Brush Kit
(13, 'Medium (18oz / 530ml)', 'Multicolor', 100),

-- Product 14: Ace Luxe Metallic Gold
(14, 'Medium (18oz / 530ml)', 'Metallic Gold', 5),

-- Product 15: Sense Espresso Travel Cup
(15, 'Micro (12oz / 350ml)', 'Charcoal Grey', 3),

-- Product 16: Ice Infuser Fruit Bottle
(16, 'Medium (18oz / 530ml)', 'Crystal Clear', 11),

-- Product 17: Paracord Bottle Strap
(17, 'Medium (18oz / 530ml)', 'Army Green', 45),

-- Product 18: Knight Wide-Mouth Hydrator
(18, 'Mega (32oz / 950ml)', 'Deep Blue', 16),

-- Product 19: Sense Cold Brew Tumbler (Sold Out test)
(19, 'Medium (18oz / 530ml)', 'Frost White', 0),

-- Product 20: Ace Pocket Mini Flask
(20, 'Micro (12oz / 350ml)', 'Rose Gold', 25);