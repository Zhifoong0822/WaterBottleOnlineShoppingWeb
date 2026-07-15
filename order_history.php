<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . "/config/db_connect.php";

// Temporary user ID for testing
$user_id = 2;

$sql = "SELECT order_id, order_date, status, total_amount
        FROM orders
        WHERE user_id = ?
        ORDER BY order_date DESC";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Query preparation failed: " . $conn->error);
}

$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>My Orders</title>

    <link rel="stylesheet" href="css/style.css">

    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f5f7fa;
            margin: 0;
            padding: 40px 20px;
        }

        .order-container {
            max-width: 1000px;
            margin: 0 auto;
            background-color: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.08);
        }

        .page-title {
            margin-top: 0;
            margin-bottom: 25px;
            color: #222;
        }

        .order-table {
            width: 100%;
            border-collapse: collapse;
        }

        .order-table th {
            background-color: #222;
            color: white;
            padding: 14px;
            text-align: left;
        }

        .order-table td {
            padding: 14px;
            border-bottom: 1px solid #ddd;
        }

        .order-table tr:hover {
            background-color: #f1f1f1;
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

        .view-button {
            display: inline-block;
            padding: 8px 14px;
            background-color: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 6px;
        }

        .view-button:hover {
            background-color: #0056b3;
        }

        .no-orders {
            text-align: center;
            color: #777;
            padding: 30px;
        }

        .back-link {
            display: inline-block;
            margin-top: 20px;
            text-decoration: none;
            color: #333;
        }

        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>

<body>

<div class="order-container">

    <h2 class="page-title">My Orders</h2>

    <table class="order-table">

        <tr>
            <th>Order ID</th>
            <th>Date</th>
            <th>Status</th>
            <th>Total</th>
            <th>Action</th>
        </tr>

        <?php if ($result->num_rows > 0): ?>

            <?php while ($row = $result->fetch_assoc()): ?>

                <?php
                $status = strtolower($row['status']);
                $status_class = "status-" . $status;
                ?>

                <tr>
                    <td>
                        #<?php echo htmlspecialchars($row['order_id']); ?>
                    </td>

                    <td>
                        <?php
                        echo date(
                            "d M Y, h:i A",
                            strtotime($row['order_date'])
                        );
                        ?>
                    </td>

                    <td>
                        <span class="status <?php echo $status_class; ?>">
                            <?php echo ucfirst(htmlspecialchars($row['status'])); ?>
                        </span>
                    </td>

                    <td>
                        RM <?php echo number_format($row['total_amount'], 2); ?>
                    </td>

                    <td>
                        <a class="view-button"
                           href="order_detail.php?id=<?php echo urlencode($row['order_id']); ?>">
                            View Details
                        </a>
                    </td>
                </tr>

            <?php endwhile; ?>

        <?php else: ?>

            <tr>
                <td colspan="5" class="no-orders">
                    You do not have any orders yet.
                </td>
            </tr>

        <?php endif; ?>

    </table>

    <a class="back-link" href="index.php">← Back to Home</a>

</div>

</body>

</html>

<?php

$stmt->close();
$conn->close();

?>