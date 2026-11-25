<?php
session_start();
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/member_session_security.php';
require_login('../login.php');
if (current_role() !== 'user') { http_response_code(403); exit('Forbidden'); }
try {
    $pdo = (new Database())->getConnection();
    if (!member_session_is_active($pdo, (int)current_user_id())) { http_response_code(401); exit('Session expired'); }
    $id = (int)current_user_id();
    $profile = $pdo->prepare("SELECT id, fname, lname, email, btype, phone, city, municipality, barangay, donor_available, created_at FROM uinfo WHERE id = :id AND role = 'user'");
    $profile->execute([':id' => $id]);
    $account = $profile->fetch(PDO::FETCH_ASSOC);
    if (!$account) { http_response_code(404); exit('Account not found'); }
    $export = ['account' => $account, 'exported_at' => gmdate('c'), 'community_posts' => [], 'blood_requests' => [], 'request_responses' => [], 'request_messages' => [], 'donation_offer_messages' => [], 'notifications' => []];
    $queries = [
        'community_posts' => ['SELECT id, post_type, content, event_date, created_at FROM community_posts WHERE user_id = :id ORDER BY created_at DESC', $id],
        'blood_requests' => ['SELECT id, blood_type, units, hospital, location, urgency, needed_by, details, status, created_at FROM blood_requests WHERE requester_id = :id ORDER BY created_at DESC', $id],
        'request_responses' => ['SELECT rr.id, rr.request_id, rr.message, rr.status, rr.created_at FROM request_responses rr WHERE rr.user_id = :id ORDER BY rr.created_at DESC', $id],
        'notifications' => ['SELECT id, notification_type, target_id, message, is_read, created_at FROM member_notifications WHERE user_id = :id ORDER BY created_at DESC', $id]
    ];
    foreach ($queries as $key => [$sql, $ownerId]) { $statement = $pdo->prepare($sql); $statement->execute([':id' => $ownerId]); $export[$key] = $statement->fetchAll(PDO::FETCH_ASSOC); }
    $tableCheck = $pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name");
    $tableCheck->execute([':table_name' => 'request_response_messages']);
    if ((int)$tableCheck->fetchColumn() > 0) {
        $messages = $pdo->prepare('SELECT m.id, m.response_id, m.sender_id, m.message, m.created_at FROM request_response_messages m JOIN request_responses rr ON rr.id = m.response_id JOIN blood_requests br ON br.id = rr.request_id WHERE m.sender_id = :sender_id OR rr.user_id = :response_owner OR br.requester_id = :requester_id ORDER BY m.created_at');
        $messages->execute([':sender_id' => $id, ':response_owner' => $id, ':requester_id' => $id]);
        $export['request_messages'] = $messages->fetchAll(PDO::FETCH_ASSOC);
    }
    $tableCheck->execute([':table_name' => 'donation_offer_messages']);
    if ((int)$tableCheck->fetchColumn() > 0) {
        $messages = $pdo->prepare('SELECT m.id, m.post_id, m.seeker_id, m.sender_id, m.message, m.urgency, m.created_at FROM donation_offer_messages m JOIN community_posts p ON p.id = m.post_id WHERE m.sender_id = :sender_id OR m.seeker_id = :seeker_id OR p.user_id = :post_owner ORDER BY m.created_at');
        $messages->execute([':sender_id' => $id, ':seeker_id' => $id, ':post_owner' => $id]);
        $export['donation_offer_messages'] = $messages->fetchAll(PDO::FETCH_ASSOC);
    }
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="blife-ph-account-data-' . $id . '.json"');
    header('Cache-Control: no-store, private');
    echo json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
} catch (Throwable $error) { http_response_code(500); header('Content-Type: text/plain; charset=utf-8'); echo 'Unable to export account data.'; }
