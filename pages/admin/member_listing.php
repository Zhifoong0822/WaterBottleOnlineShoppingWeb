<?php
// Core Components
require_once '../../_base.php';
require_admin('/products.php');
$_title = 'Member Listing';

if (is_post() && isset($_FILES['csv_file'])) {
    $csvFile = $_FILES['csv_file'];

    if ($csvFile['error'] !== UPLOAD_ERR_OK) {
        die('CSV upload failed.');
    }

    $handle = fopen($csvFile['tmp_name'], 'r');

    if (!$handle) {
        die('Unable to open CSV file.');
    }

    // Read header
    $header = fgetcsv($handle, 0, ',', '"', '\\');

    $expectedHeader = ['username', 'email', 'password'];

    if ($header !== $expectedHeader) {
        fclose($handle);
        die('Invalid CSV format.');
    }

    $insertSQL = "
        INSERT INTO users
            (username, email, password, role, profilepic, reward_points)
        VALUES
            (:username, :email, :password, 'member', NULL, 0)
    ";

    $stmt = $_db->prepare($insertSQL);

    $imported = 0;
    $skipped = 0;
    $errors = [];

    $rowNumber = 1;

    while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
        $rowNumber++;

        if (count($row) < 3) {
            $skipped++;
            $errors[] = "Row {$rowNumber}: Invalid number of columns.";
            continue;
        }

        $username = trim($row[0]);
        $email    = trim($row[1]);
        $password = $row[2];

        if ($username === '' || $email === '' || $password === '') {
            $skipped++;
            $errors[] = "Row {$rowNumber}: Username, email and password are required.";
            continue;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $skipped++;
            $errors[] = "Row {$rowNumber}: Invalid email address.";
            continue;
        }

        // Check duplicate username/email
        $check = $_db->prepare("
            SELECT user_id
            FROM users
            WHERE username = :username
               OR email = :email
            LIMIT 1
        ");

        $check->execute([
            ':username' => $username,
            ':email'    => $email
        ]);

        if ($check->fetch()) {
            $skipped++;
            $errors[] = "Row {$rowNumber}: Username or email already exists.";
            continue;
        }

        try {
            $stmt->execute([
                ':username' => $username,
                ':email'    => $email,
                ':password' => password_hash($password, PASSWORD_DEFAULT)
            ]);

            $imported++;
        } catch (PDOException $e) {
            $skipped++;
            $errors[] = "Row {$rowNumber}: Unable to import member.";
        }
    }

    fclose($handle);

    temp('info', "Import completed! Imported: {$imported}, Skipped: {$skipped}.");

    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

include '../../_head.php';

// Define user fields
$userFields = [
    'user_id'       => 'Id',
    'username'      => 'Username',
    'email'         => 'Email',
    // 'role'          => 'Role',
    'created_at'    => 'Created At',
];

// Page Query Parameters
$search = trim(req('search', ''));

$sort = req('sort', '');
key_exists($sort, $userFields) || $sort = 'created_at';

$dir = req('dir', '');
in_array($dir, ['asc', 'desc']) || $dir = 'asc';

$page = max(1, intval(req('page', 1)));
$pageSize = 10;
$pageOffset = ($page - 1) * $pageSize;

// Database Query
$selectSQL = "SELECT user_id, username, email, created_at";
$countSQL = "SELECT COUNT(*) AS total_members";

$baseSQL = "
    FROM users
    WHERE 1=1
    AND role = 'member'
    AND (username LIKE '%{$search}%' OR email LIKE '%{$search}%')
";

$orderSQL = "ORDER BY {$sort} {$dir}";
$paginationSQL = "LIMIT {$pageSize} OFFSET {$pageOffset}";

$total_members = $_db->query("{$countSQL} {$baseSQL}")->fetchColumn();
$total_pages = max(1, (int) ceil($total_members / $pageSize));
$page > $total_pages && $page = $total_pages;

$members = $_db->query("{$selectSQL} {$baseSQL} {$orderSQL} {$paginationSQL}")->fetchAll(PDO::FETCH_ASSOC);

?>

<link rel="stylesheet" href="../../css/admin.css">
<link rel="stylesheet" href="../../css/main.css">
<link rel="stylesheet" href="../../css/orders.css">

<div class="admin-orders-page">
    <!-- Main Bar -->
    <div class="page-header" style="justify-content: space-between;">
        <a class="back-link" href="/" style="margin: 0px;">
            ← Back to Home
        </a>

        <div class="order-count">
            Total Members: <?= $total_members ?>
        </div>
    </div>

    <!-- Main Container -->
    <div class="admin-order-card">

        <!-- Search Bar -->
        <div class="table-tools" style="justify-content: normal;">
            <input
                id="searchInput"
                type="text"
                class="search-box"
                placeholder="Search by username or email"
                value="<?= encode($search) ?>"
            >

            <button id="searchBtn" class="add-button">
                Search
            </button>

            <form id="importForm" method="post" enctype="multipart/form-data">
                <input
                    type="file"
                    name="csv_file"
                    id="csvFile"
                    accept=".csv,text/csv"
                    hidden
                >

                <button id="ImportBtn" type="button" class="receipt-button">
                    Import Members
                </button>
            </form>
        </div>

        <!-- Table -->
        <div style="margin: 10px;">
            <table class="admin-order-table">
                <!-- Table Header -->
                <thead>
                    <tr>
                        <?php foreach ($userFields as $field => $label): ?>
                            <th data-field="<?= encode($field) ?>" style="cursor: pointer;">
                                <?= encode($label) ?>
                                <?php if ($sort == $field): ?>
                                    <?= $dir == 'asc' ? '▲' : '▼' ?>
                                <?php endif; ?>
                            </th>
                        <?php endforeach; ?>
                        <th>Actions</th>
                    </tr>
                </thead>

                <!-- Table Content -->
                <tbody>
                    <?php if ($total_members < 1): ?>
                        <tr>
                            <td colspan="<?= count($userFields) ?>" class="no-orders">
                                No members found.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($members as $member): ?>
                            <tr>
                                <?php foreach ($userFields as $field => $label): ?>
                                    <td>
                                        <?php if ($field == 'created_at'): ?>
                                            <?= date('Y-m-d', strtotime($member[$field])) ?>
                                        <?php else: ?>
                                            <?= encode($member[$field]) ?>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                                
                                <td class="action-buttons">
                                    <a href="member_details.php?id=<?= $member['user_id'] ?>&page=<?= $page ?>"
                                    class="btn-view" style="text-align: center; font-size: medium;">
                                        View
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>                    
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div style="display: flex; gap: 8px; justify-content: center; align-items: center; margin: 30px 0;">
                <!-- Previous Page Link -->
                <?php if ($page > 1): ?>
                    <button 
                        style="padding: 8px 14px; border: 1px solid #ccc; border-radius: 4px; text-decoration: none; color: #333;"
                        data-page="<?= $page - 1 ?>"
                    >
                        &laquo; Prev
                    </button>
                <?php endif; ?>

                <!-- Numbered Page Links -->
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <button 
                        style="padding: 8px 14px; border: 1px solid #ccc; border-radius: 4px; text-decoration: none; <?= ($i === $page) ? 'background: #111; color: #fff; font-weight: bold;' : 'color: #333;' ?>"
                        data-page="<?= $i ?>"
                    >
                        <?= $i ?>
                    </button>
                <?php endfor; ?>

                <!-- Next Page Link -->
                <?php if ($page < $total_pages): ?>
                    <button 
                        style="padding: 8px 14px; border: 1px solid #ccc; border-radius: 4px; text-decoration: none; color: #333;"
                        data-page="<?= $page + 1 ?>"
                    >
                        Next &raquo;
                    </button>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../../_foot.php'; ?>

<script>
$(function () {
    function redirectPage(search, sort, dir, page) {
        const params = new URLSearchParams({
            search: search ?? $("#searchInput").val().trim(),
            sort: sort ?? "<?= encode($sort) ?>",
            dir: dir ?? "<?= encode($dir) ?>",
            page: page ?? "<?= encode($page) ?>"
        });

        window.location.href = "?" + params.toString();
    }
 
    // Handle table sorting
    $("[data-field]").on("click", function () {
        const sort = $(this).data("field");
        let dir = "asc";

        if (sort === "<?= encode($sort) ?>") {
            dir = "<?= encode($dir === 'asc' ? 'desc' : 'asc') ?>";
        }

        redirectPage(null, sort, dir, null);
    });

    // Handle search button
    $("#searchBtn").on("click", function () {
        redirectPage(null, null, null, null);
    });

    // Search when Enter is pressed
    $("#searchInput").on("keypress", function (e) {
        // Check if Enter key is pressed
        if (e.which === 13) {
            redirectPage(null, null, null, null);
        }
    });

    // Handle pagination buttons
    $("[data-page]").on("click", function () {
        const page = $(this).data("page");
        redirectPage(null, null, null, page);
    });

    // Handle import button
    $("#ImportBtn").on("click", function () {
        $("#csvFile").click();
    });

    $("#csvFile").on("change", function () {
        if (this.files.length > 0) {
            $("#importForm").submit();
        }
    });
});
</script>
