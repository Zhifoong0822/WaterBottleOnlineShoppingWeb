========================================================================
             SippyGo - Water Bottle Online Shopping Web
                    System Testing & Credentials Guide
========================================================================

1. BASE SYSTEM URLS
------------------------------------------------------------------------
Local Server Base URL:  http://localhost/WaterBottleOnlineShoppingWeb/
Login Page URL:         http://localhost/WaterBottleOnlineShoppingWeb/login.php

Relative URLs:
  - Public Storefront: /products.php
  - Login Page:        /login.php
  - Admin Dashboard:   /admin_dashboard.php


2. TEST CREDENTIALS
------------------------------------------------------------------------

[ ADMIN ACCOUNTS ]
------------------------------------------------------------------------
Account 1 (Main Admin):
  - Email:    admin@example.com
  - Password: admin123
  - Role:     admin
  - Target URL: http://localhost/WaterBottleOnlineShoppingWeb/admin_dashboard.php

Admin Quick Links:
  - Admin Dashboard:  /admin_dashboard.php
  - Product Manager:  /pages/admin/admin_products.php
  - Order Management: /admin_orders.php
  - Member Listing:   /pages/admin/member_listing.php


[ MEMBER ACCOUNTS ]
------------------------------------------------------------------------
Account 1 (John):
  - Email:    john@example.com
  - Password: john123
  - Role:     member
  - Reward Points: 500 points

Account 2 (Jane):
  - Email:    jane@example.com
  - Password: jane123
  - Role:     member
  - Reward Points: 250 points

Account 3 (Ali):
  - Email:    ali@example.com
  - Password: ali123
  - Role:     member
  - Reward Points: 0 points

Member Quick Links:
  - Shop Catalogue:   /products.php
  - Shopping Cart:    /cart_view.php
  - Order History:    /order_history.php
  - Store Finder:     /find_store.php
  - Live Support:     /live_chat.php
  - Profile Page:     /profile.php


3. DATABASE SETUP INSTRUCTIONS
------------------------------------------------------------------------
1. Open phpMyAdmin or your MySQL client.
2. Import the database schema file:
   db/database.sql
========================================================================
