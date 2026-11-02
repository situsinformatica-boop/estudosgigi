<?php
class SubjectModel {
    private int $uid;
    public function __construct() { $this->uid = uid(); }

    public function getAll(): array {
        $stmt = db()->prepare("
            SELECT s.*,
                COUNT(DISTINCT t.id) AS topic_count,
                COUNT(DISTINCT CASE WHEN t.is_completed=1 THEN t.id END) AS topics_done,
                COALESCE(SUM(CASE WHEN ss.started_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                    THEN ss.duration_min END), 0) AS minutes_this_week
            FROM subjects s
            LEFT JOIN topics t ON t.subject_id = s.id
            LEFT JOIN study_sessions ss ON ss.subject_id = s.id AND ss.ended_at IS NOT NULL AND ss.user_id = ?
            WHERE s.is_active = 1 AND s.user_id = ?
            GROUP BY s.id ORDER BY s.priority DESC, s.name ASC
        ");
        $stmt->execute([$this->uid, $this->uid]);
        return $stmt->fetchAll();
    }

    public function getById(int $id): ?array {
        $stmt = db()->prepare("SELECT * FROM subjects WHERE id=? AND user_id=?");
        $stmt->execute([$id, $this->uid]);
        return $stmt->fetch() ?: null;
    }

    public function create(array $data): int {
        $stmt = db()->prepare("INSERT INTO subjects (user_id,name,description,color,icon,weekly_goal_minutes,priority)
            VALUES (?,?,?,?,?,?,?)");
        $stmt->execute([$this->uid, $data['name'], $data['description'] ?? '',
            $data['color'] ?? '#6366f1', $data['icon'] ?? 'book',
            (int)($data['weekly_goal_minutes'] ?? 300), (int)($data['priority'] ?? 2)]);
        return (int)db()->lastInsertId();
    }

    public function delete(int $id): bool {
        return db()->prepare("UPDATE subjects SET is_active=0 WHERE id=? AND user_id=?")
                   ->execute([$id, $this->uid]);
    }
}

class TopicModel {
    private int $uid;
    public function __construct() { $this->uid = uid(); }

    public function getBySubject(int $subjectId): array {
        $stmt = db()->prepare("
            SELECT t.*, sr.next_review, sr.repetitions
            FROM topics t
            LEFT JOIN spaced_reviews sr ON sr.topic_id = t.id AND sr.user_id = ?
            WHERE t.subject_id = ?
            ORDER BY t.order_index ASC, t.name ASC
        ");
        $stmt->execute([$this->uid, $subjectId]);
        return $stmt->fetchAll();
    }

    public function getById(int $id): ?array {
        $stmt = db()->prepare("SELECT * FROM topics WHERE id=?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function create(array $data): int {
        $stmt = db()->prepare("INSERT INTO topics (subject_id,name,description,difficulty,order_index)
            VALUES (?,?,?,?,?)");
        $stmt->execute([$data['subject_id'], $data['name'], $data['description'] ?? '',
            (int)($data['difficulty'] ?? 2), (int)($data['order_index'] ?? 0)]);
        return (int)db()->lastInsertId();
    }

    public function updateProgress(int $id, int $progress): bool {
        return db()->prepare("UPDATE topics SET progress=?, is_completed=? WHERE id=?")
                   ->execute([$progress, $progress >= 100 ? 1 : 0, $id]);
    }

    public function delete(int $id): bool {
        return db()->prepare("DELETE FROM topics WHERE id=?")->execute([$id]);
    }
}
