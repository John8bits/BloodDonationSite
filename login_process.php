<?php

    session_start();

    require_once __DIR__ . "/config/database.php";

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header("Location: login.php");
        exit;
    }

    if (
        !isset($_POST['csrf_token']) ||
        !isset($_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {

        $_SESSION['login_error'] = "Invalid login.";
        
        header("Location: login.php");
        exit;
    }

    $login = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($login === '' || $password === '') {

        $_SESSION['login_error'] = "Please enter your username/email and password.";

        header("Location: login.php");
        exit;
    }

    $pdo = (new Database())->getConnection();

    $stmt = $pdo->prepare("
        SELECT id, username, email, password
        FROM users
        WHERE username = :login
        OR email = :login
        LIMIT 1
    ");

    $stmt->execute([
        ':login' => $login
    ]);

    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {

        $_SESSION['login_error'] =
            "Invalid username/email or password.";

        header("Location: login.php");
        exit;
    }

    session_regenerate_id(true);

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['email'] = $user['email'];

    header("Location: dashboard.php");
    exit;

?>