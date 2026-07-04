<?php

class CartItem {
    private $conn;
    private $table_name = "cart_items";

    public $cart_item_id;
    public $cart_id;
    public $product_id;
    public $quantity;
    public $added_at;

    public function __construct($db){
        $this->conn = $db;
    }

    public function create(){
        $query = "INSERT INTO " . $this->table_name . " SET cart_id=:cart_id, product_id=:product_id, quantity=:quantity";
        $stmt = $this->conn->prepare($query);

        $this->cart_id=htmlspecialchars(strip_tags($this->cart_id));
        $this->product_id=htmlspecialchars(strip_tags($this->product_id));
        $this->quantity=htmlspecialchars(strip_tags($this->quantity));

        $stmt->bindParam(":cart_id", $this->cart_id);
        $stmt->bindParam(":product_id", $this->product_id);
        $stmt->bindParam(":quantity", $this->quantity);

        if($stmt->execute()){
            return true;
        }
        return false;
    }

    public function update(){
        $query = "UPDATE " . $this->table_name . " SET quantity = :quantity WHERE cart_item_id = :cart_item_id";
        $stmt = $this->conn->prepare($query);

        $this->quantity=htmlspecialchars(strip_tags($this->quantity));
        $this->cart_item_id=htmlspecialchars(strip_tags($this->cart_item_id));

        $stmt->bindParam(":quantity", $this->quantity);
        $stmt->bindParam(":cart_item_id", $this->cart_item_id);

        if($stmt->execute()){
            return true;
        }
        return false;
    }

    public function delete(){
        $query = "DELETE FROM " . $this->table_name . " WHERE cart_item_id = ?";
        $stmt = $this->conn->prepare($query);

        $this->cart_item_id=htmlspecialchars(strip_tags($this->cart_item_id));

        $stmt->bindParam(1, $this->cart_item_id);

        if($stmt->execute()){
            return true;
        }
        return false;
    }

    public function getByCartIdAndProductId(){
        $query = "SELECT cart_item_id, cart_id, product_id, quantity FROM " . $this->table_name . " WHERE cart_id = ? AND product_id = ? LIMIT 0,1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->cart_id);
        $stmt->bindParam(2, $this->product_id);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if($row) {
            $this->cart_item_id = $row["cart_item_id"];
            $this->cart_id = $row["cart_id"];
            $this->product_id = $row["product_id"];
            $this->quantity = $row["quantity"];
            return true;
        }
        return false;
    }

    public function getCartItemsByCartId(){
        $query = "SELECT ci.cart_item_id, ci.product_id, ci.quantity, p.name, p.price, p.image_url FROM " . $this->table_name . " ci LEFT JOIN products p ON ci.product_id = p.product_id WHERE ci.cart_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->cart_id);
        $stmt->execute();
        return $stmt;
    }

    public function deleteByCartId() {
        $query = "DELETE FROM " . $this->table_name . " WHERE cart_id = :cart_id";
        $stmt = $this->conn->prepare($query);

        $this->cart_id=htmlspecialchars(strip_tags($this->cart_id));

        $stmt->bindParam(":cart_id", $this->cart_id);

        if($stmt->execute()){
            return true;
        }
        return false;
    }
}
?>