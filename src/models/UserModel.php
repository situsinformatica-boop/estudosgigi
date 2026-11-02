<?php
class UserModel {

    public function register(string $name, string $email, string $password): array {
        if ($this->findByEmail($email))
            return ['success' => false, 'error' => 'Este e-mail já está cadastrado.'];
        if (strlen($password) < 6)
            return ['success' => false, 'error' => 'A senha deve ter pelo menos 6 caracteres.'];

        $colors = ['#6366f1','#22c55e','#f59e0b','#ef4444','#06b6d4','#ec4899','#8b5cf6','#14b8a6'];
        $color  = $colors[array_rand($colors)];
        $hash   = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

        db()->prepare("INSERT INTO users (name, email, password_hash, avatar_color, theme) VALUES (?,?,?,?,'dark')")
            ->execute([$name, strtolower(trim($email)), $hash, $color]);
        $userId = (int)db()->lastInsertId();

        // INSERT IGNORE evita erro de chave duplicada se já existir
        db()->prepare("INSERT IGNORE INTO user_stats (user_id) VALUES (?)")->execute([$userId]);
        db()->prepare("INSERT IGNORE INTO pomodoro_config (user_id) VALUES (?)")->execute([$userId]);

        return ['success' => true, 'user_id' => $userId];
    }

    public function login(string $email, string $password, bool $remember = false): array {
        $user = $this->findByEmail($email);
        if (!$user || !$user['is_active'])
            return ['success' => false, 'error' => 'E-mail ou senha incorretos.'];
        if (!password_verify($password, $user['password_hash']))
            return ['success' => false, 'error' => 'E-mail ou senha incorretos.'];

        db()->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);
        $this->startSession($user);

        if ($remember) {
            $token = bin2hex(random_bytes(32));
            $hash  = hash('sha256', $token);
            db()->prepare("INSERT INTO remember_tokens (user_id, token_hash, expires_at) VALUES (?,?,?)")
                ->execute([$user['id'], $hash, date('Y-m-d H:i:s', strtotime('+30 days'))]);
            setcookie('remember_token', $token, [
                'expires' => strtotime('+30 days'), 'path' => '/', 'httponly' => true, 'samesite' => 'Lax',
            ]);
        }
        return ['success' => true, 'user' => $user];
    }

    public function logout(): void {
        if (!empty($_COOKIE['remember_token'])) {
            $hash = hash('sha256', $_COOKIE['remember_token']);
            db()->prepare("DELETE FROM remember_tokens WHERE token_hash = ?")->execute([$hash]);
            setcookie('remember_token', '', time() - 3600, '/');
        }
        $_SESSION = []; session_destroy();
    }

    public function updateProfile(int $id, array $data): array {
        $exists = db()->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $exists->execute([$data['email'], $id]);
        if ($exists->fetch()) return ['success' => false, 'error' => 'E-mail já em uso.'];

        $validThemes = ['dark','light','midnight','forest','ocean','sakura','candy','blush','rosegold','hotpink'];
        $theme = in_array($data['theme'] ?? '', $validThemes) ? $data['theme'] : 'dark';

        db()->prepare("UPDATE users SET name=?, email=?, avatar_color=?, theme=? WHERE id=?")
            ->execute([$data['name'], $data['email'], $data['avatar_color'], $theme, $id]);

        $_SESSION['user'] = $this->findById($id);
        return ['success' => true];
    }

    public function uploadAvatar(int $userId, array $file): array {
        if ($file['error'] !== UPLOAD_ERR_OK)
            return ['success' => false, 'error' => 'Erro no upload.'];
        if ($file['size'] > 3 * 1024 * 1024)
            return ['success' => false, 'error' => 'Imagem muito grande. Máximo: 3 MB.'];

        $allowedMimes = ['image/jpeg','image/png','image/gif','image/webp'];
        $mime = mime_content_type($file['tmp_name']);
        if (!in_array($mime, $allowedMimes))
            return ['success' => false, 'error' => 'Formato inválido. Use JPG, PNG, GIF ou WebP.'];

        $uploadDir = APP_ROOT . '/public/uploads/avatars/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        $current = $this->findById($userId);
        if (!empty($current['avatar_path'])) {
            $old = APP_ROOT . '/public/' . $current['avatar_path'];
            if (file_exists($old)) unlink($old);
        }

        $ext = match($mime) {
            'image/jpeg' => 'jpg', 'image/png' => 'png',
            'image/gif'  => 'gif', 'image/webp' => 'webp', default => 'jpg',
        };
        $filename   = 'avatar_' . $userId . '_' . time() . '.' . $ext;
        $avatarPath = 'uploads/avatars/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $uploadDir . $filename))
            return ['success' => false, 'error' => 'Falha ao salvar imagem.'];

        db()->prepare("UPDATE users SET avatar_path=? WHERE id=?")->execute([$avatarPath, $userId]);
        $_SESSION['user'] = $this->findById($userId);
        return ['success' => true, 'path' => $avatarPath];
    }

    public function removeAvatar(int $userId): void {
        $user = $this->findById($userId);
        if (!empty($user['avatar_path'])) {
            $old = APP_ROOT . '/public/' . $user['avatar_path'];
            if (file_exists($old)) unlink($old);
        }
        db()->prepare("UPDATE users SET avatar_path=NULL WHERE id=?")->execute([$userId]);
        $_SESSION['user'] = $this->findById($userId);
    }

    public function changePassword(int $id, string $current, string $new): array {
        $user = $this->findById($id);
        if (!password_verify($current, $user['password_hash']))
            return ['success' => false, 'error' => 'Senha atual incorreta.'];
        if (strlen($new) < 6)
            return ['success' => false, 'error' => 'Nova senha deve ter ao menos 6 caracteres.'];
        db()->prepare("UPDATE users SET password_hash=? WHERE id=?")
            ->execute([password_hash($new, PASSWORD_BCRYPT, ['cost' => 12]), $id]);
        return ['success' => true];
    }

    public function findByEmail(string $email): ?array {
        $stmt = db()->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([strtolower(trim($email))]);
        return $stmt->fetch() ?: null;
    }

    public function findById(int $id): ?array {
        $stmt = db()->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function getAll(): array {
        return db()->query("
            SELECT u.*, COUNT(DISTINCT ss.id) AS total_sessions,
                   COALESCE(SUM(ss.duration_min),0) AS total_minutes
            FROM users u
            LEFT JOIN study_sessions ss ON ss.user_id = u.id AND ss.ended_at IS NOT NULL
            GROUP BY u.id ORDER BY u.created_at DESC
        ")->fetchAll();
    }

    private function startSession(array $user): void {
        session_regenerate_id(true);
        $_SESSION['user_id']   = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user']      = array_diff_key($user, ['password_hash' => '']);
    }
}
