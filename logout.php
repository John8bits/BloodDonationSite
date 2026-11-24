<?php

    session_start();
    if (($_SESSION['role'] ?? '') === 'user' && session_id() !== '') {
        try {
            require_once __DIR__ . '/config/database.php';
            $pdo = (new Database())->getConnection();
            $exists = $pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'member_sessions'");
            $exists->execute();
            if ((int)$exists->fetchColumn() > 0) {
                $pdo->prepare('UPDATE member_sessions SET revoked_at = NOW() WHERE session_hash = :hash AND user_id = :user_id AND revoked_at IS NULL')
                    ->execute([':hash' => hash('sha256', session_id()), ':user_id' => (int)($_SESSION['user_id'] ?? 0)]);
            }
        } catch (Throwable $error) {
            // Logout must continue even if session-record cleanup is unavailable.
        }
    }
    $_SESSION = [];
    session_destroy();

    header("Location: login.php");
    exit;

?>
