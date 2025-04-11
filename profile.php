<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Fetch user's products
$stmt = $pdo->prepare("SELECT * FROM products WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$user_id]);
$products = $stmt->fetchAll();

// Handle profile updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $name = $_POST['name'];
        $email = $_POST['email'];
        $phone_number = $_POST['phone_number'];
        $location = $_POST['location'];
        $bio = $_POST['bio'];
        $address = $_POST['address'];
        $contact_details = $_POST['contact_details'];
        
        $stmt = $pdo->prepare("
            UPDATE users SET 
            name = ?, email = ?, phone_number = ?, location = ?, 
            bio = ?, address = ?, contact_details = ?
            WHERE user_id = ?
        ");
        $stmt->execute([$name, $email, $phone_number, $location, $bio, $address, $contact_details, $user_id]);
        
        header("Location: profile.php");
        exit();
    }
    
    // Handle profile photo upload
    if (isset($_POST['update_photo'])) {
        if (!empty($_FILES['profile_photo']['name'])) {
            $target_dir = "assets/images/profile_photos/";
            if (!file_exists($target_dir)) {
                mkdir($target_dir, 0777, true);
            }
            
            $file_extension = pathinfo($_FILES["profile_photo"]["name"], PATHINFO_EXTENSION);
            $new_filename = uniqid() . '.' . $file_extension;
            $target_file = $target_dir . $new_filename;
            
            // Check if image file is valid
            $check = getimagesize($_FILES["profile_photo"]["tmp_name"]);
            if ($check !== false && in_array(strtolower($file_extension), ['jpg', 'jpeg', 'png', 'gif'])) {
                if (move_uploaded_file($_FILES["profile_photo"]["tmp_name"], $target_file)) {
                    // Delete old photo if it exists and isn't the default
                    if (!empty($user['profile_photo_url']) && $user['profile_photo_url'] !== 'assets/images/default-profile.png') {
                        @unlink($user['profile_photo_url']);
                    }
                    
                    $pdo->prepare("UPDATE users SET profile_photo_url = ? WHERE user_id = ?")
                      ->execute([$target_file, $user_id]);
                    header("Location: profile.php");
                    exit();
                }
            }
        }
    }
    
    // Handle product deletion
    if (isset($_POST['delete_product'])) {
        $product_id = $_POST['product_id'];
        
        // First get the image URL to delete the file
        $stmt = $pdo->prepare("SELECT image_url FROM products WHERE product_id = ? AND user_id = ?");
        $stmt->execute([$product_id, $user_id]);
        $product = $stmt->fetch();
        
        if ($product && !empty($product['image_url']) && $product['image_url'] !== 'assets/images/product-placeholder.jpg') {
            @unlink($product['image_url']);
        }
        
        // Then delete the product
        $stmt = $pdo->prepare("DELETE FROM products WHERE product_id = ? AND user_id = ?");
        $stmt->execute([$product_id, $user_id]);
        header("Location: profile.php");
        exit();
    }
    
    // Handle product submission
    if (isset($_POST['add_product'])) {
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $price = (float)$_POST['price'];
        $type = trim($_POST['type']);
        $requirement = trim($_POST['requirement']);
        $d_o_f = trim($_POST['d_o_f']);
        $status = "available";
        $image_url = 'assets/images/product-placeholder.jpg'; // Default placeholder
        
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
        
        // Insert product into the database
        $stmt = $pdo->prepare("
            INSERT INTO products 
            (user_id, title, description, price, type, d_o_f, status, requirement, image_url) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $user_id, $title, $description, $price, $type, $d_o_f, $status, $requirement, $image_url
        ]);

        header("Location: profile.php");
        exit();
    }
    
    // Handle product update
    if (isset($_POST['update_product'])) {
        $product_id = $_POST['product_id'];
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $price = (float)$_POST['price'];
        $type = trim($_POST['type']);
        $requirement = trim($_POST['requirement']);
        $d_o_f = trim($_POST['d_o_f']);
        $status = trim($_POST['status']);
        $current_image = $_POST['current_image'] ?? '';
        
        $image_url = $current_image; // Keep current image unless new one is uploaded
        
        // Handle new image upload
        if (!empty($_FILES['new_image']['name'])) {
            $upload_dir = 'assets/images/products/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_extension = pathinfo($_FILES["new_image"]["name"], PATHINFO_EXTENSION);
            $new_filename = uniqid() . '.' . $file_extension;
            $target_file = $upload_dir . $new_filename;
            
            // Check if image file is valid
            $check = getimagesize($_FILES["new_image"]["tmp_name"]);
            if ($check !== false && in_array(strtolower($file_extension), ['jpg', 'jpeg', 'png', 'gif'])) {
                if (move_uploaded_file($_FILES["new_image"]["tmp_name"], $target_file)) {
                    // Delete old image if it exists and isn't the placeholder
                    if (!empty($current_image) && $current_image !== 'assets/images/product-placeholder.jpg') {
                        @unlink($current_image);
                    }
                    $image_url = $target_file;
                }
            }
        }
        
        $stmt = $pdo->prepare("
            UPDATE products SET 
            title = ?, description = ?, price = ?, type = ?, 
            d_o_f = ?, status = ?, requirement = ?, image_url = ?
            WHERE product_id = ? AND user_id = ?
        ");
        $stmt->execute([
            $title, $description, $price, $type, $d_o_f, $status, $requirement, $image_url, 
            $product_id, $user_id
        ]);
        
        header("Location: profile.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Profile | Switch Mart</title>
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
      <img src="assets/images/logo.png" alt="Logo" class="h-8">
      <nav class="hidden md:flex space-x-6">
        <a href="landingpage.php" class="text-gray-600 hover:text-blue-600 font-medium">Shop</a>
        <a href="messages.php" class="text-gray-600 hover:text-blue-600 font-medium">Messages</a>
        <a href="profile.php" class="text-orange-600 font-medium">Profile</a>
      </nav>
      <div class="flex items-center space-x-4">
        <img src="<?= htmlspecialchars($user['profile_photo_url'] ?? 'assets/images/default-profile.png') ?>" 
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
          <img src="<?= htmlspecialchars($user['profile_photo_url'] ?? 'assets/images/default-profile.png') ?>" 
               alt="Profile Photo" class="h-40 w-40 rounded-full object-cover border-4 border-white shadow-md mb-4">
          <form method="POST" enctype="multipart/form-data" class="text-center">
            <label class="cursor-pointer">
              <span class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition inline-block">
                Change Photo
              </span>
              <input type="file" name="profile_photo" class="hidden" accept="image/*">
              <input type="hidden" name="update_photo" value="1">
            </label>
            <button type="submit" class="hidden" id="photoSubmitBtn"></button>
          </form>
          <div class="mt-4 text-center">
            <span class="text-xl font-bold"><?= htmlspecialchars($user['name']) ?></span>
            <div class="flex items-center justify-center mt-2">
              <i class="fas fa-star text-yellow-400 mr-1"></i>
              <span><?= number_format($user['rating'] ?? 0, 1) ?></span>
            </div>
          </div>
        </div>

        <!-- Profile Details -->
        <div class="md:w-3/4 p-6">
          <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold text-gray-800">Profile Information</h1>
            <button id="editProfileBtn" class="edit-btn bg-gray-200 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-300">
              <i class="fas fa-edit mr-2"></i>Edit Profile
            </button>
          </div>

          <form id="profileForm" method="POST" class="hidden">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
              <div>
                <label class="block text-gray-700 mb-2">Full Name</label>
                <input type="text" name="name" value="<?= htmlspecialchars($user['name']) ?>" 
                       class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
              </div>
              <div>
                <label class="block text-gray-700 mb-2">Email</label>
                <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" 
                       class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
              </div>
              <div>
                <label class="block text-gray-700 mb-2">Phone Number</label>
                <input type="tel" name="phone_number" value="<?= htmlspecialchars($user['phone_number'] ?? '') ?>" 
                       class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
              </div>
              <div>
                <label class="block text-gray-700 mb-2">Location</label>
                <input type="text" name="location" value="<?= htmlspecialchars($user['location'] ?? '') ?>" 
                       class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
              </div>
              <div class="md:col-span-2">
                <label class="block text-gray-700 mb-2">Bio</label>
                <textarea name="bio" rows="3" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
              </div>
              <div class="md:col-span-2">
                <label class="block text-gray-700 mb-2">Address</label>
                <textarea name="address" rows="2" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"><?= htmlspecialchars($user['address'] ?? '') ?></textarea>
              </div>
              <div>
                <label class="block text-gray-700 mb-2">Contact Details</label>
                <input type="text" name="contact_details" value="<?= htmlspecialchars($user['contact_details'] ?? '') ?>" 
                       class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
              </div>
            </div>
            <div class="flex justify-end space-x-4">
              <button type="button" id="cancelEditBtn" class="bg-gray-200 text-gray-700 px-6 py-2 rounded-lg hover:bg-gray-300">
                Cancel
              </button>
              <button type="submit" name="update_profile" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700">
                Save Changes
              </button>
            </div>
          </form>

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
        <h2 class="text-2xl font-bold text-gray-800">My Products</h2>
        <button onclick="openAddProductPopup()" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700">
          Add Product
        </button>
      </div>

      <?php if (empty($products)): ?>
        <div class="bg-white rounded-lg shadow p-6 text-center">
          <p class="text-gray-600">You haven't posted any products yet.</p>
          <button onclick="openAddProductPopup()" class="mt-4 inline-block bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700">
            Post Your First Product
          </button>
        </div>
      <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          <?php foreach ($products as $product): ?>
            <div class="product-card bg-white rounded-lg shadow-md overflow-hidden transition duration-300">
              <img src="<?= htmlspecialchars($product['image_url'] ?? 'assets/images/product-placeholder.jpg') ?>" 
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
                <div class="mt-4 flex justify-between space-x-2">
                <button onclick='openEditProductPopup(<?= json_encode($product) ?>)'
                 class="flex-1 bg-yellow-500 text-white text-center py-2 rounded hover:bg-yellow-600">
                    <i class="fas fa-edit mr-2"></i>Edit
                  </button>
                  <form method="POST" class="flex-1">
                    <input type="hidden" name="product_id" value="<?= $product['product_id'] ?>">
                    <button type="submit" name="delete_product" 
                            class="w-full bg-red-500 text-white py-2 rounded hover:bg-red-600"
                            onclick="return confirm('Are you sure you want to delete this product?')">
                      <i class="fas fa-trash mr-2"></i>Delete
                    </button>
                  </form>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </main>
  
  <!-- Edit Product Popup -->
  <div id="editProductPopup" class="popup-overlay" style="display: none;">
    <div class="popup-form">
      <h2>Edit Product</h2>
      <form method="POST" enctype="multipart/form-data" id="editProductForm">
        <input type="hidden" name="product_id" id="editProductId">
        <input type="hidden" name="current_image" id="editCurrentImage">
        <input type="text" name="title" id="editTitle" placeholder="Title" required>
        <textarea name="description" id="editDescription" placeholder="Description" required></textarea>
        <input type="number" name="price" id="editPrice" placeholder="Price" step="0.01" required>
        <select name="type" id="editType" required>
          <option value="food">Food</option>
          <option value="electronics">Electronics</option>
          <option value="clothing">Clothing</option>
          <option value="other">Other</option>
        </select>
        <select name="status" id="editStatus" required>
          <option value="available">Available</option>
          <option value="pending">Pending</option>
        </select>
        <input type="date" name="d_o_f" id="editDof" placeholder="Date of Purchase" required>
        <input type="text" name="requirement" id="editRequirement" placeholder="Requirement" required>
        
        <div class="mb-4">
          <label class="block mb-2">Current Image</label>
          <img id="editImagePreview" src="" alt="Current Image" class="w-full h-48 object-cover mb-2">
        </div>
        
        <label class="block mb-2">Change Image (Leave empty to keep current)</label>
        <input type="file" name="new_image" accept="image/*">
        
        <div class="flex justify-end space-x-4 mt-4">
          <button type="button" onclick="closePopup()" class="bg-gray-200 text-gray-700 px-4 py-2 rounded">
            Cancel
          </button>
          <button type="submit" name="update_product" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
            Update Product
          </button>
        </div>
      </form>
    </div>
  </div>


  <!-- Add Product Popup -->
  <div id="addProductPopup" class="popup-overlay" style="display: none;">
    <div class="popup-form">
      <h2>Add Product</h2>
      <form method="POST" enctype="multipart/form-data">
        <input type="text" name="title" placeholder="Title" required>
        <textarea name="description" placeholder="Description" required></textarea>
        <input type="number" name="price" placeholder="Price" step="0.01" required>
        <select name="type" required>
          <option value="food">Food</option>
          <option value="electronics">Electronics</option>
          <option value="clothing">Clothing</option>
          <option value="other">Other</option>
        </select>
        <input type="date" name="d_o_f" placeholder="Date of Purchase" required>
        <input type="text" name="requirement" placeholder="Requirement" required>
        
        <label class="block mb-2">Product Image</label>
        <input type="file" name="image" accept="image/*" required>
        
        <div class="flex justify-end space-x-4 mt-4">
          <button type="button" onclick="closePopup()" class="bg-gray-200 text-gray-700 px-4 py-2 rounded">
            Cancel
          </button>
          <button type="submit" name="add_product" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">
            Add Product
          </button>
        </div>
      </form>
    </div>
  </div>


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

  <script>
    // Profile edit toggle
    const editProfileBtn = document.getElementById('editProfileBtn');
    const cancelEditBtn = document.getElementById('cancelEditBtn');
    const profileForm = document.getElementById('profileForm');
    const profileView = document.getElementById('profileView');
    
    editProfileBtn.addEventListener('click', () => {
      profileForm.classList.remove('hidden');
      profileView.classList.add('hidden');
    });
    
    cancelEditBtn.addEventListener('click', () => {
      profileForm.classList.add('hidden');
      profileView.classList.remove('hidden');
    });

    // Profile photo upload
    document.querySelector('input[name="profile_photo"]').addEventListener('change', function(e) {
      if (this.files && this.files[0]) {
        const reader = new FileReader();
        const profileImg = document.querySelector('img[alt="Profile Photo"]');
        
        reader.onload = function(e) {
          profileImg.src = e.target.result;
        }
        
        reader.readAsDataURL(this.files[0]);
        document.getElementById('photoSubmitBtn').click();
      }
    });

    // Product popup functions
    function openAddProductPopup() {
      document.getElementById('addProductPopup').style.display = 'flex';
    }
    
    function openEditProductPopup(product) {
          document.getElementById('editProductId').value = product.product_id;
          document.getElementById('editTitle').value = product.title;
          document.getElementById('editDescription').value = product.description;
          document.getElementById('editPrice').value = product.price;
          document.getElementById('editType').value = product.type;
          document.getElementById('editStatus').value = product.status;
          document.getElementById('editDof').value = product.d_o_f;
          document.getElementById('editRequirement').value = product.requirement;
          document.getElementById('editCurrentImage').value = product.image_url;
          
          // Set current image preview
          const imgPreview = document.getElementById('editImagePreview');
          imgPreview.src = product.image_url || 'assets/images/product-placeholder.jpg';
          imgPreview.alt = product.title;
          
          document.getElementById('editProductPopup').style.display = 'flex';

    }
    
    function closePopup() {
      document.getElementById('addProductPopup').style.display = 'none';
      document.getElementById('editProductPopup').style.display = 'none';
    }
    
    // Close popup when clicking outside
    window.addEventListener('click', function(event) {
      if (event.target.classList.contains('popup-overlay')) {
        closePopup();
      }
    });
  </script>
</body>
</html>