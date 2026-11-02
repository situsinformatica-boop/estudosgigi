<?php
$subjectModel = new SubjectModel();
$topicModel   = new TopicModel();
$reviewModel  = new ReviewModel();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['_action'] ?? '';
    if ($act === 'create_subject') {
        $subjectModel->create([
            'name'                => trim($_POST['name']),
            'description'         => $_POST['description'] ?? '',
            'color'               => $_POST['color'] ?? '#6366f1',
            'icon'                => 'book',
            'weekly_goal_minutes' => (int)($_POST['weekly_goal_minutes'] ?? 300),
            'priority'            => (int)($_POST['priority'] ?? 2),
        ]);
        header('Location: ' . BASE_URL . '/index.php?page=subjects'); exit;
    }
    if ($act === 'create_topic') {
        $topicModel->create([
            'subject_id'  => (int)$_POST['subject_id'],
            'name'        => trim($_POST['name']),
            'description' => $_POST['description'] ?? '',
            'difficulty'  => (int)($_POST['difficulty'] ?? 2),
            'order_index' => 0,
        ]);
        header('Location: ' . BASE_URL . '/index.php?page=subjects&id=' . (int)$_POST['subject_id']); exit;
    }
    if ($act === 'delete_topic') {
        $topicModel->delete((int)$_POST['topic_id']);
        header('Location: ' . BASE_URL . '/index.php?page=subjects&id=' . (int)$_POST['subject_id']); exit;
    }
    if ($act === 'delete_subject') {
        $subjectModel->delete((int)$_POST['subject_id']);
        header('Location: ' . BASE_URL . '/index.php?page=subjects'); exit;
    }
}

$selectedId = isset($_GET['id']) ? (int)$_GET['id'] : null;
$subjects   = $subjectModel->getAll();
$selected   = $selectedId ? $subjectModel->getById($selectedId) : null;
$topics     = $selected   ? $topicModel->getBySubject($selectedId) : [];
?>

<div class="page-header">
    <div><div class="page-title">📖 Matérias</div><div class="page-subtitle">Gerencie suas matérias e tópicos</div></div>
    <button class="btn btn-primary" onclick="toggleEl('form-subject')">+ Nova Matéria</button>
</div>

<div id="form-subject" style="display:none;margin-bottom:20px">
    <div class="card" style="max-width:580px">
        <div class="card-title">Nova Matéria</div>
        <form method="POST" action="">
            <input type="hidden" name="_action" value="create_subject">
            <div class="grid-2">
                <div class="form-group"><label class="form-label">Nome *</label><input type="text" name="name" class="form-input" required placeholder="Ex: Matemática"></div>
                <div class="form-group"><label class="form-label">Cor</label><input type="color" name="color" class="form-input" value="#6366f1" style="height:42px;padding:4px"></div>
            </div>
            <div class="form-group"><label class="form-label">Descrição</label><textarea name="description" class="form-textarea" placeholder="Opcional..."></textarea></div>
            <div class="grid-2">
                <div class="form-group"><label class="form-label">Meta semanal (min)</label><input type="number" name="weekly_goal_minutes" class="form-input" value="300" min="0"></div>
                <div class="form-group"><label class="form-label">Prioridade</label>
                    <select name="priority" class="form-select"><option value="1">Baixa</option><option value="2" selected>Média</option><option value="3">Alta</option></select></div>
            </div>
            <div style="display:flex;gap:10px">
                <button type="submit" class="btn btn-primary">Criar</button>
                <button type="button" class="btn btn-ghost" onclick="toggleEl('form-subject')">Cancelar</button>
            </div>
        </form>
    </div>
</div>

<div style="display:grid;grid-template-columns:250px 1fr;gap:20px">
    <div>
        <div class="card-title" style="margin-bottom:10px">Suas matérias</div>
        <?php foreach($subjects as $s): ?>
        <a href="<?= BASE_URL ?>/index.php?page=subjects&id=<?= $s['id'] ?>" style="text-decoration:none">
            <div class="card" style="margin-bottom:10px;border-color:<?= $selectedId==$s['id']?e($s['color']):'var(--border)' ?>;transition:border-color .15s">
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px">
                    <span style="width:10px;height:10px;border-radius:50%;background:<?= e($s['color']) ?>;flex-shrink:0"></span>
                    <span style="font-weight:700;font-size:14px;color:var(--text)"><?= e($s['name']) ?></span>
                </div>
                <div style="font-size:12px;color:var(--muted)"><?= $s['topic_count'] ?> tópicos · <?= minutesToReadable((int)$s['minutes_this_week']) ?>/sem</div>
            </div>
        </a>
        <?php endforeach; ?>
        <?php if(empty($subjects)): ?><p style="color:var(--muted);font-size:14px">Nenhuma matéria ainda.</p><?php endif; ?>
    </div>

    <div>
        <?php if($selected): ?>
        <div class="card" style="margin-bottom:16px">
            <div style="display:flex;justify-content:space-between;align-items:start">
                <div>
                    <div style="display:flex;align-items:center;gap:10px;margin-bottom:4px">
                        <span style="width:12px;height:12px;border-radius:50%;background:<?= e($selected['color']) ?>"></span>
                        <span style="font-size:20px;font-weight:700"><?= e($selected['name']) ?></span>
                    </div>
                    <?php if($selected['description']): ?><p style="color:var(--muted);font-size:14px"><?= e($selected['description']) ?></p><?php endif; ?>
                </div>
                <form method="POST" action="" onsubmit="return confirm('Excluir esta matéria e todos os seus tópicos?')">
                    <input type="hidden" name="_action" value="delete_subject">
                    <input type="hidden" name="subject_id" value="<?= $selected['id'] ?>">
                    <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--red)">Excluir matéria</button>
                </form>
            </div>
        </div>

        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
            <div class="card-title">Tópicos (<?= count($topics) ?>)</div>
            <button class="btn btn-ghost btn-sm" onclick="toggleEl('form-topic')">+ Adicionar tópico</button>
        </div>

        <div id="form-topic" style="display:none;margin-bottom:16px">
            <div class="card">
                <div class="card-title">Novo Tópico</div>
                <form method="POST" action="">
                    <input type="hidden" name="_action" value="create_topic">
                    <input type="hidden" name="subject_id" value="<?= $selected['id'] ?>">
                    <div class="grid-2">
                        <div class="form-group"><label class="form-label">Nome *</label><input type="text" name="name" class="form-input" required placeholder="Ex: Equações de 2º grau"></div>
                        <div class="form-group"><label class="form-label">Dificuldade</label>
                            <select name="difficulty" class="form-select"><option value="1">Fácil</option><option value="2" selected>Médio</option><option value="3">Difícil</option></select></div>
                    </div>
                    <div class="form-group"><label class="form-label">Descrição</label><input type="text" name="description" class="form-input" placeholder="O que você vai estudar..."></div>
                    <div style="display:flex;gap:10px">
                        <button type="submit" class="btn btn-primary btn-sm">Criar tópico</button>
                        <button type="button" class="btn btn-ghost btn-sm" onclick="toggleEl('form-topic')">Cancelar</button>
                    </div>
                </form>
            </div>
        </div>

        <?php if(empty($topics)): ?>
        <div class="card" style="text-align:center;padding:32px;color:var(--muted)">Nenhum tópico ainda. Adicione os assuntos que vai estudar!</div>
        <?php endif; ?>

        <?php foreach($topics as $t): ?>
        <div class="card" style="margin-bottom:10px">
            <div style="display:flex;align-items:start;gap:12px">
                <div style="flex:1">
                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;flex-wrap:wrap">
                        <span style="font-weight:600;font-size:15px"><?= e($t['name']) ?></span>
                        <span style="font-size:11px;color:var(--muted)"><?= ['','⭐','⭐⭐','⭐⭐⭐'][(int)$t['difficulty']] ?></span>
                        <?php if($t['is_completed']): ?><span style="font-size:11px;background:rgba(34,197,94,.2);color:var(--green);padding:1px 8px;border-radius:10px">✓ Concluído</span><?php endif; ?>
                        <?php if($t['next_review']): ?><span style="font-size:11px;color:var(--primary-lt);margin-left:auto">🔄 Revisão: <?= formatDate($t['next_review']) ?></span><?php endif; ?>
                    </div>
                    <?php if($t['description']): ?><div style="font-size:13px;color:var(--muted);margin-bottom:8px"><?= e($t['description']) ?></div><?php endif; ?>
                    <div style="display:flex;align-items:center;gap:10px">
                        <input type="range" min="0" max="100" value="<?= $t['progress'] ?>" style="flex:1"
                            oninput="updateProgress(this,<?= $t['id'] ?>,document.getElementById('prog-<?= $t['id'] ?>'))">
                        <span id="prog-<?= $t['id'] ?>" style="font-size:13px;font-family:var(--mono);min-width:36px;color:<?= progressColor((int)$t['progress']) ?>"><?= $t['progress'] ?>%</span>
                    </div>
                </div>
                <form method="POST" action="" onsubmit="return confirm('Excluir este tópico?')">
                    <input type="hidden" name="_action" value="delete_topic">
                    <input type="hidden" name="topic_id" value="<?= $t['id'] ?>">
                    <input type="hidden" name="subject_id" value="<?= $selected['id'] ?>">
                    <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--red);padding:4px 8px">✕</button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>

        <?php else: ?>
        <div class="card" style="text-align:center;padding:48px;color:var(--muted)">
            <div style="font-size:40px;margin-bottom:12px">📖</div>
            Selecione uma matéria para ver os tópicos
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function toggleEl(id){const el=document.getElementById(id);el.style.display=el.style.display==='none'?'block':'none';}
let pt={};
function updateProgress(input,id,label){
    label.textContent=input.value+'%';
    label.style.color=input.value>=70?'#22c55e':input.value>=40?'#f59e0b':'#ef4444';
    clearTimeout(pt[id]);
    pt[id]=setTimeout(async()=>{
        await api('topic_progress',{topic_id:id,progress:input.value},'POST');
        toast(`Progresso: ${input.value}%`,'success');
    },600);
}
</script>
