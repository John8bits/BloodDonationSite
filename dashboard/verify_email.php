<?php
session_start();
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';
$csrfToken = $_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
$token = $_SERVER['REQUEST_METHOD'] === 'POST' ? ($_POST['token'] ?? '') : ($_GET['token'] ?? '');
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!is_string($token) || !preg_match('/^[A-Za-z0-9_-]{40,50}$/', $token)) {
        $_SESSION['member_flash'] = ['message' => 'This verification link is invalid or expired.', 'type' => 'error'];
        header('Location: dashboard.php?page=settings');
        exit;
    }
    ?><!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Verify email · BLife PH</title><link rel="stylesheet" href="../assets/user-dashboard.css"><body class="dashboard-body"><main class="panel" style="max-width:520px;margin:10vh auto;padding:28px"><h1>Confirm your new email</h1><p>Choose Confirm to finish updating your BLife PH account email.</p><form method="post"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>"><button class="button button-primary" type="submit">Confirm email change</button><?php if (current_user_id()): ?> <a class="button button-secondary" href="dashboard.php?page=settings">Cancel</a><?php endif; ?></form></main></body></html><?php
    exit;
}
if (!isset($_POST['csrf_token'], $_SESSION['csrf_token']) || !is_string($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $_SESSION['member_flash'] = ['message' => 'Your session check failed. Please try again.', 'type' => 'error'];
    header('Location: dashboard.php?page=settings');
    exit;
}
if (!is_string($token) || !preg_match('/^[A-Za-z0-9_-]{40,50}$/', $token)) {
    $_SESSION['member_flash'] = ['message' => 'This verification link is invalid or expired.', 'type' => 'error'];
    header('Location: dashboard.php?page=settings');
    exit;
}
$pdo = null;
try {
    $pdo = (new Database())->getConnection();
    $pdo->beginTransaction();
    $find = $pdo->prepare('SELECT id, user_id, new_email FROM member_email_change_tokens WHERE token_hash = :hash AND expires_at > NOW() LIMIT 1 FOR UPDATE');
    $find->execute([':hash' => hash('sha256', $token)]);
    $request = $find->fetch();
    if (!$request) throw new RuntimeException('This verification link is invalid or expired.');
    $update = $pdo->prepare("UPDATE uinfo SET email = :email WHERE id = :id AND role = 'user' AND NOT EXISTS (SELECT 1 FROM (SELECT id, email FROM uinfo) AS other_account WHERE other_account.email = :email_check AND other_account.id <> :other_id)");
    $update->execute([':email' => $request['new_email'], ':id' => (int)$request['user_id'], ':email_check' => $request['new_email'], ':other_id' => (int)$request['user_id']]);
    if ($update->rowCount() !== 1) throw new RuntimeException('That email address is no longer available. Request a new verification link.');
    $pdo->prepare('DELETE FROM member_email_change_tokens WHERE user_id = :id')->execute([':id' => (int)$request['user_id']]);
    $pdo->commit();
    if ((int)($_SESSION['user_id'] ?? 0) === (int)$request['user_id']) $_SESSION['email'] = $request['new_email'];
    if (isset($_SESSION['user_id'])) $_SESSION['member_flash'] = ['message' => 'Your account email has been updated.', 'type' => 'success'];
} catch (Throwable $error) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    if (isset($_SESSION['user_id'])) $_SESSION['member_flash'] = ['message' => $error instanceof RuntimeException ? $error->getMessage() : 'We could not update your email. Please try again.', 'type' => 'error'];
}
if (isset($_SESSION['user_id'])) { header('Location: dashboard.php?page=settings'); exit; }
?><!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Email verification · BLife PH</title><link rel="stylesheet" href="../assets/user-dashboard.css"><body class="dashboard-body"><main class="panel" style="max-width:520px;margin:10vh auto;padding:28px"><h1><?= isset($request) && $request ? 'Email updated' : 'Email verification failed' ?></h1><p><?= isset($request) && $request ? 'Your BLife PH email address is now verified.' : 'This link may have expired or already been used. Sign in and request a new link from Settings & Privacy.' ?></p><a class="button button-primary" href="../login.php">Sign in</a></main></body></html>
