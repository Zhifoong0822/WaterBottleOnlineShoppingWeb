<?php

class Product {
    // 1. Change this to match whatever variable name you use inside your functions
    private $conn;
    private $table_name = "products";

    // 2. The constructor must accept the $db variable passed from index.php
    public function __construct($db) {
        // This links the external database connection to this class property
        $this->conn = $db; 
    }

    public function readAll() {
        // 3. Select your columns from the database
        // (Make sure these column names exactly match your MySQL database table structure!)
        $query = "SELECT product_id, name, description, price, stock, image_url FROM " . $this->table_name;

        // 4. Use $this->conn instead of any other variable name
        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        return $stmt;
    }
}
?>