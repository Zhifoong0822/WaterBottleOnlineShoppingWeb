<?php

class OrderItem {
    private $conn;
    private $table_name = "order_items";

    public $order_item_id;
    public $order_id;
    public $product_id;
    public $quantity;
    public $price;

    public function __construct($db){
        $this->conn = $db;
    }

    public function create(){
        $query = "INSERT INTO " . $this->table_name . " SET order_id=:order_id, product_id=:product_id, quantity=:quantity, price=:price";
        $stmt = $this->conn->prepare($query);

        $this->order_id=htmlspecialchars(strip_tags($this->order_id));
        $this->product_id=htmlspecialchars(strip_tags($this->product_id));
        $this->quantity=htmlspecialchars(strip_tags($this->quantity));
        $this->price=htmlspecialchars(strip_tags($this->price));

        $stmt->bindParam(":order_id", $this->order_id);
        $stmt->bindParam(":product_id", $this->product_id);
        $stmt->bindParam(":quantity", $this->quantity);
        $stmt->bindParam(":price", $this->price);

        if($stmt->execute()){
            return true;
        }
        return false;
    }
}
?>