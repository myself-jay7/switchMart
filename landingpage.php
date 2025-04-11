<?php
session_start();

include 'db.php';

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// User is logged in, fetch session data
$user_id = $_SESSION['user_id'];
$name = $_SESSION['name'];
$profileurl = $_SESSION['profile_photo_url'];

// Handle product submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product'])) {
  $title = trim($_POST['title']);
  $description = trim($_POST['description']);
  $price = (float)$_POST['price'];
  $type = trim($_POST['type']);
  $requirement = trim($_POST['requirement']);
  $d_o_f = trim($_POST['d_o_f']);
  $status = "available";
  $image_url = 'assets/images/product-placeholder.jpg';

  // Handle image upload
  if (!empty($_FILES['image']['name'])) {
      $upload_dir = 'assets/images/products/';
      if (!file_exists($upload_dir)) {
          mkdir($upload_dir, 0777, true);
      }

      $file_extension = pathinfo($_FILES["image"]["name"], PATHINFO_EXTENSION);
      $new_filename = uniqid() . '.' . $file_extension;
      $target_file = $upload_dir . $new_filename;

      // Check if image file is valid
      $check = getimagesize($_FILES["image"]["tmp_name"]);
      if ($check !== false && in_array(strtolower($file_extension), ['jpg', 'jpeg', 'png', 'gif'])) {
          if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
              $image_url = $target_file;
          }
      }
  }

  // Insert product into the database with image URL
  $stmt = $pdo->prepare("
      INSERT INTO products 
      (user_id, title, description, price, type, d_o_f, status, requirement, image_url) 
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
  ");
  $stmt->execute([
      $user_id, 
      $title, 
      $description, 
      $price,
      $type,  
      $d_o_f, 
      $status, 
      $requirement,
      $image_url
  ]);

  // Refresh the page to show the newly added product
  header("Location: " . $_SERVER['PHP_SELF']);
  exit();
}

// Fetch filter values from the URL
$minPrice = isset($_GET['min_price']) ? (float)$_GET['min_price'] : 0;
$maxPrice = isset($_GET['max_price']) ? (float)$_GET['max_price'] : 1000;
$type = isset($_GET['type']) ? $_GET['type'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';

// Build the SQL query based on filters
$sql = "SELECT * FROM products WHERE price BETWEEN :min_price AND :max_price AND user_id != :user_id";
$params = [':min_price' => $minPrice, ':max_price' => $maxPrice, ':user_id' => $user_id];

if (!empty($type)) {
    $sql .= " AND type = :type";
    $params[':type'] = $type;
}

if (!empty($status)) {
    $sql .= " AND status = :status";
    $params[':status'] = $status;
}

// Fetch filtered products
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>switchMart ~Trade With Ease</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    /* Floating Button */
    .floating-button {
      position: fixed;
      bottom: 2rem;
      right: 2rem;
      background-color: #4f46e5;
      color: white;
      border: none;
      border-radius: 50%;
      width: 4rem;
      height: 4rem;
      font-size: 2rem;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
      transition: background-color 0.3s ease;
    }

    .floating-button:hover {
      background-color: #4338ca;
    }

    /* Popup Form */
    .popup-overlay {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background-color: rgba(0, 0, 0, 0.5);
      display: flex;
      justify-content: center;
      align-items: center;
      z-index: 1000;
    }

    .popup-form {
      background-color: white;
      padding: 2rem;
      border-radius: 0.5rem;
      width: 90%;
      max-width: 500px;
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
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
      padding: 0.75rem;
      margin-bottom: 1rem;
      border: 1px solid #ddd;
      border-radius: 0.5rem;
      font-size: 1rem;
    }

    .popup-form button {
      width: 100%;
      padding: 0.75rem;
      background-color: #4f46e5;
      color: white;
      border: none;
      border-radius: 0.5rem;
      font-size: 1rem;
      cursor: pointer;
    }

    .popup-form button:hover {
      background-color: #4338ca;
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
        <a href="landingpage.php" class="text-orange-600 font-medium">Shop</a>
        <a href="requests.php" class="text-gray-600 hover:text-blue-600 font-medium">Requests</a>
        <a href="messages.php" class="text-gray-600 font-medium">Messages</a>
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
  <main class="relative">
  <!-- Background Image with Overlay -->
  <div class="absolute inset-0 z-0">
    <img src="./assets/images/banner-ecommerce.jpg" 
         alt="E-commerce background"
         class="w-full h-full object-cover">
    <div class="absolute inset-0 bg-black bg-opacity-60"></div>
  </div>

  <!-- Content Container -->
  <div class="container mx-auto px-6 py-24 relative z-10">
    <div class="max-w-lg space-y-4 text-white">
      <h1 class="text-5xl font-bold">
        The Best Place To <br>Find & Trade <br>Amazing <span class="text-pink-400">Products</span>
      </h1>
      <button class="bg-orange-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition duration-300">
        Trade now!
      </button>
    </div>
  </div>
</main>

  <!-- Product Grid -->
  <div class="container mx-auto p-6">
    <div class="flex">
      <!-- Sidebar -->
      <aside class="w-64 p-6 rounded-lg">
        <div class=" w-64 p-6 bg-white  rounded-lg shadow">
        <div class="mb-6">
          <h2 class="font-semibold text-lg">Filters</h2>
          <button class="text-blue-600 text-sm" onclick="resetFilters()">Clear</button>
        </div>

        <!-- Filter Section -->
        <form id="filter-form" method="GET" action="">
          <div class="mb-4">
            <h3 class="font-semibold mb-2">Price Range</h3>
            <input type="range" id="price-range" name="max_price" min="0" max="1000" value="<?php echo $maxPrice; ?>" class="w-full">
            <p class="text-sm text-gray-600">Max Price: $<span id="price-value"><?php echo $maxPrice; ?></span></p>
          </div>

          <div class="mb-4">
            <h3 class="font-semibold mb-2">Type</h3>
            <label class="block"><input type="checkbox" name="type" value="food" <?php echo $type === 'food' ? 'checked' : ''; ?>> Food</label>
            <label class="block"><input type="checkbox" name="type" value="electronics" <?php echo $type === 'electronics' ? 'checked' : ''; ?>> Electronics</label>
            <label class="block"><input type="checkbox" name="type" value="clothing" <?php echo $type === 'clothing' ? 'checked' : ''; ?>> Clothing</label>
            <label class="block"><input type="checkbox" name="type" value="other" <?php echo $type === 'other' ? 'checked' : ''; ?>> Other</label>
          </div>

          <div class="mb-4">
            <h3 class="font-semibold mb-2">Status</h3>
            <label class="block"><input type="checkbox" name="status" value="available" <?php echo $status === 'available' ? 'checked' : ''; ?>> Available</label>
            <label class="block"><input type="checkbox" name="status" value="sold" <?php echo $status === 'sold' ? 'checked' : ''; ?>> Sold</label>
            <label class="block"><input type="checkbox" name="status" value="pending" <?php echo $status === 'pending' ? 'checked' : ''; ?>> Pending</label>
          </div>

          <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg w-full">Apply Filters</button>
        </form>
       </div>
      </aside>

      <!-- Product Grid -->
      <main class="flex-1 ml-8">
        <div class="flex justify-between items-center mb-6">
          <h2 class="text-xl font-bold">Product Results</h2>
          <select class="border rounded-lg px-3 py-2">
            <option>Sort by: Last posted</option>
          </select>
        </div>

        <!-- Product Cards -->
        <div class="grid grid-cols-3 gap-6">
          <?php foreach ($products as $product ): ?>
            <div class="bg-white p-4 rounded-lg shadow">
              <!-- Display images for the product -->
                <div class="mb-4">
                    <img src="<?php echo htmlspecialchars($product['image_url']); ?>" alt="Product Image" class="rounded-md mb-2">
                </div>

              <h3 class="font-semibold text-lg"><?php echo htmlspecialchars($product['title']); ?></h3>
              <p class="text-gray-500 text-sm"><?php echo htmlspecialchars($product['description']); ?></p>
              <div class="mt-4">
                <p class="text-gray-600"><strong>Price:</strong> $<?php echo htmlspecialchars($product['price']); ?></p>
                <p class="text-gray-600"><strong>Type:</strong> <?php echo htmlspecialchars($product['type']); ?></p>
                <p class="text-gray-600"><strong>Status:</strong> <?php echo htmlspecialchars($product['status']); ?></p>
                <p class="text-gray-600"><strong>Requirement:</strong> <?php echo htmlspecialchars($product['requirement']); ?></p>
              </div>
              <div class="mt-4 flex justify-between items-center">
                <a href="request.php?product_id=<?php echo $product['product_id']; ?>" class="bg-blue-600 text-white px-4 py-2 rounded-lg">Trade</a>
                <a href="inboxmessage.php?receiver_id=<?php echo $product['user_id']; ?>" class="bg-green-600 text-white px-4 py-2 rounded-lg">Negotiate</a>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </main>
    </div>
  </div>

  <button class="floating-button" onclick="openPopup()">+</button>

  <!-- Popup Form -->
  <div id="popup-overlay" class="popup-overlay" style="display: none;" onclick="closePopup(event)">
    <div class="popup-form" onclick="event.stopPropagation()">
      <h2>Add Product</h2>
      <form method="POST" action="" enctype="multipart/form-data">
        <input type="text" name="title" placeholder="Title" required>
        <textarea name="description" placeholder="Description" required></textarea>
        <input type="number" name="price" placeholder="Price" step="0.01" required>
        <select name="type" required>
          <option value="food">Food</option>
          <option value="electronics">Electronics</option>
          <option value="clothing">Clothing</option>
          <option value="other">Other</option>
        </select>
        <select name="status" required>
          <option value="available">Available</option>
          <option value="pending">Pending</option>
        </select>
        <input type="date" name="d_o_f" placeholder="Date of Purchase" required>
        <input type="text" name="requirement" placeholder="Requirement" required>
        <input type="file" name="image" accept="image/*" required>
        <button type="submit" name="add_product">Submit</button>
      </form>
    </div>
  </div>

  <!-- Footer -->
  <footer class="mt-8 bg-white p-6 shadow">
    <div class="container mx-auto flex justify-between items-center">
      <div>
        <h3 class="font-bold">Switch Mart</h3>
        <p class="text-sm text-gray-500">© 2025 Switch Mart Inc.</p>
      </div>
      <nav class="flex space-x-6">
        <a href="#" class="text-gray-500 hover:text-gray-800">About</a>
        <a href="#" class="text-gray-500 hover:text-gray-800">Blog</a>
        <a href="#" class="text-gray-500 hover:text-gray-800">Contact</a>
      </nav>
    </div>
  </footer>

  <script>
    // Update price range value
    const priceRange = document.getElementById('price-range');
    const priceValue = document.getElementById('price-value');
    priceRange.addEventListener('input', () => {
      priceValue.textContent = priceRange.value;
    });

    // Reset filters
    function resetFilters() {
      window.location.href = window.location.pathname;
    }

    // Open popup form
    function openPopup() {
      document.getElementById('popup-overlay').style.display = 'flex';
    }

    // Close popup form
    function closePopup(event) {
      if (event.target === document.getElementById('popup-overlay')) {
        document.getElementById('popup-overlay').style.display = 'none';
      }
    }
  </script>
</body>
</html>