<?php

    session_start();

    require_once __DIR__ . "/config/database.php";

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

        header("Location: login.php");
        exit;

    }

    if (!isset($_POST['csrf_token']) ||
        !isset($_SESSION['csrf_token']) ||
        !hash_equals( $_SESSION['csrf_token'],$_POST['csrf_token'])
    ) {

        $_SESSION['login_error'] = "Invalid login request.";
        header("Location: login.php");
        exit;
    }

    $email = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';

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
            FROM uinfo WHERE email = :email LIMIT 1
        ");

        $stmt->execute([
            ':email' => $email
        ]);

        $user = $stmt->fetch();

        if (!$user || !password_verify($password,$user['password']))
        {

            $_SESSION['login_error'] = "Invalid email or password.";
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

        header("Location: dashboard/dashboard.php");
        exit;

    } catch (PDOException $e) {

        error_log($e->getMessage());

        $_SESSION['login_error'] = "Unable to login at this time.";
        header("Location: login.php");
        exit;
        
    }

?>