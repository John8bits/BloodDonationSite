(function () {
    'use strict';
    if (!window.L) return;

    const bounds = L.latLngBounds([4.0, 116.0], [21.7, 127.5]);
    const initialized = new WeakSet();
    const dialogHooks = new WeakSet();

    function setup(root) {
        if (initialized.has(root)) return;
        initialized.add(root);
        const mapElement = root.querySelector('[data-location-map]');
        const latInput = root.querySelector('[data-location-latitude]');
        const lngInput = root.querySelector('[data-location-longitude]');
        const status = root.querySelector('[data-location-status]');
        if (!mapElement || !latInput || !lngInput) return;

        const map = L.map(mapElement, {
            center: [12.8797, 121.7740], zoom: 5, minZoom: 5, maxZoom: 18,
            maxBounds: bounds, maxBoundsViscosity: 1.0, scrollWheelZoom: false
        });
        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(map);

        let marker = null;
        function setPoint(point, zoom) {
            if (!bounds.contains(point)) {
                status.textContent = 'Choose a point within the Philippines.';
                status.dataset.state = 'error';
                return;
            }
            if (!marker) {
                marker = L.marker(point, { draggable: true }).addTo(map);
                marker.on('dragend', () => setPoint(marker.getLatLng(), map.getZoom()));
            } else {
                marker.setLatLng(point);
            }
            latInput.value = Number(point.lat).toFixed(7);
            lngInput.value = Number(point.lng).toFixed(7);
            status.textContent = 'Location selected. Drag the marker or click the map to adjust.';
            status.dataset.state = 'selected';
            if (zoom) map.setView(point, zoom);
        }

        const initialLat = Number(latInput.value);
        const initialLng = Number(lngInput.value);
        if (latInput.value !== '' && lngInput.value !== '' && Number.isFinite(initialLat) && Number.isFinite(initialLng) && bounds.contains([initialLat, initialLng])) {
            setPoint(L.latLng(initialLat, initialLng), 14);
        }
        map.on('click', event => setPoint(event.latlng, Math.max(map.getZoom(), 12)));
        mapElement.addEventListener('keydown', event => {
            if ((event.key === 'Enter' || event.key === ' ') && marker) {
                event.preventDefault();
                map.panTo(marker.getLatLng());
            }
        });

        const dialog = root.closest('dialog');
        if (dialog) {
            const refreshMap = () => requestAnimationFrame(() => map.invalidateSize());
            dialog.addEventListener('toggle', refreshMap);
            dialog.addEventListener('close', refreshMap);
            if (window.ResizeObserver) {
                const observer = new ResizeObserver(refreshMap);
                observer.observe(mapElement);
            }
        }
        const form = root.closest('form');
        if (form && !form.dataset.locationValidationBound) {
            form.dataset.locationValidationBound = '1';
            form.addEventListener('submit', event => {
                const pickers = [...form.querySelectorAll('[data-location-picker]')];
                const incomplete = pickers.find(picker => {
                    const lat = picker.querySelector('[data-location-latitude]').value;
                    const lng = picker.querySelector('[data-location-longitude]').value;
                    return lat === '' || lng === '' || !Number.isFinite(Number(lat)) || !Number.isFinite(Number(lng));
                });
                if (incomplete) {
                    event.preventDefault();
                    const message = incomplete.querySelector('[data-location-status]');
                    message.textContent = 'Select a location on the map before saving this post.';
                    message.dataset.state = 'error';
                    incomplete.querySelector('[data-location-map]').focus();
                }
            });
        }
    }

    function initialize() {
        document.querySelectorAll('[data-location-picker]').forEach(root => {
            const dialog = root.closest('dialog');
            if (!dialog || dialog.open) {
                setup(root);
                return;
            }
            if (dialogHooks.has(dialog)) return;
            dialogHooks.add(dialog);
            dialog.addEventListener('toggle', () => {
                if (dialog.open) dialog.querySelectorAll('[data-location-picker]').forEach(setup);
            });
        });
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize);
    else initialize();
})();
