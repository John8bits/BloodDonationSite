<?php
session_start();
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';
require_clinic('../login.php');

function clinic_redirect(string $message, string $type, string $page = 'overview'): void
{
    $_SESSION['clinic_flash'] = ['message' => $message, 'type' => $type];
    header('Location: dashboard.php?page=' . rawurlencode($page));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['csrf_token'], $_SESSION['csrf_token']) || !is_string($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    clinic_redirect('Your session check failed. Please try again.', 'error');
}
foreach ($_POST as $value) if (!is_string($value)) clinic_redirect('Invalid form data.', 'error');
$userId = (int) current_user_id();
$action = $_POST['action'] ?? '';
$pdo = (new Database())->getConnection();
$activeStmt = $pdo->prepare("SELECT account_status,must_change_password FROM uinfo WHERE id=:id AND role='clinic' LIMIT 1");
$activeStmt->execute([':id'=>$userId]);
$clinicAccount = $activeStmt->fetch();
if (!$clinicAccount || $clinicAccount['account_status'] !== 'active') { session_destroy(); header('Location: ../login.php?suspended=1'); exit; }
if ((int)$clinicAccount['must_change_password'] === 1 && $action !== 'clinic_password_change') clinic_redirect('Change your temporary password before using clinic functions.', 'error', 'settings');

try {
    switch ($action) {
        case 'clinic_request_create':
        case 'clinic_request_edit':
            $requestId = $action === 'clinic_request_edit' ? filter_var($_POST['request_id'] ?? null, FILTER_VALIDATE_INT) : null;
            if ($action === 'clinic_request_edit' && !$requestId) clinic_redirect('Choose a valid request to edit.', 'error', 'requests');
            $bloodType = $_POST['blood_type'] ?? '';
            $units = filter_var($_POST['units'] ?? null, FILTER_VALIDATE_INT);
            $hospital = trim($_POST['hospital'] ?? '');
            $location = trim($_POST['location'] ?? '');
            $neededBy = $_POST['needed_by'] ?? '';
            $urgency = $_POST['urgency'] ?? '';
            $details = trim($_POST['details'] ?? '');
            $provinces = require __DIR__ . '/../config/philippine_provinces.php';
            $date = DateTime::createFromFormat('!Y-m-d', $neededBy);
            if (!in_array($bloodType,['A+','A-','B+','B-','AB+','AB-','O+','O-'],true) || !$units || $units < 1 || $units > 20 || $hospital === '' || strlen($hospital)>160 || !in_array($location,$provinces,true) || !$date || $date->format('Y-m-d') !== $neededBy || $neededBy < date('Y-m-d') || !in_array($urgency,['routine','urgent','emergency'],true) || strlen($details)>1000) clinic_redirect('Review the blood request details and try again.', 'error', 'requests');
            if (!$requestId && ($_POST['represented_person_permission'] ?? '') !== '1') clinic_redirect('Confirm that the patient or their authorized representative gave permission.', 'error', 'requests');
            if (!$requestId) {
                $permissionColumn = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='blood_requests' AND COLUMN_NAME='representation_authorized_at'");
                $permissionColumn->execute();
                if (!(int)$permissionColumn->fetchColumn()) clinic_redirect('Run database_sql/clinic_represented_posts_migration.sql before publishing represented requests.', 'error', 'requests');
            }
            if ($requestId) {
                $accepted = $pdo->prepare("SELECT COUNT(*) FROM request_responses WHERE request_id=:id AND status='accepted'");
                $accepted->execute([':id'=>$requestId]);
                if ((int)$accepted->fetchColumn() > 0) clinic_redirect('Requests with an accepted response cannot be edited. Update the request status instead.', 'error', 'requests');
                $update = $pdo->prepare("UPDATE blood_requests SET blood_type=:blood_type,units=:units,hospital=:hospital,location=:location,needed_by=:needed_by,urgency=:urgency,details=:details WHERE id=:id AND requester_id=:user_id AND status='open'");
                $update->execute([':blood_type'=>$bloodType,':units'=>$units,':hospital'=>$hospital,':location'=>$location,':needed_by'=>$neededBy,':urgency'=>$urgency,':details'=>$details,':id'=>$requestId,':user_id'=>$userId]);
                $exists = $pdo->prepare("SELECT 1 FROM blood_requests WHERE id=:id AND requester_id=:user_id"); $exists->execute([':id'=>$requestId,':user_id'=>$userId]); if (!$exists->fetchColumn()) clinic_redirect('Only your open requests can be edited.', 'error', 'requests');
            } else {
                $insert = $pdo->prepare("INSERT INTO blood_requests (requester_id,blood_type,units,hospital,location,needed_by,urgency,details,representation_authorized_at) VALUES (:requester_id,:blood_type,:units,:hospital,:location,:needed_by,:urgency,:details,NOW())");
                $insert->execute([':requester_id'=>$userId,':blood_type'=>$bloodType,':units'=>$units,':hospital'=>$hospital,':location'=>$location,':needed_by'=>$neededBy,':urgency'=>$urgency,':details'=>$details]);
            }
            clinic_redirect($requestId ? 'Blood request updated.' : 'Blood request published.', 'success', 'requests');

        case 'clinic_offer_create':
        case 'clinic_offer_edit':
            $postId = $action === 'clinic_offer_edit' ? filter_var($_POST['post_id'] ?? null, FILTER_VALIDATE_INT) : null;
            if ($action === 'clinic_offer_edit' && !$postId) clinic_redirect('Choose a valid donation offer to edit.', 'error', 'donation-offers');
            $content = trim($_POST['content'] ?? '');
            $bloodType = $_POST['donation_blood_type'] ?? '';
            $location = trim($_POST['donation_location'] ?? '');
            $availableDateValue = $_POST['donation_available_date'] ?? '';
            $availableTimeValue = $_POST['donation_available_time'] ?? '';
            if ($content === '' || strlen($content)>1000 || !in_array($bloodType,['A+','A-','B+','B-','AB+','AB-','O+','O-'],true) || $location === '' || strlen($location)>120) clinic_redirect('Review the donation offer details and try again.', 'error', 'donation-offers');
            if (!$postId && ($_POST['represented_person_permission'] ?? '') !== '1') clinic_redirect('Confirm that the potential donor gave permission for this offer.', 'error', 'donation-offers');
            $availableDate = null;
            if ($availableDateValue !== '') { $availableDate = DateTime::createFromFormat('!Y-m-d', $availableDateValue); if (!$availableDate || $availableDate->format('Y-m-d') !== $availableDateValue || $availableDateValue < date('Y-m-d')) clinic_redirect('Choose a valid availability date today or later.', 'error', 'donation-offers'); }
            $availableTime = null;
            if ($availableTimeValue !== '') { $availableTime = DateTime::createFromFormat('!H:i', $availableTimeValue); if (!$availableTime || $availableTime->format('H:i') !== $availableTimeValue) clinic_redirect('Choose a valid availability time.', 'error', 'donation-offers'); }
            $donationColumnStmt = $pdo->prepare("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='community_posts'");
            $donationColumnStmt->execute();
            $donationColumns = array_fill_keys($donationColumnStmt->fetchAll(PDO::FETCH_COLUMN), true);
            foreach (['donation_blood_type','donation_location','donation_available_date','representation_authorized_at'] as $column) if (empty($donationColumns[$column])) clinic_redirect('Run the donation offer and clinic representation migrations before publishing.', 'error', 'donation-offers');
            if ($postId) {
                $owned = $pdo->prepare("SELECT 1 FROM community_posts WHERE id=:id AND user_id=:user_id AND post_type='donation' AND COALESCE(donation_status,'available')<>'completed'");
                if (empty($donationColumns['donation_status'])) $owned = $pdo->prepare("SELECT 1 FROM community_posts WHERE id=:id AND user_id=:user_id AND post_type='donation'");
                $owned->execute([':id'=>$postId,':user_id'=>$userId]);
                if (!$owned->fetchColumn()) clinic_redirect('Only your non-completed donation offers can be edited.', 'error', 'donation-offers');
                $timeSql = !empty($donationColumns['donation_available_time']) ? ',donation_available_time=:available_time' : '';
                $update = $pdo->prepare("UPDATE community_posts SET content=:content,donation_blood_type=:blood_type,donation_location=:location,donation_available_date=:available_date{$timeSql} WHERE id=:id AND user_id=:user_id AND post_type='donation'");
                $values = [':content'=>$content,':blood_type'=>$bloodType,':location'=>$location,':available_date'=>$availableDate ? $availableDate->format('Y-m-d') : null,':id'=>$postId,':user_id'=>$userId];
                if (!empty($donationColumns['donation_available_time'])) $values[':available_time'] = $availableTime ? $availableTime->format('H:i:s') : null;
                $update->execute($values);
                clinic_redirect('Donation offer updated.', 'success', 'donation-offers');
            }
            $timeColumn = !empty($donationColumns['donation_available_time']) ? ',donation_available_time' : '';
            $timeValue = !empty($donationColumns['donation_available_time']) ? ',:available_time' : '';
            $insert = $pdo->prepare("INSERT INTO community_posts (user_id,post_type,content,event_date,donation_blood_type,donation_location,donation_available_date,representation_authorized_at{$timeColumn}) VALUES (:user_id,'donation',:content,NULL,:blood_type,:location,:available_date,NOW(){$timeValue})");
            $insertValues = [':user_id'=>$userId,':content'=>$content,':blood_type'=>$bloodType,':location'=>$location,':available_date'=>$availableDate ? $availableDate->format('Y-m-d') : null];
            if (!empty($donationColumns['donation_available_time'])) $insertValues[':available_time'] = $availableTime ? $availableTime->format('H:i:s') : null;
            $insert->execute($insertValues);
            clinic_redirect('Donation offer published.', 'success', 'donation-offers');

        case 'clinic_offer_status':
            $postId = filter_var($_POST['post_id'] ?? null, FILTER_VALIDATE_INT);
            $status = $_POST['status'] ?? '';
            if (!$postId || !in_array($status,['available','unavailable','completed'],true)) clinic_redirect('Choose a valid donation offer status.', 'error', 'donation-offers');
            $statusColumn = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='community_posts' AND COLUMN_NAME='donation_status'");
            $statusColumn->execute();
            if (!(int)$statusColumn->fetchColumn()) clinic_redirect('Run database_sql/donation_offer_status_migration.sql to manage offer status.', 'error', 'donation-offers');
            $completedColumn = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='community_posts' AND COLUMN_NAME='donation_completed_at'");
            $completedColumn->execute();
            $completionAvailable = (int)$completedColumn->fetchColumn() > 0;
            $ownedOffer = $pdo->prepare("SELECT 1 FROM community_posts WHERE id=:id AND user_id=:user_id AND post_type='donation'");
            $ownedOffer->execute([':id'=>$postId,':user_id'=>$userId]);
            if (!$ownedOffer->fetchColumn()) clinic_redirect('That donation offer is not yours.', 'error', 'donation-offers');
            $completionSql = $completionAvailable ? ',donation_completed_at=IF(:status_check=\'completed\',COALESCE(donation_completed_at,NOW()),NULL)' : '';
            $update = $pdo->prepare("UPDATE community_posts SET donation_status=:status{$completionSql} WHERE id=:id AND user_id=:user_id AND post_type='donation'");
            $values = [':status'=>$status,':id'=>$postId,':user_id'=>$userId];
            if ($completionAvailable) $values[':status_check'] = $status;
            $update->execute($values);
            clinic_redirect('Donation offer status updated.', 'success', 'donation-offers');

        case 'clinic_request_status':
            $requestId = filter_var($_POST['request_id'] ?? null, FILTER_VALIDATE_INT); $status = $_POST['status'] ?? '';
            if (!$requestId || !in_array($status,['fulfilled','cancelled'],true)) clinic_redirect('Choose a valid status.', 'error', 'requests');
            $statusUpdate = $pdo->prepare("UPDATE blood_requests SET status=:status WHERE id=:id AND requester_id=:requester_id AND status='open'");
            $statusUpdate->execute([':status'=>$status,':id'=>$requestId,':requester_id'=>$userId]);
            if (!$statusUpdate->rowCount()) clinic_redirect('Only your open requests can change status.', 'error', 'requests');
            clinic_redirect('Request status updated.', 'success', 'requests');

        case 'clinic_request_delete':
            $requestId = filter_var($_POST['request_id'] ?? null, FILTER_VALIDATE_INT);
            if (!$requestId) clinic_redirect('Choose a valid request to delete.', 'error', 'requests');
            $ownedRequest = $pdo->prepare('SELECT id FROM blood_requests WHERE id=:id AND requester_id=:owner');
            $ownedRequest->execute([':id'=>$requestId, ':owner'=>$userId]);
            if (!$ownedRequest->fetchColumn()) clinic_redirect('That request is not managed by your facility.', 'error', 'requests');
            $responseCount = $pdo->prepare('SELECT COUNT(*) FROM request_responses WHERE request_id=:id');
            $responseCount->execute([':id'=>$requestId]);
            if ((int)$responseCount->fetchColumn() > 0) clinic_redirect('This request has responses, so it cannot be deleted. Update its status to close it.', 'error', 'requests');
            $pdo->prepare('DELETE FROM blood_requests WHERE id=:id AND requester_id=:owner')->execute([':id'=>$requestId, ':owner'=>$userId]);
            clinic_redirect('Blood request deleted.', 'success', 'requests');

        case 'clinic_offer_delete':
            $postId = filter_var($_POST['post_id'] ?? null, FILTER_VALIDATE_INT);
            if (!$postId) clinic_redirect('Choose a valid donation offer to delete.', 'error', 'donation-offers');
            $offerStatusColumn = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='community_posts' AND COLUMN_NAME='donation_status'");
            $offerStatusColumn->execute();
            $statusSelect = (int)$offerStatusColumn->fetchColumn() > 0 ? "COALESCE(donation_status,'available')" : "'available'";
            $ownedOffer = $pdo->prepare("SELECT id,{$statusSelect} FROM community_posts WHERE id=:id AND user_id=:owner AND post_type='donation'");
            $ownedOffer->execute([':id'=>$postId, ':owner'=>$userId]);
            $offerStatus = $ownedOffer->fetch(PDO::FETCH_NUM);
            if (!$offerStatus) clinic_redirect('That donation offer is not managed by your facility.', 'error', 'donation-offers');
            if ($offerStatus[1] === 'completed') clinic_redirect('Completed offers are retained as donation records.', 'error', 'donation-offers');
            $messageTable = $pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='donation_offer_messages'");
            $messageTable->execute();
            if ((int)$messageTable->fetchColumn() > 0) {
                $messageCount = $pdo->prepare('SELECT COUNT(*) FROM donation_offer_messages WHERE post_id=:id');
                $messageCount->execute([':id'=>$postId]);
                if ((int)$messageCount->fetchColumn() > 0) clinic_redirect('This offer has conversation history, so it cannot be deleted. Mark it unavailable instead.', 'error', 'donation-offers');
            }
            $pdo->prepare("DELETE FROM community_posts WHERE id=:id AND user_id=:owner AND post_type='donation'")->execute([':id'=>$postId, ':owner'=>$userId]);
            clinic_redirect('Donation offer deleted.', 'success', 'donation-offers');

        case 'clinic_profile_update':
            $contact = trim($_POST['contact_number'] ?? ''); $officialEmail = trim($_POST['official_email'] ?? ''); $representative = trim($_POST['authorized_representative'] ?? ''); $description = trim($_POST['description'] ?? '');
            if ($contact === '' || strlen($contact)>30 || !filter_var($officialEmail,FILTER_VALIDATE_EMAIL) || strlen($officialEmail)>255 || $representative === '' || strlen($representative)>160 || strlen($description)>1000) clinic_redirect('Enter valid contact and representative details.', 'error', 'profile');
            $profileUpdate = $pdo->prepare('UPDATE clinic_profiles SET contact_number=:contact,official_email=:email,authorized_representative=:representative,description=:description WHERE user_id=:user_id');
            $profileUpdate->execute([':contact'=>$contact,':email'=>$officialEmail,':representative'=>$representative,':description'=>$description,':user_id'=>$userId]);
            $pdo->prepare('UPDATE uinfo SET phone=:phone WHERE id=:id AND role=\'clinic\'')->execute([':phone'=>$contact,':id'=>$userId]);
            clinic_redirect('Clinic contact profile updated.', 'success', 'profile');

        case 'clinic_password_change':
            $current = $_POST['current_password'] ?? ''; $new = $_POST['new_password'] ?? ''; $confirm = $_POST['confirm_password'] ?? '';
            if (strlen($new)<12 || $new !== $confirm) clinic_redirect('New passwords must match and contain at least 12 characters.', 'error', 'settings');
            $account = $pdo->prepare("SELECT password FROM uinfo WHERE id=:id AND role='clinic'"); $account->execute([':id'=>$userId]); $hash=$account->fetchColumn();
            if (!$hash || !password_verify($current,$hash)) clinic_redirect('Current password did not match.', 'error', 'settings');
            $pdo->prepare("UPDATE uinfo SET password=:password,must_change_password=0 WHERE id=:id AND role='clinic'")->execute([':password'=>password_hash($new,PASSWORD_DEFAULT),':id'=>$userId]);
            $_SESSION['must_change_password']=0;
            clinic_redirect('Password updated successfully.', 'success', 'overview');

        default:
            clinic_redirect('Choose a valid clinic action.', 'error');
    }
} catch (PDOException $e) {
    error_log($e->getMessage());
    clinic_redirect('Unable to complete that action. Check the database migrations and try again.', 'error');
}
