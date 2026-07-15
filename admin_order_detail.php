<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . "/config/db_connect.php";

// Check whether the order ID exists.
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid order ID.");
}

$order_id = (int) $_GET['id'];

$message = "";
$error = "";

// Update the status when the form is submitted.
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $allowed_statuses = [
        "pending",
        "completed",
        "cancelled"
    ];

    $status = $_POST['status'] ?? "";

    if (!in_array($status, $allowed_statuses, true)) {
        $error = "Invalid order status.";
    } else {

        $update_sql = "UPDATE orders
                       SET status = ?
                       WHERE order_id = ?";

        $update_stmt = $conn->prepare($update_sql);

        if (!$update_stmt) {
            die("Update preparation failed: " . $conn->error);
        }

        $update_stmt->bind_param("si", $status, $order_id);

        if ($update_stmt->execute()) {
            $message = "Order status updated successfully.";
        } else {
            $error = "Unable to update the order status.";
        }

        $update_stmt->close();
    }
}

// Retrieve the latest order information
$sql = "SELECT order_id, user_id, order_date, total_amount, status
        FROM orders
        WHERE order_id = ?";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Query preparation failed: " . $conn->error);
}

$stmt->bind_param("i", $order_id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Order not found.");
}

$order = $result->fetch_assoc();

$status_class = "status-" . strtolower($order['status']);

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Admin Order Details</title>

    <link rel="stylesheet" href="css/style.css">

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 40px 20px;
            font-family: Arial, Helvetica, sans-serif;
            background-color: #f4f6f8;
            color: #222222;
        }

        .page-container {
            max-width: 850px;
            margin: 0 auto;
        }

        .page-header {
            margin-bottom: 24px;
        }

        .page-header h1 {
            margin: 0 0 8px;
            font-size: 30px;
        }

        .page-header p {
            margin: 0;
            color: #666666;
        }

        .alert {
            margin-bottom: 20px;
            padding: 14px 16px;
            border-radius: 8px;
            font-weight: bold;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .detail-card {
            background-color: #ffffff;
            border-radius: 14px;
            box-shadow: 0 5px 18px rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }

        .card-header {
            padding: 22px 25px;
            background-color: #222222;
            color: #ffffff;
        }

        .card-header h2 {
            margin: 0;
            font-size: 22px;
        }

        .order-table {
            width: 100%;
            border-collapse: collapse;
        }

        .order-table th,
        .order-table td {
            padding: 17px 25px;
            border-bottom: 1px solid #eeeeee;
            text-align: left;
        }

        .order-table th {
            width: 35%;
            background-color: #fafafa;
            color: #444444;
        }

        .amount {
            font-size: 18px;
            font-weight: bold;
        }

        .status-badge {
            display: inline-block;
            padding: 7px 13px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: bold;
            text-transform: capitalize;
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

        .status-section {
            padding: 25px;
        }

        .status-section h3 {
            margin-top: 0;
            margin-bottom: 18px;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
        }

        .status-select {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid #cccccc;
            border-radius: 8px;
            font-size: 15px;
            background-color: #ffffff;
        }

        .status-select:focus {
            outline: none;
            border-color: #222222;
        }

        .button-group {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .update-button {
            border: none;
            padding: 11px 18px;
            border-radius: 7px;
            background-color: #222222;
            color: #ffffff;
            cursor: pointer;
            font-size: 15px;
        }

        .update-button:hover {
            background-color: #555555;
        }

        .back-button {
            display: inline-block;
            padding: 11px 18px;
            border-radius: 7px;
            background-color: #e9ecef;
            color: #222222;
            text-decoration: none;
            font-size: 15px;
        }

        .back-button:hover {
            background-color: #d8dce0;
        }

        @media (max-width: 600px) {
            body {
                padding: 20px 12px;
            }

            .order-table th,
            .order-table td {
                display: block;
                width: 100%;
            }

            .order-table th {
                padding-bottom: 7px;
                border-bottom: none;
            }

            .order-table td {
                padding-top: 5px;
            }

            .button-group {
                flex-direction: column;
            }

            .update-button,
            .back-button {
                width: 100%;
                text-align: center;
            }
        }
    </style>
</head>

<body>

<div class="page-container">

    <div class="page-header">
        <h1>Admin Order Details</h1>
        <p>Review the order information and update its current status.</p>
    </div>

    <?php if ($message !== ""): ?>
        <div class="alert alert-success">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <?php if ($error !== ""): ?>
        <div class="alert alert-error">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <div class="detail-card">

        <div class="card-header">
            <h2>
                Order #<?php echo htmlspecialchars($order['order_id']); ?>
            </h2>
        </div>

        <table class="order-table">

            <tr>
                <th>Order ID</th>
                <td>
                    #<?php echo htmlspecialchars($order['order_id']); ?>
                </td>
            </tr>

            <tr>
                <th>User ID</th>
                <td>
                    <?php echo htmlspecialchars($order['user_id']); ?>
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
                <th>Total Amount</th>
                <td class="amount">
                    RM <?php echo number_format($order['total_amount'], 2); ?>
                </td>
            </tr>

            <tr>
                <th>Current Status</th>
                <td>
                    <span class="status-badge <?php echo $status_class; ?>">
                        <?php echo ucfirst(htmlspecialchars($order['status'])); ?>
                    </span>
                </td>
            </tr>

        </table>

        <div class="status-section">

            <h3>Update Order Status</h3>

            <form
                method="POST"
                action="admin_order_detail.php?id=<?php echo urlencode($order['order_id']); ?>"
                onsubmit="return confirmStatusUpdate();"
            >

                <div class="form-group">

                    <label for="status">Select New Status</label>

                    <select
                        name="status"
                        id="status"
                        class="status-select"
                        required
                    >

                        <option value="pending"
                            <?php echo $order['status'] === "pending" ? "selected" : ""; ?>>
                            Pending
                        </option>

                        <option value="completed"
                            <?php echo $order['status'] === "completed" ? "selected" : ""; ?>>
                            Completed
                        </option>

                        <option value="cancelled"
                            <?php echo $order['status'] === "cancelled" ? "selected" : ""; ?>>
                            Cancelled
                        </option>

                    </select>

                </div>

                <div class="button-group">

                    <button type="submit" class="update-button">
                        Update Status
                    </button>

                    <a href="admin_orders.php" class="back-button">
                        ← Back to Manage Orders
                    </a>

                </div>

            </form>

        </div>

    </div>

</div>

<script>
function confirmStatusUpdate() {
    const statusSelect = document.getElementById("status");
    const selectedStatus =
        statusSelect.options[statusSelect.selectedIndex].text;

    return confirm(
        "Are you sure you want to update this order status to " +
        selectedStatus +
        "?"
    );
}
</script>

</body>

</html>

<?php

$stmt->close();
$conn->close();

?>