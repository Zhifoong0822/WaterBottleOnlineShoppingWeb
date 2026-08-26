<?php

require_once "_base.php";
$admin_name = $_SESSION['users']->username ?? 'Admin';
require_admin('products.php');

$_title = "Admin Dashboard";
$_hide_page_title = true;

/*----------------------------------------------------------
    Stat Cards
-----------------------------------------------------------*/
$total_products = $_db->query("
    SELECT COUNT(*) FROM products WHERE status = 'active'
")->fetchColumn();

$total_orders = $_db->query("
    SELECT COUNT(*) FROM orders
")->fetchColumn();

$total_members = $_db->query("
    SELECT COUNT(*) FROM users WHERE role = 'member'
")->fetchColumn();

$in_stock_count = $_db->query("
    SELECT COUNT(*)
    FROM product_variants pv
    JOIN products p ON p.product_id = pv.product_id
    WHERE p.status = 'active' AND pv.stock > 5
")->fetchColumn();

$low_stock_count = $_db->query("
    SELECT COUNT(*)
    FROM product_variants pv
    JOIN products p ON p.product_id = pv.product_id
    WHERE p.status = 'active' AND pv.stock > 0 AND pv.stock <= 5
")->fetchColumn();

$out_of_stock_count = $_db->query("
    SELECT COUNT(*)
    FROM product_variants pv
    JOIN products p ON p.product_id = pv.product_id
    WHERE p.status = 'active' AND pv.stock = 0
")->fetchColumn();

$total_stock_types = $in_stock_count + $low_stock_count + $out_of_stock_count;
/*----------------------------------------------------------
    Current Admin
-----------------------------------------------------------*/
$current_admin = $_SESSION['username'] ?? 'Admin';
/*----------------------------------------------------------
    Orders Overview - current week (Mon-Sun), order count
    per day. Days with no orders yet (including future days
    this week) simply show 0.
-----------------------------------------------------------*/
$stmt = $_db->prepare("
    SELECT DATE(order_date) AS order_day, COUNT(*) AS order_count
    FROM orders
    WHERE YEARWEEK(order_date, 1) = YEARWEEK(CURDATE(), 1)
    GROUP BY DATE(order_date)
");

$stmt->execute();

$daily_orders_raw = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$week_start = new DateTime('monday this week');
$day_labels = [];
$day_counts = [];

for ($i = 0; $i < 7; $i++) {

    $date = (clone $week_start)->modify("+{$i} day");
    $key = $date->format('Y-m-d');

    $day_labels[] = $date->format('D');
    $day_counts[] = (int) ($daily_orders_raw[$key] ?? 0);
}

require "_head.php";
?>

<link rel="stylesheet" href="css/main.css">
<link rel="stylesheet" href="css/admin.css">

<div class="admin-container">

    <div class="page-title">
        <h1>Welcome back, <?= encode($admin_name) ?></h1>
        <p>Here's what's happening with your store today.</p><br>
    </div>

    <!-- ==========================================
         STAT CARDS
    =========================================== -->
    <div class="dashboard-cards">
        <div class="dashboard-card stat-card-icon">
            <div class="stat-card-top">
                <span class="stat-icon stat-icon-blue">&#128230;</span>
                <span class="stat-card-tag">Products listed</span>
            </div>
            <h3>Total Products</h3>
            <h2><?= $total_products ?></h2>
        </div>

        <div class="dashboard-card stat-card-icon">
            <div class="stat-card-top">
                <span class="stat-icon stat-icon-green">&#128717;</span>
                <span class="stat-card-tag">Successful sales</span>
            </div>
            <h3>Total Orders</h3>
            <h2><?= $total_orders ?></h2>
        </div>

        <div class="dashboard-card stat-card-icon">
            <div class="stat-card-top">
                <span class="stat-icon stat-icon-purple">&#128101;</span>
                <span class="stat-card-tag">Registered users</span>
            </div>
            <h3>Total Members</h3>
            <h2><?= $total_members ?></h2>
        </div>

        <div class="dashboard-card stat-card-icon">
            <div class="stat-card-top">
                <span class="stat-icon stat-icon-orange">&#9888;</span>
                <span class="stat-card-tag">Requires attention</span>
            </div>
            <h3>Low Stock Alert</h3>
            <h2><?= $low_stock_count ?></h2>
        </div>

    </div>

    <!-- ==========================================
         CHARTS
    =========================================== -->
    <div class="chart-container">
        <div class="chart-card">
            <div class="chart-card-header">
                <div>
                    <h3>Orders Overview</h3>
                    <p class="chart-card-subtitle">Sales frequency over the last 7 days</p>
                </div>
                <span class="chart-week-tag">This Week</span>
            </div>

            <div class="chart-canvas-wrap">
                <canvas id="ordersChart"></canvas>
            </div>

        </div>

        <div class="chart-card">
            <div class="chart-card-header">
                <div>
                    <h3>Product Stock Status</h3>
                    <p class="chart-card-subtitle">Current inventory levels</p>
                </div>
            </div>

            <div class="donut-row">

                <div class="donut-wrap">
                    <canvas id="stockChart" width="180" height="180"></canvas>
                    <div class="donut-center">
                        <span class="donut-center-value"><?= $total_stock_types ?></span>
                        <span class="donut-center-label">Total Types</span>
                    </div>
                </div>

                <ul class="donut-legend">
                    <li>
                        <span class="donut-dot donut-dot-green"></span>
                        In Stock
                        <span class="donut-legend-count"><?= $in_stock_count ?></span>
                    </li>
                    <li>
                        <span class="donut-dot donut-dot-orange"></span>
                        Low Stock
                        <span class="donut-legend-count"><?= $low_stock_count ?></span>
                    </li>
                    <li>
                        <span class="donut-dot donut-dot-red"></span>
                        Out of Stock
                        <span class="donut-legend-count"><?= $out_of_stock_count ?></span>
                    </li>
                </ul>

            </div>

        </div>

    </div>

    <!-- ==========================================
         QUICK ACCESS
    =========================================== -->
    <div class="quick-access">
        <h2>Quick Access</h2>
        <div class="quick-grid">

            <a href="pages/admin/admin_products.php" class="quick-card quick-card-row">
                <span class="quick-card-icon quick-card-icon-blue">&#128230;</span>
                <span class="quick-card-text">
                    <h3>Manage Products</h3>
                    <p>Add, edit and manage products</p>
                </span>
                <span class="quick-card-chevron">&#8250;</span>
            </a>

            <a href="admin_orders.php" class="quick-card quick-card-row">
                <span class="quick-card-icon quick-card-icon-green">&#128196;</span>
                <span class="quick-card-text">
                    <h3>Manage Orders</h3>
                    <p>View and manage customer orders</p>
                </span>
                <span class="quick-card-chevron">&#8250;</span>
            </a>

            <a href="pages/admin/member_listing.php" class="quick-card quick-card-row">
                <span class="quick-card-icon quick-card-icon-purple">&#128101;</span>
                <span class="quick-card-text">
                    <h3>Member Listing</h3>
                    <p>View and manage members</p>
                </span>
                <span class="quick-card-chevron">&#8250;</span>
            </a>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>

/* ==================================================
   ORDERS OVERVIEW - LINE CHART
================================================== */
const ordersCtx = document.getElementById('ordersChart').getContext('2d');

new Chart(ordersCtx, {
    type: 'line',
    data: {
        labels: <?= json_encode($day_labels) ?>,
        datasets: [{
            data: <?= json_encode($day_counts) ?>,
            borderColor: '#1976d2',
            backgroundColor: 'rgba(25, 118, 210, 0.08)',
            borderWidth: 2.5,
            pointBackgroundColor: '#1976d2',
            pointRadius: 4,
            pointHoverRadius: 6,
            tension: 0.35,
            fill: true
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: function (context) {
                        return context.label + ': ' + context.parsed.y + ' Orders';
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: { stepSize: 5 },
                grid: { color: '#f0f0f0' }
            },
            x: {
                grid: { display: false }
            }
        }
    }
});

/* ==================================================
   PRODUCT STOCK STATUS - DOUGHNUT CHART
================================================== */
const stockCtx = document.getElementById('stockChart').getContext('2d');

new Chart(stockCtx, {
    type: 'doughnut',
    data: {
        labels: ['In Stock', 'Low Stock', 'Out of Stock'],
        datasets: [{
            data: [<?= $in_stock_count ?>, <?= $low_stock_count ?>, <?= $out_of_stock_count ?>],
            backgroundColor: ['#1e7a34', '#e67e22', '#dc3545'],
            borderWidth: 0
        }]
    },
    options: {
        responsive: false,
        cutout: '72%',
        plugins: {
            legend: { display: false },
            tooltip: { enabled: true }
        }
    }
});
</script>
<?php require "_foot.php"; ?>