(function () {
    'use strict';
    if (!window.L) return;

    const philippinesBounds = L.latLngBounds([4.0, 116.0], [21.7, 127.5]);

    function textNode(tag, className, text) {
        const node = document.createElement(tag);
        if (className) node.className = className;
        node.textContent = text == null ? '' : String(text);
        return node;
    }

    function createPopup(item, config) {
        const popup = document.createElement('div');
        popup.className = 'blood-map-popup';
        const isRequest = item.type === 'request';
        const heading = isRequest ? `Blood request - ${item.blood_type || 'Blood type not specified'}` : `Donation offer - ${item.blood_type || 'Blood type not specified'}`;
        popup.append(textNode('p', 'map-popup-kind', isRequest ? 'Blood request' : 'Donation offer'));
        popup.append(textNode('h3', '', heading));
        popup.append(textNode('p', '', item.location || 'Location not specified'));
        if (isRequest) {
            popup.append(textNode('p', '', `${item.units} unit${Number(item.units) === 1 ? '' : 's'} - ${String(item.urgency || 'routine').replace(/^./, c => c.toUpperCase())}`));
            if (item.needed_by) popup.append(textNode('p', '', `Needed by ${item.needed_by}`));
        } else if (item.available_date) {
            popup.append(textNode('p', '', `Available ${item.available_date}`));
        }
        if (item.publisher_type === 'clinic' && item.facility_name) {
            popup.append(textNode('p', item.facility_verified ? 'map-popup-verified' : '', `${item.facility_name}${item.facility_verified ? ' - Verified facility' : ''}`));
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
            iconSize: [38, 42],
            iconAnchor: [19, 39],
            popupAnchor: [0, -36]
        });
    }

    function appendOptions(select, values, firstLabel) {
        select.replaceChildren();
        const first = document.createElement('option');
        first.value = 'all';
        first.textContent = firstLabel;
        select.append(first);
        values.forEach(value => {
            const option = document.createElement('option');
            option.value = value;
            option.textContent = value;
            select.append(option);
        });
    }

    function normalized(value) {
        return String(value || '').trim().toLocaleLowerCase();
    }

    async function loadMap(element) {
        const page = element.closest('.blood-map-page');
        const status = page.querySelector('[data-blood-map-status]');
        const filterForm = page.querySelector('[data-blood-map-filters]');
        const filters = Object.fromEntries(Array.from(filterForm.querySelectorAll('[data-filter]'), input => [input.dataset.filter, input]));
        const locationInput = page.querySelector('[data-location-search]');
        const locationList = page.querySelector('datalist');
        const locationStatus = page.querySelector('[data-location-status]');
        const refreshButton = page.querySelector('[data-map-refresh]');
        const map = L.map(element, { center: [12.8797, 121.7740], zoom: 5, minZoom: 5, maxZoom: 18, maxBounds: philippinesBounds, maxBoundsViscosity: 1.0 });
        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(map);

        const endpoint = new URL(element.dataset.endpoint, window.location.href);
        let items = [];
        const locations = new Map();
        const markerLayer = typeof L.markerClusterGroup === 'function'
            ? L.markerClusterGroup({ showCoverageOnHover: false, spiderfyOnMaxZoom: true, maxClusterRadius: 55 })
            : L.featureGroup();
        markerLayer.addTo(map);

        async function fetchMapItems() {
            const fetchedItems = [];
            let offset = 0;
            while (offset <= 100000) {
                endpoint.searchParams.set('limit', '500');
                endpoint.searchParams.set('offset', String(offset));
                const response = await fetch(endpoint.toString(), { credentials: 'same-origin', headers: { Accept: 'application/json' } });
                const payload = await response.json();
                if (!response.ok || !payload.success || !payload.data) throw new Error(payload?.error?.message || 'Map data could not be loaded.');
                const batch = [...(payload.data.requests || []), ...(payload.data.donations || [])];
                fetchedItems.push(...batch);
                if (batch.length < 500) break;
                offset += 500;
            }
            return fetchedItems;
        }

        function populateProvinces() {
            const previous = filters.province.value;
            const provinceValues = [...new Set(items.map(item => String(item.province || '').trim()).filter(Boolean))].sort((a, b) => a.localeCompare(b));
            appendOptions(filters.province, provinceValues, 'All provinces');
            if (provinceValues.includes(previous)) filters.province.value = previous;
        }

        function populateLocations() {
            locations.clear();
            locationList.replaceChildren();
            items.forEach(item => {
                const key = normalized(item.location || `${item.city || ''}, ${item.province || ''}`);
                if (!key || locations.has(key)) return;
                locations.set(key, { label: item.location || `${item.city || ''}, ${item.province || ''}`, latitude: Number(item.latitude), longitude: Number(item.longitude) });
            });
            locations.forEach(location => {
                const option = document.createElement('option');
                option.value = location.label;
                locationList.append(option);
            });
        }
        let visibleItems = [];

        function populateCities() {
                const selectedProvince = filters.province.value;
                const cities = [...new Set(items
                    .filter(item => selectedProvince === 'all' || normalized(item.province) === normalized(selectedProvince))
                    .map(item => String(item.city || '').trim()).filter(Boolean))]
                    .sort((a, b) => a.localeCompare(b));
                const previous = filters.city.value;
                appendOptions(filters.city, cities, 'All cities / municipalities');
                filters.city.disabled = cities.length === 0;
                if (cities.includes(previous)) filters.city.value = previous;
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

        function renderMarkers() {
                markerLayer.clearLayers();
                visibleItems = currentMatches().filter(item => {
                    const latitude = Number(item.latitude);
                    const longitude = Number(item.longitude);
                    return Number.isFinite(latitude) && Number.isFinite(longitude) && philippinesBounds.contains([latitude, longitude]);
                });
                visibleItems.forEach(item => {
                    const latitude = Number(item.latitude);
                    const longitude = Number(item.longitude);
                    L.marker([latitude, longitude], { icon: markerIcon(item.type), title: `${item.type === 'request' ? 'Blood request' : 'Donation offer'} - ${item.blood_type || ''}` })
                        .bindPopup(createPopup(item, {
                            requestHref: element.dataset.requestHref,
                            offerHref: element.dataset.offerHref,
                            clinicPanel: element.dataset.clinicPanel === '1'
                        }))
                        .addTo(markerLayer);
                });
                const count = visibleItems.length;
                status.textContent = count ? `${count} matching post${count === 1 ? '' : 's'}.` : '0 matching posts. No posts match these filters. Try changing or resetting your filters.';
                status.dataset.state = count ? 'ready' : 'empty';
        }

        function updateUrgencyVisibility() {
                const isRequests = filters.type.value !== 'donation';
                page.querySelector('[data-urgency-filter]').hidden = !isRequests;
                if (!isRequests) filters.urgency.value = 'all';
        }

        async function refreshMapData() {
            refreshButton.disabled = true;
            refreshButton.textContent = 'Refreshing...';
            try {
                items = await fetchMapItems();
                populateProvinces();
                populateCities();
                populateLocations();
                updateUrgencyVisibility();
                renderMarkers();
            } catch (error) {
                status.textContent = error.message || 'Map data could not be loaded. Please try again.';
                status.dataset.state = 'error';
            } finally {
                refreshButton.disabled = false;
                refreshButton.textContent = 'Refresh posts';
            }
        }

        filterForm.addEventListener('change', event => {
            if (event.target === filters.province) populateCities();
            updateUrgencyVisibility();
            renderMarkers();
        });
        filterForm.addEventListener('reset', () => {
            window.setTimeout(() => {
                populateCities();
                updateUrgencyVisibility();
                renderMarkers();
            }, 0);
        });

        function goToLocation() {
            const match = locations.get(normalized(locationInput.value));
            if (!match || !Number.isFinite(match.latitude) || !Number.isFinite(match.longitude)) {
                locationStatus.textContent = 'Choose a location from the suggestions.';
                return;
            }
            map.setView([match.latitude, match.longitude], Math.max(map.getZoom(), 11));
            locationStatus.textContent = `Map centered on ${match.label}.`;
        }
        refreshButton.addEventListener('click', refreshMapData);
        page.querySelector('[data-location-go]').addEventListener('click', goToLocation);
        locationInput.addEventListener('keydown', event => {
            if (event.key === 'Enter') {
                event.preventDefault();
                goToLocation();
            }
        });
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
