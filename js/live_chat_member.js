$(function () {

    // Open chat widget
    $("#memberChatButton").on("click", function () {
        $("#memberChatWindow").addClass("active");
        $(this).removeClass("active");

        scrollToBottom();

        $("#memberChatMessageInput").focus();
    });

    // Close chat widget
    $("#memberChatClose").on("click", function () {
        $("#memberChatWindow").removeClass("active");
        $("#memberChatButton").addClass("active");
    });

    // Send message
    $("#memberChatMessageForm").on("submit", function (e) {
        e.preventDefault();

        const form = $(this);
        const input = $("#memberChatMessageInput");
        const button = $(".member-chat-send-button");

        // Prevent double-click / double-submit
        if (input.prop("disabled") || button.prop("disabled")) {
            return;
        }

        const chatSessionId = form
            .find('input[name="chat_session_id"]')
            .val();

        const message = input.val().trim();

        if (!message) {
            return;
        }

        // Create a new chat if there is no active chat session
        const chatStatus = parseInt( $("#memberChatStatus").val()) || 0;
        if (!chatSessionId || parseInt(chatSessionId) <= 0 || chatStatus !== 1 ) {
            createChatAndSendMessage(message);
            return;
        }

        // Disable input and button while sending
        button.prop("disabled", true);
        input.prop("disabled", true);

        sendMessage(chatSessionId, message);
    });

    // Allow Enter to send message
    $("#memberChatMessageInput").on("keydown", function (e) {
        if (e.key === "Enter" && !e.shiftKey) {
            e.preventDefault();
            $("#memberChatMessageForm").trigger("submit");
        }
    });

    // Open New chat
    $("#memberStartNewChatButton").on("click", function () {
        const button = $(this);
        if (button.prop("disabled")) {
            return;
        }

        button.prop("disabled", true);

        $.ajax({
            url: "live_chat.php",
            type: "POST",
            dataType: "json",
            data: {
                action: "create_chat"
            },

            success: function (response) {
                if (!response.success) {
                    alert(
                        response.message ||
                        "Unable to start a new chat."
                    );
                    return;
                }

                const url = new URL(window.location.href);
                url.searchParams.set("chat", response.chatSession.chat_session_id);
                window.location.href = url.toString();
            },

            error: function (xhr, status, error) {
                console.error(
                    "Create chat failed:",
                    status,
                    error
                );

                alert("Unable to start a new chat. Please try again.");
            },

            complete: function () {
                button.prop("disabled", false);
            }
        });
    });

    // Create chat and send first message
    function createChatAndSendMessage(message) {
        const input = $("#memberChatMessageInput");
        const button = $(".member-chat-send-button");

        // Prevent double-click / double-submit
        if (input.prop("disabled") || button.prop("disabled")) {
            return;
        }

        // Disable input and button while creating chat
        button.prop("disabled", true);
        input.prop("disabled", true);

        $.ajax({
            url: "live_chat.php",
            type: "POST",
            dataType: "json",
            data: {
                action: "create_chat"
            },

            success: function (response) {
                if (!response.success) {
                    alert(response.message || "Unable to start a chat.");
                    return;
                }

                const chatSessionId = response.chatSession.chat_session_id;

                // Set the new chat session ID
                $("#memberChatSessionId") .val(chatSessionId);

                // Send the first message
                sendMessage(chatSessionId, message);
            },

            error: function (xhr, status, error) {
                console.error(
                    "Create chat failed:",
                    status,
                    error
                );

                alert("Unable to start a chat. Please try again.");

                button.prop("disabled", false);
                input.prop("disabled", false);
            }
        });
    }

    // Send message
    function sendMessage(chatSessionId, message) {
        const input = $("#memberChatMessageInput");
        const button = $(".member-chat-send-button");

        if (!chatSessionId || !message) {
            button.prop("disabled", false);
            input.prop("disabled", false);
            return;
        }

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

                // Remove empty message state
                $("#memberChatMessages .member-chat-empty").remove();

                // Add message to chat
                displayMessageContent(response.message);

                // Update chat session ID
                if (response.chatSession) {
                    updateChatSession(response.chatSession);
                }

                // Clear input
                input.val("");

                // Scroll to latest message
                scrollToBottom();
            },

            error: function (xhr, status, error) {
                console.error(
                    "Send message failed:",
                    status,
                    error
                );

                alert("Unable to send message. Please try again.");
            },

            complete: function () {
                button.prop("disabled", false);
                input.prop("disabled", false);

                input.focus();
            }
        });
    }

    // Display message content
    function displayMessageContent(messageData) {
        // Check if this message belongs to the current user
        const isOwnMessage = parseInt(messageData.user_id) === parseInt(currentUserId);
        const messageClass = isOwnMessage ? "own" : "other";

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

        $("#memberChatMessages").append(messageHtml);
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

    // Update current chat session
    function updateChatSession(chatData) {
        if (!chatData) {
            return;
        }

        const chatSessionId = parseInt(chatData.chat_session_id) || 0;
        const status = parseInt(chatData.status) || 0;

        if (chatSessionId <= 0) {
            return;
        }

        $("#memberChatSessionId").val(chatSessionId);
        $("#memberChatStatus").val(status);

        const statusBadge = $(".chat-header-status .status");
        statusBadge
            .removeClass("status-0 status-1 status-2")
            .addClass("status-" + status)
            .text(getStatusLabel(status));

        if (status === 1) {
            // Active
            $("#memberChatStatusMessage").hide();
            $("#memberChatMessageForm").show();

        } else {
            // Closed / Resolved
            $("#memberChatMessageForm").hide();
            $("#memberChatStatusMessage").show();

            $("#memberChatStatusText").text(getStatusLabel(status));
        }
    }

    // Poll for new messages
    let pollingMessages = false;
    function pollNewMessages() {
        if (pollingMessages) {
            return;
        }
        pollingMessages = true;

        const chatMessages = $("#memberChatMessages");

        // No chat widget is available
        if (chatMessages.length === 0) {
            pollingMessages = false;
            return;
        }

        const chatSessionId = $("#memberChatSessionId").val();
        if (!chatSessionId || parseInt(chatSessionId) <= 0) {
            pollingMessages = false;
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
                chat_session_id: chatSessionId,
                last_message_id: lastMessageId
            },

            success: function (response) {
                if (!response.success) {
                    console.error(
                        response.message ||
                        "Failed to get new messages."
                    );
                    return;
                }

                if (response.chatSession) {
                    updateChatSession(response.chatSession);
                }

                if (
                    !response.messages ||
                    response.messages.length === 0
                ) {
                    return;
                }

                // Remove empty message state
                chatMessages.find(".member-chat-empty").remove();

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

                    receivedNewMessage = true;
                });

                if (receivedNewMessage) {
                    console.log("New messages received.");

                    if (isNearBottom(chatMessages[0])) {
                        scrollToBottom();
                    }
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

    // Poll for chat session updates
    let pollingChat = false;
    function pollChatSession() {
        if (pollingChat) {
            return;
        }
        pollingChat = true;

        const chatSessionId = $("#memberChatSessionId").val();
        if (!chatSessionId || parseInt(chatSessionId) <= 0) {
            pollingChat = false;
            return;
        }

        $.ajax({
            url: "live_chat.php",
            type: "POST",
            dataType: "json",
            data: {
                action: "get_chat_updates"
            },

            success: function (response) {
                if (!response.success) {
                    console.error(
                        response.message ||
                        "Failed to update chat."
                    );
                    return;
                }

                if (!response.chats) {
                    return;
                }

                response.chats.forEach(function (chatData) {
                    if (parseInt(chatData.chat_session_id) === parseInt(chatSessionId)) {
                        updateChatSession(chatData);
                    }
                });
            },

            error: function (xhr, status, error) {
                console.error(
                    "Chat polling failed:",
                    status,
                    error
                );
            },

            complete: function () {
                pollingChat = false;
            }
        });
    }

    // Check if scroll of the element is near bottom
    function isNearBottom(container, messageThreshold = 10, tolerance = 50) {
        const messages = [...container.querySelectorAll('[data-message-id]')];
        const containerBottom = container.getBoundingClientRect().bottom;

        const messagesBelow = messages.filter(message => {
            const rect = message.getBoundingClientRect();
            return rect.top >= (containerBottom + tolerance);
        });

        return messagesBelow.length <= messageThreshold;
    }

    // Scroll to latest message
    function scrollToBottom() {
        const messages = $("#memberChatMessages");

        if (messages.length === 0) {
            return;
        }

        messages.scrollTop(
            messages[0].scrollHeight
        );
    }
    scrollToBottom();

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

    // Escape HTML tags
    function escapeHtml(text) {
        return $("<div>")
            .text(text)
            .html();
    }

    // Start new message polling
    setInterval(function () {
        pollNewMessages();
    }, 2000);

    // Start chat session polling
    setInterval(function () {
        pollChatSession();
    }, 2000);

});