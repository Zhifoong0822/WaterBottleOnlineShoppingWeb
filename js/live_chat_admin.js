$(function () {

    // Search for chat
    $("#chatSearch").on("input", function () {
        const search = $(this).val().trim().toLowerCase();

        $(".chat-item").each(function () {
            const chatId = $(this).find(".chat-session-id").text().trim().toLowerCase();
            const customerName = $(this).find(".chat-customer-name").text().trim().toLowerCase();

            const searchableText = chatId + " " + customerName;

            $(this).toggle(searchableText.includes(search));
        });
    });

    // Filter chat by status
    $("#chatStatus").on("change", function() {
        const selectedStatus = $(this).val();

         // Reload page with status filter
        const url = new URL(window.location.href);
        url.searchParams.set("chat", 0);
        url.searchParams.set("status", selectedStatus);
        window.location.href = url.toString();
    });

    // Select chat session
    $(".chat-item").on("click", function (e) {
        // Don't select the chat when clicking an action button
        if ($(e.target).hasClass("chat-status-btn")) {
            return;
        }

        const chatId = $(this).data("chat-id");

        if (!chatId) {
            return;
        }

        // Highlight selected chat
        $(".chat-item").removeClass("active");
        $(this).addClass("active");

        // Reload page with selected chat
        const url = new URL(window.location.href);
        url.searchParams.set("chat", chatId);
        window.location.href = url.toString();
    });

    // Update chat status
    $(document).on(
        "click",
        ".resolve-btn, .close-btn, .reopen-btn",
        function (e) {
            e.stopPropagation();

            const button = $(this);
            const chatId = button.data("chat-id");

            if (!chatId) {
                return;
            }

            let status;
            let confirmMessage;

            if (button.hasClass("resolve-btn")) {
                status = 2;
                confirmMessage = "Mark this chat as resolved?";
            } 
            else if (button.hasClass("close-btn")) {
                status = 0;
                confirmMessage = "Close this chat?";
            } 
            else if (button.hasClass("reopen-btn")) {
                status = 1;
                confirmMessage = "Reopen this chat?";
            }

            if (!confirm(confirmMessage)) {
                return;
            }

            // Prevent double-click
            button.prop("disabled", true);

            $.ajax({
                url: "live_chat.php",
                type: "POST",
                dataType: "json",
                data: {
                    action: "update_chat_status",
                    chat_session_id: chatId,
                    status: status
                },

                success: function (response) {
                    if (!response.success) {
                        alert(response.message || "Failed to update chat status.");
                        return;
                    }

                    // Reload page while keeping the current chat selected
                    const url = new URL(window.location.href);
                    url.searchParams.set("chat", chatId);
                    window.location.href = url.toString();
                },

                error: function (err) {
                    console.error("Update chat status failed:", err);
                    alert("Unable to update chat status. Please try again.");
                },

                complete: function () {
                    button.prop("disabled", false);
                }
            });
        }
    );

    // Send message
    $("#chatMessageForm").on("submit", function (e) {
        e.preventDefault();

        const form = $(this);
        const input = $("#chatMessageInput");
        const button = $(".chat-send-button");

        // Prevent double-click / double-submit
        if (input.prop("disabled") || button.prop("disabled")) {
            return;
        }

        const chatSessionId = form
            .find('input[name="chat_session_id"]')
            .val();

        const message = input.val().trim();

        if (!chatSessionId || message === "") {
            return;
        }

        // Disbale input and button while sending
        button.prop("disabled", true);
        input.prop("disabled", true);

        $.ajax({
            url: "live_chat.php",
            type: "POST",
            dataType: "json",

            data: {
                action: "send_message",
                chat_session_id: chatSessionId,
                message: message
            },

            success: function (response) {
                if (!response.success) {
                    alert(response.message || "Failed to send message.");
                    return;
                }

                // Remove "No messages yet." if it exists
                $("#chatMessages .chat-empty-messages").remove();

                // Add message to right panel and update chat list
                displayMessageContent(response.message);
                updateChatListItem(response.chatSession);
                
                // Clear input
                input.val("");

                // Scroll to latest message
                scrollToBottom();
            },

            error: function (err) {
                console.error("Send message failed:", err);
                alert("Unable to send message. Please try again.");
            },

            complete: function () {
                button.prop("disabled", false);
                input.prop("disabled", false);

                input.focus();
            }
        });
    });

    $("#chatMessageInput").on("keydown", function (e) {
        if (e.key === "Enter" && !e.shiftKey) {
            e.preventDefault();
            $("#chatMessageForm").trigger("submit");
        }
    });

    // Display message content in the right panel
    function displayMessageContent(messageData) {
        // Check if this message belongs to the current user
        const isOwnMessage = parseInt(messageData.user_id) === parseInt(currentUserId);
        const isAdminMessage = parseInt(messageData.is_admin) === 1;
        const isOwn = isOwnMessage || (isAdminMessage && currentUserIsAdmin);

        const messageClass = isOwn ? "own" : "other";

        const messageHtml = `
            <div
                class="chat-message-row ${messageClass}"
                data-message-id="${messageData.chat_message_id}"
                data-created-at="${escapeHtml(messageData.created_at)}"
            >
                <div class="chat-message">

                    <div class="chat-message-username">
                        ${escapeHtml(messageData.sender_username)}
                    </div>

                    <div class="chat-message-text">
                        ${escapeHtml(messageData.message)}
                    </div>

                    <div class="chat-message-time">
                        just now
                    </div>

                </div>
            </div>
        `;

        $("#chatMessages").append(messageHtml);
    }

    // Update times of all displayed message 
    function updateMessageTimes() {
        $("#memberChatMessages .chat-message-row[data-message-id]").each(function () {
            const row = $(this);
            const messageId = row.attr("data-message-id");

            // You need created_at stored on the row
            const createdAt = row.attr("data-created-at");

            if (!createdAt) {
                return;
            }

            row.find(".chat-message-time").text(
                getTimeAgo(createdAt)
            );
        });
    }

    // Update chat list item with last message and time
    function updateChatListItem(chatData) {
        const item = $('.chat-item[data-chat-id="' + chatData.chat_session_id + '"]');

        if (item.length === 0) {
            return;
        }

        // Remove If status doesn't match current filter
        if (!chatMatchesCurrentFilter(chatData.status)) {
            item.remove();
            return;
        }

        // Update status
        item.find(".chat-item-status")
            .removeClass("status-0 status-1 status-2")
            .addClass("status-" + chatData.status)
            .text(getStatusLabel(chatData.status));

        // Update admin buttons
        updateChatActionButtons(item, chatData.status);

        // Only update/move if the message changed
        const oldMessageAt = item.attr("data-last-message-at") || "";
        const newMessageAt = chatData.last_message_at || "";

        if (newMessageAt === oldMessageAt) {
            return;
        }

        item.attr("data-last-message-at", newMessageAt);

        item.find(".chat-last-message").text(
            chatData.last_message_sender + ": " + chatData.last_message
        );

        item.find(".chat-time").text(
            getTimeAgo(newMessageAt)
        );

        // Move to the top
        $("#chatList").prepend(item);
    }

    // Update chat list item action buttons
    function updateChatActionButtons(item, status) {
        if (!currentUserIsAdmin) {
            return;
        }

        const actions = item.find(".chat-item-actions");

        if (actions.length === 0) {
            return;
        }

        const chatId = item.data("chat-id");

        status = parseInt(status);

        if (status === 1) {
            actions.html(`
                <button
                    type="button"
                    class="chat-status-btn resolve-btn"
                    data-chat-id="${chatId}"
                >
                    Resolve
                </button>

                <button
                    type="button"
                    class="chat-status-btn close-btn"
                    data-chat-id="${chatId}"
                >
                    Close
                </button>
            `);
        } else {
            actions.html(`
                <button
                    type="button"
                    class="chat-status-btn reopen-btn"
                    data-chat-id="${chatId}"
                >
                    Reopen
                </button>
            `);
        }
    }

    // Check if chat status match selected status filter
    function chatMatchesCurrentFilter(status) {
        const selectedStatus = parseInt($("#chatStatus").val(), 10);

        return selectedStatus === -1 ||
            selectedStatus === parseInt(status, 10);
    }

    // Check if scroll of the element is near bottom
    function isNearBottom(element, threshold = 100) {
        return (
            element.scrollHeight -
            element.scrollTop -
            element.clientHeight
        ) <= threshold;
    }

    // Auto scroll when new messages are added
    function scrollToBottom() {
        const messages = $("#chatMessages");

        if (messages.length === 0) {
            return;
        }

        messages.scrollTop(
            messages[0].scrollHeight
        );
    }
    scrollToBottom();

    // Escape HTML tags
    function escapeHtml(text) {
        return $("<div>")
            .text(text)
            .html();
    }

    // Convert status code to label
    function getStatusLabel(status) {
        switch (parseInt(status)) {
            case 0:
                return "Closed";

            case 1:
                return "Active";

            case 2:
                return "Resolved";

            default:
                return "Unknown";
        }
    }

    // Convert datetime to "time ago" format
    function getTimeAgo(datetime) {
        const time = new Date(datetime.replace(" ", "T"));

        if (isNaN(time.getTime())) {
            return "";
        }

        const diff = Math.floor(
            (Date.now() - time.getTime()) / 1000
        );

        if (diff < 60) {
            return "just now";
        }

        if (diff < 3600) {
            return Math.floor(diff / 60) + " min ago";
        }

        if (diff < 86400) {
            return Math.floor(diff / 3600) + " hr ago";
        }

        if (diff < 604800) {
            const days = Math.floor(diff / 86400);

            return days + " day" + (days > 1 ? "s" : "") + " ago";
        }

        return time.toISOString().slice(0, 10);
    }

    // Poll for new messages
    let pollingMessages = false;
    function pollNewMessages() {
        if (pollingMessages) {
            return;
        }
        pollingMessages = true;

        const chatMessages = $("#chatMessages");

        // No chat is currently open
        if (chatMessages.length === 0) {
            return;
        }

        const chatSessionId = $("#chatMessageForm")
            .find('input[name="chat_session_id"]')
            .val();

        // Fallback from query param
        let currentChatId = chatSessionId;

        if (!currentChatId) {
            const urlParams = new URLSearchParams(window.location.search);
            currentChatId = urlParams.get("chat");
        }

        if (!currentChatId || parseInt(currentChatId) <= 0) {
            return;
        }

        // Find the newest message currently displayed
        let lastMessageId = 0;

        const lastMessage = chatMessages
            .find(".chat-message-row[data-message-id]")
            .last();

        if (lastMessage && lastMessage.length > 0) {
            lastMessageId = parseInt(lastMessage.attr("data-message-id")) || 0;
        }

        // Update old message times
        updateMessageTimes();
        
        $.ajax({
            url: "live_chat.php",
            type: "POST",
            dataType: "json",
            data: {
                action: "get_new_messages",
                chat_session_id: currentChatId,
                last_message_id: lastMessageId
            },

            success: function (response) {
                // console.log("Polling for new messages");

                if (!response.success) {
                    console.error(
                        response.message || "Failed to get new messages."
                    );
                    return;
                }

                if (!response.messages || response.messages.length === 0) {
                    return;
                }

                // Remove empty message state
                chatMessages.find(".chat-empty-messages").remove();

                let receivedNewMessage = false;

                response.messages.forEach(function (messageData) {
                    // Prevent duplicates
                    const alreadyExists = chatMessages.find(
                        '.chat-message-row[data-message-id="' +
                        messageData.chat_message_id +
                        '"]'
                    ).length > 0;

                    if (alreadyExists) {
                        return;
                    }

                    displayMessageContent(messageData);
                    updateChatListItem(response.chatSession);

                    receivedNewMessage = true;
                });

                if (receivedNewMessage && isNearBottom(chatMessages[0])) {
                    scrollToBottom();
                    console.log("New messages received.");
                }
            },

            error: function (xhr, status, error) {
                console.error(
                    "Polling failed:",
                    status,
                    error
                );
            },

            complete: function () {
                pollingMessages = false;
            }
        });
    }

    // Poll for chat list updates
    let pollingChats = false;
    function pollChatList() {
        if (pollingChats) {
            return;
        }
        pollingChats = true;

        $.ajax({
            url: "live_chat.php",
            type: "POST",
            dataType: "json",
            data: {
                action: "get_chat_updates"
            },

            success: function (response) {
                // console.log("Polling for chat list updates");

                if (!response.success) {
                    console.error(
                        response.message || "Failed to update chat list."
                    );
                    return;
                }

                if (!response.chats) {
                    return;
                }

                response.chats.forEach(function (chatData) {
                    updateChatListItem(chatData);
                });

            },

            error: function (xhr, status, error) {
                console.error(
                    "Chat list polling failed:",
                    status,
                    error
                );
            },

            complete: function () {
                pollingChats = false;
            }

        });

    }

    // Start new message polling
    setInterval(function () {
        pollNewMessages();
    }, 2000);
    
    // Start chat list polling
    setInterval(function () {
        pollChatList();
    }, 2000);
});