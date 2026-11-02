<?php
$sess   = new SessionModel();
$report = new ReportModel();
$gamif  = new GamificationModel();
$uStats = $gamif->getStats();

$summary7  = $report->getSummary(7);
$summary30 = $report->getSummary(30);
$summaryAll = $report->getSummary(3650);
$bySubject = $report->topSubjectsByHour();
$weekGoals = $report->weeklyGoalsProgress();

// Horas por dia últimos 30 dias
$hoursPerDay = $report->hoursPerDay(30);

// Melhor dia da semana
$stmt = db()->prepare("
    SELECT DAYOFWEEK(started_at)-1 AS dow, SUM(duration_min) AS total
    FROM study_sessions WHERE user_id=? AND ended_at IS NOT NULL
    GROUP BY dow ORDER BY total DESC LIMIT 1
");
$stmt->execute([uid()]);
$bestDow = $stmt->fetch();
$dowNames = ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'];

// Melhor hora do dia
$stmtH = db()->prepare("
    SELECT HOUR(started_at) AS h, SUM(duration_min) AS total
    FROM study_sessions WHERE user_id=? AND ended_at IS NOT NULL
    GROUP BY h ORDER BY total DESC LIMIT 1
");
$stmtH->execute([uid()]); $bestHour = $stmtH->fetch();

// Sessões recentes
$recent = $sess->getRecent(10);

// Streaks
$streak = $uStats['current_streak'];
$longest = $uStats['longest_streak'];
?>

<div class="page-header">
    <div><div class="page-title">⏱ Horas de Estudo</div>
    <div class="page-subtitle">Acompanhe sua evolução e consistência</div></div>
</div>

<!-- Cards de resumo -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-label">Hoje</div>
        <div class="stat-value stat-blue"><?= minutesToReadable((new SessionModel())->minutesToday()) ?></div>
        <div class="stat-sub">Meta: <?= minutesToReadable((int)getSetting('daily_goal_minutes','120')) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Esta semana</div>
        <div class="stat-value stat-green"><?= round($summary7['total_minutes']/60,1) ?>h</div>
        <div class="stat-sub"><?= $summary7['total_sessions'] ?> sessões</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Este mês</div>
        <div class="stat-value stat-amber"><?= round($summary30['total_minutes']/60,1) ?>h</div>
        <div class="stat-sub"><?= $summary30['days_studied'] ?> dias estudados</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Total histórico</div>
        <div class="stat-value"><?= round($summaryAll['total_minutes']/60,1) ?>h</div>
        <div class="stat-sub"><?= $summaryAll['total_sessions'] ?> sessões totais</div>
    </div>
</div>

<!-- Streak + Insights -->
<div class="grid-2" style="margin-bottom:20px">
    <div class="card">
        <div class="card-title">🔥 Sequência de estudos</div>
        <div style="display:flex;gap:24px;align-items:center">
            <div style="text-align:center">
                <div style="font-size:52px;font-weight:700;color:var(--accent);font-family:var(--mono)"><?= $streak ?></div>
                <div style="font-size:13px;color:var(--muted)">dias seguidos</div>
            </div>
            <div style="flex:1">
                <div style="font-size:14px;margin-bottom:10px">Recorde pessoal: <strong style="color:var(--primary-lt)"><?= $longest ?> dias</strong></div>
                <?php if($streak > 0): ?>
                <div style="font-size:13px;color:var(--green)">✓ Continue estudando para não quebrar a sequência!</div>
                <?php else: ?>
                <div style="font-size:13px;color:var(--muted)">Comece a estudar hoje para iniciar uma sequência!</div>
                <?php endif; ?>
                <div style="margin-top:12px">
                    <?php for($i=6;$i>=0;$i--):
                        $d = date('Y-m-d',strtotime("-{$i} days"));
                        $stmtD = db()->prepare("SELECT COUNT(*) FROM study_sessions WHERE user_id=? AND DATE(started_at)=? AND ended_at IS NOT NULL");
                        $stmtD->execute([uid(),$d]); $had = (int)$stmtD->fetchColumn();
                    ?>
                    <div style="display:inline-flex;flex-direction:column;align-items:center;margin-right:6px">
                        <div style="width:28px;height:28px;border-radius:6px;background:<?= $had?'var(--green)':'var(--bg3)' ?>;display:flex;align-items:center;justify-content:center;font-size:12px"><?= $had?'✓':'·' ?></div>
                        <div style="font-size:10px;color:var(--muted);margin-top:3px"><?= $dowNames[date('w',strtotime($d))] ?></div>
                    </div>
                    <?php endfor; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-title">💡 Seus hábitos</div>
        <div style="display:flex;flex-direction:column;gap:10px">
            <?php if($bestDow): ?>
            <div style="display:flex;align-items:center;gap:10px;padding:10px;background:var(--bg3);border-radius:var(--radius)">
                <span style="font-size:24px">📆</span>
                <div><div style="font-size:13px;font-weight:600">Melhor dia da semana</div>
                <div style="font-size:14px;color:var(--primary-lt)"><?= $dowNames[$bestDow['dow']] ?> — <?= minutesToReadable((int)$bestDow['total']) ?> acumulados</div></div>
            </div>
            <?php endif; ?>
            <?php if($bestHour): ?>
            <div style="display:flex;align-items:center;gap:10px;padding:10px;background:var(--bg3);border-radius:var(--radius)">
                <span style="font-size:24px">🕐</span>
                <div><div style="font-size:13px;font-weight:600">Horário mais produtivo</div>
                <div style="font-size:14px;color:var(--accent)"><?= $bestHour['h'] ?>h – <?= $bestHour['h']+1 ?>h</div></div>
            </div>
            <?php endif; ?>
            <div style="display:flex;align-items:center;gap:10px;padding:10px;background:var(--bg3);border-radius:var(--radius)">
                <span style="font-size:24px">⭐</span>
                <div><div style="font-size:13px;font-weight:600">Qualidade média das sessões</div>
                <div style="font-size:14px;color:var(--green)"><?= number_format($summary30['avg_quality'],1) ?> / 5.0</div></div>
            </div>
        </div>
    </div>
</div>

<!-- Gráfico de barras + pizza -->
<div class="grid-2" style="margin-bottom:20px">
    <div class="card">
        <div class="card-title">Horas por dia — últimos 30 dias</div>
        <canvas id="chart-hours" height="180"></canvas>
    </div>
    <div class="card">
        <div class="card-title">Progresso das metas semanais</div>
        <?php foreach($weekGoals as $g): ?>
        <div style="margin-bottom:12px">
            <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:4px">
                <span style="font-weight:600"><span class="subject-dot" style="background:<?= e($g['color']) ?>"></span><?= e($g['name']) ?></span>
                <span style="color:var(--muted);font-family:var(--mono)"><?= minutesToReadable((int)$g['achieved_minutes']) ?>/<?= minutesToReadable((int)$g['weekly_goal_minutes']) ?></span>
            </div>
            <div class="progress-wrap"><div class="progress-bar" style="width:<?= $g['percent'] ?>%;background:<?= e($g['color']) ?>"></div></div>
        </div>
        <?php endforeach; ?>
        <?php if(empty($weekGoals)): ?><p style="color:var(--muted);font-size:14px">Cadastre matérias com meta semanal.</p><?php endif; ?>
    </div>
</div>

<!-- Top matérias + Sessões recentes -->
<div class="grid-2">
    <div class="card">
        <div class="card-title">Top matérias por horas</div>
        <?php if(empty($bySubject)): ?><p style="color:var(--muted);font-size:14px">Nenhuma sessão registrada.</p><?php endif; ?>
        <?php foreach($bySubject as $i => $t): ?>
        <div style="display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid var(--border)">
            <div style="width:24px;height:24px;border-radius:50%;background:<?= e($t['color']) ?>;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;color:#fff;flex-shrink:0"><?= $i+1 ?></div>
            <div style="flex:1;min-width:0">
                <div style="font-weight:600;font-size:14px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= e($t['name']) ?></div>
                <div class="progress-wrap" style="height:4px;margin-top:4px"><div class="progress-bar" style="width:<?= min(100,($t['hours']/max(1,$bySubject[0]['hours']))*100) ?>%;background:<?= e($t['color']) ?>"></div></div>
            </div>
            <div style="text-align:right;flex-shrink:0">
                <div style="font-family:var(--mono);font-size:14px;color:var(--primary-lt)"><?= $t['hours'] ?>h</div>
                <div style="font-size:11px;color:var(--accent)"><?= $t['avg_quality'] ?> ★</div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="card">
        <div class="card-title">Sessões recentes</div>
        <?php if(empty($recent)): ?><p style="color:var(--muted);font-size:14px">Nenhuma sessão ainda.</p><?php endif; ?>
        <?php foreach($recent as $r): ?>
        <div style="display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid var(--border)">
            <div style="width:8px;height:8px;border-radius:50%;background:<?= e($r['color']) ?>;flex-shrink:0"></div>
            <div style="flex:1;min-width:0">
                <div style="font-size:13px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= e($r['subject_name']) ?></div>
                <div style="font-size:11px;color:var(--muted)"><?= formatDate($r['started_at'],'d/m H:i') ?></div>
            </div>
            <div style="text-align:right;flex-shrink:0">
                <div style="font-family:var(--mono);font-size:13px;font-weight:600"><?= minutesToReadable((int)$r['duration_min']) ?></div>
                <?php if($r['quality']): ?><div style="font-size:11px;color:var(--accent)"><?= str_repeat('★',$r['quality']) ?></div><?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded',()=>{
    fetch('index.php?page=api&action=report_data&type=hours_per_day&days=30').then(r=>r.json()).then(data=>{
        makeBarChart('chart-hours',
            data.map(d=>{const[,m,dia]=d.date.split('-');return`${dia}/${m}`;}),
            data.map(d=>d.hours),'Horas');
    });
});
</script>
