<?php

// ============================================================================
// PHP Setups
// ============================================================================

date_default_timezone_set('Asia/Kuala_Lumpur');
session_start();

// Compatibility for sessions created before the merge. The profile module
// stores a user object, while the shop modules use individual session keys.
if (!isset($_SESSION['user_id']) && isset($_SESSION['users']) && is_object($_SESSION['users'])) {
    $session_user = $_SESSION['users'];
    if (isset($session_user->user_id)) {
        $_SESSION['user_id'] = (int) $session_user->user_id;
        $_SESSION['name'] = $session_user->username ?? '';
        $_SESSION['role'] = $session_user->role ?? 'member';
    }
}

// ============================================================================
// General Page Functions
// ============================================================================

// Is GET request?
function is_get() {
    return $_SERVER['REQUEST_METHOD'] == 'GET';
}

// Is POST request?
function is_post() {
    return $_SERVER['REQUEST_METHOD'] == 'POST';
}

// Obtain GET parameter
function get($key, $value = null) {
    $value = $_GET[$key] ?? $value;
    return is_array($value) ? array_map('trim', $value) : trim($value);
}

// Obtain POST parameter
function post($key, $value = null) {
    $value = $_POST[$key] ?? $value;
    return is_array($value) ? array_map('trim', $value) : trim($value);
}

// Obtain REQUEST (GET and POST) parameter
function req($key, $value = null) {
    $value = $_REQUEST[$key] ?? $value;

    if ($value === null) {
        return null;
    }

    return is_array($value) ? array_map('trim', $value) : trim($value);
}

// Redirect to URL
function redirect($url = null) {
    $url ??= $_SERVER['REQUEST_URI'];
    header("Location: $url");
    exit();
}

// Set or get temporary session variable
function temp($key, $value = null) {
    if ($value !== null) {
        $_SESSION["temp_$key"] = $value;
    }
    else {
        $value = $_SESSION["temp_$key"] ?? null;
        unset($_SESSION["temp_$key"]);
        return $value;
    }
}

// ============================================================================
// HTML Helpers
// ============================================================================

// Encode HTML special characters
function encode($value) {
    return htmlentities($value);
}

// Generate <input type='text'>
function html_text($key, $attr = '') {
    $value = encode($GLOBALS[$key] ?? '');
    echo "<input type='text' id='$key' name='$key' value='$value' $attr>";
}

// Generate <input type='radio'> list
function html_radios($key, $items, $br = false) {
    $value = encode($GLOBALS[$key] ?? '');
    echo '<div>';
    foreach ($items as $id => $text) {
        $state = $id == $value ? 'checked' : '';
        echo "<label><input type='radio' id='{$key}_$id' name='$key' value='$id' $state>$text</label>";
        if ($br) {
            echo '<br>';
        }
    }
    echo '</div>';
}

// Generate <select>
function html_select($key, $items, $default = '- Select One -', $attr = '') {
    $value = encode($GLOBALS[$key] ?? '');
    echo "<select id='$key' name='$key' $attr>";
    if ($default !== null) {
        echo "<option value=''>$default</option>";
    }
    foreach ($items as $id => $text) {
        $state = $id == $value ? 'selected' : '';
        echo "<option value='$id' $state>$text</option>";
    }
    echo '</select>';
}

// ============================================================================
// Check Price helper function
// ============================================================================
function variant_price($base_price, $size) {
    if (str_contains($size, 'Mini'))   return $base_price * 1.10;
    if (str_contains($size, 'Medium')) return $base_price * 1.20;
    if (str_contains($size, 'Mega'))   return $base_price * 1.40;

    return $base_price; // Micro/default
}

function valid_phone($phone) {
    return preg_match('/^[0-9+\-\s]{8,20}$/', $phone);
}

function is_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function valid_positive_int($value) {
    return filter_var($value, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1]
    ]) !== false;
}

// ============================================================================
// Reward Points
// ============================================================================

const POINTS_PER_RINGGIT = 100;
const POINTS_EARNED_PER_RM10 = 10;
const MAX_POINTS_REDEMPTION_RATE = 0.10;

function points_to_ringgit($points) {
    return $points / POINTS_PER_RINGGIT;
}

function maximum_redeemable_points($subtotal) {
    return (int) floor($subtotal * MAX_POINTS_REDEMPTION_RATE * POINTS_PER_RINGGIT);
}

function earned_reward_points($amount_paid) {
    return (int) floor($amount_paid / 10) * POINTS_EARNED_PER_RM10;
}

// ============================================================================
// E-receipt email
// ============================================================================

function get_mail() {
    $config_file = __DIR__ . '/mail_config.php';
    $mailer_file = __DIR__ . '/lib/PHPMailer.php';
    $smtp_file = __DIR__ . '/lib/SMTP.php';

    if (!is_file($config_file) || !is_file($mailer_file) || !is_file($smtp_file)) {
        throw new RuntimeException('Email has not been configured.');
    }

    $mail_config = require $config_file;
    if (empty($mail_config['enabled'])) {
        throw new RuntimeException('Email sending is disabled.');
    }

    require_once $mailer_file;
    require_once $smtp_file;

    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->SMTPAuth = true;
    $mail->Host = $mail_config['host'];
    $mail->Port = (int) $mail_config['port'];
    $mail->Username = $mail_config['username'];
    $mail->Password = $mail_config['password'];
    $mail->CharSet = 'utf-8';
    $mail->setFrom($mail_config['from_email'], $mail_config['from_name']);

    return $mail;
}

function receipt_html($order, $items) {
    $rows = '';
    foreach ($items as $item) {
        $line_total = (float) $item->price * (int) $item->quantity;
        $rows .= '<tr>'
            . '<td style="padding:10px;border-bottom:1px solid #e5e7eb;">' . encode($item->name)
            . '<br><span style="color:#6b7280;font-size:12px;">' . encode($item->size)
            . ' &times; ' . (int) $item->quantity . '</span></td>'
            . '<td style="padding:10px;border-bottom:1px solid #e5e7eb;text-align:right;">RM '
            . number_format($line_total, 2) . '</td></tr>';
    }

    $points_row = '';
    if ((int) $order->points_used > 0) {
        $points_row = '<tr><td style="padding:8px 10px;">Reward points discount</td><td style="padding:8px 10px;text-align:right;">- RM '
            . number_format((float) $order->points_discount, 2) . '</td></tr>';
    }

    $payment_method = ucwords(str_replace('_', ' ', $order->payment_method));
    $address = nl2br(encode($order->shipping_address));

    return '<!doctype html><html><body style="margin:0;background:#f3f4f6;font-family:Arial,sans-serif;color:#1f2937;">'
        . '<div style="max-width:640px;margin:24px auto;background:#ffffff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;">'
        . '<div style="padding:24px;background:#0284c7;color:#fff;"><h1 style="margin:0;font-size:24px;">SippyGo</h1><p style="margin:8px 0 0;">E-Receipt for Order #' . (int) $order->order_id . '</p></div>'
        . '<div style="padding:24px;"><p>Hi ' . encode($order->recipient_name) . ',</p><p>Thank you for your order. Your e-receipt is below.</p>'
        . '<p style="line-height:1.6;"><strong>Order date:</strong> ' . date('d M Y, h:i A', strtotime($order->order_date)) . '<br>'
        . '<strong>Payment method:</strong> ' . encode($payment_method) . '<br>'
        . '<strong>Payment status:</strong> ' . encode(ucfirst($order->payment_status)) . '</p>'
        . '<table style="width:100%;border-collapse:collapse;margin:20px 0;"><thead><tr style="background:#f9fafb;"><th style="padding:10px;text-align:left;">Item</th><th style="padding:10px;text-align:right;">Amount</th></tr></thead><tbody>' . $rows . '</tbody></table>'
        . '<table style="width:100%;border-collapse:collapse;"><tr><td style="padding:8px 10px;">Subtotal</td><td style="padding:8px 10px;text-align:right;">RM ' . number_format((float) $order->subtotal_amount, 2) . '</td></tr>'
        . $points_row
        . '<tr style="font-size:18px;font-weight:bold;background:#f9fafb;"><td style="padding:12px 10px;">Amount paid</td><td style="padding:12px 10px;text-align:right;">RM ' . number_format((float) $order->total_amount, 2) . '</td></tr></table>'
        . '<p style="margin:24px 0 4px;"><strong>Delivery address</strong><br>' . $address . '</p>'
        . '<p style="color:#6b7280;font-size:13px;margin-top:24px;">Please keep this email as your proof of purchase.</p></div></div></body></html>';
}

function send_order_receipt($order_id, $user_id) {
    global $_db;

    $order_stmt = $_db->prepare('SELECT o.*, u.email FROM orders o JOIN users u ON u.user_id = o.user_id WHERE o.order_id = ? AND o.user_id = ?');
    $order_stmt->execute([$order_id, $user_id]);
    $order = $order_stmt->fetch();

    if (!$order || !is_email($order->email)) {
        throw new RuntimeException('A receipt cannot be sent for this order.');
    }

    $item_stmt = $_db->prepare('SELECT oi.size, oi.quantity, oi.price, p.name FROM order_items oi JOIN products p ON p.product_id = oi.product_id WHERE oi.order_id = ? ORDER BY oi.order_item_id');
    $item_stmt->execute([$order_id]);
    $items = $item_stmt->fetchAll();

    $mail = get_mail();
    $mail->addAddress($order->email, $order->recipient_name);
    $mail->isHTML(true);
    $mail->Subject = 'SippyGo e-Receipt - Order #' . $order->order_id;
    $mail->Body = receipt_html($order, $items);
    $mail->AltBody = 'Thank you for your order. E-receipt for order #' . $order->order_id . '. Amount paid: RM ' . number_format((float) $order->total_amount, 2) . '.';
    $mail->send();
}

// ============================================================================
// Error Handlings
// ============================================================================

// Global error array
$_err = [];

// Generate <span class='err'>
function err($key) {
    global $_err;
    if ($_err[$key] ?? false) {
        echo "<span class='err'>$_err[$key]</span>";
    }
    else {
        echo '<span></span>';
    }
}

// ============================================================================
// Security
// ============================================================================

// Global user object
$_user = $_SESSION['user'] ?? $_SESSION['users'] ?? null;

function auth(...$roles) {
    global $_user;
    if ($_user) {
        if ($roles) {
            if (in_array($_user->role, $roles)) {
                return; // OK
            }
        }
        else {
            return; // OK
        }
    }
    
    redirect('/login.php');
}

// Use this on every admin-only route. Navigation links are not a security
// boundary: the role must be checked again when a URL is requested directly.
function require_admin($redirect_to = 'products.php') {
    if (($_SESSION['role'] ?? '') === 'admin') {
        return;
    }

    temp('info', 'You do not have permission to access that page.');
    redirect($redirect_to);
}

// ============================================================================
// Database Setups and Functions
// ============================================================================

// Global PDO object
$_db = new PDO('mysql:dbname=waterbottle_shop', 'root', '', [
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
]);

// Is unique?
function is_unique($value, $table, $field) {
    global $_db;
    $stm = $_db->prepare("SELECT COUNT(*) FROM $table WHERE $field = ?");
    $stm->execute([$value]);
    return $stm->fetchColumn() == 0;
}

// Is exists?
function is_exists($value, $table, $field) {
    global $_db;
    $stm = $_db->prepare("SELECT COUNT(*) FROM $table WHERE $field = ?");
    $stm->execute([$value]);
    return $stm->fetchColumn() > 0;
}

// ============================================================================
// Global Constants and Variables
// ============================================================================

$_genders = [
    'F' => 'Female',
    'M' => 'Male',
];

// ============================================================================
// Access Control - require login for every page except these public ones
// ============================================================================

$_public_pages = [
    'login.php',
    'register.php',
    'forgot_password.php',
    'reset_password.php',
    'index.php',
    'products.php',
    'product_detail.php',
];

$_current_page = basename($_SERVER['PHP_SELF']);

if (!isset($_SESSION['user_id']) && !in_array($_current_page, $_public_pages)) {
    temp('info', 'Please login to continue.');
    redirect('login.php');
}
