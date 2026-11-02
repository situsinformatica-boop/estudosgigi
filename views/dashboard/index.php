<?php
$sess    = new SessionModel();
$gamif   = new GamificationModel();
$report  = new ReportModel();
$review  = new ReviewModel();
$subject = new SubjectModel();

$minutesToday = $sess->minutesToday();
$dailyGoal    = (int)getSetting('daily_goal_minutes','120');
$todayPct     = $dailyGoal > 0 ? min(100,(int)(($minutesToday/$dailyGoal)*100)) : 0;
$uStats       = $gamif->getStats();
$summary      = $report->getSummary(7);
$weekGoals    = $report->weeklyGoalsProgress();
$subjects     = $subject->getAll();
$reviewsDue   = $review->countDue();

// Agenda de hoje
$today = date('Y-m-d');
$stmtA = db()->prepare("SELECT * FROM agenda_items WHERE user_id=? AND study_date=? ORDER BY priority DESC, start_time ASC");
$stmtA->execute([uid(), $today]); $agendaToday = $stmtA->fetchAll();
$pendingAgenda = array_filter($agendaToday, fn($i) => !$i['is_done']);
?>

<div class="page-header">
    <div><div class="page-title">Dashboard</div>
    <div class="page-subtitle"><?= date('l, d \d\e F \d\e Y') ?></div></div>
    <a href="<?= BASE_URL ?>/index.php?page=timer" class="btn btn-primary">▶ Iniciar Sessão</a>
</div>

<!-- Aviso da agenda do dia -->
<?php if(count($pendingAgenda) > 0): ?>
<div class="card" style="border-color:var(--primary);background:rgba(99,102,241,.07);margin-bottom:20px">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px">
        <strong style="color:var(--primary-lt)">📅 Agenda de hoje — <?= count($pendingAgenda) ?> item(ns) pendente(s)</strong>
        <a href="<?= BASE_URL ?>/index.php?page=agenda" style="font-size:13px;color:var(--primary-lt)">Ver agenda →</a>
    </div>
    <?php foreach(array_slice($pendingAgenda,0,3) as $ag): ?>
    <div style="display:flex;align-items:center;gap:8px;padding:5px 0;border-bottom:1px solid var(--border)">
        <span style="width:8px;height:8px;border-radius:50%;background:<?= e($ag['color']) ?>;flex-shrink:0"></span>
        <span style="font-size:14px;font-weight:500"><?= e($ag['title']) ?></span>
        <?php if($ag['start_time']): ?><span style="font-size:12px;color:var(--muted);margin-left:auto"><?= substr($ag['start_time'],0,5) ?></span><?php endif; ?>
    </div>
    <?php endforeach; ?>
    <?php if(count($pendingAgenda)>3): ?><div style="font-size:12px;color:var(--muted);margin-top:6px">e mais <?= count($pendingAgenda)-3 ?> itens...</div><?php endif; ?>
</div>
<?php endif; ?>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-label">Estudado hoje</div>
        <div class="stat-value stat-blue"><?= minutesToReadable($minutesToday) ?></div>
        <div class="stat-sub">Meta: <?= minutesToReadable($dailyGoal) ?> (<?= $todayPct ?>%)</div>
        <div class="progress-wrap" style="margin-top:8px"><div class="progress-bar" style="width:<?= $todayPct ?>%;background:<?= progressColor($todayPct) ?>"></div></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Sequência atual</div>
        <div class="stat-value stat-amber">🔥 <?= $uStats['current_streak'] ?></div>
        <div class="stat-sub">Recorde: <?= $uStats['longest_streak'] ?> dias</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Revisões pendentes</div>
        <div class="stat-value <?= $reviewsDue>0?'stat-red':'stat-green' ?>"><?= $reviewsDue ?></div>
        <div class="stat-sub"><a href="<?= BASE_URL ?>/index.php?page=review" style="color:var(--primary-lt)">Ver revisões →</a></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Sessões (7 dias)</div>
        <div class="stat-value stat-green"><?= $summary['total_sessions'] ?></div>
        <div class="stat-sub"><?= minutesToReadable((int)$summary['total_minutes']) ?> de estudo</div>
    </div>
</div>

<!-- Acesso rápido aos novos módulos -->
<div class="grid-3" style="margin-bottom:20px">
    <a href="<?= BASE_URL ?>/index.php?page=agenda" style="text-decoration:none">
        <div class="card" style="text-align:center;padding:20px;cursor:pointer;transition:border-color .15s;border-color:var(--border)" onmouseover="this.style.borderColor='#f472b6'" onmouseout="this.style.borderColor='var(--border)'">
            <div style="font-size:32px;margin-bottom:8px">📅</div>
            <div style="font-weight:700;font-size:15px">Agenda</div>
            <div style="font-size:12px;color:var(--muted);margin-top:4px"><?= count($pendingAgenda) ?> item(s) hoje</div>
        </div>
    </a>
    <a href="<?= BASE_URL ?>/index.php?page=flashcards" style="text-decoration:none">
        <div class="card" style="text-align:center;padding:20px;cursor:pointer;transition:border-color .15s" onmouseover="this.style.borderColor='var(--accent)'" onmouseout="this.style.borderColor='var(--border)'">
            <div style="font-size:32px;margin-bottom:8px">🃏</div>
            <div style="font-weight:700;font-size:15px">Flashcards</div>
            <div style="font-size:12px;color:var(--muted);margin-top:4px">Estude com fichas</div>
        </div>
    </a>
    <a href="<?= BASE_URL ?>/index.php?page=notebook" style="text-decoration:none">
        <div class="card" style="text-align:center;padding:20px;cursor:pointer;transition:border-color .15s" onmouseover="this.style.borderColor='var(--green)'" onmouseout="this.style.borderColor='var(--border)'">
            <div style="font-size:32px;margin-bottom:8px">📓</div>
            <div style="font-weight:700;font-size:15px">Caderno Digital</div>
            <div style="font-size:12px;color:var(--muted);margin-top:4px">Anote as videoaulas</div>
        </div>
    </a>
</div>

<div class="grid-2" style="margin-bottom:20px">
    <div class="card">
        <div class="card-title">Metas semanais</div>
        <?php if(empty($weekGoals)): ?><p style="color:var(--muted);font-size:14px">Nenhuma matéria com meta. <a href="<?= BASE_URL ?>/index.php?page=subjects" style="color:var(--primary-lt)">Adicionar →</a></p><?php endif; ?>
        <?php foreach($weekGoals as $g): ?>
        <div style="margin-bottom:12px">
            <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:4px">
                <span style="font-weight:600"><span class="subject-dot" style="background:<?= e($g['color']) ?>"></span><?= e($g['name']) ?></span>
                <span style="color:var(--muted);font-family:var(--mono)"><?= minutesToReadable((int)$g['achieved_minutes']) ?>/<?= minutesToReadable((int)$g['weekly_goal_minutes']) ?></span>
            </div>
            <div class="progress-wrap"><div class="progress-bar" style="width:<?= $g['percent'] ?>%;background:<?= progressColor($g['percent']) ?>"></div></div>
        </div>
        <?php endforeach; ?>
    </div>
    <div class="card">
        <div class="card-title">Horas — últimos 7 dias</div>
        <canvas id="chart-week" height="160"></canvas>
    </div>
</div>

<div class="card-title" style="margin-bottom:12px">Suas matérias</div>
<div class="grid-3">
    <?php foreach($subjects as $s): ?>
    <a href="<?= BASE_URL ?>/index.php?page=subjects&id=<?= $s['id'] ?>" class="card" style="text-decoration:none;color:inherit;cursor:pointer;transition:border-color .15s" onmouseover="this.style.borderColor='<?= e($s['color']) ?>'" onmouseout="this.style.borderColor='var(--border)'">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px">
            <span style="width:10px;height:10px;border-radius:50%;background:<?= e($s['color']) ?>;flex-shrink:0"></span>
            <span style="font-weight:700;font-size:15px"><?= e($s['name']) ?></span>
        </div>
        <div style="font-size:12px;color:var(--muted);margin-bottom:8px"><?= $s['topics_done'] ?>/<?= $s['topic_count'] ?> tópicos · <?= minutesToReadable((int)$s['minutes_this_week']) ?>/sem</div>
        <div class="progress-wrap"><div class="progress-bar" style="width:<?= $s['topic_count']>0?round(($s['topics_done']/$s['topic_count'])*100):0 ?>%;background:<?= e($s['color']) ?>"></div></div>
    </a>
    <?php endforeach; ?>
    <a href="<?= BASE_URL ?>/index.php?page=subjects" class="card" style="text-decoration:none;color:var(--muted);display:flex;align-items:center;justify-content:center;min-height:80px;border-style:dashed">+ Adicionar matéria</a>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    fetch('index.php?page=api&action=report_data&type=hours_per_day&days=7').then(r=>r.json()).then(data=>{
        makeBarChart('chart-week', data.map(d=>{const[,m,dia]=d.date.split('-');return`${dia}/${m}`;}), data.map(d=>d.hours));
    });
});
</script>
