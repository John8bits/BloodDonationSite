<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/philippine_map_locations.php';

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
    if (!array_key_exists($name, $_GET) || !is_string($_GET[$name]) || $_GET[$name] === '' || !is_numeric($_GET[$name])) return null;
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
    if (count($providedBounds) !== 4) map_json_response(400, ['success' => false, 'error' => ['code' => 'invalid_bounds', 'message' => 'Provide all four bounding box values.']]);
    $mapBounds = [];
    foreach ($boundNames as $name) {
        $mapBounds[$name] = map_numeric_parameter($name);
        if ($mapBounds[$name] === null) map_json_response(400, ['success' => false, 'error' => ['code' => 'invalid_bounds', 'message' => 'Bounding box values must be finite numbers.']]);
    }
    if ($mapBounds['min_lat'] < 4.0 || $mapBounds['max_lat'] > 21.7 || $mapBounds['min_lng'] < 114.0 || $mapBounds['max_lng'] > 127.5 || $mapBounds['min_lat'] >= $mapBounds['max_lat'] || $mapBounds['min_lng'] >= $mapBounds['max_lng']) {
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
    $account = $pdo->prepare('SELECT u.role, u.account_status, u.must_change_password, u.fname, u.lname, u.phone, u.btype, u.city AS profile_city, c.city AS facility_city, c.province AS facility_province FROM uinfo u LEFT JOIN clinic_profiles c ON c.user_id = u.id WHERE u.id = :id LIMIT 1');
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
        trim((string) $accountRow['phone']) === '' || trim((string) $accountRow['profile_city']) === '' ||
        trim((string) $accountRow['btype']) === ''
    )) {
        map_json_response(403, ['success' => false, 'error' => ['code' => 'profile_completion_required', 'message' => 'Complete your profile before accessing map data.']]);
    }

    $viewerLocation = null;
    if ($role === 'clinic') {
        $viewerLocation = blife_map_resolve_location(null, null, '', '', '', (string) $accountRow['facility_city'], (string) $accountRow['facility_province']);
    } elseif ($role === 'user') {
        $viewerLocation = blife_map_resolve_location(null, null, '', '', '', (string) $accountRow['profile_city']);
    }
    if ($viewerLocation !== null) {
        $viewerLocation['latitude'] = round((float) $viewerLocation['latitude'], 2);
        $viewerLocation['longitude'] = round((float) $viewerLocation['longitude'], 2);
        $viewerLocation['location'] = trim(($viewerLocation['city'] ?? '') . ', ' . ($viewerLocation['province'] ?? ''), ', ');
    }

    $items = [];
    $unmappedCount = 0;
    if (in_array($requestedType, ['all', 'request'], true)) {
        $sql = "SELECT br.id, br.requester_id = :viewer_id AS is_owner, br.blood_type, br.units, br.hospital, br.location AS province, br.city, br.latitude, br.longitude, br.urgency, br.status, br.needed_by,
                       u.role AS publisher_role, u.fname, u.lname, u.city AS profile_city, clinic.city AS facility_city, clinic.province AS facility_province, clinic.facility_name, clinic.is_verified
                FROM blood_requests br
                JOIN uinfo u ON u.id = br.requester_id
                LEFT JOIN clinic_profiles clinic ON clinic.user_id = u.id AND u.role = 'clinic'
                WHERE u.role IN ('user','clinic') AND u.account_status = 'active'
                  AND COALESCE(br.details, '') NOT LIKE '%map-api-test-%'
                  AND br.status = 'open' AND br.needed_by >= CURDATE()
                ORDER BY FIELD(br.urgency,'emergency','urgent','routine'), br.needed_by ASC, br.created_at DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':viewer_id' => $userId]);
        foreach ($stmt->fetchAll() as $row) {
            $location = blife_map_resolve_location(
                $row['latitude'], $row['longitude'], (string) ($row['city'] ?? ''), (string) ($row['province'] ?? ''), (string) ($row['province'] ?? ''),
                $row['publisher_role'] === 'clinic' ? (string) ($row['facility_city'] ?? '') : (string) ($row['profile_city'] ?? ''),
                $row['publisher_role'] === 'clinic' ? (string) ($row['facility_province'] ?? '') : ''
            );
            if ($location === null) { $unmappedCount++; continue; }
            $lat = round((float) $location['latitude'], 2); $lng = round((float) $location['longitude'], 2);
            if ($mapBounds !== null && ($lat < $mapBounds['min_lat'] || $lat > $mapBounds['max_lat'] || $lng < $mapBounds['min_lng'] || $lng > $mapBounds['max_lng'])) continue;
            $publisherName = $row['publisher_role'] === 'clinic'
                ? (string) ($row['facility_name'] ?? 'Clinic / Blood Bank')
                : trim((string) $row['fname'] . ' ' . (trim((string) $row['lname']) !== '' ? strtoupper(substr(trim((string) $row['lname']), 0, 1)) . '.' : ''));
            $items[] = [
                'id' => (int) $row['id'], 'type' => 'request', 'blood_type' => $row['blood_type'], 'units' => (int) $row['units'],
                'facility' => (string) ($row['hospital'] ?? ''), 'province' => $location['province'], 'city' => $location['city'],
                'location' => trim(($location['city'] ?? '') . ', ' . ($location['province'] ?? ''), ', '),
                'latitude' => $lat, 'longitude' => $lng, 'location_level' => $location['location_level'], 'approximate' => (bool) $location['approximate'], 'coordinate_source' => $location['coordinate_source'],
                'urgency' => $row['urgency'], 'status' => $row['status'], 'needed_by' => $row['needed_by'],
                'publisher_type' => $row['publisher_role'], 'publisher_name' => $publisherName, 'is_owner' => (bool) $row['is_owner'],
                'facility_name' => $row['publisher_role'] === 'clinic' ? $row['facility_name'] : null,
                'facility_verified' => $row['publisher_role'] === 'clinic' ? (bool) $row['is_verified'] : false,
            ];
        }
    }

    if (in_array($requestedType, ['all', 'donation'], true)) {
        $sql = "SELECT cp.id, cp.user_id = :viewer_id AS is_owner, cp.donation_blood_type AS blood_type, cp.donation_province AS province, cp.donation_city AS city,
                       cp.donation_location, cp.donation_latitude AS latitude, cp.donation_longitude AS longitude, cp.donation_status, cp.donation_available_date,
                       u.role AS publisher_role, u.fname, u.lname, u.city AS profile_city, clinic.city AS facility_city, clinic.province AS facility_province, clinic.facility_name, clinic.is_verified
                FROM community_posts cp
                JOIN uinfo u ON u.id = cp.user_id
                LEFT JOIN clinic_profiles clinic ON clinic.user_id = u.id AND u.role = 'clinic'
                WHERE cp.post_type = 'donation' AND cp.status = 'published' AND cp.donation_status = 'available'
                  AND cp.content NOT LIKE '%map-api-test-%'
                  AND (cp.donation_available_date IS NULL OR cp.donation_available_date >= CURDATE())
                  AND u.role IN ('user','clinic') AND u.account_status = 'active'
                ORDER BY cp.donation_available_date IS NULL ASC, cp.donation_available_date ASC, cp.created_at DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':viewer_id' => $userId]);
        foreach ($stmt->fetchAll() as $row) {
            $location = blife_map_resolve_location(
                $row['latitude'], $row['longitude'], (string) ($row['city'] ?? ''), (string) ($row['province'] ?? ''), (string) ($row['donation_location'] ?? ''),
                $row['publisher_role'] === 'clinic' ? (string) ($row['facility_city'] ?? '') : (string) ($row['profile_city'] ?? ''),
                $row['publisher_role'] === 'clinic' ? (string) ($row['facility_province'] ?? '') : ''
            );
            if ($location === null) { $unmappedCount++; continue; }
            $lat = round((float) $location['latitude'], 2); $lng = round((float) $location['longitude'], 2);
            if ($mapBounds !== null && ($lat < $mapBounds['min_lat'] || $lat > $mapBounds['max_lat'] || $lng < $mapBounds['min_lng'] || $lng > $mapBounds['max_lng'])) continue;
            $publisherName = $row['publisher_role'] === 'clinic'
                ? (string) ($row['facility_name'] ?? 'Clinic / Blood Bank')
                : trim((string) $row['fname'] . ' ' . (trim((string) $row['lname']) !== '' ? strtoupper(substr(trim((string) $row['lname']), 0, 1)) . '.' : ''));
            $items[] = [
                'id' => (int) $row['id'], 'type' => 'donation', 'blood_type' => $row['blood_type'],
                'province' => $location['province'], 'city' => $location['city'], 'location' => trim(($location['city'] ?? '') . ', ' . ($location['province'] ?? ''), ', '),
                'latitude' => $lat, 'longitude' => $lng, 'location_level' => $location['location_level'], 'approximate' => (bool) $location['approximate'], 'coordinate_source' => $location['coordinate_source'],
                'status' => 'available', 'available_date' => $row['donation_available_date'], 'publisher_type' => $row['publisher_role'],
                'publisher_name' => $publisherName, 'is_owner' => (bool) $row['is_owner'],
                'facility_name' => $row['publisher_role'] === 'clinic' ? $row['facility_name'] : null,
                'facility_verified' => $row['publisher_role'] === 'clinic' ? (bool) $row['is_verified'] : false,
            ];
        }
    }

    $total = count($items);
    $pageItems = array_slice($items, $offset, $limit);
    $requests = array_values(array_filter($pageItems, static fn(array $item): bool => $item['type'] === 'request'));
    $offers = array_values(array_filter($pageItems, static fn(array $item): bool => $item['type'] === 'donation'));
    map_json_response(200, ['success' => true, 'data' => [
        'requests' => $requests, 'donations' => $offers, 'count' => $total, 'unmapped_count' => $unmappedCount,
        'offset' => $offset, 'limit' => $limit, 'has_more' => $offset + $limit < $total,
        'coordinate_precision' => 'public map points are rounded to 2 decimal places',
        'viewer_location' => $viewerLocation === null ? null : [
            'latitude' => $viewerLocation['latitude'], 'longitude' => $viewerLocation['longitude'], 'location' => $viewerLocation['location'],
            'location_level' => $viewerLocation['location_level'], 'approximate' => $viewerLocation['approximate'],
        ],
    ]]);
} catch (Throwable $error) {
    error_log('BLife PH map data API error: ' . $error->getMessage());
    map_json_response(500, ['success' => false, 'error' => ['code' => 'server_error', 'message' => 'Map data is temporarily unavailable.']]);
}
