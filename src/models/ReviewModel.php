<?php
class ReviewModel {
    private int $uid;
    public function __construct() { $this->uid = uid(); }

    public function schedule(int $topicId, int $subjectId): int {
        $stmt = db()->prepare("SELECT id FROM spaced_reviews WHERE topic_id=? AND user_id=?");
        $stmt->execute([$topicId, $this->uid]);
        if ($stmt->fetch()) return 0;
        db()->prepare("INSERT INTO spaced_reviews (user_id,topic_id,subject_id,next_review,interval_days) VALUES (?,?,?,CURDATE(),1)")
            ->execute([$this->uid, $topicId, $subjectId]);
        return (int)db()->lastInsertId();
    }

    public function getDueToday(): array {
        $stmt = db()->prepare("
            SELECT sr.*, t.name AS topic_name, t.difficulty, s.name AS subject_name, s.color
            FROM spaced_reviews sr
            JOIN topics t ON t.id=sr.topic_id
            JOIN subjects s ON s.id=sr.subject_id
            WHERE sr.next_review <= CURDATE() AND sr.user_id=?
            ORDER BY sr.next_review ASC
        ");
        $stmt->execute([$this->uid]);
        return $stmt->fetchAll();
    }

    public function countDue(): int {
        $stmt = db()->prepare("SELECT COUNT(*) FROM spaced_reviews WHERE next_review<=CURDATE() AND user_id=?");
        $stmt->execute([$this->uid]);
        return (int)$stmt->fetchColumn();
    }

    public function countDone(): int {
        $stmt = db()->prepare("SELECT COUNT(*) FROM spaced_reviews WHERE last_reviewed IS NOT NULL AND user_id=?");
        $stmt->execute([$this->uid]);
        return (int)$stmt->fetchColumn();
    }

    public function recordResult(int $reviewId, int $quality): array {
        $stmt = db()->prepare("SELECT * FROM spaced_reviews WHERE id=? AND user_id=?");
        $stmt->execute([$reviewId, $this->uid]);
        $r = $stmt->fetch();
        if (!$r) return [];
        $result = sm2((int)$r['repetitions'], (float)$r['ease_factor'], (int)$r['interval_days'], $quality);
        db()->prepare("UPDATE spaced_reviews SET interval_days=?,ease_factor=?,repetitions=?,last_quality=?,last_reviewed=CURDATE(),next_review=? WHERE id=? AND user_id=?")
            ->execute([$result['interval'],$result['ease_factor'],$result['repetitions'],$quality,$result['next_review'],$reviewId,$this->uid]);
        return $result;
    }

    public function getUpcoming(int $days = 7): array {
        $stmt = db()->prepare("SELECT next_review, COUNT(*) AS count FROM spaced_reviews
            WHERE next_review > CURDATE() AND next_review <= DATE_ADD(CURDATE(), INTERVAL ? DAY) AND user_id=?
            GROUP BY next_review ORDER BY next_review ASC");
        $stmt->execute([$days, $this->uid]);
        return $stmt->fetchAll();
    }
}
