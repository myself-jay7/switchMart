<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// User is logged in, fetch session data
$user_id = $_SESSION['user_id'];
$name = $_SESSION['name'];
$profileurl = $_SESSION['profile_photo_url'];
$other_user_id = isset($_GET['viewer_id']) ? (int)$_GET['viewer_id'] : 0;
if ($user_id == $other_user_id){
    header("Location: /profile.php");
}

// Fetch user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$other_user_id]);
$user = $stmt->fetch();

// Check if current user has already rated this user
$stmt = $pdo->prepare("SELECT rating FROM ratings WHERE user_id = ? AND rater_id = ?");
$stmt->execute([$other_user_id, $user_id]);
$user_rating = $stmt->fetch();

// Fetch user's products
$stmt = $pdo->prepare("SELECT * FROM products WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$other_user_id]);
$products = $stmt->fetchAll();

// Handle rating submission or update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_rating'])) {
    $rating = (float)$_POST['rating'];
    
    // Validate rating
    if ($rating < 0 || $rating > 5) {
        $error = "Rating must be between 0 and 5";
    } else {
        try {
            $pdo->beginTransaction();
            
            if ($user_rating) {
                // Update existing rating
                $stmt = $pdo->prepare("UPDATE ratings SET rating = ? WHERE user_id = ? AND rater_id = ?");
                $stmt->execute([$rating, $other_user_id, $user_id]);
            } else {
                // Insert new rating
                $stmt = $pdo->prepare("INSERT INTO ratings (user_id, rater_id, rating) VALUES (?, ?, ?)");
                $stmt->execute([$other_user_id, $user_id, $rating]);
            }
            
            // Calculate new average rating
            $stmt = $pdo->prepare("SELECT AVG(rating) as avg_rating FROM ratings WHERE user_id = ?");
            $stmt->execute([$other_user_id]);
            $avg = $stmt->fetch();
            
            // Update user's average rating
            $stmt = $pdo->prepare("UPDATE users SET rating = ? WHERE user_id = ?");
            $stmt->execute([$avg['avg_rating'], $other_user_id]);
            
            $pdo->commit();
            
            // Refresh the page to show updated rating
            header("Location: ".$_SERVER['REQUEST_URI']);
            exit();
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error updating rating: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Profile | Switch Mart</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
  <style>
    .profile-bg {
      background: linear-gradient(to right, #f3f4f6, #e5e7eb);
    }
    .edit-btn {
      transition: all 0.3s ease;
    }
    .edit-btn:hover {
      transform: translateY(-2px);
    }
    .product-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
    }
    .popup-overlay {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background-color: rgba(0,0,0,0.5);
      display: flex;
      justify-content: center;
      align-items: center;
      z-index: 1000;
    }
    .popup-form {
      background: white;
      padding: 2rem;
      border-radius: 8px;
      width: 90%;
      max-width: 500px;
      max-height: 90vh;
      overflow-y: auto;
    }
    .popup-form h2 {
      font-size: 1.5rem;
      font-weight: bold;
      margin-bottom: 1rem;
    }
    .popup-form input,
    .popup-form textarea,
    .popup-form select {
      width: 100%;
      padding: 0.5rem;
      margin-bottom: 1rem;
      border: 1px solid #ddd;
      border-radius: 4px;
    }
    .popup-form button[type="submit"] {
      background-color: #4CAF50;
      color: white;
      padding: 0.5rem 1rem;
      border: none;
      border-radius: 4px;
      cursor: pointer;
    }
    .popup-form button[type="submit"]:hover {
      background-color: #45a049;
    }
  </style>
</head>
<body class="bg-gray-50">
  <!-- Header -->
  <header class="bg-white shadow-sm sticky top-0 z-10">
    <div class="container mx-auto px-4 py-4 flex items-center justify-between">
      <img src="/assets/images/logo.png" alt="Logo" class="h-8">
      <nav class="hidden md:flex space-x-6">
        <a href="/landingpage.php" class="text-gray-600 hover:text-blue-600 font-medium">Shop</a>
        <a href="/messages.php" class="text-gray-600 hover:text-blue-600 font-medium">Messages</a>
        <a href="/profile.php" class="text-orange-600 font-medium">Profile</a>
      </nav>
      <div class="flex items-center space-x-4">
        <img src="/<?= htmlspecialchars($profileurl ?? '/assets/images/default-profile.png') ?>" 
             alt="Profile" class="h-8 w-8 rounded-full">
      </div>
    </div>
  </header>

  <!-- Profile Section -->
  <main class="container mx-auto px-4 py-8">
    <div class="profile-bg rounded-xl shadow-md overflow-hidden mb-8">
      <div class="md:flex">
        <!-- Profile Photo -->
        <div class="md:w-1/4 p-6 flex flex-col items-center">
          <img src="/<?= htmlspecialchars($user['profile_photo_url'] ?? '/assets/images/default-profile.png') ?>" 
               alt="Profile Photo" class="h-40 w-40 rounded-full object-cover border-4 border-white shadow-md mb-4">
<!-- In the profile photo section -->
        <div class="mt-4 text-center">
            <span class="text-xl font-bold"><?= htmlspecialchars($user['name']) ?></span>
            <div class="flex items-center justify-center mt-2">
                <i class="fas fa-star text-yellow-400 mr-1"></i>
                <span><?= number_format($user['rating'] ?? 0, 1) ?></span>
                <span class="text-gray-500 text-sm ml-1">(<?= 
                    $pdo->query("SELECT COUNT(*) FROM ratings WHERE user_id = $other_user_id")->fetchColumn() 
                ?> ratings)</span>
            </div>
            
            <!-- Rating form -->
            <form method="POST" class="mt-3">
                <div class="flex flex-col items-center">
                    <div class="flex items-center mb-2">
                        <select name="rating" class="border rounded px-2 py-1 mr-2">
                            <option value="">Select rating</option>
                            <option value="1" <?= $user_rating && $user_rating['rating'] == 1 ? 'selected' : '' ?>>1 - Poor</option>
                            <option value="2" <?= $user_rating && $user_rating['rating'] == 2 ? 'selected' : '' ?>>2 - Fair</option>
                            <option value="3" <?= $user_rating && $user_rating['rating'] == 3 ? 'selected' : '' ?>>3 - Good</option>
                            <option value="4" <?= $user_rating && $user_rating['rating'] == 4 ? 'selected' : '' ?>>4 - Very Good</option>
                            <option value="5" <?= $user_rating && $user_rating['rating'] == 5 ? 'selected' : '' ?>>5 - Excellent</option>
                        </select>
                        <button type="submit" name="update_rating" class="bg-blue-500 text-white px-3 py-1 rounded hover:bg-blue-600">
                            <?= $user_rating ? 'Update Rating' : 'Submit Rating' ?>
                        </button>
                    </div>
                    <?php if ($user_rating): ?>
                        <span class="text-sm text-gray-500">You previously rated this user <?= $user_rating['rating'] ?></span>
                    <?php endif; ?>
                    <?php if (isset($error)): ?>
                        <span class="text-sm text-red-500"><?= $error ?></span>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        </div>

        <!-- Profile Details -->
        <div class="md:w-3/4 p-6">
          <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold text-gray-800">Profile Information</h1>

          </div>

          <div id="profileView" class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <h3 class="text-lg font-semibold text-gray-700 mb-2">Contact Information</h3>
              <div class="space-y-2">
                <p><span class="font-medium">Email:</span> <?= htmlspecialchars($user['email']) ?></p>
                <p><span class="font-medium">Phone:</span> <?= htmlspecialchars($user['phone_number'] ?? 'Not provided') ?></p>
                <p><span class="font-medium">Location:</span> <?= htmlspecialchars($user['location'] ?? 'Not provided') ?></p>
                <p><span class="font-medium">Contact:</span> <?= htmlspecialchars($user['contact_details'] ?? 'Not provided') ?></p>
              </div>
            </div>
            <div>
              <h3 class="text-lg font-semibold text-gray-700 mb-2">About Me</h3>
              <p class="text-gray-600"><?= !empty($user['bio']) ? htmlspecialchars($user['bio']) : 'No bio yet' ?></p>
              <h3 class="text-lg font-semibold text-gray-700 mt-4 mb-2">Address</h3>
              <p class="text-gray-600"><?= !empty($user['address']) ? htmlspecialchars($user['address']) : 'No address provided' ?></p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- My Products Section -->
    <div class="mb-8">
      <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold text-gray-800">Products</h2>
      </div>

      <?php if (empty($products)): ?>
        <div class="bg-white rounded-lg shadow p-6 text-center">
          <p class="text-gray-600">User haven't posted any products yet.</p>
        </div>
      <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          <?php foreach ($products as $product): ?>
            <div class="product-card bg-white rounded-lg shadow-md overflow-hidden transition duration-300">
              <img src="/<?= htmlspecialchars($product['image_url'] ?? '/assets/images/product-placeholder.jpg') ?>" 
                   alt="<?= htmlspecialchars($product['title']) ?>" 
                   class="w-full h-48 object-cover">
              <div class="p-4">
                <div class="flex justify-between items-start">
                  <h3 class="text-lg font-bold text-gray-800"><?= htmlspecialchars($product['title']) ?></h3>
                  <span class="bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded">
                    <?= ucfirst($product['type']) ?>
                  </span>
                </div>
                <p class="text-gray-600 mt-2 line-clamp-2"><?= htmlspecialchars($product['description']) ?></p>
                <div class="mt-4 flex justify-between items-center">
                  <span class="text-xl font-bold text-gray-800">$<?= number_format($product['price'], 2) ?></span>
                  <span class="text-sm <?= $product['status'] === 'available' ? 'text-green-600' : 'text-red-600' ?>">
                    <?= ucfirst($product['status']) ?>
                  </span>
                </div>
                <div class="mt-4 flex justify-between items-center">
                <a href="/request.php?product_id=<?php echo $product['product_id']; ?>" class="bg-blue-600 text-white px-4 py-2 rounded-lg">Trade</a>
                <a href="/inboxmessage.php?receiver_id=<?php echo $product['user_id']; ?>" class="bg-green-600 text-white px-4 py-2 rounded-lg">Negotiate</a>
              </div>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </main>
  


  <!-- Footer -->
  <footer class="bg-white shadow">
    <div class="container mx-auto px-4 py-6">
      <div class="flex flex-col md:flex-row justify-between items-center">
        <div class="mb-4 md:mb-0">
          <h3 class="font-bold text-lg">Switch Mart</h3>
          <p class="text-sm text-gray-500">© <?= date('Y') ?> Switch Mart Inc.</p>
        </div>
        <nav class="flex flex-wrap justify-center gap-4 md:gap-6">
          <a href="#" class="text-gray-500 hover:text-gray-800">About</a>
          <a href="#" class="text-gray-500 hover:text-gray-800">Blog</a>
          <a href="#" class="text-gray-500 hover:text-gray-800">Contact</a>
        </nav>
      </div>
    </div>
  </footer>
</body>
</html>