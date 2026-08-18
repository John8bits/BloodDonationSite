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

        <!-- LEFT SIDE - LOGIN -->
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

            <form action="login_process.php" method="POST">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>"
                >

                <div class="form-group">

                    <label for="login">
                        Username or Email
                    </label>

                    <input
                        type="text"
                        id="login"
                        name="login"
                        required
                        autocomplete="username"
                        placeholder="Enter your username or email"
                    >

                </div>

                <div class="form-group">

                    <label for="password">
                        Password
                    </label>

                    <input
                        type="password"
                        id="password"
                        name="password"
                        required
                        autocomplete="current-password"
                        placeholder="Enter your password"
                    >

                </div>

                <button type="submit" class="btn">
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
                src="assets/images/blood-donation.jpg"
                alt="Blood donation"
            >

            <div class="image-overlay"></div>

            <div class="image-content">

                <div class="blood-drop">♥</div>

                <h2>Donate Blood.<br>Save Lives.</h2>

                <p>
                    Every blood donation can make a difference.
                    Your generosity can give someone another chance at life.
                </p>

                <div class="donation-stat">

                    <strong>1 Donation</strong>

                    <span>Can Help Save Lives</span>

                </div>

            </div>

        </div>

    </div>

</body>

</html>