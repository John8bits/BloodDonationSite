<?php
declare(strict_types=1);

function blife_psgc_data(): array
{
    static $data = null;
    if ($data === null) {
        $path = __DIR__ . '/../config/philippine_psgc_locations.json';
        $decoded = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
        $data = is_array($decoded) ? $decoded : ['areas' => []];
    }
    return $data;
}

function blife_psgc_key(string $value): string
{
    $value = trim($value);
    if (function_exists('iconv')) $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
    return strtolower(preg_replace('/[^a-z0-9]+/i', '', $value) ?? '');
}

function blife_psgc_area(string $province): ?array
{
    $areas = blife_psgc_data()['areas'] ?? [];
    static $areaNamesByKey = null;
    if ($areaNamesByKey === null) {
        $areaNamesByKey = [];
        foreach (array_keys($areas) as $name) $areaNamesByKey[blife_psgc_key((string) $name)] = (string) $name;
    }
    $target = blife_psgc_key($province);
    $name = $areaNamesByKey[$target] ?? null;
    return $name === null || !isset($areas[$name]) ? null : ['name' => $name] + $areas[$name];
}

function blife_psgc_city(string $province, string $city): ?array
{
    $area = blife_psgc_area($province);
    if ($area === null) return null;
    $target = blife_psgc_key($city);
    foreach ($area['cities'] ?? [] as $record) {
        if (blife_psgc_key((string) ($record[1] ?? '')) === $target) {
            return ['code' => (string) $record[0], 'name' => (string) $record[1], 'type' => (string) $record[2], 'latitude' => $record[3], 'longitude' => $record[4], 'barangays' => $record[5] ?? []];
        }
    }
    return null;
}

function blife_psgc_barangay(string $province, string $city, string $barangay): ?array
{
    $cityRecord = blife_psgc_city($province, $city);
    if ($cityRecord === null) return null;
    static $barangayIndexes = [];
    $cityCode = $cityRecord['code'];
    if (!isset($barangayIndexes[$cityCode])) {
        $barangayIndexes[$cityCode] = [];
        foreach ($cityRecord['barangays'] as $record) $barangayIndexes[$cityCode][blife_psgc_key((string) ($record[1] ?? ''))] = $record;
    }
    $target = blife_psgc_key($barangay);
    $record = $barangayIndexes[$cityCode][$target] ?? null;
    return $record === null ? null : ['code' => (string) $record[0], 'name' => (string) $record[1], 'latitude' => $record[2], 'longitude' => $record[3]];
}

function blife_psgc_valid_profile_location(string $province, string $city, string $barangay): bool
{
    if (blife_psgc_city($province, $city) === null) return false;
    return $barangay === '' || blife_psgc_barangay($province, $city, $barangay) !== null;
}

