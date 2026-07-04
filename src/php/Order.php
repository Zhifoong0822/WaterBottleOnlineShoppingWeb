<?php

class Order {
    private $conn;
    private $table_name = "orders";

    public $order_id;
    public $user_id;
    public $order_date;
    public $total_amount;
    public $status;

    public function __construct($db){
        $this->conn = $db;
    }

    public function create(){
        $query = "INSERT INTO " . $this->table_name . " SET user_id=:user_id, total_amount=:total_amount, status=:status";
        $stmt = $this->conn->prepare($query);

        $this->user_id=htmlspecialchars(strip_tags($this->user_id));
        $this->total_amount=htmlspecialchars(strip_tags($this->total_amount));
        $this->status=htmlspecialchars(strip_tags($this->status));

        $stmt->bindParam(":user_id", $this->user_id);
        $stmt->bindParam(":total_amount", $this->total_amount);
        $stmt->bindParam(":status", $this->status);

        if($stmt->execute()){
            $this->order_id = $this->conn->lastInsertId();
            return true;
        }
        return false;
    }
}
?>