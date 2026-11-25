<?php
declare(strict_types=1);
require_once __DIR__ . '/philippine_psgc_locations.php';

/**
 * Resolve public map points from saved locality names only. Boundary centroids
 * are approximate representative points; exact coordinates are never returned.
 *
 * The bundled lookup was generated from faeldon/philippines-json-maps (MIT),
 * using low-resolution OCHA COD-AB boundaries sourced from NAMRIA/PSA
 * (CC-BY-IGO), matched to PSGC. See config/philippine_location_centroids.json.
 */
function blife_map_location_data(): array
{
    static $data = null;
    if ($data === null) {
        $path = __DIR__ . '/../config/philippine_location_centroids.json';
        $decoded = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
        $data = is_array($decoded) ? $decoded : ['cities' => [], 'provinces' => []];
    }
    return $data;
}

function blife_map_normalize_location(string $value): string
{
    return blife_psgc_key($value);
}

function blife_map_location_name_matches(string $candidate, string $requested): bool
{
    $normalize = static function (string $value): string {
        $value = blife_map_normalize_location($value);
        $value = preg_replace('/^cityof/', '', $value) ?? $value;
        return preg_replace('/city$/', '', $value) ?? $value;
    };
    return $normalize($candidate) !== '' && $normalize($candidate) === $normalize($requested);
}

function blife_map_valid_coordinates(mixed $latitude, mixed $longitude): ?array
{
    if (!is_numeric($latitude) || !is_numeric($longitude)) return null;
    $lat = (float) $latitude;
    $lng = (float) $longitude;
    if (!is_finite($lat) || !is_finite($lng) || $lat < 4.0 || $lat > 21.7 || $lng < 114.0 || $lng > 127.5) return null;
    return [$lat, $lng];
}

function blife_map_resolve_location(
    mixed $latitude,
    mixed $longitude,
    string $postCity,
    string $postProvince,
    string $legacyLocation,
    string $fallbackCity = '',
    string $fallbackProvince = '',
    string $fallbackBarangay = '',
    string $postBarangay = ''
): ?array {
    $explicitCoordinates = blife_map_valid_coordinates($latitude, $longitude);
    $city = trim($postCity);
    $province = trim($postProvince);
    $legacyLocation = trim($legacyLocation);
    $data = blife_map_location_data();

    if (($city === '' || $province === '') && str_contains($legacyLocation, ',')) {
        $parts = array_map('trim', explode(',', $legacyLocation, 2));
        if ($city === '') $city = $parts[0] ?? '';
        if ($province === '') $province = $parts[1] ?? '';
    } elseif ($city === '' && $province === '' && $legacyLocation !== '') {
        // A single saved locality may be either a province or a municipality.
        $province = $legacyLocation;
    }

    $usingProfileLocation = ($city === '' || ($fallbackCity !== '' && blife_map_location_name_matches($city, $fallbackCity)))
        && ($province === '' || blife_map_normalize_location($province) === blife_map_normalize_location($fallbackProvince));

    // Member profiles store province in the legacy uinfo.city column and have
    // separate municipality / barangay values after the member location migration.
    if ($city === '' && $province === '' && $fallbackCity !== '') {
        $city = trim($fallbackCity);
        $province = trim($fallbackProvince);
    }

    // Clinic posts without a city use the registered facility locality when
    // it belongs to the saved post province. This never replaces a saved city.
    if ($city === '' && $fallbackCity !== '' && ($province === '' || blife_map_normalize_location($province) === blife_map_normalize_location($fallbackProvince))) {
        $city = trim($fallbackCity);
        if ($province === '') $province = trim($fallbackProvince);
    }

    if ($city === '' && $province === '' && $fallbackProvince !== '') $province = trim($fallbackProvince);

    $provinceAlias = [
        'northcotabato' => 'cotabato',
        'westernsamar' => 'samar',
        'samarwesternsamar' => 'samar',
    ];
    $provinceKey = blife_map_normalize_location($province);
    if (isset($provinceAlias[$provinceKey])) $provinceKey = blife_map_normalize_location($provinceAlias[$provinceKey]);

    if ($explicitCoordinates !== null) {
        return [
            'latitude' => $explicitCoordinates[0], 'longitude' => $explicitCoordinates[1],
            'city' => $city, 'province' => $province, 'location_level' => $city !== '' ? 'city' : 'province',
            'approximate' => true, 'coordinate_source' => 'saved_post',
        ];
    }

    $barangayName = trim($postBarangay) !== '' ? trim($postBarangay) : ($usingProfileLocation ? trim($fallbackBarangay) : '');
    if ($city !== '' && $province !== '' && $barangayName !== '') {
        $barangay = blife_psgc_barangay($province, $city, $barangayName);
        $barangayCoordinates = $barangay === null ? null : blife_map_valid_coordinates($barangay['latitude'], $barangay['longitude']);
        if ($barangay !== null && $barangayCoordinates !== null) {
            return [
                'latitude' => $barangayCoordinates[0], 'longitude' => $barangayCoordinates[1],
                'city' => $city, 'province' => $province, 'location_level' => 'barangay',
                'approximate' => true, 'coordinate_source' => 'barangay_boundary_point',
            ];
        }
    }

    if ($city !== '' && $provinceKey !== '') {
        foreach (($data['cities'] ?? []) as $item) {
            if (blife_map_normalize_location((string) ($item['province'] ?? '')) === $provinceKey && blife_map_location_name_matches((string) ($item['name'] ?? ''), $city)) {
                return ['latitude' => (float) $item['latitude'], 'longitude' => (float) $item['longitude'], 'city' => $item['name'], 'province' => $item['province'], 'location_level' => 'city', 'approximate' => true, 'coordinate_source' => 'municipality_centroid'];
            }
        }
        $psgcCity = blife_psgc_city($province, $city);
        $psgcCityCoordinates = $psgcCity === null ? null : blife_map_valid_coordinates($psgcCity['latitude'], $psgcCity['longitude']);
        if ($psgcCity !== null && $psgcCityCoordinates !== null) {
            return ['latitude' => $psgcCityCoordinates[0], 'longitude' => $psgcCityCoordinates[1], 'city' => $psgcCity['name'], 'province' => $province, 'location_level' => 'city', 'approximate' => true, 'coordinate_source' => 'municipality_centroid'];
        }
    } elseif ($city !== '') {
        $matches = array_values(array_filter($data['cities'] ?? [], static fn(array $item): bool => blife_map_normalize_location((string) ($item['name'] ?? '')) === blife_map_normalize_location($city)));
        if (count($matches) === 1) {
            $item = $matches[0];
            return ['latitude' => (float) $item['latitude'], 'longitude' => (float) $item['longitude'], 'city' => $item['name'], 'province' => $item['province'], 'location_level' => 'city', 'approximate' => true, 'coordinate_source' => 'municipality_centroid'];
        }
    }

    if ($provinceKey !== '') {
        foreach (($data['provinces'] ?? []) as $item) {
            if (blife_map_normalize_location((string) ($item['name'] ?? '')) === $provinceKey) {
                return ['latitude' => (float) $item['latitude'], 'longitude' => (float) $item['longitude'], 'city' => '', 'province' => $item['name'], 'location_level' => 'province', 'approximate' => true, 'coordinate_source' => 'province_centroid'];
            }
        }
    }
    return null;
}
