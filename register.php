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

$errors = $_SESSION['register_errors'] ?? [];
unset($_SESSION['register_errors']);
$old = $_SESSION['register_old'] ?? [];
unset($_SESSION['register_old']);

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register</title>
    <link rel="stylesheet" href="assets/style.css">
</head>

<body>
    <div class="auth-container register-container">
        <div class="auth-card">
            <a class="auth-logo" href="index.php" aria-label="BloodLife home">
                <img src="images/Blife_logo.png" alt="BloodLife">
            </a>

            <h1 class="auth-heading">Create Account</h1>
            <p class="subtitle auth-subtitle">Join the community.</p>

            <?php if (isset($errors['general'])): ?>
                <div class="error-message">
                    <?= htmlspecialchars($errors['general'], ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <div id="registerMessage" class="form-message" aria-live="polite"></div>

            <form action="register_process.php" method="POST" id="registerForm" novalidate>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

                <div class="form-group">
                    <label for="email">Email</label>
                    <div class="input-wrapper">
                        <img src="images/mail.png" alt="Email icon">
                        <input type="email" id="email" name="email" maxlength="255" autocomplete="email" placeholder="jbits@gmail.com" value="<?= htmlspecialchars($old['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
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
                        <input type="password" id="password" name="password" autocomplete="new-password" placeholder="Create password">
                    </div>
                    <?php if (isset($errors['password'])): ?>
                        <small class="field-error">
                            <?= htmlspecialchars($errors['password'], ENT_QUOTES, 'UTF-8') ?>
                        </small>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm password</label>
                    <div class="input-wrapper">
                        <img src="images/padlock.png" alt="Password icon">
                        <input type="password" id="confirm_password" name="confirm_password" autocomplete="new-password" placeholder="It should be the same as your password">
                    </div>
                    <?php if (isset($errors['confirm_password'])): ?>
                        <small class="field-error">
                            <?= htmlspecialchars($errors['confirm_password'], ENT_QUOTES, 'UTF-8') ?>
                        </small>
                    <?php endif; ?>
                </div>

                <button type="submit" class="btn" id="registerButton">Register</button>
            </form>

            <p class="switch">Already have an account? <a href="login.php">Sign in</a></p>
        </div>
    </div>

    <script src="validation/register.js"></script>
</body>

</html>
