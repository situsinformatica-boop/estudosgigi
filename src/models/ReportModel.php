<?php
class ReportModel {
    private int $uid;
    public function __construct() { $this->uid = uid(); }

    /**
     * Expressão SQL que calcula minutos de forma precisa:
     * usa duration_sec se disponível, senão duration_min,
     * com mínimo de 1 minuto por sessão.
     */
    private static function minExpr(): string {
        return "GREATEST(1, CASE
            WHEN duration_sec IS NOT NULL AND duration_sec > 0
                THEN CEIL(duration_sec / 60.0)
            WHEN duration_min IS NOT NULL AND duration_min > 0
                THEN duration_min
            ELSE 1
        END)";
    }

    public function getSummary(int $days = 30): array {
        $min = self::minExpr();
        $stmt = db()->prepare("
            SELECT
                COUNT(*)                     AS total_sessions,
                COALESCE(SUM($min),0)        AS total_minutes,
                COALESCE(AVG($min),0)        AS avg_duration,
                COALESCE(AVG(quality),0)     AS avg_quality,
                COALESCE(SUM(pomodoros_done),0) AS total_pomodoros,
                COUNT(DISTINCT DATE(started_at)) AS days_studied
            FROM study_sessions
            WHERE started_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
              AND ended_at IS NOT NULL
              AND user_id = ?
        ");
        try {
            $stmt->execute([$days, $this->uid]);
            return $stmt->fetch();
        } catch (\PDOException $e) {
            // Fallback sem duration_sec
            return $this->_getSummaryFallback($days);
        }
    }

    private function _getSummaryFallback(int $days): array {
        $stmt = db()->prepare("SELECT COUNT(*) AS total_sessions,
            COALESCE(SUM(GREATEST(1,duration_min)),0) AS total_minutes,
            COALESCE(AVG(GREATEST(1,duration_min)),0) AS avg_duration,
            COALESCE(AVG(quality),0) AS avg_quality,
            COALESCE(SUM(pomodoros_done),0) AS total_pomodoros,
            COUNT(DISTINCT DATE(started_at)) AS days_studied
            FROM study_sessions WHERE started_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
            AND ended_at IS NOT NULL AND user_id=?");
        $stmt->execute([$days, $this->uid]);
        return $stmt->fetch();
    }

    public function hoursPerDay(int $days = 30): array {
        $min = self::minExpr();
        try {
            $stmt = db()->prepare("
                SELECT DATE(started_at) AS date,
                       ROUND(SUM($min)/60.0, 2) AS hours
                FROM study_sessions
                WHERE started_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                  AND ended_at IS NOT NULL
                  AND user_id = ?
                GROUP BY DATE(started_at)
                ORDER BY date ASC
            ");
            $stmt->execute([$days, $this->uid]);
        } catch (\PDOException $e) {
            $stmt = db()->prepare("SELECT DATE(started_at) AS date, ROUND(SUM(GREATEST(1,duration_min))/60,2) AS hours
                FROM study_sessions WHERE started_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                AND ended_at IS NOT NULL AND user_id=? GROUP BY DATE(started_at) ORDER BY date ASC");
            $stmt->execute([$days, $this->uid]);
        }
        $map = array_column($stmt->fetchAll(), 'hours', 'date');
        $result = [];
        for ($i = $days-1; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-{$i} days"));
            $result[] = ['date' => $d, 'hours' => (float)($map[$d] ?? 0)];
        }
        return $result;
    }

    public function distributionBySubject(int $days = 30): array {
        $min = self::minExpr();
        try {
            $stmt = db()->prepare("
                SELECT s.name, s.color, SUM($min) AS minutes
                FROM study_sessions ss
                JOIN subjects s ON s.id = ss.subject_id
                WHERE ss.started_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                  AND ss.ended_at IS NOT NULL AND ss.user_id = ?
                GROUP BY s.id, s.name, s.color
                ORDER BY minutes DESC
            ");
            $stmt->execute([$days, $this->uid]);
        } catch (\PDOException $e) {
            $stmt = db()->prepare("SELECT s.name, s.color, SUM(GREATEST(1,duration_min)) AS minutes FROM study_sessions ss JOIN subjects s ON s.id=ss.subject_id WHERE ss.started_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY) AND ss.ended_at IS NOT NULL AND ss.user_id=? GROUP BY s.id, s.name, s.color ORDER BY minutes DESC");
            $stmt->execute([$days, $this->uid]);
        }
        return $stmt->fetchAll();
    }

    public function weeklyGoalsProgress(): array {
        $min = self::minExpr();
        try {
            $stmt = db()->prepare("
                SELECT s.id, s.name, s.color, s.weekly_goal_minutes,
                       COALESCE(SUM($min), 0) AS achieved_minutes
                FROM subjects s
                LEFT JOIN study_sessions ss
                    ON ss.subject_id = s.id
                    AND ss.started_at >= DATE(DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY))
                    AND ss.ended_at IS NOT NULL
                    AND ss.user_id = ?
                WHERE s.is_active = 1 AND s.user_id = ?
                GROUP BY s.id, s.name, s.color, s.weekly_goal_minutes
            ");
            $stmt->execute([$this->uid, $this->uid]);
        } catch (\PDOException $e) {
            $stmt = db()->prepare("SELECT s.id, s.name, s.color, s.weekly_goal_minutes, COALESCE(SUM(GREATEST(1,duration_min)),0) AS achieved_minutes FROM subjects s LEFT JOIN study_sessions ss ON ss.subject_id=s.id AND ss.started_at >= DATE(DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)) AND ss.ended_at IS NOT NULL AND ss.user_id=? WHERE s.is_active=1 AND s.user_id=? GROUP BY s.id, s.name, s.color, s.weekly_goal_minutes");
            $stmt->execute([$this->uid, $this->uid]);
        }
        $rows = $stmt->fetchAll();
        foreach ($rows as &$r) {
            $r['percent'] = $r['weekly_goal_minutes'] > 0
                ? min(100, (int)(($r['achieved_minutes'] / $r['weekly_goal_minutes']) * 100))
                : 0;
        }
        return $rows;
    }

    public function qualityTrend(int $days = 30): array {
        $stmt = db()->prepare("
            SELECT DATE(started_at) AS date, ROUND(AVG(quality),2) AS avg_quality
            FROM study_sessions
            WHERE started_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
              AND quality IS NOT NULL AND ended_at IS NOT NULL AND user_id=?
            GROUP BY DATE(started_at) ORDER BY date ASC
        ");
        $stmt->execute([$days, $this->uid]);
        return $stmt->fetchAll();
    }

    public function heatmap(int $days = 90): array {
        $stmt = db()->prepare("
            SELECT DAYOFWEEK(started_at)-1 AS day_of_week,
                   HOUR(started_at) AS hour_of_day,
                   COUNT(*) AS session_count,
                   SUM(GREATEST(1,COALESCE(duration_min,1))) AS total_minutes
            FROM study_sessions
            WHERE started_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
              AND ended_at IS NOT NULL AND user_id=?
            GROUP BY day_of_week, hour_of_day
        ");
        $stmt->execute([$days, $this->uid]);
        return $stmt->fetchAll();
    }

    public function topSubjectsByHour(): array {
        $min = self::minExpr();
        try {
            $stmt = db()->prepare("
                SELECT s.name, s.color,
                       ROUND(SUM($min)/60.0,1) AS hours,
                       ROUND(AVG(ss.quality),1) AS avg_quality
                FROM study_sessions ss
                JOIN subjects s ON s.id = ss.subject_id
                WHERE ss.ended_at IS NOT NULL AND ss.user_id = ?
                GROUP BY s.id, s.name, s.color
                ORDER BY hours DESC LIMIT 5
            ");
            $stmt->execute([$this->uid]);
        } catch (\PDOException $e) {
            $stmt = db()->prepare("SELECT s.name, s.color, ROUND(SUM(GREATEST(1,duration_min))/60,1) AS hours, ROUND(AVG(ss.quality),1) AS avg_quality FROM study_sessions ss JOIN subjects s ON s.id=ss.subject_id WHERE ss.ended_at IS NOT NULL AND ss.user_id=? GROUP BY s.id, s.name, s.color ORDER BY hours DESC LIMIT 5");
            $stmt->execute([$this->uid]);
        }
        return $stmt->fetchAll();
    }
}
