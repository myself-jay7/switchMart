<?php
// Database connection
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
include 'db.php'; // Ensure db.php contains your $pdo connection setup

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$message = ""; // Initialize $message variable

// Fetch the product_id from GET and buyer_id from the session
$product_id = isset($_GET['product_id']) ? $_GET['product_id'] : null;
$buyer_id = $_SESSION['user_id'];

// If product_id is not set, redirect back
if (!$product_id) {
    header("Location: landingpage.php"); // Redirect to index or product listing page
    exit();
}

// Fetch seller_id from the database using product_id
$stmt = $pdo->prepare("SELECT user_id FROM products WHERE product_id = :product_id");
$stmt->bindParam(':product_id', $product_id, PDO::PARAM_INT);
$stmt->execute();
$seller = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$seller) {
    // If no product found with the given product_id
    $message = "❌ Error: Product not found.";
} else {
    $seller_id = $seller['user_id'];
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get the POST data
    $name = $_POST['name'];
    $email = $_POST['email'];
    $item_name = $_POST['item_name'];
    $request_type = $_POST['request_type'];
    $message_text = $_POST['message'];

    // Prepare the SQL query with placeholders
    $stmt = $pdo->prepare("INSERT INTO requests (name, email, item_name, request_type, message, product_id, buyer_id, seller_id) 
            VALUES (:name, :email, :item_name, :request_type, :message, :product_id, :buyer_id, :seller_id)");

    // Bind the parameters
    $stmt->bindParam(':name', $name);
    $stmt->bindParam(':email', $email);
    $stmt->bindParam(':item_name', $item_name);
    $stmt->bindParam(':request_type', $request_type);
    $stmt->bindParam(':message', $message_text);
    $stmt->bindParam(':product_id', $product_id, PDO::PARAM_INT);
    $stmt->bindParam(':buyer_id', $buyer_id, PDO::PARAM_INT);
    $stmt->bindParam(':seller_id', $seller_id, PDO::PARAM_INT);

    // Execute the query
    if ($stmt->execute()) {
        $message = "✅ Request sent successfully!";
    } else {
        $message = "❌ Error: Unable to send the request.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Order/Exchange - Trade System</title>
    <style>
        /* Global Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Arial', sans-serif;
        }

        body {
            background: #1E2A38;
            color: #fff;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            animation: fadeIn 0.8s ease-in-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .container {
            max-width: 450px;
            width: 90%;
            background: #2D3E50;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0px 5px 15px rgba(0, 0, 0, 0.3);
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        h2 {
            color: #3B82F6;
            font-weight: 600;
            margin-bottom: 10px;
        }

        /* Form Styling */
        form {
            display: flex;
            flex-direction: column;
            gap: 15px;
            animation: fadeIn 1s ease-in-out;
        }

        label {
            font-weight: bold;
            color: #E5E7EB;
            text-align: left;
        }

        input, textarea, select {
            width: 100%;
            padding: 12px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            font-size: 16px;
            background: #1E2A38;
            color: #fff;
            outline: none;
            transition: all 0.3s ease-in-out;
        }

        input:focus, textarea:focus, select:focus {
            border-color: #3B82F6;
            transform: scale(1.02);
            box-shadow: 0px 0px 8px rgba(59, 130, 246, 0.5);
        }

        button {
            padding: 12px;
            background: #3B82F6;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 18px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease-in-out;
        }

        button:hover {
            background: #2563EB;
            transform: scale(1.05);
            box-shadow: 0px 5px 15px rgba(59, 130, 246, 0.5);
        }

        button:active {
            transform: scale(0.97);
        }

        .message {
            margin-top: 10px;
            font-weight: bold;
            color: #3B82F6;
        }

        /* Responsive Design */
        @media (max-width: 600px) {
            .container {
                width: 95%;
                padding: 15px;
            }
        }
    </style>
</head>
<body>

    <div class="container">
        <h2>Order or Exchange Request</h2>

        <form method="POST">
            <label>Name:</label>
            <input type="text" name="name" required>

            <label>Email:</label>
            <input type="email" name="email" required>

            <label>Item Name:</label>
            <input type="text" name="item_name" required>

            <label>Request Type:</label>
            <select name="request_type" required>
                <option value="Order">Order</option>
                <option value="Exchange">Exchange</option>
            </select>

            <label>Message:</label>
            <textarea name="message" rows="4" required></textarea>

            <button type="submit">Send Request</button>
        </form>

        <?php if ($message) echo "<p class='message'>$message</p>"; ?>
    </div>

</body>
</html>
