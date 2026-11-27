document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll("[data-facility-location]").forEach(function (root) {
        const provinceSelect = root.querySelector("[data-facility-province]");
        const citySelect = root.querySelector("[data-facility-city]");
        const barangaySelect = root.querySelector("[data-facility-barangay]");
        const error = root.querySelector("[data-facility-location-error]");
        const endpoint = root.dataset.locationApi;
        let requestSequence = 0;

        function fill(select, rows, placeholder, getName) {
            select.replaceChildren(new Option(placeholder, ""));
            rows.forEach(function (row) {
                const name = getName(row);
                if (name) select.add(new Option(name, name));
            });
            select.disabled = rows.length === 0;
        }

        async function load(url) {
            const response = await fetch(url, { headers: { Accept: "application/json" } });
            const payload = await response.json();
            if (!response.ok || !payload.success) throw new Error(payload.error || "Could not load location choices.");
            return payload.data;
        }

        async function loadProvinces() {
            try {
                const url = new URL(endpoint, window.location.href);
                const data = await load(url);
                fill(provinceSelect, data.provinces || [], "Select province", function (name) { return name; });
            } catch (exception) {
                error.textContent = exception.message || "Could not load provinces. Refresh the page and try again.";
                error.hidden = false;
                fill(provinceSelect, [], "Location list unavailable", function () { return ""; });
            }
        }

        provinceSelect.addEventListener("change", async function () {
            const sequence = ++requestSequence;
            const province = provinceSelect.value;
            error.hidden = true;
            fill(citySelect, [], province ? "Loading cities / municipalities..." : "Select province first", function () { return ""; });
            fill(barangaySelect, [], "Select city / municipality first", function () { return ""; });
            if (!province) return;
            try {
                const url = new URL(endpoint, window.location.href);
                url.searchParams.set("province", province);
                const data = await load(url);
                if (sequence !== requestSequence || province !== provinceSelect.value) return;
                fill(citySelect, data.cities || [], "Select city / municipality", function (name) { return name; });
            } catch (exception) {
                if (sequence !== requestSequence) return;
                error.textContent = exception.message || "Could not load cities / municipalities.";
                error.hidden = false;
                fill(citySelect, [], "Location list unavailable", function () { return ""; });
            }
        });

        citySelect.addEventListener("change", async function () {
            const sequence = ++requestSequence;
            const province = provinceSelect.value;
            const city = citySelect.value;
            error.hidden = true;
            fill(barangaySelect, [], city ? "Loading barangays..." : "Select city / municipality first", function () { return ""; });
            if (!province || !city) return;
            try {
                const url = new URL(endpoint, window.location.href);
                url.searchParams.set("province", province);
                url.searchParams.set("city", city);
                const data = await load(url);
                if (sequence !== requestSequence || province !== provinceSelect.value || city !== citySelect.value) return;
                fill(barangaySelect, data.barangays || [], "Select barangay (optional)", function (barangay) { return barangay.name; });
            } catch (exception) {
                if (sequence !== requestSequence) return;
                error.textContent = exception.message || "Could not load barangays.";
                error.hidden = false;
                fill(barangaySelect, [], "Barangay list unavailable", function () { return ""; });
            }
        });

        loadProvinces();
    });
});
