<?php

session_start();

require_once __DIR__ . "/config/database.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: register.php");
    exit;
}

if (
    !isset($_POST['csrf_token']) ||
    !isset($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
) {
    $_SESSION['register_error'] = "Invalid request.";
    header("Location: register.php");
    exit;
}

$username = trim($_POST['username'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';


if (
    $username === '' ||
    $email === '' ||
    $password === '' ||
    $confirm_password === ''
) {
    $_SESSION['register_error'] = "Please fill in all fields.";
    header("Location: register.php");
    exit;
}


if (!preg_match('/^[A-Za-z0-9_]{3,30}$/', $username)) {
    $_SESSION['register_error'] =
        "Username must be 3-30 characters and contain only letters, numbers, and underscores.";

    header("Location: register.php");
    exit;
}


if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['register_error'] = "Please enter a valid email address.";
    header("Location: register.php");
    exit;
}


if (strlen($password) < 8) {
    $_SESSION['register_error'] =
        "Password must be at least 8 characters long.";

    header("Location: register.php");
    exit;
}

if (!preg_match('/[A-Z]/', $password)) {
    $_SESSION['register_error'] =
        "Password must contain at least one uppercase letter.";

    header("Location: register.php");
    exit;
}

if (!preg_match('/[a-z]/', $password)) {
    $_SESSION['register_error'] =
        "Password must contain at least one lowercase letter.";

    header("Location: register.php");
    exit;
}

if (!preg_match('/[0-9]/', $password)) {
    $_SESSION['register_error'] =
        "Password must contain at least one number.";

    header("Location: register.php");
    exit;
}

if (!hash_equals($password, $confirm_password)) {
    $_SESSION['register_error'] = "Passwords do not match.";
    header("Location: register.php");
    exit;
}

try {

    $pdo = (new Database())->getConnection();

    $stmt = $pdo->prepare("
        SELECT id
        FROM users
        WHERE username = :username
           OR email = :email
        LIMIT 1
    ");

    $stmt->execute([
        ':username' => $username,
        ':email' => $email
    ]);

    $existingUser = $stmt->fetch();

    if ($existingUser) {
        $_SESSION['register_error'] =
            "Username or email is already registered.";

        header("Location: register.php");
        exit;
    }

    $hashedPassword = password_hash(
        $password,
        PASSWORD_DEFAULT
    );

    $stmt = $pdo->prepare("
        INSERT INTO users (
            username,
            email,
            password
        )
        VALUES (
            :username,
            :email,
            :password
        )
    ");

    $stmt->execute([
        ':username' => $username,
        ':email' => $email,
        ':password' => $hashedPassword
    ]);

    $_SESSION['register_success'] =
        "Registration successful. You can now log in.";

    header("Location: login.php");
    exit;


} catch (PDOException $e) {
    error_log($e->getMessage());

    $_SESSION['register_error'] =
        "Something went wrong. Please try again later.";

    header("Location: register.php");
    exit;
}

?>