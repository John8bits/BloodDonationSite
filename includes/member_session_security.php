<?php

function member_session_table_exists(PDO $pdo): bool
{
    static $available = null;
    if ($available !== null) return $available;
    $check = $pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'member_sessions'");
    $check->execute();
    return $available = (int) $check->fetchColumn() > 0;
}

/** Register older sessions on first use, and reject sessions the member revoked. */
function member_session_is_active(PDO $pdo, int $userId): bool
{
    if (!member_session_table_exists($pdo)) return true;
    $sessionHash = hash('sha256', session_id());
    $find = $pdo->prepare('SELECT id, user_id, revoked_at FROM member_sessions WHERE session_hash = :hash LIMIT 1');
    $find->execute([':hash' => $sessionHash]);
    $record = $find->fetch();
    if ($record) {
        if ((int) $record['user_id'] !== $userId || $record['revoked_at'] !== null) return false;
        $pdo->prepare('UPDATE member_sessions SET last_seen_at = NOW() WHERE id = :id')->execute([':id' => $record['id']]);
        return true;
    }
    $insert = $pdo->prepare('INSERT INTO member_sessions (user_id, session_hash, user_agent) VALUES (:user_id, :hash, :agent)');
    $insert->execute([
        ':user_id' => $userId,
        ':hash' => $sessionHash,
        ':agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255)
    ]);
    return true;
}

