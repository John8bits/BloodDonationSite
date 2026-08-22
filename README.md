login.php

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Login | LifeBlood</title>

    <link rel="stylesheet" href="style.css">
</head>

<body>

    <div class="page">

        <!-- =========================
             LEFT SIDE
        ========================== -->

        <section class="visual-side">

            <div class="visual-content">

                <!-- Logo -->
                <a href="login.html" class="logo">
                    <div class="logo-icon">
                        <span>♥</span>
                    </div>

                    <span>LifeBlood</span>
                </a>


                <!-- Main Text -->
               <!-- <div class="hero-text">

                    <h1>
                        Donate blood,
                        <span>save lives.</span>
                    </h1>

                    <p>
                        Your small act of kindness can make
                        a big difference.
                    </p>

                    <div class="hero-line"></div>

                </div> -->

                <!-- Donation Image -->
                <div class="donation-image">

                    <img
                        src="log.jpg"
                        alt="Blood donation illustration"
                    >

                </div>

            </div>

        </section>


        <!-- =========================
             RIGHT SIDE
        ========================== -->

        <section class="form-side">

            <div class="form-card">

                <!-- Blood Icon -->
                <div class="form-icon">
                    ♥
                </div>


                <!-- Header -->
                <div class="form-header">

                    <h2>
                        Welcome back!
                    </h2>

                    <p>
                        Sign in to continue your journey.
                    </p>

                </div>


                <!-- Login Form -->
                <form
                    id="loginForm"
                    action="#"
                    method="POST"
                    novalidate
                >

                    <!-- Email -->
                    <div class="form-group">

                        <label for="email">
                            Email address
                        </label>

                        <div class="input-box">

                            <span class="input-icon">
                                ✉
                            </span>

                            <input
                                type="email"
                                id="email"
                                name="email"
                                placeholder="you@example.com"
                                autocomplete="email"
                                required
                            >

                        </div>

                        <small
                            class="error-message"
                            id="emailError"
                        ></small>

                    </div>


                    <!-- Password -->
                    <div class="form-group">

                        <div class="label-row">

                            <label for="password">
                                Password
                            </label>

                            <a
                                href="#"
                                class="forgot-password"
                            >
                                Forgot password?
                            </a>

                        </div>


                        <div class="input-box">

                            <span class="input-icon">
                                🔒
                            </span>

                            <input
                                type="password"
                                id="password"
                                name="password"
                                placeholder="Enter your password"
                                autocomplete="current-password"
                                required
                            >

                            <button
                                type="button"
                                class="show-password"
                                data-target="password"
                                aria-label="Show password"
                            >
                                👁
                            </button>

                        </div>

                        <small
                            class="error-message"
                            id="passwordError"
                        ></small>

                    </div>


                    <!-- Remember -->
                    <label class="remember">

                        <input
                            type="checkbox"
                            name="remember"
                        >

                        <span>
                            Remember me
                        </span>

                    </label>


                    <!-- Login -->
                    <button
                        type="submit"
                        class="submit-button"
                    >
                        Sign in
                    </button>

                </form>


                <!-- Divider -->
                <div class="divider">

                    <span></span>

                    <p>or</p>

                    <span></span>

                </div>


                <!-- Google -->
                <button
                    type="button"
                    class="google-button"
                >
                    <span class="google-icon">G</span>

                    <span>
                        Continue with Google
                    </span>
                </button>


                <!-- Register -->
                <div class="account-text">

                    <span>
                        Don't have an account?
                    </span>

                    <a href="register.html">
                        Become a donor
                    </a>

                </div>


                <!-- Security -->
                <div class="security">

                    <span>🔒</span>

                    <span>
                        Your information is safe with us.
                    </span>

                </div>

            </div>

        </section>

    </div>


    <script src="script.js"></script>

</body>

</html>

reg.php:
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Register | LifeBlood</title>

    <link rel="stylesheet" href="style.css">

</head>

<body>

    <div class="page">

        <!-- =========================
             LEFT SIDE
        ========================== -->

        <section class="visual-side">

            <div class="visual-content">

                <!-- Logo -->
                <a href="login.html" class="logo">

                    <div class="logo-icon">
                        <span>♥</span>
                    </div>

                    <span>LifeBlood</span>

                </a>


                <!-- Main Text -->
                <div class="hero-text">

                    <h1>
                        Donate blood,
                        <span>save lives.</span>
                    </h1>

                    <p>
                        Your small act of kindness can make
                        a big difference.
                    </p>

                    <div class="hero-line"></div>

                </div>


                <!-- Image -->
                <div class="donation-image">

                    <img
                        src="reg.jpg"
                        alt="Blood donation illustration"
                    >

                </div>

            </div>

        </section>


        <!-- =========================
             RIGHT SIDE
        ========================== -->

        <section class="form-side">

            <div class="form-card register-card">

                <!-- Icon -->
                <div class="form-icon">
                    ♥
                </div>


                <!-- Header -->
                <div class="form-header">

                    <h2>
                        Create your account
                    </h2>

                    <p>
                        Join our community of life savers.
                    </p>

                </div>


                <!-- Register Form -->
                <form
                    id="registerForm"
                    action="#"
                    method="POST"
                    novalidate
                >

                    <!-- First + Last Name -->
                    <div class="two-column">

                        <div class="form-group">

                            <label for="firstName">
                                First name
                            </label>

                            <div class="input-box">

                                <span class="input-icon">
                                    👤
                                </span>

                                <input
                                    type="text"
                                    id="firstName"
                                    name="first_name"
                                    placeholder="First name"
                                    autocomplete="given-name"
                                    required
                                >

                            </div>

                            <small
                                class="error-message"
                                id="firstNameError"
                            ></small>

                        </div>


                        <div class="form-group">

                            <label for="lastName">
                                Last name
                            </label>

                            <div class="input-box">

                                <span class="input-icon">
                                    👤
                                </span>

                                <input
                                    type="text"
                                    id="lastName"
                                    name="last_name"
                                    placeholder="Last name"
                                    autocomplete="family-name"
                                    required
                                >

                            </div>

                            <small
                                class="error-message"
                                id="lastNameError"
                            ></small>

                        </div>

                    </div>


                    <!-- Email -->
                    <div class="form-group">

                        <label for="registerEmail">
                            Email address
                        </label>

                        <div class="input-box">

                            <span class="input-icon">
                                ✉
                            </span>

                            <input
                                type="email"
                                id="registerEmail"
                                name="email"
                                placeholder="you@example.com"
                                autocomplete="email"
                                required
                            >

                        </div>

                        <small
                            class="error-message"
                            id="registerEmailError"
                        ></small>

                    </div>


                    <!-- Blood Type + Phone -->
                    <div class="two-column">

                        <div class="form-group">

                            <label for="bloodType">
                                Blood type
                            </label>

                            <div class="input-box">

                                <span class="input-icon">
                                    ♥
                                </span>

                                <select
                                    id="bloodType"
                                    name="blood_type"
                                    required
                                >

                                    <option value="">
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

                            <small
                                class="error-message"
                                id="bloodTypeError"
                            ></small>

                        </div>


                        <div class="form-group">

                            <label for="phone">
                                Phone number
                            </label>

                            <div class="input-box">

                                <span class="input-icon">
                                    ☎
                                </span>

                                <input
                                    type="tel"
                                    id="phone"
                                    name="phone"
                                    placeholder="09XXXXXXXXX"
                                    autocomplete="tel"
                                >

                            </div>

                            <small
                                class="error-message"
                                id="phoneError"
                            ></small>

                        </div>

                    </div>


                    <!-- Password -->
                    <div class="form-group">

                        <label for="registerPassword">
                            Password
                        </label>

                        <div class="input-box">

                            <span class="input-icon">
                                🔒
                            </span>

                            <input
                                type="password"
                                id="registerPassword"
                                name="password"
                                placeholder="Create a password"
                                autocomplete="new-password"
                                required
                            >

                            <button
                                type="button"
                                class="show-password"
                                data-target="registerPassword"
                                aria-label="Show password"
                            >
                                👁
                            </button>

                        </div>


                        <!-- Password Strength -->
                        <div class="password-strength">

                            <div class="strength-bars">

                                <span></span>
                                <span></span>
                                <span></span>
                                <span></span>

                            </div>

                            <small id="strengthText">
                                Use at least 8 characters
                            </small>

                        </div>


                        <small
                            class="error-message"
                            id="registerPasswordError"
                        ></small>

                    </div>


                    <!-- Confirm -->
                    <div class="form-group">

                        <label for="confirmPassword">
                            Confirm password
                        </label>

                        <div class="input-box">

                            <span class="input-icon">
                                🔒
                            </span>

                            <input
                                type="password"
                                id="confirmPassword"
                                name="confirm_password"
                                placeholder="Confirm your password"
                                autocomplete="new-password"
                                required
                            >

                            <button
                                type="button"
                                class="show-password"
                                data-target="confirmPassword"
                                aria-label="Show password"
                            >
                                👁
                            </button>

                        </div>

                        <small
                            class="error-message"
                            id="confirmPasswordError"
                        ></small>

                    </div>


                    <!-- Terms -->
                    <label class="terms">

                        <input
                            type="checkbox"
                            id="terms"
                            required
                        >

                        <span>
                            I agree to the
                            <a href="#">
                                Terms & Conditions
                            </a>
                            and
                            <a href="#">
                                Privacy Policy
                            </a>.
                        </span>

                    </label>

                    <small
                        class="error-message"
                        id="termsError"
                    ></small>


                    <!-- Register -->
                    <button
                        type="submit"
                        class="submit-button"
                    >
                        Create account

                        <span>
                            ♥
                        </span>

                    </button>

                </form>


                <!-- Divider -->
                <div class="divider">

                    <span></span>

                    <p>or</p>

                    <span></span>

                </div>


                <!-- Google -->
                <button
                    type="button"
                    class="google-button"
                >

                    <span class="google-icon">
                        G
                    </span>

                    <span>
                        Sign up with Google
                    </span>

                </button>


                <!-- Login -->
                <div class="account-text">

                    <span>
                        Already have an account?
                    </span>

                    <a href="login.html">
                        Sign in
                    </a>

                </div>


                <!-- Security -->
                <div class="security">

                    <span>🔒</span>

                    <span>
                        Your information is safe with us.
                    </span>

                </div>

            </div>

        </section>

    </div>


    <script src="script.js"></script>

</body>

</html>

style.css
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');


/* =========================================
   VARIABLES
========================================= */

:root {

    --red: #e51b2a;
    --red-dark: #c91422;

    --red-light: #fff0f1;
    --pink-light: #fff7f7;

    --black: #18181b;
    --gray-dark: #52525b;
    --gray: #71717a;
    --gray-light: #a1a1aa;

    --border: #e4e4e7;

    --white: #ffffff;

    --shadow:
        0 20px 60px rgba(0, 0, 0, 0.08);
}


/* =========================================
   RESET
========================================= */

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}


html {
    min-height: 100%;
}


body {

    min-height: 100vh;

    font-family: "Inter", sans-serif;

    color: var(--black);

    background: white;
}


button,
input,
select {
    font-family: inherit;
}


/* =========================================
   MAIN PAGE
========================================= */

.page {

    min-height: 100vh;

    display: grid;

    grid-template-columns: 50% 50%;
}


/* =========================================
   LEFT SIDE
========================================= */

.visual-side {

    min-height: 100vh;

    position: relative;

    overflow: hidden;

    background:
        linear-gradient(
            135deg,
            #fffafa 0%,
            #fff2f3 55%,
            #fffafa 100%
        );
}


/*
    Soft curved background
*/

.visual-side::before {

    content: "";

    position: absolute;

    width: 650px;
    height: 900px;

    top: -250px;
    right: -250px;

    background: #ffffff;

    border-radius: 50%;

    opacity: .8;
}


.visual-side::after {

    content: "";

    position: absolute;

    width: 700px;
    height: 300px;

    bottom: -150px;
    left: -200px;

    background: #ffe4e6;

    border-radius: 50%;

    transform: rotate(-15deg);

    opacity: .7;
}


/* =========================================
   LEFT CONTENT
========================================= */

.visual-content {

    height: 100%;

    position: relative;

    z-index: 2;

    padding: 50px 8%;

    display: flex;

    flex-direction: column;
}


/* =========================================
   LOGO
========================================= */

.logo {

    display: inline-flex;

    align-items: center;

    gap: 10px;

    width: fit-content;

    color: var(--black);

    font-size: 20px;

    font-weight: 800;

    text-decoration: none;
}


.logo-icon {

    width: 38px;
    height: 38px;

    border-radius: 50%;

    display: flex;

    align-items: center;

    justify-content: center;

    background: var(--red);

    color: white;

    font-size: 17px;

    box-shadow:
        0 8px 20px rgba(229, 27, 42, .2);
}


/* =========================================
   HERO TEXT
========================================= */

.hero-text {

    margin-top: 75px;

    max-width: 430px;
}


.hero-text h1 {

    font-size:
        clamp(42px, 4vw, 62px);

    line-height: 1.03;

    letter-spacing: -2.5px;

    font-weight: 800;
}


.hero-text h1 span {

    display: block;

    color: var(--red);

    margin-top: 5px;
}


.hero-text p {

    margin-top: 25px;

    max-width: 330px;

    color: var(--gray-dark);

    font-size: 15px;

    line-height: 1.7;
}


.hero-line {

    width: 68px;

    height: 4px;

    margin-top: 30px;

    border-radius: 20px;

    background: var(--red);
}


/* =========================================
   DONATION IMAGE
========================================= */

.donation-image {

    position: absolute;

    left: 0;

    right: 0;

    bottom: -20px;

    width: 100%;

    display: flex;

    justify-content: center;

    align-items: flex-end;

    pointer-events: none;
}


.donation-image img {

    width: 100%;
    max-width:720px;
    height: auto;

    object-fit: contain;

    display: block;

    mix-blend-mode: multiply;
}


/*
   IMPORTANT:
   If your image already has a transparent
   background, you can remove mix-blend-mode.
*/


/* =========================================
   RIGHT SIDE
========================================= */

.form-side {

    min-height: 100vh;

    display: flex;

    align-items: center;

    justify-content: center;

    padding: 45px 7%;

    background: #ffffff;
}


/* =========================================
   FORM CARD
========================================= */

.form-card {

    width: 100%;

    max-width: 500px;

    padding: 42px;

    background: white;

    border: 1px solid #f0f0f0;

    border-radius: 20px;

    box-shadow: var(--shadow);
}


.register-card {

    max-width: 600px;
}


/* =========================================
   FORM ICON
========================================= */

.form-icon {

    width: 50px;
    height: 50px;

    margin: 0 auto 20px;

    border-radius: 50%;

    display: flex;

    align-items: center;

    justify-content: center;

    background: var(--red-light);

    color: var(--red);

    font-size: 21px;
}


/* =========================================
   HEADER
========================================= */

.form-header {

    text-align: center;

    margin-bottom: 32px;
}


.form-header h2 {

    font-size: 29px;

    letter-spacing: -1px;

    margin-bottom: 8px;
}


.form-header p {

    color: var(--gray);

    font-size: 13px;
}


/* =========================================
   FORM GROUP
========================================= */

.form-group {

    margin-bottom: 18px;
}


.form-group label {

    display: block;

    margin-bottom: 8px;

    font-size: 12px;

    font-weight: 600;

    color: #27272a;
}


.label-row {

    display: flex;

    align-items: center;

    justify-content: space-between;
}


.forgot-password {

    color: var(--red);

    font-size: 11px;

    font-weight: 600;

    text-decoration: none;
}


.forgot-password:hover {

    text-decoration: underline;
}


/* =========================================
   INPUT
========================================= */

.input-box {

    position: relative;

    width: 100%;
}


.input-box input,
.input-box select {

    width: 100%;

    height: 52px;

    padding: 0 42px;

    border: 1px solid var(--border);

    border-radius: 10px;

    background: white;

    color: var(--black);

    font-size: 13px;

    outline: none;

    transition: .2s;
}


.input-box input::placeholder {

    color: var(--gray-light);
}


.input-box input:hover,
.input-box select:hover {

    border-color: #d4d4d8;
}


.input-box input:focus,
.input-box select:focus {

    border-color: var(--red);

    box-shadow:
        0 0 0 3px rgba(229, 27, 42, .08);
}


.input-icon {

    position: absolute;

    left: 14px;

    top: 50%;

    transform: translateY(-50%);

    font-size: 14px;

    opacity: .55;

    pointer-events: none;

    z-index: 2;
}


/* =========================================
   PASSWORD TOGGLE
========================================= */

.show-password {

    position: absolute;

    right: 13px;

    top: 50%;

    transform: translateY(-50%);

    border: none;

    background: transparent;

    cursor: pointer;

    font-size: 13px;

    opacity: .5;
}


.show-password:hover {

    opacity: 1;
}


/* =========================================
   TWO COLUMNS
========================================= */

.two-column {

    display: grid;

    grid-template-columns: 1fr 1fr;

    gap: 14px;
}


/* =========================================
   REMEMBER
========================================= */

.remember {

    display: flex;

    align-items: center;

    gap: 8px;

    margin: 3px 0 22px;

    color: var(--gray-dark);

    font-size: 11px;

    cursor: pointer;
}


.remember input {

    width: 15px;
    height: 15px;

    accent-color: var(--red);
}


/* =========================================
   TERMS
========================================= */

.terms {

    display: flex;

    align-items: flex-start;

    gap: 8px;

    color: var(--gray);

    font-size: 10px;

    line-height: 1.6;

    margin: 4px 0 20px;
}


.terms input {

    width: 15px;
    height: 15px;

    flex-shrink: 0;

    accent-color: var(--red);
}


.terms a {

    color: var(--red);

    font-weight: 600;

    text-decoration: none;
}


/* =========================================
   SUBMIT BUTTON
========================================= */

.submit-button {

    width: 100%;

    height: 52px;

    border: none;

    border-radius: 9px;

    background: var(--red);

    color: white;

    font-size: 13px;

    font-weight: 700;

    cursor: pointer;

    display: flex;

    align-items: center;

    justify-content: center;

    gap: 8px;

    transition: .2s;
}


.submit-button:hover {

    background: var(--red-dark);

    box-shadow:
        0 8px 20px rgba(229, 27, 42, .2);

    transform: translateY(-1px);
}


.submit-button:active {

    transform: translateY(0);
}


/* =========================================
   DIVIDER
========================================= */

.divider {

    display: flex;

    align-items: center;

    gap: 12px;

    margin: 25px 0;
}


.divider span {

    height: 1px;

    background: #e5e5e5;

    flex: 1;
}


.divider p {

    color: var(--gray-light);

    font-size: 11px;
}


/* =========================================
   GOOGLE BUTTON
========================================= */

.google-button {

    width: 100%;

    height: 48px;

    border: 1px solid var(--border);

    border-radius: 9px;

    background: white;

    display: flex;

    align-items: center;

    justify-content: center;

    gap: 10px;

    color: #27272a;

    font-size: 12px;

    font-weight: 500;

    cursor: pointer;

    transition: .2s;
}


.google-button:hover {

    background: #fafafa;

    border-color: #d4d4d8;
}


.google-icon {

    font-weight: 800;

    font-size: 16px;

    color: #4285f4;
}


/* =========================================
   ACCOUNT TEXT
========================================= */

.account-text {

    text-align: center;

    margin-top: 25px;

    font-size: 11px;

    color: var(--gray);
}


.account-text a {

    color: var(--red);

    font-weight: 600;

    text-decoration: none;

    margin-left: 3px;
}


.account-text a:hover {

    text-decoration: underline;
}


/* =========================================
   SECURITY
========================================= */

.security {

    display: flex;

    align-items: center;

    justify-content: center;

    gap: 6px;

    margin-top: 28px;

    color: var(--gray-light);

    font-size: 10px;
}


/* =========================================
   PASSWORD STRENGTH
========================================= */

.password-strength {

    margin-top: 7px;
}


.strength-bars {

    display: flex;

    gap: 5px;
}


.strength-bars span {

    height: 3px;

    flex: 1;

    border-radius: 10px;

    background: #e4e4e7;

    transition: .2s;
}


.password-strength small {

    display: block;

    margin-top: 5px;

    color: var(--gray-light);

    font-size: 9px;
}


/* =========================================
   ERRORS
========================================= */

.error-message {

    display: block;

    color: var(--red);

    font-size: 10px;

    margin-top: 5px;
}


.input-error {

    border-color: var(--red) !important;

    background: #fff8f8 !important;
}


/* =========================================
   RESPONSIVE
========================================= */

@media (max-width: 1100px) {

    .page {

        grid-template-columns: 45% 55%;
    }


    .hero-text h1 {

        font-size: 44px;
    }


    .form-card {

        padding: 32px;
    }
}


@media (max-width: 850px) {

    .page {

        display: block;
    }


    .visual-side {

        min-height: 300px;

        height: 330px;
    }


    .visual-content {

        padding: 30px 25px;
    }


    .hero-text {

        margin-top: 35px;

        max-width: 300px;
    }


    .hero-text h1 {

        font-size: 35px;

        letter-spacing: -1.5px;
    }


    .hero-text p {

        margin-top: 12px;

        font-size: 12px;
    }


    .hero-line {

        margin-top: 15px;
    }


    .donation-image {

        width: 60%;

        left: auto;

        right: -10px;

        bottom: -40px;
    }


    .form-side {

        min-height: auto;

        padding: 40px 20px;
    }
}


@media (max-width: 600px) {

    .visual-side {

        height: 270px;

        min-height: 270px;
    }


    .visual-content {

        padding: 22px 20px;
    }


    .logo {

        font-size: 17px;
    }


    .logo-icon {

        width: 34px;
        height: 34px;
    }


    .hero-text {

        margin-top: 25px;
    }


    .hero-text h1 {

        font-size: 29px;
    }


    .hero-text p {

        font-size: 11px;

        max-width: 220px;
    }


    .donation-image {

        width: 58%;

        right: -20px;

        bottom: -25px;
    }


    .form-side {

        padding: 25px 15px;
    }


    .form-card {

        padding: 27px 20px;

        border-radius: 15px;

        box-shadow:
            0 10px 35px rgba(0, 0, 0, .06);
    }


    .form-header h2 {

        font-size: 25px;
    }


    .two-column {

        grid-template-columns: 1fr;

        gap: 0;
    }
}


@media (max-width: 380px) {

    .visual-side {

        height: 240px;

        min-height: 240px;
    }


    .hero-text h1 {

        font-size: 25px;
    }


    .donation-image {

        width: 55%;
    }


    .form-card {

        padding: 24px 17px;
    }
}

script.js:
document.addEventListener("DOMContentLoaded", () => {

    /* =====================================
       PASSWORD SHOW / HIDE
    ===================================== */

    const passwordButtons =
        document.querySelectorAll(".show-password");


    passwordButtons.forEach(button => {

        button.addEventListener("click", () => {

            const targetId =
                button.getAttribute("data-target");

            const input =
                document.getElementById(targetId);

            if (!input) return;


            if (input.type === "password") {

                input.type = "text";

                button.textContent = "🙈";

            } else {

                input.type = "password";

                button.textContent = "👁";

            }

        });

    });


    /* =====================================
       LOGIN
    ===================================== */

    const loginForm =
        document.getElementById("loginForm");


    if (loginForm) {

        loginForm.addEventListener(
            "submit",
            function (event) {

                event.preventDefault();

                clearErrors();

                let valid = true;


                const email =
                    document.getElementById("email");

                const password =
                    document.getElementById("password");


                /* Email */

                if (!email.value.trim()) {

                    showError(
                        email,
                        "emailError",
                        "Please enter your email address."
                    );

                    valid = false;

                } else if (
                    !isValidEmail(email.value.trim())
                ) {

                    showError(
                        email,
                        "emailError",
                        "Please enter a valid email address."
                    );

                    valid = false;
                }


                /* Password */

                if (!password.value) {

                    showError(
                        password,
                        "passwordError",
                        "Please enter your password."
                    );

                    valid = false;
                }


                if (valid) {

                    /*
                     * Connect to PHP here.
                     *
                     * Example:
                     *
                     * loginForm.action =
                     * "login_process.php";
                     *
                     * loginForm.method = "POST";
                     *
                     * loginForm.submit();
                     */

                    alert("Login validation successful.");

                }

            }
        );
    }


    /* =====================================
       REGISTER
    ===================================== */

    const registerForm =
        document.getElementById("registerForm");


    const registerPassword =
        document.getElementById("registerPassword");


    if (registerPassword) {

        registerPassword.addEventListener(
            "input",
            () => {

                updatePasswordStrength(
                    registerPassword.value
                );

            }
        );
    }


    if (registerForm) {

        registerForm.addEventListener(
            "submit",
            function (event) {

                event.preventDefault();

                clearErrors();

                let valid = true;


                const firstName =
                    document.getElementById("firstName");

                const lastName =
                    document.getElementById("lastName");

                const email =
                    document.getElementById(
                        "registerEmail"
                    );

                const bloodType =
                    document.getElementById("bloodType");

                const phone =
                    document.getElementById("phone");

                const password =
                    document.getElementById(
                        "registerPassword"
                    );

                const confirmPassword =
                    document.getElementById(
                        "confirmPassword"
                    );

                const terms =
                    document.getElementById("terms");


                /* First name */

                if (!firstName.value.trim()) {

                    showError(
                        firstName,
                        "firstNameError",
                        "Please enter your first name."
                    );

                    valid = false;
                }


                /* Last name */

                if (!lastName.value.trim()) {

                    showError(
                        lastName,
                        "lastNameError",
                        "Please enter your last name."
                    );

                    valid = false;
                }


                /* Email */

                if (!email.value.trim()) {

                    showError(
                        email,
                        "registerEmailError",
                        "Please enter your email address."
                    );

                    valid = false;

                } else if (
                    !isValidEmail(email.value.trim())
                ) {

                    showError(
                        email,
                        "registerEmailError",
                        "Please enter a valid email address."
                    );

                    valid = false;
                }


                /* Blood type */

                if (!bloodType.value) {

                    showError(
                        bloodType,
                        "bloodTypeError",
                        "Please select your blood type."
                    );

                    valid = false;
                }


                /* Phone */

                if (
                    phone.value.trim() &&
                    !isValidPhone(phone.value.trim())
                ) {

                    showError(
                        phone,
                        "phoneError",
                        "Please enter a valid phone number."
                    );

                    valid = false;
                }


                /* Password */

                if (password.value.length < 8) {

                    showError(
                        password,
                        "registerPasswordError",
                        "Password must be at least 8 characters."
                    );

                    valid = false;
                }


                /* Confirm */

                if (!confirmPassword.value) {

                    showError(
                        confirmPassword,
                        "confirmPasswordError",
                        "Please confirm your password."
                    );

                    valid = false;

                } else if (
                    password.value !== confirmPassword.value
                ) {

                    showError(
                        confirmPassword,
                        "confirmPasswordError",
                        "Passwords do not match."
                    );

                    valid = false;
                }


                /* Terms */

                if (!terms.checked) {

                    document.getElementById(
                        "termsError"
                    ).textContent =
                        "Please accept the terms and privacy policy.";

                    valid = false;
                }


                if (valid) {

                    /*
                     * Connect to PHP here.
                     *
                     * Example:
                     *
                     * registerForm.action =
                     * "register_process.php";
                     *
                     * registerForm.method = "POST";
                     *
                     * registerForm.submit();
                     */

                    alert(
                        "Registration validation successful."
                    );

                }

            }
        );
    }


    /* =====================================
       FUNCTIONS
    ===================================== */

    function isValidEmail(email) {

        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(
            email
        );
    }


    function isValidPhone(phone) {

        return /^(09|\+639)\d{9}$/.test(
            phone
        );
    }


    function showError(
        input,
        errorId,
        message
    ) {

        input.classList.add(
            "input-error"
        );


        const error =
            document.getElementById(errorId);


        if (error) {

            error.textContent = message;
        }
    }


    function clearErrors() {

        document
            .querySelectorAll(".error-message")
            .forEach(error => {

                error.textContent = "";

            });


        document
            .querySelectorAll(
                "input, select"
            )
            .forEach(input => {

                input.classList.remove(
                    "input-error"
                );

            });
    }


    /* =====================================
       PASSWORD STRENGTH
    ===================================== */

    function updatePasswordStrength(
        password
    ) {

        const bars =
            document.querySelectorAll(
                ".strength-bars span"
            );


        const text =
            document.getElementById(
                "strengthText"
            );


        if (!bars.length) return;


        let strength = 0;


        if (password.length >= 8)
            strength++;


        if (/[A-Z]/.test(password))
            strength++;


        if (/[0-9]/.test(password))
            strength++;


        if (/[^A-Za-z0-9]/.test(password))
            strength++;


        bars.forEach(
            (bar, index) => {

                bar.style.background =
                    index < strength
                        ? "#e51b2a"
                        : "#e4e4e7";

            }
        );


        if (!password) {

            text.textContent =
                "Use at least 8 characters.";

        } else if (strength === 1) {

            text.textContent =
                "Weak";

        } else if (strength === 2) {

            text.textContent =
                "Fair";

        } else if (strength === 3) {

            text.textContent =
                "Good";

        } else {

            text.textContent =
                "Strong";

        }

    }

});
