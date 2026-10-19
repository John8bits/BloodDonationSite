document.addEventListener("DOMContentLoaded", function () {
    const layout = document.querySelector(".dashboard-layout");
    const sidebarToggle = document.getElementById("sidebarToggle");
    const scrim = document.getElementById("mobileScrim");
    const sidebarLinks = document.querySelectorAll(".sidebar-link");

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
                const profileMenu = document.querySelector(".profile-menu");
                if (profileMenu) {
                    profileMenu.removeAttribute("open");
                }
            }
        });
    }

    const postType = document.getElementById("postType");
    const eventDateField = document.getElementById("eventDateField");
    const eventDateInput = eventDateField
        ? eventDateField.querySelector('input[name="event_date"]')
        : null;

    function updateEventDateField() {
        if (!postType || !eventDateField || !eventDateInput) {
            return;
        }

        const isEvent = postType.value === "event";
        eventDateField.hidden = !isEvent;
        eventDateInput.required = isEvent;
    }

    if (postType) {
        postType.addEventListener("change", updateEventDateField);
        updateEventDateField();
    }

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

            if (navigator.share) {
                try {
                    await navigator.share({ title: shareTitle, url: shareUrl });
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
                announce("Post link copied.");
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
