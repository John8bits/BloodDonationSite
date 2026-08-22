<?php

    session_start();

    require_once __DIR__ . "/config/database.php";

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

        header("Location: register.php");
        exit;

    }

    if (!isset($_POST['csrf_token']) ||
        !isset($_SESSION['csrf_token']) ||
        !hash_equals( $_SESSION['csrf_token'],$_POST['csrf_token'])
    ) {

        $_SESSION['register_error'] = "Invalid request.";
        header("Location: register.php");
        exit;
    }

    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $bloodType = trim($_POST['blood_type'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $terms = isset($_POST['terms']);

    if (
        $firstName === '' ||
        $lastName === '' ||
        $email === '' ||
        $bloodType === '' ||
        $phone === '' ||
        $password === '' ||
        $confirmPassword === '' ||
        !$terms
    ) {

        $_SESSION['register_error'] = "Please complete all required fields.";
        header("Location: register.php");
        exit;

    }


    try {

        $pdo = (new Database())->getConnection();

        $stmt = $pdo->prepare(" SELECT id FROM uinfo WHERE email = :email LIMIT 1 ");

        $stmt->execute([
            ':email' => $email
        ]);


        if ($stmt->fetch()) {

            $_SESSION['register_error'] = "This email address is already registered.";
            header("Location: register.php");
            exit;

        }

        $hashedPassword = password_hash($password,PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("
            INSERT INTO uinfo (
                fname,
                lname,
                email,
                btype,
                phone,
                password
            )
            VALUES (
                :fname,
                :lname,
                :email,
                :btype,
                :phone,
                :password
            )
        ");


        $stmt->execute([

            ':fname' => $firstName,
            ':lname' => $lastName,
            ':email' => $email,
            ':btype' => $bloodType,
            ':phone' => $phone,
            ':password' => $hashedPassword

        ]);

        header("Location: login.php?registered=1");
        exit;

    } catch (PDOException $e) {

        error_log($e->getMessage());

        $_SESSION['register_error'] = "Something went wrong while creating your account.";
        header("Location: register.php");
        exit;
        
    }

?>