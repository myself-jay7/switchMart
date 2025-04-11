<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$receiver_id = isset($_GET['receiver_id']) ? (int)$_GET['receiver_id'] : 0;

// Get or create conversation
$conversation = getConversation($pdo, $user_id, $receiver_id);
if (!$conversation) {
    header("Location: messages.php");
    exit();
}

// Handle message submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['message']) && !empty(trim($_POST['message']))) {
        sendMessage($pdo, $conversation['conversation_id'], $user_id, trim($_POST['message']));
    }
    // Handle reactions
    elseif (isset($_POST['reaction'])) {
        handleReaction($pdo, (int)$_POST['message_id'], $user_id, $_POST['reaction']);
    }
    exit(); // For AJAX requests
}

// Mark messages as read
markMessagesAsRead($pdo, $conversation['conversation_id'], $user_id);

// Get conversation messages with reactions
$messages = getMessages($pdo, $conversation['conversation_id']);

// Get receiver details
$receiver = getUser($pdo, $receiver_id);

// Helper functions
function getConversation($pdo, $user1, $user2) {
    // Ensure consistent order to prevent duplicate conversations
    $lower_id = min($user1, $user2);
    $higher_id = max($user1, $user2);
    
    $stmt = $pdo->prepare("SELECT * FROM conversations WHERE user1_id = ? AND user2_id = ?");
    $stmt->execute([$lower_id, $higher_id]);
    $conversation = $stmt->fetch();
    
    if (!$conversation) {
        $stmt = $pdo->prepare("INSERT INTO conversations (user1_id, user2_id) VALUES (?, ?)");
        $stmt->execute([$lower_id, $higher_id]);
        return ['conversation_id' => $pdo->lastInsertId(), 'user1_id' => $lower_id, 'user2_id' => $higher_id];
    }
    
    return $conversation;
}

function sendMessage($pdo, $conversation_id, $sender_id, $message) {
    $stmt = $pdo->prepare("INSERT INTO messages (conversation_id, sender_id, message) VALUES (?, ?, ?)");
    $stmt->execute([$conversation_id, $sender_id, $message]);
    
    // Update conversation last message time
    $pdo->prepare("UPDATE conversations SET last_message_at = NOW() WHERE conversation_id = ?")
        ->execute([$conversation_id]);
    
    return $pdo->lastInsertId();
}

function getMessages($pdo, $conversation_id) {
    $stmt = $pdo->prepare("
        SELECT m.*, u.name AS sender_name, 
               (SELECT GROUP_CONCAT(CONCAT(user_id, ':', reaction_type) SEPARATOR ',') 
                FROM message_reactions WHERE message_id = m.message_id) AS reactions
        FROM messages m
        JOIN users u ON m.sender_id = u.user_id
        WHERE m.conversation_id = ?
        ORDER BY m.created_at ASC
    ");
    $stmt->execute([$conversation_id]);
    return $stmt->fetchAll();
}

function markMessagesAsRead($pdo, $conversation_id, $user_id) {
    $pdo->prepare("
        UPDATE messages m
        JOIN conversations c ON m.conversation_id = c.conversation_id
        SET m.is_read = TRUE
        WHERE m.conversation_id = ? 
        AND m.sender_id != ?
        AND m.is_read = FALSE
    ")->execute([$conversation_id, $user_id]);
}

function handleReaction($pdo, $message_id, $user_id, $reaction) {
    if ($reaction === 'remove') {
        $pdo->prepare("DELETE FROM message_reactions WHERE message_id = ? AND user_id = ?")
            ->execute([$message_id, $user_id]);
    } else {
        $pdo->prepare("
            INSERT INTO message_reactions (message_id, user_id, reaction_type) 
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE reaction_type = VALUES(reaction_type)
        ")->execute([$message_id, $user_id, $reaction]);
    }
}

function getUser($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT user_id, name FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetch();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Chat with <?= htmlspecialchars($receiver['name']) ?> | Switch Mart</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
  <style>
    .message-container {
      height: calc(100vh - 300px);
      min-height: 400px;
    }
    .emoji-picker {
      position: absolute;
      bottom: 60px;
      right: 20px;
      z-index: 10;
    }
    .reaction-bubble {
      position: absolute;
      bottom: -20px;
      right: 10px;
      background: white;
      border-radius: 15px;
      padding: 2px 6px;
      box-shadow: 0 2px 5px rgba(0,0,0,0.1);
      font-size: 12px;
    }
  </style>
</head>
<body class="bg-gray-100">
  <!-- Header -->
  <header class="bg-white shadow-sm sticky top-0 z-10">
    <div class="container mx-auto px-4 py-3 flex items-center justify-between">
      <div class="flex items-center space-x-4">
        <a href="messages.php" class="text-blue-600 hover:text-blue-800">
          <i class="fas fa-arrow-left text-xl"></i>
        </a>
        <div class="flex items-center space-x-3">
          <img src="assets/images/user.png" alt="<?= htmlspecialchars($receiver['name']) ?>" 
               class="h-10 w-10 rounded-full object-cover">
          <div>
            <h2 class="font-semibold"><?= htmlspecialchars($receiver['name']) ?></h2>
            <p class="text-xs text-gray-500" id="typing-indicator"></p>
          </div>
        </div>
      </div>
      <div class="flex items-center space-x-4">
        <button class="text-gray-600 hover:text-blue-600">
          <i class="fas fa-phone-alt"></i>
        </button>
        <button class="text-gray-600 hover:text-blue-600">
          <i class="fas fa-video"></i>
        </button>
      </div>
    </div>
  </header>

  <!-- Main Chat Area -->
  <main class="container mx-auto px-4 pb-20">
    <div class="bg-white rounded-lg shadow overflow-hidden">
      <div class="message-container p-4 overflow-y-auto" id="message-container">
        <?php if (empty($messages)): ?>
          <div class="flex items-center justify-center h-full">
            <p class="text-gray-500">Start your conversation with <?= htmlspecialchars($receiver['name']) ?></p>
          </div>
        <?php else: ?>
          <?php foreach ($messages as $message): ?>
            <div class="mb-4 flex <?= $message['sender_id'] === $user_id ? 'justify-end' : 'justify-start' ?>">
              <div class="relative max-w-xs md:max-w-md lg:max-w-lg rounded-lg px-4 py-2 
                        <?= $message['sender_id'] === $user_id ? 'bg-blue-100' : 'bg-gray-100' ?>">
                <p class="text-gray-800"><?= htmlspecialchars($message['message']) ?></p>
                <p class="text-xs text-gray-500 mt-1">
                  <?= date('h:i A', strtotime($message['created_at'])) ?>
                  <?php if ($message['sender_id'] === $user_id): ?>
                    <i class="ml-1 <?= $message['is_read'] ? 'fas fa-check-double text-blue-500' : 'fas fa-check text-gray-400' ?>"></i>
                  <?php endif; ?>
                </p>
                
                <!-- Reactions -->
                <?php if (!empty($message['reactions'])): ?>
                  <div class="reaction-bubble flex space-x-1">
                    <?php 
                    $reactions = explode(',', $message['reactions']);
                    $reactionCounts = [];
                    foreach ($reactions as $reaction) {
                        list($uid, $type) = explode(':', $reaction);
                        $reactionCounts[$type] = ($reactionCounts[$type] ?? 0) + 1;
                    }
                    foreach ($reactionCounts as $type => $count): ?>
                      <span class="text-xs" title="<?= ucfirst($type) ?>">
                        <?= getReactionEmoji($type) ?> <?= $count > 1 ? $count : '' ?>
                      </span>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
                
                <!-- Reaction Picker -->
                <div class="absolute -bottom-2 right-0 hidden reaction-options bg-white shadow-lg rounded-full p-1">
                  <?php foreach (['like', 'love', 'laugh', 'wow', 'sad', 'angry'] as $reaction): ?>
                    <button class="emoji-option p-1 hover:scale-125 transition-transform"
                            data-message-id="<?= $message['message_id'] ?>"
                            data-reaction="<?= $reaction ?>">
                      <?= getReactionEmoji($reaction) ?>
                    </button>
                  <?php endforeach; ?>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </main>

  <!-- Message Input -->
  <div class="fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 py-3 px-4">
    <form id="message-form" class="flex items-center space-x-2">
      <button type="button" id="emoji-toggle" class="text-gray-500 hover:text-blue-600">
        <i class="far fa-smile text-xl"></i>
      </button>
      <input type="text" name="message" placeholder="Type a message..." 
             class="flex-grow px-4 py-2 border border-gray-300 rounded-full focus:outline-none focus:ring-2 focus:ring-blue-500"
             autocomplete="off">
      <button type="submit" class="bg-blue-600 text-white p-2 rounded-full hover:bg-blue-700">
        <i class="fas fa-paper-plane"></i>
      </button>
    </form>
  </div>

  <!-- Emoji Picker -->
  <div id="emoji-picker" class="emoji-picker hidden bg-white rounded-lg shadow-lg p-3">
    <div class="grid grid-cols-6 gap-2">
      <?php foreach (['like', 'love', 'laugh', 'wow', 'sad', 'angry'] as $reaction): ?>
        <button class="emoji-option p-2 hover:bg-gray-100 rounded"
                data-reaction="<?= $reaction ?>">
          <?= getReactionEmoji($reaction) ?>
        </button>
      <?php endforeach; ?>
    </div>
  </div>

  <script>
    // Helper function to get reaction emoji
    function getReactionEmoji(type) {
      const emojis = {
        'like': '👍',
        'love': '❤️',
        'laugh': '😂',
        'wow': '😮',
        'sad': '😢',
        'angry': '😠'
      };
      return emojis[type] || '👍';
    }

    // Auto-scroll to bottom
    function scrollToBottom() {
      const container = document.getElementById('message-container');
      container.scrollTop = container.scrollHeight;
    }

    // Toggle emoji picker
    document.getElementById('emoji-toggle').addEventListener('click', function() {
      const picker = document.getElementById('emoji-picker');
      picker.classList.toggle('hidden');
    });

    // Handle emoji selection
    document.querySelectorAll('.emoji-option').forEach(btn => {
      btn.addEventListener('click', function() {
        const reaction = this.getAttribute('data-reaction');
        const messageInput = document.querySelector('input[name="message"]');
        messageInput.value += getReactionEmoji(reaction);
        document.getElementById('emoji-picker').classList.add('hidden');
        messageInput.focus();
      });
    });

    // Handle message submission
    document.getElementById('message-form').addEventListener('submit', function(e) {
      e.preventDefault();
      const messageInput = this.querySelector('input[name="message"]');
      const message = messageInput.value.trim();
      
      if (message) {
        fetch(this.action, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
          },
          body: `message=${encodeURIComponent(message)}`
        }).then(() => {
          messageInput.value = '';
          // In a real app, we would fetch new messages here
          location.reload(); // Temporary solution
        });
      }
    });

    // Initialize
    scrollToBottom();

    // Real-time updates would be implemented here with WebSockets or polling
    // This is a placeholder for that functionality
    setInterval(() => {
      fetch(`get_messages.php?conversation_id=<?= $conversation['conversation_id'] ?>&last_message_id=<?= !empty($messages) ? end($messages)['message_id'] : 0 ?>`)
        .then(response => response.json())
        .then(data => {
          if (data.newMessages.length > 0) {
            // Append new messages to the container
            location.reload(); // Simplified for this example
          }
        });
    }, 5000); // Poll every 5 seconds
  </script>
</body>
</html>