<?php
session_start();
require_once __DIR__ . '/../config/auth.php';
require_admin('../login.php', '../dashboard/dashboard.php');

$_SESSION['admin_flash'] = [
    'message' => 'Use Member Management to review and remove member accounts.',
    'type' => 'info'
];
header('Location: dashboard.php?page=members');
exit;
