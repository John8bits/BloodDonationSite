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

                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(
                    $_SESSION['csrf_token'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>">


                <div class="form-row">

                    <div class="form-group">

                        <label for="first_name">
                            First name
                        </label>

                        <div class="input-wrapper">

                            <img src="user.png" alt="">

                            <input type="text" id="first_name" name="first_name" maxlength="50"
                                autocomplete="given-name" placeholder="John" required>

                        </div>

                    </div>


                    <div class="form-group">

                        <label for="last_name">
                            Last name
                        </label>

                        <div class="input-wrapper">

                            <img src="user.png" alt="">

                            <input type="text" id="last_name" name="last_name" maxlength="50" autocomplete="family-name"
                                placeholder="Bits" required>

                        </div>

                    </div>

                </div>


                <!-- EMAIL -->

                <div class="form-group">

                    <label for="email">
                        Email address
                    </label>

                    <div class="input-wrapper">

                        <img src="mail.png" alt="">

                        <input type="email" id="email" name="email" maxlength="255" autocomplete="email"
                            placeholder="jbits@example.com" required>

                    </div>

                </div>


                <div class="form-row">

                    <div class="form-group">

                        <label for="blood_type">
                            Blood type
                        </label>

                        <div class="input-wrapper">

                            <img src="blood-drop.png" alt=btype">

                            <select id="blood_type" name="blood_type" required>
                                <option value="" disabled selected>
                                    Select blood type
                                </option>

                                <option value="A+">A+</option>
                                <option value="A-">A-</option>

                                <option value="B+">B+</option>
                                <option value="B-">B-</option>

                                <option value="AB+">AB+</option>
                                <option value="AB-">AB-</option>

                                <option value="O+">O+</option>
                                <option value="O-">O-</option>

                            </select>

                        </div>

                    </div>

                    <div class="form-group">

                        <label for="phone">
                            Phone number
                        </label>

                        <div class="input-wrapper">

                            <img src="telephone.png" alt="phone">

                            <input type="tel" id="phone" name="phone" maxlength="20" autocomplete="tel"
                                placeholder="09XXXXXXXXX" required>

                        </div>

                    </div>

                </div>

                <div class="form-group">

                    <label for="password">
                        Password
                    </label>

                    <div class="input-wrapper">

                        <img src="padlock.png" alt="">

                        <input type="password" id="password" name="password" autocomplete="new-password"
                            placeholder="Create a password" required>

                    </div>

                </div>

                <div class="form-group">

                    <label for="confirm_password">
                        Confirm password
                    </label>

                    <div class="input-wrapper">

                        <img src="padlock.png" alt="">

                        <input type="password" id="confirm_password" name="confirm_password" autocomplete="new-password"
                            placeholder="Confirm your password" required>

                    </div>

                </div>

                <label class="terms">

                    <input type="checkbox" name="terms" required>

                    <span>
                        I agree to the
                        <a href="#">Terms &amp; Conditions</a>
                        and
                        <a href="#">Privacy Policy</a>.
                    </span>

                </label>

                <button type="submit" class="btn">
                    Create Account
                </button>

            </form>


            <p class="switch">
                Already have an account?
                <a href="login.php">Login</a>
            </p>

        </div>

        <div class="auth-image register-image">

            <img src="side_login.png" alt="Join the blood donor community">

            <div class="image-overlay"></div>


            <div class="image-content">

                <!-- <div class="blood-drop">
                    ♥
                </div> -->

                <h2>
                    Join the<br>
                    Life-Saving Community.
                </h2>

                <p>
                    Create your account today and become part of a community
                    dedicated to helping people in need of blood.
                </p>


                <!-- <div class="register-benefits">

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

                </div> -->

            </div>

        </div>

    </div>

</body>

</html>