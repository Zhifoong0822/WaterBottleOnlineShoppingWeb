<?php
// ============================================================================
// ONE-TIME SETUP SCRIPT — creates demo accounts you can log in with directly.
// Run this once in your browser (e.g. http://localhost/yourproject/demo_seed.php),
// then delete this file — it should not stay on a live site.
// ============================================================================

$db = new PDO('mysql:dbname=waterbottle_shop', 'root', '', [
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
]);

$demo_accounts = [
    ['username' => 'daniel',        'email' => 'daniel@example.com',    'password' => 'Daniel@123',    'role' => 'admin'],
    ['username' => 'demo_customer', 'email' => 'customer@demo.com',      'password' => 'Customer@123',  'role' => 'member'],
];

foreach ($demo_accounts as $acc) {
    // password_hash() generates a real bcrypt hash — this is what password_verify() in login.php checks against
    $hashed = password_hash($acc['password'], PASSWORD_DEFAULT);

    $stmt = $db->prepare("SELECT user_id FROM users WHERE email = ?");
    $stmt->execute([$acc['email']]);
    $existing = $stmt->fetch();

    if ($existing) {
        $stmt = $db->prepare("UPDATE users SET username = ?, password = ?, role = ? WHERE user_id = ?");
        $stmt->execute([$acc['username'], $hashed, $acc['role'], $existing->user_id]);
        echo "Updated existing account: {$acc['email']}<br>";
    }
    else {
        $stmt = $db->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
        $stmt->execute([$acc['username'], $acc['email'], $hashed, $acc['role']]);
        echo "Created new account: {$acc['email']}<br>";
    }
}

echo "<hr><strong>Done. Demo login credentials:</strong><br>";
foreach ($demo_accounts as $acc) {
    echo ucfirst($acc['role']) . ": {$acc['email']} / {$acc['password']}<br>";
}
echo "<br><em>Delete this file (demo_seed.php) now that the accounts are created.</em>";
