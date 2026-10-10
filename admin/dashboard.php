<?php

    session_start();
    require_once __DIR__ . "/../config/database.php";
    require_once __DIR__ . "/../config/auth.php";

    require_admin('../login.php', '../dashboard/dashboard.php');

    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    $firstName = $_SESSION['first_name'] ?? '';
    $lastName  = $_SESSION['last_name'] ?? '';

    $pdo = (new Database())->getConnection();

    $allowedBloodTypes = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];

    $search = trim($_GET['q'] ?? '');
    $bloodTypeFilter = trim($_GET['btype'] ?? '');

    $where = [];
    $params = [];

    if ($search !== '') {
        $where[] = "(fname LIKE :search OR lname LIKE :search OR email LIKE :search OR phone LIKE :search)";
        $params[':search'] = '%' . $search . '%';
    }

    if ($bloodTypeFilter !== '' && in_array($bloodTypeFilter, $allowedBloodTypes, true)) {
        $where[] = "btype = :btype";
        $params[':btype'] = $bloodTypeFilter;
    }

    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    $stmt = $pdo->prepare("
        SELECT id, fname, lname, email, btype, phone, role, created_at
        FROM uinfo
        {$whereSql}
        ORDER BY created_at DESC
    ");
    $stmt->execute($params);
    $donors = $stmt->fetchAll();

    $totalStmt = $pdo->query("SELECT COUNT(*) AS total FROM uinfo");
    $totalDonors = (int) $totalStmt->fetch()['total'];

    $adminStmt = $pdo->query("SELECT COUNT(*) AS total FROM uinfo WHERE role = 'admin'");
    $totalAdmins = (int) $adminStmt->fetch()['total'];

    $typeCountStmt = $pdo->query("SELECT btype, COUNT(*) AS total FROM uinfo GROUP BY btype ORDER BY total DESC LIMIT 1");
    $topType = $typeCountStmt->fetch();

    $deleteError = $_SESSION['admin_delete_error'] ?? '';
    unset($_SESSION['admin_delete_error']);

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administrator</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="../assets/dashb.css">
</head>

<body>

    <main class="dashboard">

        <div class="admin-topbar">

            <div>
                <h1>Admin Panel</h1>
                <p class="welcome">
                    Signed in as
                    <span class="user-name">
                        <?= htmlspecialchars($firstName . ' ' . $lastName, ENT_QUOTES, 'UTF-8') ?>
                    </span>
                    <span class="role-badge admin">admin</span>
                </p>
            </div>

            <a href="../logout.php" class="logout-btn" id="logoutButton" style="position:static;">Logout</a>

        </div>

        <div class="admin-stats">

            <div class="admin-stat-card">
                <span class="stat-value"><?= $totalDonors ?></span>
                <span class="stat-label">Registered donors</span>
            </div>

            <div class="admin-stat-card">
                <span class="stat-value"><?= $totalAdmins ?></span>
                <span class="stat-label">Administrators</span>
            </div>

            <div class="admin-stat-card">
                <span class="stat-value"><?= $topType['btype'] ?? '—' ?></span>
                <span class="stat-label">Most common blood type</span>
            </div>

        </div>

        <form class="admin-filter-form" method="get" action="dashboard.php">

            <input
                type="text"
                name="q"
                placeholder="Search name, email or phone"
                value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>"
            >

            <select name="btype">
                <option value="">All blood types</option>
                <?php foreach ($allowedBloodTypes as $type): ?>
                    <option value="<?= $type ?>" <?= $bloodTypeFilter === $type ? 'selected' : '' ?>>
                        <?= $type ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button type="submit">Filter</button>

            <?php if ($search !== '' || $bloodTypeFilter !== ''): ?>
                <a href="dashboard.php" class="delete-btn" style="text-decoration:none; display:inline-flex; align-items:center;">
                    Clear
                </a>
            <?php endif; ?>

        </form>

        <br>

        <div class="admin-table-wrapper">

            <?php if (empty($donors)): ?>

                <p class="empty-state">No donors match this search.</p>

            <?php else: ?>

                <table class="admin-table">

                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Blood type</th>
                            <th>Role</th>
                            <th>Joined</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach ($donors as $donor): ?>

                            <tr>
                                <td><?= htmlspecialchars($donor['fname'] . ' ' . $donor['lname'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($donor['email'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($donor['phone'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><span class="btype-badge"><?= htmlspecialchars($donor['btype'], ENT_QUOTES, 'UTF-8') ?></span></td>
                                <td><span class="role-badge <?= $donor['role'] === 'admin' ? 'admin' : 'user' ?>"><?= htmlspecialchars($donor['role'], ENT_QUOTES, 'UTF-8') ?></span></td>
                                <td><?= htmlspecialchars(date('M j, Y', strtotime($donor['created_at'])), ENT_QUOTES, 'UTF-8') ?></td>
                                <td>
                                    <?php if ((int) $donor['id'] !== (int) $_SESSION['user_id']): ?>
                                        <form method="post" action="delete_user.php" class="delete-user-form" style="display:inline;">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                                            <input type="hidden" name="user_id" value="<?= (int) $donor['id'] ?>">
                                            <button type="submit" class="delete-btn">Remove</button>
                                        </form>
                                    <?php else: ?>
                                        <span style="color:#aaa; font-size:12px; font-weight: 600;">Me</span>
                                    <?php endif; ?>
                                </td>
                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            <?php endif; ?>

        </div>

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
                    window.location.href = logoutButton.getAttribute("href");
                }
            });
        });

        document.querySelectorAll(".delete-user-form").forEach(function (form) {
            form.addEventListener("submit", function (event) {
                event.preventDefault();

                Swal.fire({
                    title: "Remove this donor?",
                    text: "This permanently deletes their account. This cannot be undone.",
                    icon: "warning",
                    showCancelButton: true,
                    confirmButtonText: "Yes, remove",
                    cancelButtonText: "Cancel",
                    confirmButtonColor: "#d62839"
                }).then((result) => {
                    if (result.isConfirmed) {
                        form.submit();
                    }
                });
            });
        });

        <?php if ($deleteError): ?>
            Swal.fire({
                title: "Couldn't remove donor",
                text: <?= json_encode($deleteError) ?>,
                icon: "error"
            });
        <?php endif; ?>

        const urlParams = new URLSearchParams(window.location.search);

        if (urlParams.get("login") === "success") {
            Swal.fire({
                title: "Login Successful!",
                text: "Welcome back, admin.",
                icon: "success",
                confirmButtonText: "Continue"
            }).then(() => {
                window.history.replaceState({}, document.title, window.location.pathname + window.location.search.replace(/[?&]login=success/, ''));
            });
        }

    </script>

</body>

</html>
