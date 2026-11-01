<?php

session_start();
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';

require_login('../login.php');

if (is_admin()) {
    header('Location: ../admin/dashboard.php');
    exit;
}

function redirect_member(string $message, string $type, string $section, ?int $postId = null): void
{
    $aliases = ['overview' => 'home', 'community' => 'home', 'donors' => 'find-blood', 'requests' => 'blood-requests'];
    $section = $aliases[$section] ?? $section;
    $_SESSION['member_flash'] = ['message' => $message, 'type' => $type];
    $location = 'dashboard.php?page=' . rawurlencode($section);
    if ($postId !== null && $postId > 0) {
        $location .= '#post-' . $postId;
    }
    header('Location: ' . $location);
    exit;
}

function member_return_page(): string
{
    $allowedPages = ['home', 'events', 'donation-offers', 'my-posts', 'saved', 'search'];
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
                redirect_member(
                    'Your post must contain 1 to 1,000 characters.',
                    'error',
                    $postType === 'donation' ? 'donation-offers' : 'community'
                );
            }
            $eventDate = null;
            if ($postType === 'event') {
                $eventDate = DateTime::createFromFormat('!Y-m-d', $eventDateValue);
                if (!$eventDate || $eventDate->format('Y-m-d') !== $eventDateValue || $eventDateValue < date('Y-m-d')) {
                    redirect_member('Choose a valid event date today or later.', 'error', 'home');
                }
            }
            if ($postType === 'donation') {
                $donationBloodType = $_POST['donation_blood_type'] ?? '';
                $donationLocation = trim($_POST['donation_location'] ?? '');
                $availableDateValue = $_POST['donation_available_date'] ?? '';
                if (!in_array($donationBloodType, $bloodTypes, true)) {
                    redirect_member('Select the blood type you are offering to donate.', 'error', 'donation-offers');
                }
                if ($donationLocation === '' || strlen($donationLocation) > 120) {
                    redirect_member('Enter the city or province where you may be available to donate (up to 120 characters).', 'error', 'donation-offers');
                }

                $availableDate = null;
                if ($availableDateValue !== '') {
                    $availableDate = DateTime::createFromFormat('!Y-m-d', $availableDateValue);
                    if (!$availableDate || $availableDate->format('Y-m-d') !== $availableDateValue || $availableDateValue < date('Y-m-d')) {
                        redirect_member('Choose a valid availability date today or later.', 'error', 'donation-offers');
                    }
                }

                $donationColumns = $pdo->prepare(
                    "SELECT COUNT(*) FROM information_schema.COLUMNS
                     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'community_posts'
                       AND COLUMN_NAME IN ('donation_blood_type', 'donation_location', 'donation_available_date')"
                );
                $donationColumns->execute();
                if ((int) $donationColumns->fetchColumn() !== 3) {
                    redirect_member('Donation offers are not enabled yet. Apply database_sql/donation_offers_migration.sql, then try again.', 'error', 'donation-offers');
                }

                $stmt = $pdo->prepare(
                    "INSERT INTO community_posts
                        (user_id, post_type, content, event_date, donation_blood_type, donation_location, donation_available_date)
                     VALUES (:user_id, 'donation', :content, NULL, :blood_type, :location, :available_date)"
                );
                $stmt->execute([
                    ':user_id' => $userId,
                    ':content' => $content,
                    ':blood_type' => $donationBloodType,
                    ':location' => $donationLocation,
                    ':available_date' => $availableDate ? $availableDate->format('Y-m-d') : null
                ]);
                redirect_member('Your donation offer has been published to the community.', 'success', 'donation-offers');
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
            if (
                !$exists &&
                in_array($interaction, ['like', 'save'], true) &&
                (int) $targetPost['user_id'] !== $userId
            ) {
                $notificationType = $interaction === 'like' ? 'post_like' : 'post_save';
                $notificationMessage = $interaction === 'like' ? 'liked your post.' : 'saved your post.';
                $notifyStmt = $pdo->prepare(
                    'INSERT INTO member_notifications (user_id, actor_id, notification_type, target_id, message)
                     SELECT id, :actor_id, :notification_type, :target_id, :message
                     FROM uinfo
                     WHERE id = :user_id AND notify_post_comments = 1'
                );
                $notifyStmt->execute([
                    ':actor_id' => $userId,
                    ':notification_type' => $notificationType,
                    ':target_id' => $postId,
                    ':message' => $notificationMessage,
                    ':user_id' => (int) $targetPost['user_id']
                ]);
            }
            $pdo->commit();
            $message = $interaction === 'report'
                ? 'The post has been reported for administrator review.'
                : ($exists ? ($interaction === 'like' ? 'Like removed.' : 'Post removed from saved items.') : ($interaction === 'like' ? 'Post liked.' : 'Post saved for later.'));
            redirect_member($message, 'success', member_return_page());

        case 'post_share':
            $postId = filter_var($_POST['post_id'] ?? null, FILTER_VALIDATE_INT);
            if (!$postId) {
                redirect_member('Choose a valid post to share.', 'error', 'home');
            }
            $postStmt = $pdo->prepare('SELECT user_id FROM community_posts WHERE id = :id LIMIT 1');
            $postStmt->execute([':id' => $postId]);
            $targetPost = $postStmt->fetch();
            if (!$targetPost) {
                redirect_member('That post is no longer available.', 'error', 'home');
            }
            if ((int) $targetPost['user_id'] !== $userId) {
                $notifyStmt = $pdo->prepare(
                    'INSERT INTO member_notifications (user_id, actor_id, notification_type, target_id, message)
                     SELECT id, :actor_id, \'post_share\', :target_id, :message
                     FROM uinfo
                     WHERE id = :user_id AND notify_post_comments = 1'
                );
                $notifyStmt->execute([
                    ':actor_id' => $userId,
                    ':target_id' => $postId,
                    ':message' => 'shared your post.',
                    ':user_id' => (int) $targetPost['user_id']
                ]);
            }
            if (($_POST['async'] ?? '') === '1') {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => true], JSON_THROW_ON_ERROR);
                exit;
            }
            redirect_member('The post share has been recorded.', 'success', member_return_page());

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
                 WHERE user_id = :user_id
                   AND notification_type IN ('post_like', 'post_comment', 'post_share', 'post_save')
                   AND target_id = :post_id"
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
            $urgency = $_POST['urgency'] ?? '';
            $neededBy = $_POST['needed_by'] ?? '';
            $details = trim($_POST['details'] ?? '');
            $date = DateTime::createFromFormat('!Y-m-d', $neededBy);
            $dateIsValid = $date && $date->format('Y-m-d') === $neededBy;

            if (!in_array($bloodType, $bloodTypes, true)) {
                redirect_member('Select a valid blood type for this request.', 'error', 'requests');
            }
            if (!in_array($urgency, ['routine', 'urgent', 'emergency'], true)) {
                redirect_member('Select a valid urgency level for this request.', 'error', 'requests');
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
                    (requester_id, blood_type, units, hospital, location, needed_by, urgency, details)
                 VALUES (:requester_id, :blood_type, :units, :hospital, :location, :needed_by, :urgency, :details)'
            );
            $stmt->execute([
                ':requester_id' => $userId,
                ':blood_type' => $bloodType,
                ':units' => $units,
                ':hospital' => $hospital,
                ':location' => $location,
                ':needed_by' => $neededBy,
                ':urgency' => $urgency,
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
                "INSERT INTO request_responses (request_id, user_id, message, status)
                 VALUES (:request_id, :user_id, :message, 'pending')"
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
                ':message' => 'offered to help with your blood request.',
                ':request_id' => $requestId
            ]);
            redirect_member('Your offer has been sent to the requester for review.', 'success', 'requests');

        case 'accept_request_response':
            $responseId = filter_var($_POST['response_id'] ?? null, FILTER_VALIDATE_INT);
            if (!$responseId) {
                redirect_member('Choose a valid offer to accept.', 'error', 'my-requests');
            }

            $pdo->beginTransaction();
            $offerStmt = $pdo->prepare(
                'SELECT rr.id, rr.status AS approval_status, rr.user_id AS responder_id,
                        br.id AS request_id, br.status AS request_status, br.needed_by
                 FROM request_responses rr
                 JOIN blood_requests br ON br.id = rr.request_id
                 WHERE rr.id = :response_id AND br.requester_id = :requester_id
                 FOR UPDATE'
            );
            $offerStmt->execute([':response_id' => $responseId, ':requester_id' => $userId]);
            $offer = $offerStmt->fetch();
            if (!$offer) {
                $pdo->rollBack();
                redirect_member('That offer is not attached to one of your blood requests.', 'error', 'my-requests');
            }
            if ($offer['approval_status'] !== 'pending') {
                $pdo->rollBack();
                redirect_member('That offer has already been reviewed.', 'info', 'my-requests');
            }
            if ($offer['request_status'] !== 'open' || $offer['needed_by'] < date('Y-m-d')) {
                $pdo->rollBack();
                redirect_member('Offers can only be accepted for an open, current request.', 'error', 'my-requests');
            }

            $acceptStmt = $pdo->prepare(
                "UPDATE request_responses SET status = 'accepted'
                 WHERE id = :response_id AND status = 'pending'"
            );
            $acceptStmt->execute([':response_id' => $responseId]);
            if ($acceptStmt->rowCount() !== 1) {
                $pdo->rollBack();
                redirect_member('That offer has already been reviewed.', 'info', 'my-requests');
            }

            $notifyStmt = $pdo->prepare(
                'INSERT INTO member_notifications (user_id, actor_id, notification_type, target_id, message)
                 SELECT id, :actor_id, \'request_response\', :target_id, :message
                 FROM uinfo
                 WHERE id = :recipient_id AND notify_request_responses = 1'
            );
            $notifyStmt->execute([
                ':actor_id' => $userId,
                ':target_id' => (int) $offer['request_id'],
                ':message' => 'accepted your offer to help with a blood request. You can now message privately.',
                ':recipient_id' => (int) $offer['responder_id']
            ]);
            $pdo->commit();
            redirect_member('Offer accepted. Private messaging is now available.', 'success', 'my-requests');

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

        case 'request_message':
            $responseId = filter_var($_POST['response_id'] ?? null, FILTER_VALIDATE_INT);
            $message = trim($_POST['message'] ?? '');
            if (!$responseId || $message === '' || strlen($message) > 1000) {
                redirect_member('Enter a reply of 1 to 1,000 characters.', 'error', 'my-requests');
            }

            $threadStmt = $pdo->prepare(
                'SELECT rr.id, rr.status AS approval_status, rr.user_id AS responder_id, br.id AS request_id,
                        br.requester_id, br.status, br.needed_by
                 FROM request_responses rr
                 JOIN blood_requests br ON br.id = rr.request_id
                 WHERE rr.id = :response_id
                   AND (br.requester_id = :requester_id OR rr.user_id = :responder_id)
                 LIMIT 1'
            );
            $threadStmt->execute([
                ':response_id' => $responseId,
                ':requester_id' => $userId,
                ':responder_id' => $userId
            ]);
            $thread = $threadStmt->fetch();
            if (!$thread) {
                redirect_member('That conversation is not available to your account.', 'error', 'my-requests');
            }
            if ($thread['approval_status'] !== 'accepted') {
                redirect_member('The requester must accept your offer before private messaging is available.', 'error', (int) $thread['requester_id'] === $userId ? 'my-requests' : 'responded');
            }
            if ($thread['status'] !== 'open' || $thread['needed_by'] < date('Y-m-d')) {
                redirect_member('This blood request is closed, so its conversation can no longer receive replies.', 'error', $thread['requester_id'] == $userId ? 'my-requests' : 'responded');
            }

            $recipientId = (int) $thread['requester_id'] === $userId
                ? (int) $thread['responder_id']
                : (int) $thread['requester_id'];
            $pdo->beginTransaction();
            $messageStmt = $pdo->prepare(
                'INSERT INTO request_response_messages (response_id, sender_id, message)
                 VALUES (:response_id, :sender_id, :message)'
            );
            $messageStmt->execute([
                ':response_id' => $responseId,
                ':sender_id' => $userId,
                ':message' => $message
            ]);
            $notifyStmt = $pdo->prepare(
                'INSERT INTO member_notifications (user_id, actor_id, notification_type, target_id, message)
                 SELECT id, :actor_id, \'request_message\', :target_id, :message
                 FROM uinfo
                 WHERE id = :recipient_id AND notify_request_responses = 1'
            );
            $notifyStmt->execute([
                ':actor_id' => $userId,
                ':target_id' => (int) $thread['request_id'],
                ':message' => 'sent you a private message about a blood request.',
                ':recipient_id' => $recipientId
            ]);
            $pdo->commit();
            redirect_member('Your private reply has been sent.', 'success', (int) $thread['requester_id'] === $userId ? 'my-requests' : 'responded');

        case 'donation_offer_message':
            $postId = filter_var($_POST['post_id'] ?? null, FILTER_VALIDATE_INT);
            $seekerId = filter_var($_POST['seeker_id'] ?? null, FILTER_VALIDATE_INT);
            $message = trim($_POST['message'] ?? '');
            $urgency = $_POST['urgency'] ?? '';
            if (!$postId || !$seekerId || $message === '' || strlen($message) > 1000) {
                redirect_member('Enter a private offer message of 1 to 1,000 characters.', 'error', 'donation-offers');
            }

            $offerStmt = $pdo->prepare(
                "SELECT id, user_id FROM community_posts
                 WHERE id = :id AND post_type = 'donation'
                   AND (donation_available_date IS NULL OR donation_available_date >= CURDATE())
                 LIMIT 1"
            );
            $offerStmt->execute([':id' => $postId]);
            $offer = $offerStmt->fetch();
            if (!$offer) {
                redirect_member('That donation offer is no longer available.', 'error', 'donation-offers');
            }

            $offerOwnerId = (int) $offer['user_id'];
            if ($offerOwnerId === $seekerId || ($userId !== $offerOwnerId && $userId !== $seekerId)) {
                redirect_member('This private conversation is not available to your account.', 'error', 'donation-offers');
            }
            $threadStmt = $pdo->prepare(
                'SELECT 1 FROM donation_offer_messages
                 WHERE post_id = :post_id AND seeker_id = :seeker_id LIMIT 1'
            );
            $threadStmt->execute([':post_id' => $postId, ':seeker_id' => $seekerId]);
            $threadExists = (bool) $threadStmt->fetchColumn();
            if ($userId === $offerOwnerId && !$threadExists) {
                redirect_member('You can reply only to a member who has contacted you about this offer.', 'error', 'donation-offers');
            }
            if ($userId === $seekerId && !$threadExists && !in_array($urgency, ['routine', 'urgent', 'emergency'], true)) {
                redirect_member('Select an urgency level before contacting the donor.', 'error', 'donation-offers', $postId);
            }

            $recipientId = $userId === $offerOwnerId ? $seekerId : $offerOwnerId;
            $pdo->beginTransaction();
            $messageStmt = $pdo->prepare(
                'INSERT INTO donation_offer_messages (post_id, seeker_id, sender_id, message, urgency)
                 VALUES (:post_id, :seeker_id, :sender_id, :message, :urgency)'
            );
            $messageStmt->execute([
                ':post_id' => $postId,
                ':seeker_id' => $seekerId,
                ':sender_id' => $userId,
                ':message' => $message,
                ':urgency' => $userId === $seekerId && !$threadExists ? $urgency : null
            ]);
            $notifyStmt = $pdo->prepare(
                'INSERT INTO member_notifications (user_id, actor_id, notification_type, target_id, message)
                 SELECT id, :actor_id, \'donation_offer_message\', :target_id, :message
                 FROM uinfo
                 WHERE id = :recipient_id AND notify_post_comments = 1'
            );
            $notifyStmt->execute([
                ':actor_id' => $userId,
                ':target_id' => $postId,
                ':message' => 'sent you a private message about a donation offer.',
                ':recipient_id' => $recipientId
            ]);
            $pdo->commit();
            redirect_member('Your private message has been sent.', 'success', 'donation-offers', $postId);

        case 'notification_open':
            $notificationId = filter_var($_POST['notification_id'] ?? null, FILTER_VALIDATE_INT);
            if (!$notificationId) {
                redirect_member('Choose a valid notification.', 'error', 'notifications');
            }
            $stmt = $pdo->prepare(
                'SELECT notification_type, target_id FROM member_notifications
                 WHERE id = :id AND user_id = :user_id LIMIT 1'
            );
            $stmt->execute([':id' => $notificationId, ':user_id' => $userId]);
            $notification = $stmt->fetch();
            if (!$notification) {
                redirect_member('That notification is no longer available.', 'error', 'notifications');
            }
            $stmt = $pdo->prepare('UPDATE member_notifications SET is_read = 1 WHERE id = :id AND user_id = :user_id');
            $stmt->execute([':id' => $notificationId, ':user_id' => $userId]);
            if ($notification['notification_type'] === 'request_message') {
                $threadStmt = $pdo->prepare(
                    'SELECT br.requester_id, rr.user_id AS responder_id
                     FROM request_responses rr
                     JOIN blood_requests br ON br.id = rr.request_id
                     WHERE br.id = :request_id
                       AND (br.requester_id = :requester_id OR rr.user_id = :responder_id)
                     LIMIT 1'
                );
                $threadStmt->execute([
                    ':request_id' => (int) $notification['target_id'],
                    ':requester_id' => $userId,
                    ':responder_id' => $userId
                ]);
                $thread = $threadStmt->fetch();
                if (!$thread) {
                    redirect_member('That conversation is no longer available.', 'error', 'notifications');
                }
                redirect_member('Conversation opened.', 'success', (int) $thread['requester_id'] === $userId ? 'my-requests' : 'responded');
            }
            if ($notification['notification_type'] === 'request_response') {
                $requestStmt = $pdo->prepare(
                    'SELECT br.requester_id,
                            EXISTS (
                                SELECT 1 FROM request_responses rr
                                WHERE rr.request_id = br.id AND rr.user_id = :responder_id
                            ) AS is_responder
                     FROM blood_requests br
                     WHERE br.id = :request_id
                     LIMIT 1'
                );
                $requestStmt->execute([
                    ':responder_id' => $userId,
                    ':request_id' => (int) $notification['target_id']
                ]);
                $request = $requestStmt->fetch();
                if (!$request || ((int) $request['requester_id'] !== $userId && !$request['is_responder'])) {
                    redirect_member('That blood-request update is no longer available.', 'error', 'notifications');
                }
                redirect_member(
                    'Blood-request update opened.',
                    'success',
                    (int) $request['requester_id'] === $userId ? 'my-requests' : 'responded'
                );
            }
            if ($notification['notification_type'] === 'donation_offer_message') {
                $offerThreadStmt = $pdo->prepare(
                    "SELECT cp.id
                     FROM community_posts cp
                     WHERE cp.id = :post_id AND cp.post_type = 'donation'
                       AND (cp.user_id = :owner_id OR EXISTS (
                           SELECT 1 FROM donation_offer_messages m
                           WHERE m.post_id = cp.id AND m.seeker_id = :seeker_id
                       ))
                     LIMIT 1"
                );
                $offerThreadStmt->execute([
                    ':post_id' => (int) $notification['target_id'],
                    ':owner_id' => $userId,
                    ':seeker_id' => $userId
                ]);
                if (!$offerThreadStmt->fetchColumn()) {
                    redirect_member('That donation conversation is no longer available.', 'error', 'notifications');
                }
                redirect_member('Donation offer conversation opened.', 'success', 'donation-offers', (int) $notification['target_id']);
            }
            if (in_array($notification['notification_type'], ['post_like', 'post_comment', 'post_share', 'post_save'], true)) {
                redirect_member('Post activity opened.', 'success', 'my-posts', (int) $notification['target_id']);
            }
            redirect_member('Notification opened.', 'success', $notification['notification_type'] === 'request_response' ? 'my-requests' : 'my-posts');

        case 'notifications_read_all':
            $stmt = $pdo->prepare('UPDATE member_notifications SET is_read = 1 WHERE user_id = :user_id AND is_read = 0');
            $stmt->execute([':user_id' => $userId]);
            redirect_member('All notifications have been marked as read.', 'success', 'notifications');

        default:
            redirect_member('Choose a valid member action.', 'error', 'overview');
    }
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log($e->getMessage());
    redirect_member('We could not save that change. Please try again.', 'error', 'overview');
}
