<?php

class Product {
    // 1. Change this to match whatever variable name you use inside your functions
    private $conn = null;
    private $table_name = "products";

    // 2. The constructor must accept the $db variable passed from index.php
    public function __construct($db) {
        if ($db === null) {
            throw new Exception("Database connection cannot be null.");
        }
        $this->conn = $db;
    }

    public function readAll() {
        $query = "SELECT * FROM " . $this->table_name;

        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        return $stmt;
    }
}
?>