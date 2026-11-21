(function () {
    'use strict';
    if (!window.L) return;

    const philippinesBounds = L.latLngBounds([4.0, 114.0], [21.7, 127.5]);
    const nearbyRadiusKm = 50;

    function textNode(tag, className, text) {
        const node = document.createElement(tag);
        if (className) node.className = className;
        node.textContent = text == null ? '' : String(text);
        return node;
    }

    function distanceKm(from, to) {
        const radians = value => value * Math.PI / 180;
        const dLat = radians(Number(to.latitude) - Number(from.latitude));
        const dLng = radians(Number(to.longitude) - Number(from.longitude));
        const lat1 = radians(Number(from.latitude));
        const lat2 = radians(Number(to.latitude));
        const a = Math.sin(dLat / 2) ** 2 + Math.cos(lat1) * Math.cos(lat2) * Math.sin(dLng / 2) ** 2;
        return 6371 * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    }

    function createPopup(item, config, viewerLocation) {
        const popup = document.createElement('div');
        popup.className = 'blood-map-popup';
        const isRequest = item.type === 'request';
        const heading = isRequest ? `Blood request - ${item.blood_type || 'Blood type not specified'}` : `Donation offer - ${item.blood_type || 'Blood type not specified'}`;
        popup.append(textNode('p', 'map-popup-kind', isRequest ? 'Blood request' : 'Donation offer'));
        popup.append(textNode('h3', '', heading));
        popup.append(textNode('p', '', item.location || 'Location not specified'));
        if (item.location_level === 'province') popup.append(textNode('p', 'map-popup-approximate', 'Approximate province location'));
        else if (item.coordinate_source === 'municipality_centroid') popup.append(textNode('p', 'map-popup-approximate', 'Approximate city center'));
        else if (item.approximate) popup.append(textNode('p', 'map-popup-approximate', 'Approximate public location'));
        if (isRequest) {
            popup.append(textNode('p', '', `Units needed: ${item.units}`));
            popup.append(textNode('p', '', `Urgency: ${String(item.urgency || 'routine').replace(/^./, c => c.toUpperCase())}`));
            if (item.facility) popup.append(textNode('p', '', `Facility: ${item.facility}`));
            if (item.needed_by) popup.append(textNode('p', '', `Needed by ${item.needed_by}`));
        } else {
            popup.append(textNode('p', '', 'Availability: Available'));
            if (item.available_date) popup.append(textNode('p', '', `Available ${item.available_date}`));
        }
        if (item.publisher_name) popup.append(textNode('p', item.facility_verified ? 'map-popup-verified' : 'map-popup-publisher', item.publisher_name + (item.facility_verified ? ' - Verified facility' : '')));
        if (viewerLocation) {
            const distance = Math.round(distanceKm(viewerLocation, item));
            popup.append(textNode('p', 'map-popup-distance', `Approx. ${distance} km from your saved location`));
        }
        const canNavigate = !config.clinicPanel || item.is_owner;
        if (canNavigate) {
            const link = document.createElement('a');
            link.className = 'blood-map-popup-link';
            link.href = `${isRequest ? config.requestHref : config.offerHref}${encodeURIComponent(item.id)}`;
            link.textContent = isRequest ? 'View Request' : 'View Offer';
            popup.append(link);
        }
        return popup;
    }

    function markerIcon(type) {
        const isRequest = type === 'request';
        return L.divIcon({
            className: '',
            html: `<span class="blood-map-marker blood-map-marker-${isRequest ? 'request' : 'offer'}"><i class="fa-solid ${isRequest ? 'fa-droplet' : 'fa-hand-holding-heart'}" aria-hidden="true"></i></span>`,
            iconSize: [38, 42], iconAnchor: [19, 39], popupAnchor: [0, -36]
        });
    }

    function appendOptions(select, values, firstLabel) {
        select.replaceChildren();
        const first = document.createElement('option');
        first.value = 'all'; first.textContent = firstLabel; select.append(first);
        values.forEach(value => {
            const option = document.createElement('option');
            option.value = value; option.textContent = value; select.append(option);
        });
    }

    function normalized(value) { return String(value || '').trim().toLocaleLowerCase(); }

    async function loadMap(element) {
        const page = element.closest('.blood-map-page');
        const status = page.querySelector('[data-blood-map-status]');
        const filterForm = page.querySelector('[data-blood-map-filters]');
        const filters = Object.fromEntries(Array.from(filterForm.querySelectorAll('[data-filter]'), input => [input.dataset.filter, input]));
        const locationInput = page.querySelector('[data-location-search]');
        const locationList = page.querySelector('datalist');
        const locationStatus = page.querySelector('[data-location-status]');
        const refreshButton = page.querySelector('[data-map-refresh]');
        const nearbyButton = page.querySelector('[data-nearby-toggle]');
        const nearbyCount = page.querySelector('[data-nearby-count]');
        const map = L.map(element, { center: [12.8797, 121.7740], zoom: 5, minZoom: 5, maxZoom: 18, maxBounds: philippinesBounds, maxBoundsViscosity: 1.0 });
        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(map);

        const endpoint = new URL(element.dataset.endpoint, window.location.href);
        let items = [];
        let viewerLocation = null;
        let unmappedCount = 0;
        let nearbyOnly = false;
        let centeredOnProfile = false;
        let visibleItems = [];
        let refreshing = false;
        const locations = new Map();
        const markerLayer = typeof L.markerClusterGroup === 'function'
            ? L.markerClusterGroup({ showCoverageOnHover: false, spiderfyOnMaxZoom: true, maxClusterRadius: 55 })
            : L.featureGroup();
        markerLayer.addTo(map);

        async function fetchMapItems() {
            const fetchedItems = [];
            let offset = 0;
            let pagePayload;
            while (offset <= 100000) {
                endpoint.searchParams.set('limit', '500');
                endpoint.searchParams.set('offset', String(offset));
                const response = await fetch(endpoint.toString(), { credentials: 'same-origin', headers: { Accept: 'application/json' } });
                const payload = await response.json();
                if (!response.ok || !payload.success || !payload.data) throw new Error(payload?.error?.message || 'Map data could not be loaded.');
                if (!pagePayload) pagePayload = payload.data;
                const batch = [...(payload.data.requests || []), ...(payload.data.donations || [])];
                fetchedItems.push(...batch);
                if (!payload.data.has_more) break;
                offset += 500;
            }
            return { items: fetchedItems, viewerLocation: pagePayload?.viewer_location || null, unmappedCount: Number(pagePayload?.unmapped_count || 0) };
        }

        function populateProvinces() {
            const previous = filters.province.value;
            const values = [...new Set(items.map(item => String(item.province || '').trim()).filter(Boolean))].sort((a, b) => a.localeCompare(b));
            appendOptions(filters.province, values, 'All provinces');
            if (values.includes(previous)) filters.province.value = previous;
        }

        function populateCities() {
            const selectedProvince = filters.province.value;
            const cities = [...new Set(items
                .filter(item => selectedProvince === 'all' || normalized(item.province) === normalized(selectedProvince))
                .map(item => String(item.city || '').trim()).filter(Boolean))].sort((a, b) => a.localeCompare(b));
            const previous = filters.city.value;
            appendOptions(filters.city, cities, 'All cities / municipalities');
            filters.city.disabled = cities.length === 0;
            if (cities.includes(previous)) filters.city.value = previous;
        }

        function populateLocations() {
            locations.clear(); locationList.replaceChildren();
            items.forEach(item => {
                const key = normalized(item.location || `${item.city || ''}, ${item.province || ''}`);
                if (!key || locations.has(key)) return;
                locations.set(key, { label: item.location || `${item.city || ''}, ${item.province || ''}`, latitude: Number(item.latitude), longitude: Number(item.longitude) });
            });
            locations.forEach(location => {
                const option = document.createElement('option'); option.value = location.label; locationList.append(option);
            });
        }

        function currentMatches() {
            const selectedType = filters.type.value;
            const selectedBlood = filters['blood-type'].value;
            const selectedProvince = filters.province.value;
            const selectedCity = filters.city.value;
            const selectedUrgency = filters.urgency.value;
            return items.filter(item => {
                if (selectedType !== 'all' && item.type !== selectedType) return false;
                if (selectedBlood !== 'all' && normalized(item.blood_type) !== normalized(selectedBlood)) return false;
                if (selectedProvince !== 'all' && normalized(item.province) !== normalized(selectedProvince)) return false;
                if (selectedCity !== 'all' && normalized(item.city) !== normalized(selectedCity)) return false;
                if (selectedUrgency !== 'all' && (item.type !== 'request' || normalized(item.urgency) !== selectedUrgency)) return false;
                return true;
            });
        }

        function updateNearbyCount(matches) {
            if (!viewerLocation) {
                nearbyCount.textContent = 'A saved profile or facility location is needed for nearby search.';
                return;
            }
            const nearby = matches.filter(item => distanceKm(viewerLocation, item) <= nearbyRadiusKm);
            const requestCount = nearby.filter(item => item.type === 'request').length;
            const offerCount = nearby.filter(item => item.type === 'donation').length;
            const locationNote = viewerLocation.approximate ? ' Approximate distances from ' + (viewerLocation.location_level === 'province' ? 'your province center.' : 'your city center.') : '';
            nearbyCount.textContent = `${requestCount} nearby blood request${requestCount === 1 ? '' : 's'} and ${offerCount} donation offer${offerCount === 1 ? '' : 's'} within ${nearbyRadiusKm} km.${locationNote}`;
        }

        function renderMarkers() {
            markerLayer.clearLayers();
            const matches = currentMatches();
            updateNearbyCount(matches);
            visibleItems = matches.filter(item => {
                const latitude = Number(item.latitude); const longitude = Number(item.longitude);
                if (!Number.isFinite(latitude) || !Number.isFinite(longitude) || !philippinesBounds.contains([latitude, longitude])) return false;
                return !nearbyOnly || (viewerLocation && distanceKm(viewerLocation, item) <= nearbyRadiusKm);
            });
            visibleItems.forEach(item => {
                const latitude = Number(item.latitude); const longitude = Number(item.longitude);
                L.marker([latitude, longitude], { icon: markerIcon(item.type), title: `${item.type === 'request' ? 'Blood request' : 'Donation offer'} - ${item.blood_type || ''}` })
                    .bindPopup(createPopup(item, {
                        requestHref: element.dataset.requestHref,
                        offerHref: element.dataset.offerHref,
                        clinicPanel: element.dataset.clinicPanel === '1'
                    }, viewerLocation))
                    .addTo(markerLayer);
            });
            const count = visibleItems.length;
            let message = `${count} matching post${count === 1 ? '' : 's'}.`;
            if (nearbyOnly && count === 0) message = `No posts are within approximately ${nearbyRadiusKm} km of your saved location. Explore the full map or change filters.`;
            else if (count === 0) message = '0 matching posts. No posts match these filters. Try changing or resetting your filters.';
            else if (unmappedCount > 0) message += ` ${unmappedCount} active post${unmappedCount === 1 ? '' : 's'} have locations that could not be resolved.`;
            status.textContent = message;
            status.dataset.state = count ? 'ready' : 'empty';
        }

        function updateUrgencyVisibility() {
            const isRequests = filters.type.value !== 'donation';
            page.querySelector('[data-urgency-filter]').hidden = !isRequests;
            if (!isRequests) filters.urgency.value = 'all';
        }

        async function refreshMapData() {
            if (refreshing) return;
            refreshing = true;
            refreshButton.disabled = true; refreshButton.textContent = 'Refreshing...';
            try {
                const result = await fetchMapItems();
                items = result.items; viewerLocation = result.viewerLocation; unmappedCount = result.unmappedCount;
                nearbyButton.disabled = !viewerLocation;
                if (viewerLocation) {
                    locationStatus.textContent = `Nearby reference: ${viewerLocation.location || 'saved location'}${viewerLocation.location_level === 'province' ? ' (approximate province location)' : ' (approximate city center)'}.`;
                    if (!centeredOnProfile) {
                        map.setView([Number(viewerLocation.latitude), Number(viewerLocation.longitude)], viewerLocation.location_level === 'province' ? 8 : 10);
                        centeredOnProfile = true;
                    }
                } else {
                    locationStatus.textContent = 'No saved profile or facility location is available; the map remains centered on the Philippines.';
                }
                populateProvinces(); populateCities(); populateLocations(); updateUrgencyVisibility(); renderMarkers();
            } catch (error) {
                status.textContent = error.message || 'Map data could not be loaded. Please try again.';
                status.dataset.state = 'error';
            } finally {
                refreshing = false;
                refreshButton.disabled = false; refreshButton.textContent = 'Refresh posts';
            }
        }

        filterForm.addEventListener('submit', event => event.preventDefault());
        filterForm.addEventListener('change', event => {
            if (event.target === filters.province) populateCities();
            updateUrgencyVisibility(); renderMarkers();
        });
        filterForm.addEventListener('reset', () => window.setTimeout(() => {
            nearbyOnly = false; nearbyButton.setAttribute('aria-pressed', 'false'); nearbyButton.textContent = 'Show Nearby (50 km)';
            populateCities(); updateUrgencyVisibility(); renderMarkers();
        }, 0));

        function goToLocation() {
            const match = locations.get(normalized(locationInput.value));
            if (!match || !Number.isFinite(match.latitude) || !Number.isFinite(match.longitude)) {
                locationStatus.textContent = 'Choose a location from the suggestions.'; return;
            }
            nearbyOnly = false; nearbyButton.setAttribute('aria-pressed', 'false'); nearbyButton.textContent = 'Show Nearby (50 km)';
            map.setView([match.latitude, match.longitude], Math.max(map.getZoom(), 11));
            locationStatus.textContent = `Map centered on ${match.label}.`;
        }
        refreshButton.addEventListener('click', refreshMapData);
        nearbyButton.addEventListener('click', () => {
            if (!viewerLocation) return;
            nearbyOnly = !nearbyOnly;
            nearbyButton.setAttribute('aria-pressed', nearbyOnly ? 'true' : 'false');
            nearbyButton.textContent = nearbyOnly ? 'Show All Posts' : `Show Nearby (${nearbyRadiusKm} km)`;
            if (nearbyOnly) map.setView([Number(viewerLocation.latitude), Number(viewerLocation.longitude)], viewerLocation.location_level === 'province' ? 8 : 10);
            renderMarkers();
        });
        page.querySelector('[data-location-go]').addEventListener('click', goToLocation);
        locationInput.addEventListener('keydown', event => { if (event.key === 'Enter') { event.preventDefault(); goToLocation(); } });
        window.addEventListener('focus', refreshMapData);
        document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'visible') refreshMapData(); });
        window.setInterval(refreshMapData, 60000);
        refreshMapData();
        if (window.ResizeObserver) new ResizeObserver(() => map.invalidateSize()).observe(element);
        else if (window.addEventListener) window.addEventListener('resize', () => map.invalidateSize());
    }

    function initialize() {
        if (!window.L) return;
        document.querySelectorAll('[data-blood-map]').forEach(element => loadMap(element));
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize);
    else initialize();
})();
