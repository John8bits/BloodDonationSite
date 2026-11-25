<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/philippine_map_locations.php';
$data = blife_psgc_data();
$source = $argv[1] ?? '';
$existingProvinceNames = require __DIR__ . '/../config/philippine_provinces.php';
foreach (array_merge($existingProvinceNames, ['Metro Manila (NCR)', 'Special Geographic Area', 'Independent Cities']) as $provinceName) {
    if (blife_psgc_area($provinceName) === null) throw new RuntimeException('Profile province is missing from the bundled PSGC data: ' . $provinceName);
}
$expectedMunicipalities = 0;
$barangayCount = 0;
$barangayPointCount = 0;
foreach ($data['areas'] as $area) {
    foreach ($area['cities'] as $city) {
        $expectedMunicipalities++;
        foreach ($city[5] as $barangay) {
            $barangayCount++;
            if ($barangay[2] !== null && $barangay[3] !== null) $barangayPointCount++;
        }
    }
}
if ($expectedMunicipalities !== 1642 || $barangayCount !== 42010) {
    throw new RuntimeException('The bundled PSGC hierarchy is incomplete for the 2026-Q3 count.');
}
if ($source !== '' && is_file($source . '/index.json')) {
    $index = json_decode((string) file_get_contents($source . '/index.json'), true, 512, JSON_THROW_ON_ERROR);
    $expectedCityCodes = [];
    $expectedBarangayCodes = [];
    foreach ($index['regions'] as $region) {
        $codes = array_merge(array_column($region['provinces'] ?? [], 'code'), array_column($region['independent'] ?? [], 'code'));
        foreach ($codes as $code) {
            $areaPath = $source . '/areas/' . $code . '.json';
            if (!is_file($areaPath)) continue;
            $sourceArea = json_decode((string) file_get_contents($areaPath), true, 512, JSON_THROW_ON_ERROR);
            foreach ($sourceArea['cities'] ?? [] as $sourceCity) {
                $expectedCityCodes[] = (string) $sourceCity['code'];
                foreach ($sourceCity['barangays'] ?? [] as $sourceBarangay) {
                    $expectedBarangayCodes[] = is_array($sourceBarangay) ? (string) ($sourceBarangay[0] ?? '') : substr((string) $sourceBarangay, 0, 10);
                }
            }
        }
    }
    $actualCityCodes = $actualBarangayCodes = [];
    foreach ($data['areas'] as $area) foreach ($area['cities'] as $city) {
        $actualCityCodes[] = (string) $city[0];
        foreach ($city[5] as $barangay) $actualBarangayCodes[] = (string) $barangay[0];
    }
    $missingCities = array_values(array_diff(array_unique($expectedCityCodes), array_unique($actualCityCodes)));
    $missingBarangays = array_values(array_diff(array_unique($expectedBarangayCodes), array_unique($actualBarangayCodes)));
    if ($missingCities || $missingBarangays) throw new RuntimeException('Dataset omissions: ' . json_encode(['cities' => $missingCities, 'barangays' => array_slice($missingBarangays, 0, 20)]));
}
$samples = [
    ['Southern Leyte', 'Sogod', 'Rizal'],
    ['Bukidnon', 'Cabanglasan', 'Lambagan'],
    ['Metro Manila (NCR)', 'Quezon City', 'Alicia'],
];
foreach ($samples as [$province, $city, $barangay]) {
    $point = blife_psgc_barangay($province, $city, $barangay);
    if ($point === null) throw new RuntimeException("Missing PSGC location: {$province} / {$city} / {$barangay}");
}
$cotabatoCity = blife_psgc_city('Cotabato', 'Makilala');
if ($cotabatoCity === null || !$cotabatoCity['barangays']) throw new RuntimeException('Cotabato / Makilala is missing from the location hierarchy.');
$cotabatoBarangay = blife_psgc_barangay('Cotabato', 'Makilala', (string) $cotabatoCity['barangays'][0][1]);
if ($cotabatoBarangay === null) throw new RuntimeException('Cotabato barangay lookup failed.');
$profileLocation = blife_map_resolve_location(null, null, '', '', '', 'Sogod', 'Southern Leyte', 'Rizal');
if (($profileLocation['location_level'] ?? '') !== 'barangay' || ($profileLocation['coordinate_source'] ?? '') !== 'barangay_boundary_point') {
    throw new RuntimeException('Profile fallback did not resolve to an approximate barangay point.');
}
$explicitPost = blife_map_resolve_location(10.5, 124.9, 'Maasin City', 'Southern Leyte', '', 'Sogod', 'Southern Leyte', 'Rizal');
if (($explicitPost['coordinate_source'] ?? '') !== 'saved_post' || (float) $explicitPost['latitude'] !== 10.5 || (float) $explicitPost['longitude'] !== 124.9) {
    throw new RuntimeException('Explicit post coordinates were overwritten by the profile location.');
}
$selectedCityPost = blife_map_resolve_location(null, null, 'Maasin City', 'Southern Leyte', '', 'Sogod', 'Southern Leyte', 'Rizal');
if (($selectedCityPost['location_level'] ?? '') !== 'city' || ($selectedCityPost['coordinate_source'] ?? '') !== 'municipality_centroid') {
    throw new RuntimeException('An explicitly selected post city was replaced by the profile barangay.');
}
$legacyCityPost = blife_map_resolve_location(null, null, '', '', 'Maasin City, Southern Leyte', 'Sogod', 'Southern Leyte', 'Rizal');
if (($legacyCityPost['location_level'] ?? '') !== 'city' || ($legacyCityPost['coordinate_source'] ?? '') !== 'municipality_centroid') {
    throw new RuntimeException('An explicitly saved legacy post location was replaced by the profile barangay.');
}
if (!blife_psgc_valid_profile_location('Southern Leyte', 'Sogod', 'Rizal') || blife_psgc_valid_profile_location('Southern Leyte', 'Sogod', 'Not a Barangay')) {
    throw new RuntimeException('Member profile location validation did not accept/reject expected values.');
}
echo json_encode([
    'municipalities_and_cities' => $expectedMunicipalities,
    'barangays' => $barangayCount,
    'barangays_with_own_map_points' => $barangayPointCount,
    'sample_points' => $samples,
    'cotabato_sample' => ['city' => $cotabatoCity['name'], 'barangay' => $cotabatoBarangay['name'], 'has_point' => $cotabatoBarangay['latitude'] !== null],
    'profile_fallback_level' => $profileLocation['location_level'],
    'explicit_post_coordinates_preserved' => true,
    'explicit_post_city_preserved' => true,
    'legacy_post_location_preserved' => true,
    'profile_location_validation' => true,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
