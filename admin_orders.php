<?php

require_once "_base.php";
require_admin('products.php');

$_title = "Manage Orders";

try {
    $sql = "SELECT
                order_id,
                user_id,
                order_date,
                total_amount,
                status
            FROM orders
            ORDER BY order_date DESC";

    $stmt = $_db->query($sql);

    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Unable to retrieve orders: " . $e->getMessage());
}

$total_orders = count($orders);

require "_head.php";

?>

<section class="admin-orders-page">

    <div class="page-header">

        <div class="order-count">
            Total Orders: <?= $total_orders ?>
        </div>

    </div>

    <div class="admin-order-card">

        <div class="table-tools">

            <input
                type="text"
                id="orderSearch"
                class="search-box"
                placeholder="Search by order ID, user ID or status"
            >

        </div>

        <div class="table-wrapper">

            <table id="orderTable" class="admin-order-table">

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

                <?php if ($orders): ?>

                    <?php foreach ($orders as $order): ?>

                        <?php
                        $status = strtolower($order["status"]);
                        ?>

                        <tr>

                            <td class="order-id">
                                #<?= encode($order["order_id"]) ?>
                            </td>

                            <td>
                                <?= encode($order["user_id"]) ?>
                            </td>

                            <td>
                                <?= date(
                                    "d M Y, h:i A",
                                    strtotime($order["order_date"])
                                ) ?>
                            </td>

                            <td class="amount">
                                RM <?= number_format(
                                    (float) $order["total_amount"],
                                    2
                                ) ?>
                            </td>

                            <td>
                                <span class="status status-<?= encode($status) ?>">
                                    <?= ucfirst(encode($status)) ?>
                                </span>
                            </td>

                            <td>
                                <a
                                    class="view-button"
                                    href="admin_order_detail.php?id=<?= urlencode(
                                        $order["order_id"]
                                    ) ?>"
                                >
                                    View Details
                                </a>
                            </td>

                        </tr>

                    <?php endforeach; ?>

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

</section>

<script>
const searchInput = document.getElementById("orderSearch");

searchInput.addEventListener("keyup", function () {
    const filter = searchInput.value.toLowerCase();
    const rows = document.querySelectorAll("#orderTable tbody tr");

    rows.forEach(function (row) {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(filter) ? "" : "none";
    });
});
</script>

<?php require "_foot.php"; ?>
