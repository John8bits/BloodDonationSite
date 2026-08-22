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

        $_SESSION['login_errors'] = ['general' => "Invalid login request."];

        header("Location: login.php");
        exit;
    }

    $email = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';

    $_SESSION['login_old'] = ['email' => $email];


    $errors = [];

    if ($email === '') {

        $errors['email'] = "Email address is required.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $errors['email'] = "Please enter a valid email address.";

    }

    if ($password === '') {

        $errors['password'] = "Password is required.";

    }

    if (!empty($errors)) {

        $_SESSION['login_errors'] = $errors;

        header("Location: login.php");
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
                password
            FROM uinfo
            WHERE email = :email
            LIMIT 1
        ");

        $stmt->execute([
            ':email' => $email
        ]);

        $user = $stmt->fetch();


        if (!$user || !password_verify($password, $user['password'])) {

            $_SESSION['login_errors'] = ['general' => "Invalid email or password."];

            header("Location: login.php");
            exit;
        }

        session_regenerate_id(true);

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['first_name'] = $user['fname'];
        $_SESSION['last_name'] = $user['lname'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['blood_type'] = $user['btype'];
        $_SESSION['phone'] = $user['phone'];

        unset($_SESSION['login_old']);
        unset($_SESSION['login_errors']);

        header("Location: dashboard/dashboard.php?login=success");
        exit;

    } catch (PDOException $e) {

        error_log($e->getMessage());
        $_SESSION['login_errors'] = ['general' => "Unable to login at this time."];

        header("Location: login.php");
        exit;
    }

?>