<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');

function map_json_response(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function map_numeric_parameter(string $name): ?float
{
    if (!array_key_exists($name, $_GET) || !is_string($_GET[$name]) || $_GET[$name] === '' || !is_numeric($_GET[$name])) {
        return null;
    }
    $value = (float) $_GET[$name];
    return is_finite($value) ? $value : null;
}

if (!is_logged_in()) {
    map_json_response(401, ['success' => false, 'error' => ['code' => 'unauthenticated', 'message' => 'Sign in to access map data.']]);
}

$requestedType = $_GET['type'] ?? 'all';
if (!is_string($requestedType) || !in_array($requestedType, ['all', 'request', 'donation'], true)) {
    map_json_response(400, ['success' => false, 'error' => ['code' => 'invalid_type', 'message' => 'Type must be all, request, or donation.']]);
}

$limitValue = $_GET['limit'] ?? '300';
$offsetValue = $_GET['offset'] ?? '0';
if (!is_string($limitValue) || !ctype_digit($limitValue) || (int) $limitValue < 1 || (int) $limitValue > 500 || !is_string($offsetValue) || !ctype_digit($offsetValue) || (int) $offsetValue > 100000) {
    map_json_response(400, ['success' => false, 'error' => ['code' => 'invalid_pagination', 'message' => 'Limit must be between 1 and 500 and offset must be a non-negative integer.']]);
}
$limit = (int) $limitValue;
$offset = (int) $offsetValue;

$boundNames = ['min_lat', 'min_lng', 'max_lat', 'max_lng'];
$providedBounds = array_filter($boundNames, static fn(string $name): bool => array_key_exists($name, $_GET));
$mapBounds = null;
if ($providedBounds) {
    if (count($providedBounds) !== 4) {
        map_json_response(400, ['success' => false, 'error' => ['code' => 'invalid_bounds', 'message' => 'Provide all four bounding box values.']]);
    }
    $mapBounds = [];
    foreach ($boundNames as $name) {
        $mapBounds[$name] = map_numeric_parameter($name);
        if ($mapBounds[$name] === null) {
            map_json_response(400, ['success' => false, 'error' => ['code' => 'invalid_bounds', 'message' => 'Bounding box values must be finite numbers.']]);
        }
    }
    if ($mapBounds['min_lat'] < 4.0 || $mapBounds['max_lat'] > 21.7 || $mapBounds['min_lng'] < 116.0 || $mapBounds['max_lng'] > 127.5 || $mapBounds['min_lat'] >= $mapBounds['max_lat'] || $mapBounds['min_lng'] >= $mapBounds['max_lng']) {
        map_json_response(400, ['success' => false, 'error' => ['code' => 'invalid_bounds', 'message' => 'Bounding box must be ordered and within the Philippines.']]);
    }
}

try {
    $pdo = (new Database())->getConnection();
    $userId = (int) current_user_id();
    $role = current_role();
    if (!in_array($role, ['user', 'admin', 'clinic'], true)) {
        map_json_response(403, ['success' => false, 'error' => ['code' => 'forbidden', 'message' => 'This account cannot access map data.']]);
    }
    $account = $pdo->prepare('SELECT role, account_status, must_change_password, fname, lname, phone, btype, city FROM uinfo WHERE id = :id LIMIT 1');
    $account->execute([':id' => $userId]);
    $accountRow = $account->fetch();
    if (!$accountRow || $accountRow['role'] !== $role || $accountRow['account_status'] !== 'active') {
        map_json_response(403, ['success' => false, 'error' => ['code' => 'inactive_account', 'message' => 'This account is not active.']]);
    }
    if ($role === 'clinic' && (int) $accountRow['must_change_password'] === 1) {
        map_json_response(403, ['success' => false, 'error' => ['code' => 'password_change_required', 'message' => 'Change your temporary password before accessing map data.']]);
    }
    if ($role === 'user' && (
        trim((string) $accountRow['fname']) === '' || trim((string) $accountRow['lname']) === '' ||
        trim((string) $accountRow['phone']) === '' || trim((string) $accountRow['city']) === '' ||
        trim((string) $accountRow['btype']) === ''
    )) {
        map_json_response(403, ['success' => false, 'error' => ['code' => 'profile_completion_required', 'message' => 'Complete your profile before accessing map data.']]);
    }

    $requests = [];
    $offers = [];
    $boundsSqlRequest = '';
    $boundsSqlOffer = '';
    $parameters = [];
    if ($mapBounds !== null) {
        $boundsSqlRequest = ' AND ROUND(br.latitude, 2) BETWEEN :min_lat AND :max_lat AND ROUND(br.longitude, 2) BETWEEN :min_lng AND :max_lng';
        $boundsSqlOffer = ' AND ROUND(cp.donation_latitude, 2) BETWEEN :min_lat AND :max_lat AND ROUND(cp.donation_longitude, 2) BETWEEN :min_lng AND :max_lng';
        $parameters = [':min_lat' => $mapBounds['min_lat'], ':max_lat' => $mapBounds['max_lat'], ':min_lng' => $mapBounds['min_lng'], ':max_lng' => $mapBounds['max_lng']];
    }

    if (in_array($requestedType, ['all', 'request'], true)) {
        $sql = "SELECT br.id, br.requester_id = :viewer_id AS is_owner, br.blood_type, br.units, br.location AS province, br.city, br.latitude, br.longitude, br.urgency, br.status, br.needed_by, u.role AS publisher_role, cp.facility_name, cp.is_verified
                FROM blood_requests br
                JOIN uinfo u ON u.id = br.requester_id
                LEFT JOIN clinic_profiles cp ON cp.user_id = u.id AND u.role = 'clinic'
                WHERE u.role IN ('user','clinic') AND u.account_status = 'active'
                  AND COALESCE(br.details, '') NOT LIKE '%map-api-test-%'
                  AND br.status = 'open' AND br.needed_by >= CURDATE()
                  AND br.latitude BETWEEN 4.0 AND 21.7 AND br.longitude BETWEEN 116.0 AND 127.5{$boundsSqlRequest}
                ORDER BY FIELD(br.urgency,'emergency','urgent','routine'), br.needed_by ASC, br.created_at DESC
                LIMIT :limit OFFSET :offset";
        $stmt = $pdo->prepare($sql);
        foreach ($parameters as $name => $value) $stmt->bindValue($name, $value, PDO::PARAM_STR);
        $stmt->bindValue(':viewer_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        foreach ($stmt->fetchAll() as $row) {
            $latitude = (float) $row['latitude'];
            $longitude = (float) $row['longitude'];
            if (!is_finite($latitude) || !is_finite($longitude) || $latitude < 4.0 || $latitude > 21.7 || $longitude < 116.0 || $longitude > 127.5) continue;
            $requests[] = [
                'id' => (int) $row['id'], 'type' => 'request', 'blood_type' => $row['blood_type'], 'units' => (int) $row['units'],
                'province' => $row['province'], 'city' => $row['city'], 'location' => trim($row['city'] . ', ' . $row['province'], ', '),
                'latitude' => round($latitude, 2), 'longitude' => round($longitude, 2), 'urgency' => $row['urgency'],
                'status' => $row['status'], 'needed_by' => $row['needed_by'], 'publisher_type' => $row['publisher_role'],
                'is_owner' => (bool) $row['is_owner'],
                'facility_name' => $row['publisher_role'] === 'clinic' ? $row['facility_name'] : null,
                'facility_verified' => $row['publisher_role'] === 'clinic' ? (bool) $row['is_verified'] : false,
            ];
        }
    }

    if (in_array($requestedType, ['all', 'donation'], true)) {
        $sql = "SELECT cp.id, cp.user_id = :viewer_id AS is_owner, cp.donation_blood_type AS blood_type, cp.donation_province AS province, cp.donation_city AS city,
                       cp.donation_latitude AS latitude, cp.donation_longitude AS longitude, cp.donation_status, cp.donation_available_date,
                       u.role AS publisher_role, clinic.facility_name, clinic.is_verified
                FROM community_posts cp
                JOIN uinfo u ON u.id = cp.user_id
                LEFT JOIN clinic_profiles clinic ON clinic.user_id = u.id AND u.role = 'clinic'
                WHERE cp.post_type = 'donation' AND cp.status = 'published' AND cp.donation_status = 'available'
                  AND cp.content NOT LIKE '%map-api-test-%'
                  AND (cp.donation_available_date IS NULL OR cp.donation_available_date >= CURDATE())
                  AND u.role IN ('user','clinic') AND u.account_status = 'active'
                  AND cp.donation_latitude BETWEEN 4.0 AND 21.7 AND cp.donation_longitude BETWEEN 116.0 AND 127.5{$boundsSqlOffer}
                ORDER BY cp.donation_available_date IS NULL ASC, cp.donation_available_date ASC, cp.created_at DESC
                LIMIT :limit OFFSET :offset";
        $stmt = $pdo->prepare($sql);
        foreach ($parameters as $name => $value) $stmt->bindValue($name, $value, PDO::PARAM_STR);
        $stmt->bindValue(':viewer_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        foreach ($stmt->fetchAll() as $row) {
            $latitude = (float) $row['latitude'];
            $longitude = (float) $row['longitude'];
            if (!is_finite($latitude) || !is_finite($longitude) || $latitude < 4.0 || $latitude > 21.7 || $longitude < 116.0 || $longitude > 127.5) continue;
            $offers[] = [
                'id' => (int) $row['id'], 'type' => 'donation', 'blood_type' => $row['blood_type'],
                'province' => $row['province'], 'city' => $row['city'], 'location' => trim(($row['city'] ?? '') . ', ' . ($row['province'] ?? ''), ', '),
                'latitude' => round($latitude, 2), 'longitude' => round($longitude, 2), 'status' => 'available',
                'available_date' => $row['donation_available_date'], 'publisher_type' => $row['publisher_role'],
                'is_owner' => (bool) $row['is_owner'],
                'facility_name' => $row['publisher_role'] === 'clinic' ? $row['facility_name'] : null,
                'facility_verified' => $row['publisher_role'] === 'clinic' ? (bool) $row['is_verified'] : false,
            ];
        }
    }

    map_json_response(200, ['success' => true, 'data' => ['requests' => $requests, 'donations' => $offers, 'count' => count($requests) + count($offers), 'coordinate_precision' => 'approximate (rounded to 2 decimal places)']]);
} catch (Throwable $error) {
    error_log('BLife PH map data API error: ' . $error->getMessage());
    map_json_response(500, ['success' => false, 'error' => ['code' => 'server_error', 'message' => 'Map data is temporarily unavailable.']]);
}
