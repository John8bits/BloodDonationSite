<?php
declare(strict_types=1);

/**
 * Generate a compact local PSGC hierarchy and approximate barangay map points.
 * Usage: php tools/generate_philippine_psgc_locations.php <extracted-ph-psgc>/psgc
 * Source snapshot: Tenasia/ph-psgc 2026-Q2 (PSA PSGC names/codes; barangay
 * points derived from NAMRIA/PSA boundaries via philippines-json-maps).
 */
$source = rtrim($argv[1] ?? '', '/\\');
if ($source === '' || !is_file($source . '/index.json')) {
    fwrite(STDERR, "Pass the extracted PSGC psgc directory as the first argument.\n");
    exit(2);
}

function location_key(string $value): string
{
    $value = trim($value);
    if (function_exists('iconv')) $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
    $value = strtolower($value);
    $value = preg_replace('/^(city of |city of |municipality of )/i', '', $value) ?? $value;
    return preg_replace('/[^a-z0-9]+/', '', $value) ?? '';
}

function read_json_file(string $path): array
{
    return json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
}

$index = read_json_file($source . '/index.json');
$centroids = read_json_file(__DIR__ . '/../config/philippine_location_centroids.json');
$centroidIndex = [];
foreach ($centroids['cities'] ?? [] as $item) {
    $centroidIndex[location_key((string) $item['province']) . '|' . location_key((string) $item['name'])] = [(float) $item['latitude'], (float) $item['longitude']];
}
$areas = [];
$provinceList = [];

foreach ($index['regions'] ?? [] as $region) {
    $isNcr = str_contains(strtolower((string) ($region['name'] ?? '')), 'national capital');
    $regionalProvinceNames = array_fill_keys(array_map(static fn(array $item): string => (string) $item['name'], $region['provinces'] ?? []), true);
    foreach ($region['provinces'] ?? [] as $province) {
        $provinceList[(string) $province['code']] = (string) $province['name'];
    }
    foreach ($region['provinces'] ?? [] as $province) {
        $code = (string) $province['code'];
        $data = read_json_file($source . '/areas/' . $code . '.json');
        $pointsPath = $source . '/points/' . $code . '.json';
        $points = is_file($pointsPath) ? read_json_file($pointsPath) : [];
        $provinceName = (string) $province['name'];
        $cityRows = [];
        foreach ($data['cities'] ?? [] as $city) {
            $cityCode = (string) $city['code'];
            $center = $points['c'][$cityCode] ?? $centroidIndex[location_key($provinceName) . '|' . location_key((string) $city['name'])] ?? null;
            $barangayRows = [];
            foreach ($city['barangays'] ?? [] as $barangay) {
                if (is_array($barangay)) {
                    $barangayCode = (string) ($barangay[0] ?? '');
                    $barangayName = (string) ($barangay[1] ?? '');
                } else {
                    if (!preg_match('/^(\d{10})\s+(.+)$/u', (string) $barangay, $match)) continue;
                    $barangayCode = $match[1];
                    $barangayName = $match[2];
                }
                if ($barangayCode === '' || $barangayName === '') continue;
                if ($provinceName === 'Bukidnon' && (string) $city['name'] === 'Cabanglasan' && $barangayName === 'Lambangan') $barangayName = 'Lambagan';
                $point = $points['b'][$barangayCode] ?? null;
                $barangayRows[] = [$barangayCode, $barangayName, is_array($point) ? (float) $point[0] : null, is_array($point) ? (float) $point[1] : null];
            }
            $cityRows[] = [$cityCode, (string) $city['name'], (string) ($city['type'] ?? 'city'), is_array($center) ? (float) $center[0] : null, is_array($center) ? (float) $center[1] : null, $barangayRows];
        }
        $areas[$provinceName] = ['code' => $code, 'cities' => $cityRows];
    }

    foreach ($region['independent'] ?? [] as $independent) {
        $code = (string) $independent['code'];
        $dataPath = $source . '/areas/' . $code . '.json';
        if (!is_file($dataPath)) continue;
        $data = read_json_file($dataPath);
        $pointsPath = $source . '/points/' . $code . '.json';
        $points = is_file($pointsPath) ? read_json_file($pointsPath) : [];
        $cityName = (string) ($data['name'] ?? $independent['name']);
        if ($isNcr) {
            $provinceName = 'Metro Manila (NCR)';
        } else {
            $cityKey = location_key($cityName);
            $provinceName = '';
            foreach ($centroids['cities'] ?? [] as $centroid) {
                if (location_key((string) $centroid['name']) === $cityKey && isset($regionalProvinceNames[(string) $centroid['province']])) {
                    $provinceName = (string) $centroid['province'];
                    break;
                }
            }
            if ($provinceName === '') $provinceName = 'Independent Cities';
        }
        if (!isset($areas[$provinceName])) $areas[$provinceName] = ['code' => '', 'cities' => []];
        foreach ($data['cities'] ?? [] as $city) {
            $cityCode = (string) $city['code'];
            $center = $points['c'][$cityCode] ?? null;
            $barangayRows = [];
            foreach ($city['barangays'] ?? [] as $barangay) {
                if (is_array($barangay)) {
                    $barangayCode = (string) ($barangay[0] ?? ''); $barangayName = (string) ($barangay[1] ?? '');
                } else {
                    if (!preg_match('/^(\d{10})\s+(.+)$/u', (string) $barangay, $match)) continue;
                    $barangayCode = $match[1]; $barangayName = $match[2];
                }
                if ($barangayCode === '' || $barangayName === '') continue;
                if ($provinceName === 'Bukidnon' && (string) $city['name'] === 'Cabanglasan' && $barangayName === 'Lambangan') $barangayName = 'Lambagan';
                $point = $points['b'][$barangayCode] ?? null;
                $barangayRows[] = [$barangayCode, $barangayName, is_array($point) ? (float) $point[0] : null, is_array($point) ? (float) $point[1] : null];
            }
            $cityRows = &$areas[$provinceName]['cities'];
            $cityExists = false;
            foreach ($cityRows as $existingCity) if ($existingCity[0] === $cityCode) { $cityExists = true; break; }
            if (!$cityExists) $cityRows[] = [$cityCode, (string) $city['name'], (string) ($city['type'] ?? 'city'), is_array($center) ? (float) $center[0] : null, is_array($center) ? (float) $center[1] : null, $barangayRows];
            unset($cityRows);
        }
    }
}

ksort($areas, SORT_NATURAL | SORT_FLAG_CASE);
$result = [
    'metadata' => [
        'source' => 'Philippine Statistics Authority, Philippine Standard Geographic Code; barangay map points from boundary centroids',
        'source_url' => 'https://psa.gov.ph/classification/psgc',
        'psgc_release' => '2026-Q3 (30 September 2026); 2026-Q2 source snapshot with the official Q3 barangay name correction applied',
        'coordinate_source' => 'Barangay boundary map points derived from NAMRIA/PSA administrative boundaries, via philippines-json-maps 4Q 2023',
        'coordinate_system' => 'WGS84 (EPSG:4326)',
        'approximate' => true,
        'license' => 'PSA attribution required; map-point source MIT; boundary source CC-BY-IGO',
    ],
    'areas' => $areas,
];
$output = __DIR__ . '/../config/philippine_psgc_locations.json';
file_put_contents($output, json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
echo 'Areas: ' . count($areas) . '; municipalities/cities: ' . array_sum(array_map(static fn(array $area): int => count($area['cities']), $areas)) . '; file bytes: ' . filesize($output) . "\n";
