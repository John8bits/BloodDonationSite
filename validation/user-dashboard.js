document.addEventListener("DOMContentLoaded", function () {
    const memberHeader = document.querySelector(".member-topbar");
    const memberNavToggle = document.getElementById("memberNavToggle");
    const memberNavigation = document.getElementById("memberMainNavigation");

    const profileLocationForm = document.querySelector("[data-profile-location-form]");
    if (profileLocationForm) {
        const provinceSelect = profileLocationForm.querySelector("[data-profile-province]");
        const citySelect = profileLocationForm.querySelector("[data-profile-city]");
        const barangaySelect = profileLocationForm.querySelector("[data-profile-barangay]");
        const status = profileLocationForm.querySelector("[data-profile-location-status]");
        const api = profileLocationForm.dataset.locationApi;
        const currentCity = profileLocationForm.dataset.currentCity || "";
        const currentBarangay = profileLocationForm.dataset.currentBarangay || "";
        let profileLocationRequest = 0;

        function updateProfileLocationStatus() {
            const selectedBarangay = barangaySelect.options[barangaySelect.selectedIndex];
            if (selectedBarangay && selectedBarangay.textContent.includes("approximate city point")) {
                status.textContent = "No barangay map point is available here. The map will use the approximate city center.";
            } else {
                status.textContent = "Your profile location is private. Public map points are approximate.";
            }
        }

        function fillProfileLocationSelect(select, rows, placeholder, selectedValue) {
            select.replaceChildren(new Option(placeholder, ""));
            rows.forEach(function (row) {
                const isRecord = typeof row === "object";
                const name = isRecord ? row.name : row;
                if (!name) return;
                const label = isRecord && !row.has_map_point ? name + " (approximate city point)" : name;
                select.add(new Option(label, name));
            });
            select.disabled = rows.length === 0;
            if (selectedValue && Array.from(select.options).some(function (option) { return option.value === selectedValue; })) {
                select.value = selectedValue;
            }
        }

        async function loadProfileLocations(cityValue, barangayValue) {
            const requestId = ++profileLocationRequest;
            const province = provinceSelect.value;
            fillProfileLocationSelect(citySelect, [], province ? "Loading cities / municipalities…" : "Select a province first");
            fillProfileLocationSelect(barangaySelect, [], "Select a city / municipality first");
            if (!province) return;
            try {
                const cityUrl = new URL(api, window.location.href);
                cityUrl.searchParams.set("province", province);
                const cityResponse = await fetch(cityUrl, { headers: { Accept: "application/json" } });
                const cityPayload = await cityResponse.json();
                if (requestId !== profileLocationRequest || province !== provinceSelect.value) return;
                if (!cityResponse.ok || !cityPayload.success) throw new Error(cityPayload.error || "Could not load city / municipality choices.");
                fillProfileLocationSelect(citySelect, cityPayload.data.cities || [], "Select city / municipality", cityValue);
                if (!citySelect.value) return;
                const barangayUrl = new URL(api, window.location.href);
                barangayUrl.searchParams.set("province", province);
                barangayUrl.searchParams.set("city", citySelect.value);
                const barangayResponse = await fetch(barangayUrl, { headers: { Accept: "application/json" } });
                const barangayPayload = await barangayResponse.json();
                if (requestId !== profileLocationRequest || province !== provinceSelect.value || citySelect.value !== cityValue) return;
                if (!barangayResponse.ok || !barangayPayload.success) throw new Error(barangayPayload.error || "Could not load barangay choices.");
                fillProfileLocationSelect(barangaySelect, barangayPayload.data.barangays || [], "Select barangay (optional)", barangayValue);
                updateProfileLocationStatus();
            } catch (error) {
                if (requestId !== profileLocationRequest) return;
                fillProfileLocationSelect(citySelect, [], "Location list unavailable");
                fillProfileLocationSelect(barangaySelect, [], "Select a city / municipality first");
                status.textContent = error.message || "Could not load Philippine location choices.";
            }
        }

        provinceSelect.addEventListener("change", function () { loadProfileLocations("", ""); });
        citySelect.addEventListener("change", function () { loadProfileLocations(citySelect.value, ""); });
        barangaySelect.addEventListener("change", updateProfileLocationStatus);
        if (provinceSelect.value) loadProfileLocations(currentCity, currentBarangay);
    }

    document.querySelectorAll(".my-post-sections").forEach(function (tabContainer) {
        const tabs = Array.from(tabContainer.querySelectorAll("[data-my-post-tab]"));
        const panels = Array.from(tabContainer.querySelectorAll("[data-my-post-panel]"));

        function activateTab(tab, moveFocus) {
            const category = tab.dataset.myPostTab;
            tabs.forEach(function (item) {
                const isActive = item === tab;
                item.setAttribute("aria-selected", String(isActive));
                item.tabIndex = isActive ? 0 : -1;
            });
            panels.forEach(function (panel) {
                panel.hidden = panel.dataset.myPostPanel !== category;
            });
            if (moveFocus) {
                tab.focus();
            }
        }

        tabs.forEach(function (tab, index) {
            tab.addEventListener("click", function () {
                activateTab(tab, false);
            });
            tab.addEventListener("keydown", function (event) {
                let nextIndex = index;
                if (event.key === "ArrowRight") {
                    nextIndex = (index + 1) % tabs.length;
                } else if (event.key === "ArrowLeft") {
                    nextIndex = (index - 1 + tabs.length) % tabs.length;
                } else if (event.key === "Home") {
                    nextIndex = 0;
                } else if (event.key === "End") {
                    nextIndex = tabs.length - 1;
                } else {
                    return;
                }
                event.preventDefault();
                activateTab(tabs[nextIndex], true);
            });
        });
    });

    document.addEventListener("click", function (event) {
        document.querySelectorAll(".header-dropdown[open]").forEach(function (menu) {
            if (!menu.contains(event.target)) menu.removeAttribute("open");
        });
    });

    document.querySelectorAll("[data-modal-open]").forEach(function (button) {
        button.addEventListener("click", function () {
            const managementMenu = button.closest(".content-management-menu");
            if (managementMenu) {
                managementMenu.removeAttribute("open");
            }
            const modal = document.getElementById(button.dataset.modalOpen);
            if (modal instanceof HTMLDialogElement) {
                modal.showModal();
            }
        });
    });

    document.addEventListener("click", function (event) {
        document.querySelectorAll(".content-management-menu[open]").forEach(function (menu) {
            if (!menu.contains(event.target)) {
                menu.removeAttribute("open");
            }
        });
    });

    document.querySelectorAll(".dashboard-modal").forEach(function (modal) {
        modal.querySelectorAll("[data-modal-close]").forEach(function (button) {
            button.addEventListener("click", function () {
                modal.close();
            });
        });

        modal.addEventListener("click", function (event) {
            if (event.target === modal) {
                modal.close();
            }
        });
    });

    document.querySelectorAll("form[data-confirm-action]").forEach(function (form) {
        form.addEventListener("submit", function (event) {
            if (form.dataset.confirmed === "true") {
                delete form.dataset.confirmed;
                const submitButton = event.submitter;
                if (submitButton) submitButton.disabled = true;
                return;
            }
            event.preventDefault();
            const message = form.dataset.confirmAction || "Continue with this action?";
            const confirm = function () {
                form.dataset.confirmed = "true";
                form.requestSubmit();
            };
            if (window.Swal && typeof window.Swal.fire === "function") {
                window.Swal.fire({
                    title: message,
                    text: "Please confirm that you want to continue.",
                    icon: "warning",
                    showCancelButton: true,
                    confirmButtonText: "Continue",
                    cancelButtonText: "Cancel",
                    confirmButtonColor: "#b42332",
                    reverseButtons: true,
                    focusCancel: true
                }).then(function (result) { if (result.isConfirmed) confirm(); });
            } else if (window.confirm(message)) {
                confirm();
            }
        });
    });

    document.querySelectorAll("[data-disclosure-toggle]").forEach(function (button) {
        button.addEventListener("click", function () {
            const target = document.getElementById(button.dataset.disclosureToggle);
            if (!target) {
                return;
            }

            target.hidden = !target.hidden;
            document.querySelectorAll("[data-disclosure-toggle]").forEach(function (toggle) {
                if (toggle.dataset.disclosureToggle === target.id) {
                    toggle.setAttribute("aria-expanded", String(!target.hidden));
                }
            });
        });
    });

    if (window.location.hash === "#postComposer") {
        const postModal = document.getElementById("createPostModal");
        if (postModal instanceof HTMLDialogElement) {
            postModal.showModal();
        }
    }

    function closeMemberNavigation() {
        if (!memberHeader || !memberNavToggle) return;
        memberHeader.classList.remove("member-nav-open");
        memberNavToggle.setAttribute("aria-expanded", "false");
        memberNavToggle.setAttribute("aria-label", "Open navigation");
    }

    if (memberHeader && memberNavToggle && memberNavigation) {
        memberNavToggle.addEventListener("click", function () {
            const isOpen = memberHeader.classList.toggle("member-nav-open");
            memberNavToggle.setAttribute("aria-expanded", String(isOpen));
            memberNavToggle.setAttribute("aria-label", isOpen ? "Close navigation" : "Open navigation");
            memberNavToggle.querySelector("i").className = isOpen ? "fa-solid fa-xmark" : "fa-solid fa-bars";
        });

        memberNavigation.querySelectorAll("a").forEach(function (link) {
            link.addEventListener("click", closeMemberNavigation);
        });

        window.addEventListener("resize", function () {
            if (window.matchMedia("(min-width: 761px)").matches) closeMemberNavigation();
        });

        document.addEventListener("keydown", function (event) {
            if (event.key === "Escape") {
                closeMemberNavigation();
                document.querySelectorAll(".header-dropdown[open]").forEach(function (menu) {
                    menu.removeAttribute("open");
                });
            }
        });
    }

    document.querySelectorAll("[data-post-composer]").forEach(function (form) {
        const postType = form.elements.post_type;
        const donationFields = form.querySelector("[data-donation-fields]");
        const eventDateField = form.querySelector("[data-event-date-field]");
        const eventDateInput = eventDateField
            ? eventDateField.querySelector('input[name="event_date"]')
            : null;
        const donationBloodType = donationFields
            ? donationFields.querySelector('select[name="donation_blood_type"]')
            : null;
        const donationLocation = donationFields
            ? donationFields.querySelector('input[name="donation_location"]')
            : null;

        function updatePostFields() {
            const type = postType ? postType.value : "";
            const isEvent = type === "event";
            const isDonation = type === "donation";
            if (eventDateField) {
                eventDateField.hidden = !isEvent;
            }
            if (eventDateInput) {
                eventDateInput.required = isEvent;
            }
            if (donationFields) {
                donationFields.hidden = !isDonation;
            }
            if (donationBloodType) {
                donationBloodType.required = isDonation;
            }
            if (donationLocation) {
                donationLocation.required = isDonation;
            }
        }

        if (postType && postType.tagName === "SELECT") {
            postType.addEventListener("change", updatePostFields);
        }
        updatePostFields();
    });

    function announce(message) {
        let status = document.getElementById("dashboardStatus");
        if (!status) {
            status = document.createElement("div");
            status.id = "dashboardStatus";
            status.className = "dashboard-toast";
            status.setAttribute("role", "status");
            status.setAttribute("aria-live", "polite");
            document.body.appendChild(status);
        }
        status.textContent = message;
        status.classList.add("visible");
        window.clearTimeout(status.hideTimer);
        status.hideTimer = window.setTimeout(function () {
            status.classList.remove("visible");
        }, 3500);
    }

    document.querySelectorAll(".share-post-button").forEach(function (button) {
        button.addEventListener("click", async function () {
            const shareUrl = new URL(button.dataset.shareUrl, window.location.href).href;
            const shareTitle = button.dataset.shareTitle || "BloodLife community post";
            const shareForm = document.getElementById(button.dataset.shareForm);

            async function recordShare() {
                if (!shareForm) {
                    return;
                }

                try {
                    const response = await fetch(shareForm.action, {
                        method: "POST",
                        body: new FormData(shareForm),
                        credentials: "same-origin"
                    });
                    const result = await response.json();
                    if (!response.ok || !result.success) {
                        throw new Error("The share notification request failed.");
                    }
                    return true;
                } catch (error) {
                    console.error("Could not record the post share:", error);
                    announce("The post was shared, but its notification could not be recorded.");
                    return false;
                }
            }

            if (navigator.share) {
                try {
                    await navigator.share({ title: shareTitle, url: shareUrl });
                    await recordShare();
                    return;
                } catch (error) {
                    if (error && error.name === "AbortError") {
                        return;
                    }
                }
            }

            if (!navigator.clipboard || !navigator.clipboard.writeText) {
                announce("Sharing is not available in this browser. Copy the page address to share this post.");
                return;
            }

            try {
                await navigator.clipboard.writeText(shareUrl);
                if (await recordShare()) {
                    announce("Post link copied.");
                }
            } catch (error) {
                console.error("Could not copy the post link:", error);
                announce("Could not copy the post link. Please copy the page address manually.");
            }
        });
    });

    document.querySelectorAll(".confirm-cancel-form").forEach(function (form) {
        form.addEventListener("submit", function (event) {
            const submitter = event.submitter;
            if (submitter && submitter.value === "cancelled" &&
                !window.confirm("Cancel this blood request? You can no longer receive responses to it.")) {
                event.preventDefault();
            }
        });
    });

    document.querySelectorAll(".confirm-delete-form").forEach(function (form) {
        form.addEventListener("submit", function (event) {
            if (form.dataset.deleteConfirmed === "true") {
                delete form.dataset.deleteConfirmed;
                return;
            }
            event.preventDefault();
            const isRequest = form.querySelector('[name="action"]')?.value === "delete_request";
            const title = isRequest ? "Delete this blood request?" : "Delete this post?";
            const detail = isRequest
                ? "Requests with donor responses or conversations are protected and cannot be deleted."
                : "This removes the post and its comments. Donation offers with message history are protected.";
            if (window.Swal && typeof window.Swal.fire === "function") {
                window.Swal.fire({
                    title: title,
                    text: detail,
                    icon: "warning",
                    showCancelButton: true,
                    confirmButtonText: "Delete",
                    cancelButtonText: "Keep it",
                    confirmButtonColor: "#b42332",
                    reverseButtons: true,
                    focusCancel: true
                }).then(function (result) {
                    if (result.isConfirmed) {
                        form.dataset.deleteConfirmed = "true";
                        form.requestSubmit();
                    }
                });
            } else if (window.confirm(title + " " + detail)) {
                form.dataset.deleteConfirmed = "true";
                form.requestSubmit();
            }
        });
    });

    document.querySelectorAll(".confirm-report-form").forEach(function (form) {
        form.addEventListener("submit", function (event) {
            if (!window.confirm("Report this post to the BloodLife administrators for review?")) {
                event.preventDefault();
            }
        });
    });
});
