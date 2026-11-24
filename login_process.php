<?php

session_start();

require_once __DIR__ . "/config/database.php";

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    echo json_encode([
        'success' => false,
        'message' => 'Invalid request.'
    ]);

    exit;
}

// CSRF validation
if (
    !isset($_POST['csrf_token']) ||
    !isset($_SESSION['csrf_token']) ||
    !is_string($_POST['csrf_token']) ||
    !hash_equals(
        $_SESSION['csrf_token'],
        $_POST['csrf_token']
    )
) {

    echo json_encode([
        'success' => false,
        'message' => 'Invalid login request.'
    ]);

    exit;
}


$emailValue = $_POST['login'] ?? '';
$password = $_POST['password'] ?? '';

if (!is_string($emailValue) || !is_string($password)) {
    echo json_encode([
        'success' => false,
        'message' => 'Enter a valid email and password.'
    ]);
    exit;
}

$email = trim($emailValue);

$errors = [];

if ($email === '' && $password === '') {

    echo json_encode([
        'success' => false,
        'message' => 'Email and password are required.'
    ]);

    exit;

}


if ($email === '') {

    $errors['email'] = "Email address is required.";

} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

    $errors['email'] = "Please enter a valid email address.";

}

if ($password === '') {

    $errors['password'] = "Password is required.";

}

if (!empty($errors)) {

    echo json_encode([
        'success' => false,
        'validation' => true,
        'errors' => $errors
    ]);

    exit;
}


try {

    $pdo = (new Database())->getConnection();
    $accountStatusCheck = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'uinfo' AND COLUMN_NAME = 'account_status'"
    );
    $accountStatusCheck->execute();
    $accountStatusAvailable = (int) $accountStatusCheck->fetchColumn() > 0;
    $accountStatusSelect = $accountStatusAvailable ? ', account_status' : '';
    $deactivationCheck = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'uinfo' AND COLUMN_NAME = 'member_deactivated_at'");
    $deactivationCheck->execute();
    $deactivationAvailable = (int) $deactivationCheck->fetchColumn() > 0;
    $deactivationSelect = $deactivationAvailable ? ', u.member_deactivated_at' : ', NULL AS member_deactivated_at';
    $clinicFieldsAvailable = false;
    $clinicTableCheck = $pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'clinic_profiles'");
    $clinicTableCheck->execute();
    $clinicFieldsAvailable = (int) $clinicTableCheck->fetchColumn() > 0;
    $clinicSelect = $clinicFieldsAvailable ? ', cp.is_verified AS clinic_verified' : ', 0 AS clinic_verified';
    $profileCompletionAvailable = false;
    $profileColumnCheck = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'uinfo' AND COLUMN_NAME IN ('profile_completed_at','must_change_password')");
    $profileColumnCheck->execute();
    $profileCompletionAvailable = (int) $profileColumnCheck->fetchColumn() === 2;
    $extraAccountSelect = $profileCompletionAvailable ? ', u.profile_completed_at, u.must_change_password' : ', NULL AS profile_completed_at, 0 AS must_change_password';


    $stmt = $pdo->prepare("
        SELECT u.id, u.fname, u.lname, u.email, u.btype, u.phone, u.city, u.password, u.role
            {$accountStatusSelect}{$extraAccountSelect}{$clinicSelect}{$deactivationSelect}
        FROM uinfo u
        " . ($clinicFieldsAvailable ? 'LEFT JOIN clinic_profiles cp ON cp.user_id = u.id' : '') . "
        WHERE u.email = :email
        LIMIT 1
    ");


    $stmt->execute([
        ':email' => $email
    ]);


    $user = $stmt->fetch();


    // Check email/password
    if (
        !$user ||
        !password_verify(
            $password,
            $user['password']
        )
    ) {

        echo json_encode([
            'success' => false,
            'message' => 'Invalid email or password.'
        ]);

        exit;
    }

    if (($user['account_status'] ?? 'active') !== 'active') {
        echo json_encode([
            'success' => false,
            'message' => 'This account is currently suspended. Contact the platform administrators for assistance.'
        ]);
        exit;
    }
    if (($user['member_deactivated_at'] ?? null) !== null) {
        echo json_encode([
            'success' => false,
            'message' => 'This account was deactivated. Contact a BLife PH administrator if you need it reactivated.'
        ]);
        exit;
    }


    // Login successful
    session_regenerate_id(true);

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['first_name'] = $user['fname'];
    $_SESSION['last_name'] = $user['lname'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['blood_type'] = $user['btype'];
    $_SESSION['phone'] = $user['phone'];
    $_SESSION['role'] = $user['role'] ?? 'user';
    $_SESSION['must_change_password'] = (int) ($user['must_change_password'] ?? 0);
    $_SESSION['clinic_verified'] = (int) ($user['clinic_verified'] ?? 0);
    $_SESSION['profile_completed_at'] = $user['profile_completed_at'] ?? null;

    $sessionTableCheck = $pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'member_sessions'");
    $sessionTableCheck->execute();
    if ((int) $sessionTableCheck->fetchColumn() > 0 && $_SESSION['role'] === 'user') {
        $sessionHash = hash('sha256', session_id());
        $recordSession = $pdo->prepare('INSERT INTO member_sessions (user_id, session_hash, user_agent) VALUES (:user_id, :session_hash, :user_agent)');
        $recordSession->execute([
            ':user_id' => (int) $user['id'],
            ':session_hash' => $sessionHash,
            ':user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255)
        ]);
        $pdo->prepare('UPDATE member_sessions SET revoked_at = COALESCE(revoked_at, NOW()) WHERE user_id = :user_id AND last_seen_at < DATE_SUB(NOW(), INTERVAL 30 DAY)')->execute([':user_id' => (int) $user['id']]);
    }


    unset($_SESSION['login_errors']);
    unset($_SESSION['login_old']);

    // Send admins to the admin panel, everyone else to their dashboard.
    $redirect = $_SESSION['role'] === 'admin'
        ? 'admin/dashboard.php?login=success'
        : ($_SESSION['role'] === 'clinic'
            ? 'clinic/dashboard.php?login=success'
            : ((trim((string) $user['fname']) === '' || trim((string) $user['lname']) === '' || trim((string) $user['phone']) === '' || empty($user['btype']) || trim((string) ($user['city'] ?? '')) === '')
                ? 'dashboard/dashboard.php?page=profile&welcome=1'
                : 'dashboard/dashboard.php?login=success'));

    echo json_encode([
        'success' => true,
        'message' => 'Submitting login...',
        'redirect' => $redirect
    ]);

    exit;


} catch (PDOException $e) {

    error_log($e->getMessage());

    echo json_encode([
        'success' => false,
        'message' => 'Unable to login at this time.'
    ]);

    exit;
}

?>
