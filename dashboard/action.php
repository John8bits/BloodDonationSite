<?php

session_start();
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';

require_login('../login.php');

if (is_admin()) {
    header('Location: ../admin/dashboard.php');
    exit;
}

function redirect_member(string $message, string $type, string $section): void
{
    $aliases = ['overview' => 'home', 'community' => 'home', 'donors' => 'find-blood', 'requests' => 'blood-requests'];
    $section = $aliases[$section] ?? $section;
    $_SESSION['member_flash'] = ['message' => $message, 'type' => $type];
    header('Location: dashboard.php?page=' . rawurlencode($section));
    exit;
}

function member_return_page(): string
{
    $allowedPages = ['home', 'events', 'my-posts', 'saved', 'search'];
    $page = $_POST['return_page'] ?? 'home';
    return is_string($page) && in_array($page, $allowedPages, true) ? $page : 'home';
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit;
}

if (
    !isset($_POST['csrf_token'], $_SESSION['csrf_token']) ||
    !is_string($_POST['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
) {
    redirect_member('Your session check failed. Please try again.', 'error', 'overview');
}

foreach ($_POST as $value) {
    if (!is_string($value)) {
        redirect_member('Invalid form data. Please review the form and try again.', 'error', 'overview');
    }
}

$userId = (int) current_user_id();
$bloodTypes = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
$action = $_POST['action'] ?? '';

try {
    $pdo = (new Database())->getConnection();
    $accountStatusCheck = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'uinfo' AND COLUMN_NAME = 'account_status'"
    );
    $accountStatusCheck->execute();
    if ((int) $accountStatusCheck->fetchColumn() > 0) {
        $statusStmt = $pdo->prepare('SELECT account_status FROM uinfo WHERE id = :id LIMIT 1');
        $statusStmt->execute([':id' => $userId]);
        if ($statusStmt->fetchColumn() !== 'active') {
            session_destroy();
            header('Location: ../login.php?suspended=1');
            exit;
        }
    }

    switch ($action) {
        case 'profile':
            $firstName = trim($_POST['fname'] ?? '');
            $lastName = trim($_POST['lname'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $city = trim($_POST['city'] ?? '');
            $bloodType = $_POST['btype'] ?? '';
            $available = ($_POST['donor_available'] ?? '') === '1';
            $errors = [];

            if ($firstName === '' || strlen($firstName) > 100) {
                $errors[] = 'Enter a first name of up to 100 characters.';
            }
            if ($lastName === '' || strlen($lastName) > 100) {
                $errors[] = 'Enter a last name of up to 100 characters.';
            }
            if (strlen($phone) > 20) {
                $errors[] = 'Phone number must be 20 characters or fewer.';
            }
            if (strlen($city) > 120) {
                $errors[] = 'City must be 120 characters or fewer.';
            }
            if ($bloodType !== '' && !in_array($bloodType, $bloodTypes, true)) {
                $errors[] = 'Select a valid blood type.';
            }
            if ($available && ($bloodType === '' || $phone === '')) {
                $errors[] = 'Add your blood type and phone number before appearing in donor search.';
            }
            if ($errors) {
                redirect_member(implode(' ', $errors), 'error', 'profile');
            }

            $stmt = $pdo->prepare(
                'UPDATE uinfo SET fname = :fname, lname = :lname, phone = :phone,
                    city = :city, btype = :btype, donor_available = :available
                 WHERE id = :id'
            );
            $stmt->execute([
                ':fname' => $firstName,
                ':lname' => $lastName,
                ':phone' => $phone,
                ':city' => $city,
                ':btype' => $bloodType === '' ? null : $bloodType,
                ':available' => $available ? 1 : 0,
                ':id' => $userId
            ]);
            $_SESSION['first_name'] = $firstName;
            $_SESSION['last_name'] = $lastName;
            $_SESSION['phone'] = $phone;
            $_SESSION['blood_type'] = $bloodType;
            redirect_member('Your member profile has been updated.', 'success', 'profile');

        case 'settings':
            $notifyResponses = ($_POST['notify_request_responses'] ?? '') === '1' ? 1 : 0;
            $notifyComments = ($_POST['notify_post_comments'] ?? '') === '1' ? 1 : 0;
            $stmt = $pdo->prepare(
                'UPDATE uinfo
                 SET notify_request_responses = :notify_responses,
                     notify_post_comments = :notify_comments
                 WHERE id = :id'
            );
            $stmt->execute([
                ':notify_responses' => $notifyResponses,
                ':notify_comments' => $notifyComments,
                ':id' => $userId
            ]);
            redirect_member('Your notification preferences have been saved.', 'success', 'settings');

        case 'post':
            $postType = $_POST['post_type'] ?? '';
            $content = trim($_POST['content'] ?? '');
            $eventDateValue = $_POST['event_date'] ?? '';
            if (!in_array($postType, ['community', 'donation', 'event', 'success_story'], true)) {
                redirect_member('Choose a valid post category.', 'error', 'community');
            }
            if ($content === '' || strlen($content) > 1000) {
                redirect_member('Your post must contain 1 to 1,000 characters.', 'error', 'community');
            }
            $eventDate = null;
            if ($postType === 'event') {
                $eventDate = DateTime::createFromFormat('!Y-m-d', $eventDateValue);
                if (!$eventDate || $eventDate->format('Y-m-d') !== $eventDateValue || $eventDateValue < date('Y-m-d')) {
                    redirect_member('Choose a valid event date today or later.', 'home');
                }
            }
            $stmt = $pdo->prepare(
                'INSERT INTO community_posts (user_id, post_type, content, event_date)
                 VALUES (:user_id, :post_type, :content, :event_date)'
            );
            $stmt->execute([
                ':user_id' => $userId,
                ':post_type' => $postType,
                ':content' => $content,
                ':event_date' => $eventDate ? $eventDate->format('Y-m-d') : null
            ]);
            redirect_member('Your post has been shared with the community.', 'success', 'home');

        case 'post_interaction':
            $postId = filter_var($_POST['post_id'] ?? null, FILTER_VALIDATE_INT);
            $interaction = $_POST['interaction'] ?? '';
            if (!$postId || !in_array($interaction, ['like', 'save', 'report'], true)) {
                redirect_member('Choose a valid post action.', 'error', 'home');
            }

            $pdo->beginTransaction();
            $postStmt = $pdo->prepare('SELECT id, user_id FROM community_posts WHERE id = :id FOR UPDATE');
            $postStmt->execute([':id' => $postId]);
            $targetPost = $postStmt->fetch();
            if (!$targetPost) {
                $pdo->rollBack();
                redirect_member('That post is no longer available.', 'error', 'home');
            }
            if ($interaction === 'report' && (int) $targetPost['user_id'] === $userId) {
                $pdo->rollBack();
                redirect_member('You cannot report your own post.', 'error', 'home');
            }

            $existingStmt = $pdo->prepare(
                'SELECT post_id FROM post_interactions
                 WHERE post_id = :post_id AND user_id = :user_id AND interaction = :interaction'
            );
            $existingStmt->execute([
                ':post_id' => $postId,
                ':user_id' => $userId,
                ':interaction' => $interaction
            ]);
            $exists = (bool) $existingStmt->fetch();

            if ($interaction === 'report') {
                if (!$exists) {
                    $reportReason = trim($_POST['report_reason'] ?? '');
                    if ($reportReason === '' || strlen($reportReason) > 255) {
                        $pdo->rollBack();
                        redirect_member('Select or enter a reason for reporting this post.', 'error', 'home');
                    }
                    $reasonColumnCheck = $pdo->prepare(
                        "SELECT COUNT(*) FROM information_schema.COLUMNS
                         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'post_interactions' AND COLUMN_NAME = 'report_reason'"
                    );
                    $reasonColumnCheck->execute();
                    if ((int) $reasonColumnCheck->fetchColumn() > 0) {
                        $insertStmt = $pdo->prepare(
                            'INSERT INTO post_interactions (post_id, user_id, interaction, report_reason)
                             VALUES (:post_id, :user_id, :interaction, :reason)'
                        );
                        $insertStmt->execute([
                            ':post_id' => $postId,
                            ':user_id' => $userId,
                            ':interaction' => $interaction,
                            ':reason' => $reportReason
                        ]);
                    } else {
                        $insertStmt = $pdo->prepare(
                            'INSERT INTO post_interactions (post_id, user_id, interaction)
                             VALUES (:post_id, :user_id, :interaction)'
                        );
                        $insertStmt->execute([':post_id' => $postId, ':user_id' => $userId, ':interaction' => $interaction]);
                    }
                }
            } elseif ($exists) {
                $deleteStmt = $pdo->prepare(
                    'DELETE FROM post_interactions
                     WHERE post_id = :post_id AND user_id = :user_id AND interaction = :interaction'
                );
                $deleteStmt->execute([':post_id' => $postId, ':user_id' => $userId, ':interaction' => $interaction]);
            } else {
                $insertStmt = $pdo->prepare(
                    'INSERT INTO post_interactions (post_id, user_id, interaction)
                     VALUES (:post_id, :user_id, :interaction)'
                );
                $insertStmt->execute([':post_id' => $postId, ':user_id' => $userId, ':interaction' => $interaction]);
            }
            $pdo->commit();
            $message = $interaction === 'report'
                ? 'The post has been reported for administrator review.'
                : ($exists ? ($interaction === 'like' ? 'Like removed.' : 'Post removed from saved items.') : ($interaction === 'like' ? 'Post liked.' : 'Post saved for later.'));
            redirect_member($message, 'success', member_return_page());

        case 'delete_post':
            $postId = filter_var($_POST['post_id'] ?? null, FILTER_VALIDATE_INT);
            if (!$postId) {
                redirect_member('Choose a valid post to delete.', 'error', 'my-posts');
            }
            $stmt = $pdo->prepare('DELETE FROM community_posts WHERE id = :id AND user_id = :user_id');
            $stmt->execute([':id' => $postId, ':user_id' => $userId]);
            if ($stmt->rowCount() !== 1) {
                redirect_member('That post could not be deleted because it is not yours or is no longer available.', 'error', 'my-posts');
            }
            $notifyStmt = $pdo->prepare(
                "DELETE FROM member_notifications
                 WHERE user_id = :user_id AND notification_type = 'post_comment' AND target_id = :post_id"
            );
            $notifyStmt->execute([':user_id' => $userId, ':post_id' => $postId]);
            redirect_member('Your post and its comments have been deleted.', 'success', 'my-posts');

        case 'comment':
            $postId = filter_var($_POST['post_id'] ?? null, FILTER_VALIDATE_INT);
            $comment = trim($_POST['comment'] ?? '');
            if (!$postId || $comment === '' || strlen($comment) > 500) {
                redirect_member('Comments must contain 1 to 500 characters.', 'error', 'community');
            }
            $stmt = $pdo->prepare('SELECT id, user_id FROM community_posts WHERE id = :id');
            $stmt->execute([':id' => $postId]);
            $targetPost = $stmt->fetch();
            if (!$targetPost) {
                redirect_member('That community post is no longer available.', 'error', 'community');
            }
            $stmt = $pdo->prepare(
                'INSERT INTO post_comments (post_id, user_id, comment)
                 VALUES (:post_id, :user_id, :comment)'
            );
            $stmt->execute([
                ':post_id' => $postId,
                ':user_id' => $userId,
                ':comment' => $comment
            ]);
            if ((int) $targetPost['user_id'] !== $userId) {
                $notifyStmt = $pdo->prepare(
                    'INSERT INTO member_notifications (user_id, actor_id, notification_type, target_id, message)
                     SELECT id, :actor_id, \'post_comment\', :target_id, :message
                     FROM uinfo
                     WHERE id = :user_id AND notify_post_comments = 1'
                );
                $notifyStmt->execute([
                    ':actor_id' => $userId,
                    ':target_id' => $postId,
                    ':message' => 'commented on your post.',
                    ':user_id' => (int) $targetPost['user_id']
                ]);
            }
            redirect_member('Your comment has been added.', 'success', member_return_page());

        case 'request':
            $bloodType = $_POST['blood_type'] ?? '';
            $units = filter_var($_POST['units'] ?? null, FILTER_VALIDATE_INT);
            $hospital = trim($_POST['hospital'] ?? '');
            $location = trim($_POST['location'] ?? '');
            $neededBy = $_POST['needed_by'] ?? '';
            $details = trim($_POST['details'] ?? '');
            $date = DateTime::createFromFormat('!Y-m-d', $neededBy);
            $dateIsValid = $date && $date->format('Y-m-d') === $neededBy;

            if (!in_array($bloodType, $bloodTypes, true)) {
                redirect_member('Select a valid blood type for this request.', 'error', 'requests');
            }
            if (!$units || $units < 1 || $units > 20) {
                redirect_member('Requested units must be between 1 and 20.', 'error', 'requests');
            }
            if ($hospital === '' || strlen($hospital) > 160 || $location === '' || strlen($location) > 160) {
                redirect_member('Enter a hospital and location, each up to 160 characters.', 'error', 'requests');
            }
            if (!$dateIsValid || $neededBy < date('Y-m-d')) {
                redirect_member('Choose a valid date today or later.', 'error', 'requests');
            }
            if (strlen($details) > 1000) {
                redirect_member('Request details must be 1,000 characters or fewer.', 'error', 'requests');
            }

            $stmt = $pdo->prepare(
                'INSERT INTO blood_requests
                    (requester_id, blood_type, units, hospital, location, needed_by, details)
                 VALUES (:requester_id, :blood_type, :units, :hospital, :location, :needed_by, :details)'
            );
            $stmt->execute([
                ':requester_id' => $userId,
                ':blood_type' => $bloodType,
                ':units' => $units,
                ':hospital' => $hospital,
                ':location' => $location,
                ':needed_by' => $neededBy,
                ':details' => $details
            ]);
            redirect_member('Your blood request is now visible to the community.', 'success', 'requests');

        case 'respond':
            $requestId = filter_var($_POST['request_id'] ?? null, FILTER_VALIDATE_INT);
            $message = trim($_POST['message'] ?? '');
            if (!$requestId || strlen($message) > 500) {
                redirect_member('Enter a valid request response of up to 500 characters.', 'error', 'requests');
            }
            $stmt = $pdo->prepare(
                "SELECT id FROM blood_requests
                 WHERE id = :id AND requester_id <> :user_id AND status = 'open' AND needed_by >= CURDATE()"
            );
            $stmt->execute([':id' => $requestId, ':user_id' => $userId]);
            if (!$stmt->fetch()) {
                redirect_member('That request is closed or is not available to respond to.', 'error', 'requests');
            }
            $stmt = $pdo->prepare(
                'SELECT id FROM request_responses WHERE request_id = :request_id AND user_id = :user_id'
            );
            $stmt->execute([':request_id' => $requestId, ':user_id' => $userId]);
            if ($stmt->fetch()) {
                redirect_member('You have already responded to that request.', 'error', 'requests');
            }
            $stmt = $pdo->prepare(
                'INSERT INTO request_responses (request_id, user_id, message)
                 VALUES (:request_id, :user_id, :message)'
            );
            $stmt->execute([
                ':request_id' => $requestId,
                ':user_id' => $userId,
                ':message' => $message
            ]);
            $notifyStmt = $pdo->prepare(
                'INSERT INTO member_notifications (user_id, actor_id, notification_type, target_id, message)
                 SELECT u.id, :actor_id, \'request_response\', :target_id, :message
                 FROM uinfo u
                 JOIN blood_requests br ON br.requester_id = u.id
                 WHERE br.id = :request_id AND u.notify_request_responses = 1'
            );
            $notifyStmt->execute([
                ':actor_id' => $userId,
                ':target_id' => $requestId,
                ':message' => 'responded to your blood request.',
                ':request_id' => $requestId
            ]);
            redirect_member('Your response has been sent to the requester.', 'success', 'requests');

        case 'request_status':
            $requestId = filter_var($_POST['request_id'] ?? null, FILTER_VALIDATE_INT);
            $status = $_POST['status'] ?? '';
            if (!$requestId || !in_array($status, ['fulfilled', 'cancelled'], true)) {
                redirect_member('Choose a valid request status.', 'error', 'requests');
            }
            $stmt = $pdo->prepare(
                "UPDATE blood_requests SET status = :status
                 WHERE id = :id AND requester_id = :user_id AND status = 'open' AND needed_by >= CURDATE()"
            );
            $stmt->execute([
                ':status' => $status,
                ':id' => $requestId,
                ':user_id' => $userId
            ]);
            if ($stmt->rowCount() !== 1) {
                redirect_member('Only your open requests can be updated.', 'error', 'requests');
            }
            redirect_member('Your request status has been updated.', 'success', 'requests');

        case 'notification_open':
            $notificationId = filter_var($_POST['notification_id'] ?? null, FILTER_VALIDATE_INT);
            if (!$notificationId) {
                redirect_member('Choose a valid notification.', 'error', 'notifications');
            }
            $stmt = $pdo->prepare(
                'SELECT notification_type FROM member_notifications
                 WHERE id = :id AND user_id = :user_id LIMIT 1'
            );
            $stmt->execute([':id' => $notificationId, ':user_id' => $userId]);
            $notification = $stmt->fetch();
            if (!$notification) {
                redirect_member('That notification is no longer available.', 'error', 'notifications');
            }
            $stmt = $pdo->prepare('UPDATE member_notifications SET is_read = 1 WHERE id = :id AND user_id = :user_id');
            $stmt->execute([':id' => $notificationId, ':user_id' => $userId]);
            redirect_member('Notification opened.', 'success', $notification['notification_type'] === 'request_response' ? 'my-requests' : 'my-posts');

        case 'notifications_read_all':
            $stmt = $pdo->prepare('UPDATE member_notifications SET is_read = 1 WHERE user_id = :user_id AND is_read = 0');
            $stmt->execute([':user_id' => $userId]);
            redirect_member('All notifications have been marked as read.', 'success', 'notifications');

        default:
            redirect_member('Choose a valid member action.', 'error', 'overview');
    }
} catch (PDOException $e) {
    error_log($e->getMessage());
    redirect_member('We could not save that change. Please try again.', 'error', 'overview');
}
