<?php
session_start();

include 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $phone_number = trim($_POST['phone_number']);
    $location = trim($_POST['location']);

    if (empty($name) || empty($email) || empty($password) || empty($phone_number) || empty($location)) {
        $error = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } else {
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = "Email already registered.";
        } else {
            $hashed_password = password_hash($password, PASSWORD_BCRYPT);

            $stmt = $pdo->prepare("INSERT INTO users (name, email, password, phone_number, location) VALUES (?, ?, ?, ?, ?)");
            if ($stmt->execute([$name, $email, $hashed_password, $phone_number, $location])) {
                $success = "Registration successful! You can now login.";
                header("Location: login.php");
                exit();
            } else {
                $error = "Registration failed. Please try again.";
                header("Location: register.php");
                exit();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register</title>
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
                <h1 class="text-3xl font-bold text-white text-center mb-6">Create Account</h1>
                
                <?php if (isset($error)): ?>
                    <div class="mb-4 p-3 bg-red-100 border-l-4 border-red-500 text-red-700 rounded">
                        <p><?php echo htmlspecialchars($error); ?></p>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($success)): ?>
                    <div class="mb-4 p-3 bg-green-100 border-l-4 border-green-500 text-green-700 rounded">
                        <p><?php echo htmlspecialchars($success); ?></p>
                    </div>
                <?php endif; ?>

                <form method="POST" action="register.php" class="space-y-4">
                    <div>
                        <input class="w-full px-4 py-3 rounded-lg bg-white bg-opacity-30 focus:bg-opacity-60 outline-none text-white placeholder-white placeholder-opacity-70" 
                               type="text" 
                               name="name" 
                               placeholder="Full Name" 
                               required>
                    </div>
                    
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
                    
                    <div>
                        <input class="w-full px-4 py-3 rounded-lg bg-white bg-opacity-30 focus:bg-opacity-60 outline-none text-white placeholder-white placeholder-opacity-70" 
                               type="text" 
                               name="phone_number" 
                               placeholder="Phone Number" 
                               required>
                    </div>
                    
                    <div>
                        <input class="w-full px-4 py-3 rounded-lg bg-white bg-opacity-30 focus:bg-opacity-60 outline-none text-white placeholder-white placeholder-opacity-70" 
                               type="text" 
                               name="location" 
                               placeholder="Location" 
                               required>
                    </div>
                    
                    <button type="submit" class="w-full py-3 px-4 bg-gradient-to-r from-pink-500 to-purple-600 text-white font-semibold rounded-lg shadow-md hover:shadow-lg transform hover:scale-105 transition duration-300 ease-in-out">
                        Register Now
                    </button>
                </form>

                <div class="mt-6 text-center">
                    <p class="text-white text-opacity-80">
                        Already have an account? 
                        <a href="login.php" class="text-blue-300 hover:text-blue-200 font-medium transition duration-200">Sign In</a>
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