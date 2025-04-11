<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$current_user_id = $_SESSION['user_id'];
$other_user_id = isset($_GET['receiver_id']) ? (int)$_GET['receiver_id'] : 0;

// Validate other user exists
$stmt = $pdo->prepare("SELECT user_id, name FROM users WHERE user_id = ?");
$stmt->execute([$other_user_id]);
$other_user = $stmt->fetch();

if (!$other_user) {
    header("Location: messages.php");
    exit();
}

// Handle message submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message']) && !empty(trim($_POST['message']))) {
    $message = trim($_POST['message']);
    $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message) VALUES (?, ?, ?)");
    $stmt->execute([$current_user_id, $other_user_id, $message]);
    
    // Refresh to show the new message
    header("Location: inboxmessage.php?receiver_id=" . $other_user_id);
    exit();
}

// Mark messages as read
$pdo->prepare("UPDATE messages SET is_read = TRUE WHERE receiver_id = ? AND sender_id = ? AND is_read = FALSE")
   ->execute([$current_user_id, $other_user_id]);

// Get conversation messages
$stmt = $pdo->prepare("
    SELECT m.*, u.name AS sender_name 
    FROM messages m
    JOIN users u ON m.sender_id = u.user_id
    WHERE (m.sender_id = ? AND m.receiver_id = ?) 
       OR (m.sender_id = ? AND m.receiver_id = ?)
    ORDER BY m.created_at ASC
");
$stmt->execute([$current_user_id, $other_user_id, $other_user_id, $current_user_id]);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
// markMessagesAsRead($pdo, $conversation['conversation_id'], $user_id);

// function markMessagesAsRead($pdo, $conversation_id, $user_id) {
//   $pdo->prepare("
//       UPDATE messages m
//       JOIN conversations c ON m.conversation_id = c.conversation_id
//       SET m.is_read = TRUE
//       WHERE m.conversation_id = ? 
//       AND m.sender_id != ?
//       AND m.is_read = FALSE
//   ")->execute([$conversation_id, $user_id]);
// }
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Chat with <?= htmlspecialchars($other_user['name']) ?> | Switch Mart</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
  <style>
    .chat-container {
      height: calc(100vh - 180px);
    }
    @media (min-width: 768px) {
      .chat-container {
        height: calc(100vh - 160px);
      }
    }
    .message-bubble {
      max-width: 70%;
      word-wrap: break-word;
    }
    .sent {
      background-color: #3b82f6;
      color: white;
      border-radius: 18px 18px 4px 18px;
    }
    .received {
      background-color: #f3f4f6;
      color: #1f2937;
      border-radius: 18px 18px 18px 4px;
    }
    .typing-indicator {
      display: inline-block;
    }
    .typing-dot {
      display: inline-block;
      width: 8px;
      height: 8px;
      border-radius: 50%;
      background-color: #9ca3af;
      animation: typingAnimation 1.4s infinite ease-in-out;
    }
    .typing-dot:nth-child(1) { animation-delay: 0s; }
    .typing-dot:nth-child(2) { animation-delay: 0.2s; }
    .typing-dot:nth-child(3) { animation-delay: 0.4s; }
    @keyframes typingAnimation {
      0%, 60%, 100% { transform: translateY(0); }
      30% { transform: translateY(-5px); }
    }
  </style>
</head>
<body class="bg-gray-50">
  <!-- Header -->
  <header class="bg-white shadow-sm sticky top-0 z-10">
      <div class="container mx-auto px-4 py-3 flex items-center justify-between">
          <div class="flex items-center">
              <a href="messages.php" class="mr-4 text-blue-600 hover:text-blue-800">
                  <i class="fas fa-arrow-left text-xl"></i>
              </a>
              <a href="viewprofile.php?viewer_id=<?= $other_user['user_id'] ?>" class="flex items-center space-x-3 hover:bg-gray-100 rounded-lg p-1 transition-colors">
                  <div class="relative">
                      <img src="<?= htmlspecialchars($other_user['profile_photo_url'] ?? 'assets/images/user.png') ?>" 
                          alt="<?= htmlspecialchars($other_user['name']) ?>" 
                          class="h-10 w-10 rounded-full object-cover border-2 border-blue-500">
                      <span class="absolute bottom-0 right-0 block h-3 w-3 rounded-full bg-green-500 ring-2 ring-white"></span>
                  </div>
                  <div>
                      <h2 class="font-semibold text-gray-800"><?= htmlspecialchars($other_user['name']) ?></h2>
                      <p class="text-xs text-gray-500">Online</p>
                  </div>
              </a>
          </div>
          
          <!-- Optional: Add additional header actions if needed -->
          <div class="flex items-center space-x-4">
              <a href="viewprofile.php?viewer_id=<?= $other_user['user_id'] ?>" 
                class="text-gray-600 hover:text-blue-600 transition-colors"
                title="View Profile">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                      <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd" />
                  </svg>
              </a>
          </div>
      </div>
  </header>

  <!-- Chat Area -->
  <main class="container mx-auto px-4">
    <div class="chat-container p-4 overflow-y-auto" id="message-container">
      <?php if (empty($messages)): ?>
        <div class="flex flex-col items-center justify-center h-full text-center">
          <div class="w-24 h-24 mb-4 rounded-full bg-blue-50 flex items-center justify-center">
            <i class="fas fa-comments text-blue-400 text-3xl"></i>
          </div>
          <h3 class="text-lg font-medium text-gray-700">No messages yet</h3>
          <p class="text-gray-500 mt-1">Send your first message to <?= htmlspecialchars($other_user['name']) ?></p>
        </div>
      <?php else: ?>
        <div class="space-y-3">
          <?php foreach ($messages as $message): ?>
            <div class="flex <?= $message['sender_id'] === $current_user_id ? 'justify-end' : 'justify-start' ?>">
              <div class="message-bubble px-4 py-2 <?= $message['sender_id'] === $current_user_id ? 'sent' : 'received' ?>">
                <p><?= htmlspecialchars($message['message']) ?></p>
                <div class="flex items-center justify-end mt-1 space-x-1">
                  <span class="text-xs opacity-80">
                    <?= date('h:i A', strtotime($message['created_at'])) ?>
                  </span>
                  <?php if ($message['sender_id'] === $current_user_id): ?>
                    <i class="text-xs <?= $message['is_read'] ? 'fas fa-check-double text-blue-100' : 'fas fa-check text-blue-100' ?>"></i>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
          <!-- Typing indicator (hidden by default) -->
          <div class="flex justify-start hidden" id="typing-indicator">
            <div class="message-bubble px-4 py-2 received">
              <div class="typing-indicator">
                <span class="typing-dot"></span>
                <span class="typing-dot"></span>
                <span class="typing-dot"></span>
              </div>
            </div>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </main>

  <!-- Message Input -->
  <div class="bg-white border-t border-gray-200 py-3 px-4">
  <div class="max-w-6xl mx-auto">
    <form method="POST" action="inboxmessage.php?receiver_id=<?= $other_user_id ?>" class="flex items-center space-x-2">
      <input type="text" name="message" placeholder="Type a message..." 
             class="flex-grow px-4 py-2 border border-gray-300 rounded-full focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
             autocomplete="off" required>
      <button type="submit" class="bg-blue-600 text-white p-2 rounded-full hover:bg-blue-700 transition-colors">
        <i class="fas fa-paper-plane"></i>
      </button>
    </form>
  </div>
  </div>

  <script>
    // Auto-scroll to bottom
    function scrollToBottom() {
      const container = document.getElementById('message-container');
      container.scrollTop = container.scrollHeight;
    }
    
    window.onload = scrollToBottom;

    // Simple polling for new messages (every 3 seconds)
    setInterval(function() {
      fetch(`check_new_messages.php?receiver_id=<?= $other_user_id ?>&last_id=<?= !empty($messages) ? end($messages)['message_id'] : 0 ?>`)
        .then(response => response.json())
        .then(data => {
          if (data.has_new) {
            location.reload();
          }
        });
    }, 3000);

    // Show typing indicator when input is focused (simulated)
    const messageInput = document.querySelector('input[name="message"]');
    const typingIndicator = document.getElementById('typing-indicator');
    
    // messageInput.addEventListener('focus', () => {
    //   typingIndicator.classList.remove('hidden');
    //   scrollToBottom();
      
    //   // Hide after 3 seconds (simulating stop typing)
    //   setTimeout(() => {
    //     typingIndicator.classList.add('hidden');
    //   }, 3000);
    // });
  </script>
</body>
</html>