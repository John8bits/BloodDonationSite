<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=86400');
header('X-Content-Type-Options: nosniff');

function location_api_response(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');
    location_api_response(405, ['success' => false, 'error' => 'Method not allowed.']);
}

require_once __DIR__ . '/../includes/philippine_psgc_locations.php';
$areas = blife_psgc_data()['areas'] ?? [];
$province = isset($_GET['province']) && is_string($_GET['province']) ? trim($_GET['province']) : '';
$city = isset($_GET['city']) && is_string($_GET['city']) ? trim($_GET['city']) : '';
if (strlen($province) > 120 || strlen($city) > 120) location_api_response(400, ['success' => false, 'error' => 'Location name is too long.']);

if ($province === '') {
    $names = array_keys($areas);
    natcasesort($names);
    location_api_response(200, ['success' => true, 'data' => ['provinces' => array_values($names)]]);
}

$area = blife_psgc_area($province);
if ($area === null) location_api_response(422, ['success' => false, 'error' => 'Select a valid province or region.']);

if ($city === '') {
    $cities = array_map(static fn(array $record): string => (string) ($record[1] ?? ''), $area['cities'] ?? []);
    natcasesort($cities);
    location_api_response(200, ['success' => true, 'data' => ['province' => $area['name'], 'cities' => array_values($cities)]]);
}

$cityRecord = blife_psgc_city($area['name'], $city);
if ($cityRecord === null) location_api_response(422, ['success' => false, 'error' => 'Select a valid city or municipality for this province.']);
$barangays = array_map(static fn(array $record): array => [
    'name' => (string) ($record[1] ?? ''),
    'has_map_point' => is_numeric($record[2] ?? null) && is_numeric($record[3] ?? null),
], $cityRecord['barangays']);
usort($barangays, static fn(array $a, array $b): int => strnatcasecmp($a['name'], $b['name']));
location_api_response(200, ['success' => true, 'data' => ['province' => $area['name'], 'city' => $cityRecord['name'], 'barangays' => $barangays]]);
