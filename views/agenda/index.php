<?php
$year  = (int)($_GET['year']  ?? date('Y'));
$month = (int)($_GET['month'] ?? date('n'));
if ($month < 1) { $month = 12; $year--; }
if ($month > 12) { $month = 1;  $year++; }
$selectedDay = $_GET['day'] ?? null;
$today       = date('Y-m-d');
$daysInMonth = (int)date('t', mktime(0,0,0,$month,1,$year));
$firstWDay   = (int)date('w', mktime(0,0,0,$month,1,$year));

$monthNames = ['','Janeiro','Fevereiro','Março','Abril','Maio','Junho',
               'Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
$dowNames   = ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'];

// Dados do mês inteiro
$stmt = db()->prepare("
    SELECT study_date, title, description, is_done, color
    FROM agenda_items
    WHERE user_id=? AND YEAR(study_date)=? AND MONTH(study_date)=?
");
$stmt->execute([uid(), $year, $month]);
$monthData = [];
foreach ($stmt->fetchAll() as $r) {
    $d = $r['study_date'];
    if (!isset($monthData[$d])) $monthData[$d] = ['tasks'=>[],'done'=>0,'notes'=>'','has_notes'=>false];
    if ($r['title']==='__notes__') {
        $monthData[$d]['has_notes'] = trim($r['description']) !== '';
        $monthData[$d]['notes']     = $r['description'];
    } else {
        $monthData[$d]['tasks'][] = $r;
        if ($r['is_done']) $monthData[$d]['done']++;
    }
}

// Dados do dia selecionado
$dayNotes=''; $dayTasks=[]; $dayStickies=[];
if ($selectedDay) {
    $q = db()->prepare("SELECT description FROM agenda_items WHERE user_id=? AND study_date=? AND title='__notes__'");
    $q->execute([uid(),$selectedDay]); $row=$q->fetch();
    $dayNotes = $row['description'] ?? '';

    $q2 = db()->prepare("SELECT * FROM agenda_items WHERE user_id=? AND study_date=? AND title!='__notes__' ORDER BY priority DESC, created_at ASC");
    $q2->execute([uid(),$selectedDay]);
    $dayTasks = $q2->fetchAll();

    $q3 = db()->prepare("SELECT * FROM stickies WHERE user_id=? AND context='agenda' AND context_id=? ORDER BY created_at DESC");
    $q3->execute([uid(), strtotime($selectedDay)]);
    $dayStickies = $q3->fetchAll();
}

$subjects  = (new SubjectModel())->getAll();
$FORM      = BASE_URL.'/index.php?page=agenda';
?>

<style>
.cal-cell {
    border-radius:8px;cursor:pointer;transition:background .12s,border-color .12s;
    border:2px solid transparent;padding:5px 3px 4px;min-height:58px;
    display:flex;flex-direction:column;align-items:center;position:relative;
    text-decoration:none;
}
.cal-cell:hover { background:var(--bg3) !important; }
.cal-cell.is-today  { border-color:var(--primary-lt) !important; }
.cal-cell.is-sel    { background:var(--primary) !important; border-color:var(--primary) !important; }

/* Tooltip */
.cal-tip {
    display:none;position:absolute;bottom:calc(100% + 6px);left:50%;
    transform:translateX(-50%);background:var(--card);border:1px solid var(--border);
    border-radius:10px;padding:9px 11px;min-width:170px;max-width:230px;
    z-index:600;box-shadow:0 4px 16px rgba(0,0,0,.35);
    pointer-events:none;font-size:12px;text-align:left;line-height:1.6;
    white-space:normal;
}
.cal-cell:hover .cal-tip { display:block; }
</style>

<div class="page-header">
    <div>
        <div class="page-title">📅 Agenda</div>
        <div class="page-subtitle"><?= $monthNames[$month] ?> <?= $year ?></div>
    </div>
    <div style="display:flex;gap:6px;align-items:center">
        <a href="<?= BASE_URL ?>/index.php?page=agenda&year=<?= $month===1?$year-1:$year ?>&month=<?= $month===1?12:$month-1 ?>" class="btn btn-ghost btn-sm">‹</a>
        <a href="<?= BASE_URL ?>/index.php?page=agenda&year=<?= date('Y') ?>&month=<?= date('n') ?>" class="btn btn-ghost btn-sm">Hoje</a>
        <a href="<?= BASE_URL ?>/index.php?page=agenda&year=<?= $month===12?$year+1:$year ?>&month=<?= $month===12?1:$month+1 ?>" class="btn btn-ghost btn-sm">›</a>
    </div>
</div>

<?php
// Aviso de pendentes hoje
if (isset($monthData[$today]) && date('Y')==$year && date('n')==$month) {
    $pend = count(array_filter($monthData[$today]['tasks'], fn($t)=>!$t['is_done']));
    if ($pend > 0):
?>
<div class="card" style="border-color:var(--primary);background:rgba(99,102,241,.07);margin-bottom:14px">
    <div style="display:flex;justify-content:space-between;align-items:center">
        <strong style="color:var(--primary-lt)">📌 Hoje: <?= $pend ?> tarefa(s) pendente(s)</strong>
        <a href="<?= BASE_URL ?>/index.php?page=agenda&year=<?= $year ?>&month=<?= $month ?>&day=<?= $today ?>"
           style="font-size:13px;color:var(--primary-lt)">Abrir →</a>
    </div>
</div>
<?php endif; } ?>

<!-- Layout: calendário + painel lado a lado -->
<div style="display:grid;grid-template-columns:<?= $selectedDay ? '1fr 420px' : '1fr' ?>;gap:20px;align-items:start">

<!-- ── CALENDÁRIO ─────────────────────────────────────────── -->
<div class="card" style="padding:16px">
    <!-- Dias da semana -->
    <div style="display:grid;grid-template-columns:repeat(7,1fr);gap:3px;margin-bottom:5px">
        <?php foreach ($dowNames as $dn): ?>
        <div style="text-align:center;font-size:11px;font-weight:600;color:var(--muted);padding:3px 0">
            <?= $dn ?>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Células dos dias -->
    <div style="display:grid;grid-template-columns:repeat(7,1fr);gap:3px">
        <?php
        for ($i=0; $i<$firstWDay; $i++) echo '<div></div>';

        for ($d=1; $d<=$daysInMonth; $d++):
            $ds      = sprintf('%04d-%02d-%02d', $year, $month, $d);
            $isToday = $ds === $today;
            $isSel   = $ds === $selectedDay;
            $data    = $monthData[$ds] ?? null;
            $tasks   = $data['tasks'] ?? [];
            $nTasks  = count($tasks);
            $nDone   = $data['done'] ?? 0;
            $allDone = $nTasks > 0 && $nDone >= $nTasks;
            $hasNotes= $data['has_notes'] ?? false;

            // Link: clica de novo no dia selecionado para fechar
            $href = $isSel
                ? BASE_URL.'/index.php?page=agenda&year='.$year.'&month='.$month
                : BASE_URL.'/index.php?page=agenda&year='.$year.'&month='.$month.'&day='.$ds;
        ?>
        <a href="<?= $href ?>"
           class="cal-cell <?= $isToday?'is-today':'' ?> <?= $isSel?'is-sel':'' ?>"
           style="background:<?= $isSel?'var(--primary)':($isToday?'var(--bg3)':'transparent') ?>">

            <!-- Número do dia -->
            <span style="font-size:13px;font-weight:<?= $isToday||$isSel?'700':'400' ?>;
                         color:<?= $isSel?'#fff':($isToday?'var(--primary-lt)':'var(--text)') ?>">
                <?= $d ?>
            </span>

            <!-- Pontos indicadores -->
            <div style="display:flex;gap:2px;margin-top:2px;justify-content:center">
                <?php if ($hasNotes): ?>
                <span style="width:4px;height:4px;border-radius:50%;background:<?= $isSel?'rgba(255,255,255,.7)':'var(--accent)' ?>"></span>
                <?php endif; ?>
                <?php if ($nTasks > 0): ?>
                <span style="width:4px;height:4px;border-radius:50%;background:<?= $isSel?'rgba(255,255,255,.7)':($allDone?'var(--green)':'var(--primary-lt)') ?>"></span>
                <?php endif; ?>
            </div>

            <!-- Contagem tarefas -->
            <?php if ($nTasks > 0): ?>
            <span style="font-size:9px;margin-top:1px;color:<?= $isSel?'rgba(255,255,255,.75)':($allDone?'var(--green)':'var(--muted)') ?>">
                <?= $nDone ?>/<?= $nTasks ?>
            </span>
            <?php endif; ?>

            <!-- Tooltip hover -->
            <?php if (($nTasks > 0 || $hasNotes) && !$isSel): ?>
            <div class="cal-tip">
                <strong style="color:var(--text);display:block;margin-bottom:4px">
                    <?= $dowNames[date('w', strtotime($ds))] ?>, <?= date('d/m', strtotime($ds)) ?>
                </strong>
                <?php if ($hasNotes && trim($data['notes'])!==''): ?>
                <div style="color:var(--accent);margin-bottom:3px">
                    📝 <?= e(mb_substr(trim($data['notes']),0,55)) ?><?= mb_strlen(trim($data['notes']))>55?'…':'' ?>
                </div>
                <?php endif; ?>
                <?php foreach (array_slice($tasks, 0, 3) as $t): ?>
                <div style="color:<?= e($t['color']) ?>;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                    <?= $t['is_done']?'✓':'○' ?> <?= e(mb_substr($t['title'],0,26)) ?>
                </div>
                <?php endforeach; ?>
                <?php if ($nTasks>3): ?>
                <div style="color:var(--muted)">+<?= $nTasks-3 ?> mais</div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </a>
        <?php endfor; ?>
    </div>

    <!-- Legenda -->
    <div style="display:flex;gap:14px;margin-top:12px;padding-top:10px;border-top:1px solid var(--border);font-size:11px;color:var(--muted);flex-wrap:wrap">
        <span><span style="display:inline-block;width:6px;height:6px;border-radius:50%;background:var(--accent);margin-right:3px;vertical-align:middle"></span>Anotações</span>
        <span><span style="display:inline-block;width:6px;height:6px;border-radius:50%;background:var(--primary-lt);margin-right:3px;vertical-align:middle"></span>Tarefas</span>
        <span><span style="display:inline-block;width:6px;height:6px;border-radius:50%;background:var(--green);margin-right:3px;vertical-align:middle"></span>Concluído</span>
        <span style="margin-left:auto">Clique num dia para abrir · clique novamente para fechar</span>
    </div>
</div>

<!-- ── PAINEL DO DIA (coluna direita) ─────────────────────── -->
<?php if ($selectedDay):
    $ts    = strtotime($selectedDay);
    $label = $dowNames[date('w',$ts)].', '.date('d',$ts).' de '.$monthNames[(int)date('n',$ts)].' '.date('Y',$ts);
?>
<div style="display:flex;flex-direction:column;gap:14px">

    <!-- Título do dia -->
    <div style="font-size:16px;font-weight:700;color:var(--primary-lt)">
        <?= $label ?>
        <?php if ($selectedDay===$today): ?>
        <span style="font-size:12px;background:var(--primary);color:#fff;padding:2px 10px;border-radius:8px;margin-left:8px;vertical-align:middle">Hoje</span>
        <?php endif; ?>
    </div>

    <!-- ANOTAÇÕES — área grande -->
    <div class="card">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px">
            <div class="card-title" style="margin:0">📝 Anotações do dia</div>
            <span id="notes-status" style="font-size:11px;color:var(--muted)">Ctrl+S salva</span>
        </div>
        <textarea id="day-notes"
            style="width:100%;min-height:220px;background:var(--bg3);border:1px solid var(--border);
                   border-radius:var(--radius);color:var(--text);font-family:var(--font);
                   font-size:14px;padding:12px 14px;outline:none;resize:vertical;line-height:1.8;
                   transition:border-color .15s;display:block"
            placeholder="O que quero estudar hoje...&#10;Metas...&#10;Lembretes..."
            onfocus="this.style.borderColor='var(--primary)'"
            onblur="this.style.borderColor='var(--border)'"
            oninput="markNotesDirty()"><?= e($dayNotes) ?></textarea>
        <div style="text-align:right;margin-top:8px">
            <button class="btn btn-primary btn-sm" onclick="saveNotes()">💾 Salvar anotações</button>
        </div>
    </div>

    <!-- TAREFAS -->
    <div class="card">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px">
            <div class="card-title" style="margin:0">✅ Tarefas</div>
            <button class="btn btn-ghost btn-sm" id="btn-add-task"
                onclick="document.getElementById('form-task').style.display='block';this.style.display='none'">
                + Adicionar
            </button>
        </div>

        <!-- Form nova tarefa -->
        <div id="form-task" style="display:none;margin-bottom:12px;padding:10px;background:var(--bg3);border-radius:var(--radius)">
            <form method="POST" action="<?= $FORM ?>">
                <input type="hidden" name="_action" value="add_task">
                <input type="hidden" name="date"  value="<?= e($selectedDay) ?>">
                <input type="hidden" name="year"  value="<?= $year ?>">
                <input type="hidden" name="month" value="<?= $month ?>">
                <input type="text" name="title" class="form-input" required
                       placeholder="Descrição da tarefa..." style="margin-bottom:8px;font-size:14px" autofocus>
                <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                    <select name="priority" class="form-select" style="width:auto;font-size:13px">
                        <option value="1">🟢 Baixa</option>
                        <option value="2" selected>🟡 Média</option>
                        <option value="3">🔴 Alta</option>
                    </select>
                    <div style="display:flex;gap:4px" id="task-colors">
                        <?php foreach (['#6366f1','#f472b6','#f59e0b','#22c55e','#ef4444','#06b6d4'] as $i=>$c): ?>
                        <label style="cursor:pointer">
                            <input type="radio" name="color" value="<?= $c ?>"
                                   <?= $i===0?'checked':'' ?> class="tc-r" style="display:none">
                            <span class="tc-s" style="display:inline-block;width:18px;height:18px;border-radius:50%;background:<?= $c ?>;border:3px solid <?= $i===0?'#fff':'transparent' ?>"></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">Salvar</button>
                    <button type="button" class="btn btn-ghost btn-sm"
                        onclick="document.getElementById('form-task').style.display='none';
                                 document.getElementById('btn-add-task').style.display=''">
                        Cancelar
                    </button>
                </div>
            </form>
        </div>

        <!-- Lista de tarefas -->
        <?php if (empty($dayTasks)): ?>
        <p style="font-size:13px;color:var(--muted);padding:4px 0">Nenhuma tarefa para este dia.</p>
        <?php endif; ?>

        <?php foreach ($dayTasks as $t): ?>
        <div style="display:flex;align-items:center;gap:8px;padding:7px 0;border-bottom:1px solid var(--border)">
            <!-- Checkbox toggle -->
            <form method="POST" action="<?= $FORM ?>" style="flex-shrink:0;margin:0">
                <input type="hidden" name="_action" value="toggle_task">
                <input type="hidden" name="id"    value="<?= $t['id'] ?>">
                <input type="hidden" name="date"  value="<?= e($selectedDay) ?>">
                <input type="hidden" name="year"  value="<?= $year ?>">
                <input type="hidden" name="month" value="<?= $month ?>">
                <button type="submit"
                    style="width:20px;height:20px;border-radius:50%;
                           border:2px solid <?= e($t['color']) ?>;
                           background:<?= $t['is_done'] ? e($t['color']) : 'transparent' ?>;
                           cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                    <?= $t['is_done'] ? '<span style="color:#fff;font-size:10px;line-height:1">✓</span>' : '' ?>
                </button>
            </form>

            <span style="flex:1;font-size:14px;<?= $t['is_done']?'text-decoration:line-through;color:var(--muted)':'' ?>">
                <?= e($t['title']) ?>
            </span>

            <span style="font-size:10px;padding:1px 7px;border-radius:6px;font-weight:600;white-space:nowrap;
                         background:<?= ['','rgba(34,197,94,.15)','rgba(245,158,11,.15)','rgba(239,68,68,.15)'][$t['priority']] ?>;
                         color:<?= ['','var(--green)','var(--accent)','var(--red)'][$t['priority']] ?>">
                <?= ['','Baixa','Média','Alta'][$t['priority']] ?>
            </span>

            <!-- Excluir -->
            <form method="POST" action="<?= $FORM ?>">
                <input type="hidden" name="_action" value="delete_task">
                <input type="hidden" name="id"    value="<?= $t['id'] ?>">
                <input type="hidden" name="date"  value="<?= e($selectedDay) ?>">
                <input type="hidden" name="year"  value="<?= $year ?>">
                <input type="hidden" name="month" value="<?= $month ?>">
                <button type="submit"
                    style="background:none;border:none;color:var(--muted);cursor:pointer;font-size:13px;padding:2px 4px"
                    onclick="return confirm('Excluir tarefa?')">✕</button>
            </form>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- POST-ITS -->
    <div class="card">
        <div class="card-title" style="margin-bottom:10px">📌 Post-its do dia</div>

        <!-- Adicionar post-it -->
        <form method="POST" action="<?= $FORM ?>" style="margin-bottom:12px">
            <input type="hidden" name="_action" value="add_sticky">
            <input type="hidden" name="date"  value="<?= e($selectedDay) ?>">
            <input type="hidden" name="year"  value="<?= $year ?>">
            <input type="hidden" name="month" value="<?= $month ?>">
            <textarea name="content" class="form-textarea"
                placeholder="Lembrete rápido..."
                style="min-height:52px;margin-bottom:8px;font-size:13px"></textarea>
            <div style="display:flex;gap:5px;align-items:center" id="sticky-colors">
                <?php foreach (['#fbbf24','#f472b6','#34d399','#60a5fa','#a78bfa','#fb923c'] as $i=>$c): ?>
                <label style="cursor:pointer">
                    <input type="radio" name="color" value="<?= $c ?>"
                           <?= $i===0?'checked':'' ?> class="sc-r" style="display:none">
                    <span class="sc-s" style="display:inline-block;width:18px;height:18px;border-radius:50%;background:<?= $c ?>;border:3px solid <?= $i===0?'#fff':'transparent' ?>"></span>
                </label>
                <?php endforeach; ?>
                <button type="submit" class="btn btn-primary btn-sm" style="margin-left:auto">+ Add</button>
            </div>
        </form>

        <!-- Post-its existentes -->
        <?php if (empty($dayStickies)): ?>
        <p style="font-size:12px;color:var(--muted)">Nenhum post-it ainda.</p>
        <?php endif; ?>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
            <?php foreach ($dayStickies as $s): ?>
            <div style="background:<?= e($s['color']) ?>;border-radius:9px;padding:10px 10px 22px;position:relative;box-shadow:1px 2px 8px rgba(0,0,0,.2)">
                <p style="font-size:12px;color:#1a1a1a;line-height:1.5;word-break:break-word">
                    <?= nl2br(e($s['content'])) ?>
                </p>
                <form method="POST" action="<?= $FORM ?>" style="position:absolute;bottom:4px;right:5px">
                    <input type="hidden" name="_action" value="del_sticky">
                    <input type="hidden" name="id"    value="<?= $s['id'] ?>">
                    <input type="hidden" name="date"  value="<?= e($selectedDay) ?>">
                    <input type="hidden" name="year"  value="<?= $year ?>">
                    <input type="hidden" name="month" value="<?= $month ?>">
                    <button type="submit"
                        style="background:rgba(0,0,0,.15);border:none;border-radius:4px;padding:1px 6px;cursor:pointer;font-size:11px;color:#333"
                        onclick="return confirm('Remover post-it?')">✕</button>
                </form>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

</div><!-- fim painel direito -->
<?php else: ?>
<!-- Nenhum dia selecionado -->
<div class="card" style="display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:280px;color:var(--muted);text-align:center">
    <div style="font-size:48px;margin-bottom:14px">📅</div>
    <div style="font-size:15px;font-weight:600;color:var(--text);margin-bottom:6px">Selecione um dia</div>
    <p style="font-size:13px">Clique em qualquer dia no calendário para ver e editar anotações, tarefas e post-its.</p>
</div>
<?php endif; ?>

</div><!-- fim grid layout -->

<script>
// ── Color pickers ────────────────────────────────────────────
document.querySelectorAll('.tc-r').forEach(r => {
    r.addEventListener('change', () => {
        document.querySelectorAll('.tc-s').forEach(s => s.style.borderColor='transparent');
        r.nextElementSibling.style.borderColor='#fff';
    });
    if (r.checked) r.nextElementSibling.style.borderColor='#fff';
});
document.querySelectorAll('.sc-r').forEach(r => {
    r.addEventListener('change', () => {
        document.querySelectorAll('.sc-s').forEach(s => s.style.borderColor='transparent');
        r.nextElementSibling.style.borderColor='#fff';
    });
    if (r.checked) r.nextElementSibling.style.borderColor='#fff';
});

// ── Auto-save das anotações ──────────────────────────────────
let notesDirty = false, notesTimer = null;
function markNotesDirty() {
    notesDirty = true;
    setNotesStatus('Não salvo...', 'var(--accent)');
    clearTimeout(notesTimer);
    notesTimer = setTimeout(saveNotes, 3000);
}
function setNotesStatus(txt, color) {
    const el = document.getElementById('notes-status');
    if (el) { el.textContent = txt; el.style.color = color; }
}
async function saveNotes() {
    const ta = document.getElementById('day-notes');
    if (!ta) return;
    try {
        const res = await fetch('<?= $FORM ?>', {
            method: 'POST',
            headers: {'Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest'},
            body: new URLSearchParams({
                _action: 'save_notes',
                date:    '<?= e($selectedDay ?? '') ?>',
                year:    '<?= $year ?>',
                month:   '<?= $month ?>',
                notes:   ta.value
            })
        });
        const data = await res.json();
        setNotesStatus('Salvo às ' + data.saved, 'var(--green)');
        notesDirty = false;
    } catch(e) {
        setNotesStatus('Erro ao salvar', 'var(--red)');
    }
}
document.addEventListener('keydown', e => {
    if ((e.ctrlKey||e.metaKey) && e.key==='s') { e.preventDefault(); saveNotes(); }
});
</script>