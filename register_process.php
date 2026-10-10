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
    $_SESSION['register_errors'] = ['general' => 'Invalid request.'];
    header("Location: register.php");
    exit;
}

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';

$_SESSION['register_old'] = [
    'email' => $email
];

$errors = [];

if ($email === '') {
    $errors['email'] = 'Email address is required.';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Please enter a valid email address.';
} elseif (strlen($email) > 255) {
    $errors['email'] = 'Email address is too long.';
}

if ($password === '') {
    $errors['password'] = 'Password is required.';
} elseif (strlen($password) < 8) {
    $errors['password'] = 'Password must be at least 8 characters.';
} elseif (!preg_match('/[A-Z]/', $password)) {
    $errors['password'] = 'Password needs at least one uppercase letter.';
} //elseif (!preg_match('/[a-z]/', $password)) {
//     $errors['password'] = 'Password needs at least one lowercase letter.';
//}
elseif (!preg_match('/[0-9]/', $password)) {
    $errors['password'] = 'Password needs at least one number.';
}

if ($confirmPassword === '') {
    $errors['confirm_password'] = 'Please confirm your password.';
} elseif ($password !== $confirmPassword) {
    $errors['confirm_password'] = 'Passwords do not match.';
}

if (!empty($errors)) {
    $_SESSION['register_errors'] = $errors;
    header("Location: register.php");
    exit;
}

try {
    $pdo = (new Database())->getConnection();

    $stmt = $pdo->prepare("SELECT id FROM uinfo WHERE email = :email LIMIT 1");
    $stmt->execute([':email' => $email]);

    if ($stmt->fetch()) {
        $_SESSION['register_errors'] = ['email' => 'This email address is already registered.'];
        header("Location: register.php");
        exit;
    }

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare(
        "INSERT INTO uinfo (fname, lname, email, btype, phone, password, role)
         VALUES (:fname, :lname, :email, :btype, :phone, :password, 'user')"
    );

    $stmt->execute([
        ':fname' => '',
        ':lname' => '',
        ':email' => $email,
        ':btype' => null,
        ':phone' => '',
        ':password' => $hashedPassword
    ]);

    unset($_SESSION['register_old']);
    unset($_SESSION['register_errors']);

    header("Location: login.php?registered=1");
    exit;
} catch (PDOException $e) {
    error_log($e->getMessage());

    $_SESSION['register_errors'] = [
        'general' => 'Something went wrong while creating your account.'
    ];

    header("Location: register.php");
    exit;
}
