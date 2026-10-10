<?php

    session_start();
    require_once __DIR__ . "/../config/database.php";
    require_once __DIR__ . "/../config/auth.php";

    require_admin('../login.php', '../dashboard/dashboard.php');

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header("Location: dashboard.php");
        exit;
    }

    if (
        !isset($_POST['csrf_token']) ||
        !isset($_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        $_SESSION['admin_delete_error'] = "Invalid request. Please try again.";
        header("Location: dashboard.php");
        exit;
    }

    $userId = (int) ($_POST['user_id'] ?? 0);

    if ($userId <= 0) {
        $_SESSION['admin_delete_error'] = "Invalid donor.";
        header("Location: dashboard.php");
        exit;
    }

    // Never let an admin delete their own account from this screen.
    if ($userId === (int) $_SESSION['user_id']) {
        $_SESSION['admin_delete_error'] = "You can't remove your own account here.";
        header("Location: dashboard.php");
        exit;
    }

    try {

        $pdo = (new Database())->getConnection();

        // Don't allow the last remaining admin account to be deleted,
        // even by another admin, so the panel never locks everyone out.
        $stmt = $pdo->prepare("SELECT role FROM uinfo WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $userId]);
        $target = $stmt->fetch();

        if (!$target) {
            $_SESSION['admin_delete_error'] = "That account no longer exists.";
            header("Location: dashboard.php");
            exit;
        }

        if ($target['role'] === 'admin') {
            $countStmt = $pdo->query("SELECT COUNT(*) AS total FROM uinfo WHERE role = 'admin'");
            $adminCount = (int) $countStmt->fetch()['total'];

            if ($adminCount <= 1) {
                $_SESSION['admin_delete_error'] = "You can't remove the last remaining admin account.";
                header("Location: dashboard.php");
                exit;
            }
        }

        $deleteStmt = $pdo->prepare("DELETE FROM uinfo WHERE id = :id");
        $deleteStmt->execute([':id' => $userId]);

        header("Location: dashboard.php");
        exit;

    } catch (PDOException $e) {

        error_log($e->getMessage());

        $_SESSION['admin_delete_error'] = "Something went wrong while removing that account.";
        header("Location: dashboard.php");
        exit;
    }

?>
