<?php
function blife_validate_post_location(string $province, string $city, string $barangay, string $latitude, string $longitude, array $provinces): array|false
{
    $province = trim($province);
    $city = trim($city);
    $barangay = trim($barangay);
    if (!in_array($province, $provinces, true) || $city === '' || strlen($city) > 120 || strlen($barangay) > 120 || strlen($city . ', ' . $province) > 120) {
        return false;
    }
    if ($latitude === '' || $longitude === '' || !is_numeric($latitude) || !is_numeric($longitude)) {
        return false;
    }
    $latitudeValue = (float) $latitude;
    $longitudeValue = (float) $longitude;
    // Broad Philippines bounds; map selection is also constrained to this area.
    if (!is_finite($latitudeValue) || !is_finite($longitudeValue) || $latitudeValue < 4.0 || $latitudeValue > 21.7 || $longitudeValue < 116.0 || $longitudeValue > 127.5) {
        return false;
    }
    return [
        'province' => $province,
        'city' => $city,
        'barangay' => $barangay !== '' ? $barangay : null,
        'latitude' => number_format($latitudeValue, 7, '.', ''),
        'longitude' => number_format($longitudeValue, 7, '.', ''),
    ];
}
