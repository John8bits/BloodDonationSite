<?php
function blife_render_blood_donation_map(string $apiUrl, string $requestHref, string $offerHref, bool $clinicPanel = false): void
{
    $escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    ?>
    <section class="blood-map-page" aria-label="Blood donation map">
        <form class="blood-map-filters" data-blood-map-filters aria-label="Filter map posts">
            <label>Post type
                <select data-filter="type"><option value="all">All posts</option><option value="request">Blood Requests</option><option value="donation">Donation Offers</option></select>
            </label>
            <label>Blood type
                <select data-filter="blood-type"><option value="all">All blood types</option><?php foreach (['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'] as $bloodType): ?><option value="<?= $escape($bloodType) ?>"><?= $escape($bloodType) ?></option><?php endforeach; ?></select>
            </label>
            <label>Province
                <select data-filter="province"><option value="all">All provinces</option></select>
            </label>
            <label>City / Municipality
                <select data-filter="city" disabled><option value="all">All cities / municipalities</option></select>
            </label>
            <label data-urgency-filter>Urgency
                <select data-filter="urgency"><option value="all">All urgencies</option><option value="routine">Routine</option><option value="urgent">Urgent</option><option value="emergency">Emergency</option></select>
            </label>
            <button class="blood-map-reset" type="reset">Reset Filters</button>
        </form>
        <div class="blood-map-toolbar">
            <p>Active blood requests and available donation offers across the Philippines.</p>
            <div class="blood-map-toolbar-actions">
                <button class="blood-map-nearby" type="button" data-nearby-toggle aria-pressed="false" disabled>Show Nearby (50 km)</button>
                <button class="blood-map-refresh" type="button" data-map-refresh>Refresh posts</button>
            </div>
            <p class="blood-map-nearby-count" data-nearby-count aria-live="polite">Nearby counts will appear when your saved location is available.</p>
            <div class="blood-map-legend" aria-label="Map legend">
                <span><i class="fa-solid fa-droplet request-map-symbol" aria-hidden="true"></i> Blood request</span>
                <span><i class="fa-solid fa-hand-holding-heart offer-map-symbol" aria-hidden="true"></i> Donation offer</span>
                <a href="https://www.openstreetmap.org/fixthemap" target="_blank" rel="noopener noreferrer">Report a map issue</a>
                <span>Approximate locality boundaries: <a href="https://data.humdata.org/dataset/cod-ab-phl" target="_blank" rel="noopener noreferrer">OCHA COD-AB (NAMRIA / PSA)</a> and <a href="https://psa.gov.ph/classification/psgc" target="_blank" rel="noopener noreferrer">PSA PSGC</a></span>
            </div>
        </div>
        <div class="blood-map-status" data-blood-map-status role="status" aria-live="polite">Loading active posts…</div>
        <div class="blood-map-location-search">
            <label for="bloodMapLocationSearch">Find a mapped location</label>
            <div><input id="bloodMapLocationSearch" type="search" data-location-search list="bloodMapLocations" placeholder="Search city or province" autocomplete="off"><datalist id="bloodMapLocations"></datalist><button type="button" data-location-go>Go to location</button></div>
            <p data-location-status role="status" aria-live="polite"></p>
        </div>
        <div class="blood-map-canvas" data-blood-map data-endpoint="<?= $escape($apiUrl) ?>" data-request-href="<?= $escape($requestHref) ?>" data-offer-href="<?= $escape($offerHref) ?>" data-clinic-panel="<?= $clinicPanel ? '1' : '0' ?>" role="application" aria-label="Interactive map of blood requests and donation offers in the Philippines"></div>
    </section>
    <?php
}
