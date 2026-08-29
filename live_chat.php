<?php
// Core Components

require_once '_base.php';
$_title = 'Live Chat';

$users = $_SESSION['users'];
if (!isset($users)) {
    redirect('/login.php');
    exit;
}

// Current User
$user_id = $users->user_id;
$user_role = $users->role;
$user_is_admin = $user_role === 'admin';
?>

<?php
// Handle AJAX Request

if (is_post() && post('action') === 'create_chat') {
    // Only members can create chat sessions
    if ($user_is_admin) {
        echo json_encode([
            'success' => false,
            'message' => 'Admins cannot create customer chat sessions.'
        ]);
        exit;
    }

    // Check for existing active chat
    $activeChatStmt = $_db->prepare("
        SELECT
            chat_session_id,
            user_id,
            status
        FROM chat_sessions
        WHERE user_id = :userId
        AND status = 1
        ORDER BY chat_session_id DESC
        LIMIT 1
    ");

    $activeChatStmt->execute([
        ':userId' => $user_id
    ]);

    $activeChat = $activeChatStmt->fetch(PDO::FETCH_ASSOC);

    if ($activeChat) {
        echo json_encode([
            'success' => true,
            'chatSession' => [
                'chat_session_id' => (int)$activeChat['chat_session_id'],
                'user_id' => (int)$activeChat['user_id'],
                'status' => (int)$activeChat['status']
            ],
            'created' => false
        ]);
        exit;
    }

    // Identify user id of first admin account (Fallback = 2)
    $adminStmt = $_db->prepare("
        SELECT user_id
        FROM users
        WHERE role = 'admin'
        ORDER BY user_id ASC
        LIMIT 1
    ");
    $adminStmt->execute();
    $adminUserId = $adminStmt->fetchColumn() ?? 2;

    // Create new chat session
    $createChatStmt = $_db->prepare("
        INSERT INTO chat_sessions (
            user_id,
            status
        )
        VALUES (
            :userId,
            1
        )
    ");

    $createChatStmt->execute([
        ':userId' => $user_id
    ]);

    $chat_session_id = (int)$_db->lastInsertId();

    // Initial message
   $welcomeMessage = <<<EOD
        Hi! 👋 Welcome! This is an automatically generated message.

        Thank you for starting a chat with us. Before a staff member can assist you, please send us a message with your question, request, or any details you would like to share.

        Once we receive your message, a staff member will review it and get back to you as soon as possible.

        We look forward to hearing from you!
    EOD;

    $welcomeStmt = $_db->prepare("
        INSERT INTO chat_messages (
            chat_session_id,
            user_id,
            message,
            created_at
        )
        VALUES (
            :chatSessionId,
            :userId,
            :message,
            NOW()
        )
    ");

    $welcomeStmt->execute([
        ':chatSessionId' => $chat_session_id,
        ':userId' => $adminUserId,
        ':message' => $welcomeMessage
    ]);

    echo json_encode([
        'success' => true,

        'chatSession' => [
            'chat_session_id' => $chat_session_id,
            'user_id' => $user_id,
            'status' => 1
        ],

        'created' => true
    ]);

    exit;
}

if (is_post() && post('action') === 'send_message') {

    $chat_session_id = intval(post('chat_session_id', 0));
    $message = trim(post('message', ''));

    // Validate form data
    if ($chat_session_id <= 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid chat session.'
        ]);
        exit;
    }

    if ($message === '') {
        echo json_encode([
            'success' => false,
            'message' => 'Message cannot be empty.'
        ]);
        exit;
    }

    if (mb_strlen($message) > 1000) {
        echo json_encode([
            'success' => false,
            'message' => 'Message is too long.'
        ]);
        exit;
    }

    // Get Chat Session data from DB
    $chatSessionStmt = $_db->prepare("
        SELECT
            chat_session_id,
            user_id,
            status
        FROM chat_sessions
        WHERE chat_session_id = :chatSessionId
        LIMIT 1
    ");

    $chatSessionStmt->execute([
        ':chatSessionId' => $chat_session_id
    ]);

    $chatSession = $chatSessionStmt->fetch(PDO::FETCH_ASSOC);

    if (!$chatSession) {
        echo json_encode([
            'success' => false,
            'message' => 'Chat session not found.'
        ]);
        exit;
    }

    // Only allow the chat session creator and admins to send messages
    if (
        !$user_is_admin &&
        (int)$chatSession['user_id'] !== (int)$user_id
    ) {
        echo json_encode([
            'success' => false,
            'message' => 'You do not have permission to use this chat.'
        ]);
        exit;
    }

    // Customers can reopen closed/resolved chats by messaging
    // if (!$user_is_admin && (int)$chatSession['status'] !== 1) {

    //     $reopenChatStmt = $_db->prepare("
    //         UPDATE chat_sessions
    //         SET status = 1
    //         WHERE chat_session_id = :chatSessionId
    //         AND user_id = :userId
    //         LIMIT 1
    //     ");

    //     $reopenChatStmt->execute([
    //         ':chatSessionId' => $chat_session_id,
    //         ':userId' => $user_id
    //     ]);

    //     $chatSession['status'] = 1;
    // }

    // Admins can only send messages when chat is active
    // if ($user_is_admin && (int)$chatSession['status'] !== 1) {
    //     echo json_encode([
    //         'success' => false,
    //         'message' => 'Please reopen this chat before sending a message.'
    //     ]);
    //     exit;
    // }
    

    // Only active chats can receive messages
    if ((int)$chatSession['status'] !== 1) {
        echo json_encode([
            'success' => false,
            'message' => 'This chat is no longer active.'
        ]);
        exit;
    }

    // Insert Message into DB
    $inputMessageStmt = $_db->prepare("
        INSERT INTO chat_messages (
            chat_session_id,
            user_id,
            message,
            created_at
        )
        VALUES (
            :chatSessionId,
            :userId,
            :message,
            NOW()
        )
    ");

    $inputMessageStmt->execute([
        ':chatSessionId' => $chat_session_id,
        ':userId' => $user_id,
        ':message' => $message
    ]);

    $message_id = (int)$_db->lastInsertId();

    // Get inserted message data
    $inputMessageStmt = $_db->prepare("
        SELECT
            cm.chat_message_id,
            cm.chat_session_id,
            cm.user_id,
            cm.message,
            cm.created_at,
            u.username,
            u.role
        FROM chat_messages cm
        INNER JOIN users u
            ON u.user_id = cm.user_id
        WHERE cm.chat_message_id = :messageId
        LIMIT 1
    ");

    $inputMessageStmt->execute([
        ':messageId' => $message_id
    ]);

    $newMessage = $inputMessageStmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,

        'chatSession' => [
            'chat_session_id' => (int)$chatSession['chat_session_id'],
            'status' => (int)$chatSession['status'],

            'last_message_sender' => 'You',
            'last_message' => (string)$newMessage['message'],
            'last_message_at' => (string)$newMessage['created_at'],
        ],

        'message' => [
            'chat_message_id' => (int)$newMessage['chat_message_id'],
            'chat_session_id' => (int)$newMessage['chat_session_id'],
            'user_id' => (int)$newMessage['user_id'],
            'sender_username' => 'You',
            'message' => (string)$newMessage['message'],
            'created_at' => (string)$newMessage['created_at'],
            'is_admin' => $newMessage['role'] === 'admin' ? 1 : 0,
        ]
    ]);

    exit;
}

if (is_post() && post('action') === 'get_new_messages') {

    $chat_session_id = intval(post('chat_session_id', 0));
    $last_message_id = intval(post('last_message_id', 0));

    // Validate form data
    if ($chat_session_id <= 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid chat session.'
        ]);
        exit;
    }

    // Get chat session
    $chatSessionStmt = $_db->prepare("
        SELECT
            chat_session_id,
            user_id,
            status
        FROM chat_sessions
        WHERE chat_session_id = :chatSessionId
        LIMIT 1
    ");

    $chatSessionStmt->execute([
        ':chatSessionId' => $chat_session_id
    ]);

    $chatSession = $chatSessionStmt->fetch(PDO::FETCH_ASSOC);

    if (!$chatSession) {
        echo json_encode([
            'success' => false,
            'message' => 'Chat session not found.'
        ]);
        exit;
    }

    // Only the chat owner and admins can read this chat
    if (
        !$user_is_admin &&
        (int)$chatSession['user_id'] !== (int)$user_id
    ) {
        echo json_encode([
            'success' => false,
            'message' => 'You do not have permission to view this chat.'
        ]);
        exit;
    }

    // Get messages newer than the last message we already have
    $messagesStmt = $_db->prepare("
        SELECT
            cm.chat_message_id,
            cm.chat_session_id,
            cm.user_id,
            cm.message,
            cm.created_at,

            (
                CASE
                    WHEN (:isAdmin = 1 AND u.role = 'admin') OR (cm.user_id = :userId) THEN 'You'
                    WHEN (:isAdmin <> 1 AND u.role = 'admin') THEN 'Support Staff'
                    ELSE u.username
                END
            ) AS sender_username,

            (
                CASE
                    WHEN u.role = 'admin' THEN 1
                    ELSE 0
                END
            ) AS is_admin

        FROM chat_messages cm

        INNER JOIN users u
            ON u.user_id = cm.user_id

        WHERE cm.chat_session_id = :chatSessionId
        AND cm.chat_message_id > :lastMessageId

        ORDER BY cm.chat_message_id ASC
    ");

    $messagesStmt->execute([
        ':isAdmin' => (int)$user_is_admin,
        ':userId' => (int)$user_id,
        ':chatSessionId' => $chat_session_id,
        ':lastMessageId' => $last_message_id
    ]);

    $newMessages = $messagesStmt->fetchAll(PDO::FETCH_ASSOC);

    // Convert IDs to integers
    foreach ($newMessages as &$message) {
        $message['chat_message_id'] = (int)$message['chat_message_id'];
        $message['chat_session_id'] = (int)$message['chat_session_id'];
        $message['user_id'] = (int)$message['user_id'];
        $message['sender_username'] = (string)$message['sender_username'];
        $message['message'] = (string)$message['message'];
        $message['created_at'] = (string)$message['created_at'];
        $message['is_admin'] = (int)$message['is_admin'];
    }

    unset($message);

    $lastMessage = !empty($newMessages)
        ? end($newMessages)
        : null;

    echo json_encode([
        'success' => true,

        'chatSession' => [
            'chat_session_id' => (int)$chatSession['chat_session_id'],
            'status' => (int)$chatSession['status'],

            'last_message_sender' => $lastMessage
                ? (string)$lastMessage['sender_username']
                : '',

            'last_message' => $lastMessage
                ? (string)$lastMessage['message']
                : '',

            'last_message_at' => $lastMessage
                ? (string)$lastMessage['created_at']
                : '',
        ],

        'messages' => $newMessages
    ]);

    exit;
}

if (is_post() && post('action') === 'get_chat_updates') {

    $chatSessionsStmt = $_db->prepare("
        SELECT
            cs.chat_session_id,
            cs.user_id,
            u.username,
            cs.status,
            cs.created_at,
            cs.updated_at,

            (
                SELECT cm.message
                FROM chat_messages cm
                WHERE cm.chat_session_id = cs.chat_session_id
                ORDER BY cm.created_at DESC
                LIMIT 1
            ) AS last_message,

            (
                SELECT
                    CASE
                        WHEN (:isAdmin = 1 AND u.role = 'admin') OR (cm.user_id = :userId) THEN 'You'
                        WHEN (:isAdmin <> 1 AND u.role = 'admin') THEN 'Support Staff'
                        ELSE u.username
                    END
                FROM chat_messages cm
                INNER JOIN users u
                    ON u.user_id = cm.user_id
                WHERE cm.chat_session_id = cs.chat_session_id
                ORDER BY cm.created_at DESC
                LIMIT 1
            ) AS last_message_sender,

            (
                SELECT cm.created_at
                FROM chat_messages cm
                WHERE cm.chat_session_id = cs.chat_session_id
                ORDER BY cm.created_at DESC
                LIMIT 1
            ) AS last_message_at

        FROM chat_sessions cs

        INNER JOIN users u
            ON u.user_id = cs.user_id

        WHERE
            :isAdmin = 1
            OR cs.user_id = :userId

        ORDER BY COALESCE(last_message_at, cs.created_at) DESC
    ");

    $chatSessionsStmt->execute([
        ':isAdmin' => (int)$user_is_admin,
        ':userId' => (int)$user_id
    ]);

    $chatUpdates = $chatSessionsStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($chatUpdates as &$chat) {
        $chat['chat_session_id'] = (int)$chat['chat_session_id'];
        $chat['user_id'] = (int)$chat['user_id'];
        $chat['status'] = (int)$chat['status'];

        $chat['last_message_sender'] = (string)$chat['last_message_sender'];
        $chat['last_message'] = (string)$chat['last_message'];
        $chat['last_message_at'] = (string)$chat['last_message_at'];
    }

    unset($chat);

    echo json_encode([
        'success' => true,
        'chats' => $chatUpdates
    ]);

    exit;
}

if (is_post() && post('action') === 'update_chat_status') {

    // Only admins can change chat status
    if (!$user_is_admin) {
        echo json_encode([
            'success' => false,
            'message' => 'You do not have permission to update chat status.'
        ]);
        exit;
    }

    $chat_session_id = intval(post('chat_session_id', 0));
    $status = intval(post('status', -1));

    // Validate form data
    if ($chat_session_id <= 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid chat session.'
        ]);
        exit;
    }

    if (!in_array($status, [0, 1, 2], true)) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid chat status.'
        ]);
        exit;
    }

    // Check chat exists
    $chatSessionStmt = $_db->prepare("
        SELECT
            chat_session_id,
            status
        FROM chat_sessions
        WHERE chat_session_id = :chatSessionId
        LIMIT 1
    ");

    $chatSessionStmt->execute([
        ':chatSessionId' => $chat_session_id
    ]);

    $chatSession = $chatSessionStmt->fetch(PDO::FETCH_ASSOC);

    if (!$chatSession) {
        echo json_encode([
            'success' => false,
            'message' => 'Chat session not found.'
        ]);
        exit;
    }

    // Update status
    $updateStatusStmt = $_db->prepare("
        UPDATE chat_sessions
        SET status = :status
        WHERE chat_session_id = :chatSessionId
        LIMIT 1
    ");

    $updateStatusStmt->execute([
        ':status' => $status,
        ':chatSessionId' => $chat_session_id
    ]);

    echo json_encode([
        'success' => true,
        'chat_session_id' => $chat_session_id,
        'status' => $status
    ]);

    exit;
}

?>

<?php
// Load Page Data

// Query Params
$chatStatus = intval(req('status', 1));
$selectedChatId = intval(req('chat', 0));

$chatSessions = [];
$messages = [];

// Get Chat Sessions
$chatSessionsStmt = $_db->prepare("
    SELECT
        cs.chat_session_id,
        cs.user_id,
        u.username,
        cs.status,
        cs.created_at,
        cs.updated_at,

        (
            SELECT cm.message
            FROM chat_messages cm
            WHERE cm.chat_session_id = cs.chat_session_id
            ORDER BY cm.created_at DESC
            LIMIT 1
        ) AS last_message,

        (
            SELECT
                CASE
                    WHEN (:isAdmin = 1 AND u.role = 'admin')
                        OR (cm.user_id = :userId)
                        THEN 'You'
                    WHEN (:isAdmin <> 1 AND u.role = 'admin')
                        THEN 'Support Staff'
                    ELSE u.username
                END
            FROM chat_messages cm
            INNER JOIN users u
                ON u.user_id = cm.user_id
            WHERE cm.chat_session_id = cs.chat_session_id
            ORDER BY cm.created_at DESC
            LIMIT 1
        ) AS last_message_sender,

        (
            SELECT cm.created_at
            FROM chat_messages cm
            WHERE cm.chat_session_id = cs.chat_session_id
            ORDER BY cm.created_at DESC
            LIMIT 1
        ) AS last_message_at

    FROM chat_sessions cs

    INNER JOIN users u
        ON u.user_id = cs.user_id

    WHERE
        :chatStatus IN (cs.status, -1)

    AND (
        :isAdmin = 1
        OR cs.user_id = :userId
    )

    ORDER BY COALESCE(last_message_at, cs.created_at) DESC
");

$chatSessionsStmt->execute([
    ':isAdmin' => (int)$user_is_admin,
    ':userId' => (int)$user_id,
    ':chatStatus' => (int)$chatStatus,
]);

$chatSessions = $chatSessionsStmt->fetchAll(PDO::FETCH_ASSOC);

// Get chat messages
if ($user_is_admin) {
    // Select requested chat, or first chat as fallback
    if (!empty($chatSessions)) {
        $selectedChatExists = false;

        foreach ($chatSessions as $chat) {
            if ((int)$chat['chat_session_id'] === $selectedChatId) {
                $selectedChatExists = true;
                break;
            }
        }

        if (!$selectedChatExists) {
            $selectedChatId = (int)$chatSessions[0]['chat_session_id'];
        }
    }

    // Load messages for selected admin chat
    if ($selectedChatId > 0) {
        $messagesStmt = $_db->prepare("
            SELECT
                cm.chat_message_id,
                cm.chat_session_id,
                cm.user_id,
                cm.message,
                cm.created_at,

                CASE
                    WHEN (:isAdmin = 1 AND u.role = 'admin')
                        OR (cm.user_id = :userId)
                        THEN 'You'
                    WHEN (:isAdmin <> 1 AND u.role = 'admin')
                        THEN 'Support Staff'
                    ELSE u.username
                END AS sender_username,

                CASE
                    WHEN u.role = 'admin' THEN 1
                    ELSE 0
                END AS is_admin

            FROM chat_messages cm

            INNER JOIN users u
                ON u.user_id = cm.user_id

            WHERE cm.chat_session_id = :chatSessionId

            ORDER BY cm.created_at ASC
        ");

        $messagesStmt->execute([
            ':isAdmin' => (int)$user_is_admin,
            ':userId' => (int)$user_id,
            ':chatSessionId' => (int)$selectedChatId,
        ]);

        $messages = $messagesStmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
else {
    // Get/check for member chat
    $memberChatStmt = $_db->prepare("
        SELECT
            cs.chat_session_id,
            cs.user_id,
            cs.status,
            cs.created_at,
            cs.updated_at,

            (
                SELECT cm.message
                FROM chat_messages cm
                WHERE cm.chat_session_id = cs.chat_session_id
                ORDER BY cm.created_at DESC
                LIMIT 1
            ) AS last_message,

            (
                SELECT cm.created_at
                FROM chat_messages cm
                WHERE cm.chat_session_id = cs.chat_session_id
                ORDER BY cm.created_at DESC
                LIMIT 1
            ) AS last_message_at

        FROM chat_sessions cs

        WHERE cs.user_id = :userId

        ORDER BY 
            (CASE
                WHEN cs.status = 1 THEN 0
                ELSE 1
            END),
            cs.chat_session_id DESC
        

        LIMIT 1
    ");

    $memberChatStmt->execute([
        ':userId' => $user_id
    ]);

    $memberChat = $memberChatStmt->fetch(PDO::FETCH_ASSOC);

    if ($memberChat) {
        $memberChat['chat_session_id'] = (int)$memberChat['chat_session_id'];
        $memberChat['user_id'] = (int)$memberChat['user_id'];
        $memberChat['status'] = (int)$memberChat['status'];

        // Load messages for member chat
        $messagesStmt = $_db->prepare("
            SELECT
                cm.chat_message_id,
                cm.chat_session_id,
                cm.user_id,
                cm.message,
                cm.created_at,

                CASE
                    WHEN cm.user_id = :userId
                        THEN 'You'
                    WHEN u.role = 'admin'
                        THEN 'Support Staff'
                    ELSE u.username
                END AS sender_username,

                CASE
                    WHEN u.role = 'admin' THEN 1
                    ELSE 0
                END AS is_admin

            FROM chat_messages cm

            INNER JOIN users u
                ON u.user_id = cm.user_id

            WHERE cm.chat_session_id = :chatSessionId

            ORDER BY cm.created_at ASC
        ");

        $messagesStmt->execute([
            ':userId' => $user_id,
            ':chatSessionId' => $memberChat['chat_session_id'],
        ]);

        $messages = $messagesStmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Helpers
function getStatusLabel(int $status)
{
    switch ($status) {
        case 0:
            return 'Closed';

        case 1:
            return 'Active';

        case 2:
            return 'Resolved';

        default:
            return 'Unknown';
    }
}

function getTimeAgo(string $datetime)
{
    if ($datetime === '') {
        return '';
    }

    $time = strtotime($datetime);

    if ($time === false) {
        return '';
    }

    $diff = time() - $time;

    if ($diff < 60) {
        return 'just now';
    }

    if ($diff < 3600) {
        $minutes = floor($diff / 60);
        return $minutes . ' min ago';
    }

    if ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hr ago';
    }

    if ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    }

    return date('Y-m-d', $time);
}

function isToday(string $datetime) { 
    $time = strtotime($datetime); 
    if ($time === false) { 
        return false; 
    } 

    return date('Y-m-d', $time) === date('Y-m-d'); 
}

include '_head.php';
?>

<link rel="stylesheet" href="css/live_chat.css">

<?php if ($user_is_admin): ?>

    <!-- Admin Live Chat -->
    <div class="live-chat-page">

        <!-- Left Panel -->
        <div class="chat-sidebar">
            <div class="chat-sidebar-header">
                <span class="chat-role-badge">
                    <?= $user_role ?>
                </span>
            </div>

            <!-- Search -->
            <div class="chat-search">
                <input
                    type="text"
                    id="chatSearch"
                    placeholder="Search by customer name..."
                >
            </div>

            <!-- Status Filter -->
            <div class="chat-filter">
                <select id="chatStatus">
                    <option
                        value="-1"
                        <?= $chatStatus === -1 ? 'selected' : '' ?>
                    >
                        All
                    </option>

                    <option
                        value="0"
                        <?= $chatStatus === 0 ? 'selected' : '' ?>
                    >
                        Closed
                    </option>

                    <option
                        value="1"
                        <?= $chatStatus === 1 ? 'selected' : '' ?>
                    >
                        Active
                    </option>

                    <option
                        value="2"
                        <?= $chatStatus === 2 ? 'selected' : '' ?>
                    >
                        Resolved
                    </option>
                </select>
            </div>

            <!-- Chat List -->
            <div class="chat-list" id="chatList">
                <?php if (empty($chatSessions)): ?>
                    <div class="chat-list-empty">
                        No chats available.
                    </div>

                <?php else: ?>
                    <?php foreach ($chatSessions as $chat): ?>
                        <div
                            class="chat-item <?= $selectedChatId == $chat['chat_session_id'] ? 'active' : '' ?>"
                            data-chat-id="<?= $chat['chat_session_id'] ?>"
                            data-status="<?= $chat['status'] ?>"
                            data-last-message-at="<?= encode($chat['last_message_at'] ?? '') ?>"
                        >

                            <div class="chat-item-top">
                                <div class="chat-item-header">
                                    <span class="chat-session-id">
                                        #<?= $chat['chat_session_id'] ?>
                                    </span>

                                    <div class="chat-customer-name">
                                        <?= encode($chat['username']) ?>
                                    </div>

                                    <span class="chat-item-status status-<?= (int)$chat['status'] ?>">
                                        <?= getStatusLabel((int)$chat['status']) ?>
                                    </span>
                                </div>

                                <div class="chat-time">
                                    <?= getTimeAgo($chat['last_message_at'] ?? '') ?>
                                </div>
                            </div>

                            <div class="chat-item-bottom">
                                <span class="chat-last-message">
                                    <?php if (!empty($chat['last_message_sender'])): ?>
                                        <?= encode($chat['last_message_sender']) . ': ' ?>
                                    <?php endif; ?>

                                    <?= encode($chat['last_message'] ?? '') ?>
                                </span>
                            </div>

                            <div class="chat-item-actions">
                                <?php if ($chat['status'] == 1): ?>
                                    <button
                                        type="button"
                                        class="chat-status-btn resolve-btn"
                                        data-chat-id="<?= $chat['chat_session_id'] ?>"
                                    >
                                        Resolve
                                    </button>

                                    <button
                                        type="button"
                                        class="chat-status-btn close-btn"
                                        data-chat-id="<?= $chat['chat_session_id'] ?>"
                                    >
                                        Close
                                    </button>

                                <?php else: ?>
                                    <button
                                        type="button"
                                        class="chat-status-btn reopen-btn"
                                        data-chat-id="<?= $chat['chat_session_id'] ?>"
                                    >
                                        Reopen
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right Panel -->
        <main class="chat-content">
            <?php if ($selectedChatId === 0): ?>
                <!-- No chat selected -->
                <div class="chat-no-selection">
                    <div class="chat-no-selection-icon">💬</div>

                    <h2>No conversation selected</h2>
                    <p>Select a conversation from the list to start chatting.</p>
                </div>

            <?php else: ?>
                <?php
                    $selectedChat = null;

                    foreach ($chatSessions as $chat) {
                        if ($chat['chat_session_id'] == $selectedChatId) {
                            $selectedChat = $chat;
                            break;
                        }
                    }
                ?>

                <?php if ($selectedChat): ?>
                    <!-- Chat Header -->
                    <div class="chat-header">
                        <div class="details">
                            <span>
                                Chat #<?= $selectedChat['chat_session_id'] ?>
                            </span>

                            <h2>
                                <?= encode($selectedChat['username']) ?>
                            </h2>
                        </div>

                        <div class="chat-header-status">
                            <span class="status status-<?= (int)$selectedChat['status'] ?>">
                                <?= getStatusLabel((int)$selectedChat['status']) ?>
                            </span>
                        </div>
                    </div>


                    <!-- Messages -->
                    <div class="chat-messages" id="chatMessages">
                        <?php
                            $hasMessages = false;
                            $isOwnMessage = false;
                            $isAdminMessage = false;

                            foreach ($messages as $message):
                                if ($message['chat_session_id'] != $selectedChatId) {
                                    continue;
                                }

                                $hasMessages = true;
                                $isOwnMessage = $message['user_id'] == $user_id;
                                $isAdminMessage = (bool)$message['is_admin'];
                        ?>
                            <div
                                class="
                                    chat-message-row
                                    <?= $isOwnMessage || ($isAdminMessage && $user_is_admin) ? 'own' : 'other' ?>
                                "
                                data-message-id="<?= (int)$message['chat_message_id'] ?>"

                                <?php if (isToday($message['created_at'])): ?> 
                                    data-created-at="<?= encode($message['created_at']) ?>" 
                                <?php endif; ?>
                            >
                                <div class="chat-message">
                                    <div class="chat-message-username">
                                        <?= encode($message['sender_username']) ?>
                                    </div>

                                    <div class="chat-message-text">
                                        <?= nl2br(encode($message['message'])) ?>
                                    </div>

                                    <div class="chat-message-time">
                                        <?= getTimeAgo($message['created_at']) ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <?php if (!$hasMessages): ?>
                            <div class="chat-empty-messages">
                                No messages yet.
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Message Input -->
                    <?php if ($selectedChat['status'] == 1): ?>
                        <form
                            id="chatMessageForm"
                            class="chat-input-area"
                            method="post"
                        >
                            <input
                                type="hidden"
                                name="chat_session_id"
                                value="<?= $selectedChatId ?>"
                            >

                            <input
                                type="text"
                                name="message"
                                id="chatMessageInput"
                                maxlength="1000"
                                placeholder="Type your message..."
                                autocomplete="off"
                            >

                            <button
                                type="submit"
                                class="chat-send-button"
                            >
                                Send
                            </button>
                        </form>

                    <?php else: ?>
                        <div class="chat-input-disabled">
                            This conversation is
                            <?= getStatusLabel($selectedChat['status']) ?>.
                            Reopen chat to continue chatting.
                        </div>
                    <?php endif; ?>

                <?php endif; ?>

            <?php endif; ?>

        </main>
    </div>

<?php else: ?>
    <!-- Member Live Chat -->
    <div class="member-chat-page">
        <main class="chat-content">
            <!-- No Chat Yet -->
            <?php if (!$memberChat): ?>
                <div class="member-chat-empty">
                    <div class="member-chat-empty-content">
                        <div class="member-chat-empty-icon">💬</div>
                        <h2>Need help?</h2>

                        <p>
                            Start a conversation with our support team.
                            We will get back to you as soon as possible.
                        </p>

                        <button
                            id="memberStartChatButton"
                            type="button"
                            class="member-start-chat-button"
                        >
                            Start Chat
                        </button>
                    </div>
                </div>

            <?php else: ?>
                <!-- Header -->
                <div class="chat-header">
                    <div class="details">
                        <span>Support</span>
                        <h2>Chat #<?= (int)$memberChat['chat_session_id'] ?></h2>

                    </div>

                    <div class="chat-header-status">
                        <span class="status status-<?= (int)$memberChat['status'] ?>">
                            <?= getStatusLabel((int)$memberChat['status']) ?>
                        </span>
                    </div>
                </div>

                <!-- Messages -->
                <div
                    class="chat-messages"
                    id="memberChatMessages"
                >
                    <?php
                        $memberHasMessages = false;

                        foreach ($messages as $message):
                            if (
                                (int)$message['chat_session_id'] !==
                                (int)$memberChat['chat_session_id']
                            ) {
                                continue;
                            }

                            $memberHasMessages = true;

                            $isOwnMessage = (int)$message['user_id'] === (int)$user_id;
                    ?>

                        <div
                            class="
                                chat-message-row
                                <?= $isOwnMessage ? 'own' : 'other' ?>
                            "
                            data-message-id="<?= (int)$message['chat_message_id'] ?>"

                            <?php if (isToday($message['created_at'])): ?> 
                                data-created-at="<?= encode($message['created_at']) ?>" 
                            <?php endif; ?>
                        >
                            <div class="chat-message">
                                <div class="chat-message-username">
                                    <?= encode($message['sender_username']) ?>
                                </div>

                                <div class="chat-message-text">
                                    <?= nl2br(encode($message['message'])) ?>
                                </div>

                                <div class="chat-message-time">
                                    <?= getTimeAgo($message['created_at']) ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <?php if (!$memberHasMessages): ?>
                        <div class="chat-empty-messages">
                            Send us a message and our support team will get back to you.
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Message Input -->
                <input
                    id="memberChatSessionId"
                    type="hidden"
                    name="chat_session_id"
                    value="<?= (int)$memberChat['chat_session_id'] ?>"
                >

                <input
                    id="memberChatStatus"
                    type="hidden"
                    value="<?= (int)$memberChat['status'] ?>"
                >

                <form
                    id="memberChatMessageForm"
                    class="chat-input-area"
                    method="post"
                    <?= (int)$memberChat['status'] !== 1 ? 'style="display:none;"' : '' ?>
                >
                    <input
                        id="memberChatMessageInput"
                        type="text"
                        name="message"
                        maxlength="1000"
                        placeholder="Type your message..."
                        autocomplete="off"
                    >

                    <button
                        type="submit"
                        class="chat-send-button"
                    >
                        Send
                    </button>
                </form>

                <!-- New chat -->
                <div
                    id="memberChatStatusMessage"
                    class="member-chat-status-message"
                    <?= (int)$memberChat['status'] === 1 ? 'style="display:none;"' : '' ?>
                >
                    <div>
                        This conversation is
                        <strong id="memberChatStatusText">
                            <?= getStatusLabel((int)$memberChat['status']) ?>
                        </strong>.
                    </div>

                    <div>
                        Start a new conversation if you need further assistance.
                    </div>

                    <button
                        id="memberStartNewChatButton"
                        type="button"
                        class="chat-reopen-button"
                    >
                        Start New Chat
                    </button>
                </div>
            <?php endif; ?>

        </main>
    </div>

<?php endif; ?>

<?php include '_foot.php'; ?>

<script>
    const currentUserId = <?= (int)$user_id ?>;
    const currentUserIsAdmin = <?= $user_is_admin ? 'true' : 'false' ?>;
</script>

<?php if ($user_is_admin): ?>
    <script src="../../js/live_chat_admin.js"></script>

<?php else: ?>
    <script src="../../js/live_chat_member.js"></script>

<?php endif; ?>