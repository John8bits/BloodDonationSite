<?php
declare(strict_types=1);

/**
 * Build config/philippine_location_centroids.json from the extracted `geojson`
 * directory in the low-resolution Philippines JSON Maps release.
 * Usage: php tools/generate_philippine_location_centroids.php <path-to-geojson>
 */
$root = $argv[1] ?? '';
if ($root === '' || !is_dir($root)) {
    fwrite(STDERR, "Pass the extracted geojson directory as the first argument.\n");
    exit(2);
}

function map_data_key(string $value): string
{
    return strtolower(preg_replace('/[^a-z0-9]+/i', '', trim($value)) ?? '');
}

function map_geometry_centroid(array $coordinates): ?array
{
    $polygons = ($coordinates && isset($coordinates[0][0]) && is_numeric($coordinates[0][0][0] ?? null))
        ? [$coordinates]
        : $coordinates;
    $weightedArea = $weightedLongitude = $weightedLatitude = 0.0;
    foreach ($polygons as $rings) {
        $ring = $rings[0] ?? [];
        if (count($ring) < 3) continue;
        $crossSum = $longitudeSum = $latitudeSum = 0.0;
        $pointCount = count($ring);
        for ($i = 0; $i < $pointCount; $i++) {
            $a = $ring[$i];
            $b = $ring[($i + 1) % $pointCount];
            $cross = (float) $a[0] * (float) $b[1] - (float) $b[0] * (float) $a[1];
            $crossSum += $cross;
            $longitudeSum += ((float) $a[0] + (float) $b[0]) * $cross;
            $latitudeSum += ((float) $a[1] + (float) $b[1]) * $cross;
        }
        if (abs($crossSum) < 1e-12) continue;
        $area = abs($crossSum / 2);
        $weightedArea += $area;
        $weightedLongitude += ($longitudeSum / (3 * $crossSum)) * $area;
        $weightedLatitude += ($latitudeSum / (3 * $crossSum)) * $area;
    }
    return $weightedArea > 0 ? [$weightedLatitude / $weightedArea, $weightedLongitude / $weightedArea] : null;
}

$provinceNames = [];
$provinces = [];
foreach (glob(rtrim($root, '/\\') . '/regions/lowres/*.json') as $file) {
    $collection = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
    foreach ($collection['features'] ?? [] as $feature) {
        $properties = $feature['properties'] ?? [];
        $name = (string) ($properties['adm2_en'] ?? '');
        $code = (string) ($properties['adm2_psgc'] ?? '');
        if ($name === '' || $code === '') continue;
        $provinceNames[$code] = $name;
        $center = map_geometry_centroid($feature['geometry']['coordinates'] ?? []);
        if ($center !== null) $provinces[map_data_key($name)] = ['name' => $name, 'latitude' => round($center[0], 5), 'longitude' => round($center[1], 5)];
    }
}

$cities = [];
foreach (glob(rtrim($root, '/\\') . '/provdists/lowres/*.json') as $file) {
    $collection = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
    foreach ($collection['features'] ?? [] as $feature) {
        $properties = $feature['properties'] ?? [];
        $name = (string) ($properties['adm3_en'] ?? '');
        $province = $provinceNames[(string) ($properties['adm2_psgc'] ?? '')] ?? '';
        if ($name === '' || $province === '') continue;
        $center = map_geometry_centroid($feature['geometry']['coordinates'] ?? []);
        if ($center === null) continue;
        $cities[map_data_key($province) . '|' . map_data_key($name)] = ['name' => $name, 'province' => $province, 'latitude' => round($center[0], 5), 'longitude' => round($center[1], 5)];
    }
}

ksort($cities);
ksort($provinces);
$result = [
    'metadata' => [
        'source' => 'faeldon/philippines-json-maps low-resolution GeoJSON; OCHA COD-AB boundaries sourced from NAMRIA/PSA and joined to PSGC',
        'source_release' => 'PSGC 2026-3Q, built 2026-11-07',
        'coordinate_system' => 'WGS84 (EPSG:4326)',
        'method' => 'Area-weighted polygon centroid of each city/municipality or province boundary; public map display remains approximate and rounded',
        'license' => 'CC-BY-IGO for upstream boundary data; MIT for source repository',
    ],
    'cities' => array_values($cities),
    'provinces' => array_values($provinces),
];
$outputPath = __DIR__ . '/../config/philippine_location_centroids.json';
file_put_contents($outputPath, json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n");
echo 'Municipalities: ' . count($cities) . '; provinces and NCR districts: ' . count($provinces) . "\n";
