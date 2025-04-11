<?php
session_start();

// Include the database connection
include 'db.php';

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Fetch the logged-in user's ID
$seller_id = $_SESSION['user_id'];

// Fetch all requests where the seller_id matches the logged-in user's ID
$stmt = $pdo->prepare("
    SELECT r.*, p.title AS product_title, p.description AS product_description, p.price, p.type, p.status, 
           u.name AS buyer_name, u.email AS buyer_email,p.image_url AS product_image
    FROM requests r
    JOIN products p ON r.product_id = p.product_id
    JOIN users u ON r.buyer_id = u.user_id
    WHERE r.seller_id = :seller_id
");
$stmt->execute(['seller_id' => $seller_id]);
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle accept, reject, or negotiate actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['accept'])) {
        $request_id = $_POST['request_id'];
        $buyer_id = $_POST['buyer_id'];
        $product_id = $_POST['product_id'];

        // Insert into trades table
        $stmt = $pdo->prepare("INSERT INTO trades (seller_id, buyer_id, product_id, request_id) VALUES (?, ?, ?, ?)");
        $stmt->execute([$seller_id, $buyer_id, $product_id, $request_id]);

        // Delete from requests table
        $stmt = $pdo->prepare("DELETE FROM requests WHERE request_id = ?");
        $stmt->execute([$request_id]);

        header("Location: requests.php");
        exit();
    } elseif (isset($_POST['reject'])) {
        $request_id = $_POST['request_id'];
        $stmt = $pdo->prepare("DELETE FROM requests WHERE request_id = ?");
        $stmt->execute([$request_id]);
        header("Location: requests.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Requests | Switch Mart</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
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
        <a href="requests.php" class="text-orange-600 font-medium">Requests</a>
        <a href="messages.php" class="text-gray-600 hover:text-blue-600 font-medium">Messages</a>
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
      <h1 class="text-3xl font-bold text-gray-800 mb-2">Your Requests</h1>
      <p class="text-gray-600">Manage incoming requests for your products</p>
    </div>

    <!-- Request Cards -->
    <div class="space-y-6">
      <?php if (empty($requests)): ?>
        <div class="bg-white rounded-lg shadow p-6 text-center">
          <p class="text-gray-600">You don't have any requests yet.</p>
        </div>
      <?php else: ?>
        <?php foreach ($requests as $request): ?>
          <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="p-6 md:flex gap-6">
              <!-- Product Image -->
              <div class="md:w-1/4 mb-4 md:mb-0">
                <?php if (!empty($request['product_image'])): ?>
                  <img src="<?php echo htmlspecialchars($request['product_image']); ?>" 
                       alt="Product Image" 
                       class="w-full h-48 object-cover rounded-lg">
                <?php else: ?>
                  <img src="assets/images/placeholder.jpg" 
                       alt="Placeholder Image" 
                       class="w-full h-48 object-cover rounded-lg">
                <?php endif; ?>
              </div>
              
              <!-- Product and Request Details -->
              <div class="md:w-3/4 space-y-4">
                <div class="border-b pb-4">
                    <h2 class="text-xl font-bold text-gray-800"><?php echo htmlspecialchars($request['product_title']); ?></h2>
                    <p class="text-gray-600"><?php echo htmlspecialchars($request['product_description']); ?></p>
                    <div class="flex flex-wrap gap-4 mt-2">
                        <span class="text-gray-700"><strong>Price:</strong> $<?php echo htmlspecialchars($request['price']); ?></span>
                        <span class="text-gray-700"><strong>Type:</strong> <?php echo htmlspecialchars($request['type']); ?></span>
                        <span class="text-gray-700"><strong>Status:</strong> <?php echo htmlspecialchars($request['status']); ?></span>
                    </div>
                </div>
                
                <div class="border-b pb-4">
                    <h3 class="font-semibold text-gray-800 mb-2">Request Details</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <p><strong>Requested by:</strong> <?php echo htmlspecialchars($request['buyer_name']); ?></p>
                            <p><strong>Email:</strong> <?php echo htmlspecialchars($request['buyer_email']); ?></p>
                        </div>
                        <div>
                            <p><strong>Request Type:</strong> <?php echo htmlspecialchars($request['request_type']); ?></p>
                            <p><strong>Message:</strong> <?php echo htmlspecialchars($request['message']); ?></p>
                        </div>
                    </div>
                </div>
                
                <!-- Actions -->
                <div class="flex flex-wrap gap-3 pt-2">
                    <form method="POST" action="">
                        <input type="hidden" name="request_id" value="<?php echo $request['request_id']; ?>">
                        <input type="hidden" name="buyer_id" value="<?php echo $request['buyer_id']; ?>">
                        <input type="hidden" name="product_id" value="<?php echo $request['product_id']; ?>">
                        <button type="submit" name="accept" 
                                class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 transition">
                            Accept Request
                        </button>
                    </form>
                    
                    <form method="POST" action="">
                        <input type="hidden" name="request_id" value="<?php echo $request['request_id']; ?>">
                        <button type="submit" name="reject" 
                                class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700 transition">
                            Reject Request
                        </button>
                    </form>
                    
                    <a href="inboxmessage.php?receiver_id=<?php echo $request['buyer_id']; ?>" 
                        class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition">
                        Negotiate
                    </a>
                    
                    <a href="viewprofile.php?viewer_id=<?php echo $request['buyer_id']; ?>" 
                        class="px-4 py-2 bg-purple-600 text-white rounded hover:bg-purple-700 transition flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd" />
                        </svg>
                        View Profile
                    </a>
                </div>
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