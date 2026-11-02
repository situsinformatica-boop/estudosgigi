<?php
class GamificationModel {
    private int $uid;
    public function __construct() { $this->uid = uid(); }

    public function getStats(): array {
        $stmt = db()->prepare("SELECT * FROM user_stats WHERE user_id=?");
        $stmt->execute([$this->uid]);
        $row = $stmt->fetch();
        if (!$row) {
            db()->prepare("INSERT INTO user_stats (user_id) VALUES (?)")->execute([$this->uid]);
            $stmt->execute([$this->uid]);
            $row = $stmt->fetch();
        }
        return $row;
    }

    public function addXP(int $amount, string $reason, ?int $refId = null): array {
        db()->prepare("INSERT INTO xp_log (user_id,amount,reason,reference_id) VALUES (?,?,?,?)")
            ->execute([$this->uid, $amount, $reason, $refId]);
        db()->prepare("UPDATE user_stats SET total_xp=total_xp+? WHERE user_id=?")
            ->execute([$amount, $this->uid]);
        $stats    = $this->getStats();
        $newLevel = $this->calcLevel($stats['total_xp']);
        $leveledUp = $newLevel > $stats['level'];
        if ($leveledUp) db()->prepare("UPDATE user_stats SET level=? WHERE user_id=?")->execute([$newLevel, $this->uid]);
        return ['xp_earned' => $amount, 'total_xp' => $stats['total_xp'], 'level' => $newLevel, 'leveled_up' => $leveledUp];
    }

    public function calcLevel(int $totalXP): int {
        $level = 1; $used = 0; $base = (int)getSetting('level_xp_base','500');
        while (true) { $n = (int)($base * $level * 1.2); if ($used + $n > $totalXP) break; $used += $n; $level++; }
        return $level;
    }

    public function xpToNextLevel(): array {
        $stats = $this->getStats(); $base = (int)getSetting('level_xp_base','500'); $used = 0;
        for ($l = 1; $l < $stats['level']; $l++) $used += (int)($base * $l * 1.2);
        $needed  = (int)($base * $stats['level'] * 1.2);
        $current = $stats['total_xp'] - $used;
        return ['current' => $current, 'needed' => $needed, 'percent' => min(100, $needed > 0 ? (int)(($current/$needed)*100) : 0)];
    }

    public function updateStreak(int $streak): void {
        db()->prepare("UPDATE user_stats SET current_streak=?,longest_streak=GREATEST(longest_streak,?),last_study_date=CURDATE() WHERE user_id=?")
            ->execute([$streak, $streak, $this->uid]);
    }

    public function registerSession(int $minutes): void {
        db()->prepare("UPDATE user_stats SET total_sessions=total_sessions+1,total_minutes=total_minutes+? WHERE user_id=?")
            ->execute([$minutes, $this->uid]);
    }

    public function checkAchievements(array $ctx = []): array {
        $all  = db()->query("SELECT * FROM achievements")->fetchAll();
        $stmt = db()->prepare("SELECT achievement_id FROM user_achievements WHERE user_id=?");
        $stmt->execute([$this->uid]);
        $done  = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $stats = $this->getStats();
        $new   = [];
        foreach ($all as $a) {
            if (in_array($a['id'], $done)) continue;
            $earned = match($a['condition_type']) {
                'total_sessions' => $stats['total_sessions'] >= $a['condition_value'],
                'streak'         => $stats['current_streak'] >= $a['condition_value'],
                'total_hours'    => ($stats['total_minutes']/60) >= $a['condition_value'],
                'pomodoros'      => ($ctx['total_pomodoros'] ?? 0) >= $a['condition_value'],
                'reviews_done'   => ($ctx['reviews_done'] ?? 0) >= $a['condition_value'],
                'night_session'  => ($ctx['is_night'] ?? false),
                'early_session'  => ($ctx['is_early'] ?? false),
                default          => false,
            };
            if ($earned) {
                db()->prepare("INSERT INTO user_achievements (user_id,achievement_id) VALUES (?,?)")->execute([$this->uid, $a['id']]);
                $this->addXP($a['xp_reward'], 'achievement_'.$a['slug'], $a['id']);
                $new[] = $a;
            }
        }
        return $new;
    }

    public function getAllWithStatus(): array {
        $stmt = db()->prepare("SELECT a.*, (ua.id IS NOT NULL) AS is_unlocked, ua.unlocked_at
            FROM achievements a LEFT JOIN user_achievements ua ON ua.achievement_id=a.id AND ua.user_id=?
            ORDER BY is_unlocked DESC, a.xp_reward DESC");
        $stmt->execute([$this->uid]);
        return $stmt->fetchAll();
    }

    public function getUnlocked(): array {
        $stmt = db()->prepare("SELECT a.*, ua.unlocked_at FROM user_achievements ua
            JOIN achievements a ON a.id=ua.achievement_id WHERE ua.user_id=? ORDER BY ua.unlocked_at DESC");
        $stmt->execute([$this->uid]);
        return $stmt->fetchAll();
    }
}
