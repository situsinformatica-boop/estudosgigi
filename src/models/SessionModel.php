<?php
class SessionModel {
    private int $uid;
    public function __construct() { $this->uid = uid(); }

    public function start(int $subjectId, ?int $topicId, string $type = 'study'): int {
        db()->prepare("INSERT INTO study_sessions (user_id,subject_id,topic_id,started_at,session_type) VALUES (?,?,?,NOW(),?)")
            ->execute([$this->uid, $subjectId, $topicId, $type]);
        return (int)db()->lastInsertId();
    }

    public function finish(int $id, int $quality, string $notes = '', int $pomodoros = 0): bool {
        return db()->prepare("
            UPDATE study_sessions
            SET ended_at=NOW(), quality=?, notes=?, pomodoros_done=?
            WHERE id=? AND user_id=? AND ended_at IS NULL
        ")->execute([$quality, $notes, $pomodoros, $id, $this->uid]);
    }

    /**
     * Garante que ended_at seja correto a partir dos segundos decorridos.
     * Chamado pelo heartbeat e pelo finish quando elapsed_seconds é fornecido.
     */
    public function forceEndAt(int $id, int $elapsedSeconds): void {
        if ($elapsedSeconds <= 0) return;
        db()->prepare("
            UPDATE study_sessions
            SET ended_at = DATE_ADD(started_at, INTERVAL ? SECOND)
            WHERE id=? AND user_id=?
        ")->execute([$elapsedSeconds, $id, $this->uid]);
    }

    public function getActive(): ?array {
        $stmt = db()->prepare("
            SELECT ss.*, s.name AS subject_name, s.color, t.name AS topic_name
            FROM study_sessions ss
            JOIN subjects s ON s.id = ss.subject_id
            LEFT JOIN topics t ON t.id = ss.topic_id
            WHERE ss.ended_at IS NULL AND ss.user_id = ?
            ORDER BY ss.started_at DESC LIMIT 1
        ");
        $stmt->execute([$this->uid]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Retorna minutos estudados hoje usando duration_sec se disponível
     * (mais preciso que duration_min para sessões curtas)
     */
    public function minutesToday(): int {
        $stmt = db()->prepare("
            SELECT COALESCE(
                SUM(
                    CASE
                        WHEN duration_sec IS NOT NULL AND duration_sec > 0
                            THEN GREATEST(1, CEIL(duration_sec / 60.0))
                        WHEN duration_min IS NOT NULL AND duration_min > 0
                            THEN duration_min
                        ELSE 0
                    END
                ), 0
            )
            FROM study_sessions
            WHERE DATE(started_at) = CURDATE()
              AND ended_at IS NOT NULL
              AND user_id = ?
        ");
        // Fallback se duration_sec não existe ainda
        try {
            $stmt->execute([$this->uid]);
            return (int)$stmt->fetchColumn();
        } catch (\PDOException $e) {
            $stmt2 = db()->prepare("SELECT COALESCE(SUM(GREATEST(1,duration_min)),0) FROM study_sessions WHERE DATE(started_at)=CURDATE() AND ended_at IS NOT NULL AND user_id=?");
            $stmt2->execute([$this->uid]);
            return (int)$stmt2->fetchColumn();
        }
    }

    public function calculateStreak(): int {
        $stmt = db()->prepare("
            SELECT DISTINCT DATE(started_at) AS d
            FROM study_sessions
            WHERE ended_at IS NOT NULL AND user_id=?
            ORDER BY d DESC
        ");
        $stmt->execute([$this->uid]);
        $dates = $stmt->fetchAll(PDO::FETCH_COLUMN);
        if (empty($dates)) return 0;
        $streak = 0; $today = new DateTime('today');
        foreach ($dates as $i => $dateStr) {
            $expected = (clone $today)->modify("-{$i} days");
            if ($dateStr !== $expected->format('Y-m-d')) break;
            $streak++;
        }
        return $streak;
    }

    public function getRecent(int $limit = 20): array {
        $stmt = db()->prepare("
            SELECT ss.*, s.name AS subject_name, s.color, t.name AS topic_name
            FROM study_sessions ss
            JOIN subjects s ON s.id=ss.subject_id
            LEFT JOIN topics t ON t.id=ss.topic_id
            WHERE ss.ended_at IS NOT NULL AND ss.user_id=?
            ORDER BY ss.started_at DESC LIMIT ?
        ");
        $stmt->execute([$this->uid, $limit]);
        return $stmt->fetchAll();
    }
}
