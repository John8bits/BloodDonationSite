<?php
session_start();
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';
require_admin('../login.php', '../dashboard/dashboard.php');

function report_redirect(string $message, string $type = 'error'): void
{
    $_SESSION['admin_flash'] = ['message' => $message, 'type' => $type];
    header('Location: dashboard.php?page=reports');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    report_redirect('Invalid request method.');
}
if (
    !isset($_POST['csrf_token'], $_SESSION['csrf_token']) ||
    !is_string($_POST['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
) {
    report_redirect('The report review could not be verified. Please try again.');
}

$postId = filter_var($_POST['post_id'] ?? null, FILTER_VALIDATE_INT);
$reporterId = filter_var($_POST['reporter_id'] ?? null, FILTER_VALIDATE_INT);
$action = $_POST['action'] ?? '';
$reason = trim(is_string($_POST['reason'] ?? null) ? $_POST['reason'] : '');
if (!$postId || !$reporterId || !in_array($action, ['dismiss', 'delete'], true) || $reason === '' || strlen($reason) > 255) {
    report_redirect('Choose a valid report action and enter a review reason of up to 255 characters.');
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
        report_redirect('That report has already been reviewed.');
    }

    if ($action === 'delete') {
        $logAction = 'remove_reported_post';
        $logDetails = 'Reported community post removed after moderation review.';
        $notifyStmt = $pdo->prepare(
            "DELETE FROM member_notifications WHERE notification_type = 'post_comment' AND target_id = :post_id"
        );
        $notifyStmt->execute([':post_id' => $postId]);
        $postStmt = $pdo->prepare('DELETE FROM community_posts WHERE id = :post_id');
        $postStmt->execute([':post_id' => $postId]);
        $message = 'The reported post and related comments were removed.';
    } else {
        $dismissStmt = $pdo->prepare(
            "DELETE FROM post_interactions
             WHERE post_id = :post_id AND user_id = :reporter_id AND interaction = 'report'"
        );
        $dismissStmt->execute([':post_id' => $postId, ':reporter_id' => $reporterId]);
        $logAction = 'dismiss_post_report';
        $logDetails = 'Community post report dismissed after moderation review.';
        $message = 'The community post report was dismissed.';
    }

    $tableCheck = $pdo->query(
        "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admin_activity_log'"
    );
    if ((int) $tableCheck->fetchColumn() > 0) {
        $logStmt = $pdo->prepare(
            'INSERT INTO admin_activity_log (admin_id, action, affected_table, affected_record_id, action_details, reason)
             VALUES (:admin_id, :action, :table_name, :record_id, :details, :reason)'
        );
        $logStmt->execute([
            ':admin_id' => (int) current_user_id(),
            ':action' => $logAction,
            ':table_name' => 'community_posts',
            ':record_id' => $postId,
            ':details' => $logDetails,
            ':reason' => $reason
        ]);
    }

    $pdo->commit();
    report_redirect($message, 'success');
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log($e->getMessage());
    report_redirect('The report could not be reviewed. Please check the database setup and try again.');
}
