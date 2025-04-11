<?php
session_start();

include 'db.php';

if (isset($_POST['login'])) {
    $email = $_POST['email'];
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT user_id, name, password, phone_number, location, rating, profile_photo_url FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['name'] = $user['name'];
        $_SESSION['email'] = $email;
        $_SESSION['phone_number'] = $user['phone_number'];
        $_SESSION['location'] = $user['location'];
        $_SESSION['rating'] = $user['rating'];
        $_SESSION['profile_photo_url'] = $user['profile_photo_url'];

        echo "<div class='fixed top-4 left-1/2 transform -translate-x-1/2 z-50 p-4 bg-green-100 border-l-4 border-green-500 text-green-700 rounded shadow-lg'>Login successful! Welcome, {$user['name']}.</div>";
        header("Refresh: 2; url=landingpage.php");
        exit();
    } else {
        echo "<div class='fixed top-4 left-1/2 transform -translate-x-1/2 z-50 p-4 bg-red-100 border-l-4 border-red-500 text-red-700 rounded shadow-lg'>Invalid email or password.</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="font-poppins">
    <!-- Video Background -->
    <div class="fixed inset-0 z-0 overflow-hidden">
        <video autoplay loop muted playsinline class="absolute inset-0 w-full h-full object-cover">
            <source src="./assets/videos/trade-bg.mp4" type="video/mp4">
            Your browser does not support the video tag.
        </video>
        <div class="absolute inset-0 bg-black bg-opacity-50"></div>
    </div>

    <!-- Main Content -->
    <div class="relative z-10 flex items-center justify-center min-h-screen p-4">
        <div class="w-full max-w-md">
            <div class="glass-container bg-white bg-opacity-15 backdrop-blur-lg rounded-xl shadow-xl overflow-hidden p-8">
                <h1 class="text-3xl font-bold text-white text-center mb-6">Welcome Back</h1>

                <form method="POST" action="login.php" class="space-y-4">
                    <div>
                        <input class="w-full px-4 py-3 rounded-lg bg-white bg-opacity-30 focus:bg-opacity-60 outline-none text-white placeholder-white placeholder-opacity-70" 
                               type="email" 
                               name="email" 
                               placeholder="Email" 
                               required>
                    </div>
                    
                    <div>
                        <input class="w-full px-4 py-3 rounded-lg bg-white bg-opacity-30 focus:bg-opacity-60 outline-none text-white placeholder-white placeholder-opacity-70" 
                               type="password" 
                               name="password" 
                               placeholder="Password" 
                               required>
                    </div>
                    
                    <button type="submit" name="login" class="w-full py-3 px-4 bg-gradient-to-r from-pink-500 to-purple-600 text-white font-semibold rounded-lg shadow-md hover:shadow-lg transform hover:scale-105 transition duration-300 ease-in-out">
                        Login
                    </button>
                </form>

                <div class="mt-6 text-center">
                    <p class="text-white text-opacity-80">
                        Don't have an account? 
                        <a href="register.php" class="text-blue-300 hover:text-blue-200 font-medium transition duration-200">Register here</a>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <style>
        .glass-container {
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        input::placeholder {
            color: rgba(255, 255, 255, 0.7);
        }
        
        @media (max-width: 640px) {
            .glass-container {
                padding: 1.5rem;
            }
            
            h1 {
                font-size: 1.8rem;
            }
        }
    </style>
</body>
</html>