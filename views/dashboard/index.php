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
$stmtA = db()->prepare("SELECT * FROM agenda_items WHERE user_id=? AND study_date=? AND title!='__notes__' ORDER BY priority DESC, start_time ASC");
$stmtA->execute([uid(), $today]); $agendaToday = $stmtA->fetchAll();
$pendingAgenda = array_filter($agendaToday, fn($i) => !$i['is_done']);
?>

<?php
$days = ['domingo','segunda-feira','terça-feira','quarta-feira','quinta-feira','sexta-feira','sábado'];
$months = ['janeiro','fevereiro','março','abril','maio','junho','julho','agosto','setembro','outubro','novembro','dezembro'];
?>
<div class="page-header">
    <div><h1 class="page-title">Hoje</h1><p class="page-subtitle"><?= $days[(int)date('w')] ?>, <?= date('j') ?> de <?= $months[(int)date('n')-1] ?></p></div>
    <a class="btn btn-ghost" href="<?= BASE_URL ?>/index.php?page=agenda">Organizar meu dia</a>
</div>
<section class="study-hero" aria-labelledby="hero-title">
    <div>
        <span class="eyebrow">Um passo de cada vez</span>
        <h2 id="hero-title"><?= empty($subjects) ? 'Seu próximo passo começa aqui.' : 'Abra espaço para aprender.' ?></h2>
        <p><?= empty($subjects) ? 'Adicione sua primeira matéria para organizar os conteúdos e começar a estudar.' : 'Escolha uma matéria, reserve alguns minutos e concentre-se no que importa agora.' ?></p>
        <?php if(empty($subjects)): ?>
        <a class="btn btn-primary" href="<?= BASE_URL ?>/index.php?page=subjects">Adicionar primeira matéria</a>
        <?php else: ?>
        <form action="<?= BASE_URL ?>/index.php" method="get" class="quick-study">
            <input type="hidden" name="page" value="timer">
            <label class="sr-only" for="quick-subject">Matéria para estudar</label>
            <select class="form-select" id="quick-subject" name="subject_id">
                <?php foreach($subjects as $s): ?><option value="<?= (int)$s['id'] ?>"><?= e($s['name']) ?></option><?php endforeach; ?>
            </select>
            <button class="btn btn-primary" type="submit">Começar a estudar →</button>
        </form>
        <?php endif; ?>
    </div>
    <div class="daily-progress">
        <span class="eyebrow">Seu ritmo de hoje</span>
        <strong><?= minutesToReadable($minutesToday) ?></strong>
        <span>de <?= minutesToReadable($dailyGoal) ?> planejados</span>
        <progress max="100" value="<?= $todayPct ?>" aria-label="Progresso da meta diária"><?= $todayPct ?>%</progress>
        <span><?= $todayPct >= 100 ? 'Meta alcançada. Bom trabalho!' : 'Cada minuto conta.' ?></span>
    </div>
</section>

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
<?php else: ?>
<div class="card empty-day"><strong>Seu dia está livre para planejar.</strong><span>Organize o próximo conteúdo na agenda.</span><a class="btn btn-ghost" href="<?= BASE_URL ?>/index.php?page=agenda">Planejar estudo</a></div>
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
        <div class="stat-sub"><a href="<?= BASE_URL ?>/index.php?page=subjects" style="color:var(--primary-lt)">Ver tópicos →</a></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Sessões (7 dias)</div>
        <div class="stat-value stat-green"><?= $summary['total_sessions'] ?></div>
        <div class="stat-sub"><?= minutesToReadable((int)$summary['total_minutes']) ?> de estudo</div>
    </div>
</div>

<div class="section-heading"><h2>Continue aprendendo</h2><a href="<?= BASE_URL ?>/index.php?page=reports">Ver minha evolução →</a></div>
<div class="grid-3 resource-grid">
    <a class="card resource-card" href="<?= BASE_URL ?>/index.php?page=notebook"><span class="eyebrow">Organizar</span><h3>Caderno digital</h3><p>Reúna suas anotações e ideias.</p><span aria-hidden="true">→</span></a>
    <a class="card resource-card" href="<?= BASE_URL ?>/index.php?page=flashcards"><span class="eyebrow">Memorizar</span><h3>Flashcards</h3><p>Relembre conceitos em pequenas sessões.</p><span aria-hidden="true">→</span></a>
    <a class="card resource-card" href="<?= BASE_URL ?>/index.php?page=review"><span class="eyebrow">Praticar</span><h3>Simulados e quiz</h3><p>Teste o que aprendeu e identifique dúvidas.</p><span aria-hidden="true">→</span></a>
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
