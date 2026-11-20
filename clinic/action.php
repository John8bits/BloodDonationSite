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
            $bloodType = $_POST['blood_type'] ?? '';
            $units = filter_var($_POST['units'] ?? null, FILTER_VALIDATE_INT);
            $hospital = trim($_POST['hospital'] ?? '');
            $location = trim($_POST['location'] ?? '');
            $neededBy = $_POST['needed_by'] ?? '';
            $urgency = $_POST['urgency'] ?? '';
            $details = trim($_POST['details'] ?? '');
            $date = DateTime::createFromFormat('!Y-m-d', $neededBy);
            if (!in_array($bloodType,['A+','A-','B+','B-','AB+','AB-','O+','O-'],true) || !$units || $units < 1 || $units > 20 || $hospital === '' || strlen($hospital)>160 || $location === '' || strlen($location)>160 || !$date || $date->format('Y-m-d') !== $neededBy || $neededBy < date('Y-m-d') || !in_array($urgency,['routine','urgent','emergency'],true) || strlen($details)>1000) clinic_redirect('Review the blood request details and try again.', 'error', $requestId ? 'requests' : 'requests');
            if ($requestId) {
                $accepted = $pdo->prepare("SELECT COUNT(*) FROM request_responses WHERE request_id=:id AND status='accepted'");
                $accepted->execute([':id'=>$requestId]);
                if ((int)$accepted->fetchColumn() > 0) clinic_redirect('Requests with an accepted response cannot be edited. Update the request status instead.', 'error', 'requests');
                $update = $pdo->prepare("UPDATE blood_requests SET blood_type=:blood_type,units=:units,hospital=:hospital,location=:location,needed_by=:needed_by,urgency=:urgency,details=:details WHERE id=:id AND requester_id=:user_id AND status='open'");
                $update->execute([':blood_type'=>$bloodType,':units'=>$units,':hospital'=>$hospital,':location'=>$location,':needed_by'=>$neededBy,':urgency'=>$urgency,':details'=>$details,':id'=>$requestId,':user_id'=>$userId]);
                $exists = $pdo->prepare("SELECT 1 FROM blood_requests WHERE id=:id AND requester_id=:user_id"); $exists->execute([':id'=>$requestId,':user_id'=>$userId]); if (!$exists->fetchColumn()) clinic_redirect('Only your open requests can be edited.', 'error', 'requests');
            } else {
                $insert = $pdo->prepare("INSERT INTO blood_requests (requester_id,blood_type,units,hospital,location,needed_by,urgency,details) VALUES (:requester_id,:blood_type,:units,:hospital,:location,:needed_by,:urgency,:details)");
                $insert->execute([':requester_id'=>$userId,':blood_type'=>$bloodType,':units'=>$units,':hospital'=>$hospital,':location'=>$location,':needed_by'=>$neededBy,':urgency'=>$urgency,':details'=>$details]);
            }
            clinic_redirect($requestId ? 'Blood request updated.' : 'Blood request published.', 'success', 'requests');

        case 'clinic_request_status':
            $requestId = filter_var($_POST['request_id'] ?? null, FILTER_VALIDATE_INT); $status = $_POST['status'] ?? '';
            if (!$requestId || !in_array($status,['fulfilled','cancelled'],true)) clinic_redirect('Choose a valid status.', 'error', 'requests');
            $statusUpdate = $pdo->prepare("UPDATE blood_requests SET status=:status WHERE id=:id AND requester_id=:requester_id AND status='open'");
            $statusUpdate->execute([':status'=>$status,':id'=>$requestId,':requester_id'=>$userId]);
            if (!$statusUpdate->rowCount()) clinic_redirect('Only your open requests can change status.', 'error', 'requests');
            clinic_redirect('Request status updated.', 'success', 'requests');

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
