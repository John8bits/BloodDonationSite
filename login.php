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
            <a class="auth-logo" href="index.php" aria-label="BloodLife home">
                <img src="images/BlifePH_no_bg.png" alt="BloodLife">
            </a>

            <h1 style="text-align: center;  margin-bottom:25px;">Sign In</h1>

            <!-- <p class="subtitle" style="text-align: center;">Sign in to continue.</p> -->

            <?php if ($success): ?>

                <div class="success-message">Registration successful. You can now login.</div>

            <?php endif; ?>

            <div id="loginMessage"
                 class="form-message"
                 aria-live="polite">
            </div>

            <form action="login_process.php" method="POST" id="loginForm" novalidate>

                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

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
                        <input type="password" class="password-input" id="password" name="password" autocomplete="current-password"
                            placeholder="******">
                        <button type="button" class="password-toggle" data-password-toggle aria-label="Show password" aria-pressed="false">
                            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M2.1 12s3.6-7 9.9-7 9.9 7 9.9 7-3.6 7-9.9 7-9.9-7-9.9-7Z"/><circle cx="12" cy="12" r="3"/><path class="password-toggle-slash" d="m4 4 16 16"/></svg>
                        </button>

                    </div>
                    <?php if (isset($errors['password'])): ?>

                        <small class="field-error">
                            <?= htmlspecialchars($errors['password'], ENT_QUOTES, 'UTF-8') ?>
                        </small>

                    <?php endif; ?>

                </div>

                <button type="submit" class="btn" id="loginButton">Sign In</button>

            </form>

            <p class="switch"> Don't have an account? <a href="register.php">Register</a> </p>

        </div>

        
    </div>

    <script src="validation/login.js"></script>
    <script src="validation/password-visibility.js"></script>

</body>

</html>
