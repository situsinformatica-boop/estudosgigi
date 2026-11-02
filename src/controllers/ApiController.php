<?php
require_once dirname(__DIR__) . '/../config/app.php';
requireAuth();

$action = $_GET['action'] ?? '';
match($action) {
    'session_start'     => apiSessionStart(),
    'session_finish'    => apiSessionFinish(),
    'session_active'    => jsonResponse(['session' => (new SessionModel())->getActive()]),
    'session_heartbeat' => apiSessionHeartbeat(),
    'session_abandon'   => apiSessionAbandon(),
    'pomodoro_config'   => apiPomodoroConfig(),
    'review_result'     => apiReviewResult(),
    'report_data'       => apiReportData(),
    'topic_progress'    => apiTopicProgress(),
    'get_topics'        => apiGetTopics(),
    default             => jsonResponse(['error' => 'not found'], 404),
};

// ── Inicia sessão ─────────────────────────────────────────────
function apiSessionStart(): never {
    $subjectId = (int)($_POST['subject_id'] ?? 0);
    $topicId   = ($_POST['topic_id'] ?? '') ? (int)$_POST['topic_id'] : null;
    if (!$subjectId) jsonResponse(['error' => 'Matéria obrigatória'], 422);
    $id = (new SessionModel())->start($subjectId, $topicId, $_POST['session_type'] ?? 'study');
    jsonResponse(['success' => true, 'session_id' => $id]);
}

// ── Finaliza sessão ───────────────────────────────────────────
function apiSessionFinish(): never {
    $id        = (int)($_POST['session_id']      ?? 0);
    $quality   = (int)($_POST['quality']         ?? 3);
    $pomodoros = (int)($_POST['pomodoros']       ?? 0);
    $elapsedS  = (int)($_POST['elapsed_seconds'] ?? 0);
    if (!$id) jsonResponse(['error' => 'ID obrigatório'], 422);

    $sess  = new SessionModel();
    $gamif = new GamificationModel();
    $rev   = new ReviewModel();

    // Usa elapsed_seconds do JS para máxima precisão antes de fechar
    if ($elapsedS > 0) {
        $sess->forceEndAt($id, $elapsedS);
    }

    $sess->finish($id, $quality, $_POST['notes'] ?? '', $pomodoros);

    $stmt = db()->prepare("SELECT * FROM study_sessions WHERE id=? AND user_id=?");
    $stmt->execute([$id, uid()]);
    $session = $stmt->fetch();

    // Calcula minutos com a maior precisão disponível
    $durSec = isset($session['duration_sec']) ? (int)$session['duration_sec'] : 0;
    $durMin = (int)($session['duration_min'] ?? 0);

    if ($durSec > 0) {
        $minutes = max(1, (int)ceil($durSec / 60));
    } elseif ($durMin > 0) {
        $minutes = $durMin;
    } elseif ($elapsedS > 0) {
        $minutes = max(1, (int)ceil($elapsedS / 60));
    } else {
        $minutes = 1;
    }

    $xp = ($minutes * (int)getSetting('xp_per_minute', '2'))
        + (int)getSetting('xp_per_session', '30')
        + ($pomodoros * (int)getSetting('xp_per_pomodoro', '15'));

    $xpResult = $gamif->addXP($xp, 'session_completed', $id);
    $gamif->registerSession($minutes);
    $gamif->updateStreak($sess->calculateStreak());

    $hour = (int)date('G', strtotime($session['started_at'] ?? 'now'));
    $achievements = $gamif->checkAchievements([
        'total_pomodoros' => $pomodoros,
        'reviews_done'    => $rev->countDone(),
        'is_night'        => $hour >= 0 && $hour < 4,
        'is_early'        => $hour >= 4 && $hour < 6,
    ]);

    jsonResponse([
        'success'          => true,
        'xp_earned'        => $xp,
        'xp_result'        => $xpResult,
        'new_achievements' => $achievements,
        'minutes'          => $minutes,
    ]);
}

// ── Heartbeat — atualiza ended_at a cada 30s ─────────────────
function apiSessionHeartbeat(): never {
    $id       = (int)($_POST['session_id']      ?? 0);
    $elapsedS = (int)($_POST['elapsed_seconds'] ?? 0);
    if (!$id || $elapsedS <= 0) jsonResponse(['ok' => false], 422);

    // Atualiza ended_at mantendo a sessão "em aberto" (quality=NULL)
    db()->prepare("
        UPDATE study_sessions
        SET ended_at = DATE_ADD(started_at, INTERVAL ? SECOND)
        WHERE id = ? AND user_id = ?
    ")->execute([$elapsedS, $id, uid()]);

    jsonResponse(['ok' => true, 'elapsed' => $elapsedS]);
}

// ── Abandono — finaliza quando usuário sai sem clicar Finalizar
function apiSessionAbandon(): never {
    $id       = (int)($_POST['session_id']      ?? 0);
    $elapsedS = (int)($_POST['elapsed_seconds'] ?? 0);
    if (!$id) jsonResponse(['ok' => false], 422);

    $duracao = $elapsedS > 0 ? $elapsedS : 60;

    db()->prepare("
        UPDATE study_sessions
        SET ended_at = DATE_ADD(started_at, INTERVAL ? SECOND),
            quality  = COALESCE(quality, 3)
        WHERE id = ? AND user_id = ? AND ended_at IS NULL
    ")->execute([$duracao, $id, uid()]);

    $stmt = db()->prepare("SELECT duration_sec, duration_min FROM study_sessions WHERE id=? AND user_id=?");
    $stmt->execute([$id, uid()]);
    $row = $stmt->fetch();

    $durSec = isset($row['duration_sec']) ? (int)$row['duration_sec'] : 0;
    $durMin = (int)($row['duration_min'] ?? 0);
    $minutes = $durSec > 0 ? max(1,(int)ceil($durSec/60))
             : ($durMin > 0 ? $durMin
             : max(1,(int)ceil($duracao/60)));

    $gamif = new GamificationModel();
    $sess  = new SessionModel();
    $xp    = ($minutes * (int)getSetting('xp_per_minute','2')) + (int)getSetting('xp_per_session','30');
    $gamif->addXP($xp, 'session_abandoned', $id);
    $gamif->registerSession($minutes);
    $gamif->updateStreak($sess->calculateStreak());

    jsonResponse(['ok' => true, 'minutes' => $minutes, 'xp' => $xp]);
}

// ── Config do Pomodoro ────────────────────────────────────────
function apiPomodoroConfig(): never {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $uid = uid();
        $stmt = db()->prepare("INSERT INTO pomodoro_config
            (user_id,work_minutes,short_break_min,long_break_min,cycles_before_long,auto_start_break,auto_start_work,sound_enabled)
            VALUES (?,?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE
            work_minutes=VALUES(work_minutes),short_break_min=VALUES(short_break_min),
            long_break_min=VALUES(long_break_min),cycles_before_long=VALUES(cycles_before_long),
            auto_start_break=VALUES(auto_start_break),auto_start_work=VALUES(auto_start_work),
            sound_enabled=VALUES(sound_enabled)");
        $stmt->execute([
            $uid,
            (int)$_POST['work_minutes'],
            (int)$_POST['short_break_min'],
            (int)$_POST['long_break_min'],
            (int)$_POST['cycles_before_long'],
            (int)($_POST['auto_start_break'] ?? 0),
            (int)($_POST['auto_start_work']  ?? 0),
            (int)($_POST['sound_enabled']    ?? 1),
        ]);
        jsonResponse(['success' => true]);
    }
    $stmt = db()->prepare("SELECT * FROM pomodoro_config WHERE user_id=?");
    $stmt->execute([uid()]);
    $cfg = $stmt->fetch();
    if (!$cfg) {
        db()->prepare("INSERT IGNORE INTO pomodoro_config (user_id) VALUES (?)")->execute([uid()]);
        $stmt->execute([uid()]); $cfg = $stmt->fetch();
    }
    jsonResponse($cfg);
}

// ── Revisão ───────────────────────────────────────────────────
function apiReviewResult(): never {
    $reviewId = (int)($_POST['review_id'] ?? 0);
    $quality  = (int)($_POST['quality']   ?? 3);
    $result   = (new ReviewModel())->recordResult($reviewId, $quality);
    $xpResult = (new GamificationModel())->addXP((int)getSetting('xp_per_review','20'), 'review_done', $reviewId);
    jsonResponse(['success' => true, 'next_review' => $result['next_review'] ?? null, 'xp' => $xpResult]);
}

// ── Dados de relatório ────────────────────────────────────────
function apiReportData(): never {
    $r    = new ReportModel();
    $days = (int)($_GET['days'] ?? 30);
    $data = match($_GET['type'] ?? '') {
        'summary'      => $r->getSummary($days),
        'hours_per_day'=> $r->hoursPerDay($days),
        'distribution' => $r->distributionBySubject($days),
        'quality'      => $r->qualityTrend($days),
        'heatmap'      => $r->heatmap($days),
        'weekly_goals' => $r->weeklyGoalsProgress(),
        default        => [],
    };
    jsonResponse($data);
}

// ── Progresso de tópico ───────────────────────────────────────
function apiTopicProgress(): never {
    $topicId  = (int)($_POST['topic_id'] ?? 0);
    $progress = (int)($_POST['progress'] ?? 0);
    (new TopicModel())->updateProgress($topicId, $progress);
    if ($progress >= 20) {
        $t = (new TopicModel())->getById($topicId);
        if ($t) (new ReviewModel())->schedule($topicId, $t['subject_id']);
    }
    jsonResponse(['success' => true, 'progress' => $progress]);
}

// ── Tópicos por matéria ───────────────────────────────────────
function apiGetTopics(): never {
    $subjectId = (int)($_GET['subject_id'] ?? 0);
    if (!$subjectId) jsonResponse([]);
    $stmt = db()->prepare("
        SELECT t.id, t.name, t.difficulty, t.progress, t.is_completed
        FROM topics t
        JOIN subjects s ON s.id = t.subject_id
        WHERE t.subject_id = ? AND s.user_id = ?
        ORDER BY t.order_index ASC, t.name ASC
    ");
    $stmt->execute([$subjectId, uid()]);
    jsonResponse($stmt->fetchAll());
}
