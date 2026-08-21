<?php

session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: .../login.php");
    exit;
}

$firstName = $_SESSION['first_name'] ?? '';
$lastName = $_SESSION['last_name'] ?? '';

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Dashboard | BloodLife</title>

    <style>

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: Arial, sans-serif;
            /* min-height: 100vh; */
            background: #f8f9fa;
            /* display: flex;
            justify-content: center;
            align-items: center; */
        }

        .dashboard {
            /* background: white; */
            padding: 40px;
            border-radius: 16px;
            /* box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08); */
            /* text-align: center; */
        }

        
        .blood-icon {
            width: 60px;
            height: 60px;
            margin: 0 auto 20px;

            display: flex;
            align-items: center;
            justify-content: center;

            background: #e63946;
            color: white;

            border-radius: 50%;

            font-size: 28px;
        }

        h1 {
            margin-bottom: 10px;
            color: #222;
        }

        .welcome {
            color: #666;
            margin-bottom: 30px;
        }

        .user-name {
            color: #d62839;
            font-weight: bold;
        }

        .logout-btn {
            display: inline-block;
            padding: 12px 25px;
            background: #d62839;
            color: white;
            position:absolute;
            top:50px;
            right:10px;
            text-decoration: none;

            border-radius: 8px;

            transition: 0.2s;
        }

        .logout-btn:hover {
            background: #b71c2b;
        }

    </style>

</head>

<body>

    <main class="dashboard">

        <div class="blood-icon">
            ♥
        </div>

        <h1>
            Welcome to BloodLife
        </h1>

        <p class="welcome">
            Hello,
            <span class="user-name">
                <?= htmlspecialchars(
                    $firstName . ' ' . $lastName,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </span>
        </p>

        <a
            href="../logout.php"
            class="logout-btn"
        >
            Logout
        </a>

    </main>

</body>

</html>