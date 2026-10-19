<?php

session_start();
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';

require_admin('../login.php', '../dashboard/dashboard.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit;
}

if (
    !isset($_POST['csrf_token'], $_SESSION['csrf_token']) ||
    !is_string($_POST['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
) {
    $_SESSION['admin_report_message'] = 'The report review could not be verified. Please try again.';
    header('Location: dashboard.php');
    exit;
}

$postId = filter_var($_POST['post_id'] ?? null, FILTER_VALIDATE_INT);
$reporterId = filter_var($_POST['reporter_id'] ?? null, FILTER_VALIDATE_INT);
$action = $_POST['action'] ?? '';
if (!$postId || !$reporterId || !in_array($action, ['dismiss', 'delete'], true)) {
    $_SESSION['admin_report_message'] = 'Choose a valid report action.';
    header('Location: dashboard.php');
    exit;
}

try {
    $pdo = (new Database())->getConnection();
    $pdo->beginTransaction();

    $reportStmt = $pdo->prepare(
        "SELECT post_id FROM post_interactions
         WHERE post_id = :post_id AND user_id = :reporter_id AND interaction = 'report'
         FOR UPDATE"
    );
    $reportStmt->execute([':post_id' => $postId, ':reporter_id' => $reporterId]);
    if (!$reportStmt->fetch()) {
        $pdo->rollBack();
        $_SESSION['admin_report_message'] = 'That report has already been reviewed.';
        header('Location: dashboard.php');
        exit;
    }

    if ($action === 'delete') {
        $notifyStmt = $pdo->prepare(
            "DELETE FROM member_notifications
             WHERE notification_type = 'post_comment' AND target_id = :post_id"
        );
        $notifyStmt->execute([':post_id' => $postId]);
        $postStmt = $pdo->prepare('DELETE FROM community_posts WHERE id = :post_id');
        $postStmt->execute([':post_id' => $postId]);
        $message = 'The reported community post and related comments were removed.';
    } else {
        $dismissStmt = $pdo->prepare(
            "DELETE FROM post_interactions
             WHERE post_id = :post_id AND user_id = :reporter_id AND interaction = 'report'"
        );
        $dismissStmt->execute([':post_id' => $postId, ':reporter_id' => $reporterId]);
        $message = 'The community post report was dismissed.';
    }

    $pdo->commit();
    $_SESSION['admin_report_message'] = $message;
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log($e->getMessage());
    $_SESSION['admin_report_message'] = 'The report could not be reviewed. Please try again.';
}

header('Location: dashboard.php');
exit;
