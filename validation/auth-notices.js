document.addEventListener("DOMContentLoaded", async function () {
    const currentUrl = new URL(window.location.href);

    if (currentUrl.searchParams.get("login") === "success") {
        currentUrl.searchParams.delete("login");
        window.history.replaceState({}, document.title, currentUrl.pathname + currentUrl.search + currentUrl.hash);

        if (window.Swal && typeof window.Swal.fire === "function") {
            await window.Swal.fire({
                icon: "success",
                title: "Welcome to BLife PH!",
                text: "You have signed in successfully.",
                confirmButtonColor: "#c62828",
                timer: 2200,
                timerProgressBar: true,
                showConfirmButton: false
            });
        }
    }

    document.addEventListener("click", async function (event) {
        const link = event.target.closest("a[href]");
        if (!link || link.dataset.logoutConfirmed === "true") return;

        let targetUrl;
        try {
            targetUrl = new URL(link.href, window.location.href);
        } catch (_) {
            return;
        }

        if (!targetUrl.pathname.replace(/\\/g, "/").endsWith("/logout.php")) return;

        event.preventDefault();
        let confirmed = false;
        if (window.Swal && typeof window.Swal.fire === "function") {
            const result = await window.Swal.fire({
                icon: "question",
                title: "Sign out?",
                text: "Are you sure you want to sign out of BLife PH?",
                showCancelButton: true,
                confirmButtonText: "Sign Out",
                cancelButtonText: "Stay signed in",
                confirmButtonColor: "#c62828",
                reverseButtons: true,
                focusCancel: true
            });
            confirmed = result.isConfirmed;
        } else {
            confirmed = window.confirm("Are you sure you want to sign out of BLife PH?");
        }

        if (confirmed) {
            link.dataset.logoutConfirmed = "true";
            window.location.assign(targetUrl.href);
        }
    });
});
