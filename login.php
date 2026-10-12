<?php

session_start();
require_once __DIR__ . "/config/auth.php";

if (is_logged_in()) {
    header("Location: " . role_home_path());
    exit;
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$errors = $_SESSION['login_errors'] ?? [];

unset($_SESSION['login_errors']);

// $old = $_SESSION['login_old'] ?? [];
// unset($_SESSION['login_old']);

$success =
    isset($_GET['registered']) &&
    $_GET['registered'] === '1';

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Login</title>

    <link rel="stylesheet" href="assets/style.css">
</head>

<body>

    <div class="auth-container">

        <div class="auth-card">

            <!-- <div class="brand">
                <div class="blood-icon">♥</div>
                <span style="font-size: 30px;">BloodLife</span>
            </div> -->
            <img src="images/Blife_logo.png" style="width: 140px; height: auto; margin-left: 110px; " alt="BloodLife Logo" class="logo">

            <h1 style="text-align: center;  font-variant: small-caps;">Sign In</h1>

            <!-- <p class="subtitle" style="text-align: center;">Sign in to continue.</p> -->

            <?php if ($success): ?>

                <div class="success-message">Registration successful. You can now login.</div>

            <?php endif; ?>

            <div id="loginMessage"
                 class="form-message"
                 aria-live="polite">
            </div>

            <form action="login_process.php" method="POST" id="loginForm" novalidate>

                <input type="hidden" name="csrf_token"
                    value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

                <div class="form-group">

                    <label for="login" >Email</label>

                    <div class="input-wrapper">

                        <img src="images/mail.png" alt="Email icon">
                        <input type="email" id="login" name="login" autocomplete="username"
                            placeholder="eg. jbits@gmail.com">
                    </div>

                    <?php if (isset($errors['email'])): ?>

                        <small class="field-error">
                            <?= htmlspecialchars($errors['email'], ENT_QUOTES, 'UTF-8') ?>
                        </small>

                    <?php endif; ?>

                </div>

                <div class="form-group">

                    <label for="password">Password</label>

                    <div class="input-wrapper">

                        <img src="images/padlock.png" alt="Password icon">
                        <input type="password" id="password" name="password" autocomplete="current-password"
                            placeholder="******">

                    </div>
                    <?php if (isset($errors['password'])): ?>

                        <small class="field-error">
                            <?= htmlspecialchars($errors['password'], ENT_QUOTES, 'UTF-8') ?>
                        </small>

                    <?php endif; ?>

                </div>

                <button type="submit" class="btn" id="loginButton">Login</button>

            </form>

            <p class="switch"> Don't have an account? <a href="register.php">Register</a> </p>

        </div>

        
    </div>

    <script src="validation/login.js"></script>

</body>

</html>