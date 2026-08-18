<?php

session_start();

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = $_SESSION['register_error'] ?? "";
unset($_SESSION['register_error']);

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Register | BloodLife</title>

    <link rel="stylesheet" href="assets/style.css">
</head>

<body>

    <div class="auth-container register-container">

        <!-- LEFT SIDE - REGISTER FORM -->
        <div class="auth-card">

            <div class="brand">
                <div class="blood-icon">♥</div>
                <span>BloodLife</span>
            </div>

            <h1>Create Account</h1>

            <p class="subtitle">
                Join our community and become part of something that can save lives.
            </p>

            <?php if (!empty($error)): ?>

                <div class="error-message">
                    <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                </div>

            <?php endif; ?>


            <form action="register_process.php" method="POST">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>"
                >


                <!-- USERNAME -->

                <div class="form-group">

                    <label for="username">
                        Username
                    </label>

                    <input
                        type="text"
                        id="username"
                        name="username"
                        required
                        autocomplete="username"
                        placeholder="Choose a username"
                    >

                </div>


                <!-- EMAIL -->

                <div class="form-group">

                    <label for="email">
                        Email Address
                    </label>

                    <input
                        type="email"
                        id="email"
                        name="email"
                        required
                        autocomplete="email"
                        placeholder="Enter your email"
                    >

                </div>


                <!-- PASSWORD -->

                <div class="form-group">

                    <label for="password">
                        Password
                    </label>

                    <input
                        type="password"
                        id="password"
                        name="password"
                        required
                        autocomplete="new-password"
                        placeholder="Create a password"
                    >

                </div>


                <!-- CONFIRM PASSWORD -->

                <div class="form-group">

                    <label for="confirm_password">
                        Confirm Password
                    </label>

                    <input
                        type="password"
                        id="confirm_password"
                        name="confirm_password"
                        required
                        autocomplete="new-password"
                        placeholder="Confirm your password"
                    >

                </div>


                <button type="submit" class="btn">
                    Create Account
                </button>

            </form>


            <p class="switch">
                Already have an account?
                <a href="login.php">Login</a>
            </p>

        </div>


        <!-- RIGHT SIDE - REGISTER IMAGE -->

        <div class="auth-image register-image">

            <img
                src="assets/images/blood-donor-register.jpg"
                alt="Join the blood donor community"
            >

            <div class="image-overlay"></div>


            <div class="image-content">

                <div class="blood-drop">
                    ♥
                </div>

                <h2>
                    Join the<br>
                    Life-Saving Community.
                </h2>

                <p>
                    Create your account today and become part of a community
                    dedicated to helping people in need of blood.
                </p>


                <div class="register-benefits">

                    <div class="benefit">
                        <span>✓</span>
                        <p>Be part of a life-saving community</p>
                    </div>

                    <div class="benefit">
                        <span>✓</span>
                        <p>Stay informed about blood donation events</p>
                    </div>

                    <div class="benefit">
                        <span>✓</span>
                        <p>Help make a difference in someone's life</p>
                    </div>

                </div>

            </div>

        </div>

    </div>

</body>

</html>