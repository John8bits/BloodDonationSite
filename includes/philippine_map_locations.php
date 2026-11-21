<?php
declare(strict_types=1);

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
    $value = trim(function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value));
    $value = str_replace(['ñ', 'Ñ'], 'n', $value);
    return preg_replace('/[^a-z0-9]+/u', '', $value) ?? '';
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
    string $fallbackProvince = ''
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

    // The member profile currently stores its province in uinfo.city. Clinic
    // profiles have a distinct city and province pair.
    if ($city === '' && $province === '' && $fallbackCity !== '') {
        $profileProvinceKey = blife_map_normalize_location($fallbackCity);
        foreach (($data['provinces'] ?? []) as $item) {
            if (blife_map_normalize_location((string) ($item['name'] ?? '')) === $profileProvinceKey) {
                $province = (string) $item['name'];
                break;
            }
        }
        if ($province === '') {
            $city = trim($fallbackCity);
            $province = trim($fallbackProvince);
        }
    }

    // Clinic posts without a city use the registered facility locality when
    // it belongs to the saved post province. This never replaces a saved city.
    if ($city === '' && $fallbackCity !== '' && ($province === '' || blife_map_normalize_location($province) === blife_map_normalize_location($fallbackProvince))) {
        $city = trim($fallbackCity);
        if ($province === '') $province = trim($fallbackProvince);
    }

    // Member profiles currently store a single city/province selector. Apply
    // it only when the post has no explicit locality at all.
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

    if ($city !== '' && $provinceKey !== '') {
        foreach (($data['cities'] ?? []) as $item) {
            if (blife_map_normalize_location((string) ($item['province'] ?? '')) === $provinceKey && blife_map_normalize_location((string) ($item['name'] ?? '')) === blife_map_normalize_location($city)) {
                return ['latitude' => (float) $item['latitude'], 'longitude' => (float) $item['longitude'], 'city' => $item['name'], 'province' => $item['province'], 'location_level' => 'city', 'approximate' => true, 'coordinate_source' => 'municipality_centroid'];
            }
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
