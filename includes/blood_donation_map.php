<?php
function blife_render_blood_donation_map(string $apiUrl, string $requestHref, string $offerHref, bool $clinicPanel = false): void
{
    $escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    ?>
    <section class="blood-map-page" aria-label="Blood donation map">
        <div class="blood-map-toolbar">
            <p class="blood-map-nearby-count" data-nearby-count aria-live="polite">Nearby counts unavailable</p>
            <div class="blood-map-toolbar-actions">
                <button class="blood-map-nearby" type="button" data-nearby-toggle aria-pressed="false" disabled>Show Nearby (50 km)</button>
                <button class="blood-map-refresh" type="button" data-map-refresh>Refresh posts</button>
            </div>
        </div>
        <div class="blood-map-status" data-blood-map-status role="status" aria-live="polite">Loading active posts…</div>
        <div class="blood-map-map-shell">
            <div class="blood-map-map-info">
                <div class="blood-map-legend" aria-label="Map legend">
                    <span><i class="fa-solid fa-droplet request-map-symbol" aria-hidden="true"></i> Blood request</span>
                    <span><i class="fa-solid fa-hand-holding-heart offer-map-symbol" aria-hidden="true"></i> Donation offer</span>
                </div>
                <div class="blood-map-controls" aria-label="Map controls">
                    <button class="blood-map-view-full" type="button" data-map-fullscreen><i class="fa-solid fa-maximize" aria-hidden="true"></i> View Full Map</button>
                    <button class="blood-map-view-exit" type="button" data-map-exit hidden><i class="fa-solid fa-minimize" aria-hidden="true"></i> Exit Full Screen</button>
                    <button class="blood-map-reset-view" type="button" data-map-reset-view><i class="fa-solid fa-crosshairs" aria-hidden="true"></i> Reset View</button>
                    <span class="blood-map-nearby-slot" data-map-nearby-slot></span>
                    <button class="blood-map-locations-toggle" type="button" data-locations-toggle aria-expanded="false" aria-controls="bloodMapLocationsPanel"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i> Locations</button>
                    <details class="blood-map-fullscreen-filters" data-map-filter-disclosure>
                        <summary><i class="fa-solid fa-sliders" aria-hidden="true"></i> Filters</summary>
                        <div class="blood-map-filter-panel" data-map-filter-slot>
                            <form class="blood-map-filters" data-blood-map-filters aria-label="Filter map posts">
                                <label>Post type
                                    <select data-filter="type"><option value="all">All posts</option><option value="request">Blood Requests</option><option value="donation">Donation Offers</option></select>
                                </label>
                                <label>Blood type
                                    <select data-filter="blood-type"><option value="all">All blood types</option><?php foreach (['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'] as $bloodType): ?><option value="<?= $escape($bloodType) ?>"><?= $escape($bloodType) ?></option><?php endforeach; ?></select>
                                </label>
                                <label data-urgency-filter>Urgency
                                    <select data-filter="urgency"><option value="all">All urgencies</option><option value="routine">Routine</option><option value="urgent">Urgent</option><option value="emergency">Emergency</option></select>
                                </label>
                                <button class="blood-map-reset" type="reset"><i class="fa-solid fa-rotate-left" aria-hidden="true"></i> Reset Filters</button>
                            </form>
                        </div>
                    </details>
                    <aside class="blood-map-locations-panel" id="bloodMapLocationsPanel" data-locations-panel hidden aria-labelledby="bloodMapLocationTitle">
                        <header class="blood-map-locations-header">
                            <div><span>MAP TOOLS</span><h2 id="bloodMapLocationTitle">Find a Location</h2></div>
                            <button class="blood-map-locations-close" type="button" data-locations-close aria-label="Close location tools"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
                        </header>
                        <div class="blood-map-location-search">
                            <div><input id="bloodMapLocationSearch" type="search" data-location-search placeholder="Search city or province" autocomplete="off"><button type="button" data-location-go>Find</button></div>
                        </div>
                        <div class="blood-map-location-levels">
                            <label>Province<select data-location-province><option value="">Select province</option></select></label>
                            <label>City / Municipality<select data-location-city disabled><option value="">Select city / municipality</option></select></label>
                            <label>Barangay<select data-location-barangay disabled><option value="">No barangay map data available</option></select></label>
                        </div>
                        <p class="blood-map-location-status" data-location-status role="status" aria-live="polite"></p>
                    </aside>
                </div>
                <small class="blood-map-attribution">Map data © <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener noreferrer">OpenStreetMap contributors</a></small>
            </div>
            <div class="blood-map-canvas" data-blood-map data-endpoint="<?= $escape($apiUrl) ?>" data-request-href="<?= $escape($requestHref) ?>" data-offer-href="<?= $escape($offerHref) ?>" data-clinic-panel="<?= $clinicPanel ? '1' : '0' ?>" role="application" aria-label="Interactive map of blood requests and donation offers in the Philippines"></div>
        </div>
    </section>
    <?php
}
