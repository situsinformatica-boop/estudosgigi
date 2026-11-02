<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['_action'] ?? '';
    if ($act === 'create_goal') {
        db()->prepare("INSERT INTO goals (user_id,title,description,subject_id,type,target_minutes,start_date,end_date) VALUES (?,?,?,?,?,?,?,?)")
            ->execute([uid(),$_POST['title'],$_POST['description']??'',$_POST['subject_id']?:null,$_POST['type'],(int)($_POST['target_minutes']??0),$_POST['start_date'],$_POST['end_date']]);
    }
    if ($act === 'complete_goal') {
        db()->prepare("UPDATE goals SET is_completed=1 WHERE id=? AND user_id=?")->execute([(int)$_POST['goal_id'],uid()]);
    }
    header('Location: ' . BASE_URL . '/index.php?page=goals'); exit;
}

$subjects = (new SubjectModel())->getAll();
$goals = db()->prepare("SELECT g.*, s.name AS subject_name, s.color FROM goals g LEFT JOIN subjects s ON s.id=g.subject_id WHERE g.user_id=? ORDER BY g.is_completed ASC, g.end_date ASC");
$goals->execute([uid()]); $goals = $goals->fetchAll();

foreach ($goals as &$g) {
    if ($g['target_minutes'] > 0) {
        $stmt = db()->prepare("SELECT COALESCE(SUM(duration_min),0) FROM study_sessions WHERE user_id=? AND subject_id=COALESCE(?,subject_id) AND started_at BETWEEN ? AND ? AND ended_at IS NOT NULL");
        $stmt->execute([uid(),$g['subject_id'],$g['start_date'],$g['end_date'].' 23:59:59']);
        $g['achieved_minutes'] = (int)$stmt->fetchColumn();
        $g['percent'] = min(100,(int)(($g['achieved_minutes']/$g['target_minutes'])*100));
    } else { $g['achieved_minutes']=0; $g['percent']=$g['is_completed']?100:0; }
}
unset($g);
?>
<div class="page-header">
    <div><div class="page-title">🎯 Metas</div><div class="page-subtitle"><?= count(array_filter($goals,fn($g)=>!$g['is_completed'])) ?> metas ativas</div></div>
    <button class="btn btn-primary" onclick="toggleEl('form-goal')">+ Nova Meta</button>
</div>

<div id="form-goal" style="display:none;margin-bottom:20px">
    <div class="card" style="max-width:580px">
        <div class="card-title">Nova Meta</div>
        <form method="POST" action="">
            <input type="hidden" name="_action" value="create_goal">
            <div class="form-group"><label class="form-label">Título *</label><input type="text" name="title" class="form-input" required placeholder="Ex: Estudar 10h de Física esta semana"></div>
            <div class="form-group"><label class="form-label">Descrição</label><input type="text" name="description" class="form-input" placeholder="Detalhes..."></div>
            <div class="grid-2">
                <div class="form-group"><label class="form-label">Matéria (opcional)</label>
                    <select name="subject_id" class="form-select"><option value="">Todas</option>
                    <?php foreach($subjects as $s): ?><option value="<?= $s['id'] ?>"><?= e($s['name']) ?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label class="form-label">Tipo</label>
                    <select name="type" class="form-select"><option value="weekly">Semanal</option><option value="daily">Diária</option><option value="monthly">Mensal</option><option value="custom">Personalizado</option></select></div>
            </div>
            <div class="form-group"><label class="form-label">Meta em minutos (ex: 600 = 10h)</label><input type="number" name="target_minutes" class="form-input" placeholder="0" min="0"></div>
            <div class="grid-2">
                <div class="form-group"><label class="form-label">Data início *</label><input type="date" name="start_date" class="form-input" value="<?= date('Y-m-d') ?>" required></div>
                <div class="form-group"><label class="form-label">Data fim *</label><input type="date" name="end_date" class="form-input" value="<?= date('Y-m-d',strtotime('+7 days')) ?>" required></div>
            </div>
            <div style="display:flex;gap:10px">
                <button type="submit" class="btn btn-primary">Criar meta</button>
                <button type="button" class="btn btn-ghost" onclick="toggleEl('form-goal')">Cancelar</button>
            </div>
        </form>
    </div>
</div>

<?php if(empty($goals)): ?>
<div class="card" style="text-align:center;padding:48px;color:var(--muted)"><div style="font-size:40px;margin-bottom:12px">🎯</div>Nenhuma meta ainda. Defina seus objetivos!</div>
<?php endif; ?>

<div style="display:flex;flex-direction:column;gap:12px;max-width:660px">
<?php foreach($goals as $g): ?>
<div class="card" style="<?= $g['is_completed']?'opacity:.6':'' ?>;border-left:3px solid <?= $g['color']??'var(--primary)' ?>">
    <div style="display:flex;align-items:start;gap:12px">
        <div style="flex:1">
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;flex-wrap:wrap">
                <span style="font-weight:700;font-size:15px"><?= e($g['title']) ?></span>
                <span style="font-size:11px;background:var(--bg3);padding:2px 8px;border-radius:10px;color:var(--muted)"><?= ['daily'=>'Diária','weekly'=>'Semanal','monthly'=>'Mensal','custom'=>'Custom'][$g['type']] ?></span>
                <?php if($g['is_completed']): ?><span style="font-size:11px;background:rgba(34,197,94,.2);color:var(--green);padding:2px 8px;border-radius:10px">✓ Concluída</span><?php endif; ?>
            </div>
            <?php if($g['subject_name']): ?><div style="font-size:12px;color:var(--muted);margin-bottom:4px">📖 <?= e($g['subject_name']) ?></div><?php endif; ?>
            <div style="font-size:12px;color:var(--muted);margin-bottom:8px"><?= formatDate($g['start_date']) ?> → <?= formatDate($g['end_date']) ?><?php if($g['target_minutes']): ?> · <?= minutesToReadable((int)$g['achieved_minutes']) ?>/<?= minutesToReadable((int)$g['target_minutes']) ?><?php endif; ?></div>
            <?php if($g['target_minutes']): ?>
            <div class="progress-wrap"><div class="progress-bar" style="width:<?= $g['percent'] ?>%;background:<?= progressColor($g['percent']) ?>"></div></div>
            <div style="font-size:11px;color:var(--muted);margin-top:4px;font-family:var(--mono)"><?= $g['percent'] ?>%</div>
            <?php endif; ?>
        </div>
        <?php if(!$g['is_completed']): ?>
        <form method="POST" action=""><input type="hidden" name="_action" value="complete_goal"><input type="hidden" name="goal_id" value="<?= $g['id'] ?>">
        <button type="submit" class="btn btn-ghost btn-sm" title="Concluir">✓</button></form>
        <?php endif; ?>
    </div>
</div>
<?php endforeach; ?>
</div>
<script>function toggleEl(id){const el=document.getElementById(id);el.style.display=el.style.display==='none'?'block':'none';}</script>
