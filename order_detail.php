<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . "/config/db_connect.php";

// Temporary customer ID for testing.
// Your customer/member1 has user_id = 2.
$user_id = 2;

// Check that an order ID was provided in the URL.
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid order ID.");
}

$order_id = (int) $_GET['id'];

// Retrieve only an order belonging to the current customer.
$sql = "SELECT order_id, user_id, order_date, total_amount, status
        FROM orders
        WHERE order_id = ? AND user_id = ?";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Query preparation failed: " . $conn->error);
}

$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Order not found or you do not have permission to view it.");
}

// This creates the $order variable used in the HTML below.
$order = $result->fetch_assoc();

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Order Details</title>

    <link rel="stylesheet" href="css/style.css">

    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
            background-color: #f5f7fa;
            margin: 0;
            padding: 40px 20px;
        }

        .order-container {
            max-width: 750px;
            margin: 0 auto;
            background-color: #ffffff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        }

        .page-title {
            margin-top: 0;
            margin-bottom: 25px;
            color: #222222;
        }

        .order-table {
            width: 100%;
            border-collapse: collapse;
        }

        .order-table th {
            width: 35%;
            padding: 16px;
            background-color: #222222;
            color: #ffffff;
            text-align: left;
            border-bottom: 1px solid #444444;
        }

        .order-table td {
            padding: 16px;
            border-bottom: 1px solid #dddddd;
        }

        .status {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: bold;
        }

        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }

        .status-completed {
            background-color: #d4edda;
            color: #155724;
        }

        .status-cancelled {
            background-color: #f8d7da;
            color: #721c24;
        }

        .back-button {
            display: inline-block;
            margin-top: 25px;
            padding: 10px 18px;
            background-color: #007bff;
            color: #ffffff;
            text-decoration: none;
            border-radius: 6px;
        }

        .back-button:hover {
            background-color: #0056b3;
        }
    </style>
</head>

<body>

<div class="order-container">

    <h2 class="page-title">Order Details</h2>

    <table class="order-table">

        <tr>
            <th>Order ID</th>
            <td>
                #<?php echo htmlspecialchars($order['order_id']); ?>
            </td>
        </tr>

        <tr>
            <th>Order Date</th>
            <td>
                <?php
                echo date(
                    "d M Y, h:i A",
                    strtotime($order['order_date'])
                );
                ?>
            </td>
        </tr>

        <tr>
            <th>Status</th>
            <td>
                <span class="status status-<?php
                    echo strtolower(htmlspecialchars($order['status']));
                ?>">
                    <?php
                    echo ucfirst(htmlspecialchars($order['status']));
                    ?>
                </span>
            </td>
        </tr>

        <tr>
            <th>Total Amount</th>
            <td>
                <strong>
                    RM <?php echo number_format($order['total_amount'], 2); ?>
                </strong>
            </td>
        </tr>

    </table>

    <a class="back-button" href="order_history.php">
        ← Back to My Orders
    </a>

</div>

</body>

</html>

<?php

$stmt->close();
$conn->close();

?>