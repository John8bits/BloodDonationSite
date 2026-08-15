<?php

session_start();

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = $_SESSION['login_error'] ?? "";
unset($_SESSION['login_error']);

$success = isset($_GET['registered']) && $_GET['registered'] === '1';

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Login</title>

    <link rel="stylesheet" href="assets/style.css">

</head>

<body>

<div class="auth-container">

    <div class="auth-card">

        <h1>Welcome Back</h1>

        <p class="subtitle">
            Login to your account
        </p>

        <?php if ($success): ?>

            <div class="success-message">
                Registration successful. You can now login.
            </div>

        <?php endif; ?>


        <?php if (!empty($error)): ?>

            <div class="error-message">
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </div>

        <?php endif; ?>


        <form action="login_process.php" method="POST">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars(
                    $_SESSION['csrf_token'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
            >


            <div class="form-group">

                <label for="login">Username or Email</label>

                <input
                    type="text"
                    id="login"
                    name="login"
                    required
                    autocomplete="username"
                >

            </div>


            <div class="form-group">

                <label for="password">Password</label>

                <input
                    type="password"
                    id="password"
                    name="password"
                    required
                    autocomplete="current-password"
                >

            </div>


            <button type="submit" class="btn">
                Login
            </button>

        </form>


        <p class="switch">

            Don't have an account?

            <a href="register.php">
                Register
            </a>

        </p>

    </div>

</div>

</body>

</html>