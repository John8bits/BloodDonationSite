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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Login | Blood Donation</title>

    <link rel="stylesheet" href="assets/style.css">
</head>

<body>

    <div class="auth-container">

        <div class="auth-card">

            <div class="brand">
                <div class="blood-icon">♥</div>
                <span>BloodLife</span>
            </div>

            <h1>Welcome Back</h1>

            <p class="subtitle">
                Login to your account and help save lives.
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

            <div id="loginMessage" class="form-message" aria-live="polite"></div>

            <form action="login_process.php" method="POST" id="loginForm" novalidate >

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

                    <label for="login">
                        Email
                    </label>

                    <div class="input-wrapper">

                        <img src="mail.png" alt="Email icon">

                        <input
                            type="text"
                            id="login"
                            name="login"
                            autocomplete="username"
                            placeholder="eg. jbits@gmail.com"
                        >

                    </div>

                    <small id="loginError" class="field-error"></small>

                </div>

                <div class="form-group">

                    <label for="password">
                        Password
                    </label>

                    <div class="input-wrapper">

                        <img src="padlock.png" alt="Password icon">

                        <input
                            type="password"
                            id="password"
                            name="password"
                            autocomplete="current-password"
                            placeholder="******"
                        >

                    </div>

                    <!-- <small id="passwordHint" class="password-hint">
                        Password must be at least 6 characters.
                    </small> -->

                    <small id="passwordError" class="field-error"></small>

                </div>

                <button
                    type="submit"
                    class="btn"
                    id="loginButton"
                >
                    Login
                </button>

            </form>

            <p class="switch">
                Don't have an account?
                <a href="register.php">Register</a>
            </p>

        </div>

        <div class="auth-image">

            <img
                src="side_login2.png"
                alt="Blood donation"
            >

            <div class="image-overlay"></div>

            <div class="image-content">

                <h2>
                    Donate Blood.<br>
                    Save Lives.
                </h2>

                <p>
                    Every blood donation can make a difference.
                    Your generosity can give someone another chance at life.
                </p>

            </div>

        </div>

    </div>

    <script src="validation/login.js"></script>

</body>

</html>