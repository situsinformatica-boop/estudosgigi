<?php
function requireAuth(): void {
    if (empty($_SESSION['user_id'])) {
        // Tenta remember me
        if (!empty($_COOKIE['remember_token'])) {
            $hash = hash('sha256', $_COOKIE['remember_token']);
            $stmt = db()->prepare("
                SELECT u.* FROM remember_tokens rt
                JOIN users u ON u.id = rt.user_id
                WHERE rt.token_hash = ? AND rt.expires_at > NOW() AND u.is_active = 1
                LIMIT 1
            ");
            $stmt->execute([$hash]);
            $user = $stmt->fetch();
            if ($user) {
                session_regenerate_id(true);
                $_SESSION['user_id']   = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user']      = array_diff_key($user, ['password_hash' => '']);
                // Renova token
                db()->prepare("DELETE FROM remember_tokens WHERE token_hash = ?")->execute([$hash]);
                $newToken = bin2hex(random_bytes(32));
                $newHash  = hash('sha256', $newToken);
                db()->prepare("INSERT INTO remember_tokens (user_id, token_hash, expires_at) VALUES (?,?,?)")
                    ->execute([$user['id'], $newHash, date('Y-m-d H:i:s', strtotime('+30 days'))]);
                setcookie('remember_token', $newToken, ['expires' => strtotime('+30 days'), 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
                return;
            }
            setcookie('remember_token', '', time() - 3600, '/');
        }
        header('Location: ' . BASE_URL . '/index.php?page=login');
        exit;
    }
}

function requireAdmin(): void {
    requireAuth();
    if (($_SESSION['user']['role'] ?? '') !== 'admin') {
        header('Location: ' . BASE_URL . '/index.php?page=dashboard');
        exit;
    }
}

function uid(): int {
    return (int)($_SESSION['user_id'] ?? 0);
}

function currentUser(): ?array {
    return $_SESSION['user'] ?? null;
}
