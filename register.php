<?php

    session_start();

    if (isset($_SESSION['user_id'])) {
        header("Location: dashboard/dashboard.php");
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
                Join our community and become part of something
                that can save lives.
            </p>

            <?php if (!empty($error)): ?>

                <div class="error-message">
                    <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                </div>

            <?php endif; ?>

            <div id="registerMessage" class="form-message" aria-live="polite"></div>

            <?php if (isset($errors['general'])): ?>

                <div class="error-message">
                    <?= htmlspecialchars($errors['general'], ENT_QUOTES, 'UTF-8') ?>
                </div>

            <?php endif; ?>

            <form action="register_process.php" method="POST" id="registerForm" novalidate>

                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

                <div class="form-row">

                    <div class="form-group">

                        <label for="first_name"> First name </label>

                        <div class="input-wrapper">

                            <img src="user.png" alt="User icon">
                            <input type="text" id="first_name" name="first_name" maxlength="50" autocomplete="given-name" placeholder="John"
                            value="<?= htmlspecialchars($old['first_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>">

                        </div>

                        <?php if (isset($errors['first_name'])): ?>

                            <small class="field-error">
                                <?= htmlspecialchars($errors['first_name'], ENT_QUOTES, 'UTF-8') ?>
                            </small>

                        <?php endif; ?>

                    </div>

                    <div class="form-group">

                        <label for="last_name">Last name</label>

                        <div class="input-wrapper">

                            <img src="user.png" alt="User icon">
                            <input type="text" id="last_name" name="last_name" maxlength="50" autocomplete="family-name" placeholder="Bits"
                            value="<?= htmlspecialchars($old['last_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>">

                        </div>

                        <?php if (isset($errors['last_name'])): ?>

                            <small class="field-error">
                                <?= htmlspecialchars($errors['last_name'], ENT_QUOTES, 'UTF-8') ?>
                            </small>

                        <?php endif; ?>

                    </div>

                </div>

                <div class="form-group">

                    <label for="email">Email address</label>

                    <div class="input-wrapper">

                        <img src="mail.png" alt="Email icon">
                        <input type="email" id="email" name="email" maxlength="255" autocomplete="email" placeholder="jbits@example.com"
                        value="<?= htmlspecialchars($old['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    
                    </div>

                    <?php if (isset($errors['email'])): ?>

                        <small class="field-error">
                            <?= htmlspecialchars($errors['email'], ENT_QUOTES, 'UTF-8') ?>
                        </small>

                    <?php endif; ?>

                </div>

                <div class="form-row">

                    <div class="form-group">

                        <label for="blood_type">Blood type</label>

                        <div class="input-wrapper">

                            <img src="blood-drop.png" alt="Blood type icon">

                            <select id="blood_type" name="blood_type">

                                <option value="">Select blood type</option>
                                <option value="A+" <?= (($old['blood_type'] ?? '') === 'A+') ? 'selected' : '' ?>> A+</option>
                                <option value="A-" <?= (($old['blood_type'] ?? '') === 'A-') ? 'selected' : '' ?>> A-</option>
                                <option value="B+" <?= (($old['blood_type'] ?? '') === 'B+') ? 'selected' : '' ?>>B+</option>
                                <option value="B-" <?= (($old['blood_type'] ?? '') === 'B-') ? 'selected' : '' ?>>B-</option>
                                <option value="AB+" <?= (($old['blood_type'] ?? '') === 'AB+') ? 'selected' : '' ?>>AB+</option>
                                <option value="AB-" <?= (($old['blood_type'] ?? '') === 'AB-') ? 'selected' : '' ?>>AB-</option>
                                <option value="O+" <?= (($old['blood_type'] ?? '') === 'O+') ? 'selected' : '' ?>>O+</option>
                                <option value="O-" <?= (($old['blood_type'] ?? '') === 'O-') ? 'selected' : '' ?>>O-</option>
                            </select>
                        </div>

                        <?php if (isset($errors['blood_type'])): ?>

                            <small class="field-error">
                                <?= htmlspecialchars($errors['blood_type'], ENT_QUOTES, 'UTF-8') ?>
                            </small>

                        <?php endif; ?>

                    </div>


                    <div class="form-group">

                        <label for="phone">Phone number</label>

                        <div class="input-wrapper">

                            <img src="telephone.png" alt="Phone icon">
                            <input type="tel" id="phone" name="phone" maxlength="20" autocomplete="tel" placeholder="09XXXXXXXXX"
                            value="<?= htmlspecialchars($old['phone'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        
                        </div>

                        <?php if (isset($errors['phone'])): ?>

                            <small class="field-error">
                                <?= htmlspecialchars($errors['phone'], ENT_QUOTES, 'UTF-8') ?>
                            </small>

                        <?php endif; ?>

                    </div>

                </div>

                <div class="form-group">

                    <label for="password"> Password </label>

                    <div class="input-wrapper">

                        <img src="padlock.png" alt="Password icon">
                        <input type="password" id="password" name="password" autocomplete="new-password"placeholder="Create a password">
                    </div>

                    <?php if (isset($errors['password'])): ?>

                        <small class="field-error">
                            <?= htmlspecialchars($errors['password'], ENT_QUOTES, 'UTF-8') ?>
                        </small>

                    <?php endif; ?>

                    <!-- <small id="passwordHint" class="password-hint">
                        Password must be at least 8 characters,
                        including uppercase, lowercase, and a number.
                    </small>

                    <small id="passwordError" class="field-error"></small> -->

                </div>

                <div class="form-group">

                    <label for="confirm_password"> Confirm password</label>

                    <div class="input-wrapper">

                        <img src="padlock.png" alt="Password icon">

                        <input type="password" id="confirm_password" name="confirm_password" autocomplete="new-password"
                        placeholder="Confirm your password">
                    
                    </div>

                    <?php if (isset($errors['confirm_password'])): ?>

                        <small class="field-error">
                            <?= htmlspecialchars($errors['confirm_password'], ENT_QUOTES, 'UTF-8') ?>
                        </small>

                    <?php endif; ?>

                </div>

                <label class="terms">

                    <input type="checkbox" id="terms" name="terms" <?= !empty($old['terms']) ? 'checked' : '' ?>>
                    <span> I agree to the <a href="#">Terms &amp; Conditions</a> and <a href="#">Privacy Policy</a>. </span>

                </label>

                <?php if (isset($errors['terms'])): ?>

                    <small class="field-error">
                        <?= htmlspecialchars($errors['terms'], ENT_QUOTES, 'UTF-8') ?>
                    </small>

                <?php endif; ?>

                <button type="submit" class="btn" id="registerButton"> Create Account </button>

            </form>

            <p class="switch">Already have an account?<a href="login.php"> Login </a> </p>

        </div>

        <div class="auth-image register-image">

            <img src="side_login.png" alt="Join the blood donor community">

            <div class="image-overlay"></div>

            <div class="image-content">

                <h2>Join the<br> Life-Saving Community.</h2>
                <p>
                    Create your account today and become part
                    of a community dedicated to helping people
                    in need of blood.
                </p>

            </div>

        </div>

    </div>

    <script src="validation/register.js"></script>

</body>

</html>