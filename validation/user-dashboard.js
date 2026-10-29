document.addEventListener("DOMContentLoaded", function () {
    const layout = document.querySelector(".dashboard-layout");
    const sidebarToggle = document.getElementById("sidebarToggle");
    const scrim = document.getElementById("mobileScrim");
    const sidebarLinks = document.querySelectorAll(".sidebar-link");
    const notificationMenu = document.querySelector(".notification-menu");

    document.addEventListener("click", function (event) {
        if (notificationMenu && !notificationMenu.contains(event.target)) {
            notificationMenu.removeAttribute("open");
        }
    });

    document.querySelectorAll("[data-modal-open]").forEach(function (button) {
        button.addEventListener("click", function () {
            const modal = document.getElementById(button.dataset.modalOpen);
            if (modal instanceof HTMLDialogElement) {
                modal.showModal();
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

    function isMobile() {
        return window.matchMedia("(max-width: 760px)").matches;
    }

    function closeSidebar() {
        if (!layout || !sidebarToggle || !scrim) {
            return;
        }

        layout.classList.remove("sidebar-open");
        scrim.classList.remove("visible");
        sidebarToggle.setAttribute("aria-expanded", "false");
    }

    if (layout && sidebarToggle && scrim) {
        if (!isMobile()) {
            sidebarToggle.setAttribute(
                "aria-expanded",
                String(!window.matchMedia("(max-width: 1100px)").matches)
            );
        }

        sidebarToggle.addEventListener("click", function () {
            if (isMobile()) {
                const isOpen = layout.classList.toggle("sidebar-open");
                scrim.classList.toggle("visible", isOpen);
                sidebarToggle.setAttribute("aria-expanded", String(isOpen));
                return;
            }

            if (window.matchMedia("(max-width: 1100px)").matches) {
                const isExpanded = layout.classList.toggle("sidebar-expanded");
                sidebarToggle.setAttribute("aria-expanded", String(isExpanded));
            } else {
                const isCollapsed = layout.classList.toggle("sidebar-collapsed");
                sidebarToggle.setAttribute("aria-expanded", String(!isCollapsed));
            }
        });

        scrim.addEventListener("click", closeSidebar);
        sidebarLinks.forEach(function (link) {
            link.addEventListener("click", closeSidebar);
        });

        window.addEventListener("resize", function () {
            if (!isMobile()) {
                closeSidebar();
                sidebarToggle.setAttribute(
                    "aria-expanded",
                    String(window.matchMedia("(max-width: 1100px)").matches
                        ? layout.classList.contains("sidebar-expanded")
                        : !layout.classList.contains("sidebar-collapsed"))
                );
            } else {
                layout.classList.remove("sidebar-collapsed");
                layout.classList.remove("sidebar-expanded");
            }
        });

        document.addEventListener("keydown", function (event) {
            if (event.key === "Escape") {
                closeSidebar();
                if (notificationMenu) {
                    notificationMenu.removeAttribute("open");
                }
                const profileMenu = document.querySelector(".profile-menu");
                if (profileMenu) {
                    profileMenu.removeAttribute("open");
                }
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
            if (!window.confirm("Delete this post and its comments? This cannot be undone.")) {
                event.preventDefault();
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
