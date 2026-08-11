<?php

require_once "_base.php";
require_admin('products.php');

$_title = "Manage Orders";
$_hide_page_title = true;

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

        <h1>Manage Orders</h1>

        <div class="order-count">
            Total Orders: <?= $total_orders ?>
        </div>

    </div>

    <div class="admin-order-card">

        <div class="table-tools">

            <label class="search-field-label" for="orderSearchField">
                Search by
            </label>

            <select id="orderSearchField" class="search-field-select">
                <option value="order-id">Order ID</option>
                <option value="user-id">User ID</option>
                <option value="status">Order Status</option>
            </select>

            <input
                type="text"
                id="orderSearch"
                class="search-box"
                placeholder="Enter order ID"
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

                        <tr
                            data-order-id="<?= encode($order["order_id"]) ?>"
                            data-user-id="<?= encode($order["user_id"]) ?>"
                            data-status="<?= encode($status) ?>"
                        >

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
const searchField = document.getElementById("orderSearchField");
const searchPlaceholders = {
    "order-id": "Enter order ID",
    "user-id": "Enter user ID",
    "status": "Enter order status"
};
const searchDataKeys = {
    "order-id": "orderId",
    "user-id": "userId",
    "status": "status"
};

function filterOrders() {
    const filter = searchInput.value.trim().toLowerCase().replace(/^#/, "");
    const field = searchField.value;
    const rows = document.querySelectorAll("#orderTable tbody tr[data-order-id]");

    rows.forEach(function (row) {
        const value = row.dataset[searchDataKeys[field]].toLowerCase();
        row.style.display = value.includes(filter) ? "" : "none";
    });
}

searchInput.addEventListener("input", filterOrders);

searchField.addEventListener("change", function () {
    searchInput.placeholder = searchPlaceholders[searchField.value];
    filterOrders();
    searchInput.focus();
});
</script>

<?php require "_foot.php"; ?>
