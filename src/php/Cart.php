<?php

class Cart {
    private $conn;
    private $table_name = "carts";

    public $cart_id;
    public $user_id;
    public $created_at;
    public $updated_at;

    public function __construct($db){
        $this->conn = $db;
    }

    public function create(){
        $query = "INSERT INTO " . $this->table_name . " SET user_id=:user_id";
        $stmt = $this->conn->prepare($query);

        $this->user_id=htmlspecialchars(strip_tags($this->user_id));

        $stmt->bindParam(":user_id", $this->user_id);

        if($stmt->execute()){
            $this->cart_id = $this->conn->lastInsertId();
            return true;
        }
        return false;
    }

    public function getCartByUserId(){
        $query = "SELECT cart_id, user_id, created_at, updated_at FROM " . $this->table_name . " WHERE user_id = ? LIMIT 0,1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->user_id);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if($row) {
            $this->cart_id = $row["cart_id"];
            $this->user_id = $row["user_id"];
            $this->created_at = $row["created_at"];
            $this->updated_at = $row["updated_at"];
            return true;
        }
        return false;
    }
}
?>