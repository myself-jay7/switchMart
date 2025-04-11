<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch all distinct conversations
$stmt = $pdo->prepare("
    SELECT 
        u.user_id as other_user_id,
        u.name as other_user_name,
        m.message as last_message,
        m.created_at as last_message_time,
        SUM(CASE WHEN m.receiver_id = :user_id AND m.is_read = 0 THEN 1 ELSE 0 END) as unread_count
    FROM users u
    JOIN (
        -- Subquery to get the most recent message for each conversation
        SELECT 
            CASE WHEN sender_id = :user_id THEN receiver_id ELSE sender_id END as other_user_id,
            MAX(created_at) as max_time
        FROM messages
        WHERE sender_id = :user_id OR receiver_id = :user_id
        GROUP BY other_user_id
    ) recent ON u.user_id = recent.other_user_id
    JOIN messages m ON 
        ((m.sender_id = :user_id AND m.receiver_id = u.user_id) OR 
         (m.receiver_id = :user_id AND m.sender_id = u.user_id)) AND
        m.created_at = recent.max_time
    GROUP BY u.user_id, u.name, m.message, m.created_at
    ORDER BY m.created_at DESC
");
$stmt->execute(['user_id' => $user_id]);
$conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Messages | Switch Mart</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
  <style>
    .conversation-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    }
    .unread-badge {
      position: absolute;
      top: -4px;
      right: -4px;
    }
  </style>
</head>
<body class="flex flex-col min-h-screen bg-gray-50">
  <!-- Header -->
  <header class="bg-white shadow-sm sticky top-0 z-10">
    <div class="container mx-auto px-4 py-4 flex flex-col md:flex-row md:items-center md:justify-between">
      <div class="flex items-center justify-between">
        <img src="assets/images/logo.png" alt="Logo" class="h-8">
        <button class="md:hidden text-gray-600" id="mobile-menu-button">
          <i class="fas fa-bars text-xl"></i>
        </button>
      </div>
      
      <nav class="hidden md:flex space-x-6 mt-4 md:mt-0" id="main-nav">
        <a href="landingpage.php" class="text-gray-600 hover:text-blue-600 font-medium">Shop</a>
        <a href="requests.php" class="text-gray-600 hover:text-blue-600 font-medium">Requests</a>
        <a href="messages.php" class="text-orange-600 font-medium">Messages</a>
      </nav>
      
      <div class="hidden md:flex items-center space-x-4 mt-4 md:mt-0">
      <a href="profile.php">
        <div class="flex items-center space-x-2">
        <img src="<?= htmlspecialchars($_SESSION['profile_photo_url'] ?? 'assets/images/default-profile.png') ?>" alt="Logo" class="h-8"> 
        <span class="text-gray-600"><?php echo htmlspecialchars($_SESSION['name']); ?></span>
        </div>
        </a>
      </div>
    </div>
  </header>

  <!-- Main Content -->
  <main class="flex-grow container mx-auto px-4 py-8">
    <div class="mb-8">
      <h1 class="text-3xl font-bold text-gray-800 mb-2">Your Messages</h1>
      <p class="text-gray-600">Connect with other users</p>
    </div>

    <!-- Conversations List -->
    <div class="grid gap-4">
      <?php if (empty($conversations)): ?>
        <div class="bg-white rounded-xl shadow-md p-8 text-center">
          <div class="mx-auto w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mb-4">
            <i class="fas fa-comment-alt text-3xl text-gray-400"></i>
          </div>
          <h3 class="text-lg font-medium text-gray-700 mb-1">No messages yet</h3>
          <p class="text-gray-500 mb-6">Start a conversation with other users</p>
          <a href="landingpage.php" class="inline-block px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
            Browse Products
          </a>
        </div>
      <?php else: ?>
        <?php foreach ($conversations as $conversation): ?>
          <div class="conversation-card bg-white rounded-xl shadow-sm p-4 hover:bg-gray-50 transition-all duration-200 relative">
    <div class="flex items-center">
        <div class="relative mr-4">
            <img src="assets/images/user.png" alt="User" class="h-14 w-14 rounded-full object-cover border-2 border-white shadow">
            <?php if ($conversation['unread_count'] > 0): ?>
                <span class="unread-badge absolute -top-1 -right-1 bg-red-500 text-white text-xs font-bold rounded-full h-5 w-5 flex items-center justify-center">
                    <?php echo $conversation['unread_count']; ?>
                </span>
            <?php endif; ?>
        </div>
        <div class="flex-1 min-w-0">
            <div class="flex justify-between items-center mb-1">
                <div class="flex items-center">
                    <a href="viewprofile.php?viewer_id=<?php echo $conversation['other_user_id']; ?>" 
                       class="text-lg font-semibold text-gray-800 hover:text-blue-600 transition-colors mr-2">
                        <?php echo htmlspecialchars($conversation['other_user_name']); ?>
                    </a>
                    <a href="viewprofile.php?viewer_id=<?php echo $conversation['other_user_id']; ?>" 
                       class="text-gray-400 hover:text-blue-500 transition-colors"
                       title="View Profile">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd" />
                        </svg>
                    </a>
                </div>
                <span class="text-xs text-gray-500 whitespace-nowrap ml-2">
                    <?php echo date('M j, g:i a', strtotime($conversation['last_message_time'])); ?>
                </span>
            </div>
            <a href="inboxmessage.php?receiver_id=<?php echo $conversation['other_user_id']; ?>" class="block">
                <p class="text-sm text-gray-600 truncate">
                    <?php echo htmlspecialchars($conversation['last_message']); ?>
                </p>
            </a>
        </div>
        <div class="ml-4 flex space-x-2">
            <a href="inboxmessage.php?receiver_id=<?php echo $conversation['other_user_id']; ?>" 
               class="text-gray-400 hover:text-blue-500 transition-colors">
                <i class="fas fa-chevron-right"></i>
            </a>
        </div>
    </div>
</div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </main>

  <!-- Footer -->
  <footer class="bg-white shadow mt-12">
    <div class="container mx-auto px-4 py-6">
      <div class="flex flex-col md:flex-row justify-between items-center">
        <div class="mb-4 md:mb-0">
          <h3 class="font-bold text-lg">Switch Mart</h3>
          <p class="text-sm text-gray-500">© <?php echo date('Y'); ?> Switch Mart Inc.</p>
        </div>
        <nav class="flex flex-wrap justify-center gap-4 md:gap-6">
          <a href="#" class="text-gray-500 hover:text-gray-800">About</a>
          <a href="#" class="text-gray-500 hover:text-gray-800">Blog</a>
          <a href="#" class="text-gray-500 hover:text-gray-800">Contact</a>
        </nav>
      </div>
    </div>
  </footer>

  <script>
    // Mobile menu toggle
    document.getElementById('mobile-menu-button').addEventListener('click', function() {
      const nav = document.getElementById('main-nav');
      nav.classList.toggle('hidden');
      nav.classList.toggle('flex');
      nav.classList.toggle('flex-col');
      nav.classList.toggle('space-y-2');
      nav.classList.toggle('mt-4');
    });
  </script>
</body>
</html>