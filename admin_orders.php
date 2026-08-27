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

    <!--Search box and Table-->
    <div class="admin-order-card">
        <div class="table-tools">

            <label class="search-field-label" for="orderSearchField">
                Search by
            </label>

            <!--Selection List for order search filters-->
            <select id="orderSearchField" class="search-field-select">
                <option value="order-id">Order ID</option>
                <option value="user-id">User ID</option>
                <option value="status">Order Status</option>
            </select>

            <!--Search Textbox (Default: Order ID)-->
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

                        <!--Store info inside HTML so JS can filter the row-->
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
                                <?= date("d M Y, h:i A", strtotime($order["order_date"])) ?>
                            </td>

                            <td class="amount">
                                RM <?= number_format((float) $order["total_amount"], 2) ?>
                            </td>

                            <td>
                                <span class="status status-<?= encode($status) ?>">
                                    <?= ucfirst(encode($status)) ?>
                                </span>
                            </td>

                            <td>
                                <a
                                    class="view-button"
                                    href="admin_order_detail.php?id=<?= urlencode($order["order_id"]) ?>"
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

    <nav
        id="adminOrderPagination"
        class="order-pagination"
        aria-label="Manage orders pages"
        hidden
    ></nav>

</section>

<script>
const searchInput = document.getElementById("orderSearch");
const searchField = document.getElementById("orderSearchField");
const searchPlaceholders = {
    "order-id": "Enter order ID",
    "user-id": "Enter user ID",
    "status": "Enter order status"
};
//Connect the dropdown option to HTML data-* attributes
const searchDataKeys = {
    "order-id": "orderId",
    "user-id": "userId",
    "status": "status"
};
const pagination = document.getElementById("adminOrderPagination");
const ordersPerPage = 12;
let currentPage = 1;

//Find orders that match the search
function getFilteredRows() {
    const filter = searchInput.value.trim().toLowerCase().replace(/^#/, "");
    const field = searchField.value;

    return Array.from(
        document.querySelectorAll("#orderTable tbody tr[data-order-id]")
    ).filter(function (row) {
        const value = row.dataset[searchDataKeys[field]].toLowerCase();
        return value.includes(filter);
    });
}

function createPageButton(label, page, options = {}) {
    const button = document.createElement("button");
    button.type = "button";
    button.className = "pagination-link";
    button.textContent = label;  //Button title eg. Previous/Next

    //Check if it's a Previous/Next button
    if (options.direction) {
        button.classList.add("pagination-direction");
    }

    if (options.active) {
        button.classList.add("active");
        button.setAttribute("aria-current", "page");
    }

    button.addEventListener("click", function () {
        currentPage = page;  //Change the current page
        renderOrders();  //Refresh displayed orders
    });

    return button;
}

function renderPagination(totalPages) {
    pagination.replaceChildren();  //Remove existing pagination buttons
    pagination.hidden = totalPages <= 1;

    if (totalPages <= 1) {
        return;
    }

    if (currentPage > 1) {
        pagination.appendChild(
            createPageButton("Previous", currentPage - 1, { direction: true })
        );
    }

    for (let page = 1; page <= totalPages; page += 1) {
        pagination.appendChild(
            createPageButton(String(page), page, { active: page === currentPage })
        );
    }

    if (currentPage < totalPages) {
        pagination.appendChild(
            createPageButton("Next", currentPage + 1, { direction: true })
        );
    }
}

//Controls which orders to display
function renderOrders() {
    const allRows = document.querySelectorAll("#orderTable tbody tr[data-order-id]");
    const filteredRows = getFilteredRows();
    const totalPages = Math.max(1, Math.ceil(filteredRows.length / ordersPerPage));

    currentPage = Math.min(currentPage, totalPages);
    //Calculate the first order begins with what number
    const firstRow = (currentPage - 1) * ordersPerPage;
    const visibleRows = new Set(
        filteredRows.slice(firstRow, firstRow + ordersPerPage)
    );

    //Control which orders are shown
    allRows.forEach(function (row) {
        row.hidden = !visibleRows.has(row);
    });

    //Updates pagination buttons
    renderPagination(totalPages);
}

function filterOrders() {
    currentPage = 1;
    renderOrders();
}

searchInput.addEventListener("input", filterOrders);

searchField.addEventListener("change", function () {
    //Change the placeholder based on searchField value
    searchInput.placeholder = searchPlaceholders[searchField.value];
    filterOrders();
    searchInput.focus();
});

renderOrders();
</script>

<?php require "_foot.php"; ?>
