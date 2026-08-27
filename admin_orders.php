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

                        <!--Store inside each tablerow (HTML) so JS can use them to filter the rows-->
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

<nav id="pagination" class="order-pagination"></nav>

</section>

<script>
const searchFilter = document.getElementById("orderSearchField");
const searchInput = document.getElementById("orderSearch");
//Get all order rows from Order table
const orderRows = document.querySelectorAll("#orderTable tbody tr[data-order-id]");

const placeholders = {
    "order-id": "Enter order ID",
    "user-id": "Enter user ID",
    "status": "Enter order status"
};

const ordersPerPage = 12;
let currentPage = 1;
//Get pagination area
const pagination = document.getElementById("pagination");

function getFilteredOrders() {
    //Get the admin input
    const searchValue = searchInput.value
        .trim()
        .toLowerCase()
        .replace(/^#/, "");

        //Check each row and Return rows that match the searchValue
        return Array.from(orderRows).filter(function(row) {
            let value;

            if(searchFilter.value === "order-id"){
                value = row.dataset.orderId;
            }

            else if (searchFilter.value === "user-id"){
                value = row.dataset.userId;
            }

            else {
                value = row.dataset.status;
            }

            //Return row that includes the searchValue
            return value.toLowerCase().includes(searchValue);
        });
    }

    function displayOrders() {
        const filteredOrders = getFilteredOrders();

        const totalPages = Math.max(1, Math.ceil(filteredOrders.length / ordersPerPage));

        if (currentPage > totalPages){
            currentPage = totalPages;
        }

        const orderStartIndex = (currentPage - 1) * ordersPerPage;
        const orderEndIndex = orderStartIndex + ordersPerPage;

        //Hide all order rows first
        orderRows.forEach(function(row) {
            row.hidden = true;
        });

        //Display filtered rows for current page
        filteredOrders.slice(orderStartIndex, orderEndIndex)
            .forEach(function(row) {
                row.hidden = false;
            });

        createPaginationButtons(totalPages);
    }

    function createPaginationButtons(totalPages) {
        // Clear existing pagination buttons
        pagination.innerHTML = "";

        if (totalPages <= 1) {
            pagination.hidden = true;
            return;
        }
        pagination.hidden = false;

        //PREVIOUS button
        if (currentPage > 1) {
            const previousButton = document.createElement("button");

            previousButton.type = "button";
            previousButton.textContent = "Previous";
            previousButton.className = "pagination-link";

            previousButton.addEventListener("click", function() {
                currentPage--;

                displayOrders();
            });

            //display the Previous button
            pagination.appendChild(previousButton);
        }

        //PAGE NUMBER buttons
        for (let page = 1; page <= totalPages; page++) {
            const pageButton = document.createElement("button");

            pageButton.type = "button";
            pageButton.textContent = page;
            pageButton.className = "pagination-link";

            //Highlight current page
            if (page === currentPage) {
                pageButton.classList.add("active");
            }

            pageButton.addEventListener("click", function() {
                currentPage = page;

                displayOrders();
            });

            pagination.appendChild(pageButton);
        }

        //NEXT button
        if (currentPage < totalPages) {
            const nextButton = document.createElement("button");

            nextButton.type = "button";
            nextButton.textContent = "Next";
            nextButton.className = "pagination-link";

            nextButton.addEventListener("click", function() {
                currentPage++;

                displayOrders();
            });

            //display the Next button
            pagination.appendChild(nextButton);
        }
    }

    searchInput.addEventListener("input", function() {
        //Go back to page1 when admin wants a new search
        currentPage = 1;

        displayOrders();
    });

    searchFilter.addEventListener("change", function () {
        //Change the placeholder
        searchInput.placeholder = placeholders[searchFilter.value];
        
        currentPage = 1;

        displayOrders();
    
        searchInput.focus();
});

displayOrders();
</script>

<?php require "_foot.php"; ?>