<?php

    session_start();
    require_once __DIR__ . "/../config/auth.php";

    require_login('../login.php');

    // Admins land here too right after login; send them to their own
    // panel instead of showing them the donor dashboard.
    if (is_admin()) {
        header("Location: ../admin/dashboard.php");
        exit;
    }

    $firstName = $_SESSION['first_name'] ?? '';
    $lastName = $_SESSION['last_name'] ?? '';
    $bloodType = $_SESSION['blood_type'] ?? '';

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="../assets/dashb.css">

</head>

<body>

    <main class="dashboard">

        <!-- <div class="blood-icon">
            ♥
        </div> -->

        <h1>
            Welcome to BloodLife
        </h1>

        <p class="welcome">
            Hello,
            <span class="user-name">
                <?= htmlspecialchars($firstName . ' ' . $lastName, ENT_QUOTES, 'UTF-8') ?>
            </span>
        </p>

        <?php if ($bloodType): ?>
            <p class="blood-type-pill">
                Blood type: <strong><?= htmlspecialchars($bloodType, ENT_QUOTES, 'UTF-8') ?></strong>
            </p>
        <?php endif; ?>

        <a href="../logout.php" class="logout-btn" id="logoutButton">Logout</a>

    </main>

    <script>

        const logoutButton = document.getElementById("logoutButton");

        logoutButton.addEventListener("click", function (event) {

            event.preventDefault();

            Swal.fire({
                title: "Logout?",
                text: "Are you sure you want to logout?",
                icon: "warning",
                showCancelButton: true,
                confirmButtonText: "Yes, logout",
                cancelButtonText: "Cancel"
            }).then((result) => {

                if (result.isConfirmed) {

                    window.location.href =
                        logoutButton.getAttribute("href");

                }

            });

        });

        const urlParams = new URLSearchParams(
            window.location.search
        );

        if (urlParams.get("login") === "success") {

            Swal.fire({
                title: "Login Successful!",
                text: "Welcome back to BloodLife.",
                icon: "success",
                confirmButtonText: "Continue"
            }).then(() => {

                window.history.replaceState(
                    {},
                    document.title,
                    window.location.pathname
                );

            }); 

        }

    </script>

</body>

</html>