<?php
function blife_location_picker(array $provinces, string $prefix, string $id, array $value = [], ?string $provinceFieldName = null): void
{
    $provinceFieldName = $provinceFieldName ?: $prefix . '_province';
    $escape = static fn($item): string => htmlspecialchars((string) $item, ENT_QUOTES, 'UTF-8');
    $province = $value['province'] ?? '';
    $latitude = $value['latitude'] ?? '';
    $longitude = $value['longitude'] ?? '';
    ?>
    <section class="location-picker" data-location-picker data-prefix="<?= $escape($prefix) ?>">
        <div class="form-row location-fields">
            <label>Province
                <select name="<?= $escape($provinceFieldName) ?>" required>
                    <option value="">Select province</option>
                    <?php foreach ($provinces as $item): ?><option value="<?= $escape($item) ?>" <?= $province === $item ? 'selected' : '' ?>><?= $escape($item) ?></option><?php endforeach; ?>
                </select>
            </label>
            <label>City / Municipality
                <input name="<?= $escape($prefix) ?>_city" maxlength="120" required value="<?= $escape($value['city'] ?? '') ?>" placeholder="e.g. Sogod">
            </label>
            <label>Barangay <span class="optional">(optional)</span>
                <input name="<?= $escape($prefix) ?>_barangay" maxlength="120" value="<?= $escape($value['barangay'] ?? '') ?>" placeholder="Optional">
            </label>
        </div>
        <p class="form-help location-privacy-note">Choose a public donation site or general area. Please do not pin a home address.</p>
        <div class="location-map" id="<?= $escape($id) ?>" data-location-map role="application" aria-label="Choose a post location on the map"></div>
        <p class="location-map-status" data-location-status aria-live="polite"><?= $latitude !== '' && $longitude !== '' ? 'Saved map point loaded. Drag the marker or click to adjust.' : 'Click the map or drag the marker to choose a location in the Philippines.' ?></p>
        <input type="hidden" name="<?= $escape($prefix) ?>_latitude" value="<?= $escape($latitude) ?>" data-location-latitude>
        <input type="hidden" name="<?= $escape($prefix) ?>_longitude" value="<?= $escape($longitude) ?>" data-location-longitude>
    </section>
    <?php
}
