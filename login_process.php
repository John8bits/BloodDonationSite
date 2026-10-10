<?php

session_start();

require_once __DIR__ . "/config/database.php";

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    echo json_encode([
        'success' => false,
        'message' => 'Invalid request.'
    ]);

    exit;
}

// CSRF validation
if (
    !isset($_POST['csrf_token']) ||
    !isset($_SESSION['csrf_token']) ||
    !hash_equals(
        $_SESSION['csrf_token'],
        $_POST['csrf_token']
    )
) {

    echo json_encode([
        'success' => false,
        'message' => 'Invalid login request.'
    ]);

    exit;
}


$email = trim($_POST['login'] ?? '');
$password = $_POST['password'] ?? '';

$errors = [];

if($email==='' && $password===''){

    echo json_encode([
        'success' => false,
        'message' => 'Email and password are required.'
    ]);

    exit;

}


if ($email === '') {

    $errors['email'] = "Email address is required.";

} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

    $errors['email'] = "Please enter a valid email address.";

}

if ($password === '') {

    $errors['password'] = "Password is required.";

}

if (!empty($errors)) {

    echo json_encode([
        'success' => false,
        'validation' => true,
        'errors' => $errors
    ]);

    exit;
}


try {

    $pdo = (new Database())->getConnection();


    $stmt = $pdo->prepare("
        SELECT
            id,
            fname,
            lname,
            email,
            btype,
            phone,
            password,
            role
        FROM uinfo
        WHERE email = :email
        LIMIT 1
    ");


    $stmt->execute([
        ':email' => $email
    ]);


    $user = $stmt->fetch();


    // Check email/password
    if (
        !$user ||
        !password_verify(
            $password,
            $user['password']
        )
    ) {

        echo json_encode([
            'success' => false,
            'message' => 'Invalid email or password.'
        ]);

        exit;
    }


    // Login successful
    session_regenerate_id(true);

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['first_name'] = $user['fname'];
    $_SESSION['last_name'] = $user['lname'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['blood_type'] = $user['btype'];
    $_SESSION['phone'] = $user['phone'];
    $_SESSION['role'] = $user['role'] ?? 'user';


    unset($_SESSION['login_errors']);
    unset($_SESSION['login_old']);

    // Send admins to the admin panel, everyone else to their dashboard.
    $redirect = $_SESSION['role'] === 'admin'
        ? 'admin/dashboard.php?login=success'
        : 'dashboard/dashboard.php?login=success';

    echo json_encode([
        'success' => true,
        'message' => 'Submitting login...',
        'redirect' => $redirect
    ]);

    exit;


} catch (PDOException $e) {

    error_log($e->getMessage());

    echo json_encode([
        'success' => false,
        'message' => 'Unable to login at this time.'
    ]);

    exit;
}

?>