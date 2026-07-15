<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . "/config/db_connect.php";

$sql = "SELECT * FROM orders ORDER BY order_date DESC";
$result = $conn->query($sql);

if (!$result) {
    die("Query failed: " . $conn->error);
}

$total_orders = $result->num_rows;

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Manage Orders</title>

    <link rel="stylesheet" href="css/style.css">

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background-color: #f4f6f8;
            color: #222;
        }

        .admin-container {
            width: 92%;
            max-width: 1200px;
            margin: 40px auto;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            margin-bottom: 25px;
        }

        .page-title h1 {
            margin: 0 0 8px;
            font-size: 30px;
        }

        .page-title p {
            margin: 0;
            color: #666;
        }

        .order-count {
            background-color: #ffffff;
            padding: 14px 20px;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.07);
            font-weight: bold;
        }

        .order-card {
            background-color: #ffffff;
            border-radius: 14px;
            box-shadow: 0 5px 18px rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }

        .table-tools {
            padding: 20px;
            border-bottom: 1px solid #e5e5e5;
        }

        .search-box {
            width: 100%;
            max-width: 320px;
            padding: 11px 14px;
            border: 1px solid #cccccc;
            border-radius: 8px;
            font-size: 15px;
        }

        .search-box:focus {
            outline: none;
            border-color: #333333;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        .order-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 850px;
        }

        .order-table th {
            background-color: #222222;
            color: #ffffff;
            text-align: left;
            padding: 16px;
            font-size: 14px;
        }

        .order-table td {
            padding: 16px;
            border-bottom: 1px solid #eeeeee;
            vertical-align: middle;
        }

        .order-table tbody tr:hover {
            background-color: #f8f9fa;
        }

        .order-id {
            font-weight: bold;
        }

        .amount {
            font-weight: bold;
        }

        .status-badge {
            display: inline-block;
            padding: 6px 12px;
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

        .view-button {
            display: inline-block;
            padding: 8px 14px;
            background-color: #222222;
            color: #ffffff;
            text-decoration: none;
            border-radius: 7px;
            font-size: 14px;
            transition: 0.2s;
        }

        .view-button:hover {
            background-color: #555555;
        }

        .no-orders {
            text-align: center;
            padding: 35px;
            color: #777777;
        }

        .back-link {
            display: inline-block;
            margin-top: 20px;
            color: #333333;
            text-decoration: none;
        }

        .back-link:hover {
            text-decoration: underline;
        }

        @media (max-width: 700px) {
            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .admin-container {
                width: 95%;
                margin-top: 20px;
            }
        }
    </style>
</head>

<body>

<div class="admin-container">

    <div class="page-header">

        <div class="page-title">
            <h1>Manage Orders</h1>
            <p>View and maintain all customer orders.</p>
        </div>

        <div class="order-count">
            Total Orders: <?php echo $total_orders; ?>
        </div>

    </div>

    <div class="order-card">

        <div class="table-tools">
            <input
                type="text"
                id="orderSearch"
                class="search-box"
                placeholder="Search by order ID, user ID or status..."
                onkeyup="searchOrders()"
            >
        </div>

        <div class="table-wrapper">

            <table class="order-table" id="orderTable">

                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>User ID</th>
                        <th>Order Date</th>
                        <th>Total Amount</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>

                <?php if ($result->num_rows > 0): ?>

                    <?php while ($row = $result->fetch_assoc()): ?>

                        <?php
                        $status = strtolower($row['status']);
                        $status_class = "status-" . $status;
                        ?>

                        <tr>

                            <td class="order-id">
                                #<?php echo htmlspecialchars($row['order_id']); ?>
                            </td>

                            <td>
                                <?php echo htmlspecialchars($row['user_id']); ?>
                            </td>

                            <td>
                                <?php
                                echo date(
                                    "d M Y, h:i A",
                                    strtotime($row['order_date'])
                                );
                                ?>
                            </td>

                            <td class="amount">
                                RM <?php echo number_format($row['total_amount'], 2); ?>
                            </td>

                            <td>
                                <span class="status-badge <?php echo $status_class; ?>">
                                    <?php echo ucfirst(htmlspecialchars($row['status'])); ?>
                                </span>
                            </td>

                            <td>
                                <a
                                    class="view-button"
                                    href="admin_order_detail.php?id=<?php echo urlencode($row['order_id']); ?>"
                                >
                                    View Details
                                </a>
                            </td>

                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="6" class="no-orders">
                            No orders found.
                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

    <a class="back-link" href="index.php">
        ← Back to Home
    </a>

</div>

<script>
function searchOrders() {
    const input = document.getElementById("orderSearch");
    const filter = input.value.toLowerCase();
    const table = document.getElementById("orderTable");
    const rows = table.getElementsByTagName("tr");

    for (let i = 1; i < rows.length; i++) {
        const rowText = rows[i].textContent.toLowerCase();

        if (rowText.includes(filter)) {
            rows[i].style.display = "";
        } else {
            rows[i].style.display = "none";
        }
    }
}
</script>

</body>

</html>

<?php

$conn->close();

?>