<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Switch Mart - Trade with Ease</title>
    <style>
        /* General Reset */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            color: #fff;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            overflow: hidden;
            position: relative;
        }

        #bg-video {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            z-index: -1;
        }

        .overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            z-index: 1;
        }

        .container {
            text-align: center;
            position: relative;
            z-index: 2;
        }

        h1 {
            font-size: 4rem;
            font-weight: 700;
            margin-bottom: 10px;
            animation: fadeIn 2s ease-in-out;
        }

        p {
            font-size: 1.5rem;
            margin-bottom: 40px;
            animation: fadeIn 3s ease-in-out;
        }

        .buttons {
            display: flex;
            justify-content: center;
            gap: 20px;
            animation: slideUp 2s ease-in-out;
        }

        .btn {
            padding: 15px 30px;
            font-size: 1.2rem;
            color: #fff;
            background-color: #007bff;
            border: none;
            border-radius: 20px;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 123, 255, 0.3);
        }

        .btn:hover {
            background-color: #0056b3;
            transform: translateY(-5px);
            box-shadow: 0 6px 20px rgba(0, 123, 255, 0.5);
        }

        .btn-secondary {
            background-color: #28a745;
        }

        .btn-secondary:hover {
            background-color: #218838;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideUp {
            from { transform: translateY(50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        @media (max-width: 768px) {
            h1 {
                font-size: 2.5rem;
            }

            p {
                font-size: 1.2rem;
            }

            .btn {
                padding: 10px 20px;
                font-size: 1rem;
            }
        }
    </style>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;700&display=swap" rel="stylesheet">
</head>
<body>
    <video id="bg-video" autoplay muted loop>
        <source src="assets/videos/trade-bg.mp4" type="video/mp4">
        Your browser does not support the video tag.
    </video>

    <div class="overlay"></div>

    <div class="container">
        <h1>Switch Mart</h1>
        <p>~ Trade with Ease</p>
        <div class="buttons">
            <a href="login.php" class="btn">Login</a>
            <a href="signup.php" class="btn btn-secondary">Sign Up</a>
        </div>
    </div>
</body>
</html>