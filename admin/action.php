<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_admin('../login.php', '../dashboard/dashboard.php');

function admin_action_redirect(string $message, string $type = 'success', string $page = 'overview'): void
{
    $_SESSION['admin_flash'] = ['message' => $message, 'type' => $type];
    header('Location: dashboard.php?page=' . rawurlencode($page));
    exit;
}

function admin_action_column_exists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column'
    );
    $stmt->execute([':table' => $table, ':column' => $column]);
    return (int) $stmt->fetchColumn() > 0;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    admin_action_redirect('Invalid request method.', 'error');
}
if (
    !isset($_POST['csrf_token'], $_SESSION['csrf_token']) ||
    !is_string($_POST['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
) {
    admin_action_redirect('Your session check failed. Please retry the action.', 'error');
}
foreach ($_POST as $value) {
    if (!is_string($value)) {
        admin_action_redirect('Invalid form data.', 'error');
    }
}

$pdo = (new Database())->getConnection();
$action = $_POST['action'] ?? '';
$adminId = (int) current_user_id();

try {
    $pdo->beginTransaction();
    $log = static function (string $name, string $table, int $recordId, string $details = '', string $reason = '') use ($pdo, $adminId): void {
        $tableCheck = $pdo->prepare(
            "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admin_activity_log'"
        );
        $tableCheck->execute();
        if ((int) $tableCheck->fetchColumn() === 0) {
            return;
        }
        $stmt = $pdo->prepare(
            'INSERT INTO admin_activity_log (admin_id, action, affected_table, affected_record_id, action_details, reason)
             VALUES (:admin_id, :action, :table_name, :record_id, :details, :reason)'
        );
        $stmt->execute([
            ':admin_id' => $adminId,
            ':action' => $name,
            ':table_name' => $table,
            ':record_id' => $recordId,
            ':details' => $details !== '' ? $details : null,
            ':reason' => $reason !== '' ? $reason : null
        ]);
    };

    switch ($action) {
        case 'member_status':
            $userId = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT);
            $status = $_POST['status'] ?? '';
            $reason = trim($_POST['reason'] ?? 'Administrative review');
            if (!$userId || $userId === $adminId || !in_array($status, ['active', 'suspended'], true)) {
                throw new InvalidArgumentException('Choose a valid member account and status.');
            }
            if (!admin_action_column_exists($pdo, 'uinfo', 'account_status')) {
                throw new RuntimeException('Apply the admin enhancement migration before changing account status.');
            }
            $stmt = $pdo->prepare("SELECT role, account_status FROM uinfo WHERE id = :id FOR UPDATE");
            $stmt->execute([':id' => $userId]);
            $member = $stmt->fetch();
            if (!$member || $member['role'] !== 'user') {
                throw new InvalidArgumentException('Only regular member accounts can be modified here.');
            }
            if ($status === 'suspended') {
                $stmt = $pdo->prepare("UPDATE uinfo SET account_status = 'suspended', suspended_at = NOW(), suspended_reason = :reason WHERE id = :id");
                $stmt->execute([':reason' => substr($reason, 0, 255), ':id' => $userId]);
            } else {
                $stmt = $pdo->prepare("UPDATE uinfo SET account_status = 'active', suspended_at = NULL, suspended_reason = NULL WHERE id = :id");
                $stmt->execute([':id' => $userId]);
            }
            $log($status === 'suspended' ? 'suspend_account' : 'reactivate_account', 'uinfo', $userId, 'Account status changed to ' . $status . '.', $reason);
            $pdo->commit();
            admin_action_redirect('Member account updated.', 'success', 'members');

        case 'member_delete':
            $userId = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT);
            if (!$userId || $userId === $adminId) {
                throw new InvalidArgumentException('You cannot remove your own account.');
            }
            $stmt = $pdo->prepare('SELECT role FROM uinfo WHERE id = :id FOR UPDATE');
            $stmt->execute([':id' => $userId]);
            $member = $stmt->fetch();
            if (!$member || $member['role'] !== 'user') {
                throw new InvalidArgumentException('Only existing member accounts can be removed.');
            }
            $log('remove_account', 'uinfo', $userId, 'Member account removed by admin.', trim($_POST['reason'] ?? 'Administrative removal'));
            $stmt = $pdo->prepare('DELETE FROM uinfo WHERE id = :id');
            $stmt->execute([':id' => $userId]);
            $pdo->commit();
            admin_action_redirect('Member account removed.', 'success', 'members');

        case 'request_status':
            $requestId = filter_var($_POST['request_id'] ?? null, FILTER_VALIDATE_INT);
            $status = $_POST['status'] ?? '';
            $reason = trim($_POST['reason'] ?? '');
            if (!$requestId || !in_array($status, ['open', 'fulfilled', 'cancelled', 'expired'], true) || $reason === '' || strlen($reason) > 255) {
                throw new InvalidArgumentException('Choose a status and provide a reason (up to 255 characters).');
            }
            if ($status === 'expired') {
                $statusType = $pdo->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'blood_requests' AND COLUMN_NAME = 'status'")->fetchColumn();
                if (!is_string($statusType) || strpos($statusType, "'expired'") === false) {
                    throw new RuntimeException('Apply the admin enhancement migration before setting requests to expired.');
                }
            }
            $stmt = $pdo->prepare('SELECT status FROM blood_requests WHERE id = :id FOR UPDATE');
            $stmt->execute([':id' => $requestId]);
            if (!$stmt->fetch()) {
                throw new InvalidArgumentException('That blood request no longer exists.');
            }
            $hasReviewColumns = admin_action_column_exists($pdo, 'blood_requests', 'status_reason');
            if ($hasReviewColumns) {
                $stmt = $pdo->prepare('UPDATE blood_requests SET status = :status, status_reason = :reason, reviewed_by = :admin_id, reviewed_at = NOW() WHERE id = :id');
                $stmt->execute([':status' => $status, ':reason' => $reason, ':admin_id' => $adminId, ':id' => $requestId]);
            } else {
                $stmt = $pdo->prepare('UPDATE blood_requests SET status = :status WHERE id = :id');
                $stmt->execute([':status' => $status, ':id' => $requestId]);
            }
            $log('change_request_status', 'blood_requests', $requestId, 'Request status changed to ' . $status . '.', $reason);
            $pdo->commit();
            admin_action_redirect('Blood request status updated.', 'success', 'requests');

        case 'request_remove':
            $requestId = filter_var($_POST['request_id'] ?? null, FILTER_VALIDATE_INT);
            $reason = trim($_POST['reason'] ?? '');
            if (!$requestId || $reason === '' || strlen($reason) > 255) {
                throw new InvalidArgumentException('A removal reason is required (up to 255 characters).');
            }
            $stmt = $pdo->prepare('SELECT id FROM blood_requests WHERE id = :id FOR UPDATE');
            $stmt->execute([':id' => $requestId]);
            if (!$stmt->fetch()) {
                throw new InvalidArgumentException('That request no longer exists.');
            }
            $log('remove_blood_request', 'blood_requests', $requestId, 'Blood request removed by admin.', $reason);
            $stmt = $pdo->prepare('DELETE FROM blood_requests WHERE id = :id');
            $stmt->execute([':id' => $requestId]);
            $pdo->commit();
            admin_action_redirect('Blood request removed.', 'success', 'requests');

        case 'post_remove':
            $postId = filter_var($_POST['post_id'] ?? null, FILTER_VALIDATE_INT);
            $reason = trim($_POST['reason'] ?? '');
            if (!$postId || $reason === '' || strlen($reason) > 255) {
                throw new InvalidArgumentException('A moderation reason is required (up to 255 characters).');
            }
            $stmt = $pdo->prepare('SELECT id FROM community_posts WHERE id = :id FOR UPDATE');
            $stmt->execute([':id' => $postId]);
            if (!$stmt->fetch()) {
                throw new InvalidArgumentException('That post no longer exists.');
            }
            $log('remove_community_post', 'community_posts', $postId, 'Post removed by admin.', $reason);
            $notify = $pdo->prepare("DELETE FROM member_notifications WHERE notification_type = 'post_comment' AND target_id = :id");
            $notify->execute([':id' => $postId]);
            $stmt = $pdo->prepare('DELETE FROM community_posts WHERE id = :id');
            $stmt->execute([':id' => $postId]);
            $pdo->commit();
            admin_action_redirect('Community post removed.', 'success', 'posts');

        case 'event_save':
            if (!admin_action_column_exists($pdo, 'community_posts', 'event_title')) {
                throw new RuntimeException('Apply the admin enhancement migration before managing events.');
            }
            $postId = filter_var($_POST['post_id'] ?? 0, FILTER_VALIDATE_INT);
            $eventTitle = trim($_POST['event_title'] ?? '');
            $content = trim($_POST['content'] ?? '');
            $organizer = trim($_POST['organizer'] ?? '');
            $eventDate = $_POST['event_date'] ?? '';
            $eventTime = trim($_POST['event_time'] ?? '');
            $venue = trim($_POST['venue'] ?? '');
            $city = trim($_POST['event_city'] ?? '');
            $contact = trim($_POST['public_contact'] ?? '');
            $eventStatus = $_POST['event_status'] ?? '';
            $validDate = DateTime::createFromFormat('!Y-m-d', $eventDate);
            if ($eventTitle === '' || strlen($eventTitle) > 160 || $content === '' || strlen($content) > 1000 ||
                !$validDate || $validDate->format('Y-m-d') !== $eventDate ||
                !in_array($eventStatus, ['published', 'draft', 'cancelled', 'completed'], true) ||
                strlen($organizer) > 160 || strlen($venue) > 180 || strlen($city) > 120 || strlen($contact) > 180) {
                throw new InvalidArgumentException('Review event title, description, date, status, and field lengths.');
            }
            if ($eventTime !== '' && !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $eventTime)) {
                throw new InvalidArgumentException('Enter a valid event time.');
            }
            if ($postId > 0) {
                $stmt = $pdo->prepare(
                    "UPDATE community_posts SET event_title = :title, content = :content, event_date = :event_date,
                     event_time = :event_time, organizer = :organizer, venue = :venue, event_city = :city,
                     public_contact = :contact, event_status = :status WHERE id = :id AND post_type = 'event'"
                );
                $stmt->execute([
                    ':title' => $eventTitle, ':content' => $content, ':event_date' => $eventDate,
                    ':event_time' => $eventTime !== '' ? $eventTime : null, ':organizer' => $organizer,
                    ':venue' => $venue, ':city' => $city, ':contact' => $contact,
                    ':status' => $eventStatus, ':id' => $postId
                ]);
                if ($stmt->rowCount() === 0) {
                    $check = $pdo->prepare("SELECT id FROM community_posts WHERE id = :id AND post_type = 'event'");
                    $check->execute([':id' => $postId]);
                    if (!$check->fetch()) {
                        throw new InvalidArgumentException('That donation event no longer exists.');
                    }
                }
                $eventId = $postId;
                $eventAction = 'edit_donation_event';
            } else {
                $stmt = $pdo->prepare(
                    "INSERT INTO community_posts
                     (user_id, post_type, content, event_date, event_title, event_time, organizer, venue, event_city, public_contact, event_status)
                     VALUES (:user_id, 'event', :content, :event_date, :title, :event_time, :organizer, :venue, :city, :contact, :status)"
                );
                $stmt->execute([
                    ':user_id' => $adminId, ':content' => $content, ':event_date' => $eventDate,
                    ':title' => $eventTitle, ':event_time' => $eventTime !== '' ? $eventTime : null,
                    ':organizer' => $organizer, ':venue' => $venue, ':city' => $city,
                    ':contact' => $contact, ':status' => $eventStatus
                ]);
                $eventId = (int) $pdo->lastInsertId();
                $eventAction = 'create_donation_event';
            }
            $log($eventAction, 'community_posts', $eventId, 'Donation event ' . $eventStatus . '.', $eventStatus);
            $pdo->commit();
            admin_action_redirect($postId > 0 ? 'Donation event updated.' : 'Donation event created.', 'success', 'events');

        case 'event_status':
            $postId = filter_var($_POST['post_id'] ?? null, FILTER_VALIDATE_INT);
            $status = $_POST['status'] ?? '';
            if (!$postId || !in_array($status, ['published', 'draft', 'cancelled', 'completed'], true) ||
                !admin_action_column_exists($pdo, 'community_posts', 'event_status')) {
                throw new InvalidArgumentException('Choose a valid event status.');
            }
            $stmt = $pdo->prepare("UPDATE community_posts SET event_status = :status WHERE id = :id AND post_type = 'event'");
            $stmt->execute([':status' => $status, ':id' => $postId]);
            if ($stmt->rowCount() === 0) {
                throw new InvalidArgumentException('That event was not found or its status is unchanged.');
            }
            $log('change_event_status', 'community_posts', $postId, 'Donation event status changed to ' . $status . '.', $status);
            $pdo->commit();
            admin_action_redirect('Donation event status updated.', 'success', 'events');

        case 'save_settings':
            $platformInfo = trim($_POST['platform_info'] ?? '');
            $guidelines = trim($_POST['moderation_guidelines'] ?? '');
            if ($platformInfo === '' || strlen($platformInfo) > 3000 || $guidelines === '' || strlen($guidelines) > 3000) {
                throw new InvalidArgumentException('Enter platform information and moderation guidance (up to 3,000 characters each).');
            }
            if (!admin_action_column_exists($pdo, 'platform_settings', 'setting_value')) {
                throw new RuntimeException('Apply the admin enhancement migration before saving settings.');
            }
            $stmt = $pdo->prepare(
                'INSERT INTO platform_settings (setting_key, setting_value, updated_by)
                 VALUES (:setting_key, :setting_value, :updated_by)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by)'
            );
            $stmt->execute([':setting_key' => 'platform_info', ':setting_value' => $platformInfo, ':updated_by' => $adminId]);
            $stmt->execute([':setting_key' => 'moderation_guidelines', ':setting_value' => $guidelines, ':updated_by' => $adminId]);
            $log('update_admin_settings', 'platform_settings', 0, 'Platform information and moderation guidance updated.', 'Admin settings');
            $pdo->commit();
            admin_action_redirect('Platform settings saved.', 'success', 'settings');

        default:
            throw new InvalidArgumentException('Choose a valid admin action.');
    }
} catch (InvalidArgumentException | RuntimeException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    admin_action_redirect($e->getMessage(), 'error', [
        'member_status' => 'members', 'member_delete' => 'members',
        'request_status' => 'requests', 'request_remove' => 'requests',
        'post_remove' => 'posts', 'event_save' => 'events', 'event_status' => 'events',
        'save_settings' => 'settings'
    ][$action] ?? 'overview');
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log($e->getMessage());
    admin_action_redirect('The admin action failed. Please verify the database migration and try again.', 'error');
}
